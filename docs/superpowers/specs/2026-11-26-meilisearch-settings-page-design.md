# Meilisearch Search Settings page: design

Status: draft for Mika's approval (2026-11-26). Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Follows the Search Rules admin page (`2026-11-26-meilisearch-search-rules-admin-design.md`).

## Why

Mika wants to run search from wp-admin without editing wp-config.php. Today three things need wp-config or a terminal:

- turning Meilisearch search on or off for editors or everyone (the `LWTV_MEILI_MODAL` constant);
- seeing whether the server and indexes are healthy, and whether their settings have drifted (`wp lwtv meili status`);
- setting up a missing index or repairing one (`wp lwtv meili reindex`).

The timeout before search falls back is also hard-coded, in three places.

## Goals

- A **Search → Settings** page for admins only (`manage_options`), under the top-level Search menu after Rules and Compare.
- **Search switch:** Off / Editors / Everyone, stored in an option. When `LWTV_MEILI_MODAL` is defined in wp-config.php, **the constant wins**: the control shows its value greyed out, with a note that it's set in wp-config.php.
- **Fallback:** one timeout for every Meilisearch search surface (the header modal, the results page and the FacetWP keyword facets), editable. A read-only line says what search falls back to: SearchWP while it's active, WordPress search after that. Detection stays automatic.
- **Status dashboard:** server health, host, index prefix, which keys are set (never their values), and, per index, whether it exists, its documents, whether it's indexing, and whether its settings and synonyms match.
- **Routine jobs from the dashboard,** run in the background with progress on the page:
  - **Apply settings:** re-send the repo settings plus the Rules page's synonyms to an index that exists.
  - **Set up:** create a missing index, apply its settings, and fill it.
  - **Reindex:** the same repair as `wp lwtv meili reindex` (re-send every published post, then prune documents that aren't published).
- **One app:** the Rules and Settings screens become one React app and one bundle, with WordPress's menu doing the routing.

## Non-goals

- **Typo tolerance.** Mika chose it for this page, but it gets its own design next (the earlier notes: per-index overrides of `minWordSizeForTypos`, `disableOnWords` and `disableOnAttributes`, a preview, reset to repo defaults). The Settings screen leaves room for it as a tab.
- **Purging the page cache.** nginx with Redis caches logged-out pages; purging stays manual (Mika). The page only warns.
- **Keys and URL on the page.** `LWTV_MEILI_URL`, `LWTV_MEILI_INDEX_KEY`, `LWTV_MEILI_SEARCH_KEY` and `LWTV_MEILI_INDEX_PREFIX` stay in wp-config.php. Keys are per environment: a production database copied to staging would otherwise hand staging production's indexing key, and staging would write to production's indexes.
- **`reindex --fresh` from the page.** Emptying an index is one click from an empty search box on production, so it stays in the CLI.
- **Porting Search Compare into the app.** It stays a plain PHP page for now; the one-app structure makes that a later, separate change.
- **A choice of fallback.** It's detected (SearchWP if active, otherwise WordPress search), as Mika decided.

## Anti-goals

- The switch silently doing nothing because a forgotten constant overrides it. The page must say, every time, when the constant is in charge.
- A web request that runs long. Every job runs in Action Scheduler, in batches; no request waits on Meilisearch more than one short timeout.
- Two jobs on the same index at once, or a job creating an index by accident. "Set up" is the only path that creates one, and only for an index that's missing.
- The page and the CLI drifting apart. Reindex and set-up share the CLI's code, not a copy of it.
- Any key reaching the browser, a log or a REST response.

## Approaches considered

1. **A second screen in the existing workspace, with its own bundle.** Matches Rules, but two builds and duplicated helpers.
2. **A classic PHP settings page** (`register_setting` plus admin-post handlers). Less code for the form, but progress polling needs JavaScript anyway, leaving two styles of admin page side by side.
3. **One combined React app with its own router.** In wp-admin each submenu item is its own URL (menu highlighting, back button and bookmarks all depend on it), so an in-app router mostly duplicates the WordPress menu.
4. **One app, one bundle, WordPress routing.** **Chosen by Mika.** The workspace builds a single `index.js`. It checks which mount point the page printed and renders that screen. Shared pieces live in one folder. It gives the combined app's structure without a router or a rewrite of Rules. Cost: each Search page loads every screen's code, a few tens of KB. Splitting it again later is a build-config change.

## Decided behaviour

### The switch

- Stored in a new option, `lwtv_meili_settings` (see Storage), as `off`, `editors` or `everyone`. The default is `off`.
- **Precedence:** if `LWTV_MEILI_MODAL` is defined, its value is used exactly as today (`Modal_Switch::enabled()`: anything other than `'editors'` or `'everyone'` is off). Otherwise the option is used.
- `Search_Modal::enabled()` reads the effective value, so the modal, the results page and the FacetWP engines all follow it, as they follow the constant today.
- **On the page:** a three-way control (Off / Editors / Everyone) with one line saying what each means:
  - "Editors" means users who can edit posts.
  - "Everyone" includes visitors and the page cache.
- When the constant is defined:
  - the control is disabled and shows the constant's value (an invalid value shows as Off, with the raw value named);
  - a note reads, for example, "Set in wp-config.php (`LWTV_MEILI_MODAL`). Remove that line to manage this here."
- The REST API refuses to change the switch while the constant is defined (409).
- **Seamless hand-over** (*builder's-check call*): while the constant is defined with a valid value and the option has no switch saved yet, loading the Settings screen saves the constant's value into the option, and the note says so ("…also saved here, so removing the line keeps it"). Removing the define later then changes nothing for visitors. An invalid constant value seeds nothing.
- **Page cache: warn only** (Mika, 2026-11-26). Logged-out pages are cached by nginx with Redis, outside the plugin, and purging stays manual. When the switch moves to or from Everyone, the save's notice and a standing line under the control say visitors see the change only as cached pages expire, and that purging the page cache applies it now. The page never purges anything. The same goes for the timeout, which also lands in cached markup for Everyone.
- The existing safety rules stay: the switch is off when the module isn't configured, or when the search key equals the indexing key. The page says which of these is stopping it.

### Fallback and timeout

- One **timeout**, in milliseconds: default 2000, range 500 to 5000, step 100. Out-of-range values are refused, not clamped.
- Used by:
  - the modal (`Modal_Config` `timeout`, ms);
  - the results page (`Search_Page`);
  - the FacetWP engines (`FacetWP_Engine`).
  The last two pass seconds to `Client::multi_search()`, which therefore takes a float (`wp_remote_request` accepts fractional seconds).
- **Read-only fallback line:** "If Meilisearch doesn't answer in time: SearchWP" when SearchWP is active, otherwise "WordPress search". The detection is the one each surface already uses.

### Status dashboard

One `GET` builds it. When the server's health check fails, it reports "unreachable" and skips the per-index calls, so the page never waits on several timeouts.

| Shown | Source |
| --- | --- |
| Server: available / unreachable, and the log-safe error | `Client::health()` |
| Host and index prefix | `Config` |
| Keys: URL set, indexing key set, search key set, search key differs from indexing key | `Config` (booleans only) |
| Switch and timeout in effect, and where the switch comes from | the option and the constant |
| Per index: uid, label, exists / missing / unknown, documents, indexing now | `Index::state()`, `index_stats()` |
| Per index: settings match the repo file, or which keys differ | `get_settings()` vs `config/{type}.json`, the same comparison as `wp lwtv meili status` (which skips `synonyms`) |
| Per index: synonyms match the Rules page | `Search_Rules::same()` vs the store's map |
| Per index: the current or last job | the jobs option |

**Buttons per index:**
- **Apply settings:** shown when the index exists and its settings or synonyms differ.
- **Reindex:** shown when the index exists.
- **Set up:** shown when the index is missing.
- **Cancel:** shown while a job runs.

All of them are disabled while any job runs on that index. "Refresh" re-reads everything.

### Jobs

- **One job per index at a time** (*builder's-check call* on how far that reaches):
  - The lock lives in the shared reindex glue, not only in REST. A page job holds it from start to done, failed or cancelled.
  - The CLI's `reindex` (plain and `--fresh`), `settings` and `synonyms apply` check it and stop with an error naming the running job ("Cancel it on Search → Settings, or wait"), so the CLI can't delete or recreate an index under a page job.
  - The Rules page's Save and apply is **not** blocked. A synonyms-only settings update is safe alongside a reindex, because Meilisearch runs an index's tasks in order. Per-post sync tasks aren't blocked either; they're the same writes a reindex makes.
  - Steps are written to survive outside changes anyway: Set up treats "index already exists" at its create step as "carry on with settings", not a failure, and any step that finds its index missing fails the job with that message.
- A job is `{ kind: apply | setup | reindex, state, step, page, sent, total, task, message, started, finished }`, stored per index type in the option `lwtv_meili_jobs` (not autoloaded). Starting a job while one runs on that index is refused (409).
- **Apply settings:** one `update_settings()` with the repo file plus `synonyms_map()`, using the short timeout. It stores the task uid. Done when the task succeeds, checked on the page's polls the way Rules does. It has no Action Scheduler step.
- **Set up**, as Action Scheduler steps:
  1. Check again that the index is missing (if it now exists, the job carries on with its settings: see the lock bullet above).
  2. `create_index()`, and wait for that task across steps (each step re-checks once, then reschedules itself).
  3. `update_settings()` with the repo file plus synonyms.
  4. The reindex batches, below.
- **Reindex**, as Action Scheduler steps, one action per batch of `WP_CLI_LWTV_Meili::BATCH` (500) published posts:
  - Each step builds that page's documents with `Index::documents()` and sends them with `add_documents()`, without waiting. Meilisearch runs an index's tasks in order.
  - It records the built IDs and schedules the next page.
  - After the last page, one prune step sends `delete_documents_by_filter( Prune_Filter::not_in( ids ) )`. The job is done when that task succeeds.
  - A reindex of a missing index is refused, and points the admin to Set up.
- **Shared with the CLI:** the page-of-posts query, document building and prune move out of `WP_CLI_LWTV_Meili::reindex_one()` and `apply_settings()` into a WordPress-glue class used by both the CLI and the jobs. The CLI keeps its blocking waits; the jobs don't wait.
- **Failure:** any `WP_Error` sets the job to `failed`, with the log-safe message, and stops scheduling. The page offers to start it again.
- **Cancel:** sets the job to `cancelled`. The next step sees that and stops. Documents already sent stay.
- **Without Action Scheduler,** Set up and Reindex are disabled, with a note to use the CLI. Apply settings still works.
- **Hooks:** `lwtv_meili_job_task` (args: index type), group `lwtv`, so jobs show in Tools → Scheduled Actions with the others.

### REST API

Namespace `lwtv/v1`. Every route requires `manage_options` and the standard REST nonce.

| Route | Does |
| --- | --- |
| `GET meili-settings` | `{ settings: { switch, timeout_ms }, constant: { defined, value } , fallback: 'searchwp' \| 'wordpress', limits: { timeout_min, timeout_max, timeout_step } }` |
| `POST meili-settings` `{ switch?, timeout_ms? }` | Validates and saves. `400` on a bad value; `409` on `switch` while the constant is defined. Returns the same shape as `GET`. |
| `GET meili-settings/status` | The dashboard (above), including each index's job. |
| `POST meili-settings/jobs` `{ type, kind }` | Starts a job. `409` if one is running on that index, or the kind doesn't fit the index's state. Returns the job. |
| `DELETE meili-settings/jobs/{type}` | Cancels the running job on that index. |

### The app

- **Structure:** one workspace (`meilisearch/admin/`, `@lwtv/meili-admin`), one `wp-scripts build`, one `build/index.js`.
  - `src/index.js` mounts the Rules screen on `#lwtv-meili-rules-root`, or the Settings screen on `#lwtv-meili-settings-root`.
  - `src/rules/` holds today's Rules app, moved, not rewritten.
  - `src/settings/` holds the new screen.
  - `src/shared/` holds what both use: `Tabs`, row messages, the polite live region (as a hook), polling with the stale-response guard and retry from the Rules fixes, and small API helpers.
- **Settings layout:** tabs for **General** (the switch, then Fallback with the timeout, then Save) and **Status** (the dashboard and its buttons). Typo tolerance can be added as a third tab later.

  ```
  Search Settings
  [ General ] [ Status ]

  General
    Who gets Meilisearch search    ( ) Off  (•) Editors  ( ) Everyone
                                   Set in wp-config.php (LWTV_MEILI_MODAL)…   ← when defined; control greyed out
    Fallback
      Timeout   [ 2000 ] ms   If Meilisearch doesn't answer in time: SearchWP
    [ Save settings ]

  Status                                              [ Refresh ]
    Server: available · search.lezwatchtv.com · prefix "local"
    Keys: indexing ✓ · search ✓ (differs from indexing ✓)
    Index         State     Documents  Settings   Synonyms   Job                 Actions
    Characters    exists    7,357      match      match      —                   [Reindex]
    Content       missing   —          —          —          —                   [Set up]
    Shows         exists    2,266      differ: … match      reindexing 3 / 5    [Cancel]
  ```

- **Polling:** while any job runs, the app polls `GET meili-settings/status` every 2 seconds. It shows each job's step and count, and announces start, finish and failure in the live region.
- **Strings:** `@wordpress/i18n`, with the `lwtv-underscores` text domain. API text is rendered as React text only.
- **Accessibility:** WCAG 2.1 AA and keyboard-only, as Rules is.
  - The status table is a real `<table>` with headers.
  - Row action buttons name their index ("Reindex Characters").
  - Disabled controls stay focusable, with the reason given.

### PHP pieces

- `Search_Settings_Page`: the Settings submenu under `Search_Menu::PARENT` (slug `lwtv_meili_settings`), its mount point, and the shared enqueue. Rules and Settings enqueue the same bundle, through one helper, with their own `window` config.
- A REST controller for `meili-settings`, registered like `Search_Rules_Rest`.
- A store for `lwtv_meili_settings` (the effective switch, the timeout), read by `Search_Modal`, `Modal_Config`, `Search_Page` and `FacetWP_Engine`.
- The job runner and the shared reindex glue.
- **Pure, in `build/`, test-first:**
  - the effective switch from the constant and option (value plus source);
  - timeout validation;
  - the dashboard's drift comparison (the one `status` uses);
  - job state transitions: start allowed for this state, next step, done, failed, cancelled.

### Storage

- `lwtv_meili_settings`: `{ version: 1, switch: 'off'|'editors'|'everyone', timeout_ms: int }`, **autoloaded**, because `Search_Modal::enabled()` reads it on every front-end request.
- `lwtv_meili_jobs`: `{ <type>: job }`, not autoloaded.
- A job's built IDs live in a per-job transient or option until its prune step runs, then they're deleted.

## Constraints

- PHP 8.1+, WordPress 6.5+, Meilisearch v1.57. The repo's PHPCS, PHPStan, ESLint and Stylelint rules, and wp-prettier.
- Admins only on every menu item, route and enqueue.
- Write only to this environment's prefixed indexes.
- SearchWP and its add-ons stay untouched.
- The app's build stays gitignored and built by CI's `npm run build:workspace`.
- Nothing is committed without Mika's approval in that turn.

## Left to the builder

- The exact copy.
- How the job runner is split into classes.
- Whether built IDs go in a transient or an option.
- How the shared enqueue helper is named.
- The status table's look on phones (stacked rows are fine).
- Whether "Refresh" also runs automatically on page load (yes is fine).

## Verification

- **PHPUnit** for every pure piece:
  - effective switch: constant defined with a valid value, an invalid value, undefined plus each option value, a missing option;
  - timeout bounds;
  - drift comparison;
  - job transitions, including cancel and fail mid-run.
- **Static checks:** `composer lint`, PHPStan, `npm run lint:js`, `npm run lint:css`, `npm run build:workspace`, `npm run docs:structure:check`.
- **Live on lwtv.local** (local_* indexes only):
  - With `LWTV_MEILI_MODAL` defined (lwtv.local has `'editors'`), the switch is greyed out with the note, the option gets seeded with `editors`, and `POST` of `switch` returns 409.
  - The option path without the constant is covered by the pure effective-switch tests, plus a `wp eval` that calls the store and `Search_Modal` with the constant's lookup bypassed through a test seam in the store, never by editing wp-config.php. Mika removes the define herself when she's ready.
  - The timeout saves and reaches the modal config, the results page and the FacetWP request. 400 and 6000 are refused.
  - The dashboard matches `wp lwtv meili status`.
  - Apply settings fixes a hand-made drift.
  - Reindex on content runs in batches and finishes with the same document count as the CLI.
  - Set up on a throwaway uid creates, configures and fills it, and the throwaway index is then deleted. *Builder's-check call:* a debug-only filter, `lwtv_meili_job_index` (`?Index`, args: type), lets a throwaway mu-plugin swap the registered `content` index for a stand-in whose `uid()` is `local_nope`, as the Rules checks did. REST still accepts only types in `Indexes::types()`. The filter only changes which `Index` object a valid type resolves to, in both the REST request and the Action Scheduler request.
  - Cancel stops a reindex.
  - A failed step shows its message.
  - Editors get no menu item, and 403 from every route.
  - Rules still works after the move into `src/rules/`.
  - Keyboard-only use, plus an accessibility-tree check.
