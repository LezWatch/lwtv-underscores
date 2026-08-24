# Plan: Longevity-Weighted Character Scoring

**Goal:** Stop sheer character headcount from driving a show's character score, so a
50-year soap that cycled through 200 one-episode queer characters no longer outranks a
tightly-written five-season drama. Replace raw counting with a model that weights each
character by *how long they were actually on screen*.

**Scope:** three changes to `count_queers_all_types()` in
`plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`, each behind its **own**
feature flag so their effects can be measured separately:

| # | Change | Flag |
|---|---|---|
| 1 | Longevity-weighted, saturating character aggregate | `lwtv_score_longevity_enabled` |
| 2 | Aired-years denominator from TVMaze season dates | inherits #1 |
| 3 | Defensive guard on the `trans` count | none needed — provably a no-op |

`show_score()`, `show_tropes_score()`, and the four-way average in `do_the_math()` are
untouched.

Two adjacent findings surfaced while writing this and are recorded at the end: the
Tambour Takedown check exists only in dead code, and `get_characters_list()` writes post
meta as a side effect of a getter. Neither is in scope; both should be tickets.

---

## The data already exists

No new ACF fields, no data entry, no migration.

The `lezchars_show_group` repeater already has an `appears` sub-field — a multi-select of
years, stored per show row, so a character credited on two shows keeps separate year lists
for each. `Statistics\Build\Character_Longevity_Leaders` already mines it. Show run length
comes from `CPTs\Shows\Airdates::get()`, and **airdate coverage is 100%** (every show has a
start and a finish, even if the finish is the `current` sentinel), so the denominator is
always computable.

Critically, `show_character_data()` at line 519 *already* calls
`get_field( 'lezchars_show_group', $char_id )` and already walks its rows looking for the
one matching this show. The `appears` array is sitting in that row, unread. Capturing it
there costs zero additional reads.

---

## Diagnosis: headcount isn't quite the bug

The stated problem is that more characters means a higher score. The actual mechanism is
narrower and worth naming precisely, because it changes what the fix has to be.

`count_queers_all_types()` builds an **unbounded sum** —
`regular×5 + recurring×2 + guest×1`, plus `queer-irl×10`, `none×5`, `dead×-5` — then
divides by a format divisor and **clamps to 100 at line 266**. Two consequences:

1. Any show with enough characters saturates the ceiling regardless of quality. Modelled,
   a 50-year soap with 30 characters lands at 82; add 20 one-episode guests and it hits a
   flat 100. Volume alone buys a perfect score.
2. Shows pile up *tied* at exactly 100, so the top of the ranking carries no information.

So multiplying each character's points by a longevity factor while keeping the
sum-and-clamp structure would move almost nothing — the soap still saturates. The
aggregation has to change too.

---

## Change 1 — the two-part model

### Part 1 — per-character longevity weight

Blend share-of-run with a curved absolute-year term (the option chosen in discussion):

```
years  = count( distinct years in this character's `appears` for this show )
run    = max( 1, finish_year - start_year + 1 )     // 'current' → current year
share  = min( 1.0, years / run )
curve  = sqrt( min( years, ABSOLUTE_CAP ) / ABSOLUTE_CAP )

weight = 0.7 × share  +  0.3 × curve                // ∈ (0, 1]
```

| Character | share | curve | **weight** |
|---|---|---|---|
| Soap regular, 40 of 50 yrs | 0.80 | 1.00 | **0.86** |
| Soap guest, 1 of 50 yrs | 0.02 | 0.35 | **0.12** |
| Drama regular, 4 of 5 yrs | 0.80 | 0.71 | **0.77** |
| Web series, 3 of 3 yrs | 1.00 | 0.61 | **0.88** |
| 20-yr show, 5 yrs | 0.25 | 0.79 | **0.41** |

The curve term earns its keep on the last two rows: pure share-of-run would score a
maximally-present web series character (1.00) above a 20-year show's five-year regular
(0.25), and would flatten every long-show character. With airdates at 100% coverage the
curve is no longer needed as a *fallback* — it's there for fairness across run lengths.

### Part 2 — saturating aggregate, not a sum or an average

```
X     = Σ ( character_value × weight )              // character_value as today: role + bonuses − penalties
X    += trans_score                                 // stays a show-level aggregate, as now
X     = X / format_divisor                          // movie ÷2, mini ÷1.5, web ÷1.25 — unchanged
score = 100 × X / ( X + SATURATION_K )              // smooth ceiling, replaces the hard clamp
```

This is the part that reverses an earlier recommendation, so it's worth the paragraph.

**I previously suggested a density model — a weighted average instead of a sum. Having run
the numbers, that's the wrong call and should not be built.** An average divides by the
roster, so every one-episode guest actively *drags the score down*: modelled, the 30-character
soap falls from 82 to **19** even though it has six genuinely excellent 20-year regulars.
Worse, it means **documenting a minor queer guest character lowers a show's score** — a
scoring system that penalises thorough documentation is hostile to the point of the site.

The saturating curve gets the same outcome without that side effect. Short-tenured
characters contribute a small positive amount, so they never punish; and because the curve
flattens, no amount of headcount can buy a top score. Volume gets *diminishing returns*
rather than a penalty.

It also retires the hard clamp at line 266 in favour of a smooth asymptote, which spreads
out the shows currently stacked at exactly 100.

### Constants (all tunable, all documented in one place)

| Constant | Proposed | Notes |
|---|---|---|
| `SHARE_WEIGHT` | 0.7 | |
| `CURVE_WEIGHT` | 0.3 | must sum to 1.0 with the above |
| `ABSOLUTE_CAP` | 8 | years at which the curve term reaches 1.0 |
| `SATURATION_K` | 40 | **calibrate from the dry run**, see Phase 2 |

---

## Modelled impact

Synthetic rosters, `SATURATION_K = 40`. These are illustrative, not measured — Phase 2
replaces them with real numbers.

| Scenario | OLD | NEW | Δ |
|---|---|---|---|
| A. 50-yr soap, 30 chars (6 long-running regulars) | 82.0 | 47.1 | −34.9 |
| B. 5-season drama, 6 chars | 40.0 | 46.2 | +6.2 |
| C. 3-yr web series, 4 chars | 51.2 | 53.6 | +2.4 |
| D. 50-yr soap, deep bench (10 × 35 yrs) | 100.0 | 72.8 | −27.2 |
| E. 50-yr soap, 60 one-off guests | 60.0 | 15.3 | −44.7 |
| F. 2-season indie, 2 chars | 30.0 | 38.9 | +8.9 |
| G. = A plus 20 more one-off guests | 100.0 | 55.6 | −44.4 |

The three rows that matter:

- **D vs E** — same 50-year run, opposite tenure profiles, now 73 vs 15. The deep-bench
  soap *keeps* its high score, because it earned it. That is the correct outcome and the
  reason not to simply penalise long shows.
- **G vs A** — twenty extra one-off guests used to be worth +18 points and a perfect 100.
  Now worth +8.5 and nowhere near the ceiling.
- **F, B, C** — small and short-format shows rise modestly, which is the intended
  redistribution.

Note that guests still add roughly +0.7 to `X` each. If that still feels too generous once
real numbers are in, the lever is guest role points (currently 1) or `ABSOLUTE_CAP`, not the
model shape.

---

## Change 2 — the aired-years denominator

`share` needs to divide by the years a show was *actually on screen*. Raw
`finish − start + 1` overcounts any show with an off-air gap, deflating `share` for every one
of its characters.

### Correction: season count beats the airdate span

An earlier draft of this plan rejected using a season count on grounds of "unit mismatch" —
`appears` is in calendar years, seasons are seasons. **That was too purist, and wrong on the
question that matters.** The question isn't whether seasons are the same unit as years; it's
which approximation is *less wrong*. Worked against Arrested Development (TVMaze show 321):

| Method | Result |
|---|---|
| Airdate span (`2019 − 2003 + 1`) | 17 |
| Season count | 5 |
| **Years actually on screen** | **7** |

Season count is off by 2. The airdate span is off by 10. The season count is clearly the
better of those two, exactly as suspected.

### But the endpoint carries something better than the count

TVMaze `/shows/{id}/seasons` returns `premiereDate` **and** `endDate` per season, so the
exact set of calendar years is computable rather than approximated — same single API call:

```
S1  2003-11-02 → 2004-06-06     2003, 2004      ← season straddling two calendar years
S2  2004-11-07 → 2005-04-17     2004, 2005
S3  2005-09-19 → 2006-02-10     2005, 2006
S4  2013-05-26 → 2013-05-26     2013            ← Netflix single-day drop
S5  2018-05-29 → 2019-03-15     2018, 2019

union = { 2003, 2004, 2005, 2006, 2013, 2018, 2019 }  →  run_years = 7   (exact)
```

The union handles every case that breaks the alternatives: the 2003→2004 straddle that a
season count undercounts, the single-day Netflix drop, and the 2006→2013 revival gap that the
airdate span swallows whole. So:

```
aired_years = ⋃ years( premiereDate … endDate )  for each season
share       = | character appears_years ∩ aired_years |  /  | aired_years |
```

Both sides are now sets of calendar years, which resolves the unit objection properly rather
than by fiat. Intersecting also self-corrects data entry errors — an `appears` year outside
the show's aired years drops out instead of needing a clamp.

### TVMaze over TMDB

TVMaze wins on three counts: the API is already paid for and already plumbed in
(`cpts/class-tvmaze.php`, `calendar/class-tvmaze.php`, `theme/class-tvmaze.php`,
`grading/class-tvmaze.php`, `cron/tvmaze.sh`, plus an ACF group), its season records carry
end dates where TMDB's carry only premieres, and it has no `season_number: 0` "Specials" row
to filter out — verified on Game of Thrones, where TMDB reports `number_of_seasons: 8`
alongside nine `seasons` entries, the extra one being Specials with an `air_date` *preceding*
the show's own premiere.

### Keep the API out of the scoring path

Store the computed set as `lezshows_aired_years` meta, refreshed by the existing TVMaze cron.
Score calculation reads meta only and never makes a network call. This matters: it keeps the
"no third-party dependency in the scoring path" property, and it means a TVMaze outage can't
affect scores.

Fallback chain, in order:

1. `lezshows_aired_years` (TVMaze-derived) — exact
2. `finish − start + 1` minus `Σ hiatus_years`, if `docs/plans/show-hiatus-gaps-plan.md`
   lands — good
3. `finish − start + 1` — today's behaviour, wrong for revivals but never missing

TVMaze coverage will not be 100% (web series and international shows are the likely gaps),
so tier 3 has to keep working. The dry run should report which tier each show landed on.

**One consequence worth naming:** this makes the hiatus repeater unnecessary *for scoring* —
gaps fall out of the union for free. It's still worth building for the on-air display logic
the other plan targets, and as tier 2 for shows TVMaze doesn't cover. But this plan no longer
blocks on it.

### Caveat

TVMaze season data for very long-running soaps is the weak spot — the shows where run length
matters most for this scoring change are also the ones most likely to be recorded oddly. The
dry run should list the highest-season-count shows for manual eyeballing before cutover.

---

## Change 3 — the `trans` count guard (a no-op, deliberately)

Line 222 of `class-calculations.php` is an exclusion test with no floor:

```php
if ( ! isset( $char_terms['cisgender'] ) && ! isset( $char_terms['intersex'] ) && ! isset( $char_terms['unknown'] ) ) {
    ++$counts['trans'];
}
```

A character with **no `lez_gender` term at all** passes it and gets counted as trans, which
feeds the trans-score branch at lines 242–246 — a swing of `+10` or `−5` per character.

**But this is latent, not active: 100% of characters carry a gender term, and the weekly
debugger enforces it.** So the blast radius is zero, today and for as long as that check
keeps passing.

That changes what this should be. Earlier drafts of this plan gave it a feature flag, its
own dry-run columns, and a possible data-cleanup gate. All of that was scaffolding built on
an assumption that turned out to be false, and it's removed. What's left:

- Add the `$has_gender` floor as a cheap guard, so the invariant is enforced in the scoring
  code and not only in a scheduled report.
- Add a unit test asserting a character with no gender term is not counted as trans. The
  test is the actual deliverable — it converts an invariant currently maintained by a weekly
  job into one enforced at build time.
- **No feature flag. No CSV columns.** The dry run should report zero deltas from this; if it
  reports anything else, the debugger's coverage claim needs re-checking.

Deliberately **not** switching to an inclusive allowlist of trans/non-binary slugs.
`Character_Queer_Cast_Firsts` documents why — it avoids "hardcoding an exhaustive list" so
new gender terms keep working without code changes.

The `get_batch_character_terms()` rekey to `[$char_id][$taxonomy][$slug]` that an earlier
draft called "required" is likewise **optional**. It closes a theoretical slug collision
between `lez_cliches` and `lez_gender`, but no colliding slug currently exists. Do it if the
`$has_gender` check reads more cleanly for it; don't do it for safety reasons that aren't
real.

---

## Adjacent findings (not in scope — file as tickets)

Both of these turned up while tracing the character loops. Neither belongs in this plan, but
neither should stay unrecorded.

### 1. The Tambour Takedown is implemented only in dead code

`theme/class-show-characters.php` lines 290–299 implement an explicit editorial policy —
queer-IRL credit requires the **primary** (first-listed) actor to actually be queer IRL:

> *"We don't award shows that have cast a cis/het actor in a queer role."*

That check populates `$char_counts['quirl']`, returned only when `$output === 'queer-irl'`.
**Nothing in the codebase calls `get_characters_list()` with that output** — verified across
the repo; every call site uses `'query'`, `'count'`, or `'dead'`. The `'queer-irl'`,
`'trans'`, `'trans-irl'` and `'none'` branches are all unreachable.

Meanwhile the live scoring path, `class-calculations.php` line 219, counts the `queer-irl`
term with **no actor check at all** and awards `× 10` for each.

So the score currently grants full queer-IRL credit to shows that cast a cis/het actor in a
queer role — the exact case the policy was written to exclude — while a working
implementation of the check sits unused a few files away. Whether scoring *should* apply it
is an editorial call, not a refactor, and it is a bigger change to show scores than
longevity weighting. It deserves its own plan.

### 2. `get_characters_list()` writes post meta as a side effect of a getter

`build_character_list()` calls `update_post_meta()` for `lezshows_dead_count`,
`lezshows_char_count` and `lezshows_char_list` at lines 319–321 — **before** the `switch`
that decides what to return. Every call writes, whatever the caller asked for.

`do_the_math()` calls `get_characters_list( $id, 'query' )` three times per show (lines 185,
320, 515), so those three writes happen three times, and then `show_character_score()`
writes two of the same keys again at lines 465–467. Four writers, one show, one request.

The values agree, so this is not a correctness bug — it's pure waste, concentrated in
exactly the bulk-recalculation path this plan is about to run across every show. Memoising
`get_characters_list( $id, 'query' )` per request is a contained fix with no behaviour
change. Worth doing **before** Phase 3's bulk recalc, since it makes that run cheaper.

---

---

## Implementation

### Phase 0 — pure transform + tests (no behaviour change)

New `plugins/lwtv-plugin/php/cpts/shows/class-longevity.php`, namespace
`LWTV\CPTs\Shows`, following the precedent set by `Airdates::resolve()` — static, pure, no
WordPress calls, therefore unit-testable without a WP bootstrap:

- `aired_years_from_seasons( array $seasons, int $current_year ): array` — the year union
  from TVMaze `premiereDate`/`endDate` pairs; null `endDate` (season still airing)
  substitutes the current year
- `run_years( array $aired_years, string $start, string $finish, int $current_year ): int` —
  the tier 1/2/3 fallback chain; `max( 1, … )` so a single-year show is 1, not 0
- `weight( int $years, int $run_years ): float`
- `saturate( float $raw ): float`

New `tests/unit/CPTs/ShowLongevityTest.php` (sits alongside the existing
`AirdatesTest.php`; `phpunit.xml.dist` already covers `tests/unit`). Write these first.
Cases: zero years, one year, years exceeding the run, `current` finish, single-year show,
a season straddling two calendar years, a single-day season, a multi-year revival gap, null
`endDate`, empty seasons array falling through to tier 3, saturation monotonicity,
saturation never reaching 100. Plus the Change 3 guard: a character with no gender term is
not counted as trans.

### Phase 0b — memoise `get_characters_list()` (pure perf, zero deltas)

Fix adjacent finding #2 before anything else: `do_the_math()` calls
`get_characters_list( $id, 'query' )` three times per show, and each call re-runs three
`update_post_meta()` writes. Memoise per request. No behaviour change, and it makes Phase 3's
bulk recalculation materially cheaper — which is the run this whole plan depends on.

Confirm the dry run reports **zero** score deltas from this phase. If it doesn't, something
about those repeated calls was load-bearing and needs understanding before anything else
lands.

### Phase 1 — wire in behind a flag (default off)

In `class-calculations.php`:

- Add a per-request memo, `private static array $longevity_cache = []`, keyed by show ID —
  same pattern as the existing `$tax_scaffold`.
- Add `private function get_character_longevity( int $show_id ): array` returning
  `[ char_id => weight ]`. It reads `Airdates::get()` once for the run length, then walks
  each character's repeater rows.
- In `show_character_data()`, capture `$char_show['appears']` inside the loop that already
  matches `$char_show['show'] == $show_id`, populating the memo. **Zero new reads.**
- In `count_queers_all_types()`, read from the memo. Because `do_the_math()` calls
  `show_character_data()` (line 603) before `show_character_score()` (line 608), the memo
  is already warm. `count_queers()` can also be called standalone, so
  `get_character_longevity()` must lazily build if the memo is cold.
- Add the `$has_gender` floor to the `trans` count (Change 3). No flag — it's a provable
  no-op while gender coverage stays at 100%.
- Read `lezshows_aired_years` for the denominator, falling through the tier chain.
- Gate the new aggregation on one filter, `lwtv_score_longevity_enabled`, defaulting to
  `false`, so Phase 2 can compute both models side by side and Phase 3 is reversible without
  a deploy.

Also extend the TVMaze cron to compute and store `lezshows_aired_years`. That can ship
independently and ahead of everything else — it writes a new meta key nothing reads yet.

### Phase 2 — dry-run comparison and calibration

New `plugins/lwtv-plugin/php/wp-cli/cli-score-preview.php`. It must be its **own command
class**: `WP_CLI_LWTV_Calculate` is registered with an `__invoke()`, so WP-CLI treats it as
a single command and it cannot host subcommands.

```
wp lwtv score-preview --format=csv > movers.csv
```

Read-only — computes scores for every published show under **each flag combination** and
**writes no meta**. Output columns:

| Column | Why |
|---|---|
| show, format, character count | identification |
| `score_current`, `score_new`, `delta` | the headline |
| `grade_current`, `grade_new` | grade transitions — the real risk |
| `mean_longevity_weight` | sanity-checks the weight maths |
| `chars_missing_appears` | how often the role-proxy fallback fires |
| `run_years` | the new denominator |
| `run_years_source` | **tier 1 / 2 / 3** — which fallback each show landed on |
| `run_years_old` | the old span, so the revival correction is visible per show |
| `season_count` | to eyeball the long-soap records flagged in Change 2's caveat |

`run_years_source` is the column to sort by first: tier 3 shows are the ones where nothing
has improved, and if that group is large, TVMaze coverage — not the scoring maths — is the
thing to fix next.

Then calibrate `SATURATION_K` so the **median** show's score is roughly unchanged. This
matters: without it the change is a mass deflation rather than a re-ranking, and every
letter grade on the site slides down at once. Review the CSV against editorial judgement
before anything ships — if the top and bottom of the ranking don't look right, the
constants are wrong, and that is far cheaper to learn here than in public.

### Phase 3 — cutover

1. Flip `lwtv_score_longevity_enabled` to `true`.
2. Chunked bulk recalculation off-peak (`do_the_math()` per show is not cheap).
3. Invalidate: `invalidate_statistics_cache( 'score' )`, the week-long
   `character_longevity_leaders_*` transients, the `Statistics\Build\Scores` caches.
4. FacetWP reindex — `lezshows_the_score` feeds facets.

### Phase 4 — follow-ups

Review the `grading/` thresholds against the new distribution, and add a short public
methodology note. The score is the site's core data product; a visible change to it should
come with an explanation readers can find.

---

## Performance

Short answer: **no front-end impact at all.** Scores are precomputed and stored in
`lezshows_the_score`; page loads read meta and never run this code.

| Path | Cost |
|---|---|
| `appears` reads | **Zero new.** Already loaded by the existing `get_field()` call at line 519. |
| Airdate reads | 1–2 `get_post_meta` per show, already in the post cache. Negligible. |
| Weight math | Pure float arithmetic, no I/O. |
| Character meta | Already primed by `prime_character_caches()`. Unchanged. |
| Bulk recalc | One-time, chunked, off-peak. Plus one FacetWP reindex. |

The reason this is nearly free is that both consumers of the data are loops that already
run over exactly the right objects with the caches already warm. Any implementation that
adds a `get_field()` call per character, or a query per show, has gone wrong — that would
be the thing that slows the site down.

---

## Risks and edge cases

1. **Distribution shift → letter grades.** The headline risk. Mitigated by median
   calibration in Phase 2 and by the grade-transition column in the dry-run CSV.
2. **TVMaze coverage, not correctness, is the live risk.** Change 2 is exact where TVMaze has
   data and unchanged from today where it doesn't. So the failure mode isn't wrong scores,
   it's *inconsistent* ones — two shows scored on different denominators. Quantify via
   `run_years_source` before cutover. Related: long-running soaps are both the shows this
   change matters most for and the ones TVMaze records least reliably.
3. **Character in a show with empty `appears`.** Must **never** weight as zero — that would
   turn a documentation gap into a score penalty. Fall back to a role proxy (regular 0.7 /
   recurring 0.4 / guest 0.15) and log via `debug_log`.
4. **`appears` years outside the show's run.** Clamp `share` to 1.0 and log; a data-entry
   error should not produce a weight above 1.
5. **Redundant meta writes in the recalc path.** Adjacent finding #2. Not a correctness risk,
   but it multiplies the cost of Phase 3's bulk run. Phase 0b fixes it first.
6. **Multi-show characters.** Not a risk: `appears` is per repeater row, so per show. Worth
   an explicit test.
7. **Scoring still ignores the Tambour Takedown.** Adjacent finding #1. Out of scope, but
   worth knowing that after this plan ships, `queer-irl` will remain the one component of the
   character score that awards points without checking the thing the policy says to check.

---

## Non-goals

- No change to `show_score()`, `show_tropes_score()`, or the `/4` average in `do_the_math()`.
- **Format divisors stay as-is** (per decision). Noted for later: a longevity model already
  normalises for run length, so `/2`, `/1.5`, `/1.25` may now double-penalise short formats.
  Measure in Phase 2, decide separately. They apply to `X` *before* saturation, preserving
  current semantics.
- No ACF schema changes.
- No changes to `Character_Longevity_Leaders` — it stays a stats view; this plan does not
  couple scoring to it.
- Does **not** apply the Tambour Takedown to scoring, or resolve the getter-writes-meta
  design. Both recorded under Adjacent findings; both need their own tickets.
- Does **not** switch the gender test to an inclusive allowlist, and does not add a new
  revival ACF field — `lezshows_aired_years` is derived, not hand-entered.

---

## Open decisions

1. Calibrate `SATURATION_K` to hold the **median** or the **mean**? Median is more robust to
   the current pile-up at 100; recommend median.
2. Are guests at ≈ +0.7 `X` each still too generous? Decide from real data, not synthetic.
3. Should the dry-run CSV ship as a permanent `wp lwtv score-preview` command, or be removed
   after cutover? Recommend keeping it — it's read-only and makes the next scoring change
   much cheaper.
4. How large is the tier 3 (no TVMaze data) group? If it's substantial, does cutover wait on
   improving TVMaze ID coverage? Recommend yes — inconsistent denominators across shows are
   harder to explain to readers than a delayed change.
5. Should `lezshows_aired_years` store the full year set or just the count? The count is all
   the score needs, but the set makes the `appears ∩ aired` intersection possible and would
   let This Year in Review stop guessing. Recommend the set.
6. Does the Tambour Takedown ticket (adjacent finding #1) come before or after this plan?
   It's a larger score change than longevity weighting, so doing it after means recalibrating
   `SATURATION_K` twice. Worth deciding the order up front.
