import os
import json
import time
from datetime import datetime

def search_catalog_shows(params):
    if not os.environ.get("CATALOG_PATH"):
        print("!! Error: CATALOG_PATH not set.")
        return []
    catalog_path = os.environ.get("CATALOG_PATH") + "-shows.json"

    if not os.path.exists(catalog_path):
        print(f"!! Error: {catalog_path} not found.")
        return []

    with open(catalog_path, 'r') as f:
        data = json.load(f)

    results = []
    # Target values from params
    target_country = params.get('country')
    target_genre = params.get('genre')
    target_trope = params.get('trope')
    target_worthit = params.get('worthit')
    target_on_air = params.get('on_air')
    target_station = params.get('station')
    target_format = params.get('format')
    target_intersection = params.get('intersection')
    target_queer_irl = params.get('queer_irl')

    try:
        max_score = int(params.get('score', 100))
    except (ValueError, TypeError):
        max_score = 100

    # IMPORTANT: We use data.values() because your JSON is a Dict, not a List
    for show in data.values():

        # 1. Country Filter
        if target_country:
            show_countries = [c.lower() for c in show.get('country', [])]
            if target_country.lower() not in show_countries:
                continue

        # 2. Genre Filter
        if target_genre:
            genres = [g.lower() for g in show.get('genres', [])]
            if target_genre.lower() not in genres:
                continue

        # 3. Trope Filter
        if target_trope:
            tropes = [t.lower() for t in show.get('tropes', [])]
            if target_trope.lower() not in tropes:
                continue

        # 4. Station Filter
        if target_station:
            stations = [s.lower() for s in show.get('stations', [])]
            if target_station.lower() not in stations:
                continue

        # 5. Format Filter
        if target_format:
            formats = [f.lower() for f in show.get('formats', [])]
            if target_format.lower() not in formats:
                continue

        # 6. Intersection Filter
        if target_intersection:
            intersections = [i.lower() for i in show.get('intersections', [])]
            if target_intersection.lower() not in intersections:
                continue

        # 7. Queer-IRL Filter
        if target_queer_irl:
            if target_queer_irl.lower() in ['yes', 'true', 'on']:
                if show.get('characters', {}).get('queer_irl', 0) == 0:
                    continue

        # 8. Score Filter
        try:
            if show.get('score', 0) > max_score:
                continue
        except (ValueError, TypeError):
            pass

        # 8. Worth-It Filter
        worthit_val = str(show.get('worthit', '')).lower()
        if target_worthit:
            if target_worthit in ['no', 'meh'] and worthit_val == 'yes':
                continue
            if target_worthit == 'yes' and worthit_val != 'yes':
                continue

        # 9. On-Air Filter
        if target_on_air:
            on_air_bool = show.get('dates', {}).get('on_air', False)
            target_on_air_bool = True if target_on_air.lower() in ['yes', 'true', 'on'] else False
            if on_air_bool != target_on_air_bool:
                continue

        results.append(show)

    # Simple Sort by score if available
    results.sort(key=lambda x: x.get('score', 0), reverse=True)

    try:
        limit = int(params.get('limit', 3))
    except (ValueError, TypeError):
        limit = 3

    return results[:limit]

def build_results_message_shows(results, say, ts, call_ollama):
    for s in results:
        try:
            show_start = time.time()

            # Pull clean data
            title = s.get('title', 'Unknown Show')
            url = s.get('permalink', s.get('url', 'https://lezwatchtv.com'))
            score = s.get('score', 0)
            
            # Ratings sub-dict
            ratings = s.get('ratings', {})
            loved = ratings.get('loved', False)
            realness = ratings.get('realness', 0)
            quality = ratings.get('quality', 0)
            screentime = ratings.get('screentime', 0)
            
            loved_heart = " ❤️" if loved else ""
            genres = ", ".join(s.get('genres', []))
            tropes = ", ".join(s.get('tropes', []))
            stations = ", ".join(s.get('stations', []))
            formats = ", ".join(s.get('formats', []))
            insight = s.get('curator_say', '') + ' - ' + s.get('excerpt', '')

            # Handle worthit rating
            worthit = s.get('worthit', 'meh')
            if worthit == 'yes':
                worthit_rating = "🙂"
            elif worthit == 'meh':
                worthit_rating = "😐"
            else:
                worthit_rating = "☹️"

            # Trigger warning handling
            trigger = s.get('triggers', None)
            trigger_warning = ""
            if trigger:
                trigger_warning = "Trigger Warning: " + trigger

            # Star handling
            star = s.get('stars', None)
            star_display = f" ({star} star)" if star else ""

            # ENHANCED PROMPT
            show_prompt = (
                f"DATA:\nTitle: {title}\nWorthit: {worthit}\nGenres: {genres}\nTropes: {tropes}\n"
                f"Realness: {realness}/5\nQuality: {quality}/5\nScreentime: {screentime}/5\nInsight: {insight}\n\n"
                f"TASK: Write a punchy, 2-sentence recommendation for this show. "
                f"Be specific to its tropes and genres. Vary your sentence structure. "
                f"STRICT RULE: Do NOT start with 'Get ready', 'Welcome to', or 'Experience'. "
                f"Avoid generic marketing fluff."
            )
            hype = call_ollama(show_prompt)

            # Dates handling
            dates = s.get('dates', {})
            start = dates.get('start') or ""
            end = dates.get('finish') or ("current" if dates.get('on_air') else "")

            if end and str(start) != str(end):
                year_display = f"{start} - {end}"
            else:
                year_display = f"{start}"

            format_time = time.time() - show_start

            # Clarify on-air status
            if dates.get('on_air'):
                on_air_tag = " *[ON AIR]*"
            else:
                on_air_tag = " *[ENDED]*"

            # Character handling
            char_data = s.get('characters', {})
            characters = char_data.get('total', 0)
            dead = char_data.get('dead', 0)
            queer_irl = char_data.get('queer_irl', 0)
            characters_display = ""
            if characters > 0:
                char_label = "character" if characters == 1 else "characters"
                characters_display = f"{characters} queer {char_label}"

            if dead > 0 or queer_irl > 0:
                extra = []
                if dead > 0:
                    dead_verb = "is" if dead == 1 else "are"
                    extra.append(f"{dead} {dead_verb} dead")
                if queer_irl > 0:
                    extra.append(f"{queer_irl} queer IRL")
                
                characters_display += f" ({', '.join(extra)})"

            # Construct the block
            message_text = (
                f"*{title.upper()}* ({year_display}){on_air_tag}\n"
                f"Score: {score}{star_display} {worthit_rating}{loved_heart}\n"
            )

            if stations:
                message_text += f"Network: {stations}\n"
            
            if formats:
                message_text += f"Format: {formats}\n"

            if trigger_warning:
                message_text += f"{trigger_warning}\n"

            if characters_display:
                message_text += f"Characters: {characters_display}\n"

            message_text += (
                f"{hype}\n"
                f"<{url}|View details on LezWatch.TV>"
            )

            blocks = [
                {
                    "type": "section",
                    "text": {
                        "type": "mrkdwn",
                        "text": message_text
                    }
                },
                {
                    "type": "context",
                    "elements": [
                        {
                            "type": "mrkdwn",
                            "text": f"_Generated in {format_time:.1f}s_"
                        }
                    ]
                }
            ]
            
            # If thumbnail exists, add it to the first section block
            if s.get('thumbnail_url'):
                blocks[0]["accessory"] = {
                    "type": "image",
                    "image_url": s.get('thumbnail_url'),
                    "alt_text": title
                }

            say(text=f"Match found: {title}", blocks=blocks, thread_ts=ts)
        except Exception as e:
            say(f"⚠️ Error formatting show '{s.get('title', 'Unknown')}': {e}", thread_ts=ts)
