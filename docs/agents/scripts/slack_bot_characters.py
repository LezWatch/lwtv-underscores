import os
import json

def search_catalog_characters(params):
    if not os.environ.get("CATALOG_PATH"):
        print("!! Error: CATALOG_PATH not set.")
        return []
    catalog_path = os.environ.get("CATALOG_PATH") + "-characters.json"

    with open(catalog_path, 'r') as f:
        data = json.load(f)

    results = []
    # Target values from params

    for character in data.values():
        results.append(character)
    return results

def build_results_message_characters(results, say, ts):
    for result in results:
        say(f"*{result.get('title', 'Unknown Character')}* ({result.get('permalink', 'https://lezwatchtv.com')})", thread_ts=ts)
    return
