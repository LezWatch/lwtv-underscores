# Statistics pages

How the `/statistics/` views are built: the build layer's contract, which `Build\*` classes feed which view, and the layout rules for pages that share a pattern.

Templates live in `plugins/lwtv-plugin/php/statistics/templates/`, build classes in `plugins/lwtv-plugin/php/statistics/build/`. For the storage rules the queries follow, see [data-model.md](data-model.md). For rounding and adaptive copy, see [presentation-rules.md](presentation-rules.md). For CSS, see [docs/design/stats-css.md](../design/stats-css.md).

## Build layer

The project-wide rule is build → format → templates (see the Architecture section of `.claude/CLAUDE.md`). In `statistics/build/` there are two kinds of class side by side:

- **Pure transforms.** Arrays and scalars in, arrays out. They make no WordPress calls (no `$wpdb`, no meta or term reads, no permalinks, no `__()`), so they're unit-tested without a WordPress runtime under `tests/unit/Statistics/`. All wording and i18n stay in the template; the transform only reports shape and numbers. New display logic goes here, test-first. See [docs/testing.md](../testing.md).
- **WP glue.** Query classes that read the database (usually cached in a transient) and hand a tally to a pure transform. They're verified against the running site, not unit-tested.

A pure transform's header says so in one line and points here, so the contract isn't repeated in every class.

## Build class index

| Class | Kind | Feeds |
|---|---|---|
| `Catalog_Depth` | glue | Shows overview (season/episode totals, plus a coverage count that gates whether they're shown) |
| `Character_Actor_Leaders` | glue | Characters → Most (most recast) |
| `Character_Death_Leaders` | glue | Characters → Most (most resurrected), Death → Characters |
| `Character_Identity_Trend` | glue | Characters → Gender / Sexuality (by-decade, Firsts) |
| `Character_Identity_Decade_Buckets` | pure | via `Character_Identity_Trend`, `Death_Trend` |
| `Character_Longevity_Leaders` | glue | Characters → Most (longest running) |
| `Character_Queer_Cast_Firsts` | glue | Characters → Queer IRL, Actors → Unknown Actor |
| `Character_Show_Leaders` | glue | Characters → Most (most shows) |
| `Cliche_Leaders` | glue | Characters → Most (most clichés), Characters overview |
| `Death_Trend` | glue | Death → Characters (deaths by decade) |
| `Donut_Segments` | pure | `partials/taxonomy-facet.php` (Nations / Stations single) |
| `Format_Trend` / `Format_Decade_Buckets` | glue / pure | Shows → Formats (Format Mix by Decade) |
| `Genre_Trend` / `Genre_Decade_Buckets` | glue / pure | Shows → Genres (Genre Mix by Decade) |
| `Intersection_Trend` | glue | Shows → Intersectionality (by decade, through `Genre_Decade_Buckets`) |
| `Intersection_Pairs` | pure | Common Pairings on Genres, Tropes, Intersectionality, Clichés |
| `Overview_Factsheet` | pure | Nations / Stations single |
| `Role_Podium` | pure | Actors → Roles, Actors overview |
| `Score_Distribution` | pure | Shows → Scores, Shows overview, main score trend |
| `Series_Trend` | pure | adaptive "more than ever / down from the peak" copy on several overviews |
| `Show_Death_Leaders` | glue | Death → Shows |
| `Star_Podium` | pure | Shows → Stars, Shows overview |
| `Taxonomy_Death_Leaders` | glue | `partials/death-taxonomy-highlights.php` (Death → Nations / Stations) |
| `Taxonomy_Profile` | glue | `partials/taxonomy-facet.php` |
| `Term_Count_Distribution` | pure | Load waffles on Genres, Tropes, Intersectionality, Clichés |
| `Trigger_Levels` | pure | Shows → Triggers, Shows overview |
| `Trope_Category_Coverage` | pure | Shows → Tropes (Trope Alignment) |
| `Unknown_Actor` | glue | Actors → Unknown Actor |
| `We_Love` / `We_Love_Compare` | glue / pure | Shows → We Love It, Shows overview |
| `Worth_It_Grid` | pure | Shows → Worth It, Shows overview |

The older `*-optimized`, `Dead`, `Nations`, `Stations`, `Scores`, `Formats`, `Worth_It`, `We_Love_It` and `This_Year` builders are glue reached through `Stats_Generator`.

## FacetWP links

A Common Pairings or Breakdown row can link to a filtered shows archive only when the taxonomy has a FacetWP facet that takes several values in one URL param. FacetWP facets are configured in the database, not in this repo. The code treats only `lez_intersections` (`fwp_show_intersectionality`) as having one: `template-parts/excerpt/shows.php` reads the `show_intersectionality` facet, and Intersectionality's pairings link to `/shows/?fwp_show_intersectionality=a,b`.

Genres, Tropes (including Trope Alignment) and Clichés rows stay unlinked. `partials/matchup-cards.php` renders a row as a link only when an item carries a `url`, and never emits a dead `#` link. Add a `url` once a facet for that taxonomy is confirmed. Single-value filters such as `fwp_show_worthit` and `fwp_show_loved` are linked from `Worth_It` and `We_Love_It`.

## Load and Pairings pages

Shows → Tropes, Shows → Genres, Shows → Intersectionality and Characters → Clichés share one pattern for a multi-value taxonomy:

1. **Pullstats row.** Three cards: average terms per object, share carrying 3 or more, and the top pairing.
2. **Load waffle.** A 100-dot waffle of how many objects carry 0, 1, 2, 3 or 4+ terms (`Term_Count_Distribution`), with a footer spotlight on the most-loaded object (`Term_Count_Distribution::top_object()`). It's a distribution, not an average and median, because those collapse to the same number when the data clusters and hide the spread. It's a waffle, not bars, so it doesn't look like a second copy of the ranked Breakdown.
3. **Common Pairings.** The top 8 co-occurring pairs (`Intersection_Pairs::top_pairs( …, 8, 2 )`) as matchup rows. A footer gives the count of objects with exactly one term, which can never appear in a pairing. It reuses bucket "1" from the Load distribution rather than running another query.
4. **Breakdown.** The full ranked list, outside the grid, full width, in two newspaper columns.
5. **By decade** (Genres, Intersectionality). The top 3 terms per decade as independent "% of shows that decade" bars. It isn't a donut, because a multi-value taxonomy doesn't partition to 100% (see [data-model.md](data-model.md#taxonomy-cardinality)).

Layout on every one of these pages: a 2:1 grid with the Load panel in the wide main column and Common Pairings alone in the narrow side column (see [stats-css.md](../design/stats-css.md#page-grids)). Per page:

| Page | Main column | Side column | Notes |
|---|---|---|---|
| Tropes | Trope Load, then Mixed Alignment | Common Pairings | Trope Alignment cards (good / maybe / bad / ploy, from `Trope_Categories`, the same grouping as the show score) sit above the grid. A show with tropes in several buckets counts toward each, so the four totals aren't a partition. Mixed Alignment is a donut of single-bucket vs multi-bucket shows (`Trope_Category_Coverage::category_sets()` / `alignment_split()`). Its category sets are fed through `Intersection_Pairs` to find the most common category pairing. |
| Genres | Genre Load | Common Pairings | Adds the "Uncharted Genres" long-tail reframe and Genre Mix by Decade. |
| Intersectionality | Intersection Load, then Single vs Multiple | Common Pairings (linked) | Ranked bars in royal blue. Intersections by Decade runs full width at the end. |
| Clichés | Cliché Load | Common Pairings | No alignment categories, so nothing else stacks in the main column. `none` is excluded from Load and averages (see [data-model.md](data-model.md#none-terms)). |

The Clichés average uses a narrower denominator than its "3+" share. The average only counts characters with at least one real cliché. The 3+ share is a percentage of every published character.

## Formats

Shows → Formats is a donut on the raspberry ramp plus Format Mix by Decade: one compact donut per `Format_Decade_Buckets` bucket. The earliest sparse decades fold into one leading "before" bucket until it holds `$min_bucket_size` shows (20 by default), so a handful of early shows doesn't render as a confident 100%. Tile colours are by rank: the biggest slice in each tile gets the darkest stop. So a colour can mean a different format from tile to tile.

## Character identity pages

Characters → Gender and Characters → Sexuality each have a donut, a pullstats row, a by-decade row of compact donuts (the same shape as Format Mix by Decade) and a Firsts list: the earliest-recorded character for each term. Both taxonomies are single-value on Characters, and each character is anchored to their earliest `appears` year (see [data-model.md](data-model.md#anchoring-characters-to-a-year)).

- Both use the green ramp. On the main Gender donut, cisgender is pulled out as grey first. Sexuality has no baseline segment, so all five ramp stops go to the top orientations.
- In the decade tiles, colours are by rank, as on Formats. On Gender, cisgender isn't forced to grey in the tiles.

## Characters overview gaps

- **Cliché Gap** pairs "Bury Your Gays" (dead) against "No Cliché". It doesn't pair against "Played by Queer Actors", so it doesn't repeat the Casting Gap.
- **Casting Gap** is one two-colour waffle of characters played by queer vs straight/cis actors. `cis_pct = 100 − queer_pct` rather than being rounded independently, so the waffle always totals 100 dots.

## Most (The Records)

Characters → Most (view slug `most-cliches`, kept so existing links don't break) has a five-card spotlight (#1 in each category) and a Full Rankings table (ranks 1–5). The categories:

| Category | Source | Measure |
|---|---|---|
| Most clichés | `Cliche_Leaders` | `lez_cliches` terms per character |
| Most shows | `Character_Show_Leaders` | distinct `show_group` show IDs |
| Most actors | `Character_Actor_Leaders` | `lezchars_actor` entries |
| Most resurrected | `Character_Death_Leaders` | `lezchars_death_year` rows, 2 or more only |
| Longest running | `Character_Longevity_Leaders` | distinct credited `appears` years (not the first-to-last span) |

Any category can have fewer than five rows. Missing ranks render as an em dash, and "No record yet" in the spotlight. Nothing is made up to fill the gaps.

## Queer IRL

Characters → Queer IRL is one waffle of the queer-vs-not split. It uses the same colours as the Casting Gap (`$lwtv-pink` / `$lwtv-aro-grey`), because it's the same statistic. `Character_Queer_Cast_Firsts` adds three callouts: the oldest and newest character played by a queer-IRL actor, and the oldest played by a trans actor. "Queer IRL" is the `queer-irl` term in `lez_cliches` on the character. "Trans actor" is read from the actor's `lez_actor_gender`: a slug containing `trans` counts, matching `Queeries\Is_Actor_Trans`.

## Actors overview

Metric cards, a Headlines band, representation callouts and top panels.

- The Headlines band inlines its own markup instead of using `partials/headlines.php`. That partial skips itself below 4 items, and Actors has too few subpages to reach that, so this page renders the band at 2 or more. On Air (distinct actors with a character on screen this year) is the lead plate, because it has no subpage of its own. Roles, Gender and Sexuality sit in the rail.
- "Who Plays the Roles" shows Openly LGBTQ+ and Trans & Non-binary as two independent waffles with no gap ratio. Trans and non-binary actors are already inside the LGBTQ+ umbrella, so the two aren't opposite ends of anything.

## Actors identity pages

Actors → Sexuality and Actors → Gender each have a donut (grey for the default bucket, the amber ramp for the rest, and Unknown), a pullstats row, "The Overlap" callout and a most-prolific-actor-per-term grid. There's no decade trend or Firsts list (see [data-model.md](data-model.md#recasts)).

- **The Overlap** is the share of actors tagged Straight (or Cisgender) who still count as queer once the other identity fields are factored in. It comes from `Actors::count_queer_among_terms()` (see [data-model.md](data-model.md#stored-queer-flag)).
- On Gender, `cis-woman`, `cis-man` and `cisgender` merge into one Cisgender bucket. The most-prolific Cisgender card takes the highest-count leader of the three per-slug leaders. The maximum of the subgroup maximums is the maximum of the union, so no second query is needed.
- Actors → Roles is a Regular / Recurring / Guest breakdown on the amber ramp. Its most-prolific-per-role-type grid uses the first-listed-actor rule.

## Unknown Actor

Actors → Unknown Actor puts the placeholder actor in the spotlight (see [data-model.md](data-model.md#the-unknown-actor)). It shows how many characters are affected, who they are (a gender / sexuality / roles trio of mini donuts in one panel), which shows carry the most, and dead or alive. `Unknown_Actor::generate_report()` builds every facet from one shared character set, so each query runs once per cache cycle.
