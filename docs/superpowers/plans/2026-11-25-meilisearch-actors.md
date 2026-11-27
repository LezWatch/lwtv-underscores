# Meilisearch Actors Index Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An `actors` Meilisearch index (third after characters and shows), plus an "Other names" ACF field that replaces the actor excerpt, and a manual migration into it.

**Architecture:** `Actors_Index` extends the existing `Index` base and is registered in `Indexes`; its document is decided by a pure `Build\Actor_Document`. Sync gains actor routes in `Build\Sync_Routes` plus two capture points in `Sync`. The migration is a new `othernames` type on `wp lwtv migrate`, with its list-vs-prose decision in a pure `Build\Other_Names`.

**Tech Stack:** PHP 8.1+, WordPress, ACF Pro (JSON field groups), Action Scheduler via `lwtv_plugin()->schedule_task()`, Meilisearch v1.57 over `Client`, PHPUnit (no WP bootstrap), WP-CLI.

**Spec:** `docs/superpowers/specs/2026-11-25-meilisearch-actors-design.md`

## Global Constraints

- PHP 8.1+; WordPress-Extra phpcs (`composer lint`) and PHPStan (`TMPDIR=<scratchpad> composer analyse`) clean.
- No new Composer runtime dependencies. HTTP only through `LWTV\Meilisearch\Client`.
- Request shapes checked against Meilisearch v1.57.
- Index uid from `Config::index( 'actors' )`; `id` filterable.
- Pure logic in `plugins/lwtv-plugin/php/meilisearch/build/`, test first; tests in `tests/unit/Meilisearch/`, each new class `require_once`d in `tests/bootstrap.php`.
- Text domain `lwtv-underscores`.
- Never a key in code, logs or commits. A failed search call never breaks a save.
- SearchWP code and engines untouched.
- `group_lwtv_actors_details.json` keeps `"show_in_rest": 0`; the new field is not added to `CPTs\Post_Meta` with `show_in_rest`.
- Ask Mika before the first write to any index and before the migration writes to any database (lwtv.local included). No commits: Mika reviews and commits the whole diff. The "Commit" steps below are checkpoints: run the checks, don't `git commit`.
- Local wp-cli: `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" /opt/homebrew/bin/wp --path=/Users/ipstenu/Websites/Local/lwtv-new/app/public` (lwtv.local's theme symlink points at the main checkout, not this worktree; see Task 8).

## Review Focus

1. `hide_all` actor whose status is still `publish` gets no document (glue; checked live in Task 8, and `Index::lookup()`'s existing test path is reused).
2. Birth dates in `Y-n-j` (`1975-1-25`), `Ymd`, `0000-00-00` or junk → right year or none (Task 1 tests).
3. An excerpt like `Robert Downey, Jr.` must not split into a bogus name `Jr.` (Task 2 test).
4. An other name equal to the current title is dropped, so the document doesn't repeat the name (Task 1 test).
5. The Search Compare REST response for actors must not carry `other_names`, even to admins (Task 6 check).

---

### Task 1: `Build\Actor_Document`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-actor-document.php`
- Create: `tests/unit/Meilisearch/ActorDocumentTest.php`
- Modify: `tests/bootstrap.php` (require after `class-show-document.php`)

**Interfaces:**
- Produces: `Actor_Document::build( int $id, string $title, $other_rows, array $characters, array $shows, $birth, $death, $privacy, string $bio, $queer, $char_count, string $url, string $thumb ): array` returning keys in this order: `id, name, other_names, characters, shows, years, bio, queer, character_count, url, thumb`.
- Produces: `Actor_Document::year( $value ): string` (four-digit year or `''`), `Actor_Document::other_names( $rows, string $name ): string[]`.
- Constant `OTHER_NAME_KEYS = array( 'field_lwtv_lezactors_other_name', 'lezactors_other_name' )`.

- [ ] **Step 1: Write failing tests** (use a `build( array $overrides )` helper like `ShowDocumentTest`):
  - `test_shape`: all keys present, in order; `queer` and `character_count` are ints.
  - `test_name_decoded`: `'Rosie O&#039;Donnell '` → `"Rosie O'Donnell"`.
  - `test_other_names_by_key_and_name`: rows `[ ['field_lwtv_lezactors_other_name' => 'Ellen Page'], ['lezactors_other_name' => 'E. Page'] ]` → `['Ellen Page','E. Page']`; `false`/`null`/non-array rows → `[]`; empty and duplicate values dropped; entities decoded.
  - `test_other_name_equal_to_title_dropped`: title `Elliot Page`, rows `[ 'elliot page', 'Ellen Page' ]` → `['Ellen Page']` (case-insensitive).
  - `test_years` (data provider), birth/death → years: `'1968-07-07',''`→`'1968'`; `'1975-1-25',''`→`'1975'`; `'19750125',''`→`'1975'`; `'','2020-03-01'`→`'2020'`; `'1940-01-01','2020-03-01'`→`'1940 2020'`; `'0000-00-00',''`→`''`; `'unknown',''`→`''`; `null,false`→`''`.
  - `test_hide_dob_drops_birth_keeps_death`: privacy `['hide_dob']`, birth `1940-01-01`, death `2020-03-01` → `'2020'`; privacy `'hide_dob'` (string, not array) → birth kept.
  - `test_bio_collapsed`: `"  Line one.\n\n Line&nbsp;two &amp; more "` → `'Line one. Line two & more'`.
  - `test_queer`: `'1'`, `1`, `true` → `1`; `''`, `'0'`, `false`, `null` → `0`.
  - `test_char_count`: `'4'` → `4`; `null`/`'abc'`/`-2` → `0`.
  - `test_characters_and_shows_deduped`: `['Sara Sidle','Sara Sidle','A &amp; B','']` → `['Sara Sidle','A & B']`.

- [ ] **Step 2: Run, expect failure**

Run: `vendor/bin/phpunit --filter ActorDocumentTest`
Expected: FAIL, class `LWTV\Meilisearch\Build\Actor_Document` not found.

- [ ] **Step 3: Implement** in `class-actor-document.php`, namespace `LWTV\Meilisearch\Build`, `ABSPATH` guard, file docblock in the module's style (pure; other names are searchable but never displayed; years only, birth dropped under `hide_dob`). `year()`: match `/^(\d{4})/`, reject `0000`. Normalizing strings: decode (`ENT_QUOTES | ENT_HTML5`, UTF-8), replace `\xC2\xA0` with a space, collapse `\s+`, trim, de-duplicate in order (same as `Show_Document::names()`).

- [ ] **Step 4: Run, expect pass**

Run: `vendor/bin/phpunit --filter ActorDocumentTest` → OK.

- [ ] **Step 5: Checkpoint** — `composer lint` clean for the new files.

### Task 2: `Build\Other_Names`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-other-names.php`
- Create: `tests/unit/Meilisearch/OtherNamesTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Produces: `Other_Names::from_excerpt( string $excerpt ): ?array` — `string[]` of names to move, `array()` for blank text, `null` for "needs a human".

- [ ] **Step 1: Write failing tests** (data provider, input → expected):
  - `'Ellen Page'` → `['Ellen Page']`
  - `'Courteney Cox Arquette, Courteney Cox-Arquette'` → both
  - `"Name One\nName Two\r\nName Three"` → three names
  - `'Name One, , Name One'` → `['Name One']`
  - `'Zoë &amp; Co'` → `['Zoë & Co']`
  - `'   '` → `[]`
  - `'Robert Downey, Jr.'` → `null` (a piece that is one word ending in `.`)
  - `'https://example.com/x'` → `null`
  - `'"A quote from her"'` and `'She said: hello'` → `null`
  - `'She starred in many films. Later she moved.'` → `null`
  - `'One two three four five six'` → `null` (piece over five words)
  - `'Mary-Kate Olsen'` → `['Mary-Kate Olsen']`

- [ ] **Step 2: Run, expect failure** — `vendor/bin/phpunit --filter OtherNamesTest`.

- [ ] **Step 3: Implement.** Decode entities, then reject the whole text (→ `null`) if it matches a URL (`#https?://|www\.#i`), a quote mark (`["“”]`), a colon, or `/\.\s+\p{Lu}/u`. Split on `/[\r\n,]+/`, trim, drop empties, de-duplicate. Reject if any piece has more than 5 words or is a single word ending in `.`.

- [ ] **Step 4: Run, expect pass.**

- [ ] **Step 5: Checkpoint** — lint.

### Task 3: Settings file and sync routes

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/config/actors.json`
- Modify: `plugins/lwtv-plugin/php/meilisearch/build/class-sync-routes.php`
- Modify: `tests/unit/Meilisearch/IndexSettingsTest.php`, `tests/unit/Meilisearch/SyncRoutesTest.php`, `tests/unit/Meilisearch/TypeOptionTest.php`

**Interfaces:**
- Produces: `Sync_Routes::EVENTS = array( 'acf_save', 'changed', 'delete', 'score', 'calculated' )`.
- Produces routes (registry order characters, shows, actors):
  - `acf_save`: character `[characters, shows, actors]`, show `[shows]`, actor `[characters, shows, actors]`
  - `changed`: all three `[characters, shows, actors]`
  - `delete`: character `[characters]`, show `[shows]`, actor `[actors]`
  - `score`: show `[shows]`
  - `calculated`: actor `[actors]`

- [ ] **Step 1: Write failing tests.**
  - `IndexSettingsTest::test_actors_file_loads`: searchable `['name','other_names','characters','shows','years','bio']`; displayed `['id','name','characters','shows','years','bio','queer','character_count','url','thumb']` and `other_names` not in it; ranking ends `['exactness','queer:desc','character_count:desc']` after the same defaults as `characters.json`; filterable `['id','queer']`; stop words `['from','on','in']`.
  - `SyncRoutesTest`: update the existing assertions to the routes above; add `test_calculated`; `test_events_listed` expects the five events.
  - `SyncRoutesTest::test_every_route_is_a_registry_type` (the shows hand-over's deferred minor): every type in every route is one of `['characters','shows','actors']`.
  - `TypeOptionTest`: `KNOWN` gains `actors`; the typo message expects `'characters, shows, actors, all'`.

- [ ] **Step 2: Run, expect failures** — `vendor/bin/phpunit --filter 'IndexSettings|SyncRoutes|TypeOption'`.

- [ ] **Step 3: Write `actors.json`** with the values above (ranking: `words, typo, proximity, attributeRank, sort, wordPosition, exactness, queer:desc, character_count:desc`) and update `Sync_Routes` (docblock: actor documents quote a show's title and visibility only, so a routine show save doesn't fan out to actors).

- [ ] **Step 4: Run, expect pass**, then the whole suite: `vendor/bin/phpunit` → OK.

- [ ] **Step 5: Checkpoint** — lint.

### Task 4: Other names field, excerpt bridge, migration command

**Files:**
- Modify: `plugins/lwtv-plugin/acf-json/group_lwtv_actors_details.json` (replace the `field_lwtv_actors_excerpt` object in place; bump `modified`)
- Modify: `plugins/lwtv-plugin/php/plugins/class-acf.php` (`save_excerpt_to_post`: `$cpts = array( 'post_type_shows' )`; comment at line 72 says shows)
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-migrate.php`

**Interfaces:**
- Consumes: `Other_Names::from_excerpt()` (Task 2).
- Produces: repeater `lezactors_other_names` (`field_lwtv_lezactors_other_names`, label "Other Names", layout `table`, button "Add Another Name") with text sub-field `lezactors_other_name` (`field_lwtv_lezactors_other_name`, label "Name", `parent_repeater` set). Instructions: "Names this actor may be searched by: a former name (including a dead name), a married name, a stage name. Never shown on the site; only used to match searches."
- Produces: `wp lwtv migrate othernames [--dry-run] [--yes] [--show-review] [--clear=<ids>] [--format=<format>]`.

- [ ] **Step 1: Edit the ACF JSON**; validate with `python3 -m json.tool` and confirm `show_in_rest` is still `0`.

- [ ] **Step 2: Narrow the bridge** in `class-acf.php`.

- [ ] **Step 3: Add the migration.** In `run_migrator()` add `elseif ( 'othernames' === $type )`; parse `show-review` and `clear` flags in `__invoke()`; document them in the `__invoke` docblock (options list and examples).
  - `migrate_othernames()`: candidates = actors of any status except `auto-draft`/`inherit` with `TRIM(post_excerpt) <> ''` (one `$wpdb` query, `ORDER BY ID`). Per candidate: `skip` if `get_field( 'lezactors_other_names', $id, false )` has rows; else `Other_Names::from_excerpt()` → `null` = `review`, `[]` = `clear` (blank text), names = `move`.
  - Output with `\WP_CLI\Utils\format_items( $this->format, $rows, array( 'id', 'title', 'status', 'decision', 'names' ) )`; `names` is a count. Never print names or excerpt text unless `--show-review`, which additionally logs, for each `review`/`skip` row, the ID, edit link and full excerpt.
  - Real run (not `--dry-run`): `\WP_CLI::confirm()` unless `--yes`; for `move`: `update_field( 'field_lwtv_lezactors_other_names', array_map( fn( $n ) => array( 'field_lwtv_lezactors_other_name' => $n ), $names ), $id )`, then a private `clear_excerpt( int $id )`; for `clear`: `clear_excerpt()` only. `clear_excerpt()`: `$wpdb->update` `post_excerpt` to `''`, `delete_post_meta( $id, 'excerpt' )`, `delete_post_meta( $id, '_excerpt' )`, `clean_post_cache( $id )`.
  - `--clear=<ids>`: comma list; each must be a `post_type_actors` post, else warn and skip; runs `clear_excerpt()` (or reports under `--dry-run`); does nothing else.
  - Finish with a summary line (moved / review / skip / cleared) and: "Next: rebuild SearchWP's index, then `wp lwtv meili reindex --type=actors`."

- [ ] **Step 4: Verify** — `composer lint` and `TMPDIR=<scratchpad> composer analyse` clean (regenerate nothing in the baseline unless PHPStan flags a new key). Migration behaviour is verified live in Task 8, never against lwtv.local without Mika's go-ahead.

- [ ] **Step 5: Checkpoint.**

### Task 5: `Actors_Index`, registry and sync

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-actors-index.php`
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-indexes.php` (add after shows)
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-sync.php`
- Modify: `plugins/lwtv-plugin/php/cpts/actors/class-calculations.php` (fire `do_action( 'lwtv_actor_calculated', $post_id )` after the three `update_post_meta` calls)
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-meili.php` (help text: `characters, shows, actors, or all`)

**Interfaces:**
- Consumes: `Actor_Document::build()` (Task 1), routes (Task 3).
- Produces: `Actors_Index::TYPE = 'actors'`; `affected_by()` per the spec table; `documents( int[] )`.

- [ ] **Step 1: `Actors_Index`.** Mirror `Shows_Index`. `documents()`: normalize IDs, `_prime_post_caches( $ids, false, true )`; keep only `Actors::SLUG` + `publish` + not `hide_all` (read `lezactors_make_option_private`); characters via `Store::characters_for_object( $id, 'actor' )`, prime them; shows via `Store::objects_for_character( $c, 'show' )`, prime them; titles via `get_post_field( 'post_title' )`; bio = `wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) )`; raw values `get_field( 'lezactors_other_names', $id, false )`, `get_post_meta` for `lezactors_birth`, `lezactors_death`, `lezactors_make_option_private`, `lezactors_queer`, `lezactors_char_count`.

- [ ] **Step 2: Register** in `Indexes`.

- [ ] **Step 3: Sync.**
  - Add `private array $actors_before = array();`. `before_acf_save` records `Store::objects_for_character( $id, 'actor' )` for characters alongside shows; `on_acf_save` queues `Actors_Index::TYPE` for each, then unsets.
  - `on_delete`: for a character, also queue actors for `Store::objects_for_character( $id, 'actor' )`.
  - Register `add_action( 'lwtv_actor_calculated', array( $this, 'on_actor_calculated' ) )`; handler routes event `calculated`.
  - Update the class docblock.

- [ ] **Step 4: Verify** — `vendor/bin/phpunit`, `composer lint`, `TMPDIR=<scratchpad> composer analyse`; `php -l` each file.

- [ ] **Step 5: Checkpoint.**

### Task 6: Search Compare

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-compare-page.php`
- Modify: `plugins/lwtv-plugin/php/meilisearch/assets/compare.js`

**Interfaces:**
- Consumes: registry (Task 5). The type switch and REST `enum` already come from `Indexes::types()`.

- [ ] **Step 1: REST hits.** In `searchwp_results()`, drop keys not in the index's displayed attributes: read `displayedAttributes` from `Index_Settings::load( file_get_contents( $index->settings_path() ) )`; when present and not `['*']`, `array_intersect_key` each hit with it. This keeps `other_names` out of the admin JSON.

- [ ] **Step 2: `compare.js`.** `highlight.actors = [ 'name', 'characters', 'shows' ]`. In `render()`, an actor hit (has `character_count`) shows: `years`, a "Queer" label when `queer` (new `cfg.labels.queer`, i18n in PHP), then the first three highlighted characters. Never reads `other_names`.

- [ ] **Step 3: Verify** — `npm run lint:js` (after `nvm use`), `composer lint`.

- [ ] **Step 4: Checkpoint.**

### Task 7: Docs

**Files:**
- Modify: `docs/architecture/meilisearch.md` (actors document, settings, matrix column, sync rows, `calculated` event, other names never displayed, SearchWP excerpt behaviour accepted)
- Modify: `docs/runbooks/meilisearch.md` (first run for actors, rollout order from the spec, migration flags, revisions note, production key needing `actors`)
- Modify: `docs/plugin-structure.txt` (new files; regenerate with `npm run docs:structure` if that's how it's maintained, then `npm run docs:structure:check`)
- Modify: the actor ACF/field docs if any list actor fields (`grep -rn "Additional Notes\|actors_excerpt" docs`)

- [ ] **Step 1: Write the docs.**
- [ ] **Step 2: Verify** — `npm run docs:structure:check` passes.
- [ ] **Step 3: Checkpoint.**

### Task 8: Live verification on lwtv.local

Each write step needs Mika's go-ahead in chat first.

- [ ] **Step 1: Point lwtv.local at this worktree** (temporary theme symlink swap, as the shows round did) and note how to restore it.
- [ ] **Step 2: Migration dry run** — `wp lwtv migrate othernames --dry-run` and `--show-review`; record counts. **Ask**, then run it; second dry run reports nothing to move.
- [ ] **Step 3: Index** — **ask**, then `wp lwtv meili settings --type=actors`, `wp lwtv meili reindex --type=actors`; `status --type=actors` reports settings match; count ≈ 6,107; the 2 `hide_all` IDs absent (`GET /indexes/local_actors/documents/{id}` → 404).
- [ ] **Step 4: Privacy checks with the search key** — search a moved other name: actor found, response has no `other_names`, no former name in `_formatted`, nothing in `_matchesPosition` naming it. A `hide_dob` actor's document has no birth year.
- [ ] **Step 5: Throwaway-post sync test** — the sequence in the spec's Verification section; check each step's documents; delete the test posts and documents.
- [ ] **Step 6: Search Compare** in the browser, Actors tab, against SearchWP; no console errors.
- [ ] **Step 7: Final checks** — `vendor/bin/phpunit`, `composer lint`, `TMPDIR=<scratchpad> composer analyse`, `npm run lint:js`, `npm run docs:structure:check`. Restore the symlink. Write the hand-over note in the shared notes folder.
