# Statistics on Characters Redesign — Design Spec

**Date:** 2026-07-16
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Characters** section — `/statistics/characters/` and its seven views. A direct mirror of the Shows redesign, reusing its shell, components, and chart partials.

## Summary

Replace the pie-chart-per-page pattern for `/statistics/characters/` with the shared shell
(primary tab bar already in the page shell + a Characters sub-nav) and one purpose-built,
server-rendered visualisation per view:

- **Overview** — 4 metric cards + 2 callouts + 3 leader/tail panels (Top Clichés / Top
  Sexual Orientations / Top Gender Identities).
- **Clichés** — ranked horizontal bars (green).
- **Most Clichés** — a leaderboard of individual characters (rank + name + bar + count).
- **Gender** — donut (grey cisgender + raspberry-ramp minority slices).
- **Sexuality** — donut (raspberry ramp + grey tail).
- **Queer IRL** — 3-segment donut (played-by-queer / straight-or-cis / unknown).
- **On Air** — area trendline (characters-on-air per year).

Driven by the same data the current pages compute (`generate_characters_statistics`, the
`lez_cliches` / `lez_gender` / `lez_sexuality` term counts, the `queer-irl` cliché,
`Build_Cliche_Leaders()->generate()`, `Build_On_Air->generate('characters')`). No Chart.js
for these views; server-rendered SVG + the existing count-up JS.

## Reuse mandate

Everything here reuses the components and tokens built in the Overview and Shows rounds. **Do
not hardcode hex, do not use the handoff README's literal hex, do not revert the user's
committed color/size tweaks.** Reuse:

- **Partials:** `partials/donut.php`, `partials/trendline.php`, `partials/ranked-bars.php`,
  `partials/sparkline.php`.
- **Components/classes:** `.lwtv-stats-overview`, `.lwtv-stats-subnav` / `-item`,
  `.lwtv-metric-card` + `.lwtv-metric-icon.{characters,…}`, `.lwtv-pullstats` +
  `.lwtv-tropegap*` (the 2-up callout cards), `.lwtv-panel` + `.lwtv-panel-head` +
  `.lwtv-leaders`/`.lwtv-leader-*` + `.lwtv-tail`/`.lwtv-tail-*`, the donut `.lwtv-donut-*`
  + `.lwtv-donut-seg--*` ramp, the trendline `.lwtv-trend-*`.
- **Tokens:** `$lwtv-stats-{green,yellow,red,blue}[-background|-border]`, `$lwtv-pink` /
  `$lwtv-ltpink` / `$lwtv-dkpink`, `$lwtv-medgrey` / `$lwtv-dkgrey` / `$lwtv-bordergrey` /
  `$lwtv-ltgrey`, and the donut ramp `$ramp-1…$ramp-5` (already in `_stats.scss` via
  `color.mix()`).

**Character-type → family** (existing `.card-header.*` classes; dark variants already
defined): Characters / Clichés → **green** (`characters`); Sexual Orientations → **blue**
(`sexuality`); Gender Identities → **yellow** (`gender`); Dead/death → **red**
(`dead-characters`).

## Non-goals

- No redesign of the other sections (Overview, Shows already done; Actors / Nations /
  Stations / Death untouched). No routing/URL/query-var/scoring changes.
- No new data layer — reuse existing generators/builders.
- No new palette beyond one `bordergrey` donut-segment color (see below).

## Fidelity

High-fidelity to the handoff (`design_handoff_statistics_characters/README.md` +
screenshots), every color via existing tokens.

---

## Architecture

Render path unchanged: `statistics.php` (shell + primary tab bar) → `generate_stats_block(
['page'=>'characters'])` → `characters.php` `switch($view)` → per-view partial.

### Shell

1. **Primary tab bar** — already promoted to `page-templates/statistics.php`; it marks
   **Characters** active automatically. No change.
2. **Characters sub-nav → `characters/subnav.php`** (new; replaces `characters/navbar.php`).
   Reuse the generic `.lwtv-stats-subnav` / `.lwtv-stats-subnav-item` classes (same as the
   Shows sub-nav). Items = Overview + `$valid_views` (`cliches`, `most-cliches`, `gender`,
   `sexuality`, `queer-irl`, `on-air`); labels "Overview · Clichés · Most Clichés · Gender ·
   Sexuality · Queer IRL · On Air". URLs unchanged.
3. **`characters.php`** wraps output in `.lwtv-stats-overview`, includes the sub-nav, keeps
   its existing `switch($view)` routing and the `overview`-only data pre-compute.

### Per-view mapping

| View (URL) | Component | Data source | Family |
|---|---|---|---|
| Overview `…/characters/` | 4 metric cards + 2 callouts + 3 panels | growth series; `lez_*` counts; top-N; queer-irl; dead cliché | mixed |
| Clichés `…/cliches/` | `ranked-bars.php` (share mode) | `lez_cliches` | green |
| Most Clichés `…/most-cliches/` | `ranked-bars.php` (leaderboard mode) | `Build_Cliche_Leaders()->generate()` | green |
| Gender `…/gender/` | `donut.php` | `lez_gender` | grey + ramp |
| Sexuality `…/sexuality/` | `donut.php` | `lez_sexuality` | ramp + grey |
| Queer IRL `…/queer-irl/` | `donut.php` (3-seg) | `queer-irl` cliché breakdown | pink/grey/border |
| On Air `…/on-air/` | `trendline.php` | `Build_On_Air->generate('characters')` | pink |

Raw data via `generate_characters_statistics('array', $type)` (unwrap the single-key
wrapper with `reset()`, as in Shows) or direct builder calls where the current overview
already does so. Exact wrapper keys/shapes per view confirmed in the plan (as with Shows).

### Overview view

- **Eyebrow** "CHARACTERS AT A GLANCE" + **4 metric cards** (`.lwtv-metric-card` +
  `.card-header.<family>` + `.lwtv-metric-icon.<family>`):
  - Characters → green, real sparkline from `generate_growth_series('characters')`.
  - Sexual Orientations → blue; Gender Identities → yellow; Clichés → red — all three are
    term counts with no time-series, so a **representative** decorative sparkline (fixed
    gently-rising point set through `lwtv_stats_sparkline_points()`, `aria-hidden`), same as
    the Shows Tropes/Genres cards.
  - Icons (Symbolicons; confirm sprite ids in the plan): Characters `user`, Sexual
    Orientations `heart`, Gender Identities a gender/venus glyph, Clichés `tag`.
- **Eyebrow** "THE STORIES WE KEEP TELLING" + **2 callouts** — a 2-up `.lwtv-pullstats`
  row of `.lwtv-tropegap` cards:
  - **Bury Your Gays** (red / `.card-header.dead-characters`): big number = characters
    carrying the Dead cliché; description "…roughly one in six" where `1 in N = round(total
    / dead)`. Count-up.
  - **Played by queer actors** (green / `.card-header.characters`): big number = the
    played-by-queer count; description names both sides (queer vs straight/cis) from the
    Queer IRL data. Count-up.
- **3 leader/tail panels** in `.lwtv-panels` (3-up): Top Clichés (green, `/cliche/{slug}`),
  Top Sexual Orientations (blue, `/sexuality/{slug}`), Top Gender Identities (yellow,
  `/gender/{slug}`) — each = 5 leader bars + a 5-row tail table + a "view all →" footer
  link, exactly the Shows overview panel pattern. Note Queer IRL is the #1 cliché.

### Clichés view

`ranked-bars.php` in its normal (share) mode: all clichés ranked by character count, green
family, bar width = true share of all characters (`pct = round(count/total*100,1)`),
count·pct labels. Note a character carries several clichés, so shares sum past 100%. Panel
header (tag icon + "All N clichés, ranked" + sub). Footer to the cliché glossary.

### Most Clichés view — extend `ranked-bars.php` with a leaderboard mode

Add an optional `mode` (default `share`; `leaderboard` for this view) to the `$ranked`
contract:

- **leaderboard mode:** rows are individual characters (from `Build_Cliche_Leaders()->
  generate()`); render **rank number** (1, 2, 3 …) + character name (→ `/character/{slug}`)
  + bar **relative to the top count** (`width = count/topCount*100`) + the raw count (no
  percentage). Bars = characters/green.
- **share mode (existing/default):** unchanged — taxonomy terms, true-share bars, count·pct.

This keeps one partial. The mode switches the width basis (relative-to-top vs true-share),
the label (count vs count·pct), and whether a rank index is shown.

### Donut views

Reuse `donut.php` (ring `pathLength=100`, legend with mini-bars; rings static, numbers
count up).

- **Sexuality** — raspberry ramp `$ramp-1…$ramp-5` (darkest = largest) + `grey` tail for
  "Other". Centre = total characters. Order: Lesbian · Bisexual · Gay · Queer · Pansexual ·
  Other. Headline "Two in three are lesbian or bisexual."
- **Gender** — cisgender dominates (~84%) so its slice is **grey** (`$lwtv-medgrey`); the
  raspberry ramp highlights the trans / non-binary / genderqueer minority. Centre = the
  cisgender count. Order: Cisgender (grey) · Trans Woman · Non-binary · Trans Man ·
  Genderqueer · Other. Headline "Most characters are cisgender — but not all."
- **Queer IRL** — 3 segments: **Played by queer actors** `$lwtv-pink` · **Straight or cis
  actors** `$lwtv-medgrey` · **Unknown** `$lwtv-bordergrey`. Centre = the played-by-queer
  count. Headline "Fewer than a third are played by queer actors." Requires one new segment
  color: **`.lwtv-donut-seg--bordergrey`** (stroke/background `$lwtv-bordergrey`) added to
  `_stats.scss`. Keep the "yes"/played-by-queer arc pink in dark.

### On Air view

Reuse `trendline.php` with `Build_On_Air->generate('characters')` — pink line + 12% area,
dashed baseline, peak dot, right-aligned current-year figure (counts up). Pink in dark.

---

## Data / derived values

- `pct = round(count / total * 100, 1)` (of all characters); leader/ranked share bars use
  it; leaderboard bars use `count / topCount`.
- Bury-Your-Gays ratio `1 in N = round(total_characters / dead_cliche_count)`.
- Guard every divisor against zero; skip zero-count rows in ranked (share) lists; render
  graceful fallback on empty builder data (harden the unwrap as in Shows).
- All user-facing strings i18n-ready (`__()`, `number_format_i18n()`, `'lwtv'` domain);
  escape all output; `get_symbolicon()` echoes carry the `phpcs:ignore`.

## Interactions & dark mode

- Count-up (1100ms `easeOutCubic`) + bar/legend-bar grow on the existing shared JS driver
  (already enqueued on the stats page for `none`/`shows` — extend the enqueue gate to
  `characters` too). Donut rings render final immediately. Reduced-motion → finals at once.
- Dark mode flips via the `.card-header.*`, link, and progress-bar classes. On Air line and
  the Queer-IRL played-by-queer arc stay pink in dark.

## Testing / verification

- `composer lint` / `composer lint-fix`; `npm run lint:css`; `npm run buildquick` (**Node
  24**; Node 18 fails `crypto is not defined`).
- Manual verification of each of the 7 views on `https://lwtv.local/statistics/characters/`:
  light + dark, reduced-motion, narrow layouts; cross-check counts; confirm the primary tab
  bar shows Characters active and the other sections still render.

## Files

**New**
- `plugins/lwtv-plugin/php/statistics/templates/characters/subnav.php` (replaces `navbar.php`)

**Modified**
- `plugins/lwtv-plugin/php/statistics/templates/characters.php` (container + sub-nav include; overview data)
- `plugins/lwtv-plugin/php/statistics/templates/characters/overview.php` (cards + callouts + 3 panels)
- `plugins/lwtv-plugin/php/statistics/templates/characters/{cliches,most-cliches,gender,sexuality,queer-irl,on-air}.php` (rewrites)
- `plugins/lwtv-plugin/php/statistics/templates/partials/ranked-bars.php` (add leaderboard mode)
- `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php` (enqueue stats JS on `characters`)
- `scss/addons/_stats.scss` (add `.lwtv-donut-seg--bordergrey`; any character-specific tweaks; leaderboard rank styling)
- `scss/partials/_colors-dark.scss` (dark variants for the above if needed)

**Removed**
- `plugins/lwtv-plugin/php/statistics/templates/characters/navbar.php`

## Open items resolved in the plan

- Exact wrapper keys / shapes of `generate_characters_statistics('array', $type)` per view
  (taxonomy vs Queer_IRL vs Cliche_Leaders vs On_Air), confirmed live with `wp eval`.
- The `queer-irl` breakdown fields (played-by-queer / straight-or-cis / unknown counts) and
  the Dead cliché count source for the callouts.
- Symbolicon sprite ids for the four metric-card icons (and the sub-nav needs none).
- The representative-sparkline point set (reuse the Shows one).
