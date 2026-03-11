# Agent API Configuration

## Overview

The Agent API allows the LezWatch.TV AI (at ai.ipstenu.com) to query show data by trope, genre, format, country, station, score, worth-it rating, year, and more. The endpoint parses natural-language prompts, extracts filters, and returns matching shows with title, permalink, score, and excerpt. Responses are cached for 1 hour to protect against MySQL bottlenecks under concurrent load.

## Configuration

Add the following constants to your `wp-config.php` file (recommended):

```php
/**
 * Agent API Configuration
 * Required for X-LezWatch-AI-Key header validation.
 * Prevents public scraping of the /lwtv/v1/agent endpoint.
 */
define( 'LWTV_AI_KEY', 'your-secret-key-here' );
define( 'LWTV_AGENTS_USER', 'server-user-name-with-access' );
define( 'LWTV_AGENTS_PASS', 'password' );
```

Generate a strong random key (e.g. 32+ characters) and share it only with your AI server. If `LWTV_AI_KEY` is not defined, the endpoint returns 401 for all requests.

## Endpoint

| Property | Value |
|----------|-------|
| **Path** | `GET /wp-json/lwtv/v1/agent` |
| **Base URL** | `https://lezwatchtv.com/wp-json/lwtv/v1/agent` |

### Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `prompt` | Yes | The user's question. Supports natural language or structured format. |
| `context` | No | Optional system instruction. When SearchWP returns no results, pass `The user tried to find [query] and failed. Help them find the closest match.` to guide the AI. Also used for chat-history (future). |

### Authentication

Send the API key in the request header:

```
X-LezWatch-AI-Key: your-secret-key-here
```

### Example Request

```bash
curl -H "X-LezWatch-AI-Key: your-secret-key-here" \
  "https://lezwatchtv.com/wp-json/lwtv/v1/agent?prompt=Find+me+a+slow+burn+show+with+a+score+over+80"
```

From the AI server (Orchestrator):

```
https://lezwatchtv.com/wp-json/lwtv/v1/agent?prompt=Find+me+a+slow+burn+show+with+a+score+over+80
```

## Prompt Parsing

The endpoint extracts filters from the prompt. At least one filter is required (trope, genre, format, country, station, score, worth-it, or year).

### Structured Format

```
trope:slow-burn,genre:drama,format:web-series,country:uk,station:netflix,score:80
```

### Natural Language

**Taxonomies** (matched against term names/slugs):

- **Tropes** (lez_tropes): "slow burn", "literary", "dead queers"
- **Genres** (lez_genres): "drama", "comedy", "sci-fi", "horror"
- **Formats** (lez_formats): "web series", "movie", "tv show", "miniseries"
- **Country** (lez_country): "british", "canadian", "american", "uk"
- **Station** (lez_stations): "netflix", "hbo", "bbc"
- **Stars** (lez_stars): "gold star", "by queers for queers"
- **Triggers** (lez_triggers): "violence", "death"
- **Intersections** (lez_intersections): "disabilities", "bipoc"

**Numeric score phrases:**

- "score over 80" / "score above 80" / "rated 80" → minimum score 80
- "score under 20" / "score below 20" → maximum score 20

**Semantic score terms** (high=80, low=20, default=50):

- "with a high score" / "high score" / "score high" → minimum 80
- "with a low score" / "low score" / "score low" → maximum 20
- "with a default score" / "default score" / "score default" → minimum 50

**Worth it:**

- "worth it", "worth watching", "recommended" → yes
- "not worth it", "skip it" → no
- "meh", "mixed" → meh
- "tbd", "to be determined" → tbd

**Year:**

- "from 2020", "in 2020", "after 2019" → year_min
- "before 2015", "pre-2015" → year_max
- "2018 to 2022", "2018-2022" → year range

## Response Format

```json
{
  "shows": [
    {
      "title": "Show Name",
      "permalink": "https://lezwatchtv.com/shows/show-name/",
      "score": 85,
      "excerpt": "Short excerpt text...",
      "characters": 14,
      "dead": 2,
      "tropes": ["law-enforcement", "outed", "coming-out"]
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Show title |
| `permalink` | string | Full URL to the show page |
| `score` | int | LezWatch score (0–100) |
| `excerpt` | string | Short text summary (~25 words, HTML stripped) |
| `characters` | int | Total queer characters linked to the show |
| `dead` | int | Count of characters with `dead` cliché (lez_cliches) |
| `tropes` | string[] | Array of trope slugs from lez_tropes |

**Display logic for character/death line:** When `dead > 0`, show "X queer characters (Y are dead)". When `dead == 0` and `characters > 0`, show "X queer characters (none are dead)" or "X queer characters".

**Ending Status badges:** When `dead === 0`, label as "Happy Ending". When `dead > 0`, label as "Tragic".

**Empty results:** When no shows match, the response includes `"message": "I don't have a record of a show like that yet in our database."` (filterable via `lwtv_agent_no_results_message`).

## Error Responses

| Status | Condition |
|--------|-----------|
| 400 | Missing or invalid `prompt`; unparseable prompt (no filters extracted) |
| 401 | Missing or invalid `X-LezWatch-AI-Key` header; `LWTV_AI_KEY` not defined |

## Caching

Responses are cached for 1 hour per unique params combination. Cache key: `lwtv_ai_{md5(wp_json_encode(params))}`. This reduces MySQL load when many users query the same filters concurrently.
