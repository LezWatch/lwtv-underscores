# Caching

How LWTV caches derived data, where it keeps data that must not be lost, and how the statistics caches are invalidated and warmed.

## Transients wrapper

All plugin code reads and writes transients through `lwtv_plugin()->get_transient()`, `set_transient()` and `delete_transient()`, implemented in `LWTV\_Components\Transients` ([plugins/lwtv-plugin/php/_components/class-transients.php](../../plugins/lwtv-plugin/php/_components/class-transients.php)).

Today the wrapper delegates to core, so every LWTV transient is an ordinary WordPress transient. It exists as a **seam for swapping the storage backend** (a separate database, for example). Two rules follow from that:

- **Deletes go through the wrapper too.** If a key is written through the wrapper but deleted through core `delete_transient()`, a future backend swap would write to the new store and delete from the old one, leaving cache nobody can clear. `Rest_API\BYQ::invalidate_death_list_cache()` is the example to copy.
- **Expiry is core's job only while the backend is core.** Core's `delete_expired_transients()` (daily, via `wp_scheduled_delete`) sweeps expired rows, which is why `wp lwtv scheduler status` has no "transient cleanup" section. A backend that is not the options table would need its own expiry, and that section would be worth building then.

### LWTV_DISABLE_TRANSIENTS

When the `LWTV_DISABLE_TRANSIENTS` constant is true, `get_transient()` always returns `false`, but `set_transient()` **still writes**. The asymmetry is deliberate:

- A development database stays production-shaped, so `wp transient get` shows what the site would serve.
- Turning the flag off gives a warm cache rather than a cold one.

The flag means "do not let a cached value hide fresh data from me", not "do not keep records". Because of it, anything that must be readable in development (freshness indexes, stores) must not depend on `get_transient()`. `Transients::get_this_year_generated_time()` reads the stats index directly for this reason.

## Cache vs store

- A **cache** is derived data that can always be recomputed. Use a transient. Statistics are the case the wrapper was written for.
- A **store** is data with no cheaper source to fall back to (debugger findings, worklists, enrichment results). Do not put a store in a transient. Stores go in non-autoloaded options.

`Debugger\Findings_Store` is the reference implementation: one option per check, a small index (`lwtv_debug_findings_keys`), and an explicit expiry (`Findings_Store::TTL`, ten days) stored alongside the data so `load()` can still return `false` for "absent or expired". `Watch_Host_Names` (`lwtv_watch_host_names`) and `Debugger\Baseline_Store` follow the same shape.

**Known exceptions.** Some background queues still live in transients: `Schedulers\Cache_Batch_Task` (`lwtv_cache_batch_queue`, `lwtv_cache_batch_status`), `Imdb_Verify_Task` and `Wikidata_Qid_Task`. They are read through the wrapper, so with `LWTV_DISABLE_TRANSIENTS` on, each `queue_post()` starts from an empty queue. They are also subject to the tier split described next.

## CLI and web cache tiers

On production, WP-CLI does not load the object-cache drop-in that web requests use. `get_transient()` therefore asks whichever tier the current process has: web requests see the persistent object cache (Redis), CLI sees the `_transient_*` rows in `wp_options`. A transient written by a cron job can be invisible to wp-admin, and the reverse.

This is the main reason stores go in options: `get_option()` sees the same row from either side. It is also why the one-shot findings migration (`wp lwtv migrate acf debugfindings`, see [cmb2-to-acf.md](../operations/migrations/cmb2-to-acf.md#debugger-findings-to-options)) reads the option rows directly instead of calling `get_transient()`.

### delete_transient() and option rows

With a persistent object cache active, `delete_transient()` deletes the cached copy and leaves any `_transient_*` / `_transient_timeout_*` option rows in place. Code that must be sure a transient is gone from both tiers deletes the option rows explicitly as well. `WP_CLI_LWTV_Migrate::drop_findings_transient()` does this so that running the migration twice is a no-op.

## Statistics cache tiers

`Transients::get_cache_dependencies()` groups statistics transient key patterns into tiers:

| Tier | Priority | TTL | Cleared on save? |
|---|---|---|---|
| `counts` | `immediate` | `HOUR_IN_SECONDS` | yes |
| `derived` | `background` | `DAY_IN_SECONDS` | yes |
| `stable` | `preserve` | `WEEK_IN_SECONDS` | never (currently has no patterns) |

`get_tiers_for_content_type()` maps what changed to tiers: the three CPTs clear `counts` and `derived`; `taxonomy` also lists `stable` (skipped because of its priority); `score` clears `derived`; anything else clears `derived`.

`invalidate_statistics_cache()` only queues the request. `process_deferred_cache_invalidation()` runs on `shutdown`, after the response is sent, collects every pattern from the affected tiers, clears them in one `clear_cache_tier()` call, then schedules one debounced warm (see [Warming](#warming)).

`clear_cache_tier()` has two passes:

1. **Index walk.** Every key in the stats index that matches a pattern is deleted through `delete_transient()`, which reaches the object cache when one is active.
2. **SQL sweep.** Only when `wp_using_ext_object_cache()` is false: `DELETE ... LIKE '_transient_<pattern>'` and its timeout row. This also catches keys that predate the index. Under Redis the rows do not exist, so the pass is skipped.

### Wildcard patterns

Patterns use a single **trailing** `*`.

- `key_matches_pattern()` does a prefix match when the pattern ends in `*` and an exact comparison otherwise.
- The SQL sweep and `get_cache_statistics()` translate `*` to `%`. Any other character, including `.`, is literal to `LIKE`.

A mid-string wildcard (`a_*_b`) matches nothing in the index walk but would match in the SQL sweep. Under a persistent object cache only the index walk runs, so such a pattern is a silent no-op in production. Use exact keys instead.

### The stats index

`Transients::STATS_INDEX_OPTION` (`lwtv_stats_cache_index`) maps each tracked stats key to the Unix time it was last built. It exists because a persistent object cache stores transients outside `wp_options`, so there is nothing to `LIKE` against. The index lets the plugin:

- evict stats transients by pattern through `delete_transient()`, and
- report an honest "last calculated" time on the statistics pages.

A key is **tracked** when it matches any tier pattern or one of `extra_stats_patterns()` (the `/this-year/` per-year keys `lwtv_*_year_*`). `set_transient()` records tracked keys in an in-request buffer, and `flush_stats_index()` writes the buffer to the option once on `shutdown`. `current_stats_index()` merges the buffer so a cold render can build data and print its "last calculated" note in the same request.

`get_this_year_generated_time()` returns the **oldest** build time among a year's `lwtv_*_<year>` keys, since the page is only as fresh as its stalest piece. It reads the index only and never writes it.

### Why post_meta_* is not tracked

`Queeries\Post_Meta` caches `WP_Query` results under `post_meta_*` keys (30-minute TTL) that are never invalidated. Adding the pattern would be worse than leaving it out:

- The key is an md5 of the call arguments, so there is one per show (`lezchars_show_group`), per actor (`lezchars_actor`), per date (`lezactors_birth`) and per IMDb ID and QID (the two REST lookups).
- Tracking them would grow `lwtv_stats_cache_index`, a single option, with the catalogue, and `clear_cache_tier()` walks the whole index against every pattern on every save.

**Rule:** a pattern makes every key it matches tracked. That suits a handful of aggregate keys and is wrong for a high-cardinality keyspace.

The real fix is upstream in `Post_Meta::make()`: it serialises a whole `WP_Query`, and caching IDs under a low-cardinality key per call site would remove both this problem and the blob size (see [docs/plans/queery-cache-ids-not-objects.md](../plans/queery-cache-ids-not-objects.md)). Until then these keys expire on their TTL. The one screen that cannot tolerate that staleness, the Exclusion Checker (`Admin_Menu\Exclusions`), queries directly.

## Warming

A burst of edits collapses into one comprehensive warm on Action Scheduler (`Transients::WARM_HOOK`, `lwtv_warm_statistics_cache`, group `lwtv`):

- `schedule_stats_warm()` unschedules the pending warm and reschedules it to `next_stats_warm_time()`.
- The target trails the **last** edit by `WARM_DEBOUNCE_DELAY` (2 minutes) but never goes past the **first** edit plus `WARM_MAX_DELAY` (10 minutes). The burst deadline is kept in `lwtv_stats_warm_deadline`.
- `next_stats_warm_time()` is pure arithmetic and unit-tested (`tests/unit/Components/WarmScheduleTest.php`).
- `Statistics_Cache_Warming::warm_all()` clears the deadline when it finishes.
- Without Action Scheduler no warm is scheduled. The daily cron still calls `warm_all()` (see [cron-schedule.md](../operations/cron-schedule.md#daily-run)), and pages rebuild lazily.

`warm_all()` runs each step in its own `try`/`catch`, so one failing builder cannot abort the rest. Warming calls each builder's normal cached path, so it rebuilds only keys that are missing (usually because invalidation just cleared them).

### Warm call to stats view

What each step in `Schedulers\Statistics_Cache_Warming` ([class-statistics-cache-warming.php](../../plugins/lwtv-plugin/php/schedulers/class-statistics-cache-warming.php)) pre-builds, and where it is read. Template paths are under `plugins/lwtv-plugin/php/statistics/templates/` unless noted.

| Step | Calls | Read by |
|---|---|---|
| `warm_count_caches` | `Characters_Builder::get_characters_for_year()`, `get_dead_characters_for_year()`, `get_overview_character_stats()`; `Shows_Builder::get_shows_for_year()`; `Statistics_Optimized::generate_total_counts()` for characters, shows, actors and dead characters; `Dead::total_dead_shows()` | `/this-year/` overview, headline counts |
| `warm_count_caches` → `warm_this_year_trends` | `This_Year_JSON::ten_years()` reduced by `Trends::to_count_map()`, stored under `Trends::cache_key()` | `this-year/templates/overview.php`. `ten_years()` makes five builder calls across eleven years, so it must never run inline on page load. |
| `warm_death_statistics` | `Dead` data, years, list, roles, and gender/sexuality breakdowns | death views |
| `warm_taxonomy_statistics` | `Taxonomy_Optimized::make_comprehensive()` for character `lez_gender`/`lez_sexuality`, show `lez_tropes`/`lez_genres`, actor `lez_actor_gender`/`lez_actor_sexuality` | taxonomy stats views |
| | `Character_Identity_Trend::generate_decades()` and `generate_firsts()` per character taxonomy (both read the same cached row set) | `characters/gender.php`, `characters/sexuality.php` |
| | `Actors::generate_roles_totals()`, `generate_prolific_by_role()` | `actors/roles.php`, and the actors overview through `generate_actors_statistics( 'array', 'roles' )` |
| | `Actors::generate_straight_queer_gap()`, `generate_prolific_by_orientation()` | `actors/sexuality.php` |
| | `Actors::generate_cis_queer_gap()`, `generate_prolific_by_gender()` | `actors/gender.php` |
| | `Unknown_Actor::generate_report()` | `actors/unknown.php` |
| `warm_on_air_statistics` | `On_Air_Optimized::generate()` for shows and characters; `Actors::generate_active_this_year()` | on-air views; `actors/overview.php`, `actors/sexuality.php` |
| `warm_queer_irl_statistics` | `Queer_IRL::generate_all_data()`; `Character_Queer_Cast_Firsts::generate_queer_actor_firsts()`, `generate_trans_actor_oldest()` | `characters/queer-irl.php` |
| `warm_formats_statistics` | `Formats::generate()` | formats view |
| `warm_loved_statistics` | `We_Love_It::generate()` | Shows We Love |
| `warm_worth_it_statistics` | `Worth_It::generate()` | Worth It |
| `warm_nation_statistics`, `warm_station_statistics` | `get_nation_summaries()` / `get_station_summaries()` and `get_top_nations( 10 )` / `get_top_stations( 10 )` | nation and station views (via `Stats_Generator`), top-10 lists in `main.php` |
| `warm_cliche_leaders_statistics` | `generate( 5 )` on `Cliche_Leaders`, `Character_Show_Leaders`, `Character_Actor_Leaders`, `Character_Death_Leaders`, `Character_Longevity_Leaders` | `characters/most-cliches.php` (`$lwtv_most_limit = 5`) |

**Leaderboard keys include the limit.** Each leaderboard caches under `<name>_top<limit>`, so the page's top-5 request needs its own warm. Warming only the default (`TOP_LIMIT`, 25) would leave the top-5 lookup to build cold. The reverse is also true: `characters/overview.php` calls `Cliche_Leaders::generate()` with the default limit, and that key is not warmed.

Only `cliche_leaders_characters_*` is in a tier. The `character_*_leaders_top*` keys are untracked, so saves never invalidate them and they refresh on their one-week TTL.

`warm_cache_tier()` also accepts `counts`, `derived` and `stable`, which run subsets of the steps above. The scheduled warm passes no arguments, so it always runs `all`.

## Page cache

After a show, character or actor save, `Scheduler::cache_queue()` asks for the related front-end URLs to be purged (`Plugins\Cache::collect_cache_urls_for_actors_or_shows()`).

- **With Action Scheduler:** `Schedulers\Cache_Batch_Task` queues the post with a priority by post type (`POST_TYPE_PRIORITIES`: shows before characters before actors), then drains the queue in batches of `BATCH_SIZE` (75) URLs with `DELAY_BETWEEN_BATCHES` between them, rescheduling itself every 30 seconds until the queue is empty.
- **Without it, or if queueing fails:** `Schedulers\Cache_Queue::queue()` collects the URLs and invalidates the related posts' object cache immediately, then purges the URLs once on `shutdown`.

`Cache_Queue` is always instantiated (its `shutdown` hook is registered in `Scheduler::initialize_task_handlers()`) but only receives work in the fallback path.

## Per-request memos

Hot lookups are memoised in static properties for the length of one request. Examples:

- `Watch_Hosts::$in_use`, `$show_ids`, `$terms`, `$term_urls`, `$host_map`. The watch-URL scan asks for `term_urls()`, a three-way join, twice in one run.
- `Watch_Host_Names::$cache`, so a page with several links reads the option once.
- `Admin_Menu\Validation::$counts`. The tab picker and the intro table both need `tab_counts()`.
- `Transients::$tracked_patterns_cache`, the flattened tracked-pattern list.
- `Calendar\Names::resolve()`, shared through `Calendar_Object_Pool` because the same show recurs across the three weeks the calendar renders.

Setters that change the underlying option (for example `Watch_Host_Names::set()`) update the memo too, so the rest of the request sees the write.

## Bury Your Queers caches

`Rest_API\BYQ` keys its caches on a data-version hash (`byq_data_version_hash`, one hour): `byq_death_list_<hash>`, `byq_last_death_<hash>` and `byq_on_this_day_<md5>_<hash>`. `invalidate_death_list_cache()` reads the current hash **before** deleting it, then deletes the hash and every key built from it. `daily_cache_refresh()`, called by the daily cron, invalidates and then re-warms the death list and last death.

## Other caches

- Calendar: see [calendar.md](calendar.md#cache-versioning).
- Stats SQL and query plans: see [docs/sql/optimization.md](../sql/optimization.md).
