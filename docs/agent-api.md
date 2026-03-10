# Agent API Configuration

## Overview

The Agent API allows the LezWatch.TV AI (at ai.ipstenu.com) to query show data by trope and score. The endpoint parses natural-language prompts, extracts trope and minimum score, and returns matching shows with title, permalink, score, and excerpt. Responses are cached for 1 hour to protect against MySQL bottlenecks under concurrent load.

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
| `context` | No | Reserved for future chat-history support. |

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

The endpoint extracts **trope** and **score** from the prompt using two strategies:

### Structured Format

When the AI Orchestrator pre-parses the user's intent:

```
trope:slow-burn,score:80
```

### Natural Language

**Numeric score phrases:**

- "score over 80" / "score above 80" / "rated 80" → minimum score 80
- "score under 20" / "score below 20" → maximum score 20

**Semantic score terms** (high=80, low=20, default=50):

- "with a high score" / "high score" / "score high" → minimum 80
- "with a low score" / "low score" / "score low" → maximum 20
- "with a default score" / "default score" / "score default" → minimum 50

Trope names are matched against the `lez_tropes` taxonomy (e.g. "slow burn" → slug `slow-burn`).

## Response Format

```json
{
  "shows": [
    {
      "title": "Show Name",
      "permalink": "https://lezwatchtv.com/shows/show-name/",
      "score": 85,
      "excerpt": "Short excerpt text..."
    }
  ]
}
```

| Field | Description |
|-------|-------------|
| `title` | Show title |
| `permalink` | Full URL to the show page |
| `score` | LezWatch score (0–100) |
| `excerpt` | Short text summary (~25 words, HTML stripped) |

## Error Responses

| Status | Condition |
|--------|-----------|
| 400 | Missing or invalid `prompt`; unparseable prompt (no trope or score extracted) |
| 401 | Missing or invalid `X-LezWatch-AI-Key` header; `LWTV_AI_KEY` not defined |

## Caching

Responses are cached for 1 hour per unique trope/score/operator combination. Cache key: `lwtv_ai_{md5(trope+score+operator)}`. This reduces MySQL load when many users query the same trope (e.g. "Slow Burn") concurrently.
