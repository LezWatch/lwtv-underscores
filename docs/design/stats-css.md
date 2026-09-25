# Statistics CSS

Layout and cascade rules for `scss/addons/_stats.scss`, which styles `/statistics/` and This Year. The dark-mode counterparts are in `scss/partials/_colors-dark.scss` (see [dark-mode.md](dark-mode.md)). Colour choices are in [colors.md](colors.md).

## Page grids

The Load and Pairings pages (see [docs/statistics/pages.md](../statistics/pages.md#load-and-pairings-pages)) all use the same uneven two-column grid:

```scss
grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
```

| Page | Grid | Main (2fr) | Side (1fr) |
|---|---|---|---|
| Tropes | `.lwtv-tropes-columns` | Trope Load, Mixed Alignment | Common Pairings |
| Genres | `.lwtv-genres-columns` | Genre Load | Common Pairings |
| Intersectionality | `.lwtv-inter-columns` | Intersection Load, Single vs Multiple | Common Pairings |
| Clichés | `.lwtv-cliches-columns` | Cliché Load | Common Pairings |

- The main column comes first in source order, so it leads when the grid collapses to one column at 900px or narrower.
- The ranked Breakdown sits outside the grid in `.lwtv-{page}-breakdown-wrap`, full width, with `column-count: 2` (1 at 900px or narrower) and `break-inside: avoid` on rows.
- Load rows (`.lwtv-{x}load-row`) are a wrapping flex row: a 320px figure plus a legend. On Tropes, Genres and Clichés the legend is capped (`max-width: 280px`), because uncapped growth in the wide column leaves a gap between name and percentage. Intersection Load's legend has no cap.
- The most-loaded spotlight (`.lwtv-{x}load-poster`) is a footer strip under the row, not a third flex item. Waffle, legend and poster won't fit on one line even in the main column without an awkward wrap.
- Common Pairings (`.lwtv-matchup-grid`) is kept dense because it lives in the narrow column.

## Card nesting

Never draw a bordered card inside another bordered card.

- `.lwtv-panel` is the outer card, with no background colour of its own, so it's white in light mode and the page background in dark mode.
- A section that already sits in a `.lwtv-panel` borrows a partial's *markup classes* without its wrapper. Mixed Alignment (`.lwtv-mixed-alignment-row`) and the Casting Gap (`.lwtv-castinggap-row`) use the donut or legend classes from `partials/donut.php` directly, and so need their own copy of the figure sizing.
- `.lwtv-donut-mini` is the compact donut without border, background or padding, so several can share one panel (Unknown Actor's trio).
- `.lwtv-decade-row` is one outer card holding every decade tile. Each tile keeps its own `.lwtv-donut-card--compact` tint.
- Queer IRL (`.lwtv-qirl-card`) and Unknown Actor (`.lwtv-unknown-card`) get top-level cards of their own, because they aren't nested in a panel the way the Casting Gap is.

## Class-name collisions

`.lwtv-decade-tile` is used by two unrelated components: the Genre and Intersections "Mix by Decade" ranked-bar tiles (in `.lwtv-decade-tile-grid`), and the donut tiles inside `.lwtv-decade-row`. The later declaration carries `stylelint-disable-next-line no-duplicate-selectors`. `.lwtv-decade-row .lwtv-decade-tile` then cancels the bordered box, which would otherwise duplicate the row's outer card. When adding a component, grep for the class name before reusing it. Renaming one of the two would remove the override.

## Cascade order

Stylelint's `no-descending-specificity` is on. Put a more specific variant *after* its base rule. For example, `a.lwtv-matchup-row` comes after `.lwtv-matchup-pair`, so specificity only increases down the file.

## Legend specificity

Load-waffle legend dots are keyed to the bucket class (`.lwtv-legend-dot--b0` … `--b4`), not `:nth-child`, because the template skips empty buckets and that would shift row positions. The page-wide raspberry ramp is set with `.lwtv-legend-row:nth-child(n) .lwtv-legend-dot`. To beat it, a per-page bucket rule needs four classes:

```scss
.lwtv-genreload .lwtv-legend-row .lwtv-legend-dot.lwtv-legend-dot--b0 { … }
```

Genre Load, Intersection Load and Cliché Load use this form. Dark-mode overrides must match the same selector.

## Jump-bar offset

The This Year lists (Shows On Air, New Shows, Canceled Shows, rendered by `this-year/templates/partials/show-block.php`) have a sticky jump bar, `.lwtv-ty-sb-jump`, at `top: 50px` under the fixed site header. Its height changes with viewport width: the A–Z chips wrap onto one to three rows. So a fixed `scroll-margin-top` can't clear it.

- `plugins/lwtv-plugin/assets/js/statistics-overview.js` (`setJumpOffsets()`) measures each visible bar and sets `--lwtv-sb-offset` on its `.tab-pane` to the bar's `top` + height + 8px. It re-runs on `resize` and on Bootstrap's `shown.bs.tab`, because a bar in a hidden pane has no height.
- `.lwtv-ty-sb-row` uses `scroll-margin-top: var(--lwtv-sb-offset, 184px)`. The fallback is deliberately large for the no-JS case.

If you change the bar's `top`, padding or the header height, check both sides.
