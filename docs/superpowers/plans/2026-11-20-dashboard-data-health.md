# Dashboard Data Health Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the "LezWatch.TV Tools" dashboard widget into a severity-grouped, trend-aware, stale-aware Data Health view, with an admin-only Background Jobs block.

**Architecture:** A pure builder `Health_Report` turns per-check inputs into a grouped structure (unit-tested). `Validation` exposes those inputs (severity + the Tools page's own counts). `Dashboard_Widgets` gathers job counts from Action Scheduler and renders.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, Action Scheduler, PHPUnit (pure, no WP bootstrap).

**Spec:** `docs/superpowers/specs/2026-11-20-dashboard-data-health-design.md`

## Global Constraints

- Text domain `lwtv-underscores`; every user-facing string i18n-wrapped.
- Builder lives in `admin-menu/build/`, namespace `LWTV\Admin_Menu\Build`, touches no WP functions except the bootstrap-shimmed `__`/`_n`.
- Editors = `upload_files` (unchanged). Admins = `manage_options`.
- Stale = `last` is 0, or `now - last > 8 * DAY_IN_SECONDS` (exactly 8 days is not stale).
- Jobs: groups `lwtv` and `lwtv_byq`; failed with scheduled date in the last 7 days; pending with scheduled date more than 1 hour in the past.
- Tiers in order: `fix_now` (Fix now), `attention` (Needs attention), `backlog` (Backlog). Assignments are in the spec table.
- Row label = the `TOOL_TABS` `name`. Link = `admin.php?page=lwtv_data_check&tab=tab_<link_tab>`, where `link_tab` = the entry's `tab` key if set, else its own key.
- **No commits.** The user reviews and commits the whole diff at the end. Leave the branch open.

## Review Focus

1. **Findings expired** (`cached` false, 10-day TTL): use the `stored` status count and mark the row `as_of`, the same as the Tools intro table. It must not silently drop to 0.
2. **A check added to `TOOL_TABS` without `severity`**: lands in `attention`, never disappears.
3. **A check that has never run** (`last` 0, count 0): no row, but listed as stale for admins.
4. **Action Scheduler missing:** jobs `available` false renders "unavailable", never "0 failed".
5. **An editor viewing:** no stale list and no jobs, even when both have data.

Each one is pinned by a test in Task 1.

---

### Task 1: `Health_Report` pure builder

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-health-report.php`
- Modify: `tests/bootstrap.php` (add a `require_once` beside the other `admin-menu/build/` lines, ~line 159)
- Test: `tests/unit/Admin_Menu/HealthReportTest.php`

**Interfaces:**
- Produces:
  - `Health_Report::TIERS = array( 'fix_now', 'attention', 'backlog' )`
  - `Health_Report::DEFAULT_TIER = 'attention'`
  - `Health_Report::STALE_AFTER = 8 * DAY_IN_SECONDS`
  - `Health_Report::build( array $checks, array $jobs, int $now, bool $is_admin ): array`
    - `$checks`: `slug => array{name:string, severity?:string, link_tab:string, count:int, cached:bool, stored:int, new:int, resolved:int, last:int}`
    - `$jobs`: `array{available:bool, failed:int, overdue:int}`
    - returns `array{tiers: array<string, list<array{key:string, name:string, count:int, new:int, resolved:int, link_tab:string, as_of:bool}>>, stale: list<array{name:string, last:int}>, last_checked:int, jobs: array|null, all_clear:bool}`
  - `Health_Report::trend( int $new, int $resolved ): string`: "+5 new, 3 fixed", "+5 new", "3 fixed", or "" when both are 0 (use `_n` for the counts).

Rules: effective count = `cached ? count : stored`, `as_of = ! cached`. Drop rows with effective count 0. Unknown or missing severity → `DEFAULT_TIER`. `tiers` holds only non-empty tiers, in `TIERS` order. Within a tier, sort by count descending, then name ascending. `stale` and `jobs` are `array()`/`null` unless `$is_admin`. `last_checked` = max `last`. `all_clear` = `tiers` is empty.

- [ ] **Step 1: Write failing tests** in `HealthReportTest` (namespace `LWTV\Tests\Admin_Menu`), with a `check( array $over ): array` fixture helper (defaults: count 0, cached true, stored 0, new 0, resolved 0, last = NOW, severity `fix_now`). Use `const NOW = 1_800_000_000`.
  - `test_rows_group_into_tiers_in_fixed_order`: one backlog row, one fix_now row → `array_keys( $r['tiers'] ) === array( 'fix_now', 'backlog' )`
  - `test_zero_count_rows_are_dropped_and_empty_tiers_hidden`
  - `test_rows_sort_by_count_desc_then_name`
  - `test_missing_or_unknown_severity_falls_back_to_attention` (Review Focus 2)
  - `test_expired_findings_use_stored_count_and_flag_as_of`: cached false, stored 12 → count 12, `as_of` true (Review Focus 1)
  - `test_stale_at_exactly_eight_days_is_not_stale_one_second_more_is`
  - `test_never_run_check_is_stale_but_has_no_row` (Review Focus 3)
  - `test_editor_sees_no_stale_and_no_jobs` (Review Focus 5)
  - `test_admin_gets_jobs_passed_through_including_unavailable` (Review Focus 4: `available` false survives)
  - `test_last_checked_is_newest_last`
  - `test_all_clear_when_no_rows`
  - `test_trend_formats`: (5,3) → `'+5 new, 3 fixed'`; (5,0) → `'+5 new'`; (0,3) → `'3 fixed'`; (1,1) → `'+1 new, 1 fixed'`; (0,0) → `''`

- [ ] **Step 2: Run, expect failure:** `vendor/bin/phpunit --filter HealthReport` → errors on class `Health_Report` not found.
- [ ] **Step 3: Implement** `Health_Report` per the Interfaces block. Add the `require_once` to `tests/bootstrap.php`.
- [ ] **Step 4: Run, expect pass:** `vendor/bin/phpunit --filter HealthReport` → all green. Then `vendor/bin/phpunit` → whole suite green.

### Task 2: `Validation` exposes health inputs

**Files:**
- Modify: `plugins/lwtv-plugin/php/admin-menu/class-validation.php` (`TOOL_TABS` ~line 51; `tab_counts()` ~line 265)
- Modify: `docs/architecture/validation-screen.md` (tab registry keys: document `severity`)

**Interfaces:**
- Consumes: `Health_Report::TIERS` values for `severity`.
- Produces: `Validation::health_checks(): array`, shaped exactly like `Health_Report::build()`'s `$checks` (`slug => name, severity, link_tab, count, cached, stored, new, resolved, last`).

- [ ] **Step 1:** Add `'severity' => '<tier>'` to each `TOOL_TABS` entry, following the spec table (BYQ = `attention`; `watch_hosts` and `watch_term_check` = `fix_now`).
- [ ] **Step 2:** Add `resolved` to each `tab_counts()` entry: `(int) ( $status['summary']['resolved'] ?? 0 )`. Update the `@var`/`@return` shapes.
- [ ] **Step 3:** Add public static `health_checks()`, which maps `TOOL_TABS` + `tab_counts()` to the shape above. `link_tab` = `$value['tab'] ?? $tab`. Keep `tab_counts()` private.
- [ ] **Step 4: Verify:** `composer lint` and `composer analyse` are clean. Against lwtv.local (see the local wp-cli memory notes), `wp eval 'print_r( LWTV\Admin_Menu\Validation::health_checks() );'` prints 13 entries with severities, and the counts match the Data Validation intro table.

### Task 3: Widget rendering + job counts

**Files:**
- Modify: `plugins/lwtv-plugin/php/_components/class-dashboard-widgets.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-admin-menu.php:140` (add `'index.php'` to `$my_hooks`; bump style version to `1.9.0`)
- Modify: `plugins/lwtv-plugin/assets/css/lwtv-tools.css` (append `.lwtv-health*` rules)
- Modify: `docs/architecture/validation-screen.md` (short "Dashboard widget" section: tiers, stale rule, admin-only jobs, link to spec)

**Interfaces:**
- Consumes: `Validation::health_checks()`, `Health_Report::build()`, `Health_Report::trend()`, `Health_Report::TIERS`.
- Produces: private `job_counts(): array{available:bool, failed:int, overdue:int}` in `Dashboard_Widgets`.

- [ ] **Step 1:** Implement `job_counts()`. If `! class_exists( 'ActionScheduler' )` return `available => false`. Otherwise, for each group, `\ActionScheduler::store()->query_actions( $args, 'count' )` with `status` `ActionScheduler_Store::STATUS_FAILED`, `date` = 7 days ago, `date_compare` `>=`; and status `STATUS_PENDING`, `date` = 1 hour ago, `date_compare` `<=`. Sum across groups.
- [ ] **Step 2:** Rewrite `check_dashboard_content()`:
  - `$is_admin = current_user_can( 'manage_options' )`. Only call `job_counts()` when admin.
  - Admin stale warnings first: one line per stale check, "%1$s hasn't run since %2$s" (or "%s has never run"), styled red.
  - Each non-empty tier gets an `<h3>` with its label ("Fix now" / "Needs attention" / "Backlog"), then a `<ul>`. Each `<li>` holds a link to the tab with the name, the count pill (`lwtv-tools-pill`, or `--stale` when `as_of`), and the trend text when non-empty.
  - `all_clear` → keep "No issues found! Celebrate gay times!" (now i18n-wrapped).
  - Editors' footer: "Last checked %s" from `last_checked` (`get_date_from_gmt` with `F j, Y`).
  - Admin "Background jobs" block: "%1$d failed this week · %2$d overdue", linking to `tools.php?page=action-scheduler&status=failed`, or "Action Scheduler unavailable".
  - Keep the "Go to the Tools Dashboard" button.
- [ ] **Step 3:** CSS: tier headings, row layout (name left, pill + trend right), stale warning in `#d63638`. Core admin colours only, `rem` font sizes.
- [ ] **Step 4: Verify** on lwtv.local as an admin and as an editor-level user. Admin sees the tiers, the stale lines (if any), and the jobs block. Editor sees tiers + "Last checked" only. Each row's link opens the matching tab (Contested hosts → Watch Providers). Counts match the Data Validation intro table. Run `npm run lint:css`, `composer lint`, `composer analyse`, and `vendor/bin/phpunit`; all clean.
- [ ] **Step 5:** Stop. Report the diff for the user to review and commit. Do not commit.
