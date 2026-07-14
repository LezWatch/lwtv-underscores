# Sync AI API Configuration

## Overview

The Sync AI endpoint provides a flat list of shows with AI-relevant metadata for the Ollama server. It supports incremental daily syncs via the `modified_after` parameter and excludes heavy content (comments, galleries, full post content). Protected by the same `X-LezWatch-AI-Key` header as the Agent API.

## Configuration

Uses the same configuration as the Agent API. Add to `wp-config.php`:

```php
define( 'LWTV_AI_KEY', 'your-secret-key-here' );
```

If `LWTV_AI_KEY` is not defined, the endpoint returns 401 for all requests.

## Endpoint

| Property | Value |
|----------|-------|
| **Path** | `GET /wp-json/lwtv/v1/sync-ai` |
| **Base URL** | `https://lezwatchtv.com/wp-json/lwtv/v1/sync-ai` |

### Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `modified_after` | No | ISO 8601 or strtotime-compatible date (e.g. `2025-03-11`). Only shows modified after this date. Omit for full sync. |

### Authentication

Send the API key in the request header:

```
X-LezWatch-AI-Key: your-secret-key-here
```

### Example Requests

Full sync (all published shows):

```bash
curl -H "X-LezWatch-AI-Key: your-secret-key-here" \
  "https://lezwatchtv.com/wp-json/lwtv/v1/sync-ai"
```

Incremental sync (shows modified since date):

```bash
curl -H "X-LezWatch-AI-Key: your-secret-key-here" \
  "https://lezwatchtv.com/wp-json/lwtv/v1/sync-ai?modified_after=2025-03-11"
```

## Response Format

Returns a JSON array of show objects:

```json
[
  {
    "id": 12345,
    "title": "Show Name",
    "slug": "show-name",
    "permalink": "https://lezwatchtv.com/shows/show-name/",
    "score": "85",
    "on_air": "yes",
    "worthit": "yes",
    "tropes": ["slow-burn", "coming-out"],
    "genres": ["drama", "romance"],
    "country": ["usa", "canada"]
  }
]
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | WordPress post ID |
| `title` | string | Show title |
| `slug` | string | Post slug (URL-safe name) |
| `permalink` | string | Full URL to the show page |
| `score` | string | LezWatch score (0–100) from `lezshows_the_score` meta |
| `on_air` | string | `"yes"` if currently on air, `"no"` if ended (from `lezshows_on_air` meta) |
| `worthit` | string | Worth-it rating: `yes`, `no`, `meh`, `tbd` (from `lezshows_worthit_rating` meta) |
| `tropes` | string[] | Array of trope slugs from `lez_tropes` taxonomy |
| `genres` | string[] | Array of genre slugs from `lez_genres` taxonomy |
| `country` | string[] | Array of country slugs from `lez_country` taxonomy |

## Error Responses

| Status | Condition |
|--------|-----------|
| 401 | Missing or invalid `X-LezWatch-AI-Key` header; `LWTV_AI_KEY` not defined |

## Usage Notes

- **Full sync:** Omit `modified_after` to retrieve all published shows. Use for initial Ollama index build.
- **Incremental sync:** Pass `modified_after` with yesterday's date (or last sync timestamp) for daily jobs. Only returns shows modified after that date.
- **Invalid dates:** If `modified_after` is not parseable by `strtotime()`, it is ignored and all shows are returned. Debug logging records invalid values when enabled.

# Sync Server Configuration

## Setup Python

Run these commands in your `/home/uptime/agents` directory:

1. **Install the venv tool** (if not already there):
```bash
sudo apt update && sudo apt install python3-venv -y
```

2. **Create the environment**:
```bash
cd ~/agents
python3 -m venv venv
```

3. **Install your dependencies inside the venv**:
```bash
./venv/bin/pip install python-dotenv requests
```

## Add the Files

Create a file `/home/uptime/agents/bin/.env` and add in values:

```bash
LWTV_AI_KEY='SECRETKEY'
STAGING_USERNAME='USERNAME'
STAGING_PASSWORD='PASSOWRD'
```

Copy `/docs/agents/sync-ai.py` to `/home/uptime/agents/bin/sync-ai.py`

**Run your script using the venv's Python**:
```bash
sudo /home/uptime/agents/venv/bin/python3 /home/uptime/agents/bin/sync-ai.py staging
```

## Setup Cron

Edit cron (`sudo crontab -e`)

```shell
0 3 * * * /home/uptime/agents/venv/bin/python3 /home/uptime/agents/bin/sync-ai.py prod >> /var/log/lwtv-ai-sync.log 2>&1
```
