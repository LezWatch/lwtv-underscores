# Dark mode

How dark-mode overrides work in the theme SCSS, and the gotchas that matter most on the statistics pages.

## How the dark block works

All dark overrides are in `scss/partials/_colors-dark.scss`, inside `@include mixins.color-mode(dark) { … }`. With `$color-mode-type: data` (set in `scss/partials/_mixins.scss`), the mixin wraps its content in `[data-bs-theme="dark"]`.

At the top of that block, a handful of variables are **redeclared locally** with dark-friendly values: `$lwtv-blue-light`, `$lwtv-yellow-light`, `$lwtv-green-light`, `$lwtv-red-light`, `$lwtv-grey-medium`, `$lwtv-grey-deep`, `$lwtv-svg` and the `$link-*` colours.

## Module-qualified variables are not swapped

Those local declarations are block-scoped Sass variables. They don't change the `colors` module. So:

- A bare `$lwtv-yellow-light` inside the dark block resolves to the dark value (`#f1c40f`).
- `colors.$lwtv-yellow-light` always resolves to the light-mode token, even inside the dark block.

The dark mixin never overrides anything automatically. Every rule in `scss/addons/_stats.scss` that uses a `colors.$lwtv-*` token keeps its light-mode colour in dark mode unless `_colors-dark.scss` has an explicit override with the same selector. When you add a stats accent, add its dark counterpart at the same time. Inside the dark block, use the bare local variable if you want the swapped value, and `colors.$…` if you want the base token.

## Bucket-0 visibility

Ramps whose lightest stop is bucket 0 (Trope Load and Cliché Load's palest green, Genre Load and Intersection Load's grey) almost disappear on the dark page background. The fix:

- Legend swatches get `outline: 1px solid rgba(colors.$white, 0.15); outline-offset: -1px;`.
- SVG waffle dots get `stroke: rgba(colors.$white, 0.15); stroke-width: 1px;`, since SVG has no outline.
- Selectors must match the light-mode bucket-class selectors (see [stats-css.md](stats-css.md#legend-specificity)).

The raspberry ramp gets the same treatment on its lightest stop, `.lwtv-share-seg:nth-child(5)`.

## Dark stand-ins

Where a light-mode pair drops below 3:1 on the dark panel, swap in a brighter token rather than adding a new hue:

- **Casting Gap waffle** (`.lwtv-castinggap-waffle`): the queer dot and `.lwtv-donut-seg--pink` become `colors.$lwtv-pink-light`, and the neutral side becomes translucent white.
- **Trope Alignment tints**: good and maybe use the bright local `$lwtv-green-light` / `$lwtv-yellow-light`. Bad and ploy have no local override and stay on the module tokens.
- **Stat cards** (`.lwtv-statcard`): lifted to `$lwtv-grey-medium` with a white-alpha border, and the icon and number use the local `$lwtv-yellow-light`. Family variants (`.lwtv-bars--characters`, `--geo`, actors) each need their own dark rule, or they fall back to that amber default.
- **Headlines spines**: `$lwtv-hl-families-dark` mirrors `$lwtv-hl-families`. Add new keys to both.

Check every new colour in both modes and aim for WCAG AA (see [accessibility.md](accessibility.md)).
