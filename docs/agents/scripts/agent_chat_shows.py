import json
import os

def search_catalog_shows(params, catalog_path):
    if not os.path.exists(catalog_path):
        return []

    with open(catalog_path, 'r') as f:
        catalog = json.load(f)

    # EUROPEAN MAPPING
    eu_countries = ['austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czechia', 'denmark', 'estonia', 'finland', 'france', 'germany', 'greece', 'hungary', 'ireland', 'italy', 'latvia', 'lithuania', 'luxembourg', 'malta', 'netherlands', 'poland', 'portugal', 'romania', 'slovakia', 'slovenia', 'spain', 'sweden']
    
    target_country = params.get('country')
    target_genre = params.get('genre')
    target_trope = params.get('trope')
    target_station = params.get('station')
    target_format = params.get('format')
    target_intersection = params.get('intersection')
    target_queer_irl = params.get('queer_irl')

    matches = []
    for show_id, show in catalog.items():
        # 1. Country Filter
        show_countries = [c.lower() for c in show.get('country', [])]
        if target_country:
            if target_country in ['us', 'usa', 'america']:
                target_country = 'usa'

            if target_country == 'eu':
                if not any(c in eu_countries for c in show_countries):
                    continue
            elif target_country not in show_countries:
                continue

        # 2. Genre Filter
        if target_genre:
            genres = [g.lower() for g in show.get('genres', [])]
            if target_genre not in genres:
                continue

        # 3. Trope Filter
        if target_trope:
            tropes = [t.lower() for t in show.get('tropes', [])]
            if target_trope not in tropes:
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

    show_prompt = (
        f"DATA:\nTITLE: {s['title']}\nYEARS: {years}\nSCORE: {s['score']}\n"
        f"REALNESS: {realness}/5\nQUALITY: {quality}/5\nSCREENTIME: {screentime}/5\n"
        f"INSIGHT: {s.get('curator_say', '')}\nEXCERPT: {s.get('excerpt', '')}\n"
        f"PEOPLE: {total_chars} queer characters{deaths}\n"
        f"TROPES: {', '.join(s.get('tropes', []))}\n\n"
        f"TASK: Write a 2-sentence recommendation using only the DATA above.\n"
        f"FORMAT:\n**Title** (Years) (Score: X)\nWhy this fits: [1 sentence]\nDescription: [1 sentence]"
    )

    output = call_ollama(show_prompt)
    print(output)
    if s.get('thumbnail_url'):
        print(f"Thumbnail: {s['thumbnail_url']}")
    print("-" * 20)
