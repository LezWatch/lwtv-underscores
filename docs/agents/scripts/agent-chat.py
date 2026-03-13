#!/usr/bin/env python3
import json
import os
import subprocess
import re
import time

# Load the catalog once into memory
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CATALOG_PATH = os.path.join(BASE_DIR, 'data', 'ai-catalog.json')

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
            key = k.strip().lower()
            val = v.strip().lower().split(' ')[0].strip('.,')

            # Alias Mapping for "Underrated"
            if val == 'underrated': val = 'meh'

            # Alias Mapping for "Limit"
            if key in ['limit', 'underrated', 'count', 'shows']:
                params['limit'] = val
            else:
                params[key] = val
    return params

def search_catalog(params):
    if not os.path.exists(CATALOG_PATH):
        print(f"!! Catalog not found at {CATALOG_PATH}")
        return []

    with open(CATALOG_PATH, 'r') as f:
        catalog = json.load(f)

    # EUROPEAN MAPPING
    eu_countries = ['austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czechia', 'denmark', 'estonia', 'finland', 'france', 'germany', 'greece', 'hungary', 'ireland', 'italy', 'latvia', 'lithuania', 'luxembourg', 'malta', 'netherlands', 'poland', 'portugal', 'romania', 'slovakia', 'slovenia', 'spain', 'sweden']
    target_country = params.get('country')
    target_genre = params.get('genre')
    target_trope = params.get('trope')

    matches = []
    for show_id, show in catalog.items():
        # 1. Country Filter
        show_countries = show.get('country', [])
        if target_country:
            if target_country in ['us', 'usa', 'america']:
                target_country = 'usa'

            if target_country == 'eu':
                if not any(c in eu_countries for c in show_countries):
                    continue
            elif target_country not in show_countries:
                continue

        # 2. Genre Filter
        if target_genre and target_genre not in show.get('genres', []):
            continue

        # 3. Trope Filter
        if target_trope and target_trope not in show.get('tropes', []):
            continue

        # 4. Smart Worth-It Filter (Underrated = Not 'yes')
        worthit_val = show.get('worthit', '').lower()
        if params.get('worthit') == 'no' or params.get('worthit') == 'meh':
            if worthit_val == 'yes':
                continue

        # 5. Score Filter
        search_score = int(params.get('score', 100))
        if show.get('score', 100) > search_score:
            continue

        # 6. Match On Air status
        if params.get('on_air'):
            # Convert 'yes'/'no' string to match your JSON data
            target_status = params['on_air'].lower()
            if show.get('on_air', '').lower() != target_status:
                continue

        matches.append(show)
        if len(matches) >= int(params.get('limit', 3)):
            break

    return matches

def run_chat_cycle(user_input):
    # PASS 1: Extraction
    extraction_start = time.time()
    print(f"-> Extracting intent for: '{user_input}'")
    extraction_output = call_ollama(f"STEP 1: EXTRACTION. User says: {user_input}")

    search_params = parse_search_action(extraction_output)

    if not search_params:
        print("!! AI didn't return a SEARCH_ACTION. Here is the response:")
        print(extraction_output)
        return
    duration = time.time() - extraction_start
    print(f"   [Extraction took {duration:.2f}s]")

    # LOCAL SEARCH
    search_start = time.time()
    print(f"-> Searching catalog for: {search_params}")
    results = search_catalog(search_params)
    search_duration = time.time() - search_start
    print(f"   [Search took {search_duration:.2f}s]")
    if not results:
        print("AI: I don't have a record of a show like that yet in our database.")
        return

    # PASS 2: Presentation
    presentation_start = time.time()
    print(f"-> Found {len(results)} matches. Generating Curated Presentation...")
    # 1. Get the Curator's Note first
    note_prompt = f"User asked for: {user_input}. I found {len(results)} shows. Write a one-sentence Curator's Note for the top of the list."
    curator_note = call_ollama(note_prompt)

    print("\n" + "="*50)
    print(curator_note + "\n")

    # 2. Format each show one by one
    for s in results:
        # Pre-format the data in Python so the AI can't mess up the math
        years = f"{s.get('start_year', '????')}"
        if s.get('on_air') == 'yes': years += " - current"
        elif s.get('end_year'): years += f" - {s['end_year']}"

        deaths = f" ({s['dead']} are dead)" if int(s.get('dead', 0)) > 0 else ""

        # Give the AI a tiny, focused task for THIS show only
        show_prompt = (
            f"DATA:\n"
            f"TITLE: {s['title']}\nYEARS: {years}\nSTATUS: {status}\nSCORE: {s['score']}\n"
            f"INSIGHT: {s.get('curator_say', '')}\nEXCERPT: {s.get('excerpt', '')}\n"
            f"PEOPLE: {s.get('characters', '0')} queer characters{deaths}\n"
            f"TROPES: {', '.join(s.get('tropes', []))}\n\n"
            f"TASK: Write a 2-sentence recommendation using only the DATA above.\n"
            f"FORMAT:\n"
            f"**Title** (Years) (Score: X) - **Status**\n"
            f"Why this fits: [1 sentence]\n"
            f"Description: [1 sentence]"
        )

        show_output = call_ollama(show_prompt)
        print(show_output)
        print("-" * 20)

    print("="*50 + "\n")

    presentation_duration = time.time() - presentation_start
    print(f"   [Presentation took {duration:.2f}s]")

if __name__ == "__main__":
    warm_up() # This ensures the model is loaded before you even type your query
    query = input("Ask the LezWatch AI: ")
    run_chat_cycle(query)
