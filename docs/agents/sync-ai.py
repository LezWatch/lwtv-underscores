#!/usr/bin/env python3
import requests
import json
import os
import sys
import tempfile
from datetime import datetime

# CONFIGURATION
# Set LWTV_AI_KEY in your environment variables for security
API_URL = "https://lezwatchtv.com/wp-json/lwtv/v1/sync-ai"
API_KEY = os.environ.get('LWTV_AI_KEY', 'your_local_dev_key')

# File Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, 'data')
CATALOG_PATH = os.path.join(DATA_DIR, 'ai-catalog.json')
STATE_PATH = os.path.join(DATA_DIR, 'sync-state.json')
TROPE_MAP_PATH = os.path.join(DATA_DIR, 'trope-map.json')

def atomic_write_json(file_path, data):
    """Safely writes JSON to a temp file then renames it to prevent corruption."""
    os.makedirs(os.path.dirname(file_path), exist_ok=True)
    with tempfile.NamedTemporaryFile('w', dir=os.path.dirname(file_path), delete=False) as tf:
        json.dump(data, tf, indent=4)
        temp_name = tf.name
    os.replace(temp_name, file_path)

def generate_trope_map(catalog):
    """Generates a map of all tropes and the count of shows associated with them."""
    trope_counts = {}
    for show_id in catalog:
        show = catalog[show_id]
        for trope in show.get('tropes', []):
            trope_counts[trope] = trope_counts.get(trope, 0) + 1

    # Sort by frequency
    sorted_tropes = dict(sorted(trope_counts.items(), key=lambda item: item[1], reverse=True))
    atomic_write_json(TROPE_MAP_PATH, sorted_tropes)
    print(f"-> Generated trope map with {len(sorted_tropes)} unique tropes.")

def sync():
    if not API_KEY:
        print("!! Error: LWTV_AI_KEY environment variable is not set.")
        sys.exit(1)

    # 1. Load Sync State
    last_modified = ""
    if os.path.exists(STATE_PATH):
        try:
            with open(STATE_PATH, 'r') as f:
                last_modified = json.load(f).get('last_modified', "")
        except json.JSONDecodeError:
            pass

    # 2. Fetch Updates from WordPress
    headers = {'X-LezWatch-AI-Key': API_KEY}
    params = {'modified_after': last_modified} if last_modified else {}

    print(f"-> Requesting updates since: {last_modified or 'Beginning of Time'}...")

    try:
        response = requests.get(API_URL, headers=headers, params=params, timeout=120)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(f"!! API Request Failed: {e}")
        return

    updates = response.json()
    if not updates:
        print("-> No new updates found. Database is in sync.")
        return

    # 3. Load & Merge with Master Catalog
    catalog = {}
    if os.path.exists(CATALOG_PATH):
        try:
            with open(CATALOG_PATH, 'r') as f:
                catalog = json.load(f)
        except json.JSONDecodeError:
            print("!! Warning: ai-catalog.json was corrupted. Rebuilding from update.")

    for show in updates:
        # The key is the Show ID (as string) to prevent duplicates
        catalog[str(show['id'])] = show

    # 4. Atomic Save
    atomic_write_json(CATALOG_PATH, catalog)

    # Update State with current timestamp for the next sync
    # We use the current UTC time to ensure we catch anything modified since this run
    atomic_write_json(STATE_PATH, {'last_modified': datetime.utcnow().isoformat()})

    # 5. Generate Secondary Insights
    generate_trope_map(catalog)

    print(f"-> Sync Complete: Updated {len(updates)} shows. Master Catalog contains {len(catalog)} entries.")

    # 6. OPTIONAL: Trigger Ollama Modelfile Rebuild
    # print("-> Refreshing Ollama model...")
    # os.system(f"ollama create lezwatch-bot -f {os.path.join(BASE_DIR, 'doc', 'lezwatchbot.md')}")

if __name__ == "__main__":
    sync()
