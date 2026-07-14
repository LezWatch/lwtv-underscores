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

# Import our new modules
from slack_bot_characters import search_catalog_characters, build_results_message_characters
from slack_bot_shows import search_catalog_shows, build_results_message_shows

# Load environment variables from .env file
load_dotenv()

# --- SHARED UTILS ---
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
                # Strip leading dashes, spaces, and other common extraction noise
                key = k.strip().lower().lstrip('- ').strip()
                val = v.strip().lower().split(' ')[0].strip('., ')
                
                # Ignore comments or empty keys/values
                if not key or not val or key.startswith('#'):
                    continue
                
                # Normalize values
                if val in ['underrated', 'maybe']: val = 'meh'
                if val in ['good', 'great', 'awesome', 'best']:
                    val = 'yes'
                    if key == 'worthit':
                        params['score'] = '65' # Default threshold for "good"
                if val in ['bad', 'terrible', 'avoid']: val = 'no'

                # Handle score ranges (e.g., "0-100" -> "100")
                if key == 'score' and '-' in val:
                    val = val.split('-')[-1].strip()
                
                # Map common geography errors from LLM (station -> country)
                if key == 'station' and val in ['canada', 'uk', 'usa', 'france', 'germany', 'australia', 'eu']:
                    params['country'] = val
                    continue

                # Map alternate keys to limit
                if key in ['limit', 'count', 'shows']:
                    params['limit'] = val
                else:
                    params[key] = val
        return params
    except:
        return {}

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
    process_start = time.time()
    user_query = event["text"].split(">")[-1].strip()
    ts = event.get('ts')

    # --- PRIVACY SHORT-CIRCUIT ---
    # We are not allowed to discuss actors.
    # EXCEPTION: We allow representation queries about "queer actors" or "played by"
    # as these refer to character 'queer-irl' metadata.
    is_rep_query = any(x in user_query.lower() for x in ["queer actor", "played by", "queer irl"])
    if "actor" in user_query.lower() and not is_rep_query:
        if "character" not in user_query.lower() and "show" not in user_query.lower():
            say("I am not allowed to discuss actors or provide information about them due to privacy and safety policies. I can only help you find shows and characters.", thread_ts=ts)
            return

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

        search_type = params.get('type')
        if search_type == 'characters':
            results = search_catalog_characters(params)
        elif search_type == 'shows':
            results = search_catalog_shows(params)
        else:
            say(f"I'm having trouble understanding the search criteria (Type: {search_type}). (Took {extraction_time:.1f}s) 🧐", thread_ts=ts)
            return

        search_time = time.time() - step_start
    except Exception as e:
        say(f"❌ Error searching catalog: {e}", thread_ts=ts)
        return

    if not results:
        say(f"I couldn't find any {search_type} matching that! 📺 (Search took {search_time:.1f}s)", thread_ts=ts)
        return

    # 3. Presentation
    say(f"✅ Found {len(results)} matches! Formatting... (Search took {search_time:.1f}s)", thread_ts=ts)

    if search_type == 'characters':
        build_results_message_characters(results, say, ts)
    elif search_type == 'shows':
        build_results_message_shows(results, say, ts, call_ollama)

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
