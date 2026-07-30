# Statistics Cache-Warming Rewire — Design

**Date:** 2026-07-30
**Branch:** `feat/continued-stats`
**Status:** Proposed (awaiting review)

## Context

The statistics system (`plugins/lwtv-plugin/php/statistics/` and `.../this-year/`)
serves visitor-facing pages from transient-cached "build" datasets. On a warm
cache these pages do no dataset queries. The weakness is what happens **after a
content edit**.

Every `save_post` on a show / character / actor — and every score recalc —
calls `lwtv_plugin()->invalidate_statistics_cache( <type>, <id> )`
(`cpts/class-shows.php:265`, `cpts/class-characters.php:318`,
`cpts/class-actors.php:254`, `cpts/*/class-calculations.php`). At `shutdown`,
`Transients::process_deferred_cache_invalidation()`
(`_components/class-transients.php`) clears the affected cache tiers and is
*supposed* to warm them again. Warming is broken three ways:

1. **The `immediate` warm is a no-op.** `process_deferred_cache_invalidation()`
   calls `$this->warm_cache_tier()`, which binds to the stub at
   `class-transients.php:379` (logs and returns). The *real* warmer is a
   different class with the same method name
   (`schedulers/class-statistics-cache-warming.php:54`), only reachable via the
   `background` path.
2. **The `background` warm only covers death stats.** The scheduled path runs
   `warm_derived_caches()`, which calls only `warm_death_statistics()`
   (`class-statistics-cache-warming.php:92`). The comprehensive
   `warm_count_caches()` / `warm_stable_caches()` methods exist but are never
   invoked from the save path.
3. **When it does schedule, it fires 30 minutes late.**
   `schedule_cache_warming()` schedules at `time() + CACHE_DURATION`
   (`CACHE_DURATION = 30 min`, `class-transients.php:14`, `:395`).

There is also **no cron backstop** — `run_cron_daily()`
(`wp-cli/cli-generate.php:192`) never warms stats.

**Net effect:** after any edit, nearly all stats caches are cleared and stay
cold until the next visitor triggers a full lazy rebuild — and that rebuild is
expensive (full-table walks + per-row lookups in the `this-year` builders). The
cost lands on a random visitor, repeatedly.

Additionally, `clear_cache_tier()` (`class-transients.php:352`) always runs ~60
raw `wp_options` `LIKE`-DELETEs per invalidation **even under a persistent
object cache**, where transients don't live in `wp_options` — pure waste.
Production runs Redis, so this fires on every edit for nothing.

## Environment (confirmed)

- **Redis** persistent object cache + **Nginx**.
- **Action Scheduler** available (`lwtv_plugin()->is_action_scheduler_available()`).
- Content is edited **one item at a time** by editors; bursts happen (e.g. a new
  show plus its ~10 characters over a few minutes). No routine cron-driven mass
  score recalcs.

## Goals

1. After an edit (or burst), stats caches are proactively re-warmed so visitors
   almost never pay a cold rebuild.
2. Editor `save_post` stays fast — all warming work is deferred off the request.
3. A burst of edits collapses into **one** comprehensive warm, not one per save.
4. Warming actually covers the datasets behind every stats view (fix the
   coverage gaps, incl. currently-unwarmed Scores and Cliche-Leaders).
5. Stop the redundant `wp_options` DELETE storm under Redis.
6. A daily cron backstop guarantees caches are never stale longer than ~a day
   even with zero edits and zero traffic.

## Non-goals (deferred; YAGNI for this profile)

- **Per-key rebuild lock / stampede guard.** With prompt debounced warming plus
  the daily backstop, cold windows are small and rare, and the stats section is
  not high-concurrency. A lock touches every build class. Revisit only if we
  observe real stampedes.
- **Per-content-type warm granularity.** We warm everything (coalesced), per the
  decision below. The tier map is still used for *clearing* precision.
- Changing cache TTLs, fixing individual N+1 builders, or the WP_Query-object
  caching in `queeries/` — separate review items, out of scope here.

## Design overview

Keep the existing **clear-on-shutdown** model. Replace the broken warm wiring
with a single **debounced, comprehensive, deferred warm**:

```
edit save_post
  └─ invalidate_statistics_cache(type,id)         [queues, as today]
       └─ shutdown: process_deferred_cache_invalidation()
            ├─ clear_cache_tier(patterns)          [+ Redis gate on SQL pass]
            └─ schedule_stats_warm()               [debounced reschedule]
                 └─ (a few min after the LAST edit)
                      Action Scheduler fires 'lwtv_warm_statistics_cache'
                        └─ Statistics_Cache_Warming::warm_all()
                             rebuilds & re-caches every stats dataset
```

A visitor who loads a stats page *during* the cold window still gets a correct
page via the normal lazy rebuild (one slow load, then cached); the debounced
warm then supersedes it.

### The debounce (answers "1 show + 10 characters in short order")

Each invalidation reschedules the single pending warm job to
`now + WARM_DEBOUNCE_DELAY`, but never later than a hard deadline of
`first_edit_in_burst + WARM_MAX_DELAY`. So a burst of 11 saves over 3 minutes
produces exactly **one** warm, firing ~`WARM_DEBOUNCE_DELAY` after the final
save (or at the hard cap, whichever comes first), warming the complete final
state once.

Proposed constants (in `Transients`):
- `WARM_DEBOUNCE_DELAY = 2 * MINUTE_IN_SECONDS`
- `WARM_MAX_DELAY = 10 * MINUTE_IN_SECONDS`

## Components

### 1. `Transients` (`_components/class-transients.php`) — clearing + scheduling

**Responsibilities:** clear the right cache patterns on invalidation, and
schedule/reschedule the single debounced warm job. Owns the burst-deadline
state. Contains **one pure, unit-testable helper** for the debounce math.

Changes:

- **Delete the no-op `warm_cache_tier()` stub** and the per-tier
  `immediate`/`background` warm branching in
  `process_deferred_cache_invalidation()`. After clearing, call
  `schedule_stats_warm()` once.
- **`schedule_stats_warm(): void`** — if Action Scheduler is unavailable, no-op
  (the daily cron backstop covers it). Otherwise:
  1. Read burst deadline from an autoload-off option
     `lwtv_stats_warm_deadline` (0/absent = no active burst).
  2. Compute the next fire time with the pure helper (below).
  3. `as_unschedule_all_actions( HOOK, array(), GROUP )` then
     `as_schedule_single_action( $target, HOOK, array(), GROUP )`.
  4. Persist the (possibly new) deadline.
- **`next_stats_warm_time( int $now, int $deadline, int $delay, int $max ): array`**
  — **pure function**, returns `array( 'target' => int, 'deadline' => int )`.
  Logic: if `$deadline <= 0` (no active burst) → new burst:
  `deadline = $now + $max`, `target = min( $now + $delay, deadline )`. Else
  (burst active) → keep `deadline`, `target = min( $now + $delay, $deadline )`.
  No WordPress calls → covered by the PHPUnit harness.
- **`clear_cache_tier()` Redis gate:** wrap the two `$wpdb->query( ... DELETE ...
  LIKE ... )` calls in `if ( ! wp_using_ext_object_cache() ) { ... }`. The
  object-cache-aware index walk above them already evicts real transients under
  Redis; the SQL pass is only for DB-stored transients.
- The warm-completion handler clears `lwtv_stats_warm_deadline` (burst over).

The `HOOK` stays `lwtv_warm_statistics_cache`; scheduled args become `array()`
("warm everything"). `GROUP` stays `'lwtv'`.

### 2. `Statistics_Cache_Warming` (`schedulers/class-statistics-cache-warming.php`) — the warmer

**Responsibilities:** rebuild and re-cache every stats dataset. Pure glue over
the build classes; no scheduling logic.

Changes:

- `warm_cache_tier( string $tier = 'all', int $post_id = 0 )` — treat `'all'`
  (the new default from the scheduled empty args) as "run everything". Keep the
  existing per-tier cases for backward compatibility / manual calls.
- **`warm_all(): void`** — invokes the full set: `warm_count_caches()`,
  `warm_death_statistics()`, `warm_taxonomy_statistics()`,
  `warm_on_air_statistics()`, `warm_queer_irl_statistics()`,
  `warm_formats_statistics()`, `warm_loved_statistics()`,
  `warm_worth_it_statistics()`, `warm_nation_statistics()`,
  `warm_station_statistics()`, **and one new warmer** (coverage gap):
  - `warm_cliche_leaders_statistics()` →
    `( new Build\Cliche_Leaders() )->generate()` (backs the "most cliches" view,
    `statistics/build/class-cliche-leaders.php`; currently unwarmed).

  **Finding — `Build\Scores` is dead code.** The spec originally also proposed a
  `warm_scores_statistics()`. During planning, a codebase search found **no
  callers** of `LWTV\Statistics\Build\Scores` and **no readers** of the
  `scores_*` transient it writes. Warming it would populate a cache nothing
  reads. So it is **not** warmed here; the unused class is flagged for a separate
  dead-code review, out of scope for this work.
- Clear the burst deadline option at the end of `warm_all()` so a warm that
  actually ran ends the burst window.
- On the last warm step, also refresh the This Year trends count map (already in
  `warm_this_year_trends()` via `warm_count_caches()`).

Coverage note: some patterns (`bulk_char_counts_*`, `bulk_show_counts_*`,
`taxonomy_opt_*`, etc.) are warmed **transitively** because the view builders we
call consume them. `warm_all()` targets the datasets behind actual views, not
every internal key; anything not covered simply lazy-rebuilds on first hit, as
today. The point is no *page* is cold after an edit.

### 3. Daily cron backstop (`wp-cli/cli-generate.php` `run_cron_daily()`)

Add one line: `( new Statistics_Cache_Warming() )->warm_all();` (guarded by
`is_action_scheduler_available()` not required — this runs inline in WP-CLI).
Guarantees warmth even with no edits/traffic and evicts the "expired but never
rebuilt" case. Daily (not hourly) — warm-on-edit handles freshness; this is only
a floor.

## Data flow / sequence

1. **Single edit:** save → shutdown clears tiers, gates SQL under Redis,
   schedules warm for `now + 2min`, sets deadline `now + 10min`.
2. **Burst (show + 10 chars over 3 min):** each save reschedules the *same* job
   forward to `last_save + 2min`, capped at `first_save + 10min`. One warm fires,
   warming the final state; deadline cleared.
3. **Visitor mid-burst:** stats page cache-misses → lazy rebuild (one slow load)
   → re-cached → later superseded by `warm_all()`. Correct throughout.
4. **No edits for a day:** daily cron `warm_all()` refreshes everything.
5. **Action Scheduler unavailable:** warm-on-edit is skipped; daily cron still
   warms; lazy rebuild still serves correct pages. Graceful degradation.

## Error handling & edge cases

- **`LWTV_DISABLE_TRANSIENTS` (dev):** `get_transient()` returns false, so
  builders always rebuild; warming still "works" (writes transients that reads
  ignore). No special-casing needed; matches current behavior.
- **Warm job mid-run when a new edit arrives:** `as_unschedule_all_actions`
  can't cancel an in-flight job → at worst one extra warm. Acceptable.
- **Deadline option race across concurrent requests:** last writer wins;
  bounded by `WARM_MAX_DELAY`; a stray extra warm is harmless.
- **A builder throws during `warm_all()`:** each warmer already logs via
  `lwtv_plugin()->debug_log`/`error_log`; one failing warmer must not abort the
  rest — wrap each call so warm_all is best-effort. (The recent stability pass
  hardened the builders against null query results.)

## Testing

**Pure unit tests (PHPUnit harness, `tests/unit/`):**
- `next_stats_warm_time()`:
  - no active burst → new deadline `now+max`, target `now+delay`.
  - active burst, `now+delay < deadline` → target `now+delay`, deadline
    unchanged.
  - active burst near the cap, `now+delay > deadline` → target clamped to
    `deadline`.
  - `delay`/`max` boundary (delay == max, delay 0).

  Requires making the class file loadable from the harness bootstrap (guarded by
  `ABSPATH`, no WP calls in the helper) — mirror the existing pattern.

**Live verification (against the running site — not unit-testable):**
- Edit a show; confirm at `shutdown` a single `lwtv_warm_statistics_cache` job is
  scheduled ~2 min out (Action Scheduler admin / WP-CLI).
- Rapid-edit a show + several characters; confirm exactly **one** pending warm
  job, firing after the last edit.
- After the job runs, confirm the tracked stats transients are present
  (`wp lwtv cache status` — existing `wp-cli/cli-cache.php`) and stats pages hit
  warm.
- Confirm under Redis no `_transient_%` DELETE queries fire on save (query
  monitor / `SAVEQUERIES`).
- Run `wp lwtv generate cron daily`; confirm `warm_all()` runs.
- Existing eviction canary (`wp lwtv cache verify`) still passes.

## Rollout

- Pure code + config constants; no schema, no migration.
- Deploy to `development` first; watch Action Scheduler for warm-job cadence and
  confirm no DELETE storm under Redis.
- No user-facing change except faster stats pages after edits.

## Open questions

- `WARM_DEBOUNCE_DELAY` / `WARM_MAX_DELAY` values (2 min / 10 min proposed) —
  tune after observing editor behavior.
