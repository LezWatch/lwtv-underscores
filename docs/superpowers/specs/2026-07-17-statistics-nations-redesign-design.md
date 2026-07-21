# Statistics on Nations Redesign — Design Spec

**Date:** 2026-07-17
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Nations** section — `/statistics/nations/` (All Nations) and `?nation={slug}` (single-nation profile with 6 sub-views). Rebuilds the section on the shared stats shell, replacing the per-view Chart.js pies/bars/trendline with server-rendered SVG.

## Summary

Two-level section, keeping the existing `<select name="nation">` GET form + `?nation=` / per-view URLs and the plugin's `$valid_views` exactly as `nations.php` does today:

1. **All Nations** (default `/statistics/nations/`) — picker row + 4 nation-specific metric cards + a ranked nation leaderboard.
2. **Single nation** (`?nation={slug}`) — picker row (+ Reset) + a nation profile bar + a nation sub-nav (Overview, Sexuality, Gender, Tropes, Formats, On Air) + one view at a time.

Server-rendered SVG + the existing count-up JS (already enqueued on `is_page('statistics')`). No Chart.js on these views.

## Reuse mandate

Reuse the components + tokens from the statistics rounds. NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Reuse `partials/{donut,ranked-bars,trendline,sparkline}.php`, `.lwtv-metric-card`, `.lwtv-stats-subnav`, the donut ramp seg classes (`dkpink`/`pink`/`mid`/`mid2`/`ltpink`), and the `$lwtv-*` / `$lwtv-stats-*` / `$ramp-*` tokens. Keep the theme font stack — do not import Inter/Lucide.

## Family map (per handoff)

| Used for | Family | Tokens |
|---|---|---|
| Nations counter, sexuality, profile eyebrow | **blue** (`shows`/`sexuality`) | `$lwtv-stats-blue` |
| Have-10+ counter, characters, tropes bars | **green** (`characters`) | `$lwtv-stats-green` |
| US+UK-share counter, shows-count | **yellow** (`actors`) | `$lwtv-stats-yellow` |
| Dead figures (leaderboard, profile, overview) | **red** (`dead-characters`) | `$lwtv-stats-red` |
| Growth counter tile, leaderboard bars, on-air line | **pink** | `$lwtv-pink` (+ `$ramp-*` for the ramp) |

## Non-goals

- No change to routing, query vars, `$valid_views`, the GET picker form, or scoring. The disabled `intersections` view stays omitted.
- No redesign of the other stats sections; the shared `donut.php`/`ranked-bars.php`/`trendline.php` default behavior stays unchanged.
- Chart.js enqueue is NOT removed (Stations, Death, and This Year still use it). This section simply stops emitting charts.

---

## Architecture

Render path unchanged: `nations.php` (picker + view routing + `$valid_views`) → includes `nations/all.php` (when nation = all) or `nations/single.php` (single nation). The primary tab bar is already rendered by the page shell (`statistics.php`); `nations.php` marks Nations active automatically. This redesign restyles those three templates and adds one new partial (`nations/leaderboard.php`) + SCSS.

### Data (verified in the plan; shapes below from the current templates)

- All-nations dataset: `Build_Taxonomy_Optimized->make_comprehensive('post_type_shows','lez_country', true)` → `[slug => ['name','count', …]]`; `->get_bulk_character_counts('lez_country', slugs)` → `[slug => ['total','dead']]`; `->get_bulk_show_counts(...)` → `[slug => ['total','onair','score','onairscore']]`.
- Total shows: `lwtv_plugin()->generate_total_counts('shows')`; share = `round(count / all_shows * 100, 1)`.
- Per-nation view data: `lwtv_plugin()->generate_nation_statistics($nation, $view, 'array', $custom_data, $bar_direction)` where `$nation` is `_slug` and `$view` is `_all`/`_sexuality`/`_gender`/`_tropes`/`_formats`/`_on-air` — returns the raw data array (`handle()` `default: return $data`), to be unwrapped/guarded and rendered with the shared partials. (The current templates request `piechart`/`percentage`/`barchart`/`trendline`; the redesign requests `array` and renders server-side.)

### Shell / picker

- **Picker row** — styled label ("NATION") + the existing `<select name="nation" id="nation">` + Go submit, and a "Reset to all nations" link when a nation is selected. Keep the `<form method="get" id="go">`. Restyle only (reuse stats eyebrow/spacing tokens); selecting a nation resets the sub-view to Overview (unchanged behavior — Overview is the default view).
- Container `max-width:1120px` (reuse `.lwtv-stats-overview`).

### All Nations (`nations/all.php`)

- Eyebrow "AROUND THE WORLD" + **4 metric cards** (all computed from the nation dataset, not stored):
  - **Nations** — blue, `globe`, count of nations (`count($all_nations_data)` with `count>0`).
  - **Have 10+ Shows** — green, `layers`, count of nations with `count >= 10`.
  - **US + UK Share** — yellow, `target`, sum of the top-two nations' shares (`%`).
  - **New Since 2020** — pink, `trending-up`, count of nations whose first show post-dates 2020; icon tile bg = `rgba($lwtv-pink, .14)`.
- **Nation leaderboard** → new `nations/leaderboard.php`: a 6-column grid (rank · nation link · share bar · Shows·pct · Chars · **Dead** in red), grid `18px 148px 1fr 104px 66px 60px`, 12px gap, 1px row borders. Share bar width = `round(count / top_nation_count * 100, 1)`, colored by **raspberry ramp by rank** (row 1 `dkpink` (darkest) → 2 `pink` → 3 `mid` → 4 `mid2` → 5+ `ltpink`), reusing the `.lwtv-donut-seg--*` ramp classes for fills. Rows skip `count===0`. Top 10 shown; footnote notes the long tail (~40+ nations). Nation link = `?nation={slug}` (drill-in). Header row labels: Nation / Share of all shows / Shows / Chars / Dead.

### Single nation (`nations/single.php`)

- **Profile bar** (`.bg-light` card): blue eyebrow "NATION PROFILE" + nation name (large) on the left; right-aligned **Shows** / **Characters** / **Dead** (red) figures.
- **Sub-nav** (`.lwtv-stats-subnav`): Overview / Sexuality / Gender / Tropes / Formats / On Air (maps to `$valid_views`). Active from `$view`. (The primary tab bar is in the shell; this is the nation sub-nav.)
- **Views** (one at a time), each reusing an existing partial fed by `generate_nation_statistics($nation, $view, 'array', …)`:
  - **Overview** — eyebrow "{NATION} AT A GLANCE" + 4 counters (Shows / On Air / Characters / Dead[red]) + an average-score line (`score` / `onairscore` out of 100) + a one-sentence summary ("{n} of {nation}'s {shows} shows are currently on air…"). Mirrors the current per-nation barchart summary, server-rendered.
  - **Sexuality** — donut (`donut.php`): raspberry ramp + grey (`$lwtv-medgrey`) "Other" tail; centre = nation character count.
  - **Gender** — donut: grey Cisgender (`$lwtv-medgrey`) + raspberry ramp for the trans/non-binary minority; centre = cisgender count.
  - **Tropes** — ranked bars (`ranked-bars.php`, green/`characters` family), "Most common tropes in {nation}".
  - **Formats** — donut: raspberry ramp; centre = nation show count.
  - **On Air** — area trendline (`trendline.php`): `$lwtv-pink` line + ~12% fill, `$lwtv-bordergrey` gridlines.

---

## New SCSS

- **Leaderboard** (`nations/leaderboard.php` styles): `.lwtv-nations-lb` grid (`18px 148px 1fr 104px 66px 60px`), header row, 1px row borders, share-bar track + ramp fills (reuse `.lwtv-donut-seg--*` for the bar color by rank, or a `.lwtv-lb-bar--{rank}` mapping to the ramp tokens), dead figure in `$lwtv-stats-red`. Right-aligned numeric columns, tabular figures.
- **Picker row** + **profile bar** + **single-overview counters**: light layout classes (`.lwtv-nations-picker`, `.lwtv-nation-profile`, reuse `.lwtv-metric-*` where possible) using existing tokens.
- **Growth tile**: `rgba(colors.$lwtv-pink, 0.14)` for the New-Since-2020 icon tile (derive, don't hardcode).
- Dark coverage extends the existing `.statistics` dark block (these pages ARE under `.statistics`, so most donut/metric/subnav dark rules already apply); add dark rules only for the NEW leaderboard/profile/picker classes as needed against the dark screenshots.

## Icons

Symbolicon equivalents (verify sprite ids / FA fallbacks in the plan; do NOT import Lucide): globe (Nations), layers (Have 10+), target (US+UK), trending-up (New Since 2020 / growth), tv (Shows), users (Characters), skull (Dead), tag (Tropes), arrow-right (links/footers).

## Data / behavior / testing

- Compute the derived counters from the dataset with guarded divisors; harden any single-key unwrap (`is_array && ! empty`). Escape all output; `get_symbolicon` echoes carry the `phpcs:ignore`; i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts.
- Count-up + bar/legend/ring grow via the existing JS (already enqueued on `is_page('statistics')`); donut rings static; reduced-motion → finals; dark mode flips via `color-mode(dark)`.
- Gate: `composer lint`; `npm run lint:css`; `npm run buildquick` (**Node 24**). Browser QA (light + dark) of All Nations + one nation's 6 sub-views against the 6 handoff screenshots; confirm picker/Reset + leaderboard-row links switch nations; primary tab bar shows Nations active; other stats sections unchanged.

## Files

**Modified:** `plugins/lwtv-plugin/php/statistics/templates/nations.php` (picker + routing restyle; request `array` format); `nations/all.php` (counters + leaderboard); `nations/single.php` (profile bar + sub-nav + 6 views); `scss/addons/_stats.scss`; `scss/partials/_colors-dark.scss` (dark for new classes).
**New:** `plugins/lwtv-plugin/php/statistics/templates/nations/leaderboard.php`.

## Open items resolved in the plan

- Exact return shapes of `generate_nation_statistics($nation, $view, 'array', …)` for each of the 6 views (verify live; map to the `$donut`/`$ranked`/`$trend` contracts).
- Whether the single-nation Overview counters/score come from `get_bulk_show_counts` (score/onairscore/onair/total) + `get_bulk_character_counts` (total/dead) directly (likely) vs. `generate_nation_statistics(..., '_all', 'array')`.
- The "New Since 2020" per-nation first-show date source (whether the dataset exposes a first-air/earliest-post date, or it needs a small query).
- Confirm the leaderboard ramp mapping (reuse `.lwtv-donut-seg--*` fills vs. a dedicated `$ramp-*`-backed class) and the sprite ids for the 9 icons.
- Whether the sub-nav "On Air" trendline needs `Build_On_Air` filtered by nation (per handoff) vs. the existing `generate_nation_statistics('_on-air','trendline')` path re-expressed as `array`.
