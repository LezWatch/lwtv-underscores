# TVMaze

How we use the TVMaze API: rate limits, how a show gets matched to a TVMaze entry, what TVMaze will and won't carry, and what its cast data can and can't tell us.

Code: `plugins/lwtv-plugin/php/wp-cli/cli-tvmaze.php` (`WP_CLI_LWTV_TVMaze`, `wp lwtv tvmaze`), `plugins/lwtv-plugin/php/wp-cli/cli-audit.php` (`WP_CLI_LWTV_Audit`, `wp lwtv audit`), `plugins/lwtv-plugin/php/calendar/class-tvmaze.php` (`LWTV\Calendar\TVMaze`), `plugins/lwtv-plugin/php/schedulers/class-imdb-verify-task.php`. API reference: <https://www.tvmaze.com/api>.

## Meta keys

| Meta key | Constant (`WP_CLI_LWTV_TVMaze`) | Written by |
|---|---|---|
| `lezshows_tvmaze_id` | `META_TVMAZE` | `wp lwtv tvmaze backfill`, and `Calendar\TVMaze::get_tvmaze_info_show()` (see [Lookup chain](#lookup-chain)) |
| `lezshows_tvmaze_checked` | `META_CHECKED` | Backfill: timestamp of the last lookup that got an answer. Separates "TVMaze has nothing" from "never asked". |
| `lezshows_aired_years` | `META_AIRED` | `seasons` action / `--with-seasons` |
| `lezshows_tvmaze_ignore` | `META_IGNORE` | Editors only ("Ignore TVMaze Match") |
| `lezshows_tvmaze_id_manual` | `META_ID_MANUAL` | Editors only ("TVMaze ID (manual)"). Never machine-written. |

## Rate limits

TVMaze documents its limit as *at least* 20 calls every 10 seconds per IP. That's 2 per second. A client that goes over gets HTTP 429 and should back off for a few seconds and retry, not treat it as a permanent failure. TVMaze also strongly recommends a user agent that identifies the client uniquely.

| Caller | Pause | On 429 |
|---|---|---|
| `WP_CLI_LWTV_TVMaze` | `DEFAULT_SLEEP_MS` 500 ms (`--sleep`) | Sleeps `BACKOFF_MS` (5 s), reports an error, and writes no checked-marker |
| `Imdb_Verify_Task` | `DELAY_US` 500 ms | Returns `unreachable` |
| `WP_CLI_LWTV_Audit` | `WAIT_TIME` 500 ms | `tvmaze_get()` retries up to 3 attempts, sleeping `max( Retry-After, 5 )` s. `resolve_show()` retries once after 10 s. |

500 ms sits right on the documented budget. Don't copy `cli-tmdb.php`'s 250 ms, because TMDB's limits are more generous. `--with-seasons` makes two calls per show, which halves the effective rate again. That extra headroom is intentional. `WP_CLI_LWTV_TVMaze::USER_AGENT` identifies the site with a contact URL.

## Lookup chain

`WP_CLI_LWTV_TVMaze::look_up()`, used by `backfill`:

1. **Manual ID:** `lezshows_tvmaze_id_manual` wins outright, with no API call. A human looked at both records.
2. **IMDb lookup:** `/lookup/shows?imdb=<lezshows_imdb>`. TVMaze answers a match with a 301 to the show's URL. `wp_remote_get` follows it, so it normally arrives as 200, and 301 is accepted as success too in case redirects are disabled by a filter. A 404 means TVMaze has nothing. It is the only non-2xx answer that earns a checked-marker.
3. **No IMDb ID: skipped.** There is no name matching.

**Why no name matching.** A TVMaze name match is a guess. A wrong TVMaze ID feeds wrong aired years into the show score ([show-score.md](../scoring/show-score.md)). So shows with no IMDb ID are reported as skipped instead of guessed at. This is not a TVMaze requirement: its inclusion policy says nothing about IMDb. An exact ID lookup is simply the only match we trust enough to write into a scoring input. Adding an IMDb ID brings a show into scope.

**`look_up()` is read-only on purpose.** `Calendar\TVMaze::get_tvmaze_info_show()` runs a similar chain (stored TVMaze ID, then IMDb lookup, then a fuzzy `/singlesearch/shows?q=<name>`). It writes whatever `id` comes back into `lezshows_tvmaze_id` as a side effect. Sharing it would give `--dry-run` a different code path from the real run. It also means `lezshows_tvmaze_id` can hold a wrong, name-matched ID and keep trusting it. This is a known hazard, and it's why the manual ID lives in a separate field that nothing overwrites. The actor-side design avoids the same trap ([actor-identity.md](../architecture/actor-identity.md)).

## Editorial overrides

The show's "Ignore TVMaze Match" toggle (`lezshows_tvmaze_ignore`) reveals "TVMaze ID (manual)" (`lezshows_tvmaze_id_manual`):

- **Toggle + manual ID:** "this show is that TVMaze entry". `look_up()` uses it directly. This works even for shows whose titles don't match TVMaze's at all.
- **Toggle, no ID:** "I looked, there's nothing to match". The show stops being a backfill candidate or an unmatched problem. `Imdb_Verify_Task` and `Debugger\Collect\Imdb_Collector` also skip the IMDb staleness check for it, because there is no TVMaze record to compare against. It does not silence the not-set/invalid IMDb checks.

ACF `true_false` stores `"1"`/`"0"`, so `'0'` is not an acknowledgement. `WP_CLI_LWTV_TVMaze::overrides()` reads both keys for the whole site in one query and keeps them in memory. They are expected to be rare, so an in-memory map beats a meta read per show.

## Inclusion policy and continuations

Not every show we list is on TVMaze. TVMaze's bar for non-curated web channels is high: credited cast and crew, sequential numbering, a fixed schedule, plus notable credits, a verified budget or a broadcast re-run (see TVMaze's [data policies](https://www.tvmaze.com/faqs/9/data-policies)). So a no-match group full of web series is expected. One full of ordinary series is a data problem, usually a stale IMDb ID on our side ([imdb.md](imdb.md#stale-ids-that-still-work)).

- `wp lwtv tvmaze status` breaks the no-match group down by `lez_formats` term, so you can tell the two apart.
- `wp lwtv tvmaze missing` lists each show with its reason (`no-match`, `no-imdb`, `never-checked`), IMDb ID and airdates. A count alone can't be acted on. The response to "TVMaze doesn't carry this web series" is nothing, and the response to "our IMDb ID is stale" is a data fix.
- `wp lwtv tvmaze reconcile` searches `/search/shows?q=` **by name** for no-match shows and reports where TVMaze's IMDb ID differs from ours. It is read-only and only suggests corrections for review. It uses the fuzzy guess that `backfill` refuses to write, which is fine because a human reviews every row.

**Continuations.** TVMaze keeps some continuations on the parent entry. For example, Criminal Minds: Evolution lives on Criminal Minds. Such a show will never match on its own IMDb ID, and in `reconcile` it looks just like a stale ID. Use the toggle with no ID.

## Sampling order

`--order` maps to `ORDER_CLAUSES` (`oldest` = `p.ID ASC`, `newest`, `random` = `RAND()`). These are fixed strings, never user input. `oldest` is the default because repeated `--limit` runs then move forward through the backlog. It's a poor sample, though: the oldest posts are the long-established mainstream shows, so a hit rate measured that way comes out optimistic. Use `--order=random` when the number needs to mean something. `wp lwtv imdb` uses the same clauses.

## Aired years

`seasons` (and `backfill --with-seasons`) fetches `/shows/{id}/seasons` and derives `lezshows_aired_years` with `Longevity::aired_years_from_seasons()`. `backfill` only touches shows that are *missing* an ID, so `--with-seasons` can only reach shows that run matched. `seasons` covers shows that already had one. `fetch_aired_years()` can't tell "no usable dates" from a transport failure, so it writes nothing either way and a re-run picks the show up again.

`--scoring-only` keeps only shows where aired years could change the score. `aired_years_would_be_used()` mirrors the tier order in `Longevity::run_years()`: a finished show with a curated season count never consults aired years. The filter runs in PHP rather than SQL so it calls the same test the scoring does. See [show-score.md](../scoring/show-score.md).

## Cast data limits

`wp lwtv audit` checks whether characters appeared in a given year (`WP_CLI_LWTV_Audit::character_appeared_in_year()`). The public API can't answer that for main cast:

- `/shows/:id/cast` lists main cast once for the whole show, with no years;
- `/episodes/:id/guestcast` contains **only** guest cast.

So a regular never shows up in any year's episode data, however many episodes they're in. Absence therefore means *unknown*, not *no*:

| Verdict | Constant | Meaning |
|---|---|---|
| `yes` | `APPEARED_YES` | Named in that year's episode guest cast. |
| `unknown-main-cast` | `APPEARED_UNKNOWN_MAIN_CAST` | In the all-time main cast. TVMaze has no per-year data for them. |
| `unknown-no-data` | `APPEARED_UNKNOWN_NO_DATA` | That year's episode cast wasn't fetched. |
| `no` | `APPEARED_NO` | Episode cast was fetched and they weren't in it. |

Treating silence as "didn't appear" is how you end up telling an editor to add a year the character was never in. Character names are matched with the names of every actor who plays them. Shows in `WP_CLI_LWTV_Audit::SKIP_GENRES` (`animation`, `anime`) are skipped, because voice actors play several characters and the mapping has to be done by hand.

`character_appeared_in_year()` is the only code that answers this question. If TVMaze ever exposes per-character appearances, answer from that first and keep the current logic as the fallback.

## Terminal output

`display_title()` decodes HTML entities in titles for CLI tables. `fit()` truncates with `mb_substr()` and pads by hand, because `sprintf( '%-42s' )` pads to a byte count. It uses `mb_strlen()`, not `mb_strwidth()`: WordPress polyfills the first two in `wp-includes/compat.php` but not `mb_strwidth()`. Full-width CJK therefore counts as one column, which is acceptable for a CLI table.

## Background

- `docs/plans/completed/on-air-audit-plan.md`
