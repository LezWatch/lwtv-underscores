# Statistics — Nations & Stations Overview fact-sheet rebuild

**Date:** 2026-07-29
**Branch:** `feat/stats-mobile`
**Design source:** `design_handoff_nations_overview_infographic` (Claude Design), build option `1d`
("Fact sheet — tiles quadrant, composition bars, and three headline facts").

## Goal

Rebuild the single-nation and single-station **Overview** (`_all` view) on `/statistics/nations/`
and `/statistics/stations/` from a signpost into a summary. Today the Overview is four vibrant
metric tiles, an Average-Show-Score card with an on-air meter, and an intro paragraph that ends
"use the tabs to break its catalogue down by…". That intro is redundant: it tells the reader to go
look at the other five tabs.

The fact sheet *previews* those tabs instead. Above the fold the reader sees the four headline
counts (tiles), a **Composition** panel that shows the sexuality / gender / format / on-air /
alive-dead splits as five 100% bars, and a bottom band of **three headline facts**. Nothing new is
queried for Composition — it re-presents counts the other tabs already compute.

Nations is the handoff subject; **Stations mirrors it** as closely as the domain allows (the two
templates are already near-identical). Keeping the two views consistent is an explicit goal — if the
tiles or bars change on one, they change on both.

## Non-goals / constraints

- **No new interactive behaviour.** No click handlers, no filtering, no bar animation. Composition
  bars are a preview, **not** links (a 14px segment is not a hit target). The existing count-up may
  apply to the four tile numbers for consistency; bars and headline facts render at final value.
  `prefers-reduced-motion: reduce` → final values immediately.
- **Zero net-new palette values and no new fonts.** Every colour is an existing `$lwtv-*` token;
  every font size is `rem`; nothing renders below `0.75rem` except the existing `.lwtv-toll-eyebrow`
  (`0.7rem`), which is matched, not changed.
- **Six symbolicons, all already in the sprite:** `globe.svg` (nations) / `tv.svg` (stations),
  `tv.svg`, `satellite-signal.svg`, `man-woman.svg`, `skull.svg`, `fireworks.svg`. Pulled via
  `lwtv_plugin()->get_symbolicon()` as today.
- **Only the `_all` case of each `single.php` changes.** Other views (sexuality, gender, tropes,
  formats, on-air), the nation/station picker, the reset link, and the leaderboard are untouched —
  except the shared sub-nav wrap (below), which is a deliberate site-wide change.
- **Reuse, don't re-author.** `.lwtv-toll-tile--{teal,amber,green,rose}`, `.lwtv-toll-top`,
  `.lwtv-toll-eyebrow`, `.lwtv-toll-num`, `.lwtv-toll-chip`, `.lwtv-trend-callout`,
  `.lwtv-stats-eyebrow`, `.lwtv-nation-profile-chip`, and the `$vibrant-toll` map already exist and
  are used as-is.
- **The old `.lwtv-metric-card` two-score card is removed from this view.** The class is shared;
  `all.php` still uses it, so the class stays — only this view stops emitting it.

## Architecture — build → format → templates

Follows the repo's `build/ → format/ → templates/` contract and mirrors the three prior This Year
rebuilds: pure transform under `build/`, unit-tested in the no-WP PHPUnit harness first; thin
template; theme SCSS with `colors.$lwtv-*` tokens only. Live WordPress glue (queries, meta reads,
`get_symbolicon`, permalinks, i18n) stays in the template.

### 1. Pure transform — `LWTV\Statistics\Build\Overview_Factsheet`

New file `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php`. Pure array-in /
array-out; no WordPress globals, `$wpdb`, meta reads, output, or i18n. Unit-tested first via
`tests/unit/Statistics/OverviewFactsheetTest.php`.

**Why a transform:** the segment-folding and thin-data collapse rules are the only non-trivial logic
in the view, and they carry the correctness traps (fixed segment order, the "never a single 100%
segment" rule, the < 5 chars / < 3 shows collapses). Isolating them into tested code is exactly what
`build/` is for. The methods are shared verbatim by both the nation and station templates.

```
composition_bars( array $splits, int $shows, int $onair, int $chars, int $dead ): array
```
Returns an ordered list of bar view-models. `$splits` carries the already-counted
`sexuality`, `gender`, `format` breakdowns (each a `[ ['label'=>, 'count'=>], … ]` list — the same
shape the donut tabs consume). For each bar:

- **Segments are folded to the fixed order `teal → amber → green → rose → grey`**, so the same
  colour means "the biggest category" in every bar. Take the top 4 by count into teal/amber/green/
  rose; fold the remainder into a single **grey tail**. The tail segment is emitted **only when it
  is non-zero** — bars that are complete by construction (gender, format, on-air, alive/dead) never
  carry an always-zero "Other".
- Bars 1–3 (sexuality, gender, format) and bar 5 (alive/dead) lead with the majority. **Bar 4
  (shows total vs on air) leads with the amber on-air slice** — deliberate, so the small on-air
  share reads as a left-filled meter matching the on-air meter idiom. This ordering is a property of
  the returned model, asserted in tests.
- Each segment carries `flex` (its raw count — the template renders `flex:<count>`, never a width or
  percentage, so the bar is always exactly 100% with no rounding fixes) plus the label + count used
  to build the right-hand summary and the `aria-label`.
- **Thin-data collapse** returns a `mode` flag per bar: below **5 characters**, bars 1/2/5 return
  `mode => 'text'` with the raw values as a phrase instead of a track; below **3 shows**, bars 3/4
  do the same. A track is never returned with a single 100% segment.

```
headline_facts( array $best_show, int $chars, int $shows, ?float $global_avg, int $dead ): array
```
Returns the three fact view-models:
1. **Best show** — `{ score, title, url }` from `$best_show`; caption "Best-scoring
   {adjective} show — <a>{title}</a>".
2. **Cast density** — `round($chars / $shows, 1)`, caption "Queer characters per show"; append
   ", against a global average of {global_avg}" **only when `$global_avg` is non-null** (drop the
   clause, never hardcode, if the average is unavailable).
3. **Death rate** — `round($dead / $chars * 100, 1)`%, caption "Of {adjective} queer characters
   have died on screen".

```
narrative( ?int $rank, ?int $first_year, int $shows ): string
```
Unified for both views (and simpler than the handoff's nation-only superlative, which is dropped for
being hard to derive reliably and inconsistent across the two views):

- With a rank and a first year: `"{Ordinal} busiest {noun} on the site. Steady output since
  {firstYear}."` (`{noun}` = "nation" / "network").
- Fallback for small entities (fewer than 3 shows, or missing rank/year): `"{N} tracked shows since
  {firstYear}."`, or `"{N} tracked shows."` if the year is also missing.

The **best-year** derivation (peak of the per-year on-air series, most-recent year on a tie, skipped
when the peak is 1 or the entity has fewer than 3 shows) already lives in the `_on-air` case of each
template. It is lifted into a small helper on this class so the Overview and the on-air tab share one
implementation rather than duplicating it.

### 2. Live glue in `single.php` (both nations and stations)

The `_all` case gathers the inputs, calls the transform, and renders. It:

- Computes **rank** by reproducing the leaderboard sort (`uasort` on `$all_nations_data` /
  `$all_stations_data` by `count` desc — the same sort `all.php` already uses) and finding the
  current slug's 1-based position.
- Reads the **sexuality / gender / format splits** via the existing
  `generate_nation_statistics()` / `generate_station_statistics()` calls (the same calls the donut
  tabs make).
- Reads **first year** via `Build_Taxonomy_Optimized::get_bulk_first_years( 'lez_country' |
  'lez_stations', [ $slug ] )`.
- Reads the **global average** via `generate_total_counts('characters') /
  generate_total_counts('shows')` — one site-wide pair, cacheable, computed once.
- Reads the **best-scoring show** via the new `Build_Taxonomy_Optimized` method (below).

### 3. New backend derivation — best-scoring show

The one genuinely new query. `get_bulk_show_counts()` already aggregates per term but returns
`AVG(score)` only — it cannot name the top show. Add:

```
public function get_bulk_top_shows( string $taxonomy, array $terms ): array
```

Returns `term_slug => [ 'id' => int, 'score' => float ]` for the highest-scoring published show in
each term (`lezshows_the_score` meta, `MAX`). Cached with a `DAY_IN_SECONDS` transient like its
sibling. The template resolves `id` → `get_the_title()` / `get_permalink()` at render (WP glue, not
in the transform). On a tie, any one top show is acceptable — the score is the headline, the title
is the link. If the query yields nothing (thin term), the handoff's alternate facts (biggest cast,
newest premiere) are **not** implemented now; fact 1 is simply omitted and the band renders 2-up.
The 3-up grid tolerates a missing cell, and a thin term already triggers the other collapses.

### 4. Structural change — the shared profile header

Today the profile header (`.lwtv-nation-profile--vibrant`: chip + eyebrow + name + figs + intro
paragraph) renders **before** the `switch`, so every view shows it. The fact sheet has its own
**masthead** (chip + eyebrow + name on the left, narrative on the right, 3px teal bottom rule) and
its counts live in the tiles, so the shared header would be redundant on the Overview.

Resolution: render the shared profile header only for **non-`_all`** views; the `_all` case draws
the new masthead. Every other tab keeps today's header byte-for-byte.

## Layout (per handoff)

Container: existing statistics shell (`max-width:1120px`, padding `32px 24px 56px`). Four blocks
stacked with `margin-bottom:20px`:

1. **Masthead** — chip + `NATION PROFILE`/`STATION PROFILE` eyebrow + name (left), narrative (right),
   `padding-bottom:16px`, 3px `$lwtv-teal-deep` bottom rule.
2/3. **Row** — `grid-template-columns:400px 1fr; gap:20px`. Left: 2×2 tile block
   (`.lwtv-toll--2x2`, `align-content:start`) + the Best Year callout spanning both columns
   (`grid-column:1 / -1`, reusing `.lwtv-trend-callout`). Right: Composition panel (bordered, no
   fill, like `.lwtv-ty-group-card`). Collapses to `1fr` below 992px.
4. **Headline facts** — `grid-template-columns:repeat(3,1fr); gap:20px; padding-top:18px`, 1px top
   rule. Collapses to `1fr` below 768px.

Tiles, Composition bars, and facts follow the handoff's colour/type/spacing tables exactly.

## SCSS (`scss/addons/_stats.scss` + `scss/partials/_colors-dark.scss`)

Add:

- `.lwtv-toll--2x2` — `grid-template-columns:repeat(2,1fr)`; tile `height:118px`, `padding:15px 16px`,
  `justify-content:space-between`; chip 34px (steps down from the 38px four-across chip). **No
  `max-width:575px` 1-up collapse** — tiles stay 2×2 to 375px per the handoff's verified note.
- Fact-sheet masthead classes (flex, `align-items:flex-end`, wrap; 3px teal rule).
- Composition panel + bar classes — panel `padding:18px 20px; gap:16px; border:1px
  $lwtv-grey-border; border-radius:14px`; row head `display:flex; justify-content:space-between;
  flex-wrap:wrap; gap:2px 12px; font-size:0.75rem`; track `display:flex; gap:2px; height:14px;
  border-radius:4px; overflow:hidden`; the 1px rule between bar 3 and bar 4.
- Headline-facts grid + number (`2.25rem`/700 Oswald, tabular-nums) + `/ 100` suffix
  (`1.125rem`/500) + caption (`0.812rem`).
- Accent-text variables `--teal-fg` / `--green-fg` / `--rose-fg` — deep tokens in light, dark locals
  in dark. Used only by the `PROFILE` eyebrow and the three fact numbers, so the tile fills (which
  reuse the same deep tokens) stay identical across modes and are **not** redefined per mode.

Dark mode (`_colors-dark.scss`, `#masthead`-nested per the dark-switcher pattern): panel border + the
internal 1px rule → `rgba($white,0.12)`; grey tail segment → `rgba($white,0.22)` (the
`.lwtv-donut-seg--bordergrey` dark neutral); fact numbers → `$lwtv-blue-light` / `$lwtv-green-light` /
`$lwtv-red-light`; `PROFILE` eyebrow → `$lwtv-blue-light`; links → dark `$link-color`. Tile fills and
numerals, the 3px teal masthead rule, and `.lwtv-nation-profile-chip` are unchanged (they already
read correctly on the dark surface). Verify both modes at ≥ AA.

## Shared sub-nav wrap (site-wide)

`.lwtv-stats-subnav` changes from `overflow-x:auto` (scrolls with no affordance) to
`flex-wrap:wrap; gap:0 2px`, with items keeping `white-space:nowrap`. The active item's
`margin-bottom:-1px` (which merges its underline with the container rule on a single row) is removed,
since it is only correct on the last row and the underline reads the same on the item itself.

This affects **every** statistics page. Before shipping, verify the widest sub-nav on the site
(Shows has the most items) wraps cleanly at 375px and desktop.

## Accessibility

- Colour alone carries the Composition segments, so the right-hand summary line is the text
  alternative — every bar has one, and each track carries `role="img"` with an `aria-label`
  repeating the full breakdown in words.
- The only focusable things in the block are the sub-nav, picker, reset link, and the single show
  link in fact 1; all keep the theme's default focus ring. Link hover → `$link-hover-color`.

## Responsive (per handoff, verified at 375px)

| Breakpoint | Behaviour |
|---|---|
| ≥ 992px | 400px tiles + `1fr` Composition; 3-up facts. |
| 768–991px | Row stacks to `1fr`; tiles stay 2×2 full width; facts stay 3-up. |
| 576–767px | Facts → `1fr`; masthead wraps, narrative drops below the name. |
| < 576px | Tiles stay 2×2; Composition row heads wrap (summary drops below label). |

## Testing

`tests/unit/Statistics/OverviewFactsheetTest.php` (pure, no WP bootstrap), written first:

- Segment folding: top-4 into teal/amber/green/rose, remainder into a single grey tail; tail omitted
  when zero; fixed colour order regardless of input order.
- Bar 4 leads with the on-air (amber) slice; bars 1–3/5 lead with the majority.
- `flex` values are raw counts (sum to the total; no percentages in the model).
- Thin-data: < 5 chars → bars 1/2/5 `mode => 'text'`; < 3 shows → bars 3/4 `mode => 'text'`; never a
  single 100% segment.
- Narrative: rank + first-year sentence; small-entity fallback; missing-year fallback; ordinal
  formatting.
- Headline facts: cast-density rounding; global-average clause dropped when null; death-rate
  rounding; division-by-zero guards (0 shows, 0 chars).
- Best-year helper: peak selection, most-recent-on-tie, skip when peak is 1 or fewer than 3 shows.

Live WP glue (rank sort, `get_bulk_top_shows`, `get_bulk_first_years`, `generate_total_counts`,
permalinks) is verified against the running site (`lwtv.local`), not unit-tested.

## Files

- `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php` — **new** pure transform.
- `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php` — **new** method
  `get_bulk_top_shows()`.
- `plugins/lwtv-plugin/php/statistics/templates/nations/single.php` — rebuild the `_all` case; gate
  the shared header to non-`_all`.
- `plugins/lwtv-plugin/php/statistics/templates/stations/single.php` — mirror the same.
- `scss/addons/_stats.scss` — `.lwtv-toll--2x2`, masthead, Composition, headline-facts, sub-nav wrap,
  accent-text variables.
- `scss/partials/_colors-dark.scss` — dark overrides listed above.
- `tests/unit/Statistics/OverviewFactsheetTest.php` — **new** unit suite.

## Risks

- **New best-show query** is the only added DB cost — mitigated by the day-long transient, computed
  with the rest of the stats cache.
- **Sub-nav wrap is a shared change** — mitigated by the Shows-page check before shipping.
- **Thin-data nations/stations** (Austria-scale) — handled explicitly by the transform's collapse
  rules and the best-year/best-show skips; the band tolerates a missing fact cell.
- **Stations aren't nations** — narrative noun and best-show adjective differ; everything else is
  identical. The unified narrative formula avoids per-domain superlative logic that could rot.
