# Statistics on Stations Redesign — Design Spec

**Date:** 2026-07-17
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Stations** section — `/statistics/stations/` (All Stations) and `?station={slug}` (single-station profile with 6 sub-views). Structurally identical to the just-shipped **Nations** redesign, re-pointed at the `lez_stations` taxonomy.

## Summary

Port the Nations redesign to Stations (the plugin's `stations.php` and `nations.php` share the same two-level shape). Keep the existing `<select name="station">` GET form / `?station=` / per-view URLs / `$valid_views`. Two levels:

1. **All Stations** (default) — picker row + 4 station-specific counters + a ranked station leaderboard.
2. **Single station** (`?station={slug}`) — a station profile bar + a station sub-nav (Overview, Sexuality, Gender, Tropes, Formats, On Air) + one server-rendered view at a time.

Server-rendered SVG (donut / ranked-bars / trendline) + the existing count-up JS. No Chart.js on these views.

## Reuse mandate

Reuse EVERYTHING the Nations round built. NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Reuse `partials/{donut,ranked-bars,trendline}.php` (incl. the trendline's `$trend['callouts']` Best-Year row + fireworks), `.lwtv-metric-card`, `.lwtv-stats-subnav`, the `.lwtv-nation-profile*` / `.lwtv-nations-lb*` styles, `$ramp-*`, and `Build_Taxonomy_Optimized::get_bulk_first_years()`. The only genuinely NEW code is generalizing the leaderboard partial + porting the three station templates.

## Non-goals

- No routing/query-var/`$valid_views`/scoring changes; the disabled `intersections` view stays omitted.
- No **streaming vs linear** counter — the data has no clean streaming flag (per handoff); the third counter is the top-station share ("Biggest Platform").
- Chart.js enqueue is NOT removed (Death + This Year still use it). After Stations, those two are the only remaining Chart.js consumers.
- No new data layer; `generate_station_statistics()` / `Build_Stations` / the bulk counters already exist and mirror the nation equivalents.

---

## Architecture

Render path unchanged: `stations.php` (picker + routing + `$valid_views`) → `stations/all.php` (all) or `stations/single.php` (single). Primary tab bar is in the page shell (marks Stations active). The three station templates are ports of the Nations templates.

### Data (verified — mirrors nations)

- `Build_Taxonomy_Optimized->make_comprehensive('post_type_shows','lez_stations', true)` → `[slug => ['count','name','url']]`; `->get_bulk_character_counts('lez_stations', slugs)` → `[slug=>['total','dead']]`; `->get_bulk_show_counts(...)` → `[slug=>['onair','total','score','onairscore']]`; all already loaded in `stations.php`.
- `lwtv_plugin()->generate_station_statistics($station, $view, 'array')` (→ `generate_stations` → `Build_Stations->get_station_details`, `formatted ?? raw`) returns the raw per-view slice, shapes identical to nations: sexuality/gender = `[[name,count,url,slug],…]`; tropes/formats = `[[name,count,url],…]`; on-air = `[year=>[name=>year,count,url]]`. **Same `_`-prefix gotcha: pass `ltrim($view,'_')` for the `'array'` format.**
- `get_bulk_first_years('lez_stations', $slugs)` → `[slug=>(int)first_year]` for New-Since-2020.

### Leaderboard generalization

Move `nations/leaderboard.php` → **`partials/leaderboard.php`**, parameterized so both sections use it:
- Inputs: `$leaderboard_rows` (ranked `[slug=>['name','count']]`), `$leaderboard_chars` (`[slug=>['total','dead']]`), `$leaderboard_all` (int), **`$leaderboard_base`** (e.g. `/statistics/nations/` or `/statistics/stations/`), **`$leaderboard_qvar`** (`nation`/`station`), and optional `$leaderboard_title` / `$leaderboard_icon_svg`.
- Row link = `add_query_arg($leaderboard_qvar, $slug, $leaderboard_base)`.
- **Nations output must stay byte-identical** — repoint `nations/all.php` to the shared partial and pass `nation` / `/statistics/nations/` / the same title+icon.
- Keep the true-share bar (`shows/all_shows`) + raspberry ramp by rank.

### All Stations (`stations/all.php`)

- Eyebrow "ACROSS THE DIAL" + **4 metric cards** (compute, don't store; guarded divisors):
  - **Stations** — blue (`shows`), icon `satellite-signal.svg`, count = stations with `count>0`. Caption "Networks & platforms tracked".
  - **Have 10+ Shows** — green (`characters`), icon `library.svg`, count of stations with `count>=10`. Caption "A real depth of catalogue".
  - **Biggest Platform** — yellow (`actors`), icon `location-target.svg`, `round(top_station_count / all_shows * 100)` with `%` suffix. Caption "{Top station} leads — no network dominates" (top name derived) or the generic handoff text.
  - **New Since 2020** — pink (`nations-new`), icon `graph-line.svg`, stations whose earliest show ≥ 2020 via `get_bulk_first_years`. Caption "Aired their first queer show".
- **Leaderboard** via `partials/leaderboard.php` (title "Stations by number of shows", `satellite-signal` icon, `station`/`/statistics/stations/`). Top 10; long-tail note.

### Single station (`stations/single.php`)

Port of `nations/single.php`:
- **Profile bar**: blue eyebrow "STATION PROFILE" + station name + right-aligned Shows / Characters / **Dead** (red).
- **Sub-nav** (`.lwtv-stats-subnav`): Overview / Sexuality / Gender / Tropes / Formats / On Air.
- **Views** (via `generate_station_statistics($station, ltrim($view,'_'), 'array')`):
  - **Overview** — "{STATION} AT A GLANCE" + 4 counters (Shows / On Air / Characters / Dead[red]) + avg-score line + one-sentence summary.
  - **Sexuality / Gender / Formats** — donuts (ramp + grey Other; Gender pulls Cisgender into grey; centres = char count / cis count / show count).
  - **Tropes** — ranked bars (green, `base=''`).
  - **On-Air** — trendline + **Best Year callout** (fireworks): peak on-air count, most-recent year on a tie, `_n()` pluralized, skipped if max 0.

### Enqueue

Add `'stations'` to the count-up JS gate in `class-stats-enqueues.php` (same fix Nations needed) so the counter/leaderboard/donut-legend bars animate. Chart.js enqueue untouched.

## SCSS

No new component styles expected — Stations reuses the Nations classes (`.lwtv-nations-lb*`, `.lwtv-nation-profile*`, `.lwtv-metric-*`, `.card-header.nations-new` pink, donut/trend/callout). If the profile/leaderboard need a station-scoped selector, prefer reusing the existing `.lwtv-nations-*` class names as-is (they're not nation-specific in meaning) rather than adding `.lwtv-stations-*` duplicates. Any dark coverage already exists from the Nations round.

## Icons

`satellite-signal` (Stations counter + leaderboard header), `library` (Have 10+), `location-target` (Biggest Platform), `graph-line` (New Since 2020), `tv` (Shows), `group` (Characters), `skull` (Dead), `tag` (Tropes), `fireworks` (Best Year). All verified in the sprite; FA fallbacks via `get_symbolicon`'s `icon:` arg. No Lucide.

## Data / behavior / testing

- Guard every divisor; harden single-key/empty unwrap. Escape all output; `get_symbolicon` echoes carry the `phpcs:ignore`; i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts.
- Count-up + bar/legend/ring grow via the shared JS; rings static; reduced-motion → finals; dark mode flips.
- Gate: `composer lint`; `npm run lint:css`; `npm run buildquick` (Node 24). Browser QA (light + dark) of All Stations + one station's 6 sub-views against the 4 handoff screenshots; confirm picker/Reset + leaderboard-row links; primary tab bar shows Stations active; **Nations still renders byte-identically** (leaderboard generalization); other sections unchanged.

## Files

**Modified:** `stations.php`; `stations/all.php`; `stations/single.php`; `nations/all.php` (repoint to shared leaderboard); `class-stats-enqueues.php` (add `'stations'`); possibly `scss/addons/_stats.scss` only if a shared-leaderboard class rename is needed (avoid).
**New:** `partials/leaderboard.php` (generalized from `nations/leaderboard.php`).
**Removed:** `nations/leaderboard.php` (superseded by the shared partial).

## Open items resolved in the plan

- Whether "Biggest Platform" caption names the top station dynamically or uses the generic handoff text.
- Confirm `partials/leaderboard.php` produces byte-identical Nations markup after the move (diff Nations before/after).
- Confirm the station on-air `_on-air` 'array' shape + Best-Year parity with nations (same `Build_On_Air->make` path).
