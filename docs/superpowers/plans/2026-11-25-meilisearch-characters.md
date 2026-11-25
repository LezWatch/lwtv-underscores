# Meilisearch Characters Prototype Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Meilisearch `characters` index next to SearchWP — versioned settings, a pure document builder, `wp lwtv meili` commands, queued sync hooks and an admin comparison page — so Mika can compare result quality.

**Architecture:** A self-contained module at `plugins/lwtv-plugin/php/meilisearch/` (`LWTV\Meilisearch\`). Pure transforms live in `build/` and are unit-tested first; all HTTP lives in one `Client` on `wp_remote_request()`; WordPress glue (`Indexer`, `Sync`, CLI, admin page) is verified against lwtv.local. The module registers nothing unless its constants are set.

**Tech Stack:** PHP 8.1+ (targets 8.5), WordPress 6.5+, ACF Pro, Action Scheduler via `lwtv_plugin()->schedule_task()`, WP-CLI, PHPUnit (pure tests, no WP bootstrap), plain JS (no build step), Meilisearch v1.57 REST API.

**Spec:** `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md`

## Global Constraints

- Leave SearchWP, its configuration and the live search UI untouched.
- No new Composer runtime dependencies. HTTP via `wp_remote_request()` only.
- Constants: `LWTV_MEILI_URL`, `LWTV_MEILI_INDEX_KEY`, `LWTV_MEILI_SEARCH_KEY` (required), `LWTV_MEILI_INDEX_PREFIX` (optional; empty on production). Index uids are `{PREFIX}_{type}` — `local` → `local_characters` — or just `{type}` when the prefix is empty. Always get the uid from `Config::index( 'characters' )`; never hard-code it.
- Never write a key into the repo, a log line, an error message, a CLI message or a commit message.
- A failed call to the search server must never block or break saving a post. Log to the `meilisearch` channel via `lwtv_plugin()->debug_log( 'meilisearch', ... )` and continue.
- Check every request shape against the **Meilisearch v1.57** API reference before using it.
- **Stop and ask Mika in chat before the first write to the search server on any index, and separately before the first write to production's `characters` index.** Until then, verify with `--dry-run` and unit tests.
- Text domain `lwtv-underscores`; code prefix `lwtv_`. WordPress-Extra phpcs; `composer lint`, `composer analyse` (PHPStan) and `vendor/bin/phpunit` must all pass. Regenerate the PHPStan baseline only if a new error is unavoidable, and say so.
- **No commits.** Mika commits the whole change herself at the end. Do not `git add`, `git commit` or stage per task.

## Review Focus

1. **A linked show or actor that no longer exists** (deleted post, stale ID in the repeater) → skipped silently; the document still builds. *Test in Task 2.*
2. **Titles with HTML entities** (`Kate &amp; Allie`, `&#8217;`) → indexed as plain text. *Test in Task 2.*
3. **Search server down or slow during a queued sync** → one log line with status and Meilisearch error code, no key, no exception escaping the Action Scheduler handler. *Pure error formatter tested in Task 3; handler behaviour verified in Task 5 by pointing `LWTV_MEILI_URL` at a closed port on lwtv.local.*
4. **Partially configured constants** (URL set, key empty or missing) → module inert: no hooks, no admin page, CLI errors with which constant is missing (name only, never a value). *Test in Task 1.*
5. **A character with no shows and no actors** (published mid-entry) → document with empty `shows`/`actors`, `role` `''`, `role_rank` 0. *Test in Task 2.*

---

### Task 1: Config and index settings

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-config.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-index-settings.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/config/characters.json`
- Modify: `tests/bootstrap.php` (add `require_once` lines for both classes)
- Test: `tests/unit/Meilisearch/ConfigTest.php`, `tests/unit/Meilisearch/IndexSettingsTest.php`

**Interfaces:**
- Produces:
  - `final class LWTV\Meilisearch\Config` with `__construct( string $url, string $index_key, string $search_key, string $prefix = '' )`, `static from_constants(): Config`, `is_configured(): bool`, `missing(): string[]` (constant **names** that are empty/undefined), `url(): string` (no trailing slash), `index( string $type ): string` (`'{prefix}_{type}'`, or `$type` when the prefix is empty; the prefix is trimmed of whitespace and trailing `_`), `index_key(): string`, `search_key(): string`, `host(): string` (for CLI banners). `from_constants()` reads `LWTV_MEILI_INDEX_PREFIX` if defined, else `''`.
  - `LWTV\Meilisearch\Build\Index_Settings::load( string $json ): array` — decodes and validates; throws `\InvalidArgumentException` on bad JSON or missing keys. `Index_Settings::path(): string` returns the JSON file's absolute path. `Index_Settings::PRIMARY_KEY = 'id'`.
- `characters.json` holds the **settings payload only** (what `PATCH /indexes/{uid}/settings` accepts in v1.57): `searchableAttributes` `["name","shows","actors"]`; `rankingRules` = v1.57's default rules in default order, written out explicitly, followed by `"role_rank:desc"`; `filterableAttributes` `["role"]`; `stopWords` `["from","on"]`. Add `sortableAttributes` only if v1.57 requires it for a custom ranking rule.

- [ ] **Step 1: Write the failing tests**

`ConfigTest`:
- `test_configured_when_all_three_present` — `new Config( 'https://s.example', 'ik', 'sk' )` → `is_configured()` true, `missing()` `[]`.
- `test_missing_names_constants_not_values` — `new Config( 'https://s.example', '', 'sk' )` → `is_configured()` false, `missing()` === `['LWTV_MEILI_INDEX_KEY']`; assert no element contains `'sk'`.
- `test_url_trailing_slash_trimmed` — `'https://s.example/'` → `url()` `'https://s.example'`, `host()` `'s.example'`.
- `test_no_prefix` — prefix `''` → `index( 'characters' )` === `'characters'`.
- `test_prefix_joined_with_underscore` — prefix `'local'` → `index( 'characters' )` === `'local_characters'`; prefix `'local_'` gives the same.

`IndexSettingsTest`:
- `test_repo_file_loads` — `Index_Settings::load( file_get_contents( Index_Settings::path() ) )` returns an array whose `searchableAttributes` === `['name','shows','actors']`, whose last `rankingRules` entry === `'role_rank:desc'`, and whose `stopWords` === `['from','on']`.
- `test_bad_json_throws` — `load( '{' )` throws `InvalidArgumentException`.
- `test_missing_searchable_throws` — `load( '{"rankingRules":[]}' )` throws.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter 'ConfigTest|IndexSettingsTest'`
Expected: FAIL (classes not found).

- [ ] **Step 3: Check the v1.57 settings reference, then implement `Config`, `Index_Settings` and `characters.json`**

Confirm the default ranking-rule names for v1.57 from the official docs and record the URL you checked in a comment at the top of `class-index-settings.php` (JSON can't hold comments).

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter 'ConfigTest|IndexSettingsTest'`
Expected: PASS.

---

### Task 2: Character document builder

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-character-document.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Meilisearch/CharacterDocumentTest.php`

**Interfaces:**
- Produces: `LWTV\Meilisearch\Build\Character_Document::build( int $id, string $title, $show_group, $actor_ids, array $lookup, string $url, string $thumb ): array`
  - `$show_group`: raw repeater rows, each `array( 'show' => int|string, 'type' => string )` (Task 4 normalises ACF's raw shape into this before calling).
  - `$actor_ids`: list of IDs (ints or numeric strings), or anything non-array for none.
  - `$lookup`: `int id => array( 'title' => string, 'visible' => bool )` for every linked show/actor that exists. `visible` = published (and, for actors, no `hide_all`).
  - Returns exactly the keys `id, name, shows, actors, role, role_rank, url, thumb`.
- Produces: `Character_Document::ROLE_RANKS = array( 'regular' => 3, 'recurring' => 2, 'guest' => 1 )`.

- [ ] **Step 1: Write the failing tests**

- `test_full_document_shape` — one regular show (visible), one actor (visible) → exact array with `role` `'regular'`, `role_rank` 3.
- `test_best_role_across_shows` — rows guest on show 5, recurring on show 6 → `role` `'recurring'`, `role_rank` 2.
- `test_hidden_show_ignored_for_titles_and_role` — regular on hidden show 5, guest on visible 6 → `shows` `['Six']`, `role` `'guest'`.
- `test_hidden_actor_left_out` — actors 7 (visible) and 8 (`visible` false) → `actors` `['Seven']`.
- `test_missing_lookup_entry_skipped` — show ID 99 not in `$lookup` → no error, not in `shows`. *(Review Focus 1)*
- `test_duplicate_show_listed_once` — two rows for show 5 (guest, regular) → `shows` `['Five']`, `role` `'regular'`.
- `test_entities_decoded` — title `'Kate &amp; Allie'` and show title `'Grey&#8217;s Anatomy'` → `'Kate & Allie'`, `'Grey’s Anatomy'`. *(Review Focus 2)*
- `test_no_shows_no_actors` — `$show_group` false, `$actor_ids` `''` → `shows` `[]`, `actors` `[]`, `role` `''`, `role_rank` 0. *(Review Focus 5)*
- `test_unknown_role_type` — type `'cameo'` on a visible show → show listed, `role_rank` 0.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter CharacterDocumentTest`
Expected: FAIL.

- [ ] **Step 3: Implement `Character_Document::build()`**

Decode with `html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' )` (pure PHP, no WP shim). Shows keep field order; first occurrence wins for the title, best rank wins for the role.

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter CharacterDocumentTest`
Expected: PASS.

---

### Task 3: Client

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-client.php`
- Modify: `tests/bootstrap.php` (require for the pure `describe_error()` only)
- Test: `tests/unit/Meilisearch/ClientErrorTest.php`

**Interfaces:**
- Consumes: `Config` (Task 1).
- Produces: `LWTV\Meilisearch\Client` — `__construct( Config $config )`. Each method returns the decoded response array or `\WP_Error`; none throw.
  - `health()`, `version()`
  - `create_index( string $primary_key )`, `delete_index()`, `index_stats()`
  - `update_settings( array $settings )`, `get_settings()`
  - `add_documents( array $documents, int $timeout = 30 )`, `delete_document( int $id )`
  - `get_task( int $task_uid )`, `wait_for_task( int $task_uid, int $max_seconds = 120 ): array|\WP_Error` (CLI only; polls `get_task`)
  - `static describe_error( int $status, string $body ): string` — pure: `"HTTP {status}: {code} — {message}"` from Meilisearch's JSON error body, or `"HTTP {status}"` when the body isn't JSON. Never includes headers.
- Single-document calls use a 3-second timeout; batch calls take the `$timeout` argument. `WP_Error` messages are built only from `describe_error()` or the transport's own `WP_Error` message.

- [ ] **Step 1: Write the failing tests**

- `test_meili_error_body` — `describe_error( 401, '{"message":"The provided API key is invalid.","code":"invalid_api_key","type":"auth"}' )` === `'HTTP 401: invalid_api_key — The provided API key is invalid.'`.
- `test_non_json_body` — `describe_error( 502, '<html>Bad Gateway</html>' )` === `'HTTP 502'`.
- `test_never_echoes_bearer` — body `'Bearer abc123'` → result does not contain `'abc123'`. *(Review Focus 3)*

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter ClientErrorTest`
Expected: FAIL.

- [ ] **Step 3: Check each endpoint's path, method and body against the v1.57 reference, then implement `Client`**

Note the reference URL per method in its docblock.

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter ClientErrorTest`
Expected: PASS.

---

### Task 4: Indexer, component wiring and `wp lwtv meili`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-indexer.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/class-meilisearch.php`
- Create: `plugins/lwtv-plugin/php/_components/class-meilisearch.php`
- Create: `plugins/lwtv-plugin/php/wp-cli/cli-meili.php`
- Modify: `plugins/lwtv-plugin/php/class-plugin.php` (`core_components()`: add `_Components\Meilisearch::class` after `Relationships`)
- Modify: `plugins/lwtv-plugin/php/_components/class-wp-cli.php` (add `cli-meili.php` to the loader, with a `// wp lwtv MEILI [status|settings|reindex|sync]` comment)

**Interfaces:**
- Consumes: `Config`, `Index_Settings`, `Character_Document`, `Client`.
- Produces:
  - `LWTV\Meilisearch\Indexer` — `__construct( Client $client )`; `document( int $character_id ): ?array` (null unless the character exists and is `publish`); `documents( int[] $ids ): array` (primes caches first: `_prime_post_caches()` for the characters, then for every linked show/actor ID, plus meta); `sync( int $character_id ): bool` (add document if `document()` non-null, else `delete_document()`; logs and returns false on `WP_Error`).
  - `_Components\Meilisearch implements Component` — `init()` returns early unless `Config::from_constants()->is_configured()`; otherwise boots `LWTV\Meilisearch\Meilisearch`, which instantiates `Sync` (Task 5) and `Compare_Page` (Task 6, admin only).
  - `WP_CLI_LWTV_Meili`, registered as `lwtv meili`, subcommands `status`, `settings`, `reindex [--fresh] [--batch=<n>] [--dry-run]`, `sync <id>`. Every subcommand prints `Index: {index} on {host}` first. Unconfigured → `WP_CLI::error()` naming the missing constants. (The CLI file loads even when unconfigured, so `status` can say what's missing.)
- Indexer reads relationships raw: `get_field( 'lezchars_show_group', $id, false )` and `get_field( 'lezchars_actor', $id, false )`. Raw repeater rows are keyed by ACF field keys, not names — map them to `array( 'show' => ..., 'type' => ... )` using the sub-field keys from `acf-json/group_lwtv_chars_details.json`, and say which keys in a comment. Actor visibility: `'publish' === get_post_status()` and `hide_all` not in `get_post_meta( $id, 'lezactors_make_option_private', true )`. `thumb`: `get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: ''`. Title: `get_the_title()` raw post title via `get_post_field( 'post_title', $id )` (the builder decodes).
- `reindex`: `WP_Query` over `post_type_characters`, `publish`, `fields => ids`, `no_found_rows`, paged by `--batch` (default 500). Each batch → `documents()` → one `add_documents()`; `wait_for_task()` on each before the next. `--fresh` first runs `delete_index()` (ignore 404), `create_index( Index_Settings::PRIMARY_KEY )`, `update_settings()`, waiting on each. Report document count from `index_stats()` and elapsed seconds. `--dry-run` sends nothing: prints the count and one JSON-pretty sample document.
- `status`: health, version, `index_stats()` document count, and whether `get_settings()` matches the repo file for the keys in it.

- [ ] **Step 1: Write the four files and the two modifications**

- [ ] **Step 2: Lint and analyse**

Run: `composer lint && composer analyse`
Expected: no errors.

- [ ] **Step 3: Verify the unconfigured state on lwtv.local** (see the Local wp-cli setup notes for the socket override)

With no constants defined: `wp lwtv meili status` → error naming all three constants. Load the site front end and wp-admin → no errors, no new menu item.

- [ ] **Step 4: Verify `--dry-run` on lwtv.local**

Define the constants with `LWTV_MEILI_INDEX_PREFIX` = `local`. Run: `wp lwtv meili reindex --dry-run`
Expected: `Index: local_characters on {host}`, a count equal to `wp post list --post_type=post_type_characters --post_status=publish --format=count`, and a sample document whose `shows`, `actors` and `role` match that character's edit screen. Spot-check one character linked to a private actor: the actor is absent.

- [ ] **Step 5: STOP — ask Mika for the go-ahead to write to `local_characters`**, then run `wp lwtv meili settings`, `wp lwtv meili reindex --fresh` and `wp lwtv meili status`. Record the document count and elapsed time for the final note.

---

### Task 5: Sync hooks

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-change-filter.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/class-sync.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Meilisearch/ChangeFilterTest.php`

**Interfaces:**
- Consumes: `Indexer::sync()` (Task 4), `LWTV\Relationships\Store::characters_for_object( int $id, string $type ): array`, `lwtv_plugin()->schedule_task( string $task_type, int $post_id, int $priority = 0, int $delay = 30 )`.
- Produces:
  - `LWTV\Meilisearch\Build\Change_Filter::show_needs_fanout( string $old_title, string $new_title, string $old_status, string $new_status ): bool` — true when the status changed and either side is `publish`, or the title changed to a non-empty value while published; false for `auto-draft` before-status.
  - `LWTV\Meilisearch\Sync` — hooks:
    - `acf/save_post` priority 30: character → `schedule_task( 'meili_character', $id, 0, 5 )`; actor → `schedule_task( 'meili_fanout', $id, 0, 5 )`. Skip non-numeric IDs, revisions, autosaves.
    - `transition_post_status`: character leaving `publish` → `meili_character`.
    - `before_delete_post`: character → `meili_character`.
    - `post_updated`: show where `Change_Filter::show_needs_fanout()` → `meili_fanout`.
    - `lwtv_meili_character_task( int $id )` → `Indexer::sync( $id )`.
    - `lwtv_meili_fanout_task( int $id )` → character IDs from `Store::characters_for_object( $id, 'show' | 'actor' )` by the object's post type → `Indexer::documents()` in chunks of 100 → `add_documents()`. Catch `\Throwable` in both handlers, log, return.
- Handler behaviour on a failed request: one `debug_log( 'meilisearch', ... )` line using the `WP_Error` message (already key-free), no rethrow.

- [ ] **Step 1: Write the failing tests**

- `test_rename_published_show` — `( 'A', 'B', 'publish', 'publish' )` → true.
- `test_routine_edit` — `( 'A', 'A', 'publish', 'publish' )` → false.
- `test_unpublish` — `( 'A', 'A', 'publish', 'draft' )` → true.
- `test_publish_from_draft` — `( 'A', 'A', 'draft', 'publish' )` → true.
- `test_rename_to_empty` — `( 'A', '', 'publish', 'publish' )` → false.
- `test_draft_rename` — `( 'A', 'B', 'draft', 'draft' )` → false.
- `test_auto_draft` — `( '', 'A', 'auto-draft', 'publish' )` → false.

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter ChangeFilterTest`
Expected: FAIL.

- [ ] **Step 3: Implement `Change_Filter` and `Sync`; wire `Sync` from `LWTV\Meilisearch\Meilisearch`**

- [ ] **Step 4: Run to verify they pass, then lint and analyse**

Run: `vendor/bin/phpunit --filter ChangeFilterTest && composer lint && composer analyse`
Expected: PASS, no errors.

- [ ] **Step 5: Verify on lwtv.local against `local_characters`** (Mika's go-ahead from Task 4 covers this index)

For each, run `wp action-scheduler run --group=lwtv` (or wait), then check the document by fetching it with the index key (`GET /indexes/{index}/documents/{id}` via a throwaway `wp eval`, never echoing the key) or, once Task 6 exists, by searching for it:
- Edit and save a character → its document updates.
- Rename a character → new name searchable, old name gone.
- Unpublish, then trash, then permanently delete a character → document removed at the first step and stays removed.
- Rename a show → its characters' `shows` show the new title.
- Set an actor to `hide_all` and save → the actor's name is gone from their characters.
- Set `LWTV_MEILI_URL` to `http://127.0.0.1:9` and save a character → save completes normally; the debug log has one `meilisearch` line with no key. *(Review Focus 3)* Restore the URL and run `wp lwtv meili reindex --fresh` to repair.

---

### Task 6: Comparison page

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-compare-page.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/assets/compare.js`

**Interfaces:**
- Consumes: `Config` (URL, index, search key — **never** the index key).
- Produces: submenu under `lwtv` (`add_submenu_page( 'lwtv', ... )`, capability `manage_options`, slug `lwtv_meili_compare`, title "Search Compare"). A REST route `lwtv/v1/meili-compare/searchwp?q=` (permission callback `current_user_can( 'manage_options' )`, standard REST nonce) that runs the existing SearchWP `characters` engine (`new \SearchWP\Query( $q, array( 'engine' => 'characters', 'fields' => 'ids' ) )` — confirm the SearchWP 4 API in the installed plugin) and returns up to 20 `{ name, shows, url }` items plus `ms`. When SearchWP is inactive, the route returns an empty list and a `note`.
- JS: one `<input type="search">`, 250 ms debounce. Meilisearch: `fetch( url + '/indexes/' + index + '/search', { method: 'POST', headers: { Authorization: 'Bearer ' + searchKey, 'Content-Type': 'application/json' }, body: { q, limit: 20, attributesToHighlight: [ 'name', 'shows', 'actors' ] } } )` (check the v1.57 search body). Two columns, Meilisearch left, SearchWP right: rank, name (highlighted via `_formatted`, inserted with DOM APIs and only `<em>` allowed), shows, role, link, and each side's ms. Strings go through `wp_localize_script`; user-facing labels are i18n'd in PHP.

- [ ] **Step 1: Write both files; register `Compare_Page` from `LWTV\Meilisearch\Meilisearch` when `is_admin()` or a REST request**

- [ ] **Step 2: Lint**

Run: `composer lint && composer analyse && nvm use && npx eslint plugins/lwtv-plugin/php/meilisearch/assets/compare.js`
Expected: no errors.

- [ ] **Step 3: Verify in the browser on lwtv.local**

Open LezWatch.TV → Search Compare. Type a known character name, a misspelling of it, a common first name, and "Name from Show". Expected: both columns fill; in the network panel the Meilisearch request goes straight to `LWTV_MEILI_URL` with the search key and the page source contains no index key. Check light and dark admin colour schemes stay readable.

---

### Task 7: Docs and final checks

**Files:**
- Create: `docs/architecture/meilisearch.md` — what the module does, the four constants (names only) and per-environment indexes, `wp lwtv meili` usage, sync triggers, how to rebuild, what's deferred.
- Modify: `docs/README.md` (index entry), `docs/plugin-structure.txt` (new folder).

- [ ] **Step 1: Write the docs**

- [ ] **Step 2: Full suite**

Run: `vendor/bin/phpunit && composer lint && composer analyse && npm run lint:js`
Expected: all pass.

- [ ] **Step 3: Write the note for Mika** (in chat, not a file): what was built, what was verified by running it (with the Task 4 count and elapsed time), what couldn't be verified (production index, SearchWP column against production data), and the go-ahead still needed for production's `characters` index.
