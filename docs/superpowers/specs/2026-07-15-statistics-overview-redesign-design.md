# Statistics Overview Redesign — Design Spec

**Date:** 2026-07-15
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Overview** view only (`/statistics/`, `statstype = 'main'`).

## Summary

Replace the current Overview view — a plain Bootstrap card grid plus two "Top Ten"
tables — with the story-driven layout from the design handoff
(`design_handoff_statistics_overview`):

1. A **tab bar** for the statistics sub-sections (URLs unchanged).
2. **"The Database, Live"** — four metric cards (Shows, Characters, Actors, Dead) with
   count-up numbers and growth sparklines.
3. A **"Bury Your Gays"** callout band promoting the death stat.
4. Two panels: **"Where queer TV lives"** (top networks, horizontal bars) and
   **"Around the world"** (nations, a 100% stacked share bar + ranked legend).

Everything is driven by data the site already computes, plus one new cumulative growth
series for the sparklines. No new palette, no new webfont, no Lucide dependency, no
change to scoring or CPT relationships.

## Non-goals

- No redesign of the sub-section pages (Shows, Characters, Actors, Nations, Stations,
  Death). They keep their current look and their own `nav-tabs`.
- No change to statistics URLs or query-var routing.
- No change to `class-calculations.php` or any show-score logic.
- The tab bar is **not** promoted into the page template in this pass; it renders as part
  of the Overview render path only (built as a standalone partial so it can be lifted to
  `page-templates/statistics.php` later if the redesign is extended section-wide).

## Fidelity

High-fidelity. Colors, type scale, spacing, and interactions are specified by the handoff
(`design_handoff_statistics_overview/README.md`) and its screenshots. Recreate faithfully,
but express every value through the theme's existing SCSS tokens and Bootstrap utilities —
do not hardcode the prototype's raw numbers.

---

## Architecture

The existing render path is preserved:

```
page-templates/statistics.php
  → lwtv_plugin()->generate_stats_block( ['page' => 'main'] )
    → Gutenberg_SSR::statistics()  (includes templates/main.php)
      → templates/main.php  (orchestrator; includes the partials below)
```

### Data layer (new)

Add a cumulative growth series used by the sparklines. It lives alongside the existing
count helpers so it shares their caching and invalidation.

- **Method:** `Stats_Counter::get_growth_series( string $subject ): array`
  - `$subject` ∈ `shows | characters | actors | dead`.
  - Returns a normalized, year-ordered array: `[ [ 'year' => int, 'count' => int ], … ]`
    where `count` is the **cumulative** total of entries up to and including that year
    (monotonically non-decreasing; the final `count` equals the card's headline total).
  - `shows | characters | actors`: one grouped query per CPT —
    `SELECT YEAR(post_date) AS y, COUNT(*) AS c FROM …posts WHERE post_type = %s AND
    post_status = 'publish' GROUP BY y ORDER BY y` — then cumulative-summed in PHP. The
    series spans from the earliest entry year present to the current year. Because entries
    were added to the database starting at site launch (~2016), the series naturally
    begins around then, matching the "growth since 2016" caption.
  - `dead`: cumulative dead characters over time. Reuse the `Build\Dead` builder's
    death-year data (it already ranges `LWTV_FIRST_YEAR`→now and is used by the death
    trendline), cumulative-summed. **Open implementation detail for the plan:** confirm
    the death-year source returns per-year dead counts; if a `post_date`-based cumulative
    is cleaner/consistent with the other three, use that instead. Either way the series is
    real, monotonic, and cached.
  - **Caching:** transient per subject (e.g. `stats_growth_series_shows`),
    `DAY_IN_SECONDS`, using the plugin's existing `get_transient`/`set_transient`
    wrappers. Invalidated by the same hooks that already clear the total-count transients
    (verify/extend during planning).
- **Template tag:** expose as `generate_growth_series` on `lwtv_plugin()` in
  `_components/class-statistics-optimized.php`, mirroring `generate_total_counts`.

### Presentation layer

`templates/main.php` (rewritten orchestrator) computes:

- The four totals: `generate_total_counts( 'shows' | 'characters' | 'actors' )` and
  `generate_total_dead( 'characters' )` (as today).
- The four growth series via `generate_growth_series( … )`.
- `get_top_stations( 7 )` and `get_top_nations( … )` (top nations for the share bar +
  legend; final row is an aggregated "N other nations").
- Totals for footer links: `wp_count_terms( 'lez_stations' )`, `wp_count_terms(
  'lez_country' )`.
- Derived values (see below).

Then includes these partials (all under `templates/main/`):

| Partial | Status | Purpose |
|---|---|---|
| `tabbar.php` | **new** | Pill tab bar, 8 links, Overview active |
| `overview.php` | **rewrite** | "THE DATABASE, LIVE" eyebrow + 4 metric cards |
| `bury-your-gays.php` | **new** | Red callout band + "Death Statistics →" button |
| `where-tv-lives.php` | **new** (replaces `top-stations.php`) | Network rows + progress bars |
| `around-the-world.php` | **new** (replaces `top-nations.php`) | Stacked share bar + legend |

`templates/main/top-stations.php` and `top-nations.php` are **removed** — they are only
included by `main.php`. (The standalone Stations/Nations pages render from
`templates/stations.php` / `templates/nations.php`, which are untouched.)

### JavaScript (new)

`statistics-overview.js`, enqueued only on the statistics page through the existing
`Stats_Enqueues` (which already runs solely on `is_page('statistics')`). Responsibilities:

- **Count-up:** the four metric numbers and the "643" in the band animate 0→target over
  **1100ms**, easing `easeOutCubic` (`1 − (1−t)³`).
- **Bar growth:** the "Where queer TV lives" progress-bar widths and the "Around the
  world" stacked-segment widths grow 0→final on the **same** animation driver.
- **Reduced motion:** if `prefers-reduced-motion: reduce`, skip animation and render final
  values/widths immediately.
- **Data passing:** targets and final widths come from `data-*` attributes on the rendered
  elements (server-computed). No inline `<script>` data blobs, no new query-var surface.
- Vanilla JS (no jQuery dependency needed); passes `npm run lint:js`.

### Styling

All CSS in `scss/addons/_stats.scss` (light) and `scss/partials/_colors-dark.scss` (dark),
scoped under `.statistics`. Reuse existing tokens/classes — introduce new values only for
the two intermediate nation-ramp steps, and only via `color.mix()`:

- **Cards / band / panels:** `.bg-light` surface (already dark-themed via the `.bg-light`
  override), `$lwtv-bordergrey` ring, radius `14px`, padding per handoff.
- **Card accents:** drive the icon tile, eyebrow, and sparkline stroke from the existing
  `.card-header.{shows|characters|actors|dead-characters}` classes so light and dark stay
  in sync automatically (colors already defined in both `_stats.scss` and
  `_colors-dark.scss`).
- **Progress bars:** reuse `.statistics .progress` / `.progress-bar` (teal fill in light,
  `$lwtv-ltpink` in dark — comes for free).
- **Nations ramp:** anchor `$lwtv-dkpink → $lwtv-pink → $lwtv-ltpink`; generate the two
  intermediate stops with SCSS `color.mix()` between those tokens (no pasted hex). Largest
  share = `$lwtv-dkpink`; "other nations" = `$lwtv-ltpink`.
- **Tab bar:** `.bg-light` container, `border-radius:10px`, `padding:4px`; active pill =
  white surface + subtle shadow + `$lwtv-dkgrey`; inactive = `$lwtv-medgrey`.
- **Neutral text:** body/headings `$lwtv-dkgrey` (light) / `$white` via `#main` (dark);
  meta `$lwtv-medgrey`. Do not give the metric numbers an explicit light-mode color that
  would survive into dark.
- **Type:** H1 `24/700/-0.02em`; H2 `16/600`; metric number `40/700` tabular; eyebrow
  `10/700/0.08em/uppercase`; body `14/1.5`; meta `12`. Use the theme's existing font stack.

### Icons (Symbolicons, not Lucide)

Called via `lwtv_plugin()->get_symbolicon( svg: '…', icon: 'svg-…' )`. All confirmed
present in the sprite input set:

| Use | SVG |
|---|---|
| Shows card | `tv.svg` |
| Characters card | `user.svg` |
| Actors card | `film-strip.svg` |
| Dead card + Bury Your Gays band | `skull.svg` |
| "Where queer TV lives" | `satellite-signal.svg` |
| "Around the world" | `globe.svg` |

The page header icon and jumbotron are unchanged (existing `get_stats_symbolicon('main')`
+ `.archive-subheader .jumbotron`).

---

## Layout & content

Container centered, `max-width ≈ 1120px`, matching the handoff's rhythm but via theme
utilities. Blocks top to bottom:

1. **Page header** — existing jumbotron: H1 "Statistics" + intro copy. Unchanged.
2. **Tab bar** — Overview `/statistics/` · Shows `/statistics/shows/` · Characters
   `/statistics/characters/` · Actors `/statistics/actors/` · Nations
   `/statistics/nations/` · Stations `/statistics/stations/` · Death `/statistics/death/`
   · This Year `/this-year/`. Overview active.
3. **"THE DATABASE, LIVE"** eyebrow.
4. **Metric cards** — 4-up grid (stack to 2×2 / 1-up on narrow). Each: colored eyebrow
   label, big count-up number (`tabular-nums`), icon tile, full-width inline SVG sparkline
   (`viewBox 0 0 120 26`, ~10% area fill + `1.5px` stroke in the card's accent color),
   caption. Current values are illustrative only — all read live from the DB.
5. **"Bury Your Gays" band** — red scheme (`.card-header.dead-characters` family),
   `border-left:3px` accent. Skull icon tile, eyebrow "BURY YOUR GAYS", line
   "**{dead}** characters — 1 in {N} — have been killed off." (the number counts up),
   one-line description, right-aligned "Death Statistics →" button → `/statistics/death/`.
6. **Two panels** (`1.5fr / 1fr`, stack on narrow):
   - **Where queer TV lives** — `satellite-signal` icon + H2 + sub. 7 rows: network name ·
     progress track+fill animating to `count / topCount` · right label "`{count} · {pct}%`".
     Footer "View all {stations} networks →" → `/statistics/stations/`. Data =
     `get_top_stations(7)`.
   - **Around the world** — `globe` icon + H2 + sub. Headline derived from the top nation's
     share (e.g. "Nearly 6 in 10 shows are American."). 100% stacked share bar of the
     raspberry ramp. Legend rows: color dot + nation + share%. Footer "View all {nations}
     nations →" → `/statistics/nations/`. Data = `get_top_nations()` + aggregated remainder.

## Derived values

- `pct = round( count / total * 100, 1 )`
- `1 in N = round( characters / dead )`
- Nations headline phrase derived from the top nation's share bucket (e.g. ≥55% → "Nearly
  6 in 10 …"). Exact phrasing rules finalized in the plan; must be i18n-ready.
- All strings use `__()`/`esc_html__()` etc. with the `'lwtv'` text domain.

## Data integrity & safety

- Guard every divisor (`total`, `topCount`, `dead`) against zero — fall back to `0`/empty,
  never divide by zero (consistent with the recent hardening commits).
- Escape all output; keep the query-var sanitization already in place. No new query vars.
- Do not silently drop data: if a builder returns empty, render the existing graceful
  fallback rather than a broken panel.

## Testing / verification

- `composer lint` (PHPCS, WordPress-Extra) and `composer lint-fix` for PHP.
- `npm run lint:js` and `npm run lint:css`.
- `npm run buildquick` to compile SCSS/JS assets.
- Manual verification on the local site (`lwtv.local/statistics/`): light + dark modes,
  `prefers-reduced-motion`, and narrow/stacked responsive layouts; confirm counts and
  percentages match the sub-section pages and that count-up/bar animations run once and
  land on the correct finals.

## Files touched

**New**
- `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php`
- `plugins/lwtv-plugin/php/statistics/templates/main/bury-your-gays.php`
- `plugins/lwtv-plugin/php/statistics/templates/main/where-tv-lives.php`
- `plugins/lwtv-plugin/php/statistics/templates/main/around-the-world.php`
- `plugins/lwtv-plugin/assets/js/statistics-overview.js` (path finalized in plan)

**Modified**
- `plugins/lwtv-plugin/php/statistics/templates/main.php` (orchestrator rewrite)
- `plugins/lwtv-plugin/php/statistics/templates/main/overview.php` (metric cards rewrite)
- `plugins/lwtv-plugin/php/statistics/class-stats-counter.php` (`get_growth_series`)
- `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php` (template tag)
- `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php` (enqueue the new JS)
- `plugins/lwtv-plugin/php/class-plugin.php` (`@method` docblock for the new tag)
- `scss/addons/_stats.scss` (light styles)
- `scss/partials/_colors-dark.scss` (dark styles)

**Removed**
- `plugins/lwtv-plugin/php/statistics/templates/main/top-stations.php`
- `plugins/lwtv-plugin/php/statistics/templates/main/top-nations.php`
