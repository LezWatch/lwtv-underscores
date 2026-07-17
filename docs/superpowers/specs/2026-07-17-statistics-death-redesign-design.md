# Statistics on Death Redesign — Design Spec

**Date:** 2026-07-17
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Death** section — `/statistics/death/`, a single-page, multi-view page (no drill-down) with seven views over the death dataset. The last section still emitting Chart.js.

## Summary

Rebuild `/statistics/death/` on the shared stats shell, keeping `death.php`'s existing `$valid_views` routing. Replace the old `death/navbar.php` with the shared `.lwtv-stats-subnav`. Seven views, all server-rendered (no Chart.js):

1. **Overview** — "THE TOLL": 3 red counters (Characters who die % · Shows that kill % · Deaths/year avg) + a compact year bar chart annotated with the **auto-detected** deadliest year.
2. **Characters** — dead-by-sexuality / gender / role donuts (reuse `donut.php`); dynamic "die most" headline.
3. **Shows** — donut of the dead-by-show buckets (all/some/no deaths) + a catalogue-size caveat.
4. **Stations** — ranked bars of the dead by network.
5. **Nations** — ranked bars of the dead by country.
6. **Years** — a **new** vertical bar-per-year chart (earliest death → present, peak bar full-red) + per-year average.
7. **List** — "THE RECORD": 3 derived gap cards (Longest / Shortest / Most-in-one-day) + a sortable `<table>` of every recorded death.

Death leans on the **red** (`dead-characters`) family as the primary accent.

## Reuse mandate

Reuse everything prior rounds built. NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Reuse `partials/{donut,ranked-bars,phrases}.php`, `.lwtv-metric-card`, `.lwtv-stats-subnav`, `$ramp-*`, the `.card-header.*` families, and the existing jQuery **tablesorter** (already enqueued on stats pages) for the List sort. Keep the theme font stack.

## Decisions (from brainstorming)

- **List "Show(s)" column: dropped.** The death record has no per-character show data; columns are **Name · Date · Days-since-prev** (no extra queries).
- **List sort: real `<table class="tablesorter">`** using the theme's already-enqueued jQuery tablesorter (client-side, click-to-sort), styled to the redesign; default **Date, newest first**.
- **Year chart:** one new `partials/year-bars.php`, shared by Overview (compact) and Years (full).

## Non-goals

- No routing/`$valid_views`/scoring changes. No drill-down.
- Chart.js enqueue is NOT removed in this round (This Year still uses it). After Death, This Year is the only remaining Chart.js consumer → narrowing the enqueue to This-Year-only is a **follow-up**, out of scope here.
- The List does not show shows; the "Show(s)" column is intentionally omitted.

---

## Architecture

Render path unchanged: `death.php` (routing + per-view dataset pre-loads) → `death/navbar.php` (→ becomes the shared sub-nav) → one `death/<view>.php`. Primary tab bar is in the page shell (marks Death active).

### Data layer (verified live)

- **One-line addition:** `Build\Dead::generate_all( $format )` (`build/class-dead.php:261`) currently routes only `list`/`time` to `generate_list()`; add `case 'array':` so `generate_dead_statistics('characters','all','array')` returns the date-keyed record array (it already exists via `output_list()`'s `array` case, just isn't reachable from the facade). Purely additive.
- **Overview counters:** `generate_total_dead('characters')` / `('shows')` + `generate_total_counts('characters')` / `('shows')` → ints; percents `round(dead/all*100,1)` (already computed in `death.php`). Deaths/year avg: `generate_dead_statistics('characters','years','average')` → numeric string (e.g. "9.97").
- **Year series:** `generate_dead_statistics('characters','years','array')` → sparse `[ ['death_year'=>int,'death_count'=>int], … ]` (years with ≥1 death, ascending; real data starts ~1973). **Peak = `max()` over `death_count`** — never hardcoded. For the bar chart, zero-fill dense from the earliest death year → current year.
- **Characters:** `generate_dead_statistics('characters', 'sexuality'|'gender'|'role', 'array')` → `slug => ['name','count']` (sorted desc). ("homosexual" is the lesbian slug; top segment drives the dynamic headline.)
- **Shows:** `generate_dead_statistics('shows','per-show','array')` → `['all_dead'|'some_dead'|'no_dead' => ['name','count']]`.
- **Stations / Nations:** `generate_dead_statistics('shows','stations'|'nations','array')` → `[ ['term_slug','term_name','count'], … ]` (sorted desc; nations maps to `lez_country`).
- **List records:** `generate_dead_statistics('characters','all','array')` (after the fix) → date-keyed `[ 'YYYY-MM-DD' => ['date','chars'=>[id=>['name','url']],'since'(str days),'most'(int)], … ]`, newest-first. **List gap cards:** `generate_dead_statistics('characters','all','time')` → `['most'=>['count','date'],'time'=>maxGapDays,'start','end']`.

### Shell / sub-nav / enqueue

- `death/navbar.php` → shared `.lwtv-stats-subnav` (Overview + the six `$valid_views`), active from `$view`. Wrap output in `.lwtv-stats-overview`.
- **Enqueue:** add `'death'` to the count-up JS gate in `class-stats-enqueues.php` (so counters + donut-legend bars animate). The tablesorter init for `death` already targets `#DeadCharactersTable`; scope it to the List view and add a `sortList` default of date-desc.

### Overview (`death/overview.php`)

- Eyebrow "THE TOLL" + **3 metric cards** (`.lwtv-metric-card`, `dead-characters` red family, icon tile `dead`): **Characters who die** (`%` + "N of M queer characters", icon `skull`), **Shows that kill** (`%` + "N of M shows…", icon `tv`), **Deaths per year** (the avg, icon `calendar`, caption "On average, including quiet years").
- **Year bar chart** (compact, via `year-bars.php`) with the dynamic headline "Deaths peaked in {peak_year} — and have fallen since" + the peak year's count as the right-side figure.

### Characters (`death/characters.php`)

- Eyebrow "WHO DIES — BY SEXUAL ORIENTATION" + `donut.php`: raspberry ramp of the dead-by-sexuality; centre = total dead; **headline dynamic** from the top segment (e.g. "Lesbian characters die most") with its share via `lwtv_stats_fraction_phrase()`.
- Then dead-by-gender donut (cisgender grey + ramp) and dead-by-role donut/bars.

### Shows (`death/shows.php`)

- Donut of the three buckets (all-dead / some-dead / no-dead), red→grey; caveat line that per-show death counts track catalogue size.

### Stations / Nations (`death/stations.php`, `death/nations.php`)

- `ranked-bars.php` of the dead by station / nation (top rows), family red (`dead`), links to `/network/` `/country/` archives; a one-line caveat (raw counts track how many shows a network/country has).

### Years (`death/years.php`)

- Eyebrow "DEATHS BY YEAR" + full `year-bars.php`: one bar per year (earliest → current), red family; **peak bar** full `$lwtv-stats-red`, others reduced weight; year labels; dynamic title "Every year, {first}–{current}" + subtitle naming the peak; per-year average called out on the right.

### List (`death/list.php`)

- Eyebrow "THE RECORD" + **3 gap cards** (`.lwtv-metric-card`): **Longest gap** (`time` days), **Shortest gap** (0 — multiple same-day), **Most in one day** (`most.count`), each red.
- **Sortable table** `<table id="DeadCharactersTable" class="tablesorter">`, styled to the redesign: columns **Name** (link to character) · **Date** (`YYYY-MM-DD`) · **Days since prev** (right-aligned, numeric; first/oldest row shows `—`). Rows = the date-keyed records flattened to one row per dead character (a date's `since` gap applies to each character on that date). Newest-first; tablesorter handles click-to-sort with a date-desc default. Intro line "N characters, newest first. Click a column heading to sort." The table is **not** count-up animated.

---

## New SCSS

- **`year-bars`**: a flex bar chart — `.lwtv-yearbars` (flex, align-flex-end, fixed height), `.lwtv-yearbar` (flex:1, red at reduced opacity), `.lwtv-yearbar--peak` (full `$lwtv-stats-red`), year/value labels, and the average callout. Bars render at final height (static; the counters/average count up). Dark: peak + bars keep the red family (dark variant).
- **`list-table`**: `.lwtv-death-list` grid-styled `<table>` (`minmax(150px,1.5fr) … 112px 140px` feel via table layout), 1px row borders, tabular numerics, right-aligned gap, `overflow-x:auto` wrapper (`min-width:600px`); a sort-caret affordance on `<th>` (tablesorter's bootstrap theme provides carets).
- Reuse `.lwtv-metric-card` / `.card-header.dead-characters` / `.lwtv-metric-icon.dead` for the counters + gap cards, `donut`/`ranked-bars` classes for the breakdowns. Dark coverage for the two new blocks in `_colors-dark.scss` as needed.

## Icons

`skull` (deaths/who-dies), `tv` (shows), `calendar` / `calendar-alt` (per-year), `satellite-signal` (stations), `globe` (nations), `group` (characters), `tag` (role). All in the sprite (verified in prior rounds); FA fallback via `get_symbolicon`. No Lucide.

## Data / behavior / testing

- Peak year is `max()`-derived and reused in every peak mention (Overview headline, Years title/subtitle). Guard every divisor; harden single-key unwrap. Escape all output; `get_symbolicon` echoes carry the `phpcs:ignore`; i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts. Character links `esc_url`.
- Count-up + bar/legend grow on the shared JS (donut rings + year bars static; the List table static). Reduced-motion → finals. Dark mode flips via `color-mode(dark)`.
- Gate: `composer lint`; `npm run lint:css`; `npm run buildquick` (Node 24). Browser QA (light + dark) of all 7 views against the handoff screenshots; confirm the List sorts (Name/Date/Gap, click-to-flip, date-desc default) and every death view is `<canvas>`-free; other stats sections unchanged.

## Files

**Modified:** `death.php` (sub-nav include + any per-view dataset tweaks); `death/{overview,characters,shows,stations,nations,years,list}.php` (rewrites); `build/class-dead.php` (`generate_all` `array` case); `class-stats-enqueues.php` (`death` count-up gate + list tablesorter sortList); `scss/addons/_stats.scss`; `scss/partials/_colors-dark.scss`.
**New:** `plugins/lwtv-plugin/php/statistics/templates/partials/year-bars.php`; sub-nav replaces `death/navbar.php` (removed).
**Removed:** `death/navbar.php`.

## Open items resolved in the plan

- Confirm `generate_dead_statistics('characters','all','array')` returns the date-keyed records after the `generate_all` `array` case (verified via the direct `generate_list('array')` call — 581 dated groups, name/url/since/most).
- Year-bar range floor: earliest actual death year vs a 1998 floor (the handoff shows 1998; real data starts ~1973). Default: span the real earliest → current, dynamic title.
- Exact tablesorter column parsers/`sortList` for the List (ISO date text-sortable; gap numeric; `—` sorts last).
- Shows-view bucket donut colors + the dead-by-role presentation (donut vs bars).
- Characters headline copy when the top segment's term name reads clinically ("Homosexual") vs the editorial "Lesbian" — derive from the top segment; owner may tune the label.
