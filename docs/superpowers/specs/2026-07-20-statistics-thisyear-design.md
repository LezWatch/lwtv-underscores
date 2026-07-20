# This Year Redesign + Chart.js Removal — Design

**Date:** 2026-07-20
**Branch:** `feat/cliche-stats` (unmerged, continues the statistics redesign series)
**Handoff:** `~/Downloads/LWTV-This-Year.zip` (`design_handoff_statistics_thisyear`)

## Goal

Re-skin the `/this-year/` section to match the redesigned statistics sections (shared shell, server-rendered HTML, theme tokens — no Chart.js), and then **remove Chart.js from the codebase entirely**. This Year is the last section still styled with the old Bootstrap tables/cards; once it is re-skinned there is no reason to keep Chart.js loaded anywhere.

Two decisions confirmed with the owner:
1. **Full Chart.js removal** — redesign This Year, drop it from the Chart.js enqueue gate, AND remove every remaining Chart.js consumer (the actor `piechart` path, the `barchart`/`piechart`/`trendline` format classes + `Stats_Handler` cases) and the `chart.min.js` / `chartjs-plugin-trendline` / `palette` assets + enqueues.
2. **Keep the 1961 reach** — the year navigator floors at `LWTV_FIRST_YEAR` (1961), NOT the handoff's 2016. The dropdown simply lists more years. No browsing reach is lost.

## Key facts from reconnaissance

- **This Year does not consume Chart.js today.** Its 7 templates render plain server-side Bootstrap tables/cards. Chart.js is loaded on `/this-year/` only because the page is bundled into the `$is_stats_page` gate in `class-statistics-optimized.php::enqueue_scripts()`. The `chart-container` class on two wrappers is CSS-only.
- **The data layer is complete and reusable.** All builders/formatters the handoff references already exist, transient-cached per year. No new queries are needed; the three "highlights" derive from data already fetched.
- **This Year is a standalone page template** (`page-templates/thisyear.php`) with its own in-plugin nav (`this-year/templates/navigation.php` = Bootstrap `nav-tabs`; `navigation-year.php` = `lwtv-pagination`). It does NOT use the shared stats shell. The shared tab bar (`statistics/templates/main/tabbar.php`, class `.lwtv-stats-tabs`) already contains a "This Year" tab.
- **No count-up JS runs on This Year today.** The `statistics-overview.js` gate keys off the `statistics` query var, which This Year does not set.

## Architecture

Server-rendered PHP, Bootstrap 5, theme SCSS — same approach as the other redesigned sections. The redesign edits the plugin's `This_Year` templates + `Display` and adds `.lwtv-ty-*` SCSS. No changes to the builders/formatters (data layer) except the two consistency fixes noted below.

Files:
- **Modify** `plugins/lwtv-plugin/php/this-year/class-display.php` — pass derived overview data (deltas, highlights) into `overview.php`; keep the existing per-view formatter calls.
- **Rewrite** `plugins/lwtv-plugin/php/this-year/templates/`:
  - `navigation.php` → render the shared `.lwtv-stats-tabs` top tab bar (This Year active) + the This Year **sub-nav** (`.lwtv-stats-subnav`-style) for the 6 views.
  - `navigation-year.php` → the new **year navigator** (arrows + dropdown + Live chip + delta caption).
  - `overview.php` → editorial lead card + 5-metric ribbon + 3 highlight cards.
  - `characters-on-air.php` → By Name 2-col grid / By Show cards (client-side pill toggle).
  - `dead-characters.php` → By Date rows / By Show cards / warm empty state.
  - `shows-on-air.php`, `new-shows.php`, `canceled-shows.php` → the shared two-column group-card block (title/accent/footnote per view).
- **Add** SCSS to `scss/addons/_stats.scss` (+ dark overrides in `scss/partials/_colors-dark.scss`): `.lwtv-ty-*` families for lead card, ribbon, highlights, char grid/cards, death rows/cards, empty state, two-column group cards, year navigator. Reuse existing tokens/patterns wherever possible.
- **Modify** `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php` — enqueue `statistics-overview.js` on This Year (for ribbon count-up); remove This Year from the Chart.js gate (part of removal).
- **Chart.js removal** — see the kill-list section (finalized from the audit).

The By-X grouping (By Name/Show/Format/Country, By Date/Show) stays a **client-side pill toggle**: all groupings render in the DOM and a tiny vanilla toggle (or Bootstrap pills, as today) switches panes. This matches both the current implementation and the prototype. No new routing. VIEW switching stays on the existing per-view URLs (`/this-year/{year}/{view}/`).

## Shared shell & navigation

**Top tab bar.** Replace This Year's Bootstrap `nav-tabs` with the shared `.lwtv-stats-tabs` markup (This Year active). Prefer including the existing `statistics/templates/main/tabbar.php` so there is a single source of truth for the tab bar; if that partial can't be cleanly reused from the This Year page template, mirror its markup with the This Year tab marked active. (Task will determine reuse vs. mirror.)

**Year navigator** (replaces `navigation-year.php`'s `lwtv-pagination`):
- Prev / Next arrow **links** (not buttons) to `/this-year/{year-1}/{view}/` and `/this-year/{year+1}/{view}/`, preserving the current `$view` suffix (blank for overview). Prev disabled/hidden at the floor `LWTV_FIRST_YEAR` (1961); Next disabled/hidden at the ceiling `gmdate('Y')` (current year).
- A year **`<select>`** listing every year from the current year down to `LWTV_FIRST_YEAR`; `onchange` navigates to `/this-year/{value}/{view}/`. A `<noscript>` "Go" fallback, matching the nations/stations picker pattern.
- A green **"Live · current year"** chip shown only on the current year (uses the green family token, uppercase).
- A right-aligned caption: "Deltas compare against {prevYear}". Omitted at the floor year (no prior year to compare).

**Sub-nav** (the 6 views): restyle the current `navigation.php` tabs as the section subnav. Links go to the per-view URLs. Active state from `$view`.

## Overview (editorial)

Data in scope (built by `Display`): the 5 scalar counts, `$characters_on_air`, `$characters_on_air_by_show`, `$shows_by_name/_by_format/_by_country`. Prior-year counts + highlight inputs are computed for the overview (in `Display` or `overview.php`).

**Lead card** — eyebrow "{year} in review"; a big one-sentence standout (`leadStat`) and a supporting narrative sentence, both derived from the counts. Copy pattern (from prototype, adapted; zero-death branch):
- leadStat (deaths>0): "{coa} queer characters on air, {new} premieres, and {dead} we lost."
- leadStat (deaths=0): "{coa} characters on air, {new} new shows, and not a single death — so far."
- narrative: "{year} has {coa} queer characters on air across {soa} shows — {trendWord}. {new} series premiered and {canceled} wrapped, {deaths clause}." where `trendWord` = "the first year we tracked" (floor year) / "up N from {prevYear}" / "down N from {prevYear}" / "flat against {prevYear}" using the coa delta.

**5-metric ribbon** — compact cards in a `repeat(5,1fr)` grid (responsive down to 2/1 columns). Each: family-colored uppercase label, the count (count-up via `data-count-to`), and a delta line "{↑|↓|–} N vs {prevYear}". Delta = `count(year) − count(year−1)`, computed live from the builders; at the floor year the chip reads "first tracked". Counters + families:
| Counter | Family | Source |
|---|---|---|
| Characters On Air | green | `get_character_count_for_year` |
| Dead Characters | red | `get_dead_character_count_for_year` |
| Shows On Air | blue | `get_show_count_for_year` |
| New Shows | pink/raspberry | `get_started_show_count_for_year` |
| Canceled Shows | amber | `get_ended_show_count_for_year` |

**Highlights of the year** — 3 cards, each an icon chip + kicker + title + description. All derived (recompute; do not hand-author):
1. **Biggest premiere** (pink) — the NEW show (a `$shows_by_name` entry with `airdates.start === year`) with the most characters (joined against `$characters_on_air_by_show` character counts). Title = show name; desc = "{format} from {country} — {N} queer characters, the most of any new show this year." Fallback when no new shows: "No new shows yet" / "Nothing has premiered yet this year."
2. **Leading nation** (blue) — the country with the most NEW shows (`New_Shows_Formatter::format_by_country_for_year(year, $shows_by_country)`, the group with the most). Title = country; desc = "{N} of this year's new shows come from {country}, more than any other country." Fallback "—" when none.
3. **In memoriam** (red) — the most recent death (`Dead_Characters_Formatter::format_by_date_for_year(year, $characters_on_air)`, last date key after ascending sort) + its show. Title = character name; desc = "{dead} queer character(s) died this year[, N of them on {show}]." **Flips to a green "The good news / Nobody died — yet" card** when the year's death count is 0.

Icons: substitute Symbolicons for the prototype's Lucide (star→a premiere/star glyph, globe→`globe.svg`, heart→a heart/memoriam glyph; skull/tv/users for the ribbon). Exact sprite names chosen at implementation from the available set.

## Characters On Air

Header: "{count} characters on air in {year}" (count-up) + a By Name / By Show pill pair. Subtitle: "Every queer character with a role in a show airing this year."
- **By Name** — a `repeat(2,1fr)` grid of rows; each row: character link (left) + their show(s) stacked right-aligned. Multi-show characters list every show on stacked lines. Source: `$characters_on_air` (each `{name, url→/characters/{slug}/, shows:[{name,url}]}`).
- **By Show** — a 2-col grid of cards sorted by cast size; each card: show link + unique character count, a "country · format" meta line, and character chips each tagged with its per-show role (main/recurring/guest). Source: `$characters_on_air_by_show` (each `{name, url, characters:[{name,url,type}], nations:[{name}], formats:[{name}]}`). Header count = unique characters.

## Dead Characters

Header: "{count} characters died in {year}" (count-up, red) + By Date / By Show pills. Subtitle: "Queer characters we lost in {year}. [link] See the full death statistics →" → `/statistics/death/`.
- **By Date** — date-stamped rows with a red left rule; date on the left, who died + their show on the right. Source: `Dead_Characters_Formatter::format_by_date_for_year` (keyed by death date; each character `{name,url,shows:[{name,url,type}]}`). Date display via `gmdate('M j', strtotime($key))` or similar short form.
- **By Show** — 2-col cards with a red top rule; show link + death count + meta + character list. Source: `::format_by_show_for_year` (keyed by show_id; `{show:{name,url,nations,formats}, characters:[{name,url}]}`).
- **Empty state** (death count 0) — a centered green check chip + "No characters died this year" + the warm copy "I know! We're surprised too. Fingers crossed it stays that way — check back through the year." Data-driven on `$dead_characters_count === 0`.

## Shows On Air / New Shows / Canceled Shows (shared block)

One shared block renders all three; title, description, accent, and footnote swap by view:
- **Shows On Air** — blue; "{count} shows on air in {year}"; "Every tracked series airing at least one episode this year." Source: `$shows_by_name/_by_format/_by_country`.
- **New Shows** — pink; "{count} show(s) premiered in {year}"; "Series with a queer woman or non-binary character that started airing this year." Source: `$new_shows_by_name/_by_format/_by_country` (New_Shows_Formatter).
- **Canceled Shows** — amber; "{count} show(s) ended in {year}"; "Series that aired their final episode this year — whether cancelled or concluded as planned." Source: `$canceled_shows_by_*` (Canceled_Shows_Formatter).

Three pills — **By Name** (A–Z, grouped by marker `#/-/A–Z`), **By Format**, **By Country** — regroup the same set (client-side pane toggle). Each group (a letter/format/country) is a **two-column group card** (net-new pattern): a card whose show list flows into two CSS columns (`columns:2; column-gap:28px`, items `break-inside:avoid`) so a big group (e.g. USA) stays short. Group header = the letter/format/country + count. Each show = a link + compact inline `(meta)` = the non-grouping dimensions:
- By Name: `(country · format)`
- By Format: `(country)`
- By Country: `(format)`
Per-show source shape: `{url, name, country, format, airdates:{start,finish}}`. Keep the existing "On air {airdates}" tooltip on the show link (Bootstrap tooltip) — or drop tooltips if Bootstrap JS is being trimmed (task decides; low priority).

## Color mapping (the deliberate 5-counter split)

Use existing tokens; no new values. The redesign uses `.lwtv-ty-*` label classes (not the old `.card-header.*`), so the split is applied via the new classes rather than editing `.card-header.new-shows`/`.canceled-shows`. Family → token:
- green `$lwtv-stats-green`, red `$lwtv-stats-red`, blue `$lwtv-stats-blue` (existing).
- New Shows = **pink/raspberry** `$lwtv-dkpink` (`#a51e63`) light → brighter pink in dark; light raspberry background.
- Canceled = **amber** `$lwtv-stats-yellow` background family.
Links `$lwtv-pink`; hover `$lwtv-purple`. Dark mode via the theme's `color-mode(dark)` variants. Accents for the shared block: On Air blue, New pink, Canceled amber (match the ribbon).

## Count-up

Enqueue `statistics-overview.js` on the This Year page so the ribbon counters + header counts animate (`data-count-to`). Simplest path: extend the enqueue in `class-statistics-optimized.php` to load `statistics-overview.js` when `is_page('this-year')`. Respect `prefers-reduced-motion` (the script already does). Lists render at final values (no count-up).

## Chart.js removal (kill-list)

**Audit verdict: Chart.js is already functionally dead.** Every statistics landing template now calls generators with `'array'` and renders server-side SVG partials. The ONLY code still requesting a Chart.js format (`'piechart'`) is `templates/post_type_actors.php`, which is **orphaned/unreachable** — it is included only by `Gutenberg_SSR::mini_stats()` → `generate_stats_block_actor()`, which has no caller (its `[ministats]` shortcode is documented but never registered, and no block wires it). The live actor-page donut uses `template-parts/overlays/statistics-actors.php` with `'array'` + `partials/donut.php` (no Chart.js). So removal breaks **zero live callers** and is independent of the redesign.

Target: zero `<canvas>` / `new Chart(` / `chartjs` / `palette` references remain, no broken callers.

**Delete files:**
1. `plugins/lwtv-plugin/php/statistics/format/class-piecharts-optimized.php` (`<canvas>` L43, `new Chart(` L54, `palette(` L77)
2. `plugins/lwtv-plugin/php/statistics/format/class-barcharts-optimized.php` (`<canvas>` L100, `new Chart(` L108)
3. `plugins/lwtv-plugin/php/statistics/format/class-trendline-optimized.php` (`<canvas>` L48, `new Chart(` L55)
4. `plugins/lwtv-plugin/php/statistics/templates/post_type_actors.php` (orphaned; only remaining `'piechart'` caller — L32, L36)
5. `plugins/lwtv-plugin/assets/js/chart.min.js`
6. `plugins/lwtv-plugin/assets/js/chart.min.js.map`
7. `plugins/lwtv-plugin/assets/js/chart.umd.js.map` (orphan map)
8. `plugins/lwtv-plugin/assets/js/chartjs-plugin-trendline.min.js`
9. `plugins/lwtv-plugin/assets/js/palette.min.js`
10. `plugins/lwtv-plugin/assets/js/palette.js` (orphan source)

**Edit files:**
11. `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php`:
    - Remove `VERSIONING` entries `'chartjs'` (L24), `'chartjs-plugin-trendline'` (L25), `'palette'` (L26). Keep `tablesorter` + `stats-overview`.
    - Remove the enqueue block L80-85 (the `if ($is_stats_page) { chartjs / trendline / palette }`). **Keep** the `$is_stats_page` variable (still used by the early-return guard) and the `Stats_Enqueues` gate (L88).
    - Change `generate_individual_actors` default format `'piechart'` → `'array'` (L266) and update the `'barchart'/'trendline'/'piechart'` docblock mentions (L181, L195, L243) — cosmetic, but do it for cleanliness.
12. `plugins/lwtv-plugin/php/statistics/class-stats-handler.php`:
    - Remove the three `use` imports (L14-16: Barcharts/Piecharts/Trendline).
    - Remove switch cases `'barchart'` (L38-39), `'trendline'` (L40-41), `'piechart'` (L42-43). Keep `'percentage'`, `'list'`, and `default` (raw `'array'`).

**Orphaned once #4 is deleted (clean up in the same removal task):**
- `plugins/lwtv-plugin/php/statistics/build/class-gutenberg-ssr.php` — `mini_stats()` (L52-65) + the `generate_stats_block_actor` facade (`class-statistics-optimized.php` L57, L153-154) become fully dead. Remove them and the template tag registration.

**Out of scope (not Chart.js; leave):** the `format_piechart()`/`format_barchart()` *data* helpers inside `build/class-formats.php`, `class-queer-irl.php`, `class-we-love-it.php`, `class-worth-it.php`, `class-dead.php` build arrays only — they never emit Chart.js. Now-unreachable, but harmless; optional future cleanup.

**Verify after removal:** grep the theme+plugin (excluding node_modules/vendor/build) for `Chart(`, `<canvas`, `chartjs`, `palette` → zero hits; `composer lint` clean; the redesigned `/this-year/` and all `/statistics/` pages render with no console 404s for chart.min.js/palette.min.js.

Because Chart.js is already dead, the removal could run before OR after the redesign. Plan order: redesign first, removal as the final phase — so the redesigned This Year can be verified to render correctly with Chart.js gone.

*(Exact file:line kill-list inserted after the audit completes — see below.)*

## Data-shape reference (from recon, do not re-derive)

- `get_{character,dead_character,show,started_show,ended_show}_count_for_year(int $year): int`.
- `$characters_on_air` = `get_characters_with_shows_for_year(int)`: numeric list; `{slug, name, dead(bool), death_years[], shows:[{name, url, type}]}`.
- `$characters_on_air_by_show` = `get_shows_with_characters_for_year(int)`: numeric list; `{slug, name, started, ended, characters:[{character_id, type, dead, last_death, name, url}], nations:[{name,slug,url}], formats:[{name,slug,url}]}`.
- `$shows_by_name` = `get_shows_for_year_by_name(int)`: grouped by marker(`#`,`-`,`A`–`Z`) → keyed by show name → `{url, name, country, format, airdates:{start,finish}}`.
- `$shows_by_format` = `get_shows_for_year_by_format(int)`: grouped by format name.
- `$shows_by_country` = `get_shows_for_year_by_nation(int)`: grouped by country name.
- Dead by date = `Dead_Characters_Formatter::format_by_date_for_year(year, $characters_on_air)`: keyed by death-date string → list of with-shows character elements. `ksort` ascending (most recent = last).
- Dead by show = `::format_by_show_for_year(year, $characters_on_air_by_show)`: keyed by show_id → `{show:{name, url, nations, formats}, characters:[{name, url}]}`.
- New = `New_Shows_Formatter::format_by_{name,format,country}_for_year(year, $shows_by_*)`: same grouped shape filtered `airdates.start === year`.
- Canceled = `Canceled_Shows_Formatter::format_by_{name,format,country}_for_year(year, $shows_by_*)`: filtered `airdates.finish === year`.
- Marker: `Shared_Builder::get_character_marker(string): string`.
- Year floor `LWTV_FIRST_YEAR` (1961, defined `plugins/index.php`); page template redirects below it.

## Consistency fixes (small, in-scope)

- `Dead_Characters_Formatter::format_by_show_for_year` builds `/show/{slug}/` (singular) while `characters-on-air.php` uses `/shows/{slug}/` (plural). Normalize show links to the canonical form (`get_permalink`/term link) used elsewhere; pick one and use it consistently across the redesigned templates.
- `Characters_Builder::get_characters_for_year` hardcodes a `1900` floor vs. `Shows_Builder` using `LWTV_FIRST_YEAR` — note only; do not change behavior unless it affects the navigator (it does not; navigator floors at `LWTV_FIRST_YEAR`).

## Non-goals

- No changes to the builders/formatters' queries or caching.
- No new per-grouping URLs (By-X stays client-side).
- Not adopting the handoff's 2016 floor (owner chose 1961 reach).
- No redesign of `/statistics/` sections (done previously).

## Risks

- **Highlights derivation** must be guarded for empty years (no shows / no new shows / no deaths) — every highlight has a defined fallback.
- **Two show representations** exist (term-array `nations/formats` vs. flattened `country/format`); use the right one per view (recon documents which).
- **Removing the chart format classes** must not break a live caller — the audit's kill-list is authoritative; if the actor `piechart` path is still live and user-facing, it must be converted to the server-rendered donut (already used in actor modals) before its Chart path is deleted.
- **Count-up freeze** on the automation tab (known) — verify counts via `data-count-to`, not screenshots.
