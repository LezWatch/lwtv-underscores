# API Usage Admin Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A read-only "API Usage" admin submenu that summarizes `usage.csv` by registered `lwtv/v1` route and by consumer.

**Architecture:** Three pure classes in `admin-menu/build/` (CSV parse, route match, report aggregate) plus a pure known-consumers helper, all unit-tested. One WordPress glue class in `admin-menu/` reads the file, caches parsed rows in a transient keyed on mtime, renders tabs, and saves the known-consumers option via `admin-post`.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, PHPUnit (pure, no WP bootstrap), phpcs WordPress-Extra, PHPStan.

**Spec:** `docs/superpowers/specs/2026-11-13-api-usage-admin-design.md`

## Global Constraints

- Namespace `LWTV\Admin_Menu\Build` for pure classes, `LWTV\Admin_Menu` for glue; files `class-*.php`, `ABSPATH` guard.
- Capability `activate_plugins` for the page and the save handler.
- Never write to the CSV. Never store or display IPs or raw UAs (only the CSV `consumer` label).
- Every CSV-derived field is untrusted: escape on output (`esc_html`, `esc_attr`, `esc_url`).
- CSV path default `/home/wp_bg3hrq/lwtv-api-usage/usage.csv`, filterable via `lwtv_api_usage_csv_path`.
- Stale warning when file mtime **or** newest date is older than 36 hours.
- Zero-hit wording: "No hits in this range". Never "unused" or "safe to remove".
- Text domain `lwtv-underscores` for user-facing strings.
- No commits without the user's approval in that turn (repo rule). Commit steps below are "stop and ask".

## Review Focus

1. Percent-encoded or odd-case paths (`%2C`, `/Stats/`) — decoded, matched case-insensitively like WP; non-matching ones land in `(unmatched)`, never dropped. → Task 2 test.
2. Header-only, empty, or truncated CSV lines — parse to `[]` / skip the bad line, no warnings. → Task 1 test.
3. A date range with no rows — empty tables, every route in the zero-hit list, error rate 0 (no division by zero). → Task 3 test.
4. A consumer label containing markup (`<script>`) — rendered escaped. → Task 5 live check with fixture.
5. Missing/unreadable CSV — admin notice, no fatal, no stale cache. → Task 5 live check.

---

### Task 1: CSV parser

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-api-usage-csv.php`
- Modify: `tests/bootstrap.php` (require the new file next to `class-exclusion-registry.php`)
- Test: `tests/unit/Admin_Menu/ApiUsageCsvTest.php`

**Interfaces:**
- Produces: `Api_Usage_Csv::parse( iterable $lines ): array` → `list<array{date:string,endpoint:string,consumer:string,status:string,hits:int,bytes:int}>`; `Api_Usage_Csv::newest_date( array $rows ): ?string`.

- [ ] **Step 1: Write failing tests**
  - `test_header_is_skipped`: parse(`['date,endpoint,consumer,status,hits,bytes']`) === `[]`.
  - `test_row_is_typed`: one row `2026-11-06,/wp-json/lwtv/v1/last-death,uptime-kuma,200,96,12345` → hits `96` (int), bytes `12345` (int), status `'200'` (string).
  - `test_duplicate_keys_are_summed`: same date/endpoint/consumer/status twice with hits 2 and 3 → one row, hits 5, bytes summed.
  - `test_malformed_lines_are_skipped`: `''`, `'2026-11-06,/x'`, `'bad-date,/wp-json/lwtv/v1/x,c,200,1,1'`, `'2026-11-06,/wp-json/lwtv/v1/x,c,2xx,1,1'`, `'2026-11-06,/wp-json/lwtv/v1/x,c,200,-1,1'` → `[]`.
  - `test_trailing_newline_and_crlf_are_tolerated`: `"…,1,10\r\n"` parses.
  - `test_newest_date_ignores_order`: rows dated 11-08, 11-12, 11-06 → `'2026-11-12'`; `[]` → `null`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter ApiUsageCsv` → FAIL (class not found).
- [ ] **Step 3: Implement** with `str_getcsv`; require exactly 6 fields; validate date `^\d{4}-\d{2}-\d{2}$`, status `^\d{3}$`, hits/bytes `ctype_digit`, endpoint starting `/`, consumer non-empty. Sum by key `date|endpoint|consumer|status`; return `array_values`.
- [ ] **Step 4:** `vendor/bin/phpunit --filter ApiUsageCsv` → PASS.
- [ ] **Step 5:** Stop — ask user before any commit.

### Task 2: Route matcher

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-api-route-matcher.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Admin_Menu/ApiRouteMatcherTest.php`

**Interfaces:**
- Produces: `new Api_Route_Matcher( array $routes, string $prefix = 'wp-json' )`; `->match( string $endpoint ): string` returns the route key or `Api_Route_Matcher::UNMATCHED` (`'(unmatched)'`); `->routes(): array` (keys as given).

Fixture routes for tests: copy these real keys verbatim from the local site:
`/lwtv/v1`, `/lwtv/v1/last-death`, `/lwtv/v1/stats/(?P<type>[a-zA-Z.\-]+)`, `/lwtv/v1/stats/(?P<type>[a-zA-Z.\-]+)/(?P<format>[a-zA-Z\-]+)/(?P<page>[a-zA-Z0-9\-]+)`, `/lwtv/v1/this-year/(?P<type>[a-zA-Z.\-]+)`, `/lwtv/v1/this-year/(?P<year>[\d]+)`, `/lwtv/v1/what-happened/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})`, `/lwtv/v1/export/(?P<type>[a-zA-Z0-9-]+)/(?P<item>[a-zA-Z0-9-]+)`, `/lwtv/v1/whats-on/(?P<when>[a-zA-Z0-9-]+)` plus a test-only literal `/lwtv/v1/stats/shows`.

- [ ] **Step 1: Write failing tests**
  - `test_two_ids_group_under_one_route`: `/wp-json/lwtv/v1/stats/shows/name/all-my-children` and `/wp-json/lwtv/v1/stats/shows/name/the-l-word` → same 3-param stats key.
  - `test_prefix_and_trailing_slash_are_stripped`: `/wp-json/lwtv/v1/last-death/` → `/lwtv/v1/last-death`; `/wp-json/lwtv/v1` → `/lwtv/v1`.
  - `test_regex_params_are_respected`: `/wp-json/lwtv/v1/this-year/2024` → year route; `/wp-json/lwtv/v1/this-year/shows` → type route; `/wp-json/lwtv/v1/what-happened/2024-13` → UNMATCHED.
  - `test_most_literal_route_wins`: `/wp-json/lwtv/v1/stats/shows` → `/lwtv/v1/stats/shows`, not the `(?P<type>…)` route.
  - `test_match_is_case_insensitive_like_wp`: `/wp-json/lwtv/v1/Last-Death` → `/lwtv/v1/last-death`.
  - `test_encoded_path_decodes_then_fails_cleanly`: `/wp-json/lwtv/v1/whats-on/a%2Cb` → UNMATCHED.
  - `test_foreign_path_is_unmatched`: `/wp-json/wp/v2/posts` → UNMATCHED.
  - `test_prefix_is_optional`: `/lwtv/v1/last-death` → `/lwtv/v1/last-death`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter ApiRouteMatcher` → FAIL.
- [ ] **Step 3: Implement.** Normalize: `rawurldecode`, strip leading `'/' . $prefix` if present, `rtrim( '/' )` (keep at least `/`). Match with `preg_match( '@^' . $route . '$@i', $path )`, the same delimiter/flags WP's `WP_REST_Server::match_request_to_handler` uses. Specificity = `strlen( preg_replace( '/\(\?P<\w+>[^)]*\)/', '', $route ) )`; pre-sort routes by specificity desc, stable on registration order. Memoize results per endpoint in an instance array.
- [ ] **Step 4:** `vendor/bin/phpunit --filter ApiRouteMatcher` → PASS.
- [ ] **Step 5:** Stop — ask user before any commit.

### Task 3: Report aggregator

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-api-usage-report.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Admin_Menu/ApiUsageReportTest.php`

**Interfaces:**
- Consumes: Task 1 row shape; `Api_Route_Matcher` (Task 2).
- Produces: `Api_Usage_Report::build( array $rows, Api_Route_Matcher $matcher, string $from, string $to, array $exclude = [], string $new_since = '' ): array` → `array{routes: list<RouteRow>, consumers: list<ConsumerRow>, no_hits: list<string>, totals: array{hits:int, bytes:int}}`.
  - RouteRow: `route, hits, s2xx, s3xx, s4xx, s499, s5xx, error_rate (float 0–1), bytes, consumers (int), last_seen, top_paths (list<array{path,hits}>, max 5, hits desc)`.
  - ConsumerRow: `consumer, hits, s5xx, error_rate, routes (list<array{route,hits}>, hits desc), first_seen, last_seen, is_new (bool)`.
  - `Api_Usage_Report::sort( array $rows, string $orderby, string $order = 'desc' ): array`; allowed `orderby`: `s5xx, error_rate, hits, bytes, consumers, last_seen, route, consumer`; unknown → `s5xx`. Ties fall back to `error_rate` desc then `hits` desc.
  - `build()` returns routes pre-sorted by `sort( …, 's5xx' )` and consumers by `hits`.
- Rules: `$from`/`$to` inclusive `Y-m-d` string compare. 499 counted only in `s499`; `s4xx` excludes 499. `error_rate = ( s4xx + s5xx ) / hits`, 0 when hits 0. `first_seen` and `is_new` come from **all** rows (ignoring range and `$exclude`); `is_new = '' !== $new_since && first_seen >= $new_since`. `no_hits` = `$matcher->routes()` with no in-range, non-excluded hits, in registration order. `(unmatched)` appears in `routes` when it has hits, never in `no_hits`.

- [ ] **Step 1: Write failing tests**
  - `test_status_classes_and_error_rate`: one route with 200×6, 404×2, 499×1, 500×1 → s2xx 6, s4xx 2, s499 1, s5xx 1, error_rate `0.3`.
  - `test_range_filters_rows`: rows 11-05 and 11-07, range 11-06..11-12 → only 11-07 counted; `last_seen` 11-07.
  - `test_excluded_consumers_are_dropped`: exclude `['uptime-kuma']` → its hits absent from routes/consumers/totals.
  - `test_distinct_consumers_and_top_paths`: two slugs, three consumers → `consumers` 3, `top_paths[0]` is the bigger slug.
  - `test_no_hits_lists_registered_routes_without_traffic`: route with zero hits appears in `no_hits`; `(unmatched)` never does.
  - `test_unmatched_paths_are_kept`: `/wp-json/lwtv/v1/nope` shows as a `(unmatched)` route row with its hits.
  - `test_empty_range_is_safe`: rows outside range → routes `[]`, consumers `[]`, every route in `no_hits`, totals `0`.
  - `test_new_consumer_uses_all_data_first_seen`: consumer first seen 11-01 but range 11-10..11-12, `$new_since` 11-05 → `is_new` false, `first_seen` 11-01.
  - `test_default_sort_puts_5xx_first` and `test_sort_rejects_unknown_key`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter ApiUsageReport` → FAIL.
- [ ] **Step 3: Implement** the signatures above.
- [ ] **Step 4:** `vendor/bin/phpunit --filter ApiUsageReport` → PASS.
- [ ] **Step 5:** Stop — ask user before any commit.

### Task 4: Known consumers helper

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/build/class-api-known-consumers.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Admin_Menu/ApiKnownConsumersTest.php`

**Interfaces:**
- Produces: `Api_Known_Consumers::STATUSES` = `[ 'none', 'to-contact', 'contacted', 'replied', 'no-contact' ]`; `::seed(): array` (keyed by label); `::normalize( array $rows ): array` (form rows → keyed by label, each `array{label, name, contact, notes, status, internal:bool}`); `::internal_labels( array $known ): list<string>`.
- Seed: `uptime-kuma` (name "Uptime Kuma (our monitor)", internal true), `WP:https://www.thetracyl.com` (name "TheTracyL (co-developer)", internal true), `EuropeanFictionalCharacterResearch`, `SapphoWorkbenchCrawler`, `queer-tv-guide-pipeline` (internal false, contact `''`, status `to-contact`). No invented contacts.
- Glue (Task 5) runs `sanitize_text_field` / `esc_url_raw` / `sanitize_textarea_field` before calling `normalize()`; `normalize()` only shapes: trims, drops rows with empty label, last duplicate label wins, unknown status → `'none'`, `internal` true only for `'1'`/`true`, caps each string at 500 chars.

- [ ] **Step 1: Write failing tests**: `test_seed_marks_our_traffic_internal`, `test_empty_labels_are_dropped`, `test_unknown_status_falls_back`, `test_internal_labels_lists_only_flagged`, `test_duplicate_label_last_wins`.
- [ ] **Step 2:** `vendor/bin/phpunit --filter ApiKnownConsumers` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4:** `vendor/bin/phpunit --filter ApiKnownConsumers` → PASS.
- [ ] **Step 5:** Stop — ask user before any commit.

### Task 5: Admin page (WordPress glue)

**Files:**
- Create: `plugins/lwtv-plugin/php/admin-menu/class-api-usage.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-admin-menu.php` (call `( new Api_Usage() )->init()` inside the `activate_plugins` block in `add_settings_page()`; register `admin_post_lwtv_api_usage_consumers` in `init()` via a static handler; add `lezwatch-tv_page_lwtv_api_usage` to `$my_hooks`)

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: page slug `lwtv_api_usage`; option `lwtv_api_known_consumers` (autoload `false`); transient `lwtv_api_usage_rows` storing `array{mtime:int, path:string, rows:array}`; filter `lwtv_api_usage_csv_path`.

Behavior:
- Read: if `! is_readable( $path )` → error notice naming the path, stop. Transient hit only when stored `mtime` and `path` equal current; else `file( $path, FILE_IGNORE_NEW_LINES )` → `Api_Usage_Csv::parse()` → `set_transient( …, DAY_IN_SECONDS )`.
- Routes: `array_keys( rest_get_server()->get_routes( 'lwtv/v1' ) )`, prefix `rest_get_url_prefix()`.
- Last-run panel: file mtime (`wp_date`) and newest date; warning notice if `time() - mtime > 36 * HOUR_IN_SECONDS` or newest date older than 36h before now (compare `strtotime( newest . ' 23:59:59 -0800' )`).
- Query args (sanitized, `GET`, no nonce needed — read-only): `tab` (`routes|consumers|no-hits|known`), `range` (`30` default | `all`), `internal` (`1` shows internal), `orderby`, `order`. Range `30` = newest date − 29 days through newest date. `new_since` = newest − 13 days.
- Routes tab: table columns Route, Hits, 2xx, 4xx, 499, 5xx, Error rate, Bytes (`size_format`), Consumers, Last seen; sortable header links; `<details>` per row with top paths. Rows with s5xx > 0 get a red-left-border class.
- Consumers tab: Consumer (+ known display name, "New" badge if `is_new`, "Internal" badge), Hits, Error rate, Routes (top 3 + count), First seen, Last seen.
- No-hits tab: heading "No hits in this range" + one-line caveat that a short window cannot tell dead routes from occasional ones.
- Known tab: one editable row per known consumer plus 3 blank rows; `admin-post.php` form, `wp_nonce_field( 'lwtv_api_usage_consumers' )`; handler checks `current_user_can( 'activate_plugins' )` + `check_admin_referer`, sanitizes, `Api_Known_Consumers::normalize()`, `update_option( …, false )`, redirects back with `updated=1`. Option missing → `seed()`.
- Unknown labels seen in data are listed under the Known tab as "Seen but not described" for quick copy.

- [ ] **Step 1:** Implement the class and the Admin_Menu wiring.
- [ ] **Step 2: Local fixture.** Write a scratch CSV with ~a week of rows covering: 2xx/4xx/499/5xx, two stats slugs, an unmatched path, a `%2C` path, an internal consumer, and a consumer label `<script>alert(1)</script>`; add a temporary mu-plugin on lwtv.local filtering `lwtv_api_usage_csv_path` to it.
- [ ] **Step 3: Live check** on `https://lwtv.local/wp-admin/admin.php?page=lwtv_api_usage`: last-run panel + stale warning (fixture is old), routes grouped, `(unmatched)` row present, 5xx first, sort links work, script label shown as text, internal toggle hides/shows, no-hits wording, known-consumer save round-trips, missing file path → notice not fatal. Check as an editor account: no menu, direct URL denied.
- [ ] **Step 4:** Remove the temporary mu-plugin.
- [ ] **Step 5:** Stop — ask user before any commit.

### Task 6: Lint, static analysis, full suite

- [ ] `composer lint` → no errors in new/changed files.
- [ ] `composer analyse` → no new errors.
- [ ] `vendor/bin/phpunit` → all green.
- [ ] Report results; stop — ask user before any commit.
