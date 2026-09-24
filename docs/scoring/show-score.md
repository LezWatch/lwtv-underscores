# Show Score

How a show's LezWatch.TV score (`lezshows_the_score`) is built, stored and regenerated.

The score is the site's core data product. Change a weight here and every show's stored score is wrong until the whole corpus is recalculated (see [Regeneration](#regeneration)).

## Components

`Calculations::do_the_math()` in [`plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`](../../plugins/lwtv-plugin/php/cpts/shows/class-calculations.php) averages four components with equal weight:

```
total = ( show_rating + tropes + alive + character ) / 4
```

| Component | Source | Range |
|---|---|---|
| Show rating | `Calculations::show_score()` → `Scoring\Show_Rating::score()` | −40 to 115, not clamped |
| Tropes | `Calculations::show_tropes_score()` → `Scoring\Show_Tropes::score()` | 0 to 100 |
| Alive ratio | `Calculations::show_character_score()` | 0 to 100 |
| Character score | `Calculations::count_queers_all_types()` → `Scoring\Character_Score::longevity()` | 0 up to (never reaching) 100 |

A null from any sub-calculation is logged to the `show-score` debug log and treated as 0, so the show still gets a score written.

### Show rating

[`scoring/class-show-rating.php`](../../plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-rating.php). A sum of point tables:

| Input | Points |
|---|---|
| Realness + quality + screentime (`lezshows_*_rating`), each capped at 5 (`BASE_RATING_CAP`) | sum × 3 (`BASE_MULTIPLIER`), max 45 |
| Worth it (`lezshows_worthit_rating`) | Yes +10, Meh +5, No −10, TBD 0 |
| Star (`lez_stars` term, falling back to meta) | gold +20, silver +10, bronze +5, anti −15 |
| Trigger warning (`lez_triggers` term, falling back to `lezshows_triggerwarning`) | high −15, med −10, low −5 |
| Shows We Love (`lezshows_worthit_show_we_love` = `on`) | +40 |

Trigger warnings subtract points. The maximum is 115 (45 + 10 + 20 + 40, no trigger warning).

#### Trigger warnings

`Scoring\Trigger_Warning::normalize()` maps legacy aliases (`on` → `high`, `medium` → `med`) and returns `high`, `med`, `low` or `none`. Matching is exact-case on purpose. Case-folding would change stored scores for shows with legacy mixed-case trigger meta.

### Tropes

[`scoring/class-show-tropes.php`](../../plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-tropes.php), with the good/maybe/bad/ploy slug lists in `Scoring\Trope_Categories` (also used by the statistics layer).

1. No tropes, or the `none` trope: 80 (`NO_TROPES_SCORE`).
2. Tropes, but none in any category: 70 (`UNCATEGORIZED_TROPES_SCORE`).
3. Otherwise: `( good + maybe − bad − ploy ) / categorised × 100`, or 0 if that numerator is not positive.
4. Add 3 per `lez_intersections` term, up to 15.
5. Floor at 0.
6. If the show has the `dead-queers` trope and `lezshows_byq_override` is not set, multiply by 0.75 when it also has `happy-ending`, otherwise by 0.66.
7. Cap at 100.

### Alive ratio

`( characters − dead ) / characters × 100`, or 0 for a show with no characters.

### Character score

Longevity-weighted and saturating. See [character-score.md](character-score.md). It is asymptotic, so it never reaches 100.

## Clamped and uncapped meta

`do_the_math()` writes the total twice:

| Meta key | Value | Why |
|---|---|---|
| `lezshows_the_score_uncapped` | The raw average. It can go above 100 (theoretical max 103.75, because only the show rating is unclamped). Not exposed in REST. | Shows at the ceiling can still be ranked against each other. |
| `lezshows_the_score` | Clamped to 0 to 100. | Everything reads this: display, the stats SQL, `Grading`, of-the-day, the taxonomy queries. None of them should have to handle a 0 to 115 range. |

Keep new consumers on `lezshows_the_score` unless they specifically need to break ties at the top.

## Other meta written

| Meta key | Written by | Notes |
|---|---|---|
| `lezshows_char_count`, `lezshows_dead_count` | `show_character_score()` | Must stay two separate scalar meta fields. Merging them into an array breaks FacetWP. |
| `lezshows_queer_irl_count` | `show_character_score()` | Characters tagged `queer-irl` whose first-billed actor is actually queer (`Character_Score` field `queer_irl_scored`). Its only reader is the "actors" column of the Shows We Love comparison (`statistics/build/class-we-love.php`). |
| `lezshows_char_roles`, `lezshows_char_gender`, `lezshows_char_sexuality`, `lezshows_char_romantic` | `show_character_data()` | Per-show role and taxonomy tallies. |
| `lezshows_on_air` | `do_the_math()` | `yes` when the finish year is empty, `current`, or not yet passed. `Longevity::run_years_detail()` uses the same rule for "still airing". |

The key names returned by `count_queers()` (`count`, `dead`, `none`, `queer-irl`, `trans`, `trans-irl`, `score`) are public. Callers outside the class depend on them, so do not rename them.

`lezshows_score` and `lezshows_on_air_score` are only ever deleted, in the non-show branch of `do_the_math()`. Nothing writes or reads them.

## Memoisation and cache priming

- `count_queers_all_types()` is memoised per show in `Calculations::$counts_memo`, because `do_the_math()` reaches it twice: once through `count_queers()` and once through `show_character_score()`.
- `do_the_math()` calls `Show_Characters::flush_cache()` and `Calculations::flush_counts()` for the show before it starts, and calls `flush_counts()` again after `show_character_data()` rewrites `lezshows_char_roles` (which `Character_Score::gather()` reads). A long-running WP-CLI process must never score from data built before the change that triggered the recalculation.
- `prime_character_caches()` loads post meta and the `lez_cliches`, `lez_gender`, `lez_sexuality` and `lez_romantic` terms for all of a show's characters in batched queries before either character loop runs.
- `show_character_data()` builds the zero-filled taxonomy scaffold once per request (`$tax_scaffold`) and copies it for each show.

`wp lwtv calc --all` clears both memos wholesale every `BATCH` (50) posts; see `WP_CLI_LWTV_Calculate::free_memory()` in [`wp-cli/cli-calc.php`](../../plugins/lwtv-plugin/php/wp-cli/cli-calc.php). Wider caching rules are in [docs/architecture/caching.md](../architecture/caching.md).

## Third-party scores

After writing the score, `do_the_math()` calls `Grading::update_scores()`, which refreshes TMDB and TVMaze ratings into `lezshows_3rd_scores`. This runs only when the `lwtv_recalculate_third_party_scores` filter returns true (the default).

On a transient miss, each show makes a live `wp_remote_get()`. A corpus-wide sweep would fire thousands of unthrottled requests, well past TVMaze's documented 20 calls per 10 seconds, and the resulting 429 responses would be written into `lezshows_3rd_scores` as if they were data. So `wp lwtv calc --all` turns the filter off unless you pass `--with-third-party`. A change to our scoring does not affect their scores. See [docs/integrations/tvmaze.md](../integrations/tvmaze.md) for rate limits.

## Regeneration

The score lives in post meta, so a change to how it is calculated does nothing until the meta is rewritten. It is recalculated:

| Trigger | Path |
|---|---|
| Saving a show, character or actor | `save_post_*` → `schedule_task( 'calculation', … )` → `Schedulers\Calculation_Task` (Action Scheduler), which also re-indexes the post in FacetWP |
| Recalculating a character | `CPTs\Characters\Calculations::do_the_math()` recalculates every show the character belongs to |
| Editor "Do the Math" button on the front end | `Theme\Do_Math::make( $post_id, true )` |
| WP-CLI, one post | `wp lwtv calc <post_id>` |
| WP-CLI, the whole corpus | `wp lwtv calc --all` (use `--dry-run` first, and `--offset` to resume) |

The daily cron (`wp lwtv generate cron daily`) does not recalculate show scores. After changing any scoring constant, run `wp lwtv calc --all`. Before that, run `wp lwtv score-preview` (read-only) to check the effect; see [calibration.md](calibration.md).

## Background

- [docs/plans/maybe-later/show-score-longevity.md](../plans/maybe-later/show-score-longevity.md) is the design plan and measurement log for the character-score rewrite. It is out of date: it says the model is switched off behind filters, but the model is live and those filters no longer exist.
- [docs/plans/completed/scoring-subfolder.md](../plans/completed/scoring-subfolder.md) covers the extraction into `cpts/shows/scoring/`.
