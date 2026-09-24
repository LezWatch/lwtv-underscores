# Scheduling

How LWTV defers work out of the request that triggered it: Action Scheduler first, WP-Cron as a fallback, and the queue patterns the background tasks share.

For what the server crontab runs each day, see [cron-schedule.md](../operations/cron-schedule.md).

## Action Scheduler and the WP-Cron fallback

`LWTV\_Components\Scheduler` ([class-scheduler.php](../../plugins/lwtv-plugin/php/_components/class-scheduler.php)) owns deferred work. `is_action_scheduler_available()` is simply `function_exists( 'as_schedule_single_action' )`.

`initialize_task_handlers()` registers two sets of handlers:

| Always registered | Only when Action Scheduler is available |
|---|---|
| `TMDB_Task`, `Cache_Task`, `Cache_Queue`, `Calculation_Task`, `FixCharShows_Task`, `Facet_Reindex_Task`, `Statistics_Cache_Warming` | `TMDB_Batch_Task`, `Cache_Batch_Task`, `BYQ_Task`, `Imdb_Verify_Task`, `Watch_URLs_Task`, `Wikidata_Qid_Task` |

Code that runs on `save_post` must not assume the second set exists. `CPTs\Characters` checks `is_action_scheduler_available()` before scheduling `BYQ_Task::AS_INVALIDATE_HOOK`, and `Scheduler::queue_wikidata_qid()` returns `false` without it.

### schedule_task()

`lwtv_plugin()->schedule_task( $type, $post_id, $priority, $delay = 30 )`:

- **Action Scheduler:** one generic hook, `lwtv_<type>_task`, with the post ID as the argument, `unique` by default.
- **Fallback:** `wp_schedule_single_event()` on `lwtv_<type>_task_<post_id>`.

Callers today: `calculation` (shows, characters, actors, and actors touched by a character's calculation), `fixcharshows` (characters) and `facet_reindex` (renames, see [facetwp-indexing.md](facetwp-indexing.md)).

**Known gap.** Every task class hooks only the generic name (for example `add_action( 'lwtv_calculation_task', ... )`). Nothing listens on the per-post `lwtv_<type>_task_<post_id>` hooks, so a task scheduled through the WP-Cron fallback is queued but never handled. Until a handler is added for those hooks, the fallback only matters on a site with no Action Scheduler at all.

`TMDB_Task` (`lwtv_tmdb_task`) and `Cache_Task` (`lwtv_cache_task`) are registered, but no current caller schedules those hooks. TMDB and page-cache work goes through `queue_tmdb_batch()` and `cache_queue()` instead.

## No HTTP in save_post

**Rule:** a `save_post` handler never makes a remote request. It records what needs doing and lets Action Scheduler do the asking.

- An HTTP call in a save hook blocks the editor until the remote end answers or times out.
- `save_post` fires far more often than "a human edited a post": autosaves, revisions, bulk edits, REST writes, and the plugin's own cron-driven recalculations.

The show and actor save handlers call `queue_tmdb_batch()`, `queue_imdb_verify()` and (actors) `queue_wikidata_qid()`. Each is cheap and synchronous-safe: it reads a few meta values and appends a post ID to a queue.

Statistics invalidation follows the same idea without HTTP: it is queued in memory and processed on `shutdown`, after the response is sent (see [caching.md](caching.md#statistics-cache-tiers)).

## Queue and drain

The background lookups share one shape:

1. `queue_post()` appends a post ID to a list stored in a transient and, if no run is pending, schedules one Action Scheduler action.
2. The handler takes `BATCH_SIZE` IDs off the front, processes them with a pause between requests, writes the rest back, and reschedules itself (`time() + 60`) while anything remains.

| Task | Hook | Batch | Pacing |
|---|---|---|---|
| `Imdb_Verify_Task` | `lwtv_imdb_verify_task` | 25 | `DELAY_US` 500 ms, sized for TVMaze's documented 20 calls per 10 seconds |
| `Wikidata_Qid_Task` | `lwtv_wikidata_qid_task` | 25 | `Identity::throttle()` |
| `TMDB_Batch_Task` | `lwtv_tmdb_batch_task` | 30 | `RATE_LIMIT_REQUESTS` 40 per `RATE_LIMIT_WINDOW` 10 s, 0.25 s between requests, backoff when rate limited |
| `Cache_Batch_Task` | `lwtv_cache_batch_task` | 75 URLs | `DELAY_BETWEEN_BATCHES`, reschedules every 30 s |

`Imdb_Verify_Task` and `Wikidata_Qid_Task` are deliberately the same shape. Two small queues that behave identically are easier to reason about during an incident than one clever shared one.

These queues are transients, which is an exception to the cache-vs-store rule. See [caching.md](caching.md#cache-vs-store).

### Retry ceilings

A queue that reschedules itself every 60 seconds refreshes its transient's TTL on every write, so the transient never expires while the queue is non-empty. Without a ceiling, one post the remote end will never answer for keeps the whole queue alive and is re-requested roughly 1,400 times a day.

`Wikidata_Qid_Task` keeps a per-post failure count in a separate transient (`ATTEMPTS`, `lwtv_wikidata_qid_attempts`) so the count survives the queue being rewritten each run:

- A transport failure or rate limit (`status` `error`) is not an answer. The post goes back on the queue and its count goes up.
- At `MAX_ATTEMPTS` (3) the post is dropped from the queue and logged. No checked-marker is written, so the next save or `wp lwtv wikidata backfill` still picks it up. The task gives up on the 60-second retry, not on the actor.
- Any real answer (including "ambiguous", which carries its own checked-marker) clears the count.

`Watch_Host_Names` applies the same idea to host-name enrichment: `fail()` counts attempts and `should_ask()` stops asking after `MAX_ATTEMPTS` (3). See [watch-providers.md](watch-providers.md#host-name-enrichment).

## Time budgets

Work that makes many HTTP requests from a single run is bounded by wall-clock time, and **stops before starting a request that could run past the budget**, rather than after one already has. Overshoot is then bounded by design rather than hoped for.

### Watch URL sweep

`Schedulers\Watch_URLs_Task` ([class-watch-urls-task.php](../../plugins/lwtv-plugin/php/schedulers/class-watch-urls-task.php)) runs the provider-URL sweep behind the Watch Term Check tab's Run Scan button. One HTTP request per provider URL is too many for a page load.

- `BUDGET` (240 s) per pass. `Debugger\Watch_URLs::find_bad_watch_urls()` only stores its findings at the end, so a pass killed by a PHP time limit would store nothing. Stopping itself first means every pass banks its work.
- Targets not reached are carried over as `watch-url-deferred` findings, unchanged, so a budget can never silently clear a real finding. If any were deferred, the task re-queues itself instead of looping, so each pass gets a fresh time limit and Action Scheduler controls the pacing.
- `set_time_limit( BUDGET * 2 )` is a backstop only.
- `queue()` is idempotent. Two concurrent passes would fetch every URL twice and race to write the findings.
- `DELAY` (10 s) lets the redirect back to the tab land before the scan starts competing for the database.

With a budget set, `find_bad_watch_urls()` skips the `Watch_URLs::SLEEP_US` pause between requests. The unbudgeted Sunday cron run keeps it.

### Admin-request lookups

Anything run inside an admin request is capped more tightly. The Watch Providers tab's name lookup uses `Watch_Hosts::UI_BATCH` (5 hosts), `UI_TIMEOUT` (3 s per request) and `UI_TIME_BUDGET` (15 s), well under a typical 30-second `max_execution_time` and 60-second gateway timeout. The unbounded version is `wp lwtv waystowatch enrich`.

## Facet re-index task

`Schedulers\Facet_Reindex_Task` re-indexes every character attached to a renamed actor or show. It runs deferred because one rename can fan out to every character on a long-running show. See [facetwp-indexing.md](facetwp-indexing.md).

## Missed schedule

Posts that miss their scheduled publish time are handled by `Features\Missed_Schedule`, on a recurring hourly Action Scheduler action. See [cron-schedule.md](../operations/cron-schedule.md#missed-schedule).

## Debugging

- `wp lwtv scheduler status`: which scheduler is active, plus TMDB batch and cache batch queue state.
- `wp lwtv scheduler tmdb trigger` / `wp lwtv scheduler missed trigger`: run now.
- Log topics `scheduler`, `caching`, `imdb-verify`, `wikidata` (enable in the Debugging settings page).
