#!/usr/bin/env python3
import json
import os
import subprocess
import re
import time

# Import specific modules
from agent_chat_shows import search_catalog_shows, format_show_result
from agent_chat_characters import search_catalog_characters, format_character_result

# Load the catalogs once into memory
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CATALOG_PATHS = {
    'shows': os.path.join(BASE_DIR, 'data', 'ai-catalog-shows.json'),
    'characters': os.path.join(BASE_DIR, 'data', 'ai-catalog-characters.json'),
}

def warm_up():
    start = time.time()
    print("-> Warming up the engine...")
    # Sends an empty prompt just to ensure the model is in RAM
    subprocess.run(['ollama', 'run', 'lezwatch-bot', ''], capture_output=True)
    duration = time.time() - start
    print(f"   [Warming up took {duration:.2f}s]")

def call_ollama(prompt, stop=None):
    """Calls Ollama and returns the text output."""
    start = time.time()
    # Build the command
    cmd = ['ollama', 'run', 'lezwatch-bot', prompt]

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        duration = time.time() - start
        print(f"   [AI took {duration:.2f}s]") # This shows exactly how long it hung
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        return f"Error calling Ollama: {e}"

def parse_search_action(ai_output):
    """Parses key:value pairs even if the AI forgets the SEARCH_ACTION prefix."""
    # If the prefix is there, strip it. If not, just use the whole output.
    if "SEARCH_ACTION:" in ai_output:
        command_text = ai_output.split("SEARCH_ACTION:")[1].strip()
    else:
        command_text = ai_output.strip()

    params = {}
    # Split by comma or newline (in case the AI lists them)
    pairs = re.split(r'[,\n]', command_text)

    for pair in pairs:
        if ':' in pair:
            k, v = pair.split(':', 1)
            # Strip leading dashes, spaces, and other common extraction noise
            key = k.strip().lower().lstrip('- ').strip()
            val = v.strip().lower().split(' ')[0].strip('., ')

            # Ignore comments or empty keys/values
            if not key or not val or key.startswith('#'):
                continue

            # Alias Mapping for Values
            if val in ['underrated', 'maybe']: val = 'meh'
            if val in ['good', 'great', 'awesome', 'best']:
                val = 'yes'
                if key == 'worthit':
                    params['score'] = '65' # Default threshold for "good"
            if val in ['bad', 'terrible', 'avoid']: val = 'no'

            # Handle score ranges (e.g., "0-100" -> "100")
            if key == 'score' and '-' in val:
                val = val.split('-')[-1].strip()

            # Map common geography errors from LLM (station -> country)
            if key == 'station' and val in ['canada', 'uk', 'usa', 'france', 'germany', 'australia', 'eu']:
                params['country'] = val
                continue

            # Alias Mapping for "Limit"
            if key in ['limit', 'count', 'shows', 'characters']:
                params['limit'] = val
            else:
                params[key] = val
    return params

def run_chat_cycle(user_input):
    # --- PRIVACY SHORT-CIRCUIT ---
    # We are not allowed to discuss actors.
    # EXCEPTION: We allow representation queries about "queer actors" or "played by"
    is_rep_query = any(x in user_input.lower() for x in ["queer actor", "played by", "queer irl"])
    if "actor" in user_input.lower() and not is_rep_query:
        print("AI: I am not allowed to discuss actors or provide information about them due to privacy and safety policies. I can only help you find shows and characters.")
        return

    # PASS 1: Extraction
    extraction_start = time.time()
    print(f"-> Extracting intent for: '{user_input}'")
    extraction_output = call_ollama(f"STEP 1: EXTRACTION. User says: {user_input}")

    search_params = parse_search_action(extraction_output)

    if not search_params:
        print("!! AI didn't return a SEARCH_ACTION. Here is the response:")
        print(extraction_output)
        return

    search_type = search_params.get('type', 'shows')
    extraction_duration = time.time() - extraction_start
    print(f"   [Extraction took {extraction_duration:.2f}s]")

    # LOCAL SEARCH
    search_start = time.time()
    print(f"-> Searching {search_type} catalog for: {search_params}")

    catalog_path = CATALOG_PATHS.get(search_type)
    if search_type == 'shows':
        results = search_catalog_shows(search_params, catalog_path)
    else:
        results = search_catalog_characters(search_params, catalog_path)

    search_duration = time.time() - search_start
    print(f"   [Search took {search_duration:.2f}s]")

    if not results:
        print(f"AI: I don't have a record of any {search_type} like that yet in our database.")
        return

    # PASS 2: Presentation
    presentation_start = time.time()
    print(f"-> Found {len(results)} matches. Generating Curated Presentation...")

    # 1. Get the Curator's Note first
    note_prompt = f"User asked for: {user_input}. I found {len(results)} {search_type}. Write a one-sentence Curator's Note for the top of the list."
    curator_note = call_ollama(note_prompt)

    print("\n" + "="*50)
    print(curator_note + "\n")

    # 2. Format each result one by one
    for s in results:
        if search_type == 'shows':
            format_show_result(s, call_ollama)
        else:
            format_character_result(s, call_ollama)

    print("="*50 + "\n")

    presentation_duration = time.time() - presentation_start
    print(f"   [Presentation took {presentation_duration:.2f}s]")

if __name__ == "__main__":
    warm_up() # This ensures the model is loaded before you even type your query
    query = input("Ask the LezWatch AI: ")
    run_chat_cycle(query)
