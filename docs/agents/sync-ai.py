#!/usr/bin/env python3
"""
Sync AI catalog from WordPress REST API.
Usage: python sync-ai.py [prod|staging]
  prod     - Sync from production (default)
  staging  - Sync from staging with basic auth
"""
import argparse
import json
import os
import sys
import tempfile
from datetime import datetime

import requests
from dotenv import load_dotenv

# Load .env from script directory or current working directory
load_dotenv(os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env'))
load_dotenv()

# CONFIGURATION - resolved after parsing args
API_URLS = {
	'prod': 'https://lezwatchtv.com/wp-json/lwtv/v1/sync-ai',
	'staging': 'https://lezwatchtvcom.stage.site/wp-json/lwtv/v1/sync-ai',
}

# File Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, 'data')
CATALOG_PATH = os.path.join(DATA_DIR, 'ai-catalog.json')
STATE_PATH = os.path.join(DATA_DIR, 'sync-state.json')
TROPE_MAP_PATH = os.path.join(DATA_DIR, 'trope-map.json')

def atomic_write_json(file_path, data):
    """Safely writes JSON to a temp file then renames it to prevent corruption."""
    os.makedirs(os.path.dirname(file_path), exist_ok=True)
    with tempfile.NamedTemporaryFile('w', dir=os.path.dirname(file_path), delete=False) as tf:
        json.dump(data, tf, indent=4)
        temp_name = tf.name
    os.replace(temp_name, file_path)

def generate_trope_map(catalog):
    """Generates a map of all tropes and the count of shows associated with them."""
    trope_counts = {}
    for show_id in catalog:
        show = catalog[show_id]
        for trope in show.get('tropes', []):
            trope_counts[trope] = trope_counts.get(trope, 0) + 1

    # Sort by frequency
    sorted_tropes = dict(sorted(trope_counts.items(), key=lambda item: item[1], reverse=True))
    atomic_write_json(TROPE_MAP_PATH, sorted_tropes)
    print(f"-> Generated trope map with {len(sorted_tropes)} unique tropes.")

def sync(api_url, api_key, auth=None):
	"""
	Sync AI catalog from the given API endpoint.
	:param api_url: Full URL to the sync-ai endpoint
	:param api_key: LWTV_AI_KEY for X-LezWatch-AI-Key header
	:param auth: Optional tuple (username, password) for basic auth
	"""
	if not api_key:
		print("!! Error: API_KEY not set. Add it to .env or set LWTV_AI_KEY in environment.")
		sys.exit(1)

	# 1. Load Sync State
	last_modified = ""
	if os.path.exists(STATE_PATH):
		try:
			with open(STATE_PATH, 'r') as f:
				last_modified = json.load(f).get('last_modified', "")
		except json.JSONDecodeError:
			pass

	# 2. Fetch Updates from WordPress
	headers = {'X-LezWatch-AI-Key': api_key}
	params = {'modified_after': last_modified} if last_modified else {}

	print(f"-> Requesting updates since: {last_modified or 'Beginning of Time'}...")

	try:
		response = requests.get(api_url, headers=headers, params=params, auth=auth, timeout=120)
		response.raise_for_status()
	except requests.exceptions.RequestException as e:
		print(f"!! API Request Failed: {e}")
		return

	updates = response.json()
	if not updates:
		print("-> No new updates found. Database is in sync.")
		return

	# 3. Load & Merge with Master Catalog
	catalog = {}
	if os.path.exists(CATALOG_PATH):
		try:
			with open(CATALOG_PATH, 'r') as f:
				catalog = json.load(f)
		except json.JSONDecodeError:
			print("!! Warning: ai-catalog.json was corrupted. Rebuilding from update.")

	for show in updates:
		# The key is the Show ID (as string) to prevent duplicates
		catalog[str(show['id'])] = show

	# 4. Atomic Save
	atomic_write_json(CATALOG_PATH, catalog)

	# Update State with current timestamp for the next sync
	# We use the current UTC time to ensure we catch anything modified since this run
	atomic_write_json(STATE_PATH, {'last_modified': datetime.utcnow().isoformat()})

	# 5. Generate Secondary Insights
	generate_trope_map(catalog)

	print(f"-> Sync Complete: Updated {len(updates)} shows. Master Catalog contains {len(catalog)} entries.")

	# 6. OPTIONAL: Trigger Ollama Modelfile Rebuild
	# print("-> Refreshing Ollama model...")
	# os.system(f"ollama create lezwatch-bot -f {os.path.join(BASE_DIR, 'doc', 'lezwatchbot.md')}")


def main():
	parser = argparse.ArgumentParser(description='Sync AI catalog from WordPress')
	parser.add_argument(
		'env',
		nargs='?',
		default='prod',
		choices=['prod', 'staging'],
		help='Target environment: prod or staging (default: prod)',
	)
	args = parser.parse_args()

	api_key = os.environ.get('API_KEY') or os.environ.get('LWTV_AI_KEY')
	api_url = API_URLS[args.env]

	auth = None
	if args.env == 'staging':
		username = os.environ.get('STAGING_USERNAME')
		password = os.environ.get('STAGING_PASSWORD')
		if not username or not password:
			print("!! Error: STAGING_USERNAME and STAGING_PASSWORD required for staging. Add them to .env")
			sys.exit(1)
		auth = (username, password)
		print("-> Using staging with basic auth")

	sync(api_url, api_key, auth)


if __name__ == "__main__":
	main()
