# Score Calibration

How the tunable constants of the [character score](character-score.md) were set, and how to re-derive them.

All figures below were measured with `wp lwtv score-preview --all --format=csv` across the published corpus. Dated "as of 2026-09" where the source gives no exact date. The raw run notes are in [docs/plans/maybe-later/show-score-longevity.md](../plans/maybe-later/show-score-longevity.md), which is background only (it describes the model as switched off, but it is live).

## Saturation K

`Longevity::SATURATION_K = 10.0`: the X at which the character score is 50.

### Objective

**Calibrate on the character component's own distribution, not on the median total score.**

Matching the median total to the pre-longevity model looks obvious, but it keeps the bug the model exists to fix. The old character score had a median of 10 while the three components it is averaged with had a median of about 69. That was a scale error from an unbounded sum of small role points, not an editorial judgement. Holding the total median fixed needs K ≈ 40, which puts the character median at 11.8 and keeps the error.

### Calibration table

Measured across all 2255 published shows (as of 2026-09):

| K | char p50 | char p99 | total median shift | Failing (<20) | 90+ Club |
|---|---|---|---|---|---|
| 5.4 | 50.0 | 86.7 | +8.25 | – | – |
| 8 | 40.2 | 81.5 | +6.05 | 60 → 44 | 16 → 14 |
| **10** | **35.0** | **77.8** | **+4.91** | **60 → 47** | **16 → 12** |
| 15 | 26.4 | 70.1 | +3.01 | 60 → 50 | 16 → 8 |
| 40 | 11.8 | 46.8 | −0.03 | 60 → 56 | 16 → 1 |

### Decision

K = 10 moves the character median from 10 to 35. That fixes most of the scale error while keeping this the *hard* component it should be: it measures documented queer screen time, which most shows genuinely have little of, so it should not sit level with the alive ratio at 69.

K = 5.4 (the median X, so the median show scores exactly 50) is the cleanest single rule, but it shifts totals by +8.

The resulting rise in the median total is a correction, not drift. Most scores go **up** because a broken component stopped dragging the average down. Public methodology notes need to say this.

### Rejected approaches

- **Matching the median total.** See [Objective](#objective).
- **Calibrating from a single show.** Never do this. A show whose old character score was clamped at 100 cannot be reproduced even in principle. Transparent's old total of 93.08 needed a character component of 100.02, which an asymptotic curve can never reach.

## The 90+ Club

"The 90+ Club" (score ≥ 90) and "Failing Grades" (score < 20) are the defaults of `Statistics\Build\Score_Distribution::tails()`. They are mirrored as `BAND_TOP` and `BAND_FAILING` in `WP_CLI_LWTV_Score_Preview`, and must be changed in both places.

The club shrinks under longevity scoring, and that is mostly correct. 12 of its 16 pre-longevity members had a character score pinned at exactly 100, so the clamp produced their membership rather than any measurement. The honest baseline is 4. K = 10 gives 12.

## Re-deriving K offline

You do not need a new `--all` run to try other values of K. One CSV is enough.

1. `wp lwtv score-preview --all --format=csv > scores.csv` (add `--tvmaze` for live aired years on shows with none stored; it is one API call per show).
2. For each row:
   - `X = char_new_raw`: the post-divisor value `saturate()` consumed. It is its own column because back-deriving it from the two-decimal `char_new` is lossy.
   - `rest = 4 × score_new_raw − char_new`: the sum of the three components K does not touch.
3. For each candidate K: `char = 100 × X / ( X + K )`, `total = ( rest + char ) / 4`, clamped to 0 to 100 for bands and deciles.
4. Compare the distribution of `char` (p50, p99) and the band counts. Do not compare the median total.

The method was validated as of 2026-09. It reproduced a full run's median and mean exactly, and predicted the next live run's median shift within 0.04. `--k=<float>` does the same thing live for a single value.

## Coverage min

`Longevity::COVERAGE_MIN = 0.75`, with `COVERAGE_MIN_EVIDENCE = 5`. These control [signal 3](character-score.md#coverage) of the aired-years guard.

### Calibrating it

`score-preview --all` prints a coverage histogram of only the shows the signal can judge: shows with a TVMaze set and at least `COVERAGE_MIN_EVIDENCE` credited years. Shows with no set would measure 0.0 for want of anything to compare, and shows below the floor are deliberately not judged. Including either would invent a cluster at the bottom of the chart.

If incomplete sets are a distinct population, there will be a sparse band between the pile at 1.00 and the broken sets, and the threshold belongs in that gap. If the distribution is smooth, there is no natural cut point, and the signal needs rethinking rather than tuning.

### What the histogram showed

As of 2026-09: of 400 shows with a TVMaze set, 305 had fewer than 5 credited years, so the signal judged 95. Of those, 70 were at exactly 1.00, and the other 25 spread almost continuously from 0.06 to 0.94. The distribution is not bimodal:

| Threshold | Gap it sits in | Rejects |
|---|---|---|
| **0.75** (current) | 0.74 → 0.80 | 13 |
| ~0.40 | 0.24 → 0.53 (the widest gap) | 3 |

0.75 was chosen on a mechanism argument, not on the histogram. Home and Away (coverage 0.53, 19 credited years, 9 discarded) has aired continuously since 1988. With no hiatus for `appears` to be loose about, every discarded year is missing data. A 0.40 threshold would let it through.

**Known ambiguity:** about ten shows sit in the 0.80 to 0.94 band (for example Rick and Morty and Doctor Who, both 0.85). These are probably a real hiatus with `appears` entered as a continuous range, where the set is right. Nothing currently distinguishes them from a data gap of the same ratio. The cost of being wrong is 1 to 3 discarded years per show.

### Constraint

`COVERAGE_MIN` must stay above 1/1.5 (≈ 0.667). At or below that, a record-size comparison signal becomes reachable and should be reconsidered (see [Rejected signals](character-score.md#rejected-signals)). `test_coverage_min_makes_a_size_comparison_redundant` enforces this.

## Credited-years floor impact

As of 2026-09, the [credited-years floor](character-score.md#credited-years-floor) raised the denominator on 292 shows, about 13% of the corpus. For example, Naruto: Shippûden went from 1 to 11, Marienhof from 9 to 17, and Absolutely Fabulous from 5 to 10. Each of those shows gets a lower character score, correctly.

It is also a data-quality signal. Each floored show has a `lezshows_seasons` value well below the calendar years it actually aired across (anime recorded as "1 season" is common). The floor makes the score right, but it does not fix the meta. `score-preview --all` prints the count, and the `floored` CSV column flags each show.

## Uncapped totals

The character score is asymptotic, and tropes and alive ratio are both capped at 100. So only the show rating (max 115, unclamped) can push a total past 100, and the theoretical ceiling is 103.75. As of 2026-09, only The L Word: Generation Q exceeded 100 uncapped. See [show-score.md#clamped-and-uncapped-meta](show-score.md#clamped-and-uncapped-meta).
