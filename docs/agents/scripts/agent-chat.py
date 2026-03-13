#!/usr/bin/env python3
import json
import os
import subprocess
import re

# Load the catalog once into memory
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CATALOG_PATH = os.path.join(BASE_DIR, 'data', 'ai-catalog.json')

def call_ollama(prompt):
    """Calls Ollama and returns the text output."""
    try:
        result = subprocess.run(
            ['ollama', 'run', 'lezwatch-bot', prompt],
            capture_output=True, text=True, check=True
        )
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        return f"Error calling Ollama: {e}"

def parse_search_action(ai_output):
    """Parses 'SEARCH_ACTION: key:value, key:value' into a dictionary."""
    if "SEARCH_ACTION:" not in ai_output:
        return None

    # Extract the part after SEARCH_ACTION:
    command_text = ai_output.split("SEARCH_ACTION:")[1].strip()
    # Split by comma and then by colon
    params = {}
    for pair in command_text.split(','):
        if ':' in pair:
            k, v = pair.split(':', 1)
            params[k.strip()] = v.strip()
    return params

def search_catalog(params):
    if not os.path.exists(CATALOG_PATH):
        print(f"!! Catalog not found at {CATALOG_PATH}")
        return []

    with open(CATALOG_PATH, 'r') as f:
        catalog = json.load(f)

    # EUROPEAN MAPPING
    eu_countries = ['france', 'germany', 'belgium', 'spain', 'uk', 'italy', 'belgium']
    target_country = params.get('country')
    target_genre = params.get('genre')
    target_trope = params.get('trope')

    matches = []
    for show_id, show in catalog.items():
        # 1. Country Filter
        show_countries = show.get('country', [])
        if target_country:
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
        if 'score' in params and show.get('score', 100) > int(params['score']):
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
    print(f"-> Extracting intent for: '{user_input}'")
    extraction_output = call_ollama(f"STEP 1: EXTRACTION. User says: {user_input}")

    search_params = parse_search_action(extraction_output)

    if not search_params:
        print("!! AI didn't return a SEARCH_ACTION. Here is the response:")
        print(extraction_output)
        return

    # LOCAL SEARCH
    print(f"-> Searching catalog for: {search_params}")
    results = search_catalog(search_params)

    if not results:
        print("AI: I don't have a record of a show like that yet in our database.")
        return

    # PASS 2: Presentation
    print(f"-> Found {len(results)} matches. Generating Curated Presentation...")

    presentation_prompt = (
        f"### TASK: PRESENTATION (STEP 2)\n"
        f"Using ONLY the database results below, craft a response. If there is only 1 result, do NOT say there are three.\n\n"
        f"USER REQUEST: {user_input}\n\n"
        f"DATABASE RESULTS (JSON):\n"
        f"{json.dumps(results)}\n\n"
        f"STRICT FORMATTING RULES (FOLLOW EXACTLY):\n"
        f"1. ONE-SENTENCE 'Curator\'s Note' at the very top.\n"
        f"2. Use this EXACT line for the header: **[title]** ([start_year] - [end_year or 'current']) (Score: [score]) — **[[on_air status]]**\n"
        f"3. 'Why this fits': Synthesize tropes and 'curator_say'.\n"
        f"4. Next line: [excerpt]\n"
        f"5. Next line: [characters] queer characters[IF dead > 0 THEN ' (' + dead + ' are dead)']\n"
        f"6. Last line: Tropes: [list]\n"
        f"NO extra text, NO numbers, NO bolding except on the title and status."
    )

    final_output = call_ollama(presentation_prompt)
    print("\n" + "="*50)
    print(final_output)
    print("="*50 + "\n")

if __name__ == "__main__":
    query = input("Ask the LezWatch AI: ")
    run_chat_cycle(query)
