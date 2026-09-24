# Cron Schedule

What the server's scheduled WP-CLI jobs run, on which day, and why they are arranged the way they are.

Background mechanics (Action Scheduler, queues, time budgets) are in [scheduling.md](../architecture/scheduling.md).

## Wrapper scripts

The server crontab runs the shell wrappers in [`cron/`](../../cron/). The copies in the repo are a backup and are not deployed. The API paths they reference are locked by IP.

| Script | Runs | Healthcheck ID |
|---|---|---|
| `hourly.sh` | `wp lwtv generate cron hourly` | `due-now-hourly` |
| `ontheten.sh` | `wp cron event run --due-now` | `due-now-10-min` |
| `debug.sh` | `wp lwtv generate debug` (today's check) | `run-debug-checks` |
| `lists.sh` | `wp lwtv generate lists` | `generate-lists` |
| `otd.sh` | `wp cache flush`, then `wp lwtv generate otd` | `set-char-and-show-otd` |
| `tvmaze.sh` | `wp lwtv generate tvmaze` | `update-tvmaze` |

The times each script runs are set in the server crontab, which is not in the repo. The repo also has no wrapper for `wp lwtv generate cron daily` (described below), although `CLAUDE.md` names it as the daily entry point. Check the server crontab to see which of the two drives the daily debug check, so that it does not run twice.

Every wrapper follows the same pattern:

1. `cd` into the site, or log the failure and ping the healthcheck as failed.
2. Run the command with stdout and stderr captured to a temp file.
3. On a non-zero exit, append that output to `cron/<name>-debug.log`. Successful runs leave no log.
4. Call `cron/ping.sh <uuid> <true|false>`, which POSTs to the healthcheck service (`<base>/<uuid>`, or `<base>/<uuid>/fail`) with a 10-second timeout and 5 retries.

## Hourly run

`wp lwtv generate cron hourly` (`WP_CLI_LWTV_Generate::run_cron_hourly()`) publishes posts that missed their schedule. See [Missed schedule](#missed-schedule).

## Daily run

`wp lwtv generate cron daily` (`run_cron_daily()`), in order:

1. `run_update_lists()`: caches the published show and actor counts (`lwtv_count_shows`, `lwtv_count_actors`) for 24 hours.
2. `BYQ::daily_cache_refresh()`: invalidates and re-warms the Bury Your Queers death caches (see [caching.md](../architecture/caching.md#bury-your-queers-caches)).
3. `run_tvmaze()`: downloads the TVMaze ICS file.
4. `Debugger\Log::rotate()`: rotates the debug log. See [Log rotation](#log-rotation).
5. `run_debug_checker( gmdate( 'D' ) )`: that day's debugger checks.
6. `Statistics_Cache_Warming::warm_all()`: a daily backstop so the statistics caches are never stale for long when nobody is editing.

## Debug checks by day

`wp lwtv generate debug <day>` takes the three-letter weekday (`mon` ... `sun`). Without one it uses today (`gmdate( 'D' )`, UTC).

| Day | Checks | Cost |
|---|---|---|
| Mon | `Queers::find_queer_chars()` | SQL |
| Tue | `Characters::find_byq_problems()` | SQL |
| Wed | `Dupes::find_duplicates()`, `OnAir::find_on_air_problems()`, `wp lwtv waystowatch enrich`, `Watch_Host_Collisions::find_host_collisions()` | SQL, plus capped HTTP (enrich) |
| Thu | `Actors::find_actors_problems()`, `find_actors_no_imdb()`, `find_actors_incomplete()` | SQL |
| Fri | `Characters::find_characters_problems()` | SQL |
| Sat | `Shows::find_shows_problems()`, `find_shows_no_imdb()` | SQL |
| Sun | full FacetWP re-index (`FWP()->indexer->index()`), then `Watch_URLs::find_bad_watch_urls()` | HTTP, slow |

### Why the HTTP jobs are spread out

Only two checks make remote requests, and they are on different days so that a cron timeout still tells you which one caused it.

- **Sunday: watch-provider URLs.** One request per provider URL, with `Watch_URLs::SLEEP_US` between them. It is the only job that could plausibly hit a cron wrapper's timeout, so it runs on the quietest day and goes **last**: if it times out, everything before it has already finished.
- **Wednesday: host-name enrichment.** Wednesday's other checks are plain SQL. The enrich step runs through `WP_CLI_LWTV_WaysToWatch::__invoke( array( 'enrich' ) )` rather than the private method, so cron takes exactly the path a human does. It is safe to repeat weekly: it skips hosts that already have a term and hosts already asked, stops asking a host after `Watch_Host_Names::MAX_ATTEMPTS` failures, and processes the default `--limit` (25) per run, so a backlog is worked through over several weeks rather than in one long run.
- **Wednesday: contested hosts.** `find_host_collisions()` costs two queries and no requests, so it rides along with the SQL checks. It should almost always find nothing. It exists to notice the day someone points a second provider term at a host that already has one. See [watch-providers.md](../architecture/watch-providers.md#contested-hosts).

## Log rotation

`Debugger\Log::rotate()` runs before the day's checks add to the log. Rotation is by **size**, not daily: a small log rotated every night just buries useful history under near-empty files.

- The daily rotation threshold is `Log_Rules::ROTATE_AT` (1 MB).
- `Log::append()` has its own mid-request backstop at `Log_Rules::MAX_BYTES` (10 MB), to catch runaway loops between cron runs.
- Rotated files are named by `Log_Rules::rotated_name()` with a `Ymd-His` stamp, and `prune()` keeps `Log_Rules::KEEP` (5) of them.

`wp lwtv debug-log` shows the log settings, rotated files and topics.

## Missed schedule

`Features\Missed_Schedule` ([class-missed-schedule.php](../../plugins/lwtv-plugin/php/features/class-missed-schedule.php)) publishes posts still in `future` status after their `post_date`, up to ten per run, with `wp_publish_post()`.

- **With Action Scheduler:** `init_action_scheduler()` (on `action_scheduler_init`, once the data store is ready) creates a recurring hourly action, `lwtv_missed_schedule_check` in group `lwtv`, if none exists.
- **Without it:** `missed_schedule()` falls back to a 15-minute `lwtv_missed_schedule` transient that stops overlapping runs.
- **Hourly cron:** `wp lwtv generate cron hourly` calls `missed_schedule()` directly as well.

Manual control:

- `wp lwtv scheduler missed status`: which method is active and when the next check is due.
- `wp lwtv scheduler missed trigger`: schedule an immediate check (or run it, without Action Scheduler).
- `wp lwtv scheduler missed`: run a check now.
