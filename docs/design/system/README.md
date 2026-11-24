Use this system for LezWatch.TV, a database and review site for lesbian, bisexual and queer women on TV. It mirrors the `lwtv-underscores` WordPress theme (`theme.json` and `scss/partials/_colors.scss`).

## Color
- Pink leads. Use `pink` for primary buttons and accents, `pink-deep` for hover and pressed, `pink-light` for highlighted rows. White text on `pink` is 4.6:1.
- Use `purple` as the second brand color and for link hover (`link-hover`). Use `plum` and `plum-deep` for dark bands and the footer.
- Set body copy in `ink` on `surface` or `surface-subtle`. Set secondary text in `ink-muted`. Never use `grey-medium` for text; it is 2.9:1 on white.
- Set links in `link`, hover and focus in `link-hover`. In the dark theme they become `pink-light` and `purple-light`, because `pink` is only 2.8:1 on the dark surface.
- Status colors are `green`, `yellow-light`, `red` and `blue`. Always add a word or icon; never rely on hue alone.
- Pride-flag colors (`flag-bi-*`, `flag-trans-*`, `flag-pan-*`, `flag-aro-*`, `flag-ace-purple`, `flag-poly-*`, `flag-gq-*`) mark identity tags and badges only. Always show the label as text beside the color, and never set text in a flag color.

## Type
- Headings in Oswald (`heading-xl`, `heading-lg`, `heading-md`) at weight 400, line height 1.3.
- Body in Open Sans on a 16px base (`body`), line height 1.5. Use `small` for meta and `caption` for badges.
- Georgia is for pull quotes only (`serif` family).
- Both fonts load from Google Fonts. No font files are stored here.

## Shape
- Default corners are `radius-sm`. Use `radius-md` or `radius-lg` for cards and `radius-pill` for tags and badges.
- Separate surfaces with `surface-border` hairlines, not heavy shadows.

## Themes
- Light is the default. Dark uses `surface` #333, `ink` #f3f3f3 and the saturated `blue-light`, `green-light`, `yellow-light` and `red-light` values for stat tiles.

## Components
- `Button`, `Badge`, `Card`, `Field`, `Alert` and `ScoreMeter` are in the bundle (`window.LezWatch`). Read each component's README before use.
- Use `Button` solid for the one primary action and outline for secondary ones. Use `Badge` for short tags, never as the only carrier of meaning.
- Show scores with `ScoreMeter`; show N/A when a show has no score.

## Logo
- Use `assets/Logos/lwtv-toaster.svg` as the site mark. Never redraw or recolor it.
