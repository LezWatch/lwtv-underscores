# Validation Screen

How the Data Validation admin screen (LWTV → Data Validation, `admin.php?page=lwtv_data_check`) decides what to show for each debugger check: where the findings come from, what each count means, and how repairs and baselines fit in.

Main code: `Admin_Menu\Validation` ([class-validation.php](../../plugins/lwtv-plugin/php/admin-menu/class-validation.php)), `Validator\Report`, `Debugger\Findings_Store`, `Debugger\Status`, `Debugger\Repair`, `Debugger\Baseline_Store`.

## The tab registry

`Validation::TOOL_TABS` has one entry per check. For report tabs the entry is the whole definition, and `Validator\Report` renders every report tab from it. A check therefore cannot have a tab without a scanner or copy, and findings keys cannot drift between the screen and the scanner.

| Key | Meaning |
|---|---|
| `name`, `desc` | Tab picker label and intro-table description |
| `option` | Key inside the debugger status option. Drives the "last run" line and the stored count. |
| `findings` | `Findings_Store` key the tab renders |
| `scanner` | `array( class, method )` producing fresh findings; takes an optional findings array for a recheck |
| `column`, `clean`, `dirty`, `note` | Report copy |
| `render` | For tabs that are not plain findings reports (Watch Providers, Watch Term Check) |
| `show_tab`, `tab` | `show_tab => false` keeps an entry out of the picker; `tab` names the tab its row links to instead |

`wp lwtv debug` keeps a parallel registry (`WP_CLI_LWTV_Debug::get_checks()`) for the CLI and cron rotation.

## Findings store vs status option

Each check writes two things:

| | `Findings_Store` (per check) | Debugger status option (`Debugger\Status`) |
|---|---|---|
| Holds | The rows: one per post (or term), with typed issues | Per check: display name, `count`, `last` run time |
| Storage | Non-autoloaded option plus index `lwtv_debug_findings_keys` | Options |
| Expires | After `Findings_Store::TTL` (10 days) | Never |

`Findings_Store::load()` returns one of three things, and callers keep them distinct:

- `false`: never run, or the findings aged out.
- `array()`: ran and found nothing.
- a non-empty array: ran and found these.

Expiry is what makes a check nobody has looked at rebuild itself instead of showing months-old figures. `Report::items()` relies on it: a `false` read triggers an automatic scan when the tab is opened.

Findings live in options rather than transients so a read cannot come back empty for reasons unrelated to the data: `LWTV_DISABLE_TRANSIENTS` in development, or WP-CLI and web requests not sharing an object-cache tier in production. See [caching.md](caching.md#cli-and-web-cache-tiers).

## Per-tab counts

`Validation::tab_counts()` builds one record per tab, memoised for the request. The tab-picker badge, the intro's Current Status table and the tab body all read it, so none of them can contradict another.

| Field | Source | Meaning |
|---|---|---|
| `count` | number of rows in the findings | outstanding items |
| `new` | rows whose `status` is `Baseline::NEW_ISSUE` | new since the last full run |
| `cached` | whether `Findings_Store::load()` returned an array | the findings exist |
| `stored` | status option `count` | what the check last recorded |
| `last` | status option `last` | when the check last ran |

**Counts come from the findings, not the status option.** The status option never expires and the findings do, so a count read from the option could advertise a number whose detail has gone (a badge of "47" over an empty report). `new` is counted from the same rows as `count`, so "N new / M" compares like with like.

### Count states

The intro table's "Issues Found" cell has four states:

| State | Condition | Shows |
|---|---|---|
| Outstanding | `count > 0` | a pill with the count |
| Clean | findings cached and empty | an em dash, labelled "No issues" |
| Stale, dated | no cached findings, but `last` is set | the stored count as a muted pill (or an em dash if it was zero), plus "as of N ago" |
| Never run | no cached findings and no `last` | "Not run" |

- **Clean vs never run.** An absence of findings is not good news. Treating the two as the same would let a check quietly stop running while looking clean.
- **Stale, dated.** The findings can be gone while the status entry remains. Reporting every such check as never run, while the site knows what it last found, would be worse than showing the old figure with its date.
- **`last`, not `stored`, answers "has it run".** A check that ran and found nothing records a count of zero against a real timestamp. Testing the count would report a clean check as never run.

The tab-picker badge shows only `count` (and `new`), never `stored`, so it never advertises a number with no detail behind it.

`Validation::last_run()` prints a check's own `last` time, or the global timestamp for the intro. A check that has never run gets "has not been run yet".

## Repairs

`Debugger\Repair` is the admin half of `wp lwtv debug <check> --fix-it`: the same `Issue_Registry` repairs, applied to one issue on one post at a time. It defines no repairs of its own. It is the request handler, the permission check (`edit_post`, memoised per request) and the cache bookkeeping.

- **Repairs are form POSTs, not links.** A repair writes to the database, so it must not sit behind something a browser or crawler could prefetch. The nonce is scoped to the post and issue (`Repair::nonce_action()`), so one row's nonce cannot be replayed against another.
- **A repair prunes the findings rather than deleting them.** `Repair::prune()` removes the fixed issue from that post's row (dropping the row if nothing is left), stores the result and updates the status count. Deleting the findings would send the next viewer into a full rescan to reflect one fixed field.
- Only issue levels in `Repair::LEVELS` (`show`, `character`, `actor`) can be repaired from the admin. Term-level issues (`watch_term`) never get a button.
- `Repair::init()` is hooked from code that runs on every admin request, because `admin-post.php` never fires `admin_menu`. `Validation::settings_page()` calls `Repair::show_notice()` once for every tab.

## Term-shaped findings

The Watch URL checks report on `lez_watch_urls` terms, not posts. `Findings::make_for_term()` sets `object_kind => 'term'`; `Findings::is_post()` is the test to run before treating `id` as a post. The identity is still carried in `post_id`: renaming it would mean migrating every stored row and every reader of `id` for no gain on the post-based checks.

`Validation::table_content()` dereferences `id` as a post, so it skips non-post rows. A future check that forgets its own renderer then gets an obviously missing row, not a plausible wrong one. The two watch tabs have their own renderers; see [watch-providers.md](watch-providers.md#watch-term-check-tab) for how their findings are triaged into "affecting" and "unused".

## Baselines

A baseline records what a check found last time, so this run can mark each finding `new` or `open` and report what was `resolved`.

- **Storage.** `Debugger\Baseline_Store`: one non-autoloaded option per check (`lwtv_debug_baseline_<scope>`) plus an index (`lwtv_debug_baselines`, `scope => { last_run, count }`). The payload is identity only (`Baseline::snapshot()`), so it is smaller than the findings.
- **Keyspace.** A finding's key is `post_id:issue_type`, plus `:identity` when set (a term can have several bad URLs, and each is its own problem). This is deliberately separate from the `wp lwtv audit` baselines, whose identity is `show_id:char_id:issue_type:year` and which were already populated under that format. Sharing the namespace would have meant rewriting Audit's identity function (resetting every audit scope) or mixing two key formats in one option space.
- **No baseline vs an empty baseline.** `Baseline_Store::exists()` is checked first. "No baseline" is a first run, where nothing is reported as new. "Empty baseline" means the check was clean last time.
- **Full runs only update it.** `Baseline_Store::apply()` diffs and saves. `tag_only()`, used by the admin Recheck (which re-scans only flagged posts), stamps rows without touching the baseline. A partial run saved as the baseline would make the next full run report every skipped post as new.
- **The raw finding set is saved**, not the displayed one, so a finding that is later filtered from display does not come back as `new` when un-filtered.

`wp lwtv debug <check> --reset-baseline` forgets a check's baseline, for use after a deliberate mass change.

In the report, only `new` issues are flagged. Marking everything else "open" would be noise on reports where most rows are long-standing by nature.

## Retired checks

Status entries outlive the check that wrote them, and the wp-admin dashboard widget (`_Components\Dashboard_Widgets::check_dashboard_content()`) lists every status entry with a count above zero. When a check is removed, add its status key to `WP_CLI_LWTV_Migrate::RETIRED_STATUS_KEYS` and run `wp lwtv migrate acf debugstatus` once. Otherwise the widget keeps showing a stale count that nothing can recompute.
