# App API

The two read-only routes the LezWatch.TV companion app (the PWA, `LezWatch/pwa`) loads at start-up: a catalogue of every published show, and where each show streams.

The contract is owned by the app: `docs/api/app-catalogue-v1.md` in [LezWatch/pwa](https://github.com/LezWatch/pwa). The app also ships a checker, `tests/Support/AppCatalogueContract.php`, which validates a response against that document. When this page and the contract disagree, the contract wins.

## Routes

| Route | Body |
|---|---|
| `GET /wp-json/lwtv/v2/app/catalogue` | `terms` (tropes, genres, formats, countries, stations, each only the terms a returned show uses) and `shows` (sorted by id) |
| `GET /wp-json/lwtv/v2/app/availability` | `attribution`, `services`, `providers`, `regions` and `shows` (show id, then region, then offer type, then provider ids) |

Both are public and carry `Cache-Control: public, max-age=3600`. Every response starts with the envelope: `schema_version` (`1`), `generated_at` (UTC, `Y-m-d\TH:i:s\Z`) and `data_version`.

**Privacy: the only parameter is `schema`.** Missing or `1` serves v1; anything else is a 400 `rest_invalid_param`. Nothing about the request (who, what, where) is read, stored or logged beyond what WordPress already logs.

Conventions the builders enforce: missing values are `null` (never `""`, `0` or `false`); ids are integers and dictionary keys are those ids as strings; an empty dictionary encodes as `{}`, never `[]`; text is plain UTF-8 with entities decoded.

## Code

`plugins/lwtv-plugin/php/rest-api/class-app-json.php` (`LWTV\Rest_API\App_JSON`) is the WordPress glue: routes, queries, cache, invalidation and raw serving. The shaping lives in pure classes in `rest-api/build/`, unit-tested without WordPress (`tests/unit/Rest_API/`).

| Class | Owns |
|---|---|
| `App_Envelope` | The envelope, `data_version`, and plain-text helpers |
| `App_Show_Record` | One show in contract field order: terms to ids, `trigger`, `star`, `queer_death`, score rounding, years, similar ids as ints |
| `App_Catalogue` | Records sorted by id, similar ids that point at no catalogue show dropped, and the used-terms-only dictionaries |
| `Service_Map` | Provider-to-service grouping (see [Grouping](#grouping-providers-into-services)) |
| `App_Availability` | Stored provider meta compacted into ids, with provider, service and region dictionaries; empty types, regions and shows left out; ranks each region's services by streamable shows (`flatrate`, `free`, `ads` only) |

Each region's `services` list is uncapped: every service with at least one streamable show there, ranked by LWTV show count. The app truncates it for its A1 grid.

Password-protected shows are excluded from both routes. `year_end` is `null` while a show is on air (and when the finish is `current` or unknown), even if a finish year is stored.

Show `trigger` uses the same lookup as `Theme\Content_Warning` (the `lez_triggers` term slug, else `lezshows_triggerwarning`, through `Trigger_Warning::normalize()`, `none` as `null`). `viewer_score` and `viewer_votes` are `null` until lwtv-underscores#757.

## data_version

`data_version` is the first 12 hex characters of the md5 of the JSON body, **excluding** `generated_at` (and the other envelope fields). It is deliberately not `MAX(post_modified)`: the watch-provider refresh and score recalculation write post meta and never touch `post_modified`, so a modified-date version would stay put while the data changed. Hashing the payload changes exactly when the app would see different data, and not when only the clock moved.

## Cache

| Transient | Holds |
|---|---|
| `lwtv_app_catalogue_v1` | The encoded catalogue JSON string |
| `lwtv_app_availability_v1` | The encoded availability JSON string |

Both last 24 hours (`DAY_IN_SECONDS`) and go through `lwtv_plugin()->get_transient()` / `set_transient()`.

**The transient holds the encoded string, not an array.** On a real HTTP request, `rest_pre_serve_request` echoes that string untouched, so a hit costs no decode and no re-encode. Consequence, on purpose: core's `_fields`, `_pretty` and `_envelope` are ignored on these routes. A `rest_do_request()` call (internal use, tests) is not an HTTP request, so it gets the decoded object instead, with empty dictionaries still `{}`.

### Invalidation

A flush deletes the transient immediately and again on `shutdown`. The second delete means a long batch (an import, a bulk recalculation) cannot leave a cache that a mid-batch request rebuilt from half-updated data. Each transient is flushed at most once per request.

| Event | Flushes |
|---|---|
| Save, trash, untrash or delete of a show | Both |
| Term created, edited or deleted in a catalogue taxonomy (`lez_formats`, `lez_country`, `lez_stations`, `lez_genres`, `lez_tropes`, `lez_triggers`, `lez_stars`) | Catalogue |
| Term created, edited or deleted in `lez_watch_urls` | Availability |
| Meta added, updated or deleted on a show for `lezshows_the_score`, `lezshows_on_air`, `lezshows_triggerwarning`, `lezshows_similar_shows`, `lezshows_tmdb_id` and the three Airdates keys | Catalogue |
| Meta written for the watch-provider data key (`Watch_Providers_Task::META_DATA`) | Availability |

**Known gaps.** `wp_set_object_terms()` called without a save, and `delete_post_meta_by_key()`, fire none of these hooks, so they do not flush. The 24 hour TTL covers them.

### Memory

Measured on lwtv.local with 2,266 shows:

| | Cold build | Response | Cached hit |
|---|---|---|---|
| Catalogue | 62 MB | 0.92 MB | under 30 MB |
| Availability | 152 MB | 2.42 MB (226 KB gzipped) | under 30 MB |

The catalogue is built in chunks of 200 ids (`CATALOGUE_CHUNK`), priming caches per chunk. A cold build calls `wp_cache_delete( $id, 'post_meta' )` for each show once it has been read, which also evicts those entries from a persistent object cache. The provider meta is about 22 MB of JSON site-wide, so availability reads it in pages of 250 rows (`META_PAGE`) straight from postmeta instead of loading it through `get_post_meta()`.

### Local testing and production

- lwtv.local sets `LWTV_DISABLE_TRANSIENTS`, so every local request is a cold build. That is the worst case; it also means local timing says nothing about cache hits.
- **Check before launch:** if production runs a persistent object cache with an item limit near 1 MB (some memcached setups), a 2.4 MB availability transient may not store. Every request would then be a cold build. Confirm the limit, or confirm a request is served from cache, before the app depends on it.

## Grouping providers into services

TMDB lists "Netflix" and "Netflix Standard with Ads", or "AMC+" and "AMC+ Amazon Channel", as separate providers. A person picks one service, so `Service_Map` groups providers into the `services` dictionary and tags each provider with its `service` slug. Order of precedence:

1. **Editor override.** A `lez_watch_urls` term can list TMDB provider ids in its **TMDB provider IDs** field (`lezwatchurls_tmdb_providers`, comma-separated). Those providers form one service, named and slugged from the term name. If two terms claim the same provider id, the lower term id wins.
2. **Ad-tier fold.** A provider named `<base> [standard|basic|essential|premium|ad-supported] with ads` joins the service named `<base>`. Only those tier words fold, so "Apple TV" (the store) and "Apple TV Plus" stay separate, and "Max" does not swallow "maxdome Store".
3. **`service_key`.** Otherwise providers group by `Tmdb_Providers::service_key()`: the name without a reseller suffix ("Amazon Channel" and similar), letters and digits only. A group made only of resellers takes the reseller-stripped name, so its slug stays stable if a direct provider appears later.

The contract allows a provider's `service` to be `null`, but this server always assigns one: symbol-only and non-Latin names get `service-<id>` (see the fallbacks below).

Service slugs are ASCII only (`[a-z0-9-]`) and identical on every server. `Service_Map::slug()` folds Latin diacritics through one fixed table (`Service_Map::ASCII_FOLD`: Latin-1 Supplement and Latin Extended-A, plus ß, æ, ø, œ, ł, đ, þ), not intl or iconv, whose output differs by platform and locale. So "Canal+ Séries" is `canal-plus-series` and "Straßen TV" is `strassen-tv`. Letters outside the table (Cyrillic, CJK) and symbols drop out. Fallbacks, so a key is never empty or numeric:

- An all-digit slug gets a `service-` prefix ("10" is `service-10`), because clients that decode JSON to arrays turn numeric keys into integers.
- An automatic group whose slug is empty (a name like "ТНТ" or a symbol) becomes `service-<lowest provider id>`, keeping its original name.
- An override term whose slug is empty becomes `service-term-<term id>`.
- Two groups that slug alike ("Café" and "Cafe"): the group with the lower provider id keeps `cafe`, the other gets `cafe-<its lowest provider id>`.

### Fixing a wrong grouping

Open the term for the service in **Watch URLs** (`lez_watch_urls`) and put every TMDB provider id that is that service into **TMDB provider IDs**, for example `8, 1796` for Netflix. Saving the term flushes availability. Provider ids are the keys of the `providers` dictionary in the availability response.

**Caveat.** The override slug comes from the term's name. If that slug differs from the slug a provider group would get automatically, the service can split: the listed ids move to the override service while an unlisted sibling stays in the automatic one. List all of the service's provider ids on the one term to avoid this.

## Deferred

Not in 8.1.0:

- The unmapped and suspect providers admin report (lwtv-underscores#755 comment). `Service_Map` output has the data it needs.
- Characters per show (#755 item 4).
- Viewer score and votes (#757); both fields are `null` until then.
- Per-region availability files, only if the single availability file proves too large.

## Related

- [Watch Providers](watch-providers.md#app-service-grouping): the term field.
- [TMDB Watch Providers](../integrations/tmdb-watch-providers.md): where the stored provider data comes from.
- [Caching](caching.md): the transient helpers.
