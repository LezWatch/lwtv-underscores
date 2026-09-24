# Statistics presentation rules

Rules for turning statistics numbers into what readers see: precision, rounding, pictogram allocation, ties and data-dependent wording. Most of these are enforced in the pure `Build\*` transforms (see [pages.md](pages.md#build-layer)) and covered by `tests/unit/Statistics/`.

## Percent precision

| Figure | Precision | Where |
|---|---|---|
| Shares on distributions, trigger levels, star rate, "none" shares | 1 decimal | `Term_Count_Distribution::build()`, `Trigger_Levels::facts()`, `Star_Podium::facts()` |
| Leading-tier share on rail cards | whole number | `Star_Podium::facts()` `leader_share_pct` |
| "1 in N" ratios | whole number | `Trigger_Levels::facts()` `scarcity_ratio`, `floor_ratio` |
| Average score per verdict | whole number (the display shows no decimals) | `Worth_It_Grid::averages()` |
| Percent of peak | whole number | `Series_Trend::classify()` |

Very small cohorts get counts, not bare percentages. For example, the Shows We Love cohort cards in `templates/shows/we-love-it.php` never print a percentage at that size of sample.

## Divisor guarding

Every ratio checks its denominator first and returns `0` / `0.0` (or an empty array) when it would be zero: `( $total > 0 ) ? round( … ) : 0.0`. Templates treat an empty result as "don't render this section" rather than showing `0%` or `NaN`. Keep this pattern in any new transform.

## Sum-to-100 allocation

A 100-cell pictogram must render exactly 100 cells. If each bucket is rounded on its own, the total drifts to 98 or 102. There are two allocators:

- **Largest remainder:** `Term_Count_Distribution::to_cells()`, used for the Load waffles. Each bucket gets its floored share, then leftover cells go one at a time to the buckets with the largest fractional remainder. Ties keep the original order. This is the default for new pictograms.
- **Largest absorbs drift:** `Worth_It_Grid::squares()`. Every verdict with a non-zero count gets at least one square, so TBD never rounds away to nothing. Whatever rounding leaves over or short goes to the largest verdict.

Binary two-part figures do the same thing more simply: compute one side and take the other as `100 − side` (for example the Casting Gap's `cis_pct`).

The bucket percentages shown in legends are rounded independently to 1 decimal and may not add up to exactly 100. Only the cell counts are guaranteed to.

## Ties

- `Term_Count_Distribution::top_object()` returns how many objects tied for the top (`tied`) along with one deterministic pick (the lowest ID). Once `tied > 1`, captions must hedge ("one of N shows with…") rather than imply the pictured object is unique.
- Leaderboards break count ties on purpose, not by database order. `Cliche_Leaders`, `Character_Show_Leaders` and `Character_Actor_Leaders` put the most recently added character first. `Character_Longevity_Leaders` goes by more recent latest year. Firsts lists and `Actors` prolific picks go by lower ID.
- `Series_Trend::classify()` takes the *latest* year matching the maximum as the peak, so tying an old high reads as "back at the peak".

## Adaptive copy gates

A sentence that makes a claim about the data is only printed while the data supports it. The transform returns the evidence, and the template picks the wording.

| Claim | Gate |
|---|---|
| "More than ever" / "down from the peak" | `Series_Trend::classify()` returns `at-peak`, `recovering`, `receding` or `steady` |
| Silver vs bronze "dead heat" | `Star_Podium::relationship()`: gap ≤ 15% of the larger count |
| Low vs high triggers "nearly / more than / exactly N to 1" | `Trigger_Levels::balance()`: counts within 15% read as `even`; otherwise a rounded ratio plus a qualifier for which way it rounded |
| "Verdict tracks the score" | `Worth_It_Grid::tracks_score()`: averages strictly yes > meh > no **and** yes − no ≥ `TRACKS_MIN_SPREAD` (15 points) |
| Loved vs rest "about the same", direction, "clearest gap" | `We_Love_Compare`: `SAME_BAND` (10% relative), `DIRECTION_TOLERANCE` (1 point), `largest_gap()` ranking |
| Catalog depth totals | `Catalog_Depth` coverage count: totals are shown only when enough shows have season and episode data |

## In-progress year

The current year is incomplete, so its count would always look like a crash.

- `Series_Trend::classify()` judges only completed years (`year < $current_year`).
- Death → Years leaves the current year out of the "years nobody died" list.
- `Series_Trend::trim_trailing_zeros()` cuts an entity's trailing zero years so an axis stops where the story stops. Zero years in the middle are kept, since a gap is part of the story. The template pairs this with a "nothing on the air since %d" line.

## Sparse early decades

Decade bucketers (`Format_Decade_Buckets`, `Genre_Decade_Buckets`, `Character_Identity_Decade_Buckets`) fold the earliest decades into one leading "before" bucket until it holds at least `$min_bucket_size` objects (20 by default). A handful of early shows then doesn't render as a confident 100%.
