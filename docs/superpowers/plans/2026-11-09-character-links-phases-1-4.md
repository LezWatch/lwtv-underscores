# Character Links (Shadow Taxonomy Phases 1–4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `shadow_tax_characters` reverse index with a `{prefix}lwtv_char_links` table. Every reader goes through one repository class. Reads keep coming from the shadow taxonomy until a CLI switch flips them to the table.

**Architecture:** A pure `Link_Rows` transform turns ACF field values into link rows. `Store` owns the table's SQL. `Sync` writes a character's rows on save. `Character_Links` is the only read API, and it serves rows from the shadow taxonomy or the table depending on an option. The shadow terms keep being written alongside the table (dual-write). Removing the library is phase 5 and ships separately.

**Tech Stack:** WordPress 6.5+, PHP 8.1+, ACF, `$wpdb` with `dbDelta()`, WP-CLI, PHPUnit (pure tests only).

**Spec:** `docs/plans/shadow-taxonomy-removal.md`, phases 0–4 plus "Legacy character links".

## Global Constraints

- Namespace `LWTV\Relationships`, autoloaded from `plugins/lwtv-plugin/php/relationships/`. Files are `class-<lowercase-hyphenated>.php`, one class per file.
- Table `{$wpdb->prefix}lwtv_char_links`: `character_id BIGINT UNSIGNED NOT NULL`, `object_id BIGINT UNSIGNED NOT NULL`, `object_type VARCHAR(20) NOT NULL`, `PRIMARY KEY (character_id, object_type, object_id)`, `KEY reverse (object_id, object_type)`.
- `object_type` is exactly `'show'`, `'actor'` or `'character'`.
- Schema version option `lwtv_char_links_db_version`, current value `'1'`.
- Read source option `lwtv_char_links_source`, values `'shadow'` (default) and `'table'`.
- `Sync` rewrites **only** `show` and `actor` rows. The legacy import is the only writer of `character` rows.
- Table reads return published posts only (`JOIN {$wpdb->posts} … post_status = 'publish'`).
- **Do not commit.** The user reviews and commits the whole change at the end (CLAUDE.md and saved feedback). Each task ends with lint and tests, not a commit.
- Before calling any task done, run `composer lint`, `composer analyse` (CI runs PHPStan) and `vendor/bin/phpunit`.
- WordPress glue is verified on lwtv.local with wp-cli (socket override, see `docs/testing.md` and the local setup notes). **Local has to be running.**

## Review Focus

1. **Legacy repeater shapes.** A `lezchars_show_group` row can hold `'show' => [123]` (pre-CMB2), `'show' => []`, a string `'123'`, or no `show` key. Each should give one show row or none, without a warning. Covered in Task 1.
2. **A character listed twice on the same show or actor.** It should produce one row, not a primary-key error. Covered in Task 1, and by `INSERT IGNORE` in Task 2.
3. **Self-reference and empty IDs in `lezchars_relationship_chart`.** X listing X, or `0`/`''`, is dropped. Covered in Task 1.
4. **A character saved with every show and actor removed.** Its show and actor rows go, and its `character` rows stay. Live check in Task 4.
5. **Drafted or trashed characters.** They disappear from table reads without any write, and come back on untrash. Live check in Task 6.

---

### Task 1: `Link_Rows` pure transform

**Files:**
- Create: `plugins/lwtv-plugin/php/relationships/build/class-link-rows.php`
- Modify: `tests/bootstrap.php` (add `require_once` for the new file next to the other `build/` requires)
- Test: `tests/unit/Relationships/LinkRowsTest.php`

**Interfaces:**
- Produces (namespace `LWTV\Relationships\Build`, all `public static`):
  - `from_fields( int $character_id, mixed $show_group, mixed $actors ): array` returns a list of `array{character_id:int, object_id:int, object_type:string}` (show rows first, then actor rows), with duplicates removed.
  - `legacy_characters( int $character_id, mixed $relationship_chart, array $holders_of_my_term ): array` returns rows of type `'character'`, using the direction table in the spec (chart entry Y → `(X, Y)`; holder Y → `(Y, X)`).
  - `ids_of( array $rows, string $object_type ): int[]` returns sorted unique `object_id`s of one type.
  - `compare( array $expected, array $actual ): array{missing:int[], extra:int[]}` treats both as int ID sets; results are sorted.

- [ ] **Step 1: Write the failing tests** in `LinkRowsTest`:
  - `test_show_and_actor_rows`: `from_fields( 10, [ ['show'=>5], ['show'=>'6'] ], ['7','8'] )` equals the rows `(10,5,'show')`, `(10,6,'show')`, `(10,7,'actor')`, `(10,8,'actor')`.
  - `test_legacy_repeater_shapes`: `[ ['show'=>[5]], ['show'=>[]], ['nope'=>1], ['show'=>''] ]` gives only `(10,5,'show')`.
  - `test_duplicates_collapse`: `[ ['show'=>5], ['show'=>5] ]` and actors `['7',7]` give one row each.
  - `test_non_arrays_give_nothing`: `from_fields( 10, false, '' )` returns `[]`.
  - `test_legacy_characters_both_directions`: `legacy_characters( 10, ['11','10',0,''], [12, 10] )` equals `(10,11,'character')` and `(12,10,'character')`. Self-references and empties are dropped.
  - `test_legacy_scalar_chart`: `legacy_characters( 10, '11', [] )` equals `(10,11,'character')`.
  - `test_compare`: `compare( [3,1,2], [2,4] )` equals `['missing'=>[1,3], 'extra'=>[4]]`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter LinkRows`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement `Link_Rows`.** Keep it pure: no WordPress calls. Cast IDs with `(int)` and drop anything `< 1`. Deduplicate on the `"type:id"` key.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter LinkRows`. Expected: PASS. Then run `composer lint`.

### Task 2: `Store` (table, install, SQL)

**Files:**
- Create: `plugins/lwtv-plugin/php/relationships/class-store.php`
- Create: `plugins/lwtv-plugin/php/_components/class-relationships.php` (a `Component`; `init()` hooks `Store::maybe_install` on `admin_init` and, when `WP_CLI` is defined, on `init`)
- Modify: `plugins/lwtv-plugin/php/class-plugin.php` (add `_Components\Relationships::class` to `core_components()` straight after `_Components\Plugins::class`)

**Interfaces:**
- Consumes: the row shape from Task 1.
- Produces (namespace `LWTV\Relationships`, class `Store`, all `public static`):
  - `table(): string`
  - `maybe_install(): void`: runs `dbDelta()` when the `lwtv_char_links_db_version` option is not `'1'`, then sets it.
  - `replace_for_character( int $character_id, array $rows, array $types ): void`: in one transaction, `DELETE … WHERE character_id = %d AND object_type IN (…types)`, then `INSERT IGNORE` the rows. Rows whose `character_id` differs from `$character_id` are skipped.
  - `insert( array $rows ): void`: `INSERT IGNORE`, for the legacy import.
  - `objects_for_character( int $character_id, string $object_type ): int[]`: forward lookup (published objects).
  - `characters_for_object( int $object_id, string $object_type ): int[]`: reverse lookup (published characters).
  - `characters_linked_to_character( int $character_id ): int[]`: both directions of `'character'` rows, published, unique.
  - `delete_for_post( int $post_id ): void`: deletes rows where the post is the `character_id`, or is the `object_id` of any type.
  - `count(): int`

- [ ] **Step 1: Implement `Store`** using `$wpdb->prepare()` for every query, and placeholders built with `implode( ',', array_fill( 0, count( $types ), '%s' ) )`. Wrap `replace_for_character` in `START TRANSACTION`/`COMMIT`, with `ROLLBACK` if any query returns `false`, and log failures with `lwtv_plugin()->debug_log( 'char-links', … )`. Add `'char-links'` to the debug log topic list wherever `'shadow-taxonomy'` is registered (`admin-menu/class-debugging.php`), and to the `VALID` list in `LogRulesTest`.
- [ ] **Step 2: Implement the `Relationships` component and register it.**
- [ ] **Step 3: Verify live.** Run `wp eval 'LWTV\Relationships\Store::maybe_install();'`, then `wp db query "SHOW CREATE TABLE wp_lwtv_char_links"`. Expected: both keys exactly as in Global Constraints. Run `wp option get lwtv_char_links_db_version`. Expected: `1`.
- [ ] **Step 4: Run** `composer lint && composer analyse && vendor/bin/phpunit`. Expected: clean.

### Task 3: `Character_Links` repository (shadow source) and routing every reader through it

**Files:**
- Create: `plugins/lwtv-plugin/php/relationships/class-character-links.php`
- Modify: `theme/class-show-characters.php`, `theme/class-actor-characters.php`, `theme/class-character-relationships.php`, `plugins/class-cache.php`, `schedulers/class-facet-reindex-task.php`, `cpts/characters/class-calculations.php`, `queeries/class-shadow-taxonomy.php` (all paths under `plugins/lwtv-plugin/php/`)

**Interfaces:**
- Consumes: `Store` from Task 2 (table branch only).
- Produces (namespace `LWTV\Relationships`, class `Character_Links`, instance methods):
  - `const SOURCE_OPTION = 'lwtv_char_links_source'`
  - `source(): string` returns `'table'` only when the option is exactly `'table'`, otherwise `'shadow'`.
  - `characters_for_show( int $show_id ): int[]`
  - `characters_for_actor( int $actor_id ): int[]`
  - `shows_for_character( int $character_id ): int[]`
  - `actors_for_character( int $character_id ): int[]`
  - `characters_referencing( int $character_id ): int[]` returns the union of both directions, including `lezchars_relationship_chart`, with the character itself excluded.
  - In this task, every method has a private `shadow_*` implementation. The table branch only delegates to `Store` (it gets switched on in Task 6).
  - Shadow implementations: for an object, use `wp_get_object_terms( $id, Characters::SHADOW_TAXONOMY, ['fields'=>'ids'] )` and map each term through `get_term_meta( $term_id, 'shadow_shadow_tax_characters_post_id', true )`. For a character, read its term via the `shadow_shadow_tax_characters_term_id` postmeta and use `get_objects_in_term()`, filtered by post type. Return any post status, same as today. The consumers already filter by status.

- [ ] **Step 0: Take a baseline before touching any code.** Use `wp eval` to print, for three shows (a large one such as The L Word, a small one, and one with dead characters) and three actors, the `make()` character list for each show and `count( lwtv_plugin()->get_actor_characters( ID ) )` for each actor. Save the output to `<scratchpad>/seam-before.txt`.
- [ ] **Step 1: Implement `Character_Links`** as described above. Use `Link_Rows::legacy_characters` plus `ids_of`, so `characters_referencing` builds the shadow-side union in a single code path.
- [ ] **Step 2: Route the readers through it:**
  - `Show_Characters::make()` becomes `$characters = ( new Character_Links() )->characters_for_show( $post_id );`. Delete `get_characters_from_shadow_tax`, `get_characters_from_taxonomy` and `get_characters_from_post_meta` (their fallbacks were dead or broken). Grep for callers first.
  - `Actor_Characters::generate_list()` becomes `characters_for_actor()`. Delete the same three helpers on that class after grepping for callers. `lezactors_char_list` is still written by `build_character_info()`.
  - `Character_Relationships::make()` becomes `characters_referencing()`, and keeps its publish filter.
  - `Cache` (both `get_the_posts` sites) and `Facet_Reindex_Task::get_character_ids()` become `characters_for_show` or `characters_for_actor`, chosen by `get_post_type()`.
  - `Characters\Calculations::sync_shows()` and `sync_actors()` get their current lists from `shows_for_character()` and `actors_for_character()` instead of the `Shadow_Taxonomy` queery. **Return early** when `get_associated_term()` gave `false`: log `debug_log( 'shadow-taxonomy', "No shadow term for character {$id}" )` and still run the show and actor `do_the_math()` calls.
- [ ] **Step 3: Remove the writes from read paths.** Turn `Show_Characters::clean_character_array()` into a pure pass-through, or delete it and its call site and update the comment that mentions it. Delete the `wp_remove_object_terms` block in `Actor_Characters::build_character_info()`.
- [ ] **Step 4: Delete the dead queery methods.** In `queeries/class-shadow-taxonomy.php`, keep only what is still called. If `get_shows_for_character` and `get_actors_for_character` no longer have callers after Step 2, delete the whole class. Check with `grep -rn "Queeries\\\\Shadow_Taxonomy\|_for_character_cached\|_for_characters_batch" plugins/lwtv-plugin/php`, which should come back empty.
- [ ] **Step 5: Verify live (source = shadow, so nothing should change).** Run Step 0's script again and save the output to `seam-before.txt`'s sibling `seam-after.txt`. `diff` the two files: they should be identical. Load those pages over HTTPS: they should return 200 with no PHP warnings in `debug.log`. Confirm that loading a show page writes nothing: `wp db query "SELECT COUNT(*) FROM wp_term_relationships"` should be unchanged.
- [ ] **Step 6: Run** `composer lint && composer analyse && vendor/bin/phpunit`. Expected: clean. Remove any PHPStan baseline entries that no longer match.

### Task 4: `Sync` dual-write

**Files:**
- Create: `plugins/lwtv-plugin/php/relationships/class-sync.php`
- Modify: `_components/class-relationships.php` (construct `Sync`), `cpts/characters/class-calculations.php` (`do_the_math()` calls `Sync::character()`)

**Interfaces:**
- Consumes: `Link_Rows::from_fields`, `Store::replace_for_character`, `Store::delete_for_post`.
- Produces (class `LWTV\Relationships\Sync`):
  - `__construct()` hooks `acf/save_post` at priority `20` to `on_acf_save( $post_id )`, and `before_delete_post` to `on_delete( int $post_id )`.
  - `public static character( int $character_id ): void` reads `get_field( 'lezchars_show_group', $id )` and `get_field( 'lezchars_actor', $id )`, then calls `Store::replace_for_character( $id, Link_Rows::from_fields(…), [ 'show', 'actor' ] )`.
  - `on_acf_save( $post_id )` returns early unless `is_numeric( $post_id )` and the post type is `Characters::SLUG`. This skips ACF options pages, whose IDs are strings like `'options'`.

- [ ] **Step 1: Implement `Sync`** and wire it in. Keep the shadow writes in `Calculations` untouched: this is the dual-write period.
- [ ] **Step 2: Verify live.** Run `wp eval 'LWTV\Relationships\Sync::character( <ID> );'` on a character with two shows and one actor, then `SELECT * FROM wp_lwtv_char_links WHERE character_id=<ID>`. Expected: 3 rows. Run it a second time: still 3 rows. Insert a fake legacy row `(ID, <other char>, 'character')`, clear that character's shows and actors in wp-admin, and save. Expected: only the `character` row is left (Review Focus #4). Restore the character afterwards.
- [ ] **Step 3: Run** `composer lint && composer analyse && vendor/bin/phpunit`.

### Task 5: `wp lwtv links` CLI (rebuild, legacy import, diff, source)

**Files:**
- Create: `plugins/lwtv-plugin/php/wp-cli/cli-links.php` (class `WP_CLI_LWTV_Links`, registered with `\WP_CLI::add_command( 'lwtv links', 'WP_CLI_LWTV_Links' )`)
- Modify: `_components/class-wp-cli.php` (add the loader line with the comment `// wp lwtv LINKS [rebuild|diff|source]`), `docs/plugin-structure.txt`

**Interfaces:**
- Consumes: `Sync::character`, `Link_Rows::legacy_characters`, `Link_Rows::compare`, `Store::insert`, `Store::count`, and `Character_Links` (calls the shadow and table implementations explicitly, whatever the option says).
- Expose the two sources for the diff by adding `public function from( string $source ): self` to `Character_Links`, which returns a clone pinned to that source. Task 3's methods use the pinned source when it is set.
- Subcommands:
  - `rebuild [--id=<id>] [--legacy-characters]`: without the flag, calls `Sync::character()` for every character of any status except `auto-draft`, with a progress bar, in batches of 200 IDs. With `--legacy-characters`, for each character X it builds `legacy_characters( X, get_post_meta( X, 'lezchars_relationship_chart', true ), <characters holding X's term> )` and calls `Store::insert()`. Prints the totals inserted.
  - `diff [--type=<show|actor|character>] [--id=<id>] [--format=table|csv]`: for each published object of that type, compares the shadow and table lists (both filtered to published), and prints `id, title, missing_in_table, extra_in_table, acf_agrees`. `acf_agrees` says whether each ID in the difference matches the character's ACF fields, so drift is visible at a glance. Ends with `N objects, M mismatched`. It exits with code 0 either way, because this is a report.
  - `source [<shadow|table>]`: prints the current source with no argument. With an argument it updates the option, then calls `wp_cache_flush()` and `Show_Characters::flush_cache()`.

- [ ] **Step 1: Implement the command.**
- [ ] **Step 2: Verify live:** `wp lwtv links rebuild`, then `wp lwtv links rebuild --legacy-characters`, then `wp db query "SELECT object_type, COUNT(*) FROM wp_lwtv_char_links GROUP BY object_type"`. Run both rebuilds again: the counts should not change. `wp lwtv links diff --type=show`, `--type=actor` and `--type=character` should each print a report. Save all three as `docs/plans/shadow-taxonomy-diff-local.md` (counts and a sample of mismatches), which serves as the Phase 0 baseline.
- [ ] **Step 3: Run** `composer lint && composer analyse && vendor/bin/phpunit`.

### Task 6: Flip reads to the table and check parity

**Files:**
- Modify: `relationships/class-character-links.php` (make sure every table branch runs through `Store`), `docs/plans/shadow-taxonomy-removal.md` (mark phases 1–4 implemented and note the cut-over runbook)

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Snapshot scores and counts with source = shadow:** `wp db query "SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE meta_key IN ('lezshows_the_score','lezshows_char_count','lezshows_dead_count','lezactors_char_count','lezactors_dead_count') ORDER BY post_id, meta_key" > <scratchpad>/before.tsv`
- [ ] **Step 2: Flip and recalculate:** `wp lwtv links source table`, then `wp lwtv calc --all`, then take the same snapshot to `after.tsv`, then `diff before.tsv after.tsv`. Expected: every changed post ID appears in Task 5's diff report with `acf_agrees` set, meaning the table fixed a drift and didn't introduce one. Any other change is a bug: flip back with `wp lwtv links source shadow` and investigate.
- [ ] **Step 3: Check the site with source = table:** three shows and three actors from Task 3 (character lists and counts), a character page with legacy relationships, saving an actor (FacetWP reindex log line `Re-indexed N character(s)`), and trashing a character (it drops off its show page with no extra write, then untrash brings it back; Review Focus #5).
- [ ] **Step 4: Leave the local site on `table`**, and write the production cut-over runbook into the spec: deploy, `rebuild`, `rebuild --legacy-characters`, `diff` ×3, snapshot, `source table`, `calc --all`, compare snapshots, and how to roll back with `source shadow`.
- [ ] **Step 5: Final gate:** `composer lint && composer analyse && vendor/bin/phpunit && npm run lint`. Then stop and hand the whole diff to the user to review and commit.
