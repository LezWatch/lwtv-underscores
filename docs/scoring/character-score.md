# Character Score

How the character component of the [show score](show-score.md) is calculated: longevity-weighted, casting-aware and saturating.

Code:

- [`cpts/shows/scoring/class-longevity.php`](../../plugins/lwtv-plugin/php/cpts/shows/scoring/class-longevity.php) (`Scoring\Longevity`): the pure maths. Unit-tested in `tests/unit/CPTs/ShowLongevityTest.php`.
- [`cpts/shows/scoring/class-character-score.php`](../../plugins/lwtv-plugin/php/cpts/shows/scoring/class-character-score.php) (`Scoring\Character_Score`): `gather()` reads WordPress data, and `longevity()` sums and saturates. `longevity()` is tested in `tests/unit/CPTs/CharacterScoreTest.php`.

`Calculations::count_queers_all_types()` and `wp lwtv score-preview` both call `Character_Score::gather()` and `Character_Score::longevity()`, so the preview cannot drift from what ships.

## Overview

```
X     = Σ over characters ( character_value × weight ) / format_divisor
score = 100 × X / ( X + SATURATION_K )
```

- **character_value** is how present the character is within a year (role points), scaled by casting, clichés and death. See [Per-character value](#per-character-value).
- **weight** is how many of the show's years that presence lasted, from 0 to 1. See [Longevity weight](#longevity-weight).
- Their product approximates total screen time. They measure different things, so they do not compete.
- The format divisor (`Character_Score::FORMAT_DIVISORS`: movie 2, mini-series 1.5, web-series 1.25, otherwise 1) is applied to X **before** saturation. Saturation is non-linear, so dividing afterwards would be a different operation.

### Why a saturating sum and not an average

Under an average, every one-episode guest drags a show's score down. That would make documenting a minor queer character cost the show points, which is the wrong incentive for this site. So every character adds a small positive amount, and the curve flattens instead. Volume gets diminishing returns and is never a penalty. A 50-year soap that cycled through hundreds of one-episode characters should not outrank a tightly written five-season drama, and a show should never lose points for documenting a minor character.

## Data gathering

`Character_Score::gather( $show_id, $options )`:

- The show's characters come from `lwtv_plugin()->get_characters_list( $show_id, 'query' )`. Terms for `lez_cliches` and `lez_gender` are loaded in two batched queries (`batch_terms()`).
- **Role** is taken from the character's `lezchars_show_group` rows for this show. If there is more than one row, `strongest_role()` keeps the role worth the most `ROLE_POINTS`. It ranks by points rather than array order, so reordering the constant cannot invert the hierarchy. An unrecognised role scores 0 and never wins.
- **Appears years** come from the `appears` sub-field of those rows. It is stored per show row, so a character on two shows keeps separate year lists. A single selection can come back as a bare scalar.
- **Primary actor** is the first entry in `lezchars_actor`. Actors are stored in billing order.
- **Aired years** come from `lezshows_aired_years` meta, written by `wp lwtv tvmaze` (see [docs/integrations/tvmaze.md](../integrations/tvmaze.md)). `score-preview --tvmaze` can pass live TVMaze years in as `aired_override`. Live HTTP never enters the scoring path.
- Hiatus years are always passed as empty, so [tier 3](#run-years) never occurs in live scoring today.

`gather()` also returns legacy counts that are not part of the score: `trans` uses `NOT_TRANS` (any character not tagged `cisgender`, `intersex` or `unknown`), and `trans_irl` counts characters with any trans actor.

## Per-character value

`Longevity::character_value( $role, $casting_multiplier, $no_cliches, $dead )`:

```
value = ROLE_POINTS[role] × casting_multiplier × ( 1.25 if no clichés ) × ( 0.5 if dead )
```

| Constant | Value | Meaning |
|---|---|---|
| `ROLE_POINTS` | regular 5, recurring 2, guest 1 (unknown 0) | Base: intensity of presence within a year |
| `NO_CLICHES_BOOST` | 0.25 | ×1.25 when the character has the `none` cliché term. Secondary by design. |
| `DEAD_FACTOR` | 0.5 | Killing a character halves everything they contributed |
| Casting multiplier | 0.5, 1.0 or 2.0 | See [Casting multiplier](#casting-multiplier) |

The qualities **multiply** the role base rather than adding to it. This keeps prominence meaningful: good casting is worth more on a lead than on a one-scene guest, and killing a lead costs more than killing a walk-on. A negative casting multiplier is clamped to 0, so the value is never negative and documenting a character can never cost a show points.

## Casting multiplier

`Longevity::casting_multiplier( $gender_class, $primary_actor_queer, $actor_class )` returns **one** multiplier per character.

| Character class | Condition | Multiplier |
|---|---|---|
| `trans-or-nb` | Primary actor classifies `trans-or-nb` | 2.0 (`1 + TRANS_BOOST`) |
| `trans-or-nb` | Primary actor explicitly `cis` | 0.5 (`TRANS_MISCAST_FACTOR`) |
| `trans-or-nb` | Actor gender `unknown` | 1.0 |
| `cis` | Tagged `queer-irl` **and** the primary actor is queer (`Is_Actor_Queer`) | 2.0 (`1 + QIRL_BOOST`) |
| `cis` | Otherwise | 1.0 |
| `unclassified` | Any | 1.0 |

Rules:

- **One casting decision, one multiplier.** A trans or non-binary role is judged only on trans/NB casting. Everyone else is judged only on queer casting. The two never stack. `character_value()` takes a single float rather than two flags, so they cannot compound by accident. The result is always within [0.5, 2.0].
- **`QIRL_BOOST = 1.0`** reads as "casting a queer actor is worth as much as doubling this character's screen time". Raise it if queer casting should outweigh prominence.
- **`TRANS_MISCAST_FACTOR` is below 1.0 on purpose.** Casting a cis actor in a trans role actively costs the show, rather than just missing a bonus. It is the only place the model docks a show, so each miscast verdict is recorded in `gather()`'s `miscast_detail` and printed by `score-preview --verbose` for audit.
- **Only an explicit cis tag earns the penalty.** An actor whose gender we have not recorded is our data gap, and scores neutrally.
- **Unclassified characters score neutrally.** An untriaged gender term must not move a score in either direction. It belongs in the unclassified report, not in the maths.
- **The queer-irl check needs both conditions** (the "Tambor Takedown"): the character is tagged `queer-irl` and the first-billed actor is actually queer. The tag alone is not enough.

## Gender classification

### Character gender

`Longevity::classify_gender( $slugs )` reads `lez_gender` slugs and returns one of three states:

| Result | Slugs |
|---|---|
| `trans-or-nb` | `GENDER_TRANS_OR_NB`: `trans-woman`, `trans-man`, `transgender`, `non-binary-transgender`, `non-binary`, `genderqueer`. Wins a mixed set. |
| `cis` | `GENDER_CIS`: `cisgender`, `intersex`, `unknown` |
| `unclassified` | No terms, or only terms in neither list |

Non-binary and genderqueer are included deliberately. A non-binary role should go to a trans or non-binary actor, and the site treats non-binary characters as a core constituency rather than an edge case.

There are three states rather than a boolean because a yes/no allowlist would treat any new taxonomy term as cis. Those characters would silently stop being assessed. `unclassified` makes that visible: `score-preview` warns about every unclassified slug.

### Actor gender

`Longevity::classify_actor_gender( $slugs )` reads `lez_actor_gender` slugs and returns `trans-or-nb`, `cis` or `unknown`:

1. Any slug **containing** `trans` or `non-binary` → `trans-or-nb`. This is a substring match because the taxonomy is full of compound slugs (`non-binary-woman`, `non-binary-intersex`, `non-binary-gender-fluid`, `two-spirit-trans-man`) that an exact list would miss, turning non-binary actors into false miscasts. It is the same rule as `Queeries\Is_Actor_Trans`.
2. Any slug in `ACTOR_GENDER_DIVERSE` (`genderfluid`, `genderqueer`, `agender`, `gender-non-conforming`) → `trans-or-nb`.
3. Otherwise, any slug in `ACTOR_CIS` (`cisgender`, `cis-man`, `cis-woman`, `intersex`) → `cis`. Cis only wins if nothing trans or non-binary was found.
4. Anything else, including `undefined`, `unknown` and no terms → `unknown`, which scores neutrally.

#### Deliberately omitted actor gender slugs

`demigender`, `androgynous`, `no-label` and `two-spirit` are left out of `ACTOR_GENDER_DIVERSE` on purpose. This is an editorial decision, not an oversight. Whether they are held to the trans/NB casting standard is an identity question, not a technical one. `androgynous` often describes presentation rather than identity, and `no-label` is a deliberate refusal to categorise, which an allowlist should not resolve in either direction. Leaving them out is the safe outcome: they classify as `unknown` and score neutrally, so no show is ever docked over them. (`two-spirit-trans-man` still matches through the `trans` substring.)

Measured as of 2026-09: 37 actors are tagged `undefined` or `unknown`, and 45 carry a slug that classifies as `unknown`.

## Longevity weight

`Longevity::weight( $years, $run_years )`:

```
share  = min( 1, years / run_years )
curve  = sqrt( min( years, ABSOLUTE_CAP ) / ABSOLUTE_CAP )
weight = min( 1, SHARE_WEIGHT × share + CURVE_WEIGHT × curve )
```

| Constant | Value |
|---|---|
| `SHARE_WEIGHT` | 0.7 |
| `CURVE_WEIGHT` | 0.3 (must sum to 1.0 with `SHARE_WEIGHT`) |
| `ABSOLUTE_CAP` | 8 years |

Share-of-run alone would flatten every character on a long show. Five solid years on a 20-year series would score 0.25, while the same five years on a five-year series scored 1.0. The curve term keeps absolute tenure worth something.

| Case | share | curve | weight |
|---|---|---|---|
| 40 of 50 yrs (soap regular) | 0.80 | 1.00 | 0.86 |
| 1 of 50 yrs (soap guest) | 0.02 | 0.35 | 0.12 |
| 4 of 5 yrs (drama regular) | 0.80 | 0.71 | 0.77 |
| 5 of 5 yrs (full-run regular) | 1.00 | 0.79 | 0.94 |
| 3 of 3 yrs (web series) | 1.00 | 0.61 | 0.88 |

Weight reaches 1.0 only for a full run of at least 8 years.

`years` is `Longevity::character_years( $appears, $aired_years )`: distinct credited years. When the show's aired years are known and trusted, years outside them are dropped as data-entry errors, so no separate clamp on `share` is needed.

### Role proxy

A character with no usable `appears` years gets `role_proxy_weight()`: regular 0.7, recurring 0.4, guest 0.15, anything else 0.15 (`ROLE_PROXY_DEFAULT`). It is never zero, because missing data must not become a penalty.

## Run years

`Longevity::run_years()` / `run_years_detail()` give the denominator of `share`, and it is always at least 1. `run_years_detail()` also reports which tier produced it. Callers must use that tier rather than re-deriving it, or the "still airing" test can drift between the two.

| Tier | Source | Used when |
|---|---|---|
| 1 | Stored season count (`lezshows_seasons`), capped at the span | The show has **finished** and has a season count |
| 2 | Count of the trusted TVMaze aired-years set | There is a set that passed [plausibility](#aired-years-plausibility) |
| 3 | Airdate span minus known hiatus years inside it | Hiatus data exists (never, today) |
| 4 | Raw airdate span, `finish − start + 1` | Always available |

- **Still airing** means the finish is empty, `current`, or a year that has not passed. This matches how `do_the_math()` sets `lezshows_on_air`. Tier 1 skips still-airing shows because the season on air may not be counted in the meta yet.
- **Tier 1 before tier 2 is a choice of curated data over exact data**, and it has a known cost. A season count undercounts calendar years whenever seasons straddle a year boundary (Arrested Development: 5 seasons, 7 calendar years), which inflates `share`. Tier 2 handles straddling seasons, multiple drops in one year, and revival gaps correctly. The ordering is deliberate, but it is not the accuracy ordering. The [credited-years floor](#credited-years-floor) limits the cost.
- The season count is capped at the span because streaming shows can drop two seasons in one calendar year.
- An unparseable start year returns 1 year at tier 4. This should be unreachable, but it is a division, so it is guarded.

### Credited-years floor

Outside tier 2, the denominator is raised to the number of distinct years any character is credited on the show (`$credited_count`). `floored` in the result records when this happened.

A denominator smaller than the span of its own numerators is inconsistent. If characters are credited across five calendar years, the show cannot have run for three. The case that exposed it was The L Word: Generation Q: 3 seasons across 5 calendar years, so tier 1 said 3. Every character with 3 or more years had `share` capped at 1.0, which made it the largest X in the corpus (116.6) and the only show whose uncapped total cleared 100.

Two bounds:

- **Capped at the span.** A show cannot have aired in more calendar years than lie between its premiere and finale, so a mistyped `appears` year cannot push the denominator past that. The worst a typo can do is raise the denominator by one, which *lowers* the score. That is the safe direction.
- **Never applied to tier 2.** Real per-season air dates are authoritative, and `character_years()` already intersects against them, so the numerator cannot exceed the denominator. Raising it would divide by years the show demonstrably did not air.

With a hiatus (tier 3), the floor also corrects a wrong hiatus: a character credited during a supposed hiatus is evidence the hiatus data is wrong. The floor is off by default (`$credited_count = 0`). A floored tier-1 result is the one case where a tier-1 denominator can exceed the season count.

For how often the floor fires, see [calibration.md#credited-years-floor-impact](calibration.md#credited-years-floor-impact).

## Aired years

`Longevity::aired_years_from_seasons( $seasons, $current_year )` takes the union of every TVMaze season's `premiereDate`–`endDate` calendar years. It is exact where the other approximations are wrong:

- A September-to-May season covers two calendar years. A season count records one.
- A single-day streaming drop covers one year.
- A revival gap drops out of the union. The airdate span swallows it: The X-Files reads as 26 years but aired in far fewer.

Seasons with no premiere date, or a premiere in the future, are skipped. A null end date runs to the current year. End dates are clamped to the current year and never earlier than the premiere. Years are parsed from the string rather than with date parsing, so a timezone cannot shift them.

## Aired-years plausibility

TVMaze's season coverage is patchy for long-running shows. A set can come back with only a handful of its years dated, which is worse than no set, because the set is used twice:

- as the tier-2 denominator, where a short set inflates every weight;
- intersected with each character's `appears` in `character_years()`, where every undated year is silently discarded.

Measured as of 2026-09, before this guard existed: 13 shows had their denominator shrink while mean character weight *also* fell. A smaller denominator can only raise weights, so the intersection was throwing away real screen time. These were mostly long-running international soaps (Gute Zeiten schlechte Zeiten, Unter Uns, Ros na Rún, Salatut elämät).

`Longevity::aired_years_verdict( $aired, $seasons, $start, $credited )` checks three signals in order. `usable_aired_years()` returns the set unchanged on `ok` and empty otherwise, so the denominator falls through to tier 3 or 4 and the intersection is skipped.

| Verdict | Signal | Rule |
|---|---|---|
| `none` | No set | Empty input |
| `seasons` | 1. Fewer aired years than seasons | `seasons ≥ 2` and `count(aired) < seasons − AIRED_SEASON_SLACK` (1) |
| `late-start` | 2. Set starts late | `min(aired) > start year + AIRED_START_SLACK` (1) |
| `coverage` | 3. Set cannot explain the credits | At least `COVERAGE_MIN_EVIDENCE` (5) distinct credited years, and `appearance_coverage() < COVERAGE_MIN` (0.75) |
| `ok` | None fired | |

- `AIRED_SEASON_SLACK = 1` covers ordinary September-to-May scheduling, where N seasons fill N−1 calendar years. A bigger gap means seasons are missing from the API.
- `AIRED_START_SLACK = 1` absorbs a December premiere recorded as the following year, or an airdate that is off by one.
- Signal 3 is opt-in through `$credited_years`. A caller that has not gathered characters gets signals 1 and 2 only, rather than a weaker check that looks like the full one.
- The function takes no current year, because `aired_years_from_seasons()` has already clamped the set.

`score-preview` explains each rejection: `seasons` and `coverage` mean TVMaze is missing seasons. `late-start` can also mean our own start year is wrong.

### Coverage

`Longevity::appearance_coverage( $aired, $credited )` is the share of the **union** of credited years that the aired set contains. It uses the union, not a per-character tally, so one long-serving regular cannot swamp it and a year credited to six characters counts once. With nothing to explain it returns 1.0, so no evidence never reads as bad coverage.

Signal 3 measures the damage directly instead of predicting it from the set's shape. That is how it separates the two ways a set can have holes:

- **A real revival gap** (The X-Files: 1993–2002, then 2016 and 2018) has no appearances inside the hole, because nothing aired then. Coverage is near-total and the set is kept.
- **A data gap** has characters credited inside the hole, which proves the show aired then and the set is incomplete. The set is rejected. Example: Gute Zeiten schlechte Zeiten, with 9 dated years across a 35-year run and characters credited in the gaps.

`COVERAGE_MIN_EVIDENCE = 5` is sized so that one stray `appears` year can never reject a set on its own: with five years of evidence, one error still leaves 0.80. Below the floor, a bad set and a bad year cannot be told apart, so the signal abstains. For how 0.75 was chosen, see [calibration.md#coverage-min](calibration.md#coverage-min).

### Rejected signals

Two further signals were designed and are deliberately absent. Both were killed by measurement, and tests guard against rebuilding them.

- **Where discarded years fall** (outside the set's range versus inside an internal hole). The theory was that only "outside" is unambiguous. In practice, the case it was meant to catch (GZSZ) and the case it was meant to spare (Rick and Morty) both put every discarded year inside a hole. `discarded_years()` still reports the split as a diagnostic (`disc_outside` / `disc_hole` in the preview CSV).
- **Comparing record sizes** (reject when credited years exceed aired years by more than 1.5×). This is provably redundant: if `|C| > 1.5 × |A|`, coverage is at most `|A|/|C| < 0.667`, which is already under `COVERAGE_MIN`. Below the evidence floor, where it could have added something, it matched zero of 304 shows (as of 2026-09). `test_coverage_min_makes_a_size_comparison_redundant` fails if `COVERAGE_MIN` is ever lowered to 1/1.5 or below.

The first signal-2 design assumed TVMaze back-fills recent seasons first, so a short set would start late. Measurement disproved that: GZSZ has 9 dated years across a 35-year span and its set starts at the premiere. Signal 2 is kept because it is free and catches a real shape, but signal 3 does the real work. Signals 1 and 2 together caught 6 of the 13 damaged shows. Signal 1 is limited because only 23 of 376 tier-2 shows had a season count to compare against (as of 2026-09).

## Saturation curve

`Longevity::saturate( $raw, $k = SATURATION_K )` returns `100 × raw / ( raw + K )`, or 0 for raw ≤ 0.

- X equal to K scores exactly 50. At K = 10: X 10 → 50, X 20 → 66.7, X 30 → 75.
- It is asymptotic, so no amount of headcount buys a perfect score, and shows do not stack at exactly 100. The top of the ranking still carries information.
- Each extra character still adds something positive.

How K was chosen is in [calibration.md#saturation-k](calibration.md#saturation-k).

## Worked examples

### Deep bench versus revolving door

Two 50-year soaps, no clichés (×1.25), K = 10:

| | Characters | Per character | X | Score |
|---|---|---|---|---|
| Deep bench | 10 regulars × 35 years | 6.25 × 0.79 = 4.94 | 49.4 | 83.2 |
| Revolving door | 60 guests × 1 year | 1.25 × 0.12 = 0.15 | 9.0 | 47.4 |

### Transparent (#655)

Run years 5 (tier 1). Per-character contributions (value × weight):

| Character | Role, years | Casting | Contribution |
|---|---|---|---|
| Sarah | regular, 5 of 5 | 1.0 | 5 × 0.937 = **4.69** |
| Davina | recurring, 4 of 5, trans woman, trans-cast | 2.0 | 4 × 0.772 = **3.09** |
| Ari | regular, 5 of 5, non-binary, cis-woman actor | 0.5 | 2.5 × 0.937 = **2.34** |
| Barb | guest, 2 of 5, queer-cast | 2.0 | 2 × 0.43 = **0.86** |
| Maura | regular, 4 of 5, trans woman, cis-man actor, dead | 0.5 | 1.25 × 0.772 = **0.97** |
| Adriana | guest, 1 of 5, casting boost | 2.0 | 2 × 0.246 = **0.49** |

The lead roles stay above the guests even after the casting penalties. Two earlier designs got this ordering wrong, and they are why the current rules exist:

- **Additive queer-irl bonus (+10).** Barb scored 11 × 0.43 = 4.73 and beat a five-season lead on 4.69, because the flat bonus was worth twice a lead role. Qualities now multiply.
- **Stacked casting multipliers (queer-irl × trans, up to ×4).** Davina reached 8 × 0.772 = 6.18 and outranked a series lead. Adriana reached 4 × 0.246 = 0.98 and outranked Maura, a protagonist, at 0.97. One casting fact was being counted twice. Casting is now [one multiplier](#casting-multiplier).

These inversions are pinned by `test_a_five_season_lead_outranks_a_queer_cast_one_scene_guest`, `test_a_series_lead_outranks_a_well_cast_recurring_character` and `test_a_miscast_dead_lead_still_outranks_a_one_episode_guest` in `ShowLongevityTest`.

## Background

- [docs/plans/maybe-later/show-score-longevity.md](../plans/maybe-later/show-score-longevity.md) is the original plan and measurement log. It is stale: it says the model is switched off, but it is live.
- [docs/plans/maybe-later/show-hiatus-gaps-plan.md](../plans/maybe-later/show-hiatus-gaps-plan.md) is the unbuilt plan for hiatus data (tier 3).
