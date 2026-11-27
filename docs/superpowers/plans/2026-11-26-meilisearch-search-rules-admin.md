# Meilisearch Search Rules Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin-only "Search Rules" page as a React app over a small REST API. It edits two-way and one-way synonyms and stores them in one option that has room for pins and redirects later. It applies them to every index that exists without blocking the request, and the CLI keeps them in step.

**Architecture:**
- **Pure logic:** `build/class-search-rules.php` normalizes and validates the rules, converts them to Meilisearch's format and compares for drift. It is unit-tested.
- **WordPress glue:** `Search_Rules_Store` handles the option, applying the rules and refreshing task status. A REST controller sits in front of it, and the CLI reuses the same store.
- **Admin page:** a PHP page class mounts a `@wordpress/scripts` app, which lives in its own npm workspace and uses WordPress's bundled React and components.

**Tech Stack:** PHP 8.1+ / WordPress 6.5+, PHPUnit (pure only), WP REST API, React via `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/i18n`, `@wordpress/scripts` (dependency extraction), Meilisearch v1.57 settings and tasks API.

**Spec:** `docs/superpowers/specs/2026-11-26-meilisearch-search-rules-admin-design.md`

## Global Constraints

- **Commits and the main checkout.** Never commit without Mika's explicit approval in that turn. Leave the work uncommitted in the main checkout (`/Users/ipstenu/Development/lezwatch/lwtv-underscores`, on `release/8.0.0`). A hook blocks Edit and Write there, so build in a worktree and copy the files across with a script.
- **Admins only.** Every route, menu item and enqueue checks `manage_options`.
- **Keys.** The indexing key never reaches the browser, a log or the repo. REST responses carry Meilisearch's error code and message only, never a key.
- **The option.** It's called `lwtv_meili_search_rules`, with `autoload` set to `false`. Its shape is exactly what the spec gives: `version`, `synonyms.two_way`, `synonyms.one_way`, `pins`, `redirects` and `applied`. `pins` and `redirects` are kept but never read or rendered.
- **Limits.** Words are at most 100 characters, with at most 500 two-way groups and 500 one-way entries. A two-way group needs at least 2 words. A one-way entry needs a `from` and at least 1 `to`.
- **Errors and warnings.** Errors block a save, and warnings don't. A stop word is a warning. The stop words are the union of `stopWords` across `config/*.json`, read at runtime, never hard-coded.
- **Applying.** Only the `synonyms` setting is applied, and only to indexes whose `state()` is `Index_State::EXISTS`. An index that doesn't exist on this environment gets "not set up" and no request. **No `wait_for_task()` in a web request.**
- **Polling.** The app checks every 2 seconds, for up to 60 seconds, while anything is "applying".
- **The build output.** The workspace's `build/` is gitignored, following the blocks pattern, and CI's existing `npm run build:workspace` builds it.
- **Strings and accessibility.** Strings use `__()` / `@wordpress/i18n` with the `'lwtv-underscores'` text domain. The page meets WCAG 2.1 AA, works with the keyboard alone, and announces saves and errors in a polite live region.
- **Out of scope:** pins, redirects, editor access, per-index synonyms, editing other index settings, and changes to Search Compare.

## Review Focus

1. **The CLI wiping admin synonyms.** `wp lwtv meili settings` and `reindex --fresh` must merge in the saved synonyms. Pinned by the Task 3 live check: save a list, run both commands, then `status` shows no drift.
2. **Creating an index by accident.** Applying to a missing index must not create a bare one. Pinned by the Task 2 live check: a throwaway filter gives one uid that doesn't exist, the save reports "not set up", and afterwards `GET /indexes/<uid>` is still 404.
3. **A save overtaking an earlier one.** A save made while the last one is still "applying" must not be marked "ok" by the older task. Pinned in Task 2: `refresh()` only updates an entry whose stored `task` equals the polled task's `uid`. There's also a live check with two saves 1 second apart.
4. **Near-duplicates.** Words that differ only by case or spacing ("Non  Binary", "non binary") and non-ASCII words ("Chicás") must normalize to one form, and that must be what's detected as a duplicate or a cross-group conflict. Pinned by the Task 1 cases.
5. **Hostile input.** Imports are parsed in the browser, but every save goes through `POST`, so the server's `normalize()` is the real defence. Input that isn't the right shape, words that aren't strings, extra keys (`applied`, `pins`, a key), input over the limits, or HTML must be refused or cleaned, never stored raw. Pinned by Task 1's hostile-input cases, plus a Task 7 live check that imports `<img src=x onerror=alert(1)>` as a word and sees it shown as plain text.

---

### Task 1: Pure rules: `Build\Search_Rules`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-search-rules.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/config/synonyms-starter.json`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Meilisearch/SearchRulesTest.php`

**Interfaces:**
- Produces:
  - `Search_Rules::MAX_WORD = 100`, `MAX_GROUPS = 500`, `MAX_ONE_WAY = 500`
  - `Search_Rules::word( string $w ): string`: trim, collapse internal whitespace, then `mb_strtolower`.
  - `Search_Rules::normalize( mixed $raw, array $stop_words ): array{ synonyms: array{ two_way: string[][], one_way: array<int, array{ from: string, to: string[] }> }, errors: array<int, array{ kind: 'two_way'|'one_way', row: int, message: string }>, warnings: same shape }`. `row` is the row's index in the **input**, so the app can attach the message to the right row. The input shape is `{ two_way: [[…]], one_way: [{ from, to: […] }] }`. Anything else counts as empty.
  - `Search_Rules::to_meilisearch( array $synonyms ): array<string, string[]>`. Every key and every value list is sorted.
  - `Search_Rules::same( array $a, array $b ): bool`. Compares two Meilisearch synonym maps, ignoring order.
- Starter file: `config/synonyms-starter.json` has the shape `{ two_way, one_way }`, holding the spec's 19 cases with full titles that match the database. For example: `tlw` → `the l word`; `svu` → `law & order special victims unit`; `ds9` → `star trek deep space nine`; `the hundred` → `the 100`.

- [ ] **Step 1: Write the failing tests.** Each is a separate method with exact `assertSame`:
  - **`word()`:** `'  Non   Binary '` → `'non binary'`, and `'Chicás'` → `'chicás'`.
  - **Two-way groups:**
    - `[ 'faq', 'FAQ', 'Frequently  asked questions' ]` → `[ 'faq', 'frequently asked questions' ]`.
    - A group with 1 word after cleaning gives an error on that row.
    - Empty groups are dropped without an error.
  - **One-way entries:**
    - `{ from: 'TLW', to: [ 'The L Word' ] }` → `{ from: 'tlw', to: [ 'the l word' ] }`.
    - A missing `from`, or a `to` that's empty, gives an error.
    - A `from` repeated in its own `to` is removed from `to`, and the entry errors if `to` is then empty.
  - **Conflicts:**
    - `nonbinary` in two two-way groups gives an error on the **second** row, and the message names the first group.
    - The same `from` on two one-way rows gives an error on the second.
  - **Stop words:** a group containing `from` gives a warning, not an error, when `$stop_words = [ 'from', 'on' ]`.
  - **Limits:**
    - A 101-character word gives an error.
    - 501 groups give one error with `row: -1`, a "too many groups" message, and nothing kept past 500.
  - **Converting:**
    - `to_meilisearch()` of the group `[ 'a', 'b', 'c' ]` gives `[ 'a' => [ 'b', 'c' ], 'b' => [ 'a', 'c' ], 'c' => [ 'a', 'b' ] ]`.
    - The one-way entry `{ from: 'x', to: [ 'y' ] }` gives `[ 'x' => [ 'y' ] ]`.
    - A word in both a group and a one-way `from` has its value lists merged and de-duplicated.
  - **`same()`:** true for the same map with keys and values in a different order, and false when one value is missing.
  - **Hostile input to `normalize()`:**
    - A string, `null`, or `{ two_way: 'x' }` gives empty synonyms with no fatal error.
    - Non-string words (arrays, ints, `true`) are dropped. Ints are cast to strings.
    - Extra top-level keys (`applied`, `pins`, `key`) are ignored and never appear in the output.
    - A word of `'<img src=x onerror=alert(1)>'` comes back as that literal string. Storing it is fine, because output escaping happens at render.
  - **The starter file** passes `normalize()` with no errors, using the real stop words from `config/*.json` (read with `file_get_contents` in the test).

- [ ] **Step 2:** Run `vendor/bin/phpunit --filter SearchRules`. Expected: errors, because the class isn't found.
- [ ] **Step 3: Implement.** Follow the style of `class-facet-search.php`: an `ABSPATH` guard, static methods, no WordPress calls.
- [ ] **Step 4:** Run `vendor/bin/phpunit`. Expected: all green.

---

### Task 2: The store and applying: `Search_Rules_Store`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-rules-store.php`

**Interfaces:**
- **Consumes:** `Search_Rules` (Task 1), `Indexes::all()`, `Index::state()`, `Index::uid()`, `Index::type()`, `Index::settings_path()`, `Index_Settings::load()`, `Client::update_settings()` and `Client::get_task()`.
- **Produces `Search_Rules_Store( Indexes $indexes, Client $client )`:**
  - `OPTION = 'lwtv_meili_search_rules'`
  - `get(): array`, returning the full option with defaults filled in
  - `stop_words(): string[]`, the union of `stopWords` from each index's repo file
  - `synonyms_map(): array`, which is `Search_Rules::to_meilisearch( get()['synonyms'] )`
  - `save( array $synonyms ): array{ ok: bool, errors, warnings }`. It normalizes the input. If there are no errors, it updates the option, keeping `pins`, `redirects` and `version`, and then calls `apply()`.
  - `apply(): array` returns the new `applied` value. For each index:
    - **Not `EXISTS`:** the entry is `{ status: 'not set up', task: null }`.
    - **Otherwise:** call `update_settings( uid, [ 'synonyms' => map ] )` with the client's short timeout. On a `WP_Error`, the entry is `{ status: <message>, task: null }`. On success it's `{ status: 'applying', task: taskUid }`.
    - Afterwards, store `applied: { at: time(), indexes }`.
  - `refresh(): array`. For each entry whose status is `'applying'`, call `get_task( task )` and check the returned `uid` matches `task`. Then:
    - `succeeded` → `'ok'`
    - `failed` / `canceled` → `"<code> — <message>"`
    - anything else stays `'applying'`
    - a `WP_Error` leaves the entry unchanged

    Save the option only if something changed, and return `applied`.
  - A debug filter, `lwtv_meili_search_rules_indexes` (`Index[]` → `Index[]`), for the live check.

- [ ] **Step 1: Implement.** Writes use `update_option( OPTION, $value, false )`.
- [ ] **Step 2: Live checks** on lwtv.local through `wp eval`. Use the wp-cli wrapper from the background.
  - `$s->save( [ 'two_way' => [ [ 'faq', 'frequently asked questions' ] ], 'one_way' => [] ] )` returns `ok: true`, and every index says `applying`. After `sleep(2)`, `refresh()` shows every index as `ok`. The search key then finds the FAQ page for `faq` in `local_content`.
  - **Review Focus 2:** a throwaway mu-plugin uses the filter to replace one index with a stand-in whose `uid()` is `local_nope`. The save reports `not set up`. `Client::index_stats( 'local_nope' )` still returns `index_not_found`. Remove the mu-plugin afterwards.
  - **Review Focus 3:** save list A, then save list B 1 second later, then refresh. Every index ends `ok` with B's task IDs, and the index's synonyms equal B.
  - Restore the option to empty at the end, unless Task 6 still needs it.

---

### Task 3: CLI: merge, drift and `synonyms apply`

**Files:**
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-meili.php`

**Interfaces:**
- Consumes: `Search_Rules_Store` (Task 2) and `Search_Rules::same()`.

- [ ] **Step 1: Merge the saved synonyms into the repo settings.** In `apply_settings()`, after `repo_settings()`, set `$settings['synonyms'] = $store->synonyms_map()`. Both `settings` and `reindex --fresh` go through this path.
- [ ] **Step 2: Check synonyms for drift in `status`.** Compare `$live['synonyms']` with `synonyms_map()` using `Search_Rules::same()`. Report a mismatch as `synonyms`, with the hint `wp lwtv meili synonyms apply`. The existing comparison skips `synonyms` because the repo files don't set it; keep it that way.
- [ ] **Step 3: Add the `synonyms` subcommand.** `wp lwtv meili synonyms apply` calls `$store->apply()`, then polls `refresh()` (the CLI may block) for up to 30 s. It prints one line per index and uses `\WP_CLI::error` if any index failed. `wp lwtv meili synonyms show` prints the saved list as JSON. It runs on every index, so `--type` doesn't apply.
- [ ] **Step 4: Live checks (Review Focus 1).**
  - Save a small list through `wp eval`.
  - `wp lwtv meili settings --type=all` keeps the synonyms: `status --type=all` shows no `synonyms` drift.
  - `wp lwtv meili reindex --type=content --fresh` keeps them too.
  - Change one index's synonyms by hand with `update_settings` through `wp eval`. `status` now reports drift on that index, and `synonyms apply` fixes it.
- [ ] **Step 5:** Run `composer lint` and `TMPDIR=<scratch> composer analyse`. Expected: both clean.

---

### Task 4: REST API

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-rules-rest.php`

**Interfaces:**
- **Consumes:** `Search_Rules_Store`, `Search_Rules::normalize()`, `Config::search_key()` and `Client::multi_search()`.
- **Produces these routes**, each with `permission_callback => fn() => current_user_can( 'manage_options' )`:
  - **`GET lwtv/v1/meili-rules`** returns `{ rules: { synonyms }, applied: <refresh() result>, warnings: <normalize warnings for the saved list>, stop_words, indexes: [ { type, uid, label, state } ], starter: <the starter file's synonyms, or null when the saved list isn't empty> }`.
  - **`POST lwtv/v1/meili-rules`** takes the body `{ synonyms }`. It returns `200 { rules, applied, errors: [], warnings }`, or `400 { code: 'lwtv_meili_rules_invalid', message, data: { status: 400, errors, warnings } }`.
  - **`POST lwtv/v1/meili-rules/apply`** returns `{ applied }`.
  - **`GET lwtv/v1/meili-rules/test?q=`** requires a non-empty `q` (with `sanitize_text_field`). It runs one `multi_search` with one query for each index that exists: `limit: 3`, `matchingStrategy: 'frequency'`, retrieving `id`, `name` and `title`. It returns `{ results: [ { type, label, hits: [ { id, title } ] } ] }`. A transport error comes back as a 502 carrying the log-safe message.

- [ ] **Step 1: Implement.** Register the routes on `rest_api_init` from the component, but only when the module is configured.
- [ ] **Step 2: Live checks** with `rest_do_request` in `wp eval`:
  - As an admin, `GET` returns 200 with all keys.
  - As an editor, `GET` and `POST` return 403. Use `wp_set_current_user` with an `editor` user, and create a throwaway one if none exists, then delete it.
  - A `POST` with a duplicate cross-group word returns 400, and the option is unchanged.
  - `GET /test?q=faq`, after the starter list is saved, shows the FAQ page in Posts.
- [ ] **Step 3:** Run `composer lint` and PHPStan. Expected: clean.

---

### Task 5: The admin page and workspace scaffold

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-rules-page.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/admin/package.json`. Set `name` to `@lwtv/meili-admin`, mark it private, and give it these scripts: `build` and `build:workspace` (`wp-scripts build`), `start`, `lint:js`, `fix:js`, `clean:workspace`, and `updater:workspace` copied from the blocks package. Its devDependencies are `@wordpress/scripts`, `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch` and `@wordpress/i18n`, at the same versions as `plugins/lwtv-plugin/php/blocks/package.json`.
- Create: `plugins/lwtv-plugin/php/meilisearch/admin/src/index.js` (a placeholder that renders a heading, so the build can be checked)
- Modify: root `package.json`
  - add `"./plugins/lwtv-plugin/php/meilisearch/admin"` to `workspaces`
  - add `lint:js:meili` / `fix:js:meili` → `wp-scripts lint-js 'plugins/lwtv-plugin/php/meilisearch/admin/src/**/*.js'` (and `--fix`)
- Modify: `.gitignore`, adding `/plugins/lwtv-plugin/php/meilisearch/admin/build/*` next to the blocks line
- Modify: `eslint.config.js`, if the new `src/` path needs the same JSX/React settings the blocks `src/` gets. Check how the blocks are covered and mirror it.
- Modify: `plugins/lwtv-plugin/php/_components/class-meilisearch.php`, to register the page and the REST controller

**Interfaces:**
- **Page:** a submenu under `lwtv` with the slug `lwtv_meili_rules`, titled "Search Rules", requiring `manage_options`.
- **Markup:** `render()` prints `<div class="wrap"><h1>…</h1><div id="lwtv-meili-rules-root"></div><noscript>…needs JavaScript…</noscript></div>`.
- **Enqueue:** on that screen only, read `admin/build/index.asset.php`. Enqueue `admin/build/index.js` with its `dependencies` and `version`, plus `wp-components` CSS, and an `index.css` if the build emits one. Call `wp_set_script_translations( handle, 'lwtv-underscores' )`.
- **Start-up data:** `wp_add_inline_script( handle, 'window.lwtvMeiliRules = ' . wp_json_encode( { restPath: 'lwtv/v1/meili-rules', pollMs: 2000, pollMaxMs: 60000 } ) . ';', 'before' )`. `api-fetch` supplies the REST nonce itself.
- **Missing build:** if the build is missing, which only happens in local dev before a build, show an admin notice saying to run `npm run build:workspace`.

- [ ] **Step 1: Scaffold.** Run `nvm use && npm install`. That updates `package-lock.json`: keep that change and call it out in the hand-over. Run `npm run build:workspace`. Expected: `admin/build/index.js` and `index.asset.php` exist, and the `.asset.php` lists `wp-element`.
- [ ] **Step 2: Live check.** As an admin, the menu item shows and the placeholder renders. As an editor, there's no menu item.
- [ ] **Step 3: Verify the lockfile.** Run `npm run lint:js`, including `lint:js:meili`, and `npm ci --dry-run`. That checks the lockfile is consistent, since CI uses `npm ci`. Expected: both clean.

---

### Task 6: The React app

**Files:**
- Create under `plugins/lwtv-plugin/php/meilisearch/admin/src/`: `index.js`, `App.js`, `TwoWay.js`, `OneWay.js`, `Status.js`, `ImportExport.js`, `TestBox.js` and `style.scss`. The split is the builder's call; this list is a suggestion.

**Interfaces:**
- **Consumes:** the REST contract from Task 4, `window.lwtvMeiliRules`, `@wordpress/components` (`Panel`, `PanelBody`, `FormTokenField`, `TextControl`, `Button`, `Notice`, `Spinner`, `Modal`), `@wordpress/api-fetch` and `@wordpress/i18n`.

- [ ] **Step 1: Behavior.** Implement each part as the spec's "The app" section describes:
  - **Two-way groups:** one `FormTokenField` per group, with a remove button named "Remove the group <words>", plus an "Add a group" button.
  - **One-way entries:** a `TextControl` for `from`, a `FormTokenField` for `to`, a remove button, and an "Add one" button.
  - **Messages:** errors and warnings come from the 400 response or from `GET`'s `warnings`, and show under the row they belong to. A top summary `Notice` has buttons that focus the first problem row.
  - **Save and apply:** `POST` the list. While any index is "applying", poll `GET` every `pollMs`, for up to `pollMaxMs`. The status line reads "Applied to N of M set-up indexes · <relative time>", plus "<type> isn't set up here" for each missing index, and each failed index's message.
  - **Apply again:** shown when any index failed or is "not set up". It calls `POST /apply`.
  - **Leaving with unsaved changes** triggers a `beforeunload` prompt.
  - **Starter list:** when `starter` isn't null and the list is empty, a `Notice` offers "Load starter list". It fills the editor and doesn't save.
  - **Export:** download `{ version: 1, synonyms }` as `lwtv-search-rules-<location.hostname>-<YYYY-MM-DD>.json`.
  - **Import:**
    1. A file input reads the file and parses it in the browser. A parse failure shows a `Notice`.
    2. A `Modal` shows the counts and the "replaces the current list" warning.
    3. Confirming loads the list into the editor and saves through `POST`.
  - **Test box:** `GET /test?q=` shows the top 3 for each index under the box. An empty query is disabled.
  - **Live region:** a polite region (`role="status"`) announces "Saved", "Applied to N of M", and the error count.
  - **Text from the API** is rendered as React text, never with `dangerouslySetInnerHTML`.
- [ ] **Step 2:** Run `npm run build:workspace` and `npm run lint:js`. Expected: both clean.

---

### Task 7: Live verification and docs

**Files:**
- Modify: `docs/architecture/meilisearch.md`. Add a "Search rules" section covering the option shape, the store, the REST routes, how applying and refreshing work, the CLI merge and drift check, and the build workspace.
- Modify: `docs/runbooks/meilisearch.md`. Add a "Search rules (synonyms)" section covering how to edit, what "not set up" means, export from staging and import on production, and `wp lwtv meili synonyms apply` / `show`. Also add a symptom row: "a synonym doesn't work" → check `status` for drift, then check whether a word is a stop word.
- Regenerate with `npm run docs:structure`, and fill in any `TODO: describe`.

- [ ] **Step 1: Browser checks on lwtv.local** using the built-in browser, logged in as an admin. Each one passes or fails:
  - Load the starter list, then save. Every set-up index goes from "applying" to "ok" within a few seconds, and the status line says 4 of 4.
  - The Test box finds the right result for "tlw", "ds9", "faq", "enby" and "kdrama". The header modal does too.
  - Put the same word in two groups. The save is refused, the error shows under the second row, and the summary button moves focus there.
  - Add "from" to a group. A warning shows, and the save still succeeds.
  - Use the hostile-import check (Review Focus 5). The word shows as plain text, and no alert fires.
  - Export, change one entry, then import the exported file. The list goes back to the exported version.
  - Make the server unreachable with a throwaway `pre_http_request` filter on `multi-search` and settings calls. The save keeps the list, and the status line shows the error for each index. Remove the filter, then "Apply again" leads to "ok".
  - Use the whole page with the keyboard alone, and check the accessibility tree: every control is labelled, the remove buttons are named after their rows, and one announcement fires per save.
  - As an editor, there's no menu item, and the REST routes return 403.
- [ ] **Step 2: Leave the data in a known state.** Keep the starter list saved on lwtv.local, since that's the point, and say so in the hand-over. Remove every throwaway mu-plugin or filter.
- [ ] **Step 3: The full gate.** Run `vendor/bin/phpunit && composer lint && TMPDIR=<scratch> composer analyse && npm run lint:js && npm run lint:css && npm run build:workspace && npm run docs:structure:check`. Expected: all green.
- [ ] **Step 4: Hand over.** Leave everything uncommitted in the main checkout. Call out the `package-lock.json` change. Report each check with its evidence, plus every ruling made along the way.
