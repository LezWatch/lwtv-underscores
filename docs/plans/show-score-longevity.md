# Plan: Longevity-Weighted Character Scoring

**Goal:** stop headcount driving a show's character score, so a 50-year soap that cycled
through 200 one-episode characters no longer outranks a tightly-written five-season drama.
What replaced it turned out to be larger than longevity weighting alone — see Diagnosis.

**Status:** built, wired in, and **switched off**. Both models live in one class that the live
calculation and the preview command share; the new one runs only when its filter says so, and
both filters default to `false`. Nothing has changed for a reader yet.

| | |
|---|---|
| `cpts/shows/class-longevity.php` | the pure model — weights, guard, saturation |
| `cpts/shows/class-character-score.php` | one `gather()`, both scoring models |
| `cpts/shows/class-calculations.php` | calls `Character_Score`, selects on the flags |
| `wp-cli/cli-score-preview.php` | presentation only; forces both gates on |
| `tests/unit/CPTs/ShowLongevityTest.php` | green |
| `tests/unit/CPTs/CharacterScoreTest.php` | green |

```php
// Both default false. Flip together; see Sequencing.
add_filter( 'lwtv_score_longevity_enabled',   '__return_true' );
add_filter( 'lwtv_score_actor_check_enabled', '__return_true' );
```

---

## Diagnosis, corrected by the data

This plan was built on the clamp at 100. **Measured across all 2255 published shows, that
was the wrong end of the distribution.**

| old character score | shows |
|---|---|
| ≤ 0 | 202 (9%) |
| ≤ 10 | 1,170 (**52%**) |
| ≤ 20 | 1,656 (73%) |
| **negative** | 133 (5.9%), worst −19 |
| clamped at 100 | **38 (1.7%)** |

Median old character score: **10.0**, against a nominal range of 0–100. The component was not
saturating, it was sitting on the floor — contributing almost nothing to the four-way average
except when it went negative and actively subtracted. Killing Eve scored 19.25 overall on a
character component of **−6**.

The old model had no lower bound at all: `min( 100, … )` capped the top and left the bottom
open, so `dead × −5` and `(trans − trans_irl) × −5` could drive a show below zero.

Both diagnoses point at the same fix — the component needed a bounded, saturating shape — but
the reason matters for calibration. At `SATURATION_K = 10` the new median character score is
**35**, against a median of **69** for the three components it is averaged with. So this
constant governs how much the character component weighs in the average *at all*, not merely
how shows rank within it — which is why calibrating it to hold the total median fixed is
self-defeating. See Calibration.

### The original diagnosis, which still holds for the top 1.7%

The original framing was that more characters means a higher score. The real mechanism was
narrower, and finding it changed the whole design.

`count_queers_all_types()` builds an **unbounded sum** — `regular×5 + recurring×2 +
guest×1`, plus `queer-irl×10`, `none×5`, `dead×−5` — then clamps to 100 at line 266.
Measured on Transparent (show 655):

| old term | points | share |
|---|---|---|
| base (roles) | +41 | 19% |
| **queer-irl bonus** | **+190** | **90%** |
| no-clichés | 0 | 0% |
| dead penalty | −10 | 5% |
| trans adjustment | −10 | 5% |
| **uncapped total** | **211** | **2.1× over the cap** |

So the character score was **100 because 19 queer-irl characters × 10 points overran a
100-point ceiling on their own.** Roles, deaths, longevity: all inert noise below the clamp.
The component carried no information.

Three consequences shaped the model:

1. Weighting each character by longevity while keeping the sum-and-clamp would have moved
   nothing — the soap still saturates.
2. The dominant term was a flat +10 per queer-irl character, **double** the 5 points a
   series lead was worth. Casting outranked prominence 2:1 everywhere.
3. That +10 was awarded with **no actor check at all**, while a working check sat unused in
   dead code (see Casting).

---

## The model

### Per-character value: role points, scaled

Role points are the base. Every quality **multiplies** rather than adds:

```
value = ROLE_POINTS[role]                  // 5 regular / 2 recurring / 1 guest
      × casting_multiplier                 // 0.5 – 2.0, one signal (see Casting)
      × (1 + NO_CLICHES_BOOST if none)     // 1.25
      × (DEAD_FACTOR if dead)              // 0.5
```

Multiplying is what keeps prominence meaningful — a queer actor cast in a lead is worth more
than one cast in a single scene, and killing a lead costs more than killing a walk-on. Under
the old flat `+10`, a one-scene guest and a series lead got the identical reward, which is
how Barb (guest, 2 of 5 years) came to outscore Ari (regular, 5 of 5) 4.73 to 4.69.

It also guarantees **`character_value()` can never return a negative.** The old flat `−5`
made a dead guest net `−4`, so documenting a dead one-scene queer character *lowered* a
show's score. Penalising thorough documentation is the wrong incentive for this site.

### Longevity weight: how long that presence lasted

```
share  = min(1, character_years / run_years)
curve  = sqrt(min(character_years, ABSOLUTE_CAP) / ABSOLUTE_CAP)
weight = SHARE_WEIGHT × share + CURVE_WEIGHT × curve        // (0, 1]
```

**Role and longevity compose rather than compete.** Role is intensity of presence *within* a
year; the weight is how many years it lasted. Their product approximates total screen time,
so there is no role-vs-longevity dial to tune.

The curve term exists for fairness across run lengths: share alone would score a maximally
present web-series character (1.00) above a 20-year show's five-year regular (0.25), and
flatten every character on a long show.

### Show aggregate: a saturating curve

```
X     = Σ (value × weight)
X     = X / format_divisor        // movie ÷2, mini ÷1.5, web ÷1.25 — unchanged
score = 100 × X / (X + SATURATION_K)
```

Deliberately **not an average.** An average divides by the roster, so every one-episode
guest drags the score down — modelled, a 30-character soap with six excellent 20-year
regulars fell to **19**. Same anti-documentation problem as the flat dead penalty.

The saturating curve gets the same outcome without it: short-tenured characters contribute a
small positive amount and never punish, while the curve flattens so no amount of headcount
buys a top score. Volume gets *diminishing returns* rather than a penalty. It also retires
the hard clamp in favour of a smooth asymptote, which spreads out the shows currently stacked
at exactly 100.

---

## Casting: one signal

Queer-irl and trans casting both reward authentic casting. They must **not** stack.

An earlier iteration had them as separate multipliers, which compounded to ×4 and overtook
the entire role hierarchy — Davina (recurring, both boosts, 6.18) beat Ari (regular, neither,
4.69). It also reduced Maura Pfefferman twice for one fact (a cis, non-queer actor cast as a
trans woman), dropping the show's title character to 0.97, *below a single-episode guest on
0.98*. A protagonist scoring under a walk-on was the tell.

So `casting_multiplier()` picks **one** standard by role:

| character | standard | primary actor passes | fails |
|---|---|---|---|
| trans or non-binary | trans/NB casting | ×2.0 | **×0.5** |
| cis | queer casting | ×2.0 | ×1.0 |
| unclassified | none | ×1.0 | ×1.0 |

A test asserts the result never leaves `[0.5, 2.0]` — that is the regression guard against
the ×4 problem returning.

Sharpest edge, deliberate: a trans character played by a **cis queer** actor lands at ×0.5.
The queer-irl boost neither offsets nor compounds; it simply does not apply to a trans role.

### The Tambor Takedown, finally applied

`theme/class-show-characters.php` lines 290–299 implement an explicit policy — queer-irl
credit requires the **first-billed** actor to actually be queer:

> *"We don't award shows that have cast a cis/het actor in a queer role."*

**Nothing in the codebase reaches it.** Verified across the repo: every caller of
`get_characters_list()` uses `'query'`, `'count'` or `'dead'`, so the `'queer-irl'`,
`'trans'`, `'trans-irl'` and `'none'` branches are unreachable. Meanwhile
`class-calculations.php` line 219 counted the term and awarded ×10 with no actor check.

The score therefore granted full queer-irl credit to shows that cast a cis/het actor in a
queer role — the exact case the policy excludes. This plan applies it for the first time.

### Gender classification: three states, not a boolean

Characters are classified from their `lez_gender` terms into `trans-or-nb`, `cis`, or
`unclassified`. Non-binary and genderqueer are held to the trans/NB casting standard.

**The third state is the point.** An allowlist answering only yes/no would treat any term it
has never seen as cis, so adding a gender term to the taxonomy would silently stop assessing
those characters — a show could quietly cease being checked with no error. `unclassified`
scores a neutral 1.0 and raises a `WP_CLI::warning` naming the slugs.

This is a deliberate departure from `Character_Queer_Cast_Firsts`, which avoids "hardcoding
an exhaustive list" so new terms keep working. An allowlist trades that for precision; the
unclassified bucket makes the cost loud rather than silent. It earned itself immediately —
the first run surfaced `non-binary` and `genderqueer`, with a non-binary series lead among
the affected characters.

### The actor side needed its own check

`Queeries\Is_Actor_Trans` decides with `strpos( $slug, 'trans' )`, which **structurally
cannot see** an actor tagged `non-binary` or `genderqueer`. Since non-binary characters are
now held to a casting standard, using it would have docked every show that cast a non-binary
actor correctly.

`Longevity::actor_is_trans_or_nb()` keeps that substring rule — so future `trans*` slugs
still match with no code change — and adds an explicit non-binary list. `Is_Actor_Trans` is
left untouched: it feeds `count_queers_all_types()` and `class-show-characters.php`, and
widening it would move existing counts.

⚠ `ACTOR_NONBINARY_SLUGS` has **no unclassified bucket**. An actor whose slug is missing
reads as cis and produces a false miscast indistinguishable from a real one. Mitigation: the
preview prints a **miscast verdicts** table with the actor's actual slugs, since miscasting
is the only place this model actively docks a show. Verify that column before trusting a
×0.5.

---

## Constants

Each has a plain-language test, so they can be retuned without re-deriving the reasoning.

| constant | value | reads as |
|---|---|---|
| `SHARE_WEIGHT` / `CURVE_WEIGHT` | 0.7 / 0.3 | must sum to 1.0 |
| `ABSOLUTE_CAP` | 8 | years at which the curve term reaches 1.0 |
| `QIRL_BOOST` | 1.0 | *"casting a queer actor is worth as much as doubling this character's screen time"* |
| `TRANS_BOOST` | 1.0 | same, for trans/NB roles |
| `TRANS_MISCAST_FACTOR` | 0.5 | *"cis-casting a trans role halves what that character contributed"* |
| `DEAD_FACTOR` | 0.5 | *"killing a character halves everything they contributed"* |
| `NO_CLICHES_BOOST` | 0.25 | secondary by design |
| `SATURATION_K` | **15.0, calibrated** | raw value equal to this scores 50 |

`SATURATION_K` was calibrated against all 2255 shows. Two display boundaries pull opposite
ways:

| K | median | vs old | Failing (<20) | 90+ Club | colour flips | deciles moved |
|---|---|---|---|---|---|---|
| 9 | 59.87 | +5.37 | 60→47 | 16→13 | 195 | 55% |
| **15** | **57.42** | **+2.92** | **60→50** | **16→8** | **143** | **37%** |
| 20 | 56.26 | +1.76 | 60→52 | 16→6 | 113 | 28% |
| 30 | 55.09 | +0.59 | 60→55 | 16→**1** | 92 | 20% |

⚠ **This table records how K was FIRST chosen, and that reasoning has since been superseded.**
It treats the total median as the thing to protect and picks 15 as a compromise against the
90+ Club. Both halves turned out to be wrong: the median is the wrong target (it can only be
held by keeping the character score on the floor), and 12 of the 16 shows in that club were
clamp artifacts rather than a top tier worth preserving. See "Calibration: the objective was
wrong before the number was" below for the replacement, and `SATURATION_K = 10.0`.

**Never calibrate from one show.** The earlier 9.0 came from Transparent alone and was off by
6×. That is the error this plan warned about and then committed.

### There are no letter grades

Worth recording, because earlier drafts of this plan and much of the discussion around it
assumed otherwise. Nothing in the repo maps `lezshows_the_score` to a letter. The real
display boundaries are:

- `< 20` → "Failing Grades" and `>= 90` → "The 90+ Club", in
  `statistics/build/class-score-distribution.php::tails()`
- a decile histogram, 0–9 … 90–100, same file
- a continuous colour ramp flipping at ≈51 in `_components/class-grading.php::color()`

`class-calculations.php:391` is the only place a number is equated to a letter (`70` = "a C"),
and that is a comment on a default inside the trope score, not a display mapping.

An incidental calibration statement worth accepting or rejecting: with `QIRL_BOOST = 1.0`,
queer casting is worth exactly one role tier — Tammy (recurring, not queer-cast) and Barb
(guest, queer-cast) tie at 0.86. That falls out of the 1/2/5 role points by coincidence.

---

## The run-length denominator

`share` divides by the years a show was *actually on screen*. Four tiers:

| tier | source | accuracy |
|---|---|---|
| 1 | `lezshows_seasons`, **finished shows only** | good |
| 2 | TVMaze `premiereDate`/`endDate` union | exact, when complete |
| 3 | airdate span less known hiatus years | fair |
| 4 | raw airdate span | today's behaviour |

The tier numbers above are the code's, in `Longevity::run_years()` — curated first, exact
second. An earlier revision of this table had 1 and 2 transposed, and so did the CLI's
summary labels, which reported 1,813 shows as using "exact years" when not one of them did.

**Tier 2 was the right call and my objection to it was wrong.** I argued "unit mismatch" —
`appears` is in calendar years, seasons are seasons — when the question is which
approximation is *less* wrong. On Arrested Development: span says 17, season count says 5,
reality is 7. On Transparent: span says 6, season count says 5, reality is 5, because no
season landed in 2018. The meta is already there and costs nothing.

Capped at the span, because years aired can never exceed premiere-to-finale while a season
count can — streaming shows drop two seasons in one calendar year. Still-airing shows are
excluded: a season currently on air may not be counted yet.

Tier 1 is better still where TVMaze has data, since `premiereDate`/`endDate` gives the exact
year set — handling straddling seasons, single-day drops, and revival gaps in one pass, with
`share` becoming `|appears ∩ aired_years| / |aired_years|` (both sides calendar years, which
resolves the unit objection properly). Store as `lezshows_aired_years` via the existing
TVMaze cron so no API call ever enters the scoring path.

**This makes the hiatus repeater unnecessary for scoring** — gaps fall out of the union for
free. Still worth building for the on-air display logic `docs/plans/show-hiatus-gaps-plan.md`
targets, and as tier 3 where TVMaze has nothing.

### The tier-2 plausibility guard, and what measuring it disproved

An incomplete aired-years set is worse than no set at all, because it is used twice and both
uses break in the same direction:

- as the `run_years` denominator, where a short set **inflates** every weight;
- intersected against each character's `appears`, where every year TVMaze does not list is
  **silently discarded**.

The second is the serious one. Measured on the live data, 13 shows had their denominator
shrink while their mean character weight *also fell* — a smaller denominator can only raise
weights, so the only explanation is the intersection throwing away real screen time. Mostly
long-running international soaps: Gute Zeiten schlechte Zeiten, Unter Uns, Ros na Rún,
Salatut elämät, Waterloo Road.

Three signals now vet the set before anything uses it. Two were built on reasoning; the third
was built after measuring how badly the reasoning did.

| signal | test | caught | verdict |
|---|---|---|---|
| 1 | fewer dated years than recorded seasons | 4 of 13 | `seasons` |
| 2 | the set starts materially later than the show did | 2 of 13 | `late-start` |
| 3 | the set cannot account for the years characters are credited in | the rest | `coverage` |

**Signal 2 was built on an assumption the data disproved.** I reasoned that TVMaze back-fills
recent seasons first, so an incomplete set would *start late* — and that this would be the
workhorse, since airdate coverage is 100% so it can judge every show. It caught two, both
small-span. GZSZ has 9 dated years across a 35-year span and the set **starts at the
premiere**; the holes are in the middle and the end. The shape signal 2 looks for is real, but
it is the rare case, not the common one. It is kept because it is free.

Signal 3 works because it stops predicting the damage from the set's shape and measures it
directly. Both a revival gap and a data gap produce a set with holes; what separates them is
whether anyone was on screen inside the hole:

- **Revival gap** — The X-Files, 1993–2002 then 2016 and 2018. No character is credited inside
  the hole, because nothing aired. Coverage is near-total. The set is real data; keep it.
- **Data gap** — characters credited squarely inside the hole. That is proof the show *was*
  airing there and TVMaze has no season dated for it. Reject it.

There is no third explanation for a credited year outside the aired set: either `appears` is
wrong or `aired_years` is incomplete. Volume is what tells them apart, so the test is a ratio
(`COVERAGE_MIN`) rather than a hard zero, with an evidence floor (`COVERAGE_MIN_EVIDENCE = 5`)
sized so a single `appears` typo can never on its own reject a set — one bad year out of five
leaves 0.80, above the threshold.

⚠ **`COVERAGE_MIN = 0.75` is provisional and must not ship as a guess.** `SATURATION_K` was
first set to 9 from a single show and was 6× too low; the same mistake is available here.
`score-preview --all` now prints a coverage histogram over every show the signal can judge.
The threshold belongs in the sparse band between the pile at 1.00 and the broken sets — and if
there is no sparse band, signal 3 is measuring a continuum and needs rethinking rather than
retuning. A boring histogram is a real answer.

Because signal 3 needs the character data, `gather()` now runs a pre-pass that reduces each
character's show-group rows to their credited years and strongest role *before* the set is
vetted. The rows are read once, not twice: the scoring loop consumes what the pre-pass
produced. Signal 3 is opt-in via the `$credited_years` argument, so a caller that has not
gathered characters gets signals 1 and 2 rather than a silently weaker check that looks like
the full one.

### Calibration run: what the histogram actually said

Signal 3 works — 17 rejections against the old 6, and every long-running soap that was losing
screen time is now caught. But **the histogram did not produce the clean bimodal split the
design assumed**, and the threshold is still an open decision rather than a measured one.

Of 400 shows holding a TVMaze set, 305 have fewer than 5 credited years, so the evidence floor
abstains on 76% of them. That is the floor working as intended — those are short shows where a
truncated set costs little — but it means signal 3 judges 95 shows, not 400.

Of those 95: **70 sit at exactly 1.00**, and 25 spread almost continuously from 0.06 to 0.94.
The widest gap in the whole distribution is **0.24 → 0.53**, nowhere near 0.75. The gap the
current threshold sits in is **0.74 → 0.80**, six points wide in a 95-show sample.

So there are two defensible readings, and they disagree about ten shows:

| threshold | lands in a gap of | rejects | leaves behind |
|---|---|---|---|
| **0.75** (current) | 0.06 | 13 | nothing material |
| **~0.40** | 0.29 | 3 | Home and Away (9 discarded years), Salatut elämät (6), Fair City (5) |

The evidence that settles it is not the ratio, it is **Home and Away**: coverage 0.53, 19
credited years, 9 of them discarded, and the show has aired continuously since 1988. A
continuously-airing show has no hiatus for the `appears` data to be loose about, so every
credited year outside the set is missing data by elimination. A 0.40 threshold would let that
through. **0.75 is therefore the better of the two, on a mechanism argument rather than on the
histogram.** Recorded that way deliberately: the number is defensible, the histogram is not
what defends it.

⚠ **The ambiguity that remains is real.** Rick and Morty (0.85, 2 discarded) and Doctor Who
(0.85, 3 discarded) are almost certainly the *opposite* case — a genuine hiatus with `appears`
marked as a continuous range, where the set is right and the intersection is correctly
dropping years. Nothing currently distinguishes those from a data gap of the same ratio.

### Two attempts to sharpen the guard, both abandoned on evidence

Signal 3 was built, and then two further signals were designed to resolve the Rick and Morty
ambiguity above. **Neither shipped.** Both are recorded here because a negative result that
took real work is worth more written down than rediscovered.

**Attempt 1 — hole location.** The idea: a discarded year *outside* the set's range is
unambiguous missing data, while one inside an internal hole is an ambiguous hiatus. Measured
on the two reference cases:

| | set | credited | discarded outside range | discarded in a hole |
|---|---|---|---|---|
| GZSZ (bad set) | 9 yrs | 26 yrs | **0** | 26 |
| Rick and Morty (good set) | 11 yrs | 13 yrs | **0** | 2 |

Both put *everything* in a hole and *nothing* outside the range. The signal assigns the case
it was built to catch and the case it was built to spare to the identical bucket. It carries no
information at all.

**Attempt 2 — record size.** The idea: reject when our own `appears` data documents materially
more years than TVMaze does (ratio > 1.5, absolute excess ≥ 2). This looked strong — it
separated GZSZ (26 against 9) from Rick and Morty (13 against 11) cleanly, and rejected none of
the 360 shows currently accepted on tier 2. It is **provably redundant**:

```
ratio > 1.5   ⟹   coverage = |C ∩ A| / |C|  ≤  |A| / |C|  <  1 / 1.5  =  0.667
```

Always below `COVERAGE_MIN`. Signal 3 has therefore already rejected every set this would
reject, and it could only ever contribute below coverage's 5-credited-year evidence floor.
Measured there: of 304 such shows, **zero** qualify — every one has `|C| ≤ |A|` or an excess of
exactly 1. It fires on nothing, now or in this corpus.

Not shipped, because an unreachable guard that looks like protection is worse than no
guard — the Tambor Takedown sat in dead code for years being exactly that. A test asserts
`COVERAGE_MIN > 1/1.5`, so if the threshold is ever lowered past the point where a size
comparison becomes reachable, the suite says so.

**So the guard is what it is.** Coverage at 0.75, resting on the Home and Away mechanism
argument, with roughly ten shows in the 0.80–0.94 band whose sets may or may not be right and
where the cost of being wrong is 1–3 discarded years each. That is the accurate description
and it is not going to improve without better source data.

What the run did add is auditability: the CSV now carries `aired_set` beside `credited_years`,
plus the `disc_outside`/`disc_hole` split, so any single rejection can be checked by hand
rather than taken on the verdict's word.

### Two reporting bugs the calibration run exposed

Neither changed a score. Both made the output lie about scores, which is worse in a document
whose whole purpose is deciding what to trust.

**1. The tier column was wrong for 12 shows.** Caught by an invariant: tier 1 returns
`min( seasons, span )`, so a tier-1 denominator can never exceed the season count — and 12
rows had `run_years > seasons`. Cause: the preview command re-derived the tier with
`Airdates::is_still_airing()` while `run_years()` decides with `$finish_year >= $current_year`
inline. On currently-airing shows (Euphoria, Heartstopper, Industry, The Chi…) the two
disagreed, so the CSV reported "tier 1, curated season count" for denominators that had come
from TVMaze.

Fixed by making the choice reportable instead of re-derivable: `run_years_detail()` returns
`years`, `tier`, `still_airing` and `span`, and `run_years()` is a wrapper over it. **This is
the third bug in this project caused by two copies of one decision** — after the transposed
tier labels and the two implementations of the model itself. The pattern is worth naming.

Tier 3 also turns out to have been unreportable: it is the span *less hiatus years*, and with
no hiatus data anywhere it is indistinguishable from tier 4 unless something subtracted. It
now reports 3 only when a gap was actually removed.

**2. `coverage` printed `0.000` for 1,855 shows that have no TVMaze set at all.**
`appearance_coverage()` returns 0.0 when there is nothing to compare against, which reads as
catastrophic coverage rather than "not measurable". Now blanked to `-`. The histogram already
excluded them, so no conclusion was affected — but any reader sorting the CSV by coverage
would have found 1,855 false catastrophes above the 13 real ones.

### An unplanned benefit: the guard catches wrong-show matches

**Family Law (Canada)** was rejected on coverage 0.67 — and it is one of the nine shows the
user identified as a TVMaze *name collision*, where an older same-titled show exists. Its
aired years may be another show's entirely. Signal 3 cannot know that, but it does notice that
the years do not fit the characters, which is the observable consequence.

So the coverage check doubles as a weak detector for bad TVMaze matches, not just incomplete
ones. Worth remembering when the TVMaze matching UX gets built: `aired_verdict = coverage` on a
show with a confident ID match is a hint the ID is wrong.

---

## Worked example: Transparent (#655)

Verified end-to-end. The preview recomputes the OLD total from scratch and warns if it
diverges from stored meta by >0.01; it reproduced 93.08 exactly.

```
Run years:   5  (season count, tier 2 — no lezshows_tvmaze_id)
Judged on:   14 trans/NB casting, 9 queer casting, 0 unclassified
Trans/NB:    14 characters, 12 correctly cast, 2 miscast

component          old      new
Show rating     115.00   115.00   unchanged
Tropes           66.00    66.00   unchanged
Alive ratio      91.30    91.30   2 dead of 23
Character score 100.00    75.47   raw X = 27.69 | OLD WAS CLAMPED AT 100
TOTAL            93.08    86.94   −6.13
```

Both miscast verdicts confirmed genuine from the audit table:

| character | gender | actor | actor gender |
|---|---|---|---|
| Ari Pfefferman | non-binary | Gaby Hoffmann | cis-woman |
| Maura Pfefferman | trans-woman | Jeffrey Tambor | cis-man |

Transparent cast cis actors as both its trans lead and its non-binary lead. The score now
reflects that; the old model awarded Maura the full +10 with no actor check.

Ranking sanity: Sarah (regular, 5 of 5) tops the show at 4.69, then Davina and Shea (trans
women, trans-cast, 4 years) at 3.09, then Ari at 2.34 — a lead, halved for casting. Guests
sit at 0.25–0.86. Maura at 0.97 is above a one-episode guest (0.49), which is the inversion
the single casting signal fixed.

---

## Verification status

| check | state |
|---|---|
| `vendor/bin/phpunit` | ✅ **82 tests, 241 assertions, green** (PHP 8.5.9) |
| `composer lint` | ✅ clean |
| OLD column reproduces stored meta | ✅ exact on Transparent (93.076) |
| character gender list vs live taxonomy | ✅ all slugs classified, no warnings |
| actor gender list vs live taxonomy | ✅ all 31 slugs triaged: 21 trans/NB (429 actors), 4 cis (5,596), 6 neutral (45) |
| `SATURATION_K` | ✅ calibrated to 15.0 across 2255 shows |
| `appears` coverage | ✅ **1 character missing of 7,513** — the role-proxy fallback is effectively dead code |
| tier 2 (TVMaze exact years) | ❌ **never exercised** — 0 of 2255 shows; see below |
| `NO_CLICHES_BOOST` | ❌ unexercised on any show inspected so far |

### The credited-years floor

Found in a live run, and the clearest illustration of tier 1's known cost actually biting.
**The L Word: Generation Q**, from the preview:

```
seasons 3   span 5   credited_years 5   tier 1   ->   run_years 3
```

Three seasons across five calendar years, so the curated count said 3 while our own character
records span 5. Every character with three or more years then had `share` capped at 1.0 — a
three-year character became indistinguishable from one present the entire run. The show carried
`mean_weight 0.588` and `char_new_raw 116.6`, the largest X in the corpus, and was the only show
whose uncapped total cleared 100 (100.43).

**A denominator narrower than the span of its own numerators is internally inconsistent**, so
`run_years` is now floored at the count of distinct credited years. For that show, 3 → 5, and a
three-year character's weight goes 0.884 → 0.604, a 32% drop for the same screen time honestly
measured.

Two bounds, both deliberate:

- **Capped at the span.** A show cannot have aired across more calendar years than lie between
  premiere and finale, so a mistyped `appears` year cannot run the denominator away. What
  remains of such a typo is +1 on the denominator, which *lowers* the score — the safe
  direction, since a data error should never flatter a show.
- **Never applied to tier 2.** Where actual per-season air dates exist, that set is the
  authoritative statement of which years the show existed, and `character_years()` already
  intersects against it — so the numerator cannot exceed the denominator and there is nothing to
  inflate. Raising it above `|aired|` would mean dividing by years the show demonstrably did not
  air. The floor is for denominators derived from something *other* than air dates, which is
  exactly where undercounting happens.

`run_years_detail()` returns `floored`, and the preview reports it per row and in the summary.

### Measured: the floor fires on 292 shows, not one

It was built for a single show and it corrects **292 — 13% of the corpus.** Badly undersold when
it was proposed. A sample of what it caught:

| show | recorded seasons | credited years | denominator |
|---|---|---|---|
| Naruto: Shippûden | 1 | 11 | 1 → **11** |
| Marienhof | 9 | 17 | 9 → **17** |
| Absolutely Fabulous | 5 | 10 | 5 → **10** |
| Bad Girls | 5 | 8 | 5 → **8** |
| Steven Universe | 5 | 7 | 5 → **7** |

So the tier-1 undercount was never a Generation Q quirk — it is systematic, and it was inflating
`share` across an eighth of the database. Every one of these gets a *lower* character score now,
correctly, because the denominator was too small.

**It also doubles as a data-quality signal**, which is why the summary now reports the count:
292 shows have a `lezshows_seasons` value materially below the calendar years they actually aired
across. Anime is heavily represented (a whole series recorded as "1 season"). Worth a debugger
check of its own eventually — the floor makes the score right, it does not make the meta right.

### Verified against a live run

`SATURATION_K = 10`, both flags on, floor active, trigger sign corrected, full corpus:

| | old | new |
|---|---|---|
| median | 54.25 | **58.98** (+4.73) |
| mean | 51.61 | 56.26 |
| Failing (<20) | 65 | 49 |
| 90+ Club | 16 | 11 |
| up / down | | 2052 / 195 |

**The offline sweep method is validated**: it projected +4.91 against +4.87 pre-trigger-fix, and
predicted the 90+ club with the same eight leavers and four joiners. So K can be re-derived from a
CSV without another full run.

### The trigger warning warning was overcautious, and I should have seen why

I flagged the trigger fix as possibly killing the "most scores went up" story, because a high
trigger swings 30 points on `show_score()` — −7.5 on the total. Measured: **189 shows (8.4%)
moved**, in clean steps of −2.5 (88 shows, low), −5.0 (52, medium) and −7.5 (48, high).

But the delta barely budged: **+4.87 → +4.73**, and up/down stayed at 2052/195.

The reason is structural and I had already described it several times without joining it up: the
trigger term lives in `show_score()`, which is one of the three components present in **both** the
old and new totals. It shifts them together, so it changes the absolute scores and leaves the
*difference* between the two models almost untouched. Verified per show — the drop is identical on
old and new for 188 of the 189, the exception being Wonder Egg Priority, where the old score hits
the 0 floor and truncates.

Lesson worth keeping: a change to a *shared* component cannot alter a before/after comparison of
the component beside it. Only changes inside the character score can move that delta.

### Final figures for the public write-up

Superseding everything quoted from `movers4` or `scores2`:

| show | old | new |
|---|---|---|
| Law & Order: SVU | 40.17 | 31.32 |
| Adventure Time | 64.75 | 77.25 |
| Killing Eve | **16.75** | 29.96 |
| American Horror Story | 55.91 | 45.40 |

Killing Eve's old score is now 16.75 rather than 19.25 — it carries a trigger warning, so the
correction pushed it *further* below the 20-point failing line. The example got stronger.

One show still exceeds 100 uncapped — The L Word: Generation Q at 100.24, down from 100.43. I
predicted the floor would eliminate it and it did not: X fell from 116.6 to 105.7, but that
still gives a character score of 91.4, and its other three components sum to 309.6 because
`show_score()` alone can reach 115.

**That is fine, and the uncapped value is now kept.** Editorial call: over-100 behind the scenes
is acceptable. Previously it was not merely acceptable but invisible — the clamp ran *before*
`update_post_meta()`, so the true value was discarded and nothing could distinguish two shows at
the ceiling. That is the old character-score clamp all over again, one level up, and the fix is
the same: keep the number.

- `lezshows_the_score` — **still clamped to 0–100.** Every consumer reads it (display, the stats
  SQL, `Grading`, of-the-day, the taxonomy queries) and none of them should have to learn about a
  0–115 range.
- `lezshows_the_score_uncapped` — the true value, no ceiling, not in REST.

With one show over 100 this buys almost nothing today. The point is that it cannot silently start
creating ties again as the data improves.

⚠ **`lezshows_score` (no "the") is a dead key.** It is deleted in `do_the_math()`'s bail branch
and never written or read anywhere. Left alone rather than tidied, since removing a `delete` is
the one edit that could strip a value someone's install still has, but it is not a thing to
build on.

### What actually distinguishes the top shows now

Worth stating carefully, because it is easy to get backwards. It is **not** the headroom above
100 — that is one show. It is that **the pile-up at 100 is gone**: the old model had 38 shows
with a character score pinned at exactly 100 and several tied at a total of 100, and now exactly
one show reaches 100 at all. The differentiation is below the cap, not above it.

### Shows with no characters are now flagged forever

Seven shows have no queer characters recorded, and that is **correct** — for two we do not have
the data yet, and the rest only ever had background or unnamed characters. They are now surfaced
by `Debugger\Shows` permanently rather than being silently fine, because the gap is a thing to
eventually fill rather than a state to accept.

It also matters more than it used to: with the character score weighted by screen time, a show
with no characters has no character component at all and is effectively scored on three of the
four parts.

Implementation note: `lezshows_char_count` comes back as the string `'0'`, and `empty( '0' )` is
`true` in PHP, so the existing meta check in `ITEMS_TO_CHECK` flags it with no new code. That
reads like an accident, so it is commented as intentional — it also catches a missing key, which
means the show has never been calculated, and that is worth surfacing too.

### Found while rewriting the public scoring page: trigger warnings were ADDING points

Auditing the documentation against the code turned up a sign inversion that had nothing to do
with this project:

```php
$trigger_score = array( 'high' => 15, 'med' => 10, 'low' => 5 );
$score += ...
```

A high trigger warning was worth **+15**. The public scoring page has always said −15, and has
always stated the intent outright — *"If a show is actively detrimental to some viewers, with
abuse, or excessive violence, its score is downgraded."* Nothing negated it downstream. Confirmed
as never intended and corrected to −15 / −10 / −5.

⚠ **This changes scores in the opposite direction to everything else in this release, and by a
lot.** A show's trigger term swings by twice its value — high goes from +15 to −15, a 30-point
move on `show_score()`, which is **−7.5 on the total** after the four-way average. Medium is −5,
low is −2.5.

That is the same order of magnitude as the +4.87 median rise from the character work, pointing the
other way. So:

- **Every number measured before this fix is stale**, including `scores2.md` and the figures
  currently in the draft blog post.
- **The blog post's central claim — "most scores went up" — may not survive it.** It depends
  entirely on how many shows carry a trigger warning, which has not been measured. Do not
  publish those numbers without a fresh `score-preview --all`.
- `SATURATION_K` itself is unaffected in principle: the trigger term lives in `show_score()`, a
  different component, and K is calibrated on the character component's own distribution. But the
  *total* median it is reported against will move, so re-read that line rather than the old one.

Also corrected on the same page, all pre-existing documentation drift rather than code bugs:

| the page said | actually |
|---|---|
| "The scores all total 100 each" | Show Ratings maxes at **115** and is never individually capped; only the four-way average is |
| BYQ "automatically loses 1/3rd" | ×0.66 without a happy ending, **×0.75 with one**, and `lezshows_byq_override` can cancel it entirely |
| tropes are a pure ratio | no tropes or `none` → **80**; only Regular tropes → **70**; the ratio is the third case |

The "max 115" figure in this plan's own display-cap notes was derived assuming triggers were
negative, so it was right about the intent and wrong about the code. It is right about both now.

### Rolling it out: `wp lwtv calc --all`

**Flipping the flags changes nothing on its own.** `lezshows_the_score` is stored post meta, so
the flags only alter what `do_the_math()` computes — every show keeps its old number until
something rewrites it. The rollout *is* the recalculation, and until now nothing could do it in
bulk: `wp lwtv calc` took a required post ID, and the daily cron does lists, caches and
debuggers but no scoring.

So `wp lwtv calc` now takes `--all`, with `--dry-run`, `--post-type`, `--offset`, `--limit` and
`--yes`. Three things in it are not obvious:

**1. Third-party score refresh is OFF by default, and this was a real trap.**
`do_the_math()` ends with `Grading::update_scores()`, and `Grading\TVMaze::update_scores()`
makes a live `wp_remote_get()` on a transient miss. A cold full sweep would therefore fire
**~4,500 unthrottled requests** — far past TVMaze's documented 20-per-10-seconds — and the
resulting 429s get written into `lezshows_3rd_scores` as though they were data. A recalculation
caused by a change to *our* scoring has no business refetching somebody else's, so the call is
now wrapped in `apply_filters( 'lwtv_recalculate_third_party_scores', true, $post_id )` and the
sweep disables it. `--with-third-party` opts back in.

**2. It reports the flag state before it does anything, and warns when both are off.**
The usual reason to run this is to apply a scoring change. Running it with the flags off would
rewrite 2,000+ rows of meta with the *old* numbers — succeeding loudly while achieving the
opposite of what was intended.

**3. `free_memory()` is deliberately not `wp_cache_flush()`.** With a persistent object cache
that would empty the whole site's cache and take the front end down for the duration of a
maintenance script. It clears only this process's local copy. It also fully flushes
`Calculations::$counts_memo` and `Show_Characters::$memo`, which are per-show memos that each
evict only their own key — correct for one calculation, and never evicted across a sweep, so
without this every show's entry accumulates for the length of the run.

A per-post failure is caught, collected and reported at the end rather than ending the run,
because one bad post should not abort a two-thousand-post migration.

### Calibration: the objective was wrong before the number was

`SATURATION_K = 10.0`, and the reasoning matters more than the value.

**The objective I originally gave — "adjust K until the median NEW total matches median OLD" —
is backwards.** It was printed by the preview command and written into the constant's docblock,
and it quietly requires keeping the bug this project exists to fix.

| | median |
|---|---|
| the three components the character score is averaged with | **69.1** |
| old character score | **10.0** |

The old component sat **59 points below** everything it was averaged into. That was not an
editorial judgment; it was a scale error from an unbounded sum of small role points. The total
median only sat where it did *because* this term was on the floor — so holding it fixed
requires `K≈40`, which puts the character median at **11.8** and preserves the error.

Calibrate on the component's own distribution instead:

| K | char p50 | char p99 | total median | Failing (<20) | 90+ Club |
|---|---|---|---|---|---|
| 5.4 | 50.0 | 86.7 | +8.25 | — | — |
| 8 | 40.2 | 81.5 | +6.05 | 60 → 44 | 16 → 14 |
| **10** | **35.0** | **77.8** | **+4.91** | **60 → 47** | **16 → 12** |
| 15 | 26.4 | 70.1 | +3.01 | 60 → 50 | 16 → 8 |
| 40 | 11.8 | 46.8 | −0.03 | 60 → 56 | 16 → 1 |

K=10 moves the character median from 10 to 35 — fixing most of the scale error while leaving
this the *hard* component it should be. It measures documented queer screen time, which most
shows genuinely do little of, so it should not sit level with `alive %` at 69. K=5.4 (= median
X, so the median show scores exactly 50, which is what K *means*) is the cleanest single rule
but shifts totals by +8.

**Most scores will go up, and the methodology note has to say why:** a broken component stopped
dragging the average down. That is a correction, not drift.

**"The 90+ Club" shrinking is also mostly correct.** 12 of its 16 members had a character score
pinned at exactly 100, so their membership was manufactured by the clamp rather than measured.
The honest baseline is 4; K=10 gives 12.

### No re-run is needed to re-calibrate

The `char_new_raw` column **is** the X that K divides, and the three components K does not
touch are recoverable as `4 * score_new_raw - char_new`. So any existing CSV can be swept
offline across every candidate K at once. Verified by reproducing the K=15 run's reported
median and mean exactly (57.51 / 54.82) from `movers4.csv` alone.

### The actor check is small, and I said otherwise

I described the Tambor Takedown as "a *larger* change to show scores than longevity
weighting." Measured: **71 of 1,826 queer-irl tags fail the check — 4% — across 62 shows.** The
90% figure I was reasoning from was the queer-irl *term's* share of Transparent's old character
score, not the actor check's effect on it. Separate flags are still right for attribution, but
the expected aggregate effect is small.

It has also been in the preview's NEW column since that column existed, so **every calibration
run so far already included it.** An earlier note here claiming K needed re-deriving because it
was fitted without the actor check was wrong.

### Measured impact at K=15 (superseded — kept for the comparison)

2,066 shows move up, 180 down, 107 by ≥10 points. Median deltas by format are close
enough that the divisors show no obvious double-penalty: series +5.59, web-series +7.36,
mini-series +5.74, movie +5.09 (at K=9; the ordering holds at 15).

**Display-only capping is settled and not worth doing.** Exactly one show exceeds 100
uncapped, at 100.61.

### Tier 2 has never run

The `--all` run put 1,813 shows on tier 1 (curated season count) and 442 on tier 4 (airdate
span). **Zero** reached tier 2, because `--tvmaze` was not passed and no show has
`lezshows_aired_years` stored yet.

With season-count-first ordering this is partly permanent: once TVMaze IDs are backfilled,
tier 2 will only ever serve the 442 shows that are still airing or have no season count. The
Arrested-Development-style undercount — 5 seasons across 7 calendar years — therefore stands
uncorrected for the other 1,813. That is the accepted cost of preferring curated data; it is
recorded here so it is a known trade rather than a surprise.

### A lesson worth keeping

The pure functions were verified by porting them to Python and re-running every
assertion — which reported ALL PASS while four PHPUnit tests were failing and **four more
were passing while asserting nothing.**

`casting_multiplier()`'s third parameter changed from `bool` to `string`; the tests kept
passing booleans. PHP coerced `true` to `"1"` and `false` to `""`, matching no branch, so
every trans case silently returned the neutral `1.0`. The worst casualty was
`test_the_casting_multiplier_never_compounds_past_its_bounds` — the guard against the ×4
stacking regression — which checked that values stayed within `[0.5, 2.0]` while every value
it produced was `1.0`.

The port validated the *maths*. It could not validate the *call sites*. That test now also
asserts `min == 0.5`, `max == 2.0`, and that `1.0` appears, so it cannot go vacuous again
without failing.

---

## Next steps

1. ✅ ~~Run the test suite and linter.~~ Green.
2. ✅ ~~Reconcile the actor gender list.~~ All 31 slugs triaged.
3. ✅ ~~Calibration run.~~ `SATURATION_K = 10.0`, from 2255 shows — and on a corrected
   objective; the first pass optimised the wrong quantity. See the calibration section.
4. ✅ ~~Add the missing CSV columns.~~ `char_new_raw` (the post-divisor value `saturate()`
   consumes, so a K sweep needs no back-derivation), plus `decile_old`/`decile_new`,
   `band_old`/`band_new` and a `moved` column flagging `decile band colour` crossings. Sort by
   `moved` to see only the changes a reader could notice.
5. ✅ ~~`wp lwtv tvmaze backfill`.~~ Built; tests and lint clean. Separate command by design:
   per-show HTTP inside `do_the_math()` would mean rate limits, timeouts and partial failures
   leaving some shows on one denominator tier and others on another depending on when the API
   blinked. IMDb lookups only — an exact match or nothing, since a guessed TVMaze ID writes
   wrong aired years into a scoring input.

   Run order, and it matters:

   ```
   wp lwtv tvmaze status
   wp lwtv tvmaze backfill --dry-run --order=random     # honest hit-rate sample
   wp lwtv tvmaze backfill --all                        # IDs, ~14 min at 500ms
   wp lwtv tvmaze backfill --all --with-seasons --scoring-only
   ```

6. ✅ ~~Calibrate `COVERAGE_MIN` from the histogram.~~ Run; 17 rejections, up from 6, and every
   long-running soap that was losing screen time is caught. `COVERAGE_MIN` **stays at 0.75**,
   but see the calibration section: the histogram is not bimodal, so the value rests on the
   Home and Away mechanism argument rather than on a measured gap. Two reporting bugs found and
   fixed in the same run. **Left open:** whether the discarded years fall in an internal hole
   of the set or outside its range — the signal that would separate a real hiatus from missing
   data. Not built.
7. ✅ ~~Re-check `SATURATION_K` after the backfill.~~ Done, and the concern was real: the first
   calibration ran in a world where tier 2 never fired for a single show. Re-derived from
   `movers4.csv`, which has 361 shows on tier 2 and the plausibility guard active. K is now
   **10.0** on the corrected objective.

   Re-deriving it again needs **no new `--all` run**: `char_new_raw` is the X that K divides,
   and the rest of the total is `4 * score_new_raw - char_new`, so a single CSV sweeps offline
   across every candidate at once. Verified by reproducing the K=15 run's median and mean
   exactly (57.51 / 54.82) from the CSV alone.
8. ✅ ~~Phase 0b, memoise `get_characters_list()`.~~ Done, and it grew: the resolve/format
   split means all seven output formats share one traversal, and `clean_character_array()`'s
   unconditional `wp_add_object_terms()` is now guarded. Across a full recalc that removes
   ~13,500 meta writes, ~15,000 term writes, ~30,000 `get_field()` and ~60,000 `has_term()`
   calls. Also removed a dead `unset( $characters[ $char_id ] )` that used a value as a key.
9. **Wire into `count_queers_all_types()` behind the two flags.** Split into three stages so
   the risky part lands with nothing else attached.

   **9a ✅ Collapse the two implementations.** Done. `LWTV\CPTs\Shows\Character_Score` now
   holds one `gather()` and one copy of each model; `count_queers_all_types()` and
   `cli-score-preview.php` both call it. The preview's `char_old` column is no longer its own
   replica of the live maths — it *is* the live maths, which makes the stored-meta divergence
   warning a genuine regression test for the whole path rather than for a copy of it.

   Deliberately zero behaviour change, and two things were needed to keep that true:

   - **`gather( $id, $override, $with_longevity = false )` from the live path.** Gathering the
     longevity inputs unconditionally would have added a `lezchars_show_group` read and an
     actor-gender read per character — ~30,000 each across the corpus — for numbers the legacy
     model never touches. A refactor that halves the speed is not a pure refactor.
   - **Memoising `count_queers_all_types()`**, which is called twice per `do_the_math()` and
     redid the whole traversal each time. Flushed at the top of `do_the_math()` *and* right
     after `show_character_data()`, because that writes `lezshows_char_roles`, which is an
     input to the legacy score.

   `legacy()` and `longevity()` are pure — every input arrives in `$data` — so
   `tests/unit/CPTs/CharacterScoreTest.php` can assert the extraction preserved the maths,
   including preserving its bugs: the unbounded negative and the double-counted role points
   for a character with two show-group rows are both asserted, because this step promised to
   change no scores.

   **9b/9c ✅ Both flags wired, both default off.** Filters, not options:
   `count_queers_all_types()` runs inside a loop over every published show, and a filter costs
   nothing where an option read costs a lookup. It also keeps "which model produced this
   score" answerable from the code rather than from database state.

   ```php
   add_filter( 'lwtv_score_longevity_enabled',   '__return_true' );
   add_filter( 'lwtv_score_actor_check_enabled', '__return_true' );
   ```

   Two things worth knowing about how they interact:

   - **Turning a model off actually stops paying for it.** `gather()` takes the gates as
     options and the live path passes `options_from_flags()`, so a disabled model costs no
     queries. Longevity forces the actor gate on regardless of its own flag, because
     `casting_multiplier()` cannot run without it.
   - **`queer_irl_scored` is resolved inside `gather()`, not `legacy()`.** `legacy()` cannot
     tell whether the actor data was collected, so a caller that gathered without it and then
     asked for an actor-checked score would get zero queer-irl credit for every character — a
     silently catastrophic score rather than an error. `gather()` knows what it collected, so
     `gather()` decides, and `legacy()` falls back to the tagged count when the key is absent.

   The preview command forces both gates on regardless of the live flags. A preview whose
   contents depend on whether the feature is already enabled is useless for deciding whether
   to enable it — and with them off every NEW column would read zero, which looks like a
   result rather than a missing input.

### `lezshows_queer_irl_count` changes, and that is correct

The actor check also rewrites `lezshows_queer_irl_count`. I argued against this and **I was
wrong**, on evidence I had not bothered to gather before arguing.

The claim was that the meta feeds sitewide statistics, so narrowing it would look like the site
had suddenly documented fewer queer-IRL characters. In fact:

- **It has exactly one reader**: `Statistics\Build\We_Love`, where it populates a column
  labelled **`actors`** in the Shows We Love comparison. The tagged-character count is not a
  count of actors. The actor-verified count is. The label has been wrong since it was written,
  and this makes it true.
- **Every sitewide queer-irl statistic queries the taxonomy term directly** in SQL —
  `Queer_IRL::build_queer_irl_data()`, `Character_Queer_Cast_Firsts`, the stats templates, the
  REST endpoints. None of them read this meta key. "Counts drop sitewide" was simply false.

⚠ The Shows We Love numbers **will** drop when the flag goes on, and that is a visible change
to a curated page. It is the correct number appearing in a column that always promised it.
10. Check the distribution against the real display boundaries — `<20` "Failing Grades", `>=90`
   "The 90+ Club", the decile histogram, the ~51 colour inflection — and publish a methodology
   note. The score is the site's core data product; a visible change deserves an explanation
   readers can find. There are no letter grades to re-threshold; see above.

### Sequencing: decided

**The Tambor Takedown ships on its own flag, in the same release as longevity.** Two flags,
both defaulting off, flipped together:

| flag | what it gates |
|---|---|
| `lwtv_score_longevity_enabled` | longevity weighting and the saturating aggregate |
| `lwtv_score_actor_check_enabled` | queer-irl credit requiring a queer first-billed actor |

The actor check is the *larger* change to show scores of the two — it is the first time
queer-irl credit has ever been actor-checked, and that term was 90% of Transparent's old
character score. Separate flags keep the two effects attributable, so a questioned score can be
traced to the right cause. A single release means `SATURATION_K` is calibrated once, with both
on, rather than twice against a distribution that shifts underneath it.

---

## Performance

**No front-end impact.** Scores are precomputed into `lezshows_the_score`; page loads read
meta and never run this code.

| path | cost |
|---|---|
| `appears` reads | **zero new** — one pre-pass over the same `get_field()` the scoring loop used to do inline, whose result the loop then consumes |
| airdate / seasons reads | 2–3 `get_post_meta` per show, already cached |
| weight maths | pure float arithmetic, no I/O |
| character meta | already primed by `prime_character_caches()` |
| bulk recalc | one-time, chunked, off-peak, plus a FacetWP reindex |

This is nearly free because both consumers are loops already running over exactly the right
objects with the caches warm. Any implementation adding a `get_field()` per character, or a
query per show, has gone wrong — that would be the thing that slows the site down.

---

## Adjacent findings — file as tickets

**1. `get_characters_list()` writes post meta as a side effect of a getter.**
`build_character_list()` calls `update_post_meta()` for `lezshows_dead_count`,
`lezshows_char_count` and `lezshows_char_list` at lines 319–321 — *before* the `switch` that
decides what to return. Every call writes, whatever the caller asked for. `do_the_math()`
calls it three times per show (lines 185, 320, 515), then `show_character_score()` writes two
of the same keys again at 465–467. Values agree, so it is waste rather than corruption — but
it is concentrated in exactly the bulk-recalculation path this plan will run across every
show. Memoise per request.

**2. The four-way average has mismatched ceilings.** `show_score()` is unclamped and can
reach 115 (30 ratings + 10 worth-it + 20 gold star + 15 trigger + 40 shows-we-love);
`show_tropes_score()` clamps at 100 (line 428), alive ≤ 100, and the new character score is
asymptotically < 100. So a show maxing `show_score()` gains up to +3.75 on the total that no
other component can offer. Transparent sits at exactly 115.

**3. Display-only capping buys little.** Given those ceilings, the maximum possible total is
`(115+100+100+100)/4 = 103.75` — at most 3.75 points of headroom, only for shows near-perfect
on everything, while `lezshows_the_score` feeds FacetWP indexing, score-distribution buckets,
grading thresholds, both REST exporters and custom columns, all assuming 0–100. The
tie-breaking it would preserve is already handled by the saturating curve. `--all` reports
`score_new_raw` and counts shows exceeding 100 so this can be settled from data. If the
answer is "a handful", keep `lezshows_the_score` capped and add a separate raw key.

**4. The `trans` count exclusion bug is latent, not active.** Line 222 counts any character
without a `cisgender`/`intersex`/`unknown` term as trans, which includes characters with no
gender term at all. 100% of characters carry a gender term and the weekly debugger enforces
it, so the blast radius is zero. The new path does not use this test. A `$has_gender` floor
plus a test asserting the invariant is cheap insurance — moving it from a weekly job to build
time.

---

## Non-goals

- No change to `show_score()`, `show_tropes_score()`, or the `/4` average.
- **Format divisors stay as-is.** Noted for later: a longevity model already normalises for
  run length, so `÷2`, `÷1.5`, `÷1.25` may now double-penalise short formats. Measure first.
  They apply to `X` *before* saturation, preserving current semantics.
- No ACF schema changes. `lezshows_aired_years` is derived, not hand-entered.
- No changes to `Character_Longevity_Leaders`, and no coupling of scoring to it.
- `Is_Actor_Trans` is not modified.
