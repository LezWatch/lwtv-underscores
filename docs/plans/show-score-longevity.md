# Plan: Longevity-Weighted Character Scoring

**Goal:** stop headcount driving a show's character score, so a 50-year soap that cycled
through 200 one-episode characters no longer outranks a tightly-written five-season drama.
What replaced it turned out to be larger than longevity weighting alone — see Diagnosis.

**Status:** the pure maths and a read-only preview command are built and verified against a
real show. Nothing is wired into live scoring yet.

| | |
|---|---|
| `plugins/lwtv-plugin/php/cpts/shows/class-longevity.php` | built |
| `tests/unit/CPTs/ShowLongevityTest.php` | built, **not yet run** (see Verification) |
| `plugins/lwtv-plugin/php/wp-cli/cli-score-preview.php` | built, verified on Transparent |
| `class-calculations.php` | **untouched** |

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
the reason matters for calibration. The new median character score is 37, so `SATURATION_K`
governs how much the character component weighs in the average at all, not just how shows
rank within it.

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

### The Tambour Takedown, finally applied

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

K=30 holds the median almost exactly but nearly empties "The 90+ Club" — a named section on
the scores stats page — because a character score of 90 would then need a raw X of 270.
K=15 accepts a ~3 point median rise to keep the top of the distribution populated.

Both movements are defensible rather than drift: the median rises because the old component
sat on the floor and went negative for 133 shows, and the 90+ Club shrinks because it was
partly populated by the 38 clamped shows each collecting a free 25 points.

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
| 1 | TVMaze `premiereDate`/`endDate` union | exact |
| 2 | `lezshows_seasons`, **finished shows only** | good |
| 3 | airdate span less known hiatus years | fair |
| 4 | raw airdate span | today's behaviour |

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

### Measured impact at K=15

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
3. ✅ ~~Calibration run.~~ `SATURATION_K = 15.0`, from 2255 shows.
4. **Add the two CSV columns that were promised and not shipped:** the new raw `X`, and the
   `<20` / `>=90` / decile band before and after. X had to be back-derived from `char_new` at
   two decimal places to run the K sweep, and the band columns are what make the display
   impact readable without re-deriving it each time.
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

6. **Re-run `score-preview --all` and re-check `SATURATION_K` after the backfill.** K=15 was
   calibrated in a world where tier 2 never fired for a single show. Once aired years exist,
   the ~442 tier-4 shows move to a different and more accurate denominator, which moves their
   scores and therefore the distribution K was fitted to. Calibrating before the backfill and
   shipping after it would silently invalidate the calibration.
7. **Phase 0b, memoise `get_characters_list()`** (see Adjacent findings) before any bulk
   recalculation — it makes that run materially cheaper.
8. **Then** wire into `count_queers_all_types()` behind `lwtv_score_longevity_enabled`,
   defaulting off.
9. Check the distribution against the real display boundaries — `<20` "Failing Grades", `>=90`
   "The 90+ Club", the decile histogram, the ~51 colour inflection — and publish a methodology
   note. The score is the site's core data product; a visible change deserves an explanation
   readers can find. There are no letter grades to re-threshold; see above.

### One sequencing decision still open

**Does the Tambour Takedown ship with this or separately?** It is currently bundled, and it
is a *larger* change to show scores than longevity weighting — the first time queer-irl credit
has ever been actor-checked. Shipping it afterwards means calibrating `SATURATION_K` twice.
Recommend separate flags, single release, so the two effects stay measurable apart.

---

## Performance

**No front-end impact.** Scores are precomputed into `lezshows_the_score`; page loads read
meta and never run this code.

| path | cost |
|---|---|
| `appears` reads | **zero new** — already loaded by the `get_field()` at line 519 |
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
