import json
import os

def search_catalog_shows(params, catalog_path):
    if not os.path.exists(catalog_path):
        return []

    with open(catalog_path, 'r') as f:
        catalog = json.load(f)

    # EUROPEAN MAPPING
    eu_countries = ['austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czechia', 'denmark', 'estonia', 'finland', 'france', 'germany', 'greece', 'hungary', 'ireland', 'italy', 'latvia', 'lithuania', 'luxembourg', 'malta', 'netherlands', 'poland', 'portugal', 'romania', 'slovakia', 'slovenia', 'spain', 'sweden']

    # SEMANTIC ALIASING: Expand search terms to match catalog tags
    def expand_term(term):
        if not term: return []
        aliases = {
            'mystery': ['mystery', 'police', 'procedural', 'legal', 'crime', 'law-enforcement'],
            'crime': ['crime', 'police', 'procedural', 'law-enforcement'], # Removed "prison"
            'sci-fi': ['sci-fi', 'science-fiction', 'fantasy', 'supernatural'],
            'comedy': ['comedy', 'sitcom', 'funny', 'humor'],
            'law-enforcement': ['law-enforcement', 'police', 'cop', 'cops', 'procedural'],
        }
        return [term.lower()] + [a.lower() for a in aliases.get(term.lower(), [])]

    target_country = params.get('country', '').lower()
    if target_country in ['us', 'usa', 'america']:
        target_country = 'usa'

    target_genre = params.get('genre')
    target_trope = params.get('trope')
    
    # Expand terms for better matching
    genre_keywords = expand_term(target_genre)
    trope_keywords = expand_term(target_trope)

    target_station = params.get('station')
    target_format = params.get('format')
    target_intersection = params.get('intersection')
    target_queer_irl = params.get('queer_irl')

    # Tighten matching: only match if the keyword is a full tag or a very specific substring
    def is_match(keyword, tags):
        for tag in tags:
            # Check for exact match or whole-word match within the tag
            if keyword == tag or f" {keyword} " in f" {tag} " or tag.startswith(f"{keyword} ") or tag.endswith(f" {keyword}"):
                return True
        return False

    matches = []
    for show_id, show in catalog.items():
        # Get separate tags for strict field searching
        genres = [g.lower() for g in show.get('genres', [])]
        tropes = [t.lower() for t in show.get('tropes', [])]
        
        # 1. Country Filter
        show_countries = [c.lower() for c in show.get('country', [])]
        if target_country:
            if target_country == 'eu':
                if not any(c in eu_countries for c in show_countries):
                    continue
            elif target_country not in show_countries:
                continue

        # 2. Genre Filter (strict field check)
        if target_genre:
            if not any(is_match(k, genres) for k in genre_keywords):
                continue

        # 3. Trope Filter (strict field check)
        if target_trope:
            if not any(is_match(k, tropes) for k in trope_keywords):
                continue

        # 4. Station Filter
        if target_station:
            stations = [s.lower() for s in show.get('stations', [])]
            if target_station not in stations:
                continue

        # 5. Format Filter
        if target_format:
            formats = [f.lower() for f in show.get('formats', [])]
            if target_format not in formats:
                continue

        # 6. Intersection Filter
        if target_intersection:
            intersections = [i.lower() for i in show.get('intersections', [])]
            if target_intersection not in intersections:
                continue

        # 7. Queer-IRL Filter
        if target_queer_irl:
            if target_queer_irl.lower() in ['yes', 'true', 'on']:
                if show.get('characters', {}).get('queer_irl', 0) == 0:
                    continue

        # 8. Smart Worth-It Filter
        worthit_val = show.get('worthit', '').lower()
        if params.get('worthit'):
            target_worthit = params['worthit'].lower()
            if target_worthit in ['no', 'meh'] and worthit_val == 'yes':
                continue
            if target_worthit == 'yes' and worthit_val != 'yes':
                continue

        # 9. Score Filter
        try:
            min_score = int(params.get('score', 0))
            show_score = int(show.get('score', 0))
            if show_score < min_score:
                continue
        except (ValueError, TypeError):
            pass

        # 10. Match On Air status
        if params.get('on_air'):
            on_air_bool = show.get('dates', {}).get('on_air', False)
            target_on_air_bool = True if params['on_air'].lower() in ['yes', 'true', 'on'] else False
            if on_air_bool != target_on_air_bool:
                continue

        matches.append(show)

    # Sort shows by score descending
    matches.sort(key=lambda x: x.get('score', 0), reverse=True)
    return matches[:int(params.get('limit', 3))]

def format_show_result(s, call_ollama):
    dates = s.get('dates', {})
    years = f"{dates.get('start', '????')}"
    if dates.get('on_air'): years += " - current"
    elif dates.get('finish'): years += f" - {dates['finish']}"

    char_data = s.get('characters', {})
    total_chars = char_data.get('total', 0)
    dead_chars = char_data.get('dead', 0)
    queer_irl = char_data.get('queer_irl', 0)

    deaths = ""
    if total_chars > 0:
        extra = []
        if dead_chars > 0:
            extra.append(f"{dead_chars} dead")
        if queer_irl > 0:
            extra.append(f"{queer_irl} queer IRL")
        if extra:
            deaths = f" ({', '.join(extra)})"

    ratings = s.get('ratings', {})
    realness = ratings.get('realness', 0)
    quality = ratings.get('quality', 0)
    screentime = ratings.get('screentime', 0)

    # Print the header directly in Python to ensure it's never missing
    print(f"**{s['title']}** ({years}) (Score: {s['score']}/100)")

    show_persona = (
        "You are a strict data formatter for LezWatchTV. "
        "Write a 2-sentence summary using ONLY the DATA provided. "
        "FORBIDDEN: Do not output any SEARCH_ACTION commands. "
        "FORBIDDEN: Do not mention the user's requested limit or search parameters. "
        "Sentence 1: Why it fits the user request. "
        "Sentence 2: Summary of quality and queer characters."
    )
    show_prompt = (
        f"INSTRUCTION: {show_persona}\n\n"
        f"DATA:\nTITLE: {s['title']}\n"
        f"GENRES: {', '.join(s.get('genres', []))}\n"
        f"REALNESS: {realness}/5\nQUALITY: {quality}/5\nSCREENTIME: {screentime}/5\n"
        f"INSIGHT: {s.get('curator_say', '')}\nEXCERPT: {s.get('excerpt', '')}\n"
        f"PEOPLE: {total_chars} queer characters{deaths}\n"
        f"TROPES: {', '.join(s.get('tropes', []))}\n\n"
        f"REQUIRED FORMAT:\nWhy this fits: [1 sentence]\nDescription: [1 sentence]"
    )

    output = call_ollama(show_prompt)
    print(output)
    if s.get('thumbnail_url'):
        print(f"Thumbnail: {s['thumbnail_url']}")
    print("-" * 20)
