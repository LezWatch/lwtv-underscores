# Handoff: CSV Download for Statistics Views

**Repo:** `LezWatch/lwtv-underscores` (plugin code under `plugins/lwtv-plugin/`)
**Branch model:** cut feature branch from `production`, merge to `development` for staging, separate PR to `production`.
**Scope:** Add CSV download capability to seven statistics views. No design/UI work beyond a functional download link. No changes to data builders.

---

## 1. Goal

Visitors on the following pages can download the underlying chart data as a CSV:

| # | Page | CSV contents |
|---|------|--------------|
| 1 | `/statistics/characters/on-air/` | Characters on air per year |
| 2 | `/statistics/shows/on-air/` | Shows on air per year |
| 3 | `/statistics/nations/on-air/?nation={slug}` | Shows on air per year for that nation |
| 4 | `/statistics/stations/on-air/?station={slug}` | Shows on air per year for that station |
| 5 | `/statistics/death/` (years view) | Character deaths per year |
| 6 | `/statistics/death/stations/` | Deaths per station/network |
| 7 | `/statistics/death/nations/` | Deaths per country |

## 2. Architecture decision (recommended)

**Mechanism:** `?download=csv` appended to the existing page URL, intercepted on `template_redirect`.

Why not REST (`class-stats-json.php`):
- The stats pages already resolve `view`, `nation`, and `station` query vars via existing rewrite rules. A `template_redirect` handler inherits all of that; a REST route would duplicate the context-resolution logic.
- The existing JSON API is a separate public contract; bolting CSV onto it invites format-negotiation complexity for no benefit.
- Revisit REST only if external tools need stable machine-consumable CSV URLs.

**Data source:** Existing builders, called with `format = 'array'`. `Stats_Handler::handle()` already returns raw data for any unrecognized format (the `default` case), so no formatter changes are needed. All builders are transient-cached (`DAY_IN_SECONDS`), so downloads are cheap and consistent with what the chart shows.

## 3. New files

### 3.1 `php/statistics/class-csv-download.php` (controller)

Namespace `LWTV\Statistics`. Responsibilities:

1. Hook `template_redirect` (priority default). Bail immediately unless:
   - `isset( $_GET['download'] ) && 'csv' === sanitize_key( $_GET['download'] )`
   - `is_page( 'statistics' )`
2. Resolve the current stats context from query vars (`statistics` page type segment, `view`, `nation`, `station`) and match it against a **hard whitelist** of the seven supported views. Anything else: `wp_safe_redirect()` back to the page sans param (or simply return and let the page render normally — simpler, recommended).
3. Fetch data via the mapping in §4, run it through the CSV formatter, emit headers, echo, `exit`.

Headers:

```php
header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'X-Robots-Tag: noindex' );
nocache_headers();
```

Filename convention: `lwtv-{group}-{view}[-{context}]-{Y-m-d}.csv`
Examples: `lwtv-characters-on-air-2026-07-17.csv`, `lwtv-shows-on-air-argentina-2026-07-17.csv`, `lwtv-death-stations-2026-07-17.csv`.

### 3.2 `php/statistics/format/class-csv.php` (formatter)

Namespace `LWTV\Statistics\Format`, matching siblings (`Barcharts_Optimized`, etc.). Pure function: takes `(array $data, array $headers)` → CSV string (or streams via `fputcsv` on `php://output`; prefer `fputcsv` — it handles quoting/escaping correctly).

Requirements:
- **CSV injection protection:** prefix any cell beginning with `=`, `+`, `-`, or `@` with a single quote. Nation/station names are editor-controlled taxonomy terms; cheap insurance.
- UTF-8 BOM (`\xEF\xBB\xBF`) at start so Excel opens accented nation names correctly.
- Skip builder-internal keys (`url`, etc.) — only emit whitelisted columns.

### 3.3 Registration

Instantiate/hook the controller wherever the statistics component wires up (`class-statistics-optimized.php` component or the plugin loader — follow the existing pattern for `Stats_Enqueues`).

## 4. View → data mapping

| View | Data call | Data shape | CSV columns |
|------|-----------|------------|-------------|
| Characters on air | `( new Build\On_Air_Optimized() )->generate( 'characters' )` | `[ year => [ name, count, url ] ]`, ksorted | `Year, Characters On Air` |
| Shows on air | `( new Build\On_Air_Optimized() )->generate( 'shows' )` | same | `Year, Shows On Air` |
| Nation on air | `lwtv_plugin()->generate_nation_statistics( $nation, '_on-air', 'array' )` — **verify** the exact view string; the template passes `$view = '_on-air'` and format `trendline`. May need `get_nation_details( $slug, 'array', '_on-air' )` directly. | per-year array | `Year, Shows On Air` |
| Station on air | `lwtv_plugin()->generate_station_statistics( $station, '_on-air', 'array' )` — same caveat | per-year array | `Year, Shows On Air` |
| Death per year | `lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'array' )` — **verify** `generate_years( 'array' )` returns the raw array; if not, add an `array` case (trivial). | per-year | `Year, Deaths` |
| Death per station | `lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'array' )` | `[ [ name, count, ... ] ]` | `Station, Deaths` |
| Death per nation | `lwtv_plugin()->generate_dead_statistics( 'shows', 'nations', 'array' )` | same | `Nation, Deaths` |

### ⚠️ Verify first (do this before writing the controller)

1. **`lez_nations` vs `lez_country`:** `class-dead.php` `generate_shows()` builds the taxonomy name as `'lez_' . $view` → `lez_nations` for the nations view, but the nations taxonomy elsewhere (e.g., `class-nations.php`) is `lez_country`. Confirm `generate_shows_by_taxonomy()` maps this internally. If it does, fine; if it's dead/mismatched code that happens to work via another path, document what actually feeds `/statistics/death/nations/`.
2. **`format = 'array'` behavior in each builder.** `Stats_Handler` passes unknown formats through, but some builders (notably `class-dead.php`) branch on `$format` *before* handing to the handler. Trace each of the seven calls once and confirm you get a raw array, not empty/HTML. Where a builder lacks an `array` case, add it as a pass-through — do not restructure the builder.
3. **Nation/station single-view data path.** `get_nation_details()` / `get_station_details()` return `$data['formatted']` in at least one spot — confirm what `array` format returns for the `_on-air` view and whether the leading-underscore view convention (`_on-air`) applies at the API surface or only inside templates.

## 5. Download links in templates

Minimal, no design pass: add a link/button to each of the seven templates:

```php
<p><a class="btn btn-outline-secondary" href="<?php echo esc_url( add_query_arg( 'download', 'csv' ) ); ?>">Download CSV</a></p>
```

`add_query_arg()` with no URL preserves current query string (so `?nation=argentina&download=csv` works automatically). Templates to touch:

- `statistics/templates/characters/on-air.php`
- `statistics/templates/shows/on-air.php`
- `statistics/templates/nations/single.php` (only when `'_on-air' === $view` **and** a nation is set)
- `statistics/templates/stations/single.php` (same condition)
- `statistics/templates/death/years.php`
- `statistics/templates/death/stations.php`
- `statistics/templates/death/nations.php`

Note item 5 in the request says `/statistics/death/` — the per-year chart lives at the `years` view. Decide whether the link belongs on the death overview page too, or only on `/statistics/death/years/`. Recommendation: years view only; overview aggregates multiple datasets and "which CSV is this?" gets ambiguous.

## 6. Edge cases

- **Invalid/unsupported view + `?download=csv`:** return normally (render the page); never emit an empty CSV.
- **Nation/station param missing or invalid slug** on views 3–4: render normally, no CSV. Sanitize with `sanitize_title()` and confirm the term exists before calling builders.
- **Empty data** (valid view, builder returns `array()`): emit CSV with header row only. Legitimate for a nation with no on-air history.
- **Zero-count years:** builders pre-fill all years `LWTV_FIRST_YEAR..current` with `count => 0` — keep those rows in the CSV. Gaps are data.
- **Caching layers:** transients handle the data. If the site has full-page caching (varnish/CDN), confirm `?download=csv` URLs aren't cached with HTML content-type — the `nocache_headers()` call plus the query string should handle it, but verify on staging.

## 7. Testing checklist

- [ ] Each of the 7 URLs + `&download=csv` produces a CSV whose numbers match the rendered chart exactly.
- [ ] `?nation=argentina` and a multi-word slug (e.g. `united-kingdom`) both work; filename reflects slug.
- [ ] Nonexistent nation/station slug → normal page render, no fatal, no empty file.
- [ ] `?download=csv` on an unsupported view (e.g. `/statistics/characters/gender/`) → normal render.
- [ ] CSV opens correctly in Excel and Google Sheets (BOM, quoting, accented nation names).
- [ ] A taxonomy term starting with a `-` or `=` (create one on staging) is escaped in output.
- [ ] Logged-out visitor can download (this is public data — no nonce/capability gate intended).
- [ ] No output-before-headers warnings (controller must run before any template output — `template_redirect` guarantees this if nothing echoes earlier).
- [ ] PHPCS passes (existing ruleset; note the codebase's phpcs:ignore conventions).

## 8. Suggested implementation order

1. Verification pass (§4 ⚠️ items) — half a day of tracing, saves rework.
2. `Format\CSV` class + unit-testable formatting (injection escaping, BOM).
3. Controller with the 7-view whitelist map.
4. Template links.
5. Staging (`development`) validation against live-ish data volume.

## 9. Explicitly out of scope (for now)

- Design/placement polish of the download button.
- CSV for other stats views (gender, sexuality, cliches, etc.) — the whitelist map makes adding these later a one-line-per-view change.
- REST/JSON API changes.
- XLSX or other formats.

## 10. Interaction with in-flight work

- **ACF migration:** this feature reads only through existing builders (which were already updated for ACF meta patterns, e.g. `lezchars_show_group_%_appears` LIKE queries in `On_Air_Optimized`). No new direct meta queries → no new migration surface. Safe to build in parallel.
- **`feat/searchwp` (PR #597):** no overlap.
