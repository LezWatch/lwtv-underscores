#!/usr/bin/env python3

import os
import json
import time
import re
import requests
from dotenv import load_dotenv
from slack_bolt import App
from slack_bolt.adapter.flask import SlackRequestHandler
from flask import Flask, request

# Load environment variables from .env file
load_dotenv()

# --- YOUR EXTRACTION & SEARCH LOGIC ---
def call_ollama(prompt):
    try:
        # Use the API instead of subprocess to avoid terminal hangs
        response = requests.post(
            os.environ.get("OLLAMA_URL") or "http://127.0.0.1:11434/api/generate",
            json={
                "model": "lezwatch-bot",
                "prompt": prompt,
                "stream": False
            },
            timeout=60 # Increased timeout for 3B thinking time
        )
        response.raise_for_status()
        return response.json().get("response", "").strip()
    except Exception as e:
        return f"Error connecting to Ollama: {e}"

def parse_search_action(ai_output):
    # Flexible parser for 3B output
    try:
        if "SEARCH_ACTION:" in ai_output:
            text = ai_output.split("SEARCH_ACTION:")[-1].strip()
        else:
            text = ai_output.strip()

        params = {}
        for pair in re.split(r'[,\n]', text):
            if ':' in pair:
                k, v = pair.split(':', 1)
                key, val = k.strip().lower(), v.strip().lower().split(' ')[0].strip('.,')
                if val == 'underrated': val = 'meh'
                # Map alternate keys to limit
                if key in ['limit', 'count', 'shows']:
                    params['limit'] = val
                else:
                    params[key] = val
        return params
    except:
        return {}

def search_catalog(params):
    if not os.environ.get("SLACK_BOT_TOKEN"):
        load_dotenv() # Ensure env is loaded if called here

    if not os.environ.get("CATALOG_PATH"):
        print("!! Error: CATALOG_PATH not set.")
        return []
    catalog_path = os.environ.get("CATALOG_PATH")

    with open(catalog_path, 'r') as f:
        data = json.load(f)

    results = []
    # Target values from params
    target_country = params.get('country')
    target_genre = params.get('genre')
    target_trope = params.get('trope')
    target_worthit = params.get('worthit')
    target_on_air = params.get('on_air')
    max_score = int(params.get('score', 100))

    # IMPORTANT: We use data.values() because your JSON is a Dict, not a List
    for show in data.values():

        # 1. Country Filter
        if target_country:
            show_countries = [c.lower() for c in show.get('country', [])]
            # Handle 'eu' mapping if needed, but keeping it simple for now
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

        # 4. Score Filter
        if show.get('score', 0) > max_score:
            continue

        # 5. Worth-It Filter
        worthit_val = str(show.get('worthit', '')).lower()
        if target_worthit in ['no', 'meh'] and worthit_val == 'yes':
            continue
        if target_worthit == 'yes' and worthit_val != 'yes':
            continue

        # 6. On-Air Filter
        if target_on_air:
            if show.get('on_air', '').lower() != target_on_air.lower():
                continue

        results.append(show)

    # Simple Sort by score if available
    results.sort(key=lambda x: x.get('score', 0), reverse=True)

    try:
        limit = int(params.get('limit', 3))
    except:
        limit = 3

    return results[:limit]

# --- SLACK APP SETUP ---
app = App(
    token=os.environ.get("SLACK_BOT_TOKEN"),
    signing_secret=os.environ.get("SLACK_SIGNING_SECRET"),
    process_before_response=True # Required for lazy listeners
)
flask_app = Flask(__name__)
handler = SlackRequestHandler(app)

def acknowledge_mention(ack, say, event):
    ack()
    user_query = event["text"].split(">")[-1].strip()
    say(f"🔍 Checking the archives for '{user_query}'...", thread_ts=event.get('ts'))

def process_mention(say, event):
    import time
    process_start = time.time()
    user_query = event["text"].split(">")[-1].strip()
    ts = event.get('ts')

    # 1. Intent Extraction
    try:
        step_start = time.time()
        raw_ai = call_ollama(f"SEARCH_ACTION extraction for: {user_query}")
        params = parse_search_action(raw_ai)
        extraction_time = time.time() - step_start
        
        if not params:
            say(f"I'm having trouble understanding the search criteria. (Took {extraction_time:.1f}s) 🧐", thread_ts=ts)
            return
    except Exception as e:
        say(f"❌ Error during extraction: {e}", thread_ts=ts)
        return

    # 2. Search
    try:
        step_start = time.time()
        criteria = ", ".join([f"{k}: {v}" for k, v in params.items()])
        say(f"🔍 Searching archives for: `{criteria}` (Extraction took {extraction_time:.1f}s)", thread_ts=ts)

        results = search_catalog(params)
        search_time = time.time() - step_start
    except Exception as e:
        say(f"❌ Error searching catalog: {e}", thread_ts=ts)
        return

    if not results:
        say(f"I couldn't find any shows matching that! 📺 (Search took {search_time:.1f}s)", thread_ts=ts)
        return

    # 3. Presentation
    say(f"✅ Found {len(results)} matches! Formatting... (Search took {search_time:.1f}s)", thread_ts=ts)
    
    for s in results:
        try:
            show_start = time.time()
            
            # Pull clean data
            title = s.get('title', 'Unknown Show')
            url = s.get('permalink', s.get('url', 'https://lezwatchtv.com'))
            genres = ", ".join(s.get('genres', []))
            tropes = ", ".join(s.get('tropes', []))
            insight = s.get('curator_say', '')

            # ENHANCED PROMPT: Using more data and negative constraints
            show_prompt = (
                f"DATA:\nTitle: {title}\nGenres: {genres}\nTropes: {tropes}\nInsight: {insight}\n\n"
                f"TASK: Write a punchy, 1-sentence recommendation for this show. "
                f"Be specific to its tropes. Vary your sentence structure. "
                f"STRICT RULE: Do NOT start with 'Get ready', 'Welcome to', or 'Experience'. "
                f"Avoid generic marketing fluff."
            )
            hype = call_ollama(show_prompt)

            # Better year handling
            
            # Better year handling
            start = s.get('start_year') or "????"
            end = s.get('end_year') or ("current" if s.get('on_air') == 'yes' else "")
            year_display = f"{start} - {end}" if end else f"{start}"
            
            format_time = time.time() - show_start

            # Construct the block
            blocks = [
                {
                    "type": "section",
                    "text": {
                        "type": "mrkdwn",
                        "text": f"*<{url}|{title}>* ({year_display})\n{hype}\n_Generated in {format_time:.1f}s_"
                    }
                }
            ]
            say(text=f"Match found: {title}", blocks=blocks, thread_ts=ts)
        except Exception as e:
            say(f"⚠️ Error formatting show '{s.get('title', 'Unknown')}': {e}", thread_ts=ts)

    total_time = time.time() - process_start
    say(f"🏁 Done! Total processing time: {total_time:.1f}s", thread_ts=ts)

# Correct registration of lazy listener
app.event("app_mention")(
    ack=acknowledge_mention,
    lazy=[process_mention]
)

@flask_app.route("/slack/events", methods=["POST"])
def slack_events():
    return handler.handle(request)

if __name__ == "__main__":
    flask_app.run(port=3000)
