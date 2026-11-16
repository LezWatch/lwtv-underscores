# theme.json

We are a classic theme. `theme.json` exists to describe the design tokens we already have to the block editor, and to style the text-input states that WordPress 7.2 added. It does not take over front-end styling. Bootstrap and our SCSS still own that.

## Source of truth

**SCSS is canonical, and `theme.json` mirrors it** (option B in issue #744). When you change a token in SCSS, change `theme.json` to match. `tests/unit/Theme/ThemeJsonTokensTest.php` fails if they drift:

- Every palette slug `lwtv-*` must match the `$lwtv-*` hex in `scss/partials/_colors.scss` (and `white`/`black` match `$white`/`$black`).
- Every font size must be in `rem` and must be one of the sizes in `scss/partials/_fonts.scss`.
- Every text-input state must use a `--lwtv-input-*` token defined in `scss/addons/_forms.scss`, with a fallback that matches the SCSS value.

## Token mapping

### Colors (`settings.color.palette`)

The slug is the SCSS variable name without the `$`, so `lwtv-pink` is `colors.$lwtv-pink`, which WordPress exposes as `--wp--preset--color--lwtv-pink`. The palette is a brand subset: pink, deep, and light pink; purple and light purple; plum and light plum; royal blue, blue, and light blue; teal; green and light green; light yellow; red and light red; the four greys (light, plain, muted, deep); white; and black. The pride-flag and chart colors stay out of it on purpose.

`custom`, `customGradient`, and `defaultPalette` are `false`, so editors only see brand colors. Core's default palette CSS (`.has-pale-pink-background-color` and so on) is still printed, so older posts that use those classes keep their colors.

Core's default **gradients** are still offered. The issue didn't ask for them to go. Set `defaultGradients: false` if we want them gone. Only one live post uses one (`blush-light-purple`), and its CSS keeps printing either way.

### Typography (`settings.typography`)

| Slug | Size | SCSS |
| --- | --- | --- |
| `x-small` | 0.75rem | `font-12` |
| `small` | 0.8125rem | `font-13` |
| `medium` | 1rem | base font |
| `large` | 1.25rem | `font-20` / `h5` |
| `x-large` | 1.5rem | `font-24` / `h4` |
| `xx-large` | 2rem | `font-32` / `h2` |

`small` is deliberately 13px. That's what core's default `small` was, and existing posts use `has-small-font-size`. No live content uses the other core slugs (`medium`, `large`, `x-large`), so redefining them changes nothing on the front end.

Font families are `open-sans` and `oswald`. The theme already loads both fonts, so `theme.json` declares no `fontFace`.

`customFontSize` and `defaultFontSizes` are `false`.

### Layout (`settings.layout`)

`contentSize` is `825px`, matching the editor width in `style-editor.scss`. `wideSize` is `1200px`, Bootstrap 6's `xl` container. No live content uses a constrained group, so these only affect the editor.

`appearanceTools` is `false`. Turning it on would offer block supports (border, spacing, and so on) that we haven't designed for.

### Text-input states (`styles.elements.textInput`)

| State | Property | Token | Light | Dark |
| --- | --- | --- | --- | --- |
| `:focus-visible` | outline (2px solid, 2px offset) | `--lwtv-input-focus-ring` | `$lwtv-pink-deep`, 7.07:1 on white | `$lwtv-pink-light`, 8.77:1 on #333, 4.37:1 on grey-medium |
| `:user-invalid` | border color | `--lwtv-input-invalid` | `$lwtv-red`, 5.44:1 on the white field | same; the fields that can be invalid keep white surfaces |
| `::placeholder` | color | `--lwtv-input-placeholder` | `$lwtv-grey-muted` (#666), 5.74:1 on white | 85% of the field's own text color, ≥ 4.6:1 on every dark field |

Light values are in `scss/addons/_forms.scss` (`:root`) and dark values are in `scss/partials/_colors-dark.scss` (`&:root`, which outranks the later light block). `theme.json` has no dark-mode variant, so it only references the custom properties.

Before this change, the placeholder was `#868e96` (3.32:1, which fails AA), and the sidebar and FacetWP search fields had **no visible focus indicator** (`outline: none`).

### Buttons (`styles.elements.button`)

Every value is `revert-layer`: background, text color, border radius, border width, and padding.

Core prints its default button element unlayered as `:root :where(.wp-element-button, .wp-block-button__link)` (a grey `#32373c` pill). It's backed by block-library's `:where(.wp-block-button__link) { border-radius: 9999px }`. Both beat our pink button rules in `scss/addons/_bootstrap.scss`, so button blocks rendered as a grey pill. In dark mode that pill nearly disappears against the #333 page. This dates from the move to `@layer components`, before this file existed.

`revert-layer` in an unlayered rule throws away every unlayered value for that property and uses whatever our layers decide. So SCSS stays the only place button styles are defined, and the hover, focus, and dark-mode rules there work too. White on pink is 4.6:1 at rest, and the hover inverts to the same pair. Buttons an editor colored keep their `has-*-color` classes. Font, line height, and `text-decoration: none` still come from core, since our SCSS doesn't set them for these buttons.

Don't replace these with copied values: `ThemeJsonTokensTest` checks they stay `revert-layer`.

## Why no base `textInput` styles

WordPress prints global styles **unlayered**. Everything in `style.css` is inside `@layer components`, and unlayered CSS beats layered CSS whatever the specificity. So a base `textInput` border, background, or padding would silently override Bootstrap's `.form-control` and `_forms.scss` on every field on the site. Only the three states above are set. `:required`, `:user-valid`, and `:focus` are left alone: we have no designs for them, and `:focus` would fight the per-component focus styles.

Gravity Forms still wins on its own fields: its unlayered CSS has higher specificity than core's `:root :where(…)`.

## What a theme.json switches on for a classic theme

`wp_theme_has_theme_json()` gates several core behaviors. Here is what changed when we added the file, checked against WordPress 7.2-beta4 with the Gutenberg plugin:

- **Aligned images lose their wrapper.** `wp_restore_image_outer_container()` stops running, so `div.wp-block-image > figure.alignright` becomes `figure.wp-block-image.alignright`. Core's unlayered `.wp-block-image { margin: 0 0 1em }` then lands on the figure. `scss/addons/_blocks-compat.scss` (loaded *outside* the layer) and a rule in `style-editor.scss` restore the old margins. About 81 posts use the unwrapped markup. The other ~190 were saved with the wrapper and are unaffected. Below 576px the same file uses `float` and `margin: revert-layer`, so `_responsive.scss` decides: no float and `2em 0` spacing. Before, core's float and 0.5em spacing won there. The figure is full width either way, so it only looks different in the spacing.
- **Groups lose the restored inner container.** `wp_restore_group_inner_container()` stops adding `.wp-block-group__inner-container` to groups that were saved without one. We have no CSS for that class, and the measured rendering doesn't change.
- **`classic-theme-styles` stops loading**, and core's default `styles.elements.button` (grey `#32373c` pill) takes over from it in global styles. See [Buttons](#buttons-styleselementsbutton).
- **`block-templates` is turned on** by `wp_enable_block_templates()`. `Features::after_setup_theme()` removes it again. That hook used to be registered on `init`, after `after_setup_theme` had already fired, so it never ran. It's now registered in `Features::init()`.
- **The editor stops loading `wp-editor-classic-layout-styles` and the reset styles.** Block margins in the editor go from 28px/28px to the theme's own margins (0/16px), which is closer to the front end (0/19.2px).
- **Layout CSS is printed**, for example `.is-layout-flow > .alignleft` and `.is-layout-constrained > …`. Nothing on our pages matched it in testing.

## How this was verified

The front end was checked by comparing computed styles for every element, before and after. The pages were the home page, a show, two characters, an actor, Statistics (Shows and Characters), This Year, Contact, Submissions, About, the shows and characters archives, search, and several posts with images and groups. Each was checked at 1280px and 375px in light and dark mode. The only remaining differences were the placeholder color and noise that also shows up when the baseline is compared with itself (random home-page content, animated stat bars). The editor was compared the same way inside the canvas iframe.
