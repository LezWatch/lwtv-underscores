import os
import json

def search_catalog_characters(params):
    if not os.environ.get("CATALOG_PATH"):
        print("!! Error: CATALOG_PATH not set.")
        return []
    catalog_path = os.environ.get("CATALOG_PATH") + "-characters.json"

    if not os.path.exists(catalog_path):
        print(f"!! Error: {catalog_path} not found.")
        return []

    with open(catalog_path, 'r') as f:
        data = json.load(f)

    results = []
    # Target values from params
    target_gender = params.get('gender')
    target_sexuality = params.get('sexuality')
    target_cliches = params.get('cliches')
    target_romantic = params.get('romantic')

    for character in data.values():
        # 1. Gender Filter
        if target_gender:
            genders = [g.lower() for g in character.get('gender', [])]
            if target_gender.lower() not in genders:
                continue

        # 2. Sexuality Filter
        if target_sexuality:
            sexualities = [s.lower() for s in character.get('sexuality', [])]
            if target_sexuality.lower() not in sexualities:
                continue

        # 3. Cliches Filter
        if target_cliches:
            cliches = [c.lower() for c in character.get('cliches', [])]
            if target_cliches.lower() not in cliches:
                continue

        # 4. Romantic Filter
        if target_romantic:
            romantics = [r.lower() for r in character.get('romantic', [])]
            if target_romantic.lower() not in romantics:
                continue

        results.append(character)

    try:
        limit = int(params.get('limit', 3))
    except (ValueError, TypeError):
        limit = 3

    return results[:limit]

def build_results_message_characters(results, say, ts):
    for result in results:
        title = result.get('title', 'Unknown Character')
        url = result.get('permalink', 'https://lezwatchtv.com')
        gender = ", ".join(result.get('gender', []))
        sexuality = ", ".join(result.get('sexuality', []))
        
        message = f"*{title}*\n"
        if gender: message += f"Gender: {gender}\n"
        if sexuality: message += f"Sexuality: {sexuality}\n"
        message += f"<{url}|View details on LezWatch.TV>"
        
        say(text=message, thread_ts=ts)
    return
