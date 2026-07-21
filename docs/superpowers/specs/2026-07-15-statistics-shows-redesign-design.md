# Statistics on Shows Redesign — Design Spec

**Date:** 2026-07-15
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Shows** section — `/statistics/shows/` and its ten views. Plus promoting the shared primary tab bar into the page shell.

## Summary

Replace the current pie-chart-per-page pattern for `/statistics/shows/` with a shared
shell (primary tab bar + a redesigned Shows sub-nav) and one purpose-built, server-rendered
visualisation per view:

- **Donuts** — Formats, Stars, Triggers, Worth It, We Love It.
- **Ranked horizontal bars** — Tropes, Genres, Intersectionality.
- **Area trendline** — On Air.
- **Overview** — metric cards + two pull-stats + Top Tropes / Top Genres panels, reusing
  the components delivered in the site-Overview round.

Driven by the same data the current pages compute (`generate_shows_statistics`, the `lez_*`
taxonomy term counts, the worth/star/trigger builders, `Build_On_Air`). No new data layer,
no Chart.js for these views (they become server-rendered SVG/HTML like the site Overview).

## Reuse mandate (from the user)

The user has intentionally tuned colors and font sizes in commit `d23d3186` ("Improve
colors and sizes"). **When in doubt, reuse existing components and tokens; do not change
them.** Specifically:

- **Color tokens** live in `scss/partials/_colors.scss` as named vars — use them, never
  hardcode hex: `$lwtv-stats-{green,yellow,red,blue}`, `…-background`, `…-border`;
  `$lwtv-stats-progressbar`; `$lwtv-pink` / `$lwtv-ltpink` / `$lwtv-dkpink`;
  `$lwtv-gold` / `$lwtv-silver` / `$lwtv-bronze`; `$lwtv-red` / `$lwtv-yellow`;
  `$lwtv-medgrey` / `$lwtv-dkgrey` / `$lwtv-bordergrey`.
- The handoff README quotes some hex values (e.g. `$lwtv-pink` as `#d1548e`) that differ
  from the committed token (`#cb3e85`). **The committed token always wins** — reference the
  variable, never the README's literal.
- Reuse the existing overview components rather than rebuilding: `.lwtv-metric-card` with
  per-type `.lwtv-metric-icon.{shows,characters,actors,dead}`; the `lwtv_stats_sparkline_points()`
  helper; the `.lwtv-byg` pull-stat band; `.lwtv-panel` + `.lwtv-bar-row` ranked bars; the
  count-up / bar-grow JS; the `.card-header.{shows|characters|actors|dead-characters}`
  family classes (which already carry dark variants).
- Do **not** revert any color/size the user set (e.g. the `.lwtv-metric-caption` size, the
  active-tab pink border, the per-type icon-tile backgrounds).

## Non-goals

- No redesign of Characters / Actors / Nations / Stations / Death view *bodies*. They only
  gain the shared primary tab bar (see below); their content is untouched.
- No change to statistics URLs, query-var routing, or `class-calculations.php` / scoring.
- No new data/query layer — reuse existing generators and builders.

## Fidelity

High-fidelity to the handoff (`design_handoff_statistics_shows/README.md` + screenshots),
but every color expressed through the theme's existing tokens/classes.

---

## Architecture

### Shell

Current render path (unchanged in shape):

```
page-templates/statistics.php
  → generate_stats_block(['page' => $statstype])
    → Gutenberg_SSR::statistics() includes templates/<statstype>.php
      → shows.php: switch($view) includes shows/<view>.php
```

Changes:

1. **Primary tab bar → page shell.** Move the `main/tabbar.php` include out of `main.php`
   and render it once in `page-templates/statistics.php` (inside the content area, above the
   per-page stats block), so it appears on every stats view. Generalize `tabbar.php` to mark
   the active tab from the current section (`$statstype`: `main`→Overview, `shows`→Shows,
   etc.) instead of always marking Overview. `main.php` no longer includes it.
   - Consequence (accepted): Characters, Actors, Nations, Stations, Death gain the tab bar.
     Additive navigation only; no other change to those pages.
2. **Shows sub-nav → `shows/subnav.php`** (new; replaces `shows/navbar.php`). A bottom-border
   tab row of the 10 views, active item = `$lwtv-pink` 2px underline + `$lwtv-dkgrey` text,
   inactive = `$lwtv-medgrey`. Horizontally scrollable (`overflow-x:auto`) on narrow screens.
   URLs unchanged (`/statistics/shows/`, `/statistics/shows/<view>/`).
3. **`shows.php`** keeps its `switch($view)` routing; each case includes its rewritten view
   partial. It wraps output in a `.lwtv-stats-overview` (or equivalent max-width `1120px`)
   container to match the Overview shell.

### Reusable chart partials (new) — `templates/partials/`

Purpose-built, parameterised, and reusable by later sections too:

- **`donut.php`** — inputs: an ordered array of segments `[ ['label','count','pct','class'|'color'], … ]`,
  a center figure (int), a center sublabel (string), a headline (string), and a description
  (string). Renders: an SVG ring — `viewBox 0 0 120 120`, `r=50`, `stroke-width=15`,
  `pathLength=100`, group `rotate(-90 60 60)`; each segment `stroke-dasharray="<share>
  <100-share>"` with cumulative negative `stroke-dashoffset` — plus a legend (color dot +
  segment label + mini progress bar in the segment color on a `.bg-light`/muted track +
  `count · pct%`). Ring renders at final proportions immediately; the center figure and
  legend counts count up; legend mini-bars grow (shared JS driver).
- **`trendline.php`** — inputs: a year→count series and optional peak/current annotations.
  Renders an SVG area trendline — `viewBox 0 0 800 280`, baseline `y=240`, area fill ~12% +
  `2.5px` `$lwtv-pink` stroke, dashed `$lwtv-bordergrey` gridlines/baseline, a peak-year dot
  + label, and a right-aligned current-year headline figure (counts up).

Segment colors are passed as **class names** mapping to existing tokens (e.g. a
`.lwtv-donut-seg--gold` / `--green` / `--red` set defined once in SCSS from the tokens),
never inline hex — so dark mode and the reuse mandate hold.

### Views

| View (URL) | Chart | Data source | Color family (tokens/classes) |
|---|---|---|---|
| Overview `/statistics/shows/` | 3 metric cards + 2 pull-stats + Top Tropes/Genres panels | `get_growth_series('shows')`; `lez_tropes`/`lez_genres` term counts; top-N via `Build_Taxonomy_Optimized` | card-header shows / characters / actors |
| Formats `…/formats/` | donut | `Build_Formats` (`lez_formats`) | raspberry ramp `$lwtv-dkpink→$lwtv-pink→$lwtv-ltpink` (mid-steps via `color.mix`) |
| Tropes `…/tropes/` | ranked bars | `lez_tropes` | characters / green (`$lwtv-stats-green*`) |
| Genres `…/genres/` | ranked bars | `lez_genres` | actors / amber (`$lwtv-stats-yellow*`) |
| Intersectionality `…/intersectionality/` | ranked bars | `lez_intersections` (URL "intersectionality" → type "intersections") | shows / blue (`$lwtv-stats-blue*`) |
| Stars `…/stars/` | donut | `lez_stars` | `$lwtv-gold` / `$lwtv-silver` / `$lwtv-bronze` / `$lwtv-red` / `$lwtv-medgrey` |
| Triggers `…/triggers/` | donut | `lez_triggers` | red severity: `$lwtv-red`, `color.mix($lwtv-red,$lwtv-yellow,65%)`, `color.mix($lwtv-red,$lwtv-yellow,25%)`, `$lwtv-medgrey` |
| On Air `…/on-air/` | area trendline | `Build_On_Air->generate('shows')` | `$lwtv-pink` |
| Worth It `…/worth-it/` | donut | `Build_Worth_It` (`lezshows_worthit_rating`) | green / amber / red / `$lwtv-medgrey` (semantic) |
| We Love It `…/we-love-it/` | donut | `Build_We_Love_It` (`lezshows_worthit_show_we_love`) | `$lwtv-pink` vs `$lwtv-medgrey` |

Raw data is obtained via the existing `generate_shows_statistics('array', $type)` (the
handler's default path returns the raw `[$view => data]` array — unwrap it) or, where the
current Overview already does so, via direct builder calls (`Build_Taxonomy_Optimized->
make_comprehensive(...)`). No new builder methods.

### Overview view details

- **Metric cards (3-up):** Shows → `.card-header.shows` (blue), Tropes → `.card-header.characters`
  (green), Genres → `.card-header.actors` (amber). Numbers count up.
  - **Sparklines:** Shows card renders the real cumulative series from
    `generate_growth_series('shows')`. Tropes and Genres have no real time-series (term
    counts, not post growth), so they render a **representative** decorative line — a fixed,
    gently-rising point set fed through the same `lwtv_stats_sparkline_points()` helper,
    `aria-hidden`, purely visual. (Decision: representative line, not omitted.)
- **Two pull-stats ("The Trope Gap"):** a 2-up row reusing the `.lwtv-byg` band styling —
  "Bury Your Queers" (red / `.card-header.dead-characters`) and "Happy Endings" (green /
  `.card-header.characters`), each `border-left:3px` accent + tint fill. Data source: the
  relevant `lez_tropes` term counts (exact term slugs confirmed in the plan).
- **Top Tropes / Top Genres panels:** two `.lwtv-panel`s reusing `.lwtv-bar-row` leader bars
  (green / amber families) + a footer link to the full view. Replaces the current tables.

---

## Interactions & behavior

- **Sub-nav:** server-rendered per-URL; active-state only, no JS.
- **Count-up:** headline figures, legend counts, and bar/legend-bar widths animate 0→target
  over **1100ms**, `easeOutCubic`, on the existing shared JS driver
  (`statistics-overview.js`). **Donut rings render at final proportions immediately** — only
  numbers count up. The stats-JS enqueue (currently overview-only, gated on the `statistics`
  query var being `none`) is extended to also fire on the Shows views.
- **Reduced motion:** `prefers-reduced-motion: reduce` → render final values/widths at once
  (existing JS already honors this).
- **Dark mode:** via the theme's `color-mode(dark)`; reuse `.card-header.*`, link, and
  progress-bar classes so views flip automatically. Keep the On Air line and the We-Love-It
  ring pink in dark; only neutral/None/grey segments follow the dark neutrals.
- **Hover:** links → `$lwtv-purple`; tab/sub-nav follow the theme's nav hover. Cards not
  clickable.

## Derived values

- `pct = round(count / total * 100, 1)`; bar widths relative to the leading value.
- Headlines derived from the leading slice (e.g. Formats "Seven in ten are full TV series";
  Stars centre = "No Star" count; Triggers centre = "None" count; Worth It centre = "Yes"
  count). Phrasing must be i18n-ready.
- Guard every divisor (`total`, leader, segment sums) against zero.

## Data integrity & safety

- Escape all output; keep existing query-var sanitization; no new query vars.
- Never divide by zero; on empty builder data render the existing graceful fallback rather
  than a broken chart.
- Donut math: segment shares sum to ≤100; the ring uses `pathLength=100` so shares map
  directly to dash lengths. Clamp/normalise if a rounding drift would exceed 100.

## Testing / verification

- `composer lint` / `composer lint-fix` (PHPCS WordPress-Extra).
- `npm run lint:css`; `npm run buildquick` (**Node 24** — `.nvmrc`; Node 18 fails with
  `crypto is not defined`).
- Manual verification on `https://lwtv.local/statistics/shows/` and each sub-view: light +
  dark, reduced-motion, narrow/stacked layouts; cross-check counts/percentages; confirm the
  primary tab bar renders on all stats sections and marks the right active tab; confirm the
  other (non-redesigned) sections still render correctly with the added tab bar.

## Files

**New**
- `plugins/lwtv-plugin/php/statistics/templates/shows/subnav.php` (replaces `navbar.php`)
- `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php`
- `plugins/lwtv-plugin/php/statistics/templates/partials/trendline.php`

**Modified**
- `page-templates/statistics.php` (render the primary tab bar in the shell)
- `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php` (active tab from `$statstype`)
- `plugins/lwtv-plugin/php/statistics/templates/main.php` (drop its tabbar include)
- `plugins/lwtv-plugin/php/statistics/templates/shows.php` (container wrapper; sub-nav; keep routing)
- `plugins/lwtv-plugin/php/statistics/templates/shows/overview.php` (cards + pull-stats + panels)
- `plugins/lwtv-plugin/php/statistics/templates/shows/{formats,tropes,genres,intersectionality,stars,triggers,on-air,worth-it,we-love-it}.php` (rewrite to new charts)
- `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php` (enqueue stats JS on Shows too)
- `plugins/lwtv-plugin/php/statistics/templates/main/overview.php` — only if the sparkline
  helper needs to move to a shared include so Shows Overview can reuse it (see below).
- `scss/addons/_stats.scss` (sub-nav, donut, trendline, ranked-bar family modifier — light)
- `scss/partials/_colors-dark.scss` (dark variants for the above)

**Removed**
- `plugins/lwtv-plugin/php/statistics/templates/shows/navbar.php` (replaced by `subnav.php`)

### Shared sparkline helper

`lwtv_stats_sparkline_points()` currently lives (guarded by `function_exists`) inside
`main/overview.php`. Shows Overview needs it too. Move it to a small shared include
(e.g. `templates/partials/sparkline.php` or a helper loaded by both), included where needed,
so there is one definition. Keep the `function_exists` guard.

---

## Open items resolved in the plan

- Exact `lez_tropes` term slugs backing the two "Trope Gap" pull-stats.
- The representative-line point set for Tropes/Genres sparklines.
- The precise unwrapping of `generate_shows_statistics('array', $type)` return shapes per
  view (taxonomy vs. Worth_It/We_Love_It/Formats/On_Air builders).
