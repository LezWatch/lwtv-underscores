# Dashboard Site Pulse Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Site Pulse" dashboard widget (unread form messages, CPTs added this week vs last, admin-only failing API routes) and rename the Slack approvals widget.

**Architecture:** A pure static builder `LWTV\Admin_Menu\Build\Site_Pulse` decides what shows; a new component `LWTV\_Components\Site_Pulse` gathers data from Gravity Forms, `WP_Query` and `Api_Usage::load()` and renders. `Api_Usage_Report` gains an `s404` counter.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, Gravity Forms `GFAPI`, PHPUnit (no WP bootstrap).

**Spec:** `docs/superpowers/specs/2026-11-20-dashboard-site-pulse-design.md`

## Global Constraints

- Base branch `release/7.5.1`. No commits without the user's explicit approval (user commits the whole diff at the end).
- Editors = `upload_files`; API section only for `manage_options`.
- Text domain `lwtv-underscores`; all strings i18n.
- Builder classes: no WordPress calls.
- 404 rule: `s404 >= 10`; 5xx: top 3 with `s5xx > 0`; API range = page default (30 days, internal excluded).
- `Api_Usage::CACHE_VERSION` → 3.
- Don't edit `_components/class-dashboard-widgets.php` (Data Health session owns it).

## Review Focus

- Gravity Forms inactive/not installed → Inbox section hidden, no fatal (`class_exists( 'GFAPI' )` guard; live-checked).
- A form with no feeds at all (Article Submissions) → counted, not excluded (test in Task 2).
- Today is the week-start day → this week starts today, not 7 days ago (test in Task 2).
- A route qualifying on both 5xx and 404 → listed once (test in Task 2).
- Missing/stale API CSV → one "unavailable" line, not an error box (live-checked).

---

### Task 1: 404 count in the API report

**Files:**
- Modify: `plugins/lwtv-plugin/php/admin-menu/build/class-api-usage-report.php` (`empty_counts()`, `add()`, `SORTABLE`)
- Modify: `plugins/lwtv-plugin/php/admin-menu/class-api-usage.php` (`CACHE_VERSION`, `load()` visibility, Routes tab column)
- Test: `tests/unit/Admin_Menu/ApiUsageReportTest.php`

**Interfaces:**
- Produces: route/consumer rows carry `s404:int`; `Api_Usage::load( string $range, array $exclude ): array` is `public`.

- [ ] **Step 1:** Add `test_404_counted_separately_inside_4xx`: rows `404`×3, `403`×2, `200`×5 on `last-death` → route `s404 === 3`, `s4xx === 5`, `error_rate === 0.5`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter ApiUsageReport` → FAIL (undefined key `s404`).
- [ ] **Step 3:** Add `'s404' => 0` to `empty_counts()`; in `add()` increment when status is `'404'`; add `'s404'` to `SORTABLE`.
- [ ] **Step 4:** Same filter → PASS.
- [ ] **Step 5:** In `class-api-usage.php`: `CACHE_VERSION = 3`; `load()` → `public static`; Routes tab gets a `'s404' => __( '404', 'lwtv-underscores' )` column after 4xx rendered with `self::num()`.

### Task 2: Pure Site_Pulse builder

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-site-pulse.php`
- Modify: `tests/bootstrap.php` (require it next to the other admin-menu/build files)
- Test: `tests/unit/Admin_Menu/SitePulseTest.php`

**Interfaces:**
- Produces:
  - `Site_Pulse::inbox( array $forms, array $feeds ): array{rows: list<array{id:int,title:string,unread:int}>, total:int}` — `$forms` = list of `{id, title, unread, is_active}`; `$feeds` = `[ form_id => list<addon_slug> ]` (active feeds only). Drops inactive forms; drops forms with any slug in `APPROVAL_SLUGS = [ 'lwtv-gf-approvals', 'gravityformsapprovals' ]`; drops forms whose feeds are non-empty and all in `SIGNUP_SLUGS = [ 'gravityformsbrevo' ]`. Sort unread desc, then title asc.
  - `Site_Pulse::week_bounds( string $today, int $start_of_week ): array{this_start:string, last_start:string}` — `$today` is local `Y-m-d`; returns `Y-m-d`.
  - `Site_Pulse::added( array $this_week, array $last_week ): list<array{type:string,this:int,last:int}>` — keys `shows`, `characters`, `actors`, always in that order, missing keys → 0.
  - `Site_Pulse::api( array $routes, int $top = 3, int $min_404 = 10 ): list<array{route:string,s5xx:int,s404:int,last_5xx:string,reason:string}>` — `reason` is `'5xx'` or `'404'`.

- [ ] **Step 1: Tests** (`SitePulseTest`):
  - `test_inbox_excludes_approval_and_signup_forms`: forms 1 (feeds `[gravityformsslack]`, unread 1), 3 (no feeds, unread 0), 4 (`[gravityformsapprovals, gravityformsslack]`, unread 1), 7 (`[gravityformsbrevo]`, unread 2), 9 inactive → ids `[1, 3]`, total 1.
  - `test_inbox_sorts_unread_then_title`: B(2), A(2), C(5) → C, A, B.
  - `test_week_bounds_monday_start`: `('2026-11-20', 1)` (Friday) → `2026-11-16`, `2026-11-09`.
  - `test_week_bounds_on_start_day`: `('2026-11-16', 1)` → `2026-11-16`, `2026-11-09`.
  - `test_week_bounds_sunday_start`: `('2026-11-20', 0)` → `2026-11-15`, `2026-11-08`.
  - `test_added_orders_and_fills_missing`: `(['actors'=>4,'shows'=>2], ['shows'=>5])` → shows 2/5, characters 0/0, actors 4/0.
  - `test_api_top_5xx_then_404_threshold`: routes a(s5xx 9), b(5), c(3), d(1), e(s404 10), f(s404 9) → `[a,b,c,e]`, reasons `5xx,5xx,5xx,404`.
  - `test_api_route_listed_once`: a(s5xx 2, s404 50) → one row, reason `5xx`.
  - `test_api_ignores_zero_5xx`: a(s5xx 0, s404 0) → `[]`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter SitePulse` → FAIL (class not found).
- [ ] **Step 3:** Implement. `week_bounds`: `diff = ( (int) date('w') - $start_of_week + 7 ) % 7` via `DateTimeImmutable`. `api`: 404 list sorted by `s404` desc.
- [ ] **Step 4:** Same filter → PASS; full `vendor/bin/phpunit` → PASS.

### Task 3: Widget component, template, rename

**Files:**
- Create: `plugins/lwtv-plugin/php/_components/class-site-pulse.php`
- Modify: `plugins/lwtv-plugin/php/class-plugin.php` (register after `Dashboard_Widgets::class`)
- Modify: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-gf-approvals.php:105` (title → `Pending Slack Applications`)

**Interfaces:**
- Consumes: Task 1 `Api_Usage::load()`, `Api_Known_Consumers::internal_labels( Api_Usage::known_consumers() )`, `Api_Usage::RANGE_DAYS`; Task 2 builder.

- [ ] **Step 1:** `Site_Pulse implements Component`; `init()` hooks `wp_dashboard_setup`; registers `lwtv_site_pulse_widget` / "Site Pulse" when `current_user_can( 'upload_files' )`.
- [ ] **Step 2:** Gather — Inbox only if `class_exists( 'GFAPI' )`: `GFAPI::get_forms()`, per form `GFAPI::count_entries( $id, [ 'status' => 'active', 'field_filters' => [ [ 'key' => 'is_read', 'value' => 0 ] ] ] )`, feeds from `GFAPI::get_feeds( null, $id )` (non-WP_Error, `is_active`). Added: `WP_Query` with `post_status => publish`, `posts_per_page => 1`, `fields => ids`, `date_query` `after`/`before` (inclusive `after`, exclusive `before`) on `current_time( 'Y-m-d' )` + `get_option( 'start_of_week' )`; use `found_posts`. API only for `manage_options`: `Api_Usage::load( (string) Api_Usage::RANGE_DAYS, … )`; `isset( $data['error'] )` → unavailable line.
- [ ] **Step 3:** Render: sections "Inbox", "Added this week", "API: most errors, last 30 days". Inbox rows link `admin.php?page=gf_entries&id=<id>&filter=unread`; added row `Shows 2 (last week 5)`; API row `route · N × 5xx / N × 404 · last 5xx <date|—>`, footer link `admin.php?page=lwtv_api_usage`; empty API → "No failing routes in the last 30 days."
- [ ] **Step 4:** Rename the approvals widget title.

### Task 4: Verify

- [ ] `vendor/bin/phpunit` → OK
- [ ] `composer lint` → no errors
- [ ] `TMPDIR=<scratchpad>/phpstan-tmp composer analyse` → no errors (regenerate baseline only if a new entry is clearly a false positive; note it)
- [ ] Live on lwtv.local (worktree via sibling theme symlink + cookie mu-plugin, per memory): dashboard as admin shows all 3 sections; Inbox shows Suggest an Edit 1, no Slack Registration / mailing lists; counts match a direct SQL count; API Usage Routes tab shows the 404 column; renamed widget title. Clean up symlink, mu-plugin.
