# Meilisearch

A prototype Meilisearch index of characters, running next to SearchWP so the two can be compared on real queries ([#749](https://github.com/LezWatch/lwtv-underscores/issues/749)). Nothing on the live site uses it. SearchWP, its engines and the live search UI are untouched.

Code: `plugins/lwtv-plugin/php/meilisearch/`. Setup and 3am fixes: [docs/runbooks/meilisearch.md](../runbooks/meilisearch.md). Design: `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md`.

## Configuration

Set in `wp-config.php`. The module registers nothing (no hooks, no admin page) until the first three are set.

| Constant | Required | What it is |
| --- | --- | --- |
| `LWTV_MEILI_URL` | yes | Server URL, e.g. `https://search.lezwatchtv.com` |
| `LWTV_MEILI_INDEX_KEY` | yes | Server-side key: documents, settings, tasks, index create/delete. Never leaves PHP. |
| `LWTV_MEILI_SEARCH_KEY` | yes | Search-only key. Public by design: the browser uses it. |
| `LWTV_MEILI_INDEX_PREFIX` | no | Per-environment prefix. Empty on production. |

Index uids are `{PREFIX}_{type}`, or just `{type}` with no prefix: production uses `characters`, lwtv.local uses `local_characters` (prefix `local`), staging `staging_characters`. The same scheme covers the planned `shows`, `actors` and `content` indexes. Keys can be scoped by pattern (`local_*`), so one indexing key and one search key per environment is enough.

Keys are never logged or printed. Errors show the HTTP status and Meilisearch's error code and message only.

A site that sets `WP_HTTP_BLOCK_EXTERNAL` (lwtv.local does) must list the search host in `WP_ACCESSIBLE_HOSTS`, or every request is refused.

## The document

One per **published** character:

```json
{ "id": 241, "name": "Adam Torres", "shows": ["Degrassi: The Next Generation"], "actors": ["Jordan Todosey"], "role": "regular", "role_rank": 3, "url": "…", "thumb": "…" }
```

- `shows` and `actors` name only published posts. An actor with `hide_all` privacy is left out even if their status hasn't caught up.
- `role` is the character's best role across their published shows; `role_rank` is regular 3, recurring 2, guest 1, none 0.

Built by `Build\Character_Document` (pure, unit-tested) from inputs gathered by `Indexer`.

## Index settings

`config/characters.json` is the source of truth. Apply it with `wp lwtv meili settings`; `wp lwtv meili status` says whether the server matches.

- Searchable, in priority order: `name`, `shows`, `actors`.
- Ranking: Meilisearch's v1.57 defaults, then `role_rank:desc`. So a name match wins, then the bigger role, then show-name matches.
- Stop words `from` and `on`, so "Mel from Slo-Pitch" searches as "Mel Slo-Pitch".
- Filterable: `role`, and `id` (so `reindex` can delete documents that are no longer published).

## Commands

```
wp lwtv meili status                      # health, document count, settings drift
wp lwtv meili settings                    # create the index if missing, apply config/characters.json
wp lwtv meili reindex --dry-run           # build every document, send nothing
wp lwtv meili reindex [--fresh] [--batch=500]
wp lwtv meili sync <id>                   # add or remove one character now
```

Every command prints `Index: <uid> on <host>` first. `reindex --fresh` deletes and recreates the index, then applies settings: that is also the upgrade path after a Meilisearch version change, since the index is fully derived from WordPress and is never backed up.

## Sync

Hooks only queue work through `lwtv_plugin()->schedule_task()` (Action Scheduler, 5-second delay, one pending task per post), so the search server can't slow or break a save.

| Change | Hook | Queued task |
| --- | --- | --- |
| Character saved | `acf/save_post` (priority 30, after the link table) | `lwtv_meili_character_task` |
| Character renamed while published (any path: quick edit, REST, CLI) | `post_updated` + `Build\Change_Filter` | `lwtv_meili_character_task` |
| Character in or out of publish (trash, unpublish, scheduled publish) | `transition_post_status` | `lwtv_meili_character_task` |
| Character deleted | `before_delete_post` | `lwtv_meili_character_task` |
| Show or actor renamed while published | `post_updated` + `Build\Change_Filter` | `lwtv_meili_fanout_task` |
| Show or actor in or out of publish (trash, unpublish, private, scheduled publish) | `transition_post_status` | `lwtv_meili_fanout_task` |
| Actor saved (privacy is ACF meta) | `acf/save_post` | `lwtv_meili_fanout_task` |

The character task adds the document if the character is published and deletes it otherwise. The fan-out task re-sends every published character linked to the show or actor (from `lwtv_char_links`), 100 per request.

Failures are logged to the `meilisearch` debug topic (tick it under LezWatch.TV → Debugging) and dropped. To repair drift, run `wp lwtv meili reindex`: it re-sends every published character, then deletes every document whose `id` isn't in that set (`POST /indexes/{uid}/documents/delete` with `id NOT IN [...]`, built by `Build\Prune_Filter`, which refuses an empty set). That is why `id` is filterable. Search keeps working throughout; `--fresh` empties the index first.

Known gap: force-deleting a published show or actor (skipping the trash) drops its link rows before any fan-out can read them, so its characters keep the old name until the next `reindex`.

## Search Compare

LezWatch.TV → Search Compare (`manage_options`). One search box; Meilisearch results come straight from the browser with the search key, SearchWP results from its `characters` engine through `GET /wp-json/lwtv/v1/meili-compare/searchwp?q=`. Both columns show rank order, shows, role and timing. It is a throwaway page for deciding whether to continue.

## Deferred

The search modal, the shows/actors/content indexes, FacetWP, synonyms (and a dead-name field), an admin settings screen, and a fallback when the search server is down.
