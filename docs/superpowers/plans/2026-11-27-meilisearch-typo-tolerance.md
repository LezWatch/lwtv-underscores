# Meilisearch Typo Tolerance Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a **Typo tolerance** tab to Search → Settings. It has one block of controls per index, saved in the `lwtv_meili_typo` option and applied in the background. That saved value is part of what every settings apply sends and what every drift check expects.

**Architecture:**
- **Pure code:** one class, `Build\Typo_Tolerance`, holds the defaults, validation, the Meilisearch payload and the comparison. It's unit-tested.
- **WordPress glue:**
  - `Typo_Store` reads and writes the option.
  - `Reindexer::settings_payload()` adds this index's `typoTolerance`.
  - `Settings_Drift` checks it.
  - `Typo_Rest` adds three routes.
- **The app:** a third tab, with an index tablist, in the existing Settings app.

**Tech Stack:** PHP 8.1+ / WordPress 6.5+, PHPUnit (pure only), WP REST API, Action Scheduler (the existing Apply job), React via `@wordpress/element`, `@wordpress/components` and `@wordpress/i18n`. Meilisearch v1.57.

**Spec:** `docs/superpowers/specs/2026-11-26-meilisearch-typo-tolerance-design.md`. It builds on `docs/superpowers/plans/2026-11-26-meilisearch-settings-page.md`, which is already shipped.

## Global Constraints

- **Never commit or stage.** Mika commits the whole diff at the end. Leave the work uncommitted in the main checkout (`/Users/ipstenu/Development/lezwatch/lwtv-underscores`, `release/8.0.0`). A hook blocks Edit/Write there from a worktree session, so write with a script. Don't overwrite another session's changes there; merge instead. A separate "Connection Settings" change, which touches `Config` and the `lwtv_meili_settings` option, may land first. This plan doesn't depend on it and doesn't touch either.
- **Corrections to the spec since approval:**
  - The Settings tabs are **Status** (the default), **General**, then **Typo tolerance**. The spec's sketch shows the old order.
  - The Search menu now opens Settings (`Search_Menu::PARENT` = `lwtv_meili_settings`).
- **Option:** `lwtv_meili_typo` = `{ version: 1, indexes: { <type>: block } }`. It's **not autoloaded**. A block is `{ enabled, oneTypo, twoTypos, disableOnWords, disableOnAttributes, disableOnNumbers }`.
- **Defaults** are the same on every index: `enabled: true`, `oneTypo: 5`, `twoTypos: 9`, `disableOnWords: []`, `disableOnAttributes: []`, `disableOnNumbers: false`. Shows also gets `disableOnAttributes: [ "nations" ]`.
- **Limits:**
  - `oneTypo` is an integer from **1** to **8**.
  - `twoTypos` is an integer greater than `oneTypo`, and at most **20**.
  - `disableOnWords` holds at most **100** words, each at most **100** characters.
  - Bad values are refused with one error per field, never clamped.
- **Admins only** (`manage_options`) on every route, with the REST nonce. No key or URL ever reaches the browser or the output.
- **Writes** go only to `local_*` indexes. Leave SearchWP untouched. Remove any throwaway mu-plugins and filters afterwards.
- **Strings:** use `__()` / `@wordpress/i18n` with the `'lwtv-underscores'` text domain. The pure class's error messages are English, as in `Search_Rules`. API text renders as React text only.
- **Accessibility:** WCAG 2.1 AA, keyboard-only, visible labels, and the existing single polite live region.
- **Tooling:**
  - Run `nvm use` (Node 24) before npm, and never edit `admin/build/`.
  - Set `TMPDIR` to a scratch dir for `composer analyse`.
  - Use the wp-cli wrapper with the mysqli socket override (memory "Local wp-cli setup").

## Review Focus

1. **A corrupted stored option** reads as the defaults for every index, and never fatals or sends junk. That covers a string, `indexes` that isn't an array, or a block with wrong types or out-of-range numbers. Pinned in Task 1 (`test_effective_ignores_corrupt_blocks`).
2. **A saved field that's no longer searchable,** because the repo file changed, is dropped from the payload and reported as removed. Pinned in Task 1 (`test_effective_drops_unsearchable_attributes`).
3. **The live `typoTolerance` comes back in a different order** (keys, or the two lists) and still counts as a match. A live object that's missing keys counts as drift. Pinned in Task 1 (`test_same_*`).
4. **Saving when the save can't be applied** still saves. That covers a job already running on that index, the server down, or the index not set up. The response says which, and never says `started`. Pinned in Task 4 (live).
5. **A save whose values equal the defaults** deletes the stored block, and `is_default` is true. Pinned in Task 1 (`test_is_default_*`) and Task 3 (live).

---

### Task 1: Pure typo logic

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-typo-tolerance.php` (`LWTV\Meilisearch\Build\Typo_Tolerance`)
- Modify: `tests/bootstrap.php`. Require the new class next to `class-settings-drift.php`.
- Test: `tests/unit/Meilisearch/TypoToleranceTest.php`

**Interfaces:**
- Produces:
  - `const ONE_MIN = 1; ONE_MAX = 8; TWO_MAX = 20; WORDS_MAX = 100; WORD_MAX = 100;`
  - `const FIELDS = array( 'enabled', 'oneTypo', 'twoTypos', 'disableOnWords', 'disableOnAttributes', 'disableOnNumbers' );`
  - `defaults( string $type ): array`: a block in `FIELDS` order.
  - `validate( string $type, mixed $input, array $searchable ): array{values: array, errors: array<string,string>}`
    - A field missing from `$input` takes that type's default.
    - Any error makes `values` `array()`.
    - `errors` is keyed by field name.
  - `effective( string $type, mixed $stored, array $searchable ): array{values: array, removed: string[]}`
    - `$stored` is that type's stored block, or null.
    - A block that doesn't validate gives the defaults.
    - Attributes no longer in `$searchable` are moved out to `removed`.
  - `is_default( string $type, array $values ): bool`. The lists are compared sorted.
  - `payload( array $values ): array`: the Meilisearch object `{ enabled, minWordSizeForTypos: { oneTypo, twoTypos }, disableOnWords, disableOnAttributes, disableOnNumbers }`, with both lists sorted.
  - `same( array $payload, mixed $live ): bool`. It ignores key order, sorts both lists, and compares the numbers as ints. A non-array, or missing keys, gives false.
- Consumes: `Search_Rules::word( string $w ): string` for word normalizing (trim, collapse inner whitespace, lowercase).

- [ ] **Step 1: Write the failing tests** in `TypoToleranceTest`, each with exact values:
  - `test_defaults_match_today`:
    - `defaults('characters')` equals `['enabled'=>true,'oneTypo'=>5,'twoTypos'=>9,'disableOnWords'=>[],'disableOnAttributes'=>[],'disableOnNumbers'=>false]`.
    - `defaults('shows')['disableOnAttributes'] === ['nations']`.
    - `actors` and `content` equal `characters`.
  - `test_validate_accepts_a_full_block`: shows, `oneTypo 4`, `twoTypos 7`, words `['  Buffy  The ', 'buffy the']`. Expect words `['buffy the']` and no errors.
  - `test_validate_fills_missing_fields_from_defaults`: `validate('shows', ['oneTypo'=>4], $searchable)` keeps `twoTypos 9` and `disableOnAttributes ['nations']`.
  - `test_validate_refuses_bad_numbers`, looping. `oneTypo`: `0`, `9`, `'5'`, `5.0`, `null`. `twoTypos`: equal to `oneTypo`, `21`. Each gives exactly the one error key.
  - `test_validate_refuses_non_booleans`: `enabled` `1` / `'true'`, and `disableOnNumbers` `0`. Each gives its own error key.
  - `test_validate_refuses_unsearchable_attribute`: `disableOnAttributes ['secret']` against `['name','nations']` gives the error key `disableOnAttributes`.
  - `test_validate_refuses_word_limits`: 101 distinct words, or one word of 101 characters, gives `disableOnWords`. A non-string word gives `disableOnWords`.
  - `test_validate_refuses_hostile_shapes`: input `'x'`, `null` or `[[]]` gives an `errors` entry for `settings`, and `values` is `[]`.
  - `test_validate_ignores_unknown_keys`: an extra `foo` doesn't appear in `values`.
  - `test_effective_ignores_corrupt_blocks`: a stored `'x'`, `['oneTypo'=>99]` or `['enabled'=>'yes']` each gives `defaults($type)`.
  - `test_effective_drops_unsearchable_attributes`: stored `['nations','gone']` against `['nations']` gives values `['nations']` and `removed` `['gone']`.
  - `test_is_default_compares_sorted_lists`: `is_default('shows', defaults('shows'))` is true. So is the same block with its lists reordered. `oneTypo 4` gives false.
  - `test_payload_shape`: shows defaults map to `['enabled'=>true,'minWordSizeForTypos'=>['oneTypo'=>5,'twoTypos'=>9],'disableOnWords'=>[],'disableOnAttributes'=>['nations'],'disableOnNumbers'=>false]`.
  - `test_same_ignores_key_and_list_order`: the live object has its keys reversed and the lists reordered, and gives true.
  - `test_same_reports_missing_or_changed_keys`: a live value without `disableOnNumbers`, with `twoTypos 8`, or `null` gives false.

- [ ] **Step 2: Run them to check they fail.** Run `vendor/bin/phpunit --filter TypoTolerance`. Expected: they FAIL with class `Typo_Tolerance` not found.

- [ ] **Step 3: Implement `Typo_Tolerance`.** It's pure: no WordPress calls, guarded by `ABSPATH` like its siblings. The shows `nations` default is a `const SHOWS_NO_TYPO = array( 'nations' )`. `effective()` calls `validate()` and falls back to `defaults()` on any error, after splitting off the removed attributes, so one bad field means the defaults for the whole block.

- [ ] **Step 4: Run them to check they pass.** Run `vendor/bin/phpunit --filter TypoTolerance`. Expected: PASS. Then run `vendor/bin/phpunit`. Expected: all green.

- [ ] **Step 5: Leave uncommitted.**

### Task 2: Drift checks know about typo tolerance; shows.json drops it

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/build/class-settings-drift.php`
- Modify: `plugins/lwtv-plugin/php/meilisearch/config/shows.json`. Remove the `typoTolerance` key.
- Test: `tests/unit/Meilisearch/SettingsDriftTest.php`, `tests/unit/Meilisearch/IndexSettingsTest.php`

**Interfaces:**
- Consumes: `Typo_Tolerance::same()` and `Typo_Tolerance::payload()` (Task 1).
- Produces: `Settings_Drift::differs( array $repo, array $live, ?array $typo = null ): string[]`.
  - It skips `synonyms` and `typoTolerance` in the repo loop.
  - When `$typo` (a payload) is given and `! Typo_Tolerance::same( $typo, $live['typoTolerance'] ?? null )`, it appends `'typoTolerance'` last.
  - With no `$typo`, it behaves as today. That is the only behavior for callers that don't pass one.

- [ ] **Step 1: Write the failing tests:**
  - `SettingsDriftTest::test_typo_drift_is_last_and_order_insensitive`. The live value is a matching payload with its lists reordered, so the result is `[]`. The live `twoTypos` is 8, so the result is `['typoTolerance']`. Repo drift plus typo drift gives `['stopWords','typoTolerance']`.
  - `SettingsDriftTest::test_repo_typo_key_is_ignored`. The repo array carries an old `typoTolerance`, the live value differs, and no `$typo` is given, so the result is `[]`.
  - `IndexSettingsTest`. Replace the shows `typoTolerance` assertion with `assertArrayNotHasKey( 'typoTolerance', $settings )`. Move the comment about `canaan` matching Canadian shows to the shows default in `Typo_Tolerance`.

- [ ] **Step 2: Run them to check they fail.** Run `vendor/bin/phpunit --filter 'SettingsDrift|IndexSettings'`. Expected: the new drift tests FAIL, and the shows assertion FAILs while `shows.json` still has the key.

- [ ] **Step 3: Implement the new signature,** and delete `typoTolerance` from `shows.json`. Keep the rest of the file byte-for-byte, including its tab indent.

- [ ] **Step 4: Run them to check they pass.** Run `vendor/bin/phpunit`. Expected: all green.

- [ ] **Step 5: Leave uncommitted.**

### Task 3: Store, payload, CLI and Status tab

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-typo-store.php` (`LWTV\Meilisearch\Typo_Store`)
- Modify:
  - `class-reindexer.php`: the constructor gains `Typo_Store $typo`, and `settings_payload()` sets `$settings['typoTolerance'] = $this->typo->payload( $index )`.
  - `class-meilisearch.php`: wire it up.
  - `wp-cli/cli-meili.php`: `boot()`, and `status()`.
  - `class-search-settings-rest.php`: `status()`, which passes `$typo` to `Settings_Drift::differs`.

**Interfaces:**
- Consumes: Task 1, Task 2.
- Produces (`Typo_Store`):
  - `const OPTION = 'lwtv_meili_typo';`
  - `searchable( Index $index ): string[]`: the repo file's `searchableAttributes`, or `[]` on an `InvalidArgumentException`.
  - `stored( string $type ): mixed`: the raw block, or null.
  - `values( Index $index ): array{values: array, removed: string[]}` (`Typo_Tolerance::effective`).
  - `payload( Index $index ): array` (`Typo_Tolerance::payload( values()['values'] )`).
  - `save( Index $index, mixed $input ): array{values: array, errors: array}`. On success, values equal to the defaults delete that type's block, and anything else stores it. Either way `update_option( self::OPTION, …, false )`, and the option is deleted entirely once it has no blocks.
  - `reset( string $type ): void`: deletes the block.

- [ ] **Step 1: Write the glue.** In `cli-meili.php` `status()`:
  - Pass `$this->reindexer`'s typo payload, `$this->typo->payload( $index )`, to `Settings_Drift::differs`.
  - Report `typoTolerance` on its own line: `Typo tolerance differs from Search → Settings → Typo tolerance. Use Apply settings on the Status tab, or run `wp lwtv meili settings --type=<type>`.`
  - Report the other keys as today, and print `Settings match the repo file.` only when neither differs.
  - `boot()` builds `$this->typo = new Typo_Store()` before `Reindexer`.

  In `Search_Settings_Rest::status()`, pass the payload too. It takes `Typo_Store` through its constructor, so update its wiring in `class-meilisearch.php`.

- [ ] **Step 2: Static checks.** Run `composer lint` and `TMPDIR=<scratch> composer analyse`. Expected: both pass.

- [ ] **Step 3: Check it live on lwtv.local, with nothing saved.**
  - Run `wp lwtv meili status --type=all`. Expected: no drift on any index, including shows' nations rule.
  - `GET /wp-json/lwtv/v1/meili-settings/status`, as an admin through the browser: every `settings_drift` is `[]`.

- [ ] **Step 4: Check that it stays applied.**
  - Save a characters block with `twoTypos` 7 through `wp eval` (`( new Typo_Store() )->save( … )`), then run `wp lwtv meili settings --type=characters`.
  - `status` shows no drift, and a fetch of the live index settings shows `twoTypos` 7.
  - Run `wp lwtv meili reindex --type=content --fresh`. Content still has its defaults, and `status --type=all` shows no drift.
  - Change `twoTypos` by hand with a `curl` PATCH to the local server, using the indexing key from wp-config through an environment variable, never printed. `status` reports the typo line.
  - Run `settings --type=characters` again and the drift clears.
  - Finally `( new Typo_Store() )->reset( 'characters' )`, then `settings --type=characters`.
- [ ] **Step 5: Run the tests.** Run `vendor/bin/phpunit`. Expected: green.
- [ ] **Step 6: Leave uncommitted.**

### Task 4: REST routes

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-typo-rest.php` (`LWTV\Meilisearch\Typo_Rest`)
- Modify: `class-meilisearch.php`, to register it.

**Interfaces:**
- Consumes: `Typo_Store`, `Index_Jobs::running( string $type ): ?array`, `Index_Jobs::start( string $type, string $kind )`, `Index_Jobs::refresh( string $type ): ?array`, and `Indexes`, with `Index::forget_state()`, `Index::state()`, `Index::label()`.
- Produces: under `lwtv/v1`, every route uses `permission_callback` `current_user_can( 'manage_options' )`.
  - `GET meili-settings/typo` returns `{ indexes: [ { type, label, settings, removed, defaults, is_default, attributes, job } ], limits: { one_min, one_max, two_max, words_max, word_max } }`, using the `Typo_Tolerance` constants.
  - `POST meili-settings/typo` takes `{ type, settings }`.
    - An unknown type returns 400 `lwtv_meili_typo_invalid`.
    - Errors return 400 `lwtv_meili_typo_invalid` with `data.errors`, and nothing is saved.
    - Otherwise it saves, applies, and returns 200 `{ index: <same row shape as GET>, applied }`.
  - `POST meili-settings/typo/reset` takes `{ type }`. It resets, applies, and gives the same 200 shape.
  - `applied` is decided before starting anything, in this order:
    - `running(type)` set → `job_running`;
    - after `forget_state()`, `state()` UNKNOWN → `unreachable`;
    - MISSING → `not_set_up`;
    - otherwise `start( type, Index_Job::APPLY )`, which gives `started`.

    If `start()` returns a `WP_Error`, it re-checks `running()`: set gives `job_running`, otherwise `unreachable`.

- [ ] **Step 1: Implement `Typo_Rest`,** with `register()` on `rest_api_init`. Leave `Search_Settings_Rest` alone except where Task 3 already changed it.
- [ ] **Step 2: Static checks.** Run `composer lint` and `TMPDIR=<scratch> composer analyse`. Expected: pass.
- [ ] **Step 3: Check it live,** through the logged-in admin browser tab with `wp.apiFetch` or `fetch` plus `wpApiSettings.nonce`. Print only status codes and response fields, never page HTML.
  - GET: four rows, all `is_default: true`. The shows `attributes` lists `nations`.
  - Each refused value returns 400 with that field's key, and GET is unchanged afterwards: `twoTypos` equal to `oneTypo`, `oneTypo` 0, the attribute `secret`, 101 words, and the type `nope`.
  - POST characters with `twoTypos` 7 returns 200 with `applied: started`. Seconds later, GET shows `job.state` done. The header modal and the Rules Test box now find Alex Danvers for "alex canvers".
  - Reset returns 200, then `is_default: true`, and "alex canvers" misses again.
  - Start a Reindex on content from the Status tab, then POST a content block. Expect `job_running`, the block saved, and Status showing `typoTolerance` drift on content once the reindex finishes. Then reset content.
  - Use a throwaway `pre_http_request` filter that fails calls to the Meilisearch host, then POST. Expect `unreachable` with the block saved. Remove the filter and reset.
  - An editor account gets 403 on all three routes.
- [ ] **Step 4: Leave uncommitted.**

### Task 5: The Typo tolerance tab

**Files:**
- Create:
  - `admin/src/shared/jobText.js`: move `jobText` (plus its `KIND`, `STEP` and `isRunning`) out of `settings/StatusTab.js` and export it, so StatusTab imports it.
  - `admin/src/settings/TypoTab.js`: the index tablist, loading and dirty tracking.
  - `admin/src/settings/TypoBlock.js`: one index's form.
- Modify:
  - `admin/src/settings/SettingsApp.js`: add a third tab `{ name: 'typo', title: __( 'Typo tolerance', 'lwtv-underscores' ) }` after General. Status stays the default.
  - `admin/src/settings/style.scss`.

  All of these are under `plugins/lwtv-plugin/php/meilisearch/`.

**Interfaces:**
- Consumes:
  - `Tabs` (`shared/Tabs.js`): `{ id, label, tabs, selected, onSelect }`, with panels kept mounted.
  - `usePoll`.
  - `say` from `useAnnounce`.
  - `REST`, `POLL_MS` and `POLL_MAX_MS` (`settings/config.js`).
  - `GET lwtv/v1/meili-rules/test?q=`, whose `results[]` rows are `{ type, label, hits: [ { id, title } ] }`. Show only the row for the selected type.
- Produces: `TypoTab( { active, say } )`, which loads `${ REST }/typo` the first time `active` is true.

- [ ] **Step 1: Build the tab.**
  - Index tabs are a second `Tabs`, `id="lwtv-typo"`, labelled "Index". Each panel holds a `TypoBlock` with its own draft state. The header reads `<label> · Using the defaults` or `· Changed from the defaults`, with **Reset to defaults** disabled when `is_default` is true.
  - The controls:
    - `CheckboxControl` "Allow typos";
    - two `TextControl type="number"` inputs, "One typo from N letters" and "Two typos from M letters", with `min` and `max` from `limits`;
    - one `CheckboxControl` per entry in `attributes`, under "Never allow typos in these fields". Each name in `removed` gets the note "removed: no longer searchable";
    - `FormTokenField` "Never allow typos in these words";
    - `CheckboxControl` "No typos in numbers".
  - Each control has a one-line explanation under it. The two-typo line reads exactly: "A wrong first letter counts as two typos, so 'alex canvers' needs two typos at 7 letters or fewer." Leave the other numbers and fields enabled while "Allow typos" is off, and say they're kept.
  - **Save and apply** POSTs, and the API errors show by each field. Say these messages in the live region, by `applied`:
    - `started`: "Saved. Applying to <label>…"
    - `job_running`: "Saved, but not applied: a job is running on this index. Use Apply settings on the Status tab when it finishes."
    - `not_set_up`: "Saved. It applies when this index is set up."
    - `unreachable`: "Saved, but not applied: couldn't reach the search server. Use Apply settings on the Status tab once it's back."
  - The status line is `jobText( job )`. While that job is running, `usePoll` re-GETs, `active` only.
  - **Test a search** is a `TextControl` plus a button that calls the Rules test route. It shows up to 3 titles for this index, or "No results".
  - Add a `beforeunload` warning while any block's draft differs from what was loaded, as `RulesApp.js` does.
  - When Reset or a save removes the focused control's row, move focus to the block heading (`tabIndex={ -1 }`).
  - In `style.scss`, use the `lwtv-settings__row` / `__field` grid from General for each control group. The two number inputs sit side by side and stack below 782px.
- [ ] **Step 2: Lint, format and build.** Run `nvm use`, `npx wp-scripts format` on the new and changed files, `npm run lint:js`, `npx wp-scripts lint-style plugins/lwtv-plugin/php/meilisearch/admin/src/settings/style.scss` and `npm run build:workspace`. Expected: all pass, and `admin/build/` is rebuilt, never hand-edited.
- [ ] **Step 3: Check it live on lwtv.local.**
  - The tab order is Status, General, Typo tolerance.
  - All four index tabs show "Using the defaults", and shows has `nations` ticked.
  - Save characters with `twoTypos` 7. The status line goes from Applying to "Apply done", the Test box finds Alex Danvers for "alex canvers", and the header reads "Changed from the defaults".
  - Reset brings back "Using the defaults", and Reset is disabled again.
  - A refused value (two equal to one) shows the error by its field, and nothing is saved.
  - An unsaved edit, then switching index tabs and back, keeps the edit. Leaving the page prompts.
  - It works keyboard-only: arrow keys move between the index tabs, and the Tab key reaches every control. The accessibility tree shows a labelled tablist and labelled controls.
  - At 375px there's no sideways scroll. Dark admin colour schemes aren't involved: the screen uses the admin components' own colours.
  - Finish with the characters index reset.
- [ ] **Step 4: Leave uncommitted.**

### Task 6: Docs and the final gate

**Files:**
- Modify:
  - `docs/architecture/meilisearch.md`: a "Typo tolerance" paragraph after the Settings → Status one. Cover the option, the defaults (now in code, not `shows.json`), the payload merge, drift and the three routes.
  - `docs/runbooks/meilisearch.md`: after deploy, nothing saved means the defaults equal today's settings, so no index changes until someone saves. `status` must show no drift on an environment that already ran `settings --type=shows`.
  - `docs/plugin-structure.txt`, regenerated with `npm run docs:structure`. Describe `class-typo-store.php`, `class-typo-rest.php` and `build/class-typo-tolerance.php`.

- [ ] **Step 1: Write the docs.**
- [ ] **Step 2: Run the full gate.**
  - `vendor/bin/phpunit`
  - `composer lint`
  - `TMPDIR=<scratch> composer analyse`
  - `npm run lint:js`
  - `npm run lint:css`
  - `npm run docs:structure:check`

  Expected: all pass. If the stray `class-search-page.php.orig` is still the only `docs:structure:check` failure, report it rather than deleting it.
- [ ] **Step 3: Clean up.** Remove any throwaway mu-plugins and filters. Confirm `wp option get lwtv_meili_typo` is empty and `wp lwtv meili status --type=all` shows no drift.
- [ ] **Step 4: Leave uncommitted.** Report the changed files to Mika.
