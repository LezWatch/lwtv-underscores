# Meilisearch

Prototype Meilisearch indexes of **characters**, **shows** and **actors**, running next to SearchWP so the two can be compared on real queries ([#749](https://github.com/LezWatch/lwtv-underscores/issues/749)). Nothing on the live site uses them. SearchWP, its engines, FacetWP's search facets and the live search UI are untouched.

Code: `plugins/lwtv-plugin/php/meilisearch/`. Setup and 3am fixes: [docs/runbooks/meilisearch.md](../runbooks/meilisearch.md). Designs: `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md`, `docs/superpowers/specs/2026-11-25-meilisearch-shows-design.md`, `docs/superpowers/specs/2026-11-25-meilisearch-actors-design.md`.

## Configuration

Set in `wp-config.php`. The module registers nothing (no hooks, no admin page) until the first three are set.

| Constant | Required | What it is |
| --- | --- | --- |
| `LWTV_MEILI_URL` | yes | Server URL, e.g. `https://search.lezwatchtv.com` |
| `LWTV_MEILI_INDEX_KEY` | yes | Server-side key: documents, settings, tasks, index create/delete. Never leaves PHP. |
| `LWTV_MEILI_SEARCH_KEY` | yes | Search-only key. Public by design: the browser uses it. |
| `LWTV_MEILI_INDEX_PREFIX` | no | Per-environment prefix. Empty on production. |

Index uids are `{PREFIX}_{type}`, or just `{type}` with no prefix: production uses `characters`, `shows` and `actors`, lwtv.local uses `local_characters`, `local_shows` and `local_actors` (prefix `local`), staging `staging_*`. The same scheme covers the planned `content` index. Keys can be scoped by pattern (`local_*`), so one indexing key and one search key per environment is enough.

Keys are never logged or printed. Errors show the HTTP status and Meilisearch's error code and message only.

A site that sets `WP_HTTP_BLOCK_EXTERNAL` (lwtv.local does) must list the search host in `WP_ACCESSIBLE_HOSTS`, or every request is refused.

## How the module is shaped

Each index is a class extending `Index` (`abstract-index.php`): `Characters_Index`, `Shows_Index`, `Actors_Index`. An index knows its type, post type, settings file and how to build documents, and answers one question, `affected_by( $post_type, $post_id )`: "this post changed; which of my documents does that touch?" `Indexes` is the registry, the only list of types. Sync, the CLI, the admin notice and Search Compare all go through it, so adding an index is one new class plus a settings file.

| Change to… | characters index | shows index | actors index |
| --- | --- | --- | --- |
| a character | that character | its shows | its actors |
| a show | its characters | that show | its characters' actors |
| an actor | their characters | their shows (via their characters) | that actor |

Every cell is a lookup on `lwtv_char_links` (`Store::characters_for_object()`, `Store::objects_for_character()`, published posts only).

Pure decisions live in `build/` and are unit-tested: the documents, title splitting, sync routing, index state, the `--type` option, settings loading and the prune filter.

## The documents

One per **published** post. Linked shows, characters and actors are named only when published; an actor with `hide_all` privacy is left out, as a document and as a name in others, even if their status hasn't caught up.

**Character** (`Build\Character_Document`):

```json
{ "id": 241, "name": "Adam Torres", "shows": ["Degrassi: The Next Generation"], "actors": ["Jordan Todosey"], "role": "regular", "role_rank": 3, "url": "…", "thumb": "…" }
```

`role` is the character's best role across their published shows; `role_rank` is regular 3, recurring 2, guest 1, none 0.

**Show** (`Build\Show_Document`):

```json
{ "id": 32399, "name": "Charmed", "qualifier": "2018", "alt_names": [], "characters": ["Mel Vera", "…"], "actors": ["Melonie Diaz", "…"], "years": "2018 2022", "worth_it": "Yes", "worth_it_rank": 3, "score": 64.45, "stations": ["The CW"], "nations": ["USA"], "url": "…", "thumb": "…" }
```

- `name` / `qualifier`: the title split on one trailing parenthetical (`Build\Show_Title`). Same-name shows then tie on `name`, instead of Meilisearch's exactness rule always ranking the plain title (*Charmed*) above the qualified one (*Charmed (2018)*). "charmed 2018" and "skins us" still match the qualifier.
- `alt_names`: from the `lezshows_show_names` repeater. SearchWP's `shows` engine doesn't index the repeater, though it can still match an alternate name the write-up mentions (it finds *Terra e Paixão* for "land of desire").
- `characters`: published characters on the show; `actors`: their visible actors. So "charmed mel" finds Charmed (2018).
- `years`: start and finish years (via `Shows\Airdates::get()`), `"2024"` for a show still airing.
- `worth_it_rank`: Yes 3, Meh 2, TBD 1, No 0. `score`: `lezshows_the_score`.
- `stations`, `nations`: stored for filtering and display, not searched.

**Actor** (`Build\Actor_Document`):

```json
{ "id": 14089, "name": "Jorja Fox", "other_names": [], "characters": ["Sara Sidle", "…"], "shows": ["CSI: Crime Scene Investigation", "…"], "years": "1968", "bio": "…", "queer": 1, "character_count": 4, "url": "…", "thumb": "…" }
```

- `other_names`: the **Other Names** repeater (`lezactors_other_names`): former names including dead names, married names, stage names. Searchable, so "Ellen Page" finds Elliot Page, but **never displayed**: the index's `displayedAttributes` leave it out of hits, highlights and match positions, and the public search key can't fetch documents. Search Compare cuts SearchWP's rows to the same fields. A name equal to the current title is dropped. These used to live in the excerpt; `wp lwtv migrate othernames` moves them (runbook).
- `characters`: the actor's published characters; `shows`: those characters' published shows.
- `years`: birth and death years only, never full dates (`lezactors_birth` / `lezactors_death`, stored as `Y-m-d`, `Y-n-j` or `Ymd`). **No birth year when the actor has Hide Date of Birth**; the death year stays.
- `bio`: post content as plain text (shortcodes and markup stripped), searched last.
- `queer`: `lezactors_queer` as 1/0; `character_count`: `lezactors_char_count`. Both written by `Actors\Calculations::do_the_math()`, which fires `lwtv_actor_calculated` afterwards so the document picks them up.
- Socials, IMDb/Wikidata IDs and privacy notes are never in the document, so Hide Social Media needs no rule.

## Index settings

`config/{type}.json` is the source of truth. Apply with `wp lwtv meili settings --type=<type>`; `wp lwtv meili status --type=<type>` says whether the server matches.

**Characters:** searchable `name`, `shows`, `actors`; ranking v1.57 defaults then `role_rank:desc` (a name match wins, then the bigger role, then show-name matches); stop words `from`, `on` ("Mel from Slo-Pitch" searches as "Mel Slo-Pitch"); filterable `role`, `id`.

**Shows:** searchable `name`, `qualifier`, `alt_names`, `characters`, `actors`, `years`; ranking v1.57 defaults then `worth_it_rank:desc`, `score:desc`; stop words `from`, `on`, `with`; filterable `id`, `worth_it`, `stations`, `nations`.

**Actors:** searchable `name`, `other_names`, `characters`, `shows`, `years`, `bio`; displayed: everything except `other_names`; ranking v1.57 defaults then `queer:desc`, `character_count:desc` (Mika, 2026-11-25: when names tie, queer actors first, then more characters); stop words `from`, `on`, `in`; filterable `id`, `queer`.

The shows tie-breakers are Mika's call (2026-11-25). Plain "skins" should find the UK original (Worth It Yes beats the US remake's No), "charmed" and "night court" the new ones, "shameless" and "ghosts" the UK ones, "mistresses" the 2008 UK one, "gossip girl" the 2021 one. Score alone gets Skins wrong; recency gets Skins wrong; character count gets Night Court wrong.

`id` is filterable on every index so `reindex` can delete documents that are no longer published.

Every search sends `matchingStrategy: "frequency"` (a per-request option, see the characters design).

## When an index isn't set up

Sync never creates an index. Meilisearch would create one on the first document add, with default settings, so the first editor save after a deploy would otherwise build a production index nobody approved.

- A queued task first checks the index exists (`Index::state()`, cached in transient `lwtv_meili_state_{uid}`: `exists` for an hour, `missing` for five minutes; any other error is `unknown`, not cached, and also skips). If it isn't, the task logs one line and returns.
- Admins (`manage_options`) see a warning on the Dashboard and LezWatch.TV screens naming each missing index and the command that creates it (`Admin_Notice`).
- Search Compare says "Index not set up on this environment".
- Only `wp lwtv meili settings` and `reindex --fresh` create an index. Running `settings --type=shows` is what switches shows on for an environment. A plain `reindex` (no `--fresh`) stops with an error on a missing index rather than creating one with default settings.
- While the server is unreachable, the admin notice re-checks at most every five minutes per index, so admin pages don't wait on a timeout each load.

When a front-end search is built on Meilisearch, it must fall back to regular WordPress search when the server or an index is unavailable (Mika, 2026-11-25).

## Commands

```
wp lwtv meili status   [--type=characters|shows|actors|all]
wp lwtv meili settings [--type=…]                     # create the index if missing, apply config/{type}.json
wp lwtv meili reindex  [--type=…] [--dry-run] [--fresh] [--batch=500]
wp lwtv meili sync <id>                               # one post, in every index it affects
wp lwtv migrate othernames [--dry-run] [--show-review] [--clear=<ids>] [--yes]  # actor excerpts → Other Names (runbook)
```

`--type` defaults to `characters`, so commands from before the shows index do what they always did; a typo stops with the list of valid types. Every command prints `Index: <uid> on <host>` before touching an index. `reindex --fresh` deletes and recreates the index, then applies settings: that is also the upgrade path after a Meilisearch version change, since indexes are fully derived from WordPress and never backed up.

## Sync

Hooks only queue work through `lwtv_plugin()->schedule_task()` (Action Scheduler, 5-second delay, one pending task per post per index), so the search server can't slow or break a save. Which indexes an event queues is `Build\Sync_Routes`; which documents in each is worked out when the task runs.

| Change | Hook | Queues |
| --- | --- | --- |
| Character saved | `acf/save_post` (priority 30, after the link table) | characters, shows, actors |
| …and the shows and actors it had before the save (so one it left drops its name) | `acf/save_post` priority 5 records them | shows / actors, for each |
| Show saved (Worth It, alternate names, airdates, terms) | `acf/save_post` | shows only |
| Actor saved (privacy and other names are ACF meta) | `acf/save_post` | characters, shows, actors |
| Any of the three renamed while published (any path: quick edit, REST, CLI) | `post_updated` + `Build\Change_Filter` | characters, shows, actors |
| Any of the three in or out of publish (trash, unpublish, private, scheduled publish) | `transition_post_status` | characters, shows, actors |
| Character deleted | `before_delete_post` priority 5 (before the link table drops its rows at 10) | characters; shows and actors, for each of its shows and actors |
| Show deleted | `before_delete_post` | shows |
| Actor deleted | `before_delete_post` | actors |
| Show score recalculated (save, calculation task, `wp lwtv calc`) | `lwtv_show_score_updated`, fired in `Calculations::do_the_math()` | shows |
| Actor counts / queer flag recalculated (after an actor or character save, `wp lwtv calc`) | `lwtv_actor_calculated`, fired in `Actors\Calculations::do_the_math()` | actors |

Tasks are `lwtv_meili_characters_task`, `lwtv_meili_shows_task` and `lwtv_meili_actors_task`, each with the changed post's ID. A task re-syncs the post's own document in its own index (added if published, deleted otherwise, deleted if the post no longer exists), or fans out to `affected_by()` in 100-document requests.

Failures are logged to the `meilisearch` debug topic (tick it under LezWatch.TV → Debugging) and dropped. To repair drift, run `wp lwtv meili reindex --type=all`: it re-sends every published post, then deletes every document whose `id` isn't in that set (`POST /indexes/{uid}/documents/delete` with `id NOT IN [...]`, built by `Build\Prune_Filter`, which refuses an empty set). Search keeps working throughout; `--fresh` empties the index first.

Known gaps: force-deleting a published show or actor (skipping the trash) drops its link rows before any fan-out can read them, so dependent documents keep the old name until the next `reindex`. Deleting an actor removes their own document but doesn't update the characters and shows that named them.

## Search Compare

LezWatch.TV → Search Compare (`manage_options`). A Characters / Shows / Actors switch and one search box. Meilisearch results come straight from the browser with the search key; SearchWP results come from its engine of the same name (`characters`, `shows`, `actors`), cut to the index's displayed fields, through `GET /wp-json/lwtv/v1/meili-compare/searchwp?q=&type=`. Both columns show rank order and timing; show rows add the qualifier, years, Worth It, score and the first few characters; actor rows add years, a queer marker and the first few characters. It is a throwaway page for deciding whether to continue.

## Deferred

The search modal and front-end search (with its WordPress-search fallback), the content index, FacetWP, synonyms, an admin settings screen.
