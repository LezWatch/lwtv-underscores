# Statistics colours

Which colour means what on the statistics pages, and how the ramps are built. Tokens come from `scss/partials/_colors.scss`. The rules live in `scss/addons/_stats.scss`. For dark mode, see [dark-mode.md](dark-mode.md).

## Card-header families

`.card-header.<type>` gives a solid family fill. The same class drives stat cards, `.lwtv-tropegap` pairs (border and eyebrow inherit from it) and waffle dots, which use `currentColor`.

| Family | Fill / border | Classes |
|---|---|---|
| Amber | `$lwtv-yellow-deep` / `-light` | `actors`, `genres`, `gender` |
| Green | `$lwtv-gq-green` / `$lwtv-green-light` | `characters`, `new-shows`, `tropes`, `actor_gender` |
| Royal blue | `$lwtv-royal-blue` / `-light` | `happy-endings`, `queer-actors`, `trans-nb-actors` |
| Crimson | `$lwtv-crimson-deep` / `-light` | `dead-characters`, `canceled-shows` |
| Plum | `$lwtv-plum` / `-light` | `bury-queers` |
| Purple | `$lwtv-purple-deep` / `-light` (text is purple-light) | `cliches`, `openly-queer` |
| Teal | `$lwtv-teal-deep` / `-light` | `shows`, `shows-onair`, `sexuality` |
| Pink | `$lwtv-pink-deep` / `-light` | `nations-new` |

Each subpage keeps to one family. Actors pages use amber throughout (donut ramp, pullstats, callouts, prolific cards), Clichés uses green, and Genres uses amber. The `.lwtv-bars--<family>` class on a section recolours its bars, matchup accents and stat-card icons. `.lwtv-bars--characters` doubles as the green stat-card variant for Clichés. `.lwtv-bars--geo` (Nations and Stations single) is pink, to match that page's donut ramp.

### Tinted pairs

`.lwtv-tropegap--tint` swaps the solid fill for a light tint with deep text. It's scoped to the tint modifier, not to the family class, because the same `card-header` classes also appear on Scores pullstats and elsewhere, and those must not change.

| Pair (page) | Tint |
|---|---|
| Dead / Happy Endings (Shows overview Trope Gap) | crimson / royal blue |
| Good / Maybe / Bad / Ploy (Trope Alignment) | green / amber / crimson / purple |
| No Cliché, Openly LGBTQ+ | purple (matching `.cliches` / `.openly-queer` solid) |
| Queer actors, Trans & NB actors | royal blue (the "good outcome" side, matching `.happy-endings`) |
| Straight/cis actors | neutral grey (the "default" side of the Casting Gap) |

### Headlines spines

The Headlines rail (`$lwtv-hl-families`, with a dark counterpart `$lwtv-hl-families-dark`) gives each subnav section one hue, so the spines double as a legend. A key in `partials/headlines.php` must have an entry in both maps, or the item renders without a spine. The spine colours don't have to match the card-header families. For example, Tropes is purple in the rail.

## Ramps

Ramps are `color.mix()` steps between a family's `-light` and `-deep` tokens at 0 / 25 / 50 / 75 / 100%.

| Ramp | Stops | Used by |
|---|---|---|
| Raspberry | `dkpink` (pink-deep), `pink`, `mid` (55% mix), `mid2` (25%), `ltpink` (pink-light) | Default for ranked legends and share bars (`$ramp-1…5`, keyed by `:nth-child`), Formats, geo donuts |
| Green (donut) | `green`, `medgreen`, `midgreen`, `ltgreen`, `palegreen`, dark to light | Characters → Gender (after grey cisgender) and Characters → Sexuality |
| Amber (donut) | `amber`, `medamber`, `midamber`, `paleamber`, `ltamber` | Actors → Roles (three buckets: amber / medamber / ltamber), Actors → Sexuality and Gender (top 4 plus Other) |

Donut ramps run darkest for the biggest segment. Donut centre numbers (`.lwtv-donut-center-num--*`) only exist for the darker stops. The palest stops fall back to the default dark grey rather than risk low contrast.

### Load waffle ramps

Load waffles go from lightest (bucket 0) to deepest (4+).

| Waffle | Bucket 0 | 1 → 4+ | Why |
|---|---|---|---|
| Trope Load, Cliché Load | palest green (ramp stop 1) | green mix steps up to green-deep | `none` is excluded first, so zero is a real reading ([data-model.md](../statistics/data-model.md#none-terms)) |
| Genre Load | `$lwtv-grey` | amber at 35 / 60 / 80 / 100% alpha | no `none` term; zero means untagged |
| Intersection Load | `$lwtv-grey` | royal blue at 35 / 60 / 80 / 100% alpha | as Genre Load |

## Same statistic, same colours

The queer-vs-not character split is `$lwtv-pink` / `$lwtv-aro-grey` wherever it appears: the Casting Gap on the Characters overview and Characters → Queer IRL. Reuse those colours for any new view of that figure.

## Worth It grid

The Worth It hundred-square grid uses yes = green, meh = yellow, no = red, tbd = grey-medium. The squares are full circles (`border-radius: 50%` on `.lwtv-wi-square`). Straight aligned gutters between saturated squares cause the Hermann grid illusion (ghost dots at the intersections), and rounded corners only soften it. Dots remove the straight edges and match the other waffles.
