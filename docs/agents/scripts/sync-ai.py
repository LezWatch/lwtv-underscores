#!/usr/bin/env python3
"""
Sync AI catalog from WordPress REST API.
Usage: python sync-ai.py [prod|staging]
"""
import argparse
import json
import os
import sys
import tempfile
import html
import base64
from datetime import datetime, timezone

import requests
from dotenv import load_dotenv

# Load .env
load_dotenv(os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env'))
load_dotenv()

# CONFIGURATION
API_DOMAIN = {
    'prod': 'lezwatchtv.com',
    'staging': 'lezwatchtvcom.stage.site',
}

# File Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, 'data')
CATALOG_PATH = os.path.join(DATA_DIR, 'ai-catalog.json')
STATE_PATH = os.path.join(DATA_DIR, 'sync-state.json')
TROPE_MAP_PATH = os.path.join(DATA_DIR, 'trope-map.json')

# Permissions (default to 0664)
FILE_MODE = int(os.environ.get('FILE_MODE', '664'), 8)
DIR_MODE = int(os.environ.get('DIR_MODE', '775'), 8)

def atomic_write_json(file_path, data):
    os.makedirs(os.path.dirname(file_path), mode=DIR_MODE, exist_ok=True)
    with tempfile.NamedTemporaryFile('w', dir=os.path.dirname(file_path), delete=False) as tf:
        json.dump(data, tf, indent=4)
        temp_name = tf.name

    # Set permissions before move to ensure it's never inaccessible
    os.chmod(temp_name, FILE_MODE)
    os.replace(temp_name, file_path)

    # If running as root, attempt to match the directory ownership
    if os.getuid() == 0:
        stat = os.stat(os.path.dirname(file_path))
        os.chown(file_path, stat.st_uid, stat.st_gid)

def generate_trope_map(catalog):
    trope_counts = {}
    for show_id in catalog:
        show = catalog[show_id]
        for trope in show.get('tropes', []):
            trope_counts[trope] = trope_counts.get(trope, 0) + 1
    sorted_tropes = dict(sorted(trope_counts.items(), key=lambda item: item[1], reverse=True))
    atomic_write_json(TROPE_MAP_PATH, sorted_tropes)
    print(f"-> Generated trope map with {len(sorted_tropes)} unique tropes.")

def cleanup_ghost_shows(catalog, api_key, env, auth=None):
    """
    Compares local catalog against a master list of IDs from WP.
    """
    integrity_url = f"https://{API_DOMAIN[env]}/wp-json/lwtv/v1/sync-ai/integrity"
    headers = {'X-LezWatch-AI-Key': api_key}

    print(f"-> Running Integrity Check ({env}) to remove deleted posts...")
    try:
        r = requests.get(integrity_url, headers=headers, auth=auth, timeout=60)
        r.raise_for_status()
        live_ids = set(map(str, r.json()))
    except Exception as e:
        print(f"!! Integrity Check failed: {e}")
        return catalog

    local_ids = set(catalog.keys())
    ghost_ids = local_ids - live_ids

    if ghost_ids:
        print(f"-> Found {len(ghost_ids)} ghost shows. Removing...")
        for gid in ghost_ids:
            print(f"   - Removing: {catalog[gid].get('title', gid)}")
            del catalog[gid]
    else:
        print("-> No ghost shows found. Catalog is clean.")

    return catalog

def sync(env, api_key, auth=None):
    if not api_key:
        print("!! Error: LWTV_AI_KEY/API_KEY not set.")
        sys.exit(1)

    api_url = f'https://{API_DOMAIN[env]}/wp-json/lwtv/v1/sync-ai'

    # 1. Load Sync State
    last_modified = ""
    if os.path.exists(STATE_PATH):
        try:
            with open(STATE_PATH, 'r') as f:
                last_modified = json.load(f).get('last_modified', "")
        except json.JSONDecodeError:
            pass

    # 2. Fetch Updates
    headers = {'X-LezWatch-AI-Key': api_key}
    params = {'modified_after': last_modified} if last_modified else {}
    print(f"-> Requesting {env} updates since: {last_modified or 'Beginning of Time'}...")

    try:
        response = requests.get(api_url, headers=headers, params=params, auth=auth, timeout=120)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(f"!! API Request Failed: {e}")
        return

    updates = response.json()

    # 3. Load Master Catalog
    catalog = {}
    if os.path.exists(CATALOG_PATH):
        try:
            with open(CATALOG_PATH, 'r') as f:
                catalog = json.load(f)
        except json.JSONDecodeError:
            print("!! Warning: Catalog corrupted. Rebuilding.")

    # Apply Updates
    if updates:
        for show in updates:
            if 'excerpt' in show and show['excerpt']:
                show['excerpt'] = html.unescape(show['excerpt'])
            if 'title' in show and show['title']:
                show['title'] = html.unescape(show['title'])
            catalog[str(show['id'])] = show
        print(f"-> Applied {len(updates)} updates.")

    # 4. Integrity Check & Atomic Save
    catalog = cleanup_ghost_shows(catalog, api_key, env, auth)
    atomic_write_json(CATALOG_PATH, catalog)

    # Update State
    atomic_write_json(STATE_PATH, {'last_modified': datetime.now(timezone.utc).isoformat()})

    # 5. Generate Insights
    generate_trope_map(catalog)
    print(f"-> Sync Complete. Total catalog: {len(catalog)} entries.")

def main():
    parser = argparse.ArgumentParser(description='Sync AI catalog')
    parser.add_argument('env', nargs='?', default='prod', choices=['prod', 'staging'])
    args = parser.parse_args()

    api_key = os.environ.get('API_KEY') or os.environ.get('LWTV_AI_KEY')

    auth = None
    if args.env == 'staging':
        username = os.environ.get('STAGING_USERNAME')
        password = os.environ.get('STAGING_PASSWORD')
        if not username or not password:
            print("!! Error: STAGING_USERNAME/PASSWORD required for staging.")
            sys.exit(1)
        auth = (username, password)

    sync(args.env, api_key, auth)

if __name__ == "__main__":
    main()
