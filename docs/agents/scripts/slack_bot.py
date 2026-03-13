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
            "http://127.0.0.1:11434/api/generate",
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
        text = ai_output.split("SEARCH_ACTION:")[-1].strip()
        params = {}
        for pair in re.split(r'[,\n]', text):
            if ':' in pair:
                k, v = pair.split(':', 1)
                key, val = k.strip().lower(), v.strip().lower().split(' ')[0].strip('.,')
                if val == 'underrated': val = 'meh'
                params[key] = val
        return params
    except:
        return {}

def search_catalog(params):
    catalog_path = "/home/uptime/agents/data/ai-catalog.json"

    if not os.environ.get("SLACK_BOT_TOKEN"):
        load_dotenv() # Ensure env is loaded if called here

    if not os.path.exists(catalog_path):
        return []

    with open(catalog_path, 'r') as f:
        data = json.load(f)

    results = []

    # IMPORTANT: We use data.values() because your JSON is a Dict, not a List
    for show in data.values():

        # 1. Country Filter (Your JSON uses a list for country: ["usa"])
        if params.get('country'):
            countries = [c.lower() for c in show.get('country', [])]
            if params['country'].lower() not in countries:
                continue

        # 2. Genre Filter
        if params.get('genre'):
            genres = [g.lower() for g in show.get('genres', [])]
            if params['genre'].lower() not in genres:
                continue

        # 3. Worth-It (Underrated) Filter
        worthit_val = str(show.get('worthit', '')).lower()
        # If user asks for underrated (worthit: meh/no), filter out 'yes'
        if params.get('worthit') in ['no', 'meh'] and worthit_val == 'yes':
            continue
        # If user asks for high quality (worthit: yes), filter out 'no/meh'
        if params.get('worthit') == 'yes' and worthit_val != 'yes':
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
# No need to manually export anymore; load_dotenv handles it!
app = App(
    token=os.environ.get("SLACK_BOT_TOKEN"),
    signing_secret=os.environ.get("SLACK_SIGNING_SECRET")
)
flask_app = Flask(__name__)
handler = SlackRequestHandler(app)

@app.event("app_mention")
def handle_mentions(event, say):
    user_query = event["text"].split(">")[-1].strip()
    ts = event.get('ts')

    say(f"🔍 Checking the archives for '{user_query}'...", thread_ts=ts)

    # 1. Intent Extraction
    raw_ai = call_ollama(f"SEARCH_ACTION extraction for: {user_query}")
    params = parse_search_action(raw_ai)

    # 2. Search
    results = search_catalog(params)
    if not results:
        say("I couldn't find any shows matching that! 📺", thread_ts=ts)
        return

    # 3. Presentation
    say(f"✅ Found {len(results)} matches! Formatting...", thread_ts=ts)
    for s in results:
        # Pre-format logic
        show_prompt = f"DATA: {s['title']}, {s.get('curator_say')}. Write a 1-sentence hype."
        hype = call_ollama(show_prompt)

        # Pull clean data
        title = s.get('title', 'Unknown Show')
        url = s.get('permalink', s.get('url', 'https://lezwatchtv.com')) # Use permalink from your JSON
        year = s.get('start_year', s.get('on_air', 'N/A')) # Fallback for year

        # Construct the block
        blocks = [
            {
                "type": "section",
                "text": {
                    "type": "mrkdwn",
                    "text": f"*<{url}|{title}>* ({year})\n{hype}"
                }
            }
        ]

        # Adding 'text' here fixes the "best practice" warning and push notifications
        say(text=f"Match found: {title}", blocks=blocks, thread_ts=ts)

@flask_app.route("/slack/events", methods=["POST"])
def slack_events():
    return handler.handle(request)

if __name__ == "__main__":
    flask_app.run(port=3000)
