#!/usr/bin/env python3
"""
Sync AI catalog from WordPress REST API.
Usage: python sync-ai.py [prod|staging] [type][--force]
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
CATALOG_PATH = {
    'shows': os.path.join(DATA_DIR, 'ai-catalog-shows.json'),
    'characters': os.path.join(DATA_DIR, 'ai-catalog-characters.json'),
}
STATE_PATH = {
    'shows': os.path.join(DATA_DIR, 'sync-state-shows.json'),
    'characters': os.path.join(DATA_DIR, 'sync-state-characters.json'),
}
TAXONOMY_MAP_PATH = {
    'shows': os.path.join(DATA_DIR, 'taxonomy-map-shows.json'),
    'characters': os.path.join(DATA_DIR, 'taxonomy-map-characters.json'),
}

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

def generate_taxonomy_map(catalog, type):
    taxonomy_counts = {}
    for post_id in catalog:
        post = catalog[post_id]
        if type == 'shows':
            for trope in post.get('tropes', []):
                taxonomy_counts[trope] = taxonomy_counts.get(trope, 0) + 1
            for genre in post.get('genres', []):
                taxonomy_counts[genre] = taxonomy_counts.get(genre, 0) + 1
            for country in post.get('country', []):
                taxonomy_counts[country] = taxonomy_counts.get(country, 0) + 1
            for station in post.get('stations', []):
                taxonomy_counts[station] = taxonomy_counts.get(station, 0) + 1
            for format in post.get('formats', []):
                taxonomy_counts[format] = taxonomy_counts.get(format, 0) + 1
            for intersection in post.get('intersections', []):
                taxonomy_counts[intersection] = taxonomy_counts.get(intersection, 0) + 1

            # Singular taxonomies
            for key in ['stars', 'triggers']:
                val = post.get(key)
                if val:
                    taxonomy_counts[val] = taxonomy_counts.get(val, 0) + 1
        elif type == 'characters':
            for taxonomy in post.get('cliches', []):
                taxonomy_counts[taxonomy] = taxonomy_counts.get(taxonomy, 0) + 1
            for taxonomy in post.get('gender', []):
                taxonomy_counts[taxonomy] = taxonomy_counts.get(taxonomy, 0) + 1
            for taxonomy in post.get('sexuality', []):
                taxonomy_counts[taxonomy] = taxonomy_counts.get(taxonomy, 0) + 1
            for taxonomy in post.get('romantic', []):
                taxonomy_counts[taxonomy] = taxonomy_counts.get(taxonomy, 0) + 1
    sorted_taxonomies = dict(sorted(taxonomy_counts.items(), key=lambda item: item[1], reverse=True))
    atomic_write_json(TAXONOMY_MAP_PATH[type], sorted_taxonomies)
    print(f"-> Generated taxonomy map with {len(sorted_taxonomies)} unique {type}.")

def cleanup_ghost_posts(catalog, api_key, env, auth=None, type='shows'):
    """
    Compares local catalog against a master list of IDs from WP.
    """
    integrity_url = f"https://{API_DOMAIN[env]}/wp-json/lwtv/v1/sync-ai/{type}/integrity"
    headers = {'X-LezWatch-AI-Key': api_key}

    print(f"-> Running Integrity Check ({env}) to remove deleted {type}...")
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
        print(f"-> Found {len(ghost_ids)} ghost {type}. Removing...")
        for gid in ghost_ids:
            print(f"   - Removing: {catalog[gid].get('title', gid)} ({gid})")
            del catalog[gid]
    else:
        print(f"-> No ghost {type} found. Catalog is clean.")

    return catalog

def sync(env, api_key, auth=None, force=False, type='shows'):
    if not api_key:
        print("!! Error: LWTV_AI_KEY/API_KEY not set.")
        sys.exit(1)

    if type == 'shows':
        api_url = f'https://{API_DOMAIN[env]}/wp-json/lwtv/v1/sync-ai/shows'
    elif type == 'characters':
        api_url = f'https://{API_DOMAIN[env]}/wp-json/lwtv/v1/sync-ai/characters'
    else:
        print("!! Error: Invalid type. Must be 'shows', 'characters', or 'all'.")
        sys.exit(1)

    # 1. Load Sync State
    last_modified = ""
    if os.path.exists(STATE_PATH[type]) and not force:
        try:
            with open(STATE_PATH[type], 'r') as f:
                last_modified = json.load(f).get('last_modified', "")
        except json.JSONDecodeError:
            pass

    # 2. Fetch Updates
    headers = {'X-LezWatch-AI-Key': api_key}
    params = {'modified_after': last_modified} if last_modified else {}
    print(f"-> Requesting {env} {type} updates since: {last_modified or 'Beginning of Time'}...")

    try:
        response = requests.get(api_url, headers=headers, params=params, auth=auth, timeout=120)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(f"!! API Request Failed: {e}")
        return

    updates = response.json()

    # 3. Load Master Catalog
    catalog = {}
    if os.path.exists(CATALOG_PATH[type]):
        try:
            with open(CATALOG_PATH[type], 'r') as f:
                catalog = json.load(f)
        except json.JSONDecodeError:
            print(f"!! Warning: {type} catalog corrupted. Rebuilding.")

    # Apply Updates
    if updates:
        for show in updates:
            if 'excerpt' in show and show['excerpt']:
                show['excerpt'] = html.unescape(show['excerpt'])
            if 'title' in show and show['title']:
                show['title'] = html.unescape(show['title'])
            catalog[str(show['id'])] = show
        print(f"-> Applied {len(updates)} {type} updates.")

    # 4. Integrity Check & Atomic Save
    if type == 'shows' or type == 'characters':
        catalog = cleanup_ghost_posts(catalog, api_key, env, auth, type)
        atomic_write_json(CATALOG_PATH[type], catalog)
    else:
        for t in ['shows', 'characters']:
            catalog = cleanup_ghost_posts(catalog, api_key, env, auth, type=t)
            atomic_write_json(CATALOG_PATH[t], catalog)

    # Generate Taxonomy Maps
    if type == 'shows' or type == 'characters':
        generate_taxonomy_map(catalog, type)
    else:
        for t in ['shows', 'characters']:
            generate_taxonomy_map(catalog, t)

    # Update State
    atomic_write_json(STATE_PATH[type], {'last_modified': datetime.now(timezone.utc).isoformat()})

    print(f"-> Sync Complete. Total {type} catalog: {len(catalog)} entries.")

def main():
    parser = argparse.ArgumentParser(description='Sync AI catalog')
    parser.add_argument('env', nargs='?', default='prod', choices=['prod', 'staging'])
    parser.add_argument('--force', action='store_true', help='Force a full sync')
    parser.add_argument('type', nargs='?', default='shows', choices=['shows', 'characters', 'all'])
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

    if args.type == 'all':
        for t in ['shows', 'characters']:
            sync(args.env, api_key, auth, args.force, type=t)
    else:
        sync(args.env, api_key, auth, args.force, type=args.type)

if __name__ == "__main__":
    main()
