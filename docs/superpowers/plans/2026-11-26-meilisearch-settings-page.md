# Meilisearch Search Settings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Search → Settings, an admin page for three things:
- the search switch, which now lives in an option (the constant still wins);
- one fallback timeout;
- a status dashboard that can run Apply settings, Set up and Reindex as background jobs.

At the same time, fold Rules and Settings into one React app.

**Architecture:**
- **Pure logic in `meilisearch/build/` (unit-tested):** the effective switch, timeout validation, settings drift and job state transitions.
- **WordPress glue:**
  - a settings store, read by the modal, the results page and the facets;
  - a job runner on Action Scheduler, sharing the reindex code the CLI moves into;
  - a REST controller;
  - a Settings page class.
- **The app:** one `@wordpress/scripts` bundle that mounts Rules or Settings depending on which mount point the page printed.

**Tech Stack:** PHP 8.1+ / WordPress 6.5+, PHPUnit (pure only), WP REST API, Action Scheduler, React via `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch` and `@wordpress/i18n`. Meilisearch v1.57.

**Spec:** `docs/superpowers/specs/2026-11-26-meilisearch-settings-page-design.md`

## Global Constraints

- **Never commit** without Mika's explicit approval in that turn. Leave the work uncommitted in the main checkout (`/Users/ipstenu/Development/lezwatch/lwtv-underscores`, `release/8.0.0`). A hook blocks Edit/Write there, so build in the worktree and copy across with a script. **Never overwrite files another session changed there** (e.g. `inc/js/meili-search.js`, `searchform.php`, `class-search-page.php`). Merge instead.
- **Admins only:** every menu item, route and enqueue checks `manage_options`.
- **Keys:** keys and the URL stay in wp-config.php. REST, the page and logs show only booleans and log-safe messages, never a key. Don't print page HTML or config JSON in output.
- **Writes:** only to `local_*` indexes. Leave SearchWP and its add-ons untouched. Remove throwaway mu-plugins afterwards. Never edit lwtv.local's wp-config.php: the `LWTV_MEILI_MODAL 'editors'` define stays.
- **Switch values:** `off`, `editors`, `everyone`; default `off`. If `LWTV_MEILI_MODAL` is defined, its value is used exactly as `Modal_Switch::mode()` reads it.
- **Timeout:** milliseconds. Default **2000**, min **500**, max **5000**, step **100**. Out-of-range or non-step values are refused, never clamped.
- **Options:**
  - `lwtv_meili_settings` = `{ version: 1, switch, timeout_ms }`, **autoloaded**;
  - `lwtv_meili_jobs` = `{ <type>: job }`, not autoloaded.
- **Jobs:**
  - one per index at a time; batch **500** (`WP_CLI_LWTV_Meili::BATCH`);
  - Action Scheduler hook `lwtv_meili_job_task`, args `[ type ]`, group `lwtv`;
  - no `wait_for_task()` in a web request or a job step;
  - `--fresh` is never offered.
- **Page cache:** warn only. When the switch moves to or from `everyone`, or the timeout changes while it's `everyone`, say that visitors see the change only as cached pages expire, and that purging the page cache applies it now. Never purge.
- **Polling:** every 2 s while a job runs, with the stale-response guard and the retry already in the Rules app.
- **Strings:** `__()` / `@wordpress/i18n`, with the `'lwtv-underscores'` text domain. API text renders as React text only.
- **Accessibility:** WCAG 2.1 AA, keyboard-only, one polite live region.
- **Tooling:** run `nvm use` (Node 24) before npm. Keep `package-lock.json` consistent (CI runs `npm ci`). Set `TMPDIR` to a scratch dir for PHPStan. Use the wp-cli wrapper with the mysqli socket override.

## Review Focus

1. **A constant with an invalid value** (`true`, `'yes'`) reads as Off; `'Editors '` reads as editors, the way `Modal_Switch::mode()` always has (corrected after the final review). The page shows the raw value and that the constant is in charge, and the option is not seeded. Pinned in Task 1 (`effective` and `seed` cases) and Task 6 (live).
2. **Hostile timeout and switch input** (`'2000'`, `2000.5`, `499`, `5001`, `2050`, `null`, arrays, `'EVERYONE'`) is refused with 400 and nothing is saved. A corrupted stored option reads back as the defaults. Pinned in Task 1 and Task 5.
3. **An index that changes under a running job.** Set up finds the index already created, so it continues with settings. A reindex or apply step finds the index missing, so the job fails with that message. Pinned in Task 3 (transitions) and Task 4 (live).
4. **Cancel racing a step.** A step re-reads the job before it works and before it schedules the next step, so a cancelled job never schedules another step. Pinned in Task 3 and Task 4.
5. **The status request with the server down.** It makes one health call, skips the per-index calls, and still returns the full shape, with each index marked unknown. Pinned in Task 5 (live, throwaway `pre_http_request` filter).

---

### Task 1: Pure settings logic

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-search-settings.php` (`LWTV\Meilisearch\Build\Search_Settings`)
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-settings-drift.php` (`Build\Settings_Drift`)
- Modify: `tests/bootstrap.php`: require both
- Test: `tests/unit/Meilisearch/SearchSettingsTest.php`, `tests/unit/Meilisearch/SettingsDriftTest.php`

**Interfaces (Produces):**
- **`Search_Settings` constants:** `DEFAULT_SWITCH = 'off'`, `TIMEOUT_DEFAULT = 2000`, `TIMEOUT_MIN = 500`, `TIMEOUT_MAX = 5000`, `TIMEOUT_STEP = 100`, `VERSION = 1`.
- **`Search_Settings::defaults(): array`** returns `{ version: 1, switch: 'off', timeout_ms: 2000 }`.
- **`Search_Settings::read( mixed $stored ): array`** returns the stored option with each bad or missing field replaced by its default.
- **`Search_Settings::validate( mixed $input ): array{ values: array, errors: array<string,string> }`**
  - Accepts only the keys `switch` and `timeout_ms`.
  - `switch` must be exactly one of the three strings (lowercase, no trimming).
  - `timeout_ms` must be an `int` in range and on the step.
  - Unknown keys are ignored. Errors are keyed by field.
- **`Search_Settings::effective( bool $defined, mixed $constant, array $settings ): array{ mode: string, source: 'constant'|'option', raw: ?string }`**
  - When `$defined`: `mode` is `Modal_Switch::mode( $constant )` and `raw` is the constant as a string, or `null` when it's not a scalar.
  - Otherwise: `mode` is the option's switch and `source` is `'option'`.
- **`Search_Settings::seed( bool $defined, mixed $constant, mixed $stored ): ?string`** returns the value to save into the option. That's the constant's normalized mode, but only when:
  - `$defined` is true;
  - the constant normalizes to `'editors'` or `'everyone'`, or is exactly `'off'`;
  - the stored option has no valid `switch`.
  Otherwise it returns `null`.
- **`Settings_Drift::differs( array $repo, array $live ): string[]`** returns the repo keys whose live value differs (`!==`), skipping `synonyms`, in repo order.

- [ ] **Step 1: Write the failing tests.** Use `assertSame` throughout.
  - **`read()`:**
    - `null` → defaults.
    - `{ switch: 'EVERYONE', timeout_ms: '3000' }` → defaults.
    - `{ switch: 'editors', timeout_ms: 3000 }` → kept.
  - **`validate()`:**
    - **Refused** (one error each): `'2000'`, `2000.5`, `499`, `5001`, `2050`, `null` and `array()` as `timeout_ms`. As `switch`: `'EVERYONE'`, `'Editors '`, `true`, `''`.
    - **Accepted:** `{ switch: 'everyone', timeout_ms: 500 }` and `{ timeout_ms: 5000 }`. `{ key: 'x', applied: 1 }` gives empty values and no errors.
  - **`effective()`:**
    - Defined `'editors'` → `{ editors, constant, 'editors' }`.
    - Defined `true` → `{ off, constant, '1' }`.
    - Defined `'Editors '` → `{ editors, constant, 'Editors ' }` (`mode()` trims and lowercases).
    - Defined `array()` → `{ off, constant, null }`.
    - Undefined with option `'everyone'` → `{ everyone, option, null }`.
  - **`seed()`:**
    - Defined `'everyone'`, stored `null` → `'everyone'`.
    - Defined `'everyone'`, stored `{ switch: 'off' }` → `null`.
    - Defined `'yes'` → `null`.
    - Undefined → `null`.
  - **`Settings_Drift::differs()`:**
    - Equal → `[]`.
    - One changed `rankingRules` → `[ 'rankingRules' ]`.
    - Live missing a key → that key.
    - A repo `synonyms` key that differs → ignored.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter 'SearchSettings|SettingsDrift'`. Expected: errors, because the classes aren't found.
- [ ] **Step 3: Implement both classes.** Use an `ABSPATH` guard and static methods, with no WordPress calls (`Modal_Switch` is already pure and loaded by the bootstrap). In `cli-meili.php`, `status()` now uses `Settings_Drift::differs()` in place of its inline loop.
- [ ] **Step 4:** Run `vendor/bin/phpunit`. Expected: all green.

---

### Task 2: Settings store, and wiring the switch and timeout

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-settings-store.php`
- Modify:
  - `class-search-modal.php` (`enabled()`)
  - `build/class-modal-config.php` (`build()` gains `int $timeout_ms = self::TIMEOUT` as its last parameter)
  - `class-search-modal.php` (`config_json()` passes the store's timeout)
  - `class-search-page.php` and `class-facetwp-engine.php` (use the store's timeout in seconds). **`class-search-page.php` has another session's uncommitted edit in the main checkout:** merge into it, and never copy the worktree's version over it.
  - `class-client.php`: `multi_search( array $body, float $timeout = 2 )`, and `request()`'s `$timeout` becomes `float`
- Test: `tests/unit/Meilisearch/ModalConfigTest.php` (add a case: `build( …, 3500 )['timeout'] === 3500`; the existing default case still gives `2000`)

**Interfaces:**
- **Consumes:** `Search_Settings` (Task 1).
- **Produces `Search_Settings_Store`**, with constructor `__construct( ?\Closure $constant = null )`. The closure returns `array{ bool, mixed }` (defined, value). The default reads `LWTV_MEILI_MODAL`. The closure is the test seam for the option-only path.
  - `OPTION = 'lwtv_meili_settings'`, `CONSTANT = 'LWTV_MEILI_MODAL'`
  - `get(): array`, which is `Search_Settings::read( get_option( OPTION ) )`
  - `effective(): array`, which is `Search_Settings::effective( …, get() )`
  - `timeout_ms(): int`; `timeout_seconds(): float`
  - `save( mixed $input ): array{ ok: bool, errors: array, values: array, status: int }`
    - `status` 400 on any error;
    - 409 if `switch` is present while the constant is defined;
    - otherwise it merges into `get()` and calls `update_option( OPTION, $merged, true )`.
  - `seed(): bool` writes `Search_Settings::seed(…)` into the option when non-null, and returns whether it wrote.
  - `constant(): array{ defined: bool, value: ?string }`
- `Search_Modal` gets the store injected (default `new Search_Settings_Store()`) and uses `effective()['mode']` in place of the raw constant. `Modal_Switch::enabled()` receives that mode.

- [ ] **Step 1:** Add the failing `ModalConfigTest` case. Run `vendor/bin/phpunit --filter ModalConfig`. Expected: FAIL on the extra argument.
- [ ] **Step 2: Implement the store and the wiring.**
- [ ] **Step 3:** Run `vendor/bin/phpunit`, `composer lint`, and `TMPDIR=<scratch> composer analyse`. Expected: clean.
- [ ] **Step 4: Live checks on lwtv.local** (`wp eval`; copy the files in first):
  - `(new Search_Settings_Store)->effective()` gives `{ editors, constant, 'editors' }`.
  - `save( [ 'switch' => 'everyone' ] )` gives status 409.
  - `save( [ 'timeout_ms' => 3500 ] )` gives ok. The modal config JSON's `timeout` is 3500: read it through `Search_Modal::config_json()` as an editor and print **only** `timeout`.
  - A throwaway `pre_http_request` logger shows the results-page and FacetWP multi-search requests using `timeout` 3.5.
  - Then save `timeout_ms` 2000 again.
  - `new Search_Settings_Store( fn() => [ false, null ] )` with the option set to `everyone` gives `{ everyone, option }`. Through that store, `Search_Modal::enabled()` is true for a logged-out user.
  - Restore the option's switch.

---

### Task 3: Pure job transitions

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-index-job.php` (`Build\Index_Job`)
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Meilisearch/IndexJobTest.php`

**Interfaces (Produces):**
- **Kinds:** `APPLY = 'apply'`, `SETUP = 'setup'`, `REINDEX = 'reindex'`.
- **States:** `RUNNING = 'running'`, `DONE = 'done'`, `FAILED = 'failed'`, `CANCELLED = 'cancelled'`.
- **Steps:** `S_CHECK = 'check'`, `S_CREATE = 'create'`, `S_SETTINGS = 'settings'`, `S_BATCH = 'batch'`, `S_PRUNE = 'prune'`, `S_WAIT = 'wait'` (waiting for the last task).
- **Job shape:** `{ kind, state, step, page, sent, total, task: ?int, message: string, started: int, finished: int }`.
- **`Index_Job::can_start( ?array $current, string $kind, string $index_state ): ?string`** returns an error message, or `null` when the job may start.
  - Any job in `running` refuses.
  - `setup` needs `missing`.
  - `apply` and `reindex` need `exists`.
  - An unknown kind refuses.
- **`Index_Job::start( string $kind, int $total, int $now ): array`** returns a running job at its first step:
  - `apply` → `settings`
  - `setup` → `check`
  - `reindex` → `batch`, at `page` 1
- **`Index_Job::advance( array $job, string $event, array $data, int $now ): array`** returns the next job. Events:
  - `exists` (Setup at `check`/`create`): → `settings`.
  - `missing` (at `check`): → `create`. At any other step: → `failed`, "The index no longer exists."
  - `created` (with `task`): → `create`, waiting on `task`.
  - `task_ok` while waiting at `create`: → `settings`.
  - `settings_sent` (with `task`):
    - `apply` → `wait`;
    - `setup` → `batch`, at `page` 1.
  - `batch_sent` (with `sent`, `last`): adds `sent`. With `last` → `prune`; otherwise `page` + 1.
  - `pruned` (with `task`): → `wait`.
  - `task_ok` at `wait`: → `done`, with `finished` set.
  - `failed` (with `message`): → `failed`.
  - `cancel`: → `cancelled`.
  - Any event on a job that isn't `running` returns it unchanged.

- [ ] **Step 1: Write the failing tests.**
  - `can_start`:
    - Refuses while running, and refuses each wrong state.
    - Allows `reindex` on `exists` and `setup` on `missing`.
  - Full happy paths for all three kinds, event by event, checking each `step`, `page`, `sent`, `state` and `task`.
  - `missing` mid-reindex → `failed` with that message.
  - `exists` at Setup's `create` → `settings`.
  - `cancel`, then `batch_sent` → still `cancelled`, with `page` unchanged.
  - `failed` keeps `sent`.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter IndexJob`. Expected: errors, because the class isn't found.
- [ ] **Step 3: Implement.**
- [ ] **Step 4:** Run `vendor/bin/phpunit`. Expected: green.

---

### Task 4: Shared reindex glue, the job runner, and the CLI lock

**Files:**
- Create:
  - `plugins/lwtv-plugin/php/meilisearch/class-reindexer.php` (the shared glue)
  - `class-index-jobs.php` (the runner and storage)
- Modify:
  - `wp-cli/cli-meili.php`: `reindex_one()`, `prune()` and `apply_settings()` call the `Reindexer`. `reindex`, `settings` and `synonyms apply` call `$jobs->running( $type )` first and `WP_CLI::error` with "A <kind> job is running on <uid> (started <time>). Cancel it on Search → Settings, or wait."
  - `class-meilisearch.php`: build the `Index_Jobs` and register its hook.

**Interfaces:**
- **Consumes:** `Index_Job` (Task 3), `Settings_Drift` (Task 1), `Search_Rules_Store::synonyms_map()`, `Index`, `Indexes`, `Client`, `Index_Settings`, `Prune_Filter`.
- **`Reindexer( Client $client, Search_Rules_Store $rules )` produces:**
  - `page_ids( Index $index, int $page, int $batch ): int[]`: the same `WP_Query` the CLI runs today.
  - `send_page( Index $index, int[] $ids ): array{ built: int[], result: array|\WP_Error }`: builds with `documents()` and sends with `add_documents( …, 60 )`. It returns the built IDs.
  - `settings_payload( Index $index ): array`: the repo file plus `synonyms`, sending `{}` when the map is empty (the CLI's current logic).
  - `prune( Index $index, int[] $built ): array|\WP_Error|null`: `null` when there's nothing to filter.
- **`Index_Jobs( Indexes $indexes, Client $client, Reindexer $reindexer )` produces:**
  - `OPTION = 'lwtv_meili_jobs'`, `HOOK = 'lwtv_meili_job_task'`
  - `index( string $type ): ?Index`: `Indexes::get()`, passed through the filter `lwtv_meili_job_index` (`?Index`, args `type`) and kept only if it's an `Index` of that type.
  - `get( string $type ): ?array`; `all(): array`; `running( string $type ): ?array`
  - `start( string $type, string $kind ): array|\WP_Error` (status 409 when `can_start` refuses). `apply` sends its settings right away; the other two schedule step 1.
  - `cancel( string $type ): array|\WP_Error`
  - `refresh( string $type ): ?array`: polls a `wait` or `create` task once (as `Search_Rules_Store::refresh()` does) and advances on `task_ok`.
  - `step( string $type ): void`: the hook handler, below.
  - **Built IDs** live in the option `lwtv_meili_job_ids_<type>` (not autoloaded). It's deleted on done, failed or cancelled.
  - `step()`:
    1. Re-read the job past the cache (`wp_cache_delete`). Return if it isn't running.
    2. Do this step's one unit of work.
    3. Advance the job.
    4. Re-read again. If it's still running and the next step needs a step action, `as_schedule_single_action( time() + 1, HOOK, [ type ], 'lwtv' )`.
  - `can_schedule(): bool` is `lwtv_plugin()->is_action_scheduler_available()`. Without it, `setup` and `reindex` refuse with "Action Scheduler isn't available; use `wp lwtv meili reindex`."

- [ ] **Step 1: Implement.** The CLI keeps its blocking waits around the `Reindexer` calls, and its output stays the same.
- [ ] **Step 2:** Run `vendor/bin/phpunit && composer lint && TMPDIR=<scratch> composer analyse`. Expected: clean.
- [ ] **Step 3: Live checks** (copy in first; local only):
  - **CLI unchanged:** `wp lwtv meili reindex --type=content` prints the same lines as before and ends with 930 documents.
  - **Reindex job:** `start( 'content', 'reindex' )`, then run the queue (`wp action-scheduler run --hooks=lwtv_meili_job_task`, repeated until idle), then `refresh()`. The job ends `done` with `sent` 930, and the index holds 930.
  - **Cancel:** start a reindex on `characters`, run one step, `cancel()`, then run the queue. The job is `cancelled` and no further `lwtv_meili_job_task` is pending.
  - **CLI lock:** with a running job, `wp lwtv meili reindex --type=characters` errors with the lock message. Cancel it afterwards.
  - **Set up (Review Focus 3):** a throwaway mu-plugin uses `lwtv_meili_job_index` to swap `content` for a stand-in whose `uid()` is `local_nope`. `start( 'content', 'setup' )` and run the queue: `local_nope` is created, configured, and filled with 930 documents. Then `delete_index( 'local_nope' )` and remove the mu-plugin.
  - **Fail:** a throwaway `pre_http_request` returns a `WP_Error` for `/documents`. A reindex job fails with that message. Remove the filter.

---

### Task 5: REST API `lwtv/v1/meili-settings`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-settings-rest.php`
- Modify: `class-meilisearch.php` (register it)

**Interfaces:**
- **Consumes:** `Search_Settings_Store`, `Index_Jobs`, `Search_Rules_Store`, `Settings_Drift`, `Search_Rules::same()`, `Client::health()`, `index_stats()`, `get_settings()`, `Config`.
- **Produces these routes,** each with `permission_callback` checking `current_user_can( 'manage_options' )`:
  - **`GET meili-settings`** returns `{ settings: { switch, timeout_ms }, effective: { mode, source, raw }, constant: { defined, value }, fallback: 'searchwp'|'wordpress', limits: { timeout_min: 500, timeout_max: 5000, timeout_step: 100 }, seeded: bool, blocked: null|'unconfigured'|'search_key_is_index_key' }`.
    - `blocked` names the existing safety rule that keeps search off whatever the switch says (`Config::is_configured()`, `Config::search_key_is_public()`).
    - It calls `store->seed()` first; `seeded` reports whether it wrote.
    - `fallback` is `'searchwp'` when `class_exists( '\SearchWP' )`.
  - **`POST meili-settings`** takes the body `{ switch?, timeout_ms? }`. It returns 200 with the `GET` shape, or a `WP_Error` `lwtv_meili_settings_invalid` (400, `data.errors`) or `lwtv_meili_settings_locked` (409).
  - **`GET meili-settings/status`** returns:

    ```
    { server: { ok, message },
      host, prefix,
      keys: { url, index_key, search_key, search_key_differs },
      action_scheduler: bool,
      indexes: [ { type, uid, label, state, documents: ?int, indexing: bool,
                   settings_drift: string[], synonyms_match: ?bool, job: ?array } ] }
    ```

    - Each running job is refreshed before it's returned.
    - With `server.ok` false, every index has `state` `unknown` and null stats, after exactly one `health()` call.
  - **`POST meili-settings/jobs`** takes `{ type, kind }`. `type` must be in `Indexes::types()` and `kind` one of the three. It returns 201 with the job, 400 or 409.
  - **`DELETE meili-settings/jobs/(?P<type>[a-z]+)`** returns 200 with the job, or 404 when no job is running.

- [ ] **Step 1: Implement.**
- [ ] **Step 2: Live checks** with `rest_do_request` in `wp eval` (print status codes and booleans only):
  - **Admin:** `GET` 200.
  - **Hostile input (Review Focus 2):** every value from Task 1's refused list gets 400 and leaves the option unchanged. `switch` gets 409 while the constant is defined.
  - **Status:** it matches `wp lwtv meili status` (document counts, drift, synonyms match).
  - **Editor:** user 12 gets 403 on all five routes.
  - **Server down (Review Focus 5):** with a throwaway `pre_http_request` failing every Meilisearch request, the status request makes exactly 1 request (count them in the filter) and returns 200 with every index `unknown`. Remove the filter.
  - **Jobs:** `POST jobs` with type `nope` gets 400. A second reindex on a running index gets 409.
- [ ] **Step 3:** Run `composer lint` and `TMPDIR=<scratch> composer analyse`. Expected: clean.

---

### Task 6: One app, the Settings page, and the General tab

**Files:**
- Move (git-unaware copy plus delete; the files are untracked): `admin/src/{App,TwoWay,OneWay,Status,ImportExport,TestBox,Messages,rules}.js` → `admin/src/rules/` (`App.js` → `rules/RulesApp.js`), and `Tabs.js` → `admin/src/shared/Tabs.js`. Fix the imports. `style.scss` is split into `rules/style.scss` and `shared/style.scss`, plus `settings/style.scss`.
- Create:
  - `admin/src/shared/useAnnounce.js`: the live-region hook, taken from `RulesApp`'s `say`/announcement.
  - `admin/src/shared/usePoll.js`: the generation guard and retry, taken from `RulesApp`, so `RulesApp` uses it too.
  - `admin/src/settings/SettingsApp.js` and `settings/General.js`.
- Modify: `admin/src/index.js`, which becomes the router. It mounts `RulesApp` on `#lwtv-meili-rules-root` and `SettingsApp` on `#lwtv-meili-settings-root`.
- Create: `plugins/lwtv-plugin/php/meilisearch/class-admin-app.php` (the shared enqueue: `Admin_App::enqueue( string $global, array $config ): bool`), and `class-search-settings-page.php`.
- Modify: `class-search-rules-page.php` (use `Admin_App`) and `class-meilisearch.php` (register the page after Compare).

**Interfaces:**
- **Consumes:** the Task 5 routes, and `Search_Menu::PARENT`.
- **`Search_Settings_Page`:**
  - Slug `lwtv_meili_settings`, title "Search Settings", menu label "Settings", `manage_options`.
  - Its mount point is `#lwtv-meili-settings-root`.
  - Config: `window.lwtvMeiliSettings = { restPath: 'lwtv/v1/meili-settings', pollMs: 2000, pollMaxMs: 600000 }`. A full reindex can run past 60 s, so polling continues while a job reports progress.
- **`SettingsApp`:** `Tabs` with `general` ("General") and `status` ("Status"). The tab names are fixed now; Typo tolerance comes later.
- **`General`:**
  - A `RadioControl` for Off / Editors / Everyone, each with its one-line meaning.
  - When `constant.defined`: disabled, showing `effective.mode`, with a note naming `LWTV_MEILI_MODAL` and the raw value (plus "also saved here" when `seeded`).
  - A `NumberControl`/`TextControl type=number` for the timeout, with min, max and step from `limits`.
  - The fallback line.
  - When `blocked` is set, a warning Notice: "Search is off on this site: the module isn't configured" (or "…the search key is the indexing key"), whatever the switch says.
  - The page-cache line (see Global Constraints).
  - A "Save settings" button that POSTs only changed fields, shows field errors inline, and announces the result.

- [ ] **Step 1: Move and restructure.** Run `npm run build:workspace` and `npm run lint:js`. Expected: clean. The Rules page on lwtv.local still loads, saves and tests a search, with no console errors.
- [ ] **Step 2: Implement the Settings page and General.** Build and lint. Expected: clean.
- [ ] **Step 3: Browser checks (Review Focus 1):**
  - Search → Settings shows under Rules and Compare.
  - The switch is greyed out and shows Editors with the constant note.
  - The timeout saves 3000, then shows 400's error inline (typed 450), then goes back to 2000.
  - The keyboard reaches every control, and the accessibility tree names each.
  - An editor sees no Settings item.

---

### Task 7: The Status tab and the jobs UI

**Files:**
- Create: `admin/src/settings/StatusTab.js` (and a small `IndexRow.js` if that helps)
- Modify: `settings/SettingsApp.js`, `settings/style.scss`

**Interfaces:**
- **Consumes:** `GET meili-settings/status`, `POST jobs`, `DELETE jobs/{type}`, `usePoll`, `useAnnounce`.
- **Produces:**
  - The server, keys, Action Scheduler and switch summary.
  - A `<table>` with headers Index / State / Documents / Settings / Synonyms / Job / Actions.
  - Buttons named with the index, for example "Reindex Characters":
    - Apply settings (drift only);
    - Reindex (when the index exists);
    - Set up (when it's missing);
    - Cancel (while a job runs).
  - "Refresh".
  - Polling every 2 s while any job is running, up to 10 minutes.
  - Announcements for start, done, failed and cancelled.
  - Rows stack below 782 px.

- [ ] **Step 1: Implement.** Build and lint. Expected: clean.
- [ ] **Step 2: Browser checks:**
  - The table matches `wp lwtv meili status`.
  - Hand-edit `local_shows`'s synonyms. Its row shows the drift, and Apply settings clears it.
  - Reindex Content shows progress (page counts) and finishes at 930, with announcements.
  - Cancel works.
  - Set up appears only for the throwaway `local_nope` stand-in (Task 4's mu-plugin), fills it, then delete `local_nope` and remove the mu-plugin.
  - Keyboard-only use, an accessibility-tree check, and phone width.

---

### Task 8: Docs and the full gate

**Files:**
- Modify: `docs/architecture/meilisearch.md`, with a "Search settings" section covering:
  - the option and the precedence (with seeding);
  - the timeout;
  - the fallback;
  - the status route;
  - the jobs (states, steps, lock, hook, filter);
  - the one-app layout.
- Modify: `docs/runbooks/meilisearch.md`, covering:
  - moving the switch off wp-config (on each environment, after deploy: open Settings once so the value is seeded, then remove the define);
  - the page-cache note;
  - reindex and set up from the page;
  - what a CLI lock error means;
  - plus a symptom row: "the switch is greyed out" → the constant is defined.
- Regenerate with `npm run docs:structure`, and fill in every `TODO: describe`.

- [ ] **Step 1:** Write the docs.
- [ ] **Step 2: The full gate.** Run `vendor/bin/phpunit && composer lint && TMPDIR=<scratch> composer analyse && npm run lint:js && npm run lint:css && npm run build:workspace && npm run docs:structure:check && npm ci --dry-run`. Expected: all green.
- [ ] **Step 3: Leave the data in a known state.**
  - The switch option seeded `editors`, with the timeout at 2000.
  - No running jobs, no `local_nope` index, no throwaway mu-plugins.
  - The starter synonyms still saved.
- [ ] **Step 4: Hand over.** Copy into the main checkout, merging around the other session's files. Leave everything uncommitted. Report each live check with its evidence, and every ruling.
