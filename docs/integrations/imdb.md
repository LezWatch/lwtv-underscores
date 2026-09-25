# IMDb IDs

How we find IMDb IDs that have quietly gone stale, which third parties we check them against, and the TMDB response shapes that check depends on.

Code: `plugins/lwtv-plugin/php/_helpers/class-imdb-canonical.php` (`LWTV\_Helpers\Imdb_Canonical`, pure), `plugins/lwtv-plugin/php/schedulers/class-imdb-verify-task.php` (`LWTV\Schedulers\Imdb_Verify_Task`), `plugins/lwtv-plugin/php/_helpers/class-tmdb-response.php` (`LWTV\_Helpers\Tmdb_Response`, pure), `plugins/lwtv-plugin/php/wp-cli/cli-imdb.php` (`wp lwtv imdb`).

## Stale IDs that still work

IMDb reassigns title and name IDs and keeps the old one working as a redirect. A stale ID still opens the right page in a browser. It is well-formed, has the right prefix and works when clicked, so `Debug_Tool::validate_imdb()` can't catch it. But it breaks every exact-match API lookup keyed on it.

**Worked example: Only Murders in the Building.** TVMaze holds `tt11691774`, we held `tt12851524`, and both open the same show on imdb.com. TVMaze's `/lookup/shows?imdb=` matches against the single canonical ID TVMaze stores, so our alias returned 404. The show looked "not on TVMaze" when the real problem was our ID.

A stale show ID fails in more than one place. `wp-cli/cli-tmdb.php` resolves TMDB IDs from the same `lezshows_imdb` value, and `Calendar\TVMaze` looks shows up by it.

## Oracles

We can't detect staleness from IMDb itself. Automated requests there get blocked, and a check that quietly reports "fine" for everything is worse than no check. Instead we ask third parties that store a canonical IMDb ID and whose IDs we already hold:

| Post type | Oracle | Request | Field | Needs |
|---|---|---|---|---|
| Shows | TVMaze | `/shows/{lezshows_tvmaze_id}` | `externals.imdb` | `lezshows_tvmaze_id`, and `lezshows_tvmaze_ignore` not set |
| Actors | TMDB | `/3/person/{lezactors_tmdb_id}` via `_Components\CPTs::get_tmdb_info()` | `imdb_id` | `lezactors_tmdb_id` |

TVMaze is a particularly good oracle for shows. Like this site, it carries television only. So when it disagrees with us, its ID is the one guaranteed to point at a TV entity rather than a film.

**Continuations look exactly like stale IDs.** TVMaze folds some continuations into the parent entry. For example, Criminal Minds: Evolution lives on the parent Criminal Minds entry. So check which ID is right before changing anything. For shows, "Ignore TVMaze Match" (`lezshows_tvmaze_ignore`) takes a show out of verification. See [tvmaze.md](tvmaze.md#editorial-overrides).

## Verdicts

`Imdb_Canonical::verdict( $ours, $theirs )` compares the two IDs after `Imdb_Canonical::normalise()`. That function lowercases and pulls the `tt`/`nm` ID out of a bare ID or an imdb.com URL. Editors paste URLs into ID fields, and comparing a URL to a bare ID would be a false mismatch every time.

| Verdict | Constant | Meaning | Meta effect (`verify()`) |
|---|---|---|---|
| `match` | `MATCH` | Oracle agrees. | Clears canonical meta |
| `stale` | `STALE` | Both well-formed, and they differ. | Writes oracle's ID to canonical meta |
| `no-oracle` | `NO_ORACLE` | Oracle holds the record but has no IMDb link. Says nothing about ours. | Clears canonical meta |
| `not-set` | `NOT_SET` | We have no usable ID. `validate_imdb()` already reports it, so it isn't reported twice. | Clears canonical meta |
| `unreachable` | (from `Imdb_Verify_Task::verify()`) | Oracle couldn't be asked: transport failure, non-200, no oracle ID, or a response shape that can't answer. | **Untouched** |

`unreachable` leaves the meta alone, so an outage can neither clear a real flag nor invent one. Clearing on `match`, `no-oracle` or `not-set` means a corrected ID stops being reported without anyone cleaning up by hand.

## Canonical IDs

`lezshows_imdb_canonical` and `lezactors_imdb_canonical` hold the oracle's ID when, and only when, the last verdict was `stale`.

- `wp lwtv imdb list` re-runs `Imdb_Canonical::is_stale()` against the current `*_imdb` value rather than trusting the stored flag. An ID an editor has since corrected drops out straight away.
- `Wikidata\Identity::imdb_id()` falls back to `lezactors_imdb_canonical` when looking up an actor's QID ([actor-identity.md](../architecture/actor-identity.md#why-only-p345-matches-may-write)).
- Having no canonical meta means either "no disagreement found" or "never checked". `wp lwtv imdb status` treats those as the same on purpose, so nothing reads silence as verified.

## Imdb_Verify_Task

`Schedulers\Imdb_Verify_Task` checks IDs in the background.

- **Queueing:** `CPTs\Shows` and `CPTs\Actors` call `lwtv_plugin()->queue_imdb_verify()` on save. `queue_post()` makes no HTTP request and no judgement. It queues only posts with a usable IMDb ID and an oracle ID, skips shows with `lezshows_tvmaze_ignore`, and appends to the `lwtv_imdb_verify_queue` transient (1-day TTL).
- **Why not in `save_post`:** see [scheduling.md](../architecture/scheduling.md#no-http-in-save_post).
- **Draining:** the standard queue-and-drain shape ([scheduling.md](../architecture/scheduling.md#queue-and-drain)). The single `DELAY_US` (500 ms) is sized for TVMaze's limit, not TMDB's more generous one, because a mixed queue could be all shows. A single conservative delay is simpler than two rate-limit budgets for a background job nobody is waiting on. See [tvmaze.md](tvmaze.md#rate-limits).
- **Shared config:** `Imdb_Verify_Task::config()` exposes the per-type meta keys. `wp lwtv imdb` builds its candidate queries from them, so the CLI and the queue can't drift apart.
- `Schedulers\Wikidata_Qid_Task` uses the same queue shape on purpose ([actor-identity.md](../architecture/actor-identity.md#scheduler-wikidata_qid_task)).

`tmdb_imdb()` returns `unreachable` when an actor has no `lezactors_tmdb_id`. Without it, `get_tmdb_info()` falls back to `/find/{imdb_id}`, whose results carry no `imdb_id`, so the request would be wasted. The check is in `tmdb_imdb()`, not only in `queue_post()`, because the CLI and the debugger call `verify()` directly and skip the queue's gate.

`tvmaze_imdb()` treats `externals.imdb` present-but-null as a real answer (`''`, no oracle link), not a failure.

## TMDB response shapes

`_Components\CPTs::get_tmdb_info()` returns one of two incompatible shapes. Which one you get depends on our own post meta, not on anything the caller asked for:

| Post has | Endpoint | Shape |
|---|---|---|
| `lez{shows,actors}_tmdb_id` | `/3/{tv,person}/{id}` | Detail object, `id` at top level |
| only `lez{shows,actors}_imdb` | `/3/find/{imdb_id}?external_source=imdb_id` | Find envelope with `tv_results` / `person_results` arrays |

Nothing in the array says which one arrived, and a post changes shape the moment a backfill writes its TMDB ID. `Tmdb_Response` is the only code that handles both, so callers never read either shape directly.

- `Tmdb_Response::id()`: tries the detail object first, then `[result_key][0]`. A find response is keyed by the IMDb ID we sent, so result 0 is the only candidate.
- `Tmdb_Response::RESULT_KEYS`: shows use `tv_results` and actors use `person_results`. TMDB files TV movies under `movie_results`. That's a different question: `WP_CLI_LWTV_TMDB::look_up()` in `wp-cli/cli-tmdb.php` reports it as `wrong_kind` and doesn't store it.
- `Tmdb_Response::vote_average()`: TMDB's own 0.5–10 scale, unscaled. The ×10 conversion to the 0–100 `lezshows_3rd_scores` scale happens only in `Grading\TMDB::update_scores()`. TMDB's scale can't express "rated zero", so it sends `0` (with `vote_count` 0) for anything unrated, such as an unaired episode. `rating()` returns `null` for that. The score then stays `TBD` and the daily recheck picks it up once votes exist, instead of caching a hard zero for a day. Where TMDB sends a `vote_count`, that count is the authority on "has anyone rated this".
- `Tmdb_Response::imdb_id()`: returns `''` when TMDB has the record and no link (`imdb_id: null`), and `null` when this shape can't answer. Only `/person/{id}` carries `imdb_id` at the top level. `/tv/{id}` has it only with `append_to_response=external_ids` (nested under `external_ids`), and find results carry none. The difference is load-bearing: to `Imdb_Canonical::verdict()`, `''` is an answer that clears a stale flag, and `null` must leave our stored value alone.

## Commands

`wp lwtv imdb status|verify|list [shows|actors|all]`. See `wp help lwtv imdb`. `status` and `list` make no API calls. `verify --dry-run` still calls the APIs. `--order` uses the same `ORDER_CLAUSES` sampling caveat as [tvmaze.md](tvmaze.md#sampling-order).
