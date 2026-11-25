# Meilisearch characters prototype: design

Issue: [LezWatch/lwtv-underscores#749](https://github.com/LezWatch/lwtv-underscores/issues/749)

## Intent

SearchWP indexes about 10,000 posts and the site adds 10 to 50 a day, mostly characters. SearchWP's comfortable ceiling is near 20,000 posts, so a replacement is needed within 7 to 33 months. This prototype builds a Meilisearch index of **characters only**, next to SearchWP, so Mika can compare result quality on real queries. Nothing on the live site changes until that comparison says it should.

The long-term goal is to replace SearchWP. This round is deliberately small: it exists to answer "is Meilisearch clearly better for characters?" cheaply.

## Goals

- A `characters` index whose settings are versioned in the repo and applied by WP-CLI.
- A document builder that turns a character into a small, searchable document.
- A WP-CLI reindex that builds the whole index from scratch and reports document count and elapsed time.
- Sync hooks so saving, renaming, unpublishing, trashing and deleting a character show up in the index, and so renaming (or changing the visibility of) a show or actor updates its characters.
- An admin-only comparison page: one input, Meilisearch results (queried from the browser with the search key) next to SearchWP `characters` engine results for the same query.

## Non-goals (deferred on purpose)

- Admin settings UI, index tuning screen, synonyms, a dead-name ACF field.
- FacetWP support.
- The front-end search modal, live search, scope picker changes.
- Shows, actors and posts indexes; multi-index search.
- A fallback for when the search server is down.
- Alternate show names (`lezshows_alt_show_name`) in character documents.
- The PHP `meilisearch-php` SDK (see Approaches).

## Anti-goals (a failure even if it works)

- Any change to SearchWP, its configuration, or the live search UI.
- A save in wp-admin that is slowed, blocked or broken by the search server.
- A key in the repository, a log line, an error message or a commit message.
- A private actor's name, or any unpublished post, reachable through the public search key.

## Constraints

- PHP 8.1+ syntax, WordPress-Extra phpcs, PHPStan (`composer analyse`) clean. `LWTV\` PSR-4 via the plugin's own autoloader.
- No new Composer runtime dependencies. The deploy artifact strips `vendor/`, and the site has none today.
- HTTP via `wp_remote_request()` only.
- Every request shape is checked against the **Meilisearch v1.57** API reference before it is used. Payloads in issue #749 were not tested against that version.
- Keys come from constants only: `LWTV_MEILI_URL`, `LWTV_MEILI_INDEX_KEY`, `LWTV_MEILI_SEARCH_KEY`, plus optional `LWTV_MEILI_INDEX_PREFIX` (defined by Mika in `wp-config.php`). None exist in the codebase yet, so these names stand.
- The **first** reindex or any write against the search server, on any index, needs Mika's go-ahead in chat. Production's `characters` index needs a separate go-ahead of its own.
- The comparison is only meaningful where SearchWP has a current `characters` index over real data, which is production (or a fresh copy of it).
- Project workflow: never commit without Mika's approval in that turn; leave branches open.

## Approaches considered

| Approach | Trade-off | Outcome |
| --- | --- | --- |
| **A. Thin home-grown client + pure builders** | ~8 endpoints in one `Client` class on `wp_remote_request()`. No dependencies, short timeouts, matches the `build/` pattern and CLAUDE.md's `wp_remote_*` rule. We own request shapes. | **Chosen.** |
| B. `meilisearch-php` SDK | Typed requests, upstream tracks API changes. Needs a PSR-18 client (Guzzle/Symfony) and a deploy change to ship `vendor/`; Guzzle version clashes with other plugins are common in WordPress. | Rejected for now. Mika leaned toward B because the end goal is replacing SearchWP, but PHP's role stays indexing-only in the end state (the browser searches directly), so the SDK's surface doesn't grow. Keeping all HTTP inside `Client` makes a later swap a one-class change. |
| C. Scry Search plugin | Routes `WP_Query` searches to Meilisearch, which collides with SearchWP on the live site. | Ruled out: breaks the side-by-side constraint. |

The SDK that will matter later is the **JavaScript** one (`meilisearch` / `instant-meilisearch`) for the modal and live search. That is a decision for the modal round.

## What the code already settles

- **Post types:** `post_type_characters` (`LWTV\CPTs\Characters::SLUG`), `post_type_shows`, `post_type_actors`.
- **Relationship fields live on the character**, and are not bidirectional:
  - `lezchars_show_group`: repeater, sub-fields `show` (post_object), `type` (`regular` | `recurring` | `guest`), `appears`.
  - `lezchars_actor`: relationship (actor IDs).
- **Reverse lookup** ("characters for this show/actor") is already indexed: `LWTV\Relationships\Store::characters_for_object( $id, 'show'|'actor' )` on the `lwtv_char_links` table. No `LIKE` queries.
- **Rename fan-out pattern** exists for FacetWP: `post_updated` in `plugins/facetwp/class-indexing.php` queues `lwtv_facet_reindex_task` (Action Scheduler), handled by `schedulers/class-facet-reindex-task.php`. Mirror it.
- **Link table sync** runs on `acf/save_post` priority 20 (`relationships/class-sync.php`). Meilisearch sync hooks later than that.
- **Actor privacy:** `lezactors_make_option_private` containing `hide_all` sets the actor's post status to `private` (`cpts/actors/class-privacy.php`). So "published" is the visibility test for actors too.
- **Logging:** `lwtv_plugin()->debug_log( '<channel>', $message )`. Channel: `meilisearch`.
- **WP-CLI:** one file per command group in `php/wp-cli/cli-*.php`, class `WP_CLI_LWTV_*`, registered as `lwtv <group>`.

## Design

### Location

`plugins/lwtv-plugin/php/meilisearch/`, namespace `LWTV\Meilisearch\`. Not under `php/plugins/`, which is for integrations with third-party WP plugins. Registered as a component the same way `Relationships` is (`_components/`, `class-plugin.php`).

Suggested layout (builder may adjust names):

```
php/meilisearch/
├── class-meilisearch.php       # boots hooks only when configured
├── class-client.php            # all HTTP lives here
├── class-config.php            # reads the constants; is_configured()
├── class-sync.php              # WP hooks → Action Scheduler
├── class-indexer.php           # WP glue: gather inputs, call builders, call client
├── class-compare-page.php      # admin-only comparison screen
├── build/
│   ├── class-character-document.php   # pure
│   └── class-index-settings.php       # pure: loads + validates the JSON
├── config/
│   └── characters.json         # index settings, versioned
└── assets/
    └── compare.js              # plain JS, no build step
```

WP-CLI: `php/wp-cli/cli-meili.php`, `WP_CLI_LWTV_Meili`, registered as `lwtv meili`.

### Configuration and the "off" state

**One index per environment (Mika's decision).** An optional fourth constant, `LWTV_MEILI_INDEX_PREFIX`, prefixes every index uid as `{PREFIX}_{type}`. Production leaves it empty (`characters`); lwtv.local uses `local` (`local_characters`) and staging `staging` (`staging_characters`). This is built for the end state of four indexes per environment (`characters`, `shows`, `actors`, `content` for posts and pages). Mika mints one indexing key and one search key per environment, scoped by index pattern (for example `local_*`) if v1.57 key patterns allow it, else per index. Every part of the module (CLI, sync, settings, comparison page, the browser request) reads the index uid from `Config::index( 'characters' )`, never a literal. `wp lwtv meili status` and every CLI write print the URL host and index name first, so it is always clear which index is being touched. This lets sync be tested end to end on lwtv.local without risk to production.

`Config::is_configured()` is true only when the three required constants are defined and non-empty. When it is false, the module registers **no** hooks and no admin page, and `wp lwtv meili` commands exit with a clear error. This is how the module stays inert on environments Mika hasn't configured.

Keys are read from constants at call time and never stored in options, transients, logs or exceptions. Log lines and CLI errors include the HTTP status and Meilisearch's error `code`/`message` but never request headers.

### The document

One document per **published** character:

```json
{
  "id": 54321,
  "name": "Mel",
  "shows": ["Slo-Pitch"],
  "actors": ["Actor Name"],
  "role": "regular",
  "role_rank": 3,
  "url": "https://lezwatchtv.com/character/mel/",
  "thumb": "https://lezwatchtv.com/wp-content/uploads/...-150x150.jpg"
}
```

Rules:

- `name`: the character's post title, entity-decoded.
- `shows`: titles of linked shows that are **published**, in field order, de-duplicated.
- `actors`: titles of linked actors that are **published and do not have `hide_all`** in `lezactors_make_option_private`. *(Builder-check call: status alone can lag the meta, since `Privacy::make()` only syncs status on save and other privacy-sensitive code checks the meta directly, so check both.)* The lookup map passed to the builder carries a `hidden` flag per actor.
- `role` / `role_rank`: the best role across the character's **published** shows: regular=3, recurring=2, guest=1. No shows or unknown type → `role` empty string, `role_rank` 0.
- `url`: permalink. `thumb`: featured image at `thumbnail` size, or empty string.
- Nothing else. Fields nobody types or filters on stay out.

`Build\Character_Document` is pure. The WP glue (`Indexer`) gathers the title, the raw show-group rows, actor IDs, and a lookup map of `id => [title, status]` for the linked shows and actors, then passes those in. Unit tests cover: published/private/draft filtering, best-role selection across shows, duplicate shows, empty fields, entity decoding.

Relationship reads use raw values (`get_field( $field, $id, false )`) to get IDs without ACF formatting. The builder must confirm what the repeater returns in raw mode (sub-field keys vs names) and normalise it.

### Index settings (`config/characters.json`)

- Primary key `id` (set at index creation).
- **Searchable attributes, in order:** `name`, `shows`, `actors`. Order is a ranking signal, so a name match beats a show match. A query like "Mel from Slo-Pitch" matches `name` and `shows` together.
- **Ranking rules:** Meilisearch's defaults, in default order, then `role_rank:desc` as the final tie-breaker. Mika's ranking is **name, then role, then show name**. Verify the v1.57 default rule names (the `attribute` rule has been split/renamed in recent versions) and write them explicitly.
- **Sortable attributes:** `role_rank` only if v1.57 requires it for a custom ranking rule (it normally doesn't).
- **Filterable attributes:** `role`.
- **Stop words:** `from`, `on`, so "Mel from Slo-Pitch" and "Mel on Slo-Pitch" behave like "Mel Slo-Pitch".
- **Displayed attributes:** all (default).

`Build\Index_Settings` loads the JSON, validates that it decodes and has the expected keys, and returns the settings payload. Tested.

### Query options (search requests)

Every search request, from the comparison page now and from the front-end search later, sends **`matchingStrategy: "frequency"`** *(Mika's decision, 2026-11-25)*. This is a per-request option, not an index setting, so it lives in the code that builds the search request (`assets/compare.js` today; the modal and live search when they are built), not in `config/characters.json`.

Why: when no document matches every word, Meilisearch drops words to find more results. The default (`last`) drops words from the end of the query; `frequency` drops the most common word first, so the rarer, more telling word survives. Tested on `local_characters` (7,357 characters):

| Query | `last` (default) | `frequency` | `all` |
| --- | --- | --- | --- |
| kara danvers | 8 characters named Kara | **Alex Danvers**, Leah Danvers, Jack Danvers | no results |
| alex danvers | Alex Danvers, then 19 other Alexes | **Alex Danvers**, then 2 other Danvers | Alex Danvers only |
| mel from slo-pitch | Mel (Slo Pitch), then 15 other Mels | **Mel (Slo Pitch)**, then the rest of the Slo Pitch cast | Mel only |
| adam tores | Adam Torres first | Adam Torres first | Adam Torres only |

There is no Kara Danvers in the database, so the useful answer is the Danvers who is there; `frequency` is the only strategy that gives it (SearchWP also did). `all` is too strict for search-as-you-type, where the last word is usually half-typed.

The front-end round should keep `frequency` unless the twenty-query comparison turns up cases where it does worse, and re-test with it whenever the searchable attributes or stop words change.

### Client

`Client` wraps only what this round needs, each checked against v1.57:

- health and version (status)
- create index, delete index, get index stats
- update settings
- add/replace documents (batch), delete one document
- get task (wait for completion in CLI only)

Every call: `wp_remote_request()`, `Authorization: Bearer <index key>`, JSON body, short timeout (about 3 s for single-document calls, longer for CLI batches). Returns the decoded body or a `WP_Error`. Never throws to callers.

### Sync

All writes during a request are **queued**, never sent inline, so the search server can't affect an editor's save. Handlers run in Action Scheduler, the same as `lwtv_facet_reindex_task`.

- **Character saved:** `acf/save_post` at priority 30 (after the link table's 20) → queue `lwtv_meili_sync_character( $id )` as a unique action. Skip revisions, autosaves, non-numeric ACF IDs.
- **Character unpublished / trashed:** `transition_post_status` for characters, leaving `publish` → same action.
- **Character deleted:** `before_delete_post` for characters → same action (by run time the post is gone).
- **`lwtv_meili_sync_character` handler (reconcile):** if the post exists and is `publish`, build and add the document; otherwise delete document `id`. One handler for every case.
- **Show renamed, or its status changed:** `post_updated` comparing old/new title and old/new status; routine edits do nothing (shows can have many characters). Queue `lwtv_meili_fanout( $id )`.
- **Actor saved:** any actor save (`acf/save_post`, priority 30) queues `lwtv_meili_fanout( $id )` as a unique action. *(Builder-check call: privacy is ACF meta that can change without a title or status change, actors have few characters each, and the work is queued, so fanning out on every actor save is cheaper than detecting the change.)*
- **`lwtv_meili_fanout` handler:** gets character IDs from `Store::characters_for_object()` (published characters only) and re-syncs them, in batches.
- **Failure:** the handler logs to the `meilisearch` channel and returns. No retry loop; `wp lwtv meili reindex --fresh` repairs drift (a plain reindex cannot remove stale documents).

### WP-CLI: `wp lwtv meili`

- `status`: is the server up, its version, the index's document count, whether settings match the repo file. Read-only.
- `settings`: create the index if missing and apply `config/characters.json`. Waits for the task.
- `reindex [--fresh] [--batch=500] [--dry-run]`: loops over published characters in batches, primes post and meta caches per batch (and the linked shows/actors), builds documents, sends each batch in one request. `--fresh` deletes and recreates the index, then applies settings first. That is also the upgrade path. `--dry-run` builds documents and prints a count and one sample, with no network. Ends by reporting the document count (from index stats, after tasks finish) and elapsed time.
- `sync <id>`: run the reconcile handler for one character inline. For testing hooks.

### Comparison page

An admin-only screen under the LezWatch.TV admin menu, `manage_options` capability.

- One text input. On input (debounced), plain JS:
  - queries Meilisearch directly from the browser: `POST {URL}/indexes/characters/search` with `Authorization: Bearer {search key}`, asking for highlighted `name`/`shows`/`actors`, with `matchingStrategy: "frequency"` (see Query options).
  - calls a small nonce-checked admin AJAX or REST endpoint that runs the **existing SearchWP `characters` engine** for the same term and returns titles, shows and permalinks. This reads SearchWP and changes nothing.
- Two columns, Meilisearch on the left and SearchWP on the right: rank, name, shows, role, a link, and each side's response time in ms.
- The search key and URL go to the page via `wp_localize_script` / inline data. The search key is public by design; the index key never reaches the browser. Per issue #749 step 1, the search key is scoped to `search` on the `characters` index only, so it cannot read anything else on the server. `wp lwtv meili status` should not try to verify key scopes (that needs the master key); the scope is Mika's to keep correct when minting keys.
- Throwaway: no styling beyond WP admin defaults.

The decision point is Mika running about twenty real queries (misspelled names, common first names, "Name from Show") through it.

## Done when

- `wp lwtv meili reindex --fresh` builds the full `characters` index from scratch and reports document count and elapsed time (record both as baselines).
- Saving, renaming, unpublishing, trashing and deleting a character each show up correctly in the index.
- Renaming a show or actor updates its characters; making an actor private removes their name from those characters.
- The comparison page returns Meilisearch results from the browser using the search key, next to SearchWP results.
- Unit tests pass for the pure builders; `composer lint` and `composer analyse` are clean.
- Mika gets a short note: what was built, what was verified by running it, and what couldn't be verified.

## Left to the builder

- Exact class and method names, and the component wiring.
- Exact v1.57 payloads and endpoint paths (verify; don't trust the issue's).
- Timeout values, batch fan-out size, the debounce interval.
- Whether the comparison page's SearchWP side is admin-ajax or REST.
