import json
import os

def search_local_catalog(search_params):
    """
    Filters the local ai-catalog.json based on SEARCH_ACTION keys.
    """
    catalog_path = os.path.join(os.path.dirname(__file__), '../data/ai-catalog.json')

    with open(catalog_path, 'r') as f:
        catalog = json.load(f)

    results = []

    # Extract filters from the AI's SEARCH_ACTION
    # Example: {'country': 'germany', 'score': 70, 'worthit': 'no'}
    target_country = search_params.get('country')
    max_score = int(search_params.get('score', 100))
    target_worthit = search_params.get('worthit')
    target_trope = search_params.get('trope')

    for show_id, show in catalog.items():
        # 1. Filter by Country
        if target_country and target_country not in show.get('country', []):
            continue

        # 2. Filter by Score (Underrated logic: show score <= target score)
        if target_worthit == 'no' and show.get('score', 0) > max_score:
            continue

        # 3. Filter by Trope
        if target_trope and target_trope not in show.get('tropes', []):
            continue

        # If it passed filters, add to results
        results.append(show)
        if len(results) >= int(search_params.get('limit', 3)):
            break

    return results
