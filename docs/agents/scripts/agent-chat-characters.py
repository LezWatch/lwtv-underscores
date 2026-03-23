import json
import os

def search_catalog_characters(params, catalog_path):
    if not os.path.exists(catalog_path):
        return []

    with open(catalog_path, 'r') as f:
        catalog = json.load(f)

    target_gender = params.get('gender')
    target_sexuality = params.get('sexuality')
    target_cliches = params.get('cliches')
    target_romantic = params.get('romantic')

    matches = []
    for char_id, character in catalog.items():
        if target_gender:
            genders = [g.lower() for g in character.get('gender', [])]
            if target_gender.lower() not in genders: continue

        if target_sexuality:
            sexualities = [s.lower() for s in character.get('sexuality', [])]
            if target_sexuality.lower() not in sexualities: continue

        if target_cliches:
            cliches = [c.lower() for c in character.get('cliches', [])]
            if target_cliches.lower() not in cliches: continue

        if target_romantic:
            romantics = [r.lower() for r in character.get('romantic', [])]
            if target_romantic.lower() not in romantics: continue

        matches.append(character)

    return matches[:int(params.get('limit', 3))]

def format_character_result(s, call_ollama):
    gender = ", ".join(s.get('gender', []))
    sexuality = ", ".join(s.get('sexuality', []))
    cliches = ", ".join(s.get('cliches', []))
    
    char_prompt = (
        f"DATA:\nNAME: {s['title']}\nGENDER: {gender}\nSEXUALITY: {sexuality}\n"
        f"CLICHES: {cliches}\nEXCERPT: {s.get('excerpt', '')}\n\n"
        f"TASK: Write a 2-sentence description of this character.\n"
        f"FORMAT:\n**Name**\nIdentity: [Gender/Sexuality]\nBio: [1 sentence summary]"
    )

    output = call_ollama(char_prompt)
    print(output)
    if s.get('permalink'):
        print(f"Link: {s['permalink']}")
    print("-" * 20)
