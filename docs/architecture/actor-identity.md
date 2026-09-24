# Actor identity (WikiData QIDs)

How an actor post is tied to a WikiData item, how much each link can be trusted, and what the death audit is allowed to do with it.

Code: `plugins/lwtv-plugin/php/wikidata/class-identity.php` (`LWTV\Wikidata\Identity`), `plugins/lwtv-plugin/php/wikidata/build/class-qid-trust.php` (`LWTV\Wikidata\Build\Qid_Trust`, pure), `plugins/lwtv-plugin/php/debugger/build/class-actor-death-rules.php` (`LWTV\Debugger\Build\Actor_Death_Rules`, pure).

`Identity` is the one place that answers "who is this actor on WikiData?". The WikiData diff view, the death audit, the QID backfill and the scheduler all ask it, so the answer means the same thing everywhere.

## Meta keys

| Constant | Meta key | Meaning |
|---|---|---|
| `Identity::META_QID` | `lezactors_wikidata_qid` | The QID. There is one QID field and it is the source of truth. |
| `Identity::META_SOURCE` | `lezactors_wikidata_qid_source` | How the QID got there: a `Qid_Trust::SOURCE_*` value. |
| `Identity::META_CHECKED` | `lezactors_wikidata_checked` | Timestamp of the last lookup that got an answer. Separates "WikiData has nothing" from "we never asked". |
| `Identity::META_IGNORE` | `lezactors_wikidata_ignore` | The write-lock ("Lock WikiData QID" in the editor). See [The write-lock](#the-write-lock). |
| `Identity::META_IMDB` / `META_IMDB_CANONICAL` | `lezactors_imdb` / `lezactors_imdb_canonical` | The IMDb ID we ask with, and TMDB's canonical one when ours is stale (see [imdb.md](../integrations/imdb.md#canonical-ids)). |

## Sources and trust

A QID in postmeta does not say where it came from. An editor may have typed it after checking. It may come from an exact IMDb match. Or a fuzzy name search may have picked a politician who shares an actress's name. Once stored, all three look the same. So the source is stored beside the QID, and nothing unattended may act on a guess.

| Source | `Qid_Trust` constant | How it was set | Trusted |
|---|---|---|---|
| `manual` | `SOURCE_MANUAL` | An editor typed or pasted it (see [The editor field](#the-editor-field)). | Yes |
| `imdb` | `SOURCE_IMDB` | Exact statement match on the IMDb ID, property P345. One IMDb ID is one person. | Yes |
| `name` | `SOURCE_NAME` | First hit from a WikiData name search. | No |
| `legacy` | `SOURCE_LEGACY` | Stored before sources were tracked, so it could be either of the above. | No |

`Qid_Trust::TRUSTED` holds the trusted pair. `Qid_Trust::normalise_source()` reads an empty or unrecognised value as `legacy`. Unknown values get the cautious reading, not the generous one. The legacy set is a mix of hand-typed IDs and old name-search first hits, and there is no way to tell them apart. Treating them as trusted would put a fuzzy match behind a death claim. They become trusted only when `--reverify` finds an IMDb match that agrees ([Backfill](#backfill-wp-lwtv-wikidata)).

`Identity::trusted_qid()` is what unattended code asks for. It makes no request and writes nothing. You get a QID with a trusted source, or an empty QID. When we hold an untrusted QID, the source is still returned, so callers can tell "unverified" from "none".

## Why only P345 matches may write

`Identity::qid_from_imdb()` uses CirrusSearch's `haswbstatement:P345=<nm id>`. This matches a statement value, not scored text, so it is a lookup rather than a search. It uses the same API host as everything else, so no SPARQL endpoint is needed. It asks for two results (`srlimit=2`) only to spot ambiguity. Two or more hits means WikiData holds duplicate or disputed items for that ID. That comes back as `ambiguous`, and nothing is picked.

`Identity::qid_from_name()` (`wbsearchentities`, first hit) is the weakest lookup by far. For a common name, the first hit is a coin toss between our actor and anyone else with that name. It is allowed in one place only: `Debugger\Actors::check_actors_wikidata()`, the diff view. A human reads every row there. `resolve()` stores those hits with source `name`, so they are never mistaken for verified. Everything unattended (`resolve_and_record()`, the CLI backfill, `Schedulers\Wikidata_Qid_Task`) uses the P345 lookup only. `resolve( $id, false )` turns the name fallback off.

The name search sends the raw `post_title` (`Identity::search_title()`), not `get_the_title()`. The `the_title` filter runs wptexturize, which turns "O'Brien" into a curly apostrophe and a double-barrelled hyphen into an en dash. WikiData doesn't index those. The search language is taken from the actor's `lezactors_wikipedia` host when there is one.

`Identity::imdb_id()` accepts `lezactors_imdb`, then `lezactors_imdb_canonical`. It also pulls the ID out of a pasted IMDb URL with `Imdb_Rules::id_from_url()`.

## store_qid(): the only machine write path

Every machine write goes through `Identity::store_qid()`: `resolve()`, `resolve_and_record()`, and through them the backfill and the scheduler. It:

- refuses anything that isn't `Q<digits>` or isn't an actor post;
- refuses, and logs to the `wikidata` debug topic, when the write-lock is set;
- writes QID, source and checked-marker together. A QID with no source reads as legacy, so writing them separately would quietly downgrade a good match.

Because the lock is enforced here and nowhere else, no new caller can forget it.

## The write-lock

`lezactors_wikidata_ignore` is a write-lock on the QID field and nothing more. While it is set, `store_qid()` refuses every write, so only a human can change the field and no backfill can overwrite an editor's value. It says nothing about whether the stored value is right. That is the source's job.

- `trusted_qid()` does not read the lock. A locked QID with a trusted source can still be identified. That is the normal state of a hand-corrected actor. Refusing it would make the death audit skip exactly the actors an editor took the trouble to pin down.
- `Qid_Trust::should_check()` checks the lock first. There is no point spending a request on an answer `store_qid()` would refuse to write.
- The lock means something extra only in the death audit. See [Death audit](#death-audit).

ACF `true_false` stores `"1"`/`"0"` as strings. So every reader (`Identity::is_ignored()`, the SQL in `cli-wikidata.php`, `ACF::lock_wikidata_qid_field()`) treats `''` and `'0'` as unset.

## The editor field

Both behaviours live in `plugins/lwtv-plugin/php/plugins/class-acf.php`.

**Read-only until locked.** `ACF::lock_wikidata_qid_field()` makes `lezactors_wikidata_qid` read-only unless the lock is on. Without the lock, the automated check owns the field, and a typed value would sit there looking accepted until the next backfill replaced it. It hooks `acf/prepare_field`, not `acf/load_field`. `load_field` runs once per field definition with no post in context. The usual workaround, `$_GET['post']`, is missing on Gutenberg's metabox request and on `post-new.php`. `prepare_field` runs per render, with the post already set up. On `post-new.php` there is no lock to read yet, so the field is left editable. Read-only is only a UI hint. The browser still submits the value. The real protection is `store_qid()` plus the source meta.

**Manual stamping.** `ACF::record_manual_wikidata_qid()` (`acf/update_value`) stamps the source `manual` only when the value actually changed. ACF re-saves every field on every post save. Stamping every time would turn a `name` guess into a trusted identity the first time anyone hit Update. Machine writes use `update_post_meta()` directly, so they never reach this filter. Clearing the field by hand deletes the source and the checked-marker, so the backfill treats the actor as never asked. `Identity::normalise_qid()` turns a pasted `wikidata.org/wiki/Q42` URL into the bare QID on this write path. It only accepts real wikidata.org URLs.

## Lookup outcomes and the checked-marker

`Identity::resolve_and_record()` is the unit the backfill and the scheduler run. What matters most is whether WikiData answered:

| Status | Meaning | Checked-marker |
|---|---|---|
| `found` | No QID before; P345 match stored as `imdb`. | Yes (via `store_qid()`) |
| `confirmed` | Stored QID agreed with the P345 match; now `imdb`. | Yes |
| `conflict` | Stored QID disagreed; replaced with the P345 match. | Yes |
| `none` | WikiData has no item with this IMDb ID. | Yes |
| `ambiguous` | Several items share this IMDb ID. | Yes |
| `error` | Transport failure, non-200, or HTTP 429. | **No** |
| `skipped` | No IMDb ID to ask with. | No |

`error` never writes a marker. A WikiData outage must not mark thousands of actors as permanently unresolvable. `ambiguous` is an answer, not an error. Two items sharing an IMDb ID is a stable fact about WikiData's data, and asking again gets the same answer every time. Treating it as an error makes the scheduler's retry queue loop on it. A human sees these through the death audit's `AMBIGUOUS` verdict.

On HTTP 429, `Identity::request()` sleeps `BACKOFF_MS` (5 s) and reports an error, never a no-match. `DEFAULT_SLEEP_MS` (400 ms) is the pause between requests. `USER_AGENT` names the site with a contact URL. Wikimedia's user-agent policy asks for a contactable client and reserves the right to block ones without it.

## Who is worth a request

`Qid_Trust::should_check()` decides, from an array alone, so the CLI, the scheduler and the SQL can't disagree. The checks run in this order:

1. Write-locked: no.
2. QID with a trusted source: no. A hand-typed QID needs no special case here, because editing the field sets source `manual`.
3. No IMDb ID: no. The exact P345 match is the only lookup allowed to write a trusted QID.
4. Untrusted QID: only with `--reverify`. On a routine run these aren't gaps. Re-checking them all would make a backfill of the real blanks impossible to size.
5. Asked before with no match: only with `--retry-missed`.
6. Otherwise: yes.

## Backfill: `wp lwtv wikidata`

`plugins/lwtv-plugin/php/wp-cli/cli-wikidata.php`. The subcommands are `status`, `backfill` and `actor <id>` (see `wp help lwtv wikidata`).

- `get_candidates()` is only a cheap SQL narrowing, a superset of the candidates. `should_check()` makes the real decision per actor.
- `--reverify` re-checks `name` and `legacy` QIDs against the P345 match. This is what turns an inherited QID into one the death audit will act on.
- `confirmed` rows are left out of the output. They are the common, boring result under `--reverify`, and listing them would bury the conflicts. The summary line on STDERR reports skip reasons too, because a run that did almost nothing reads the same as a small successful one.

`status` splits published actors into five groups that don't overlap and add up to the total. The key point is that trust comes from the source, not the lock. So a locked QID from a trusted source counts as `trusted`. The lock only matters where it makes automatic work impossible. The truth table is kept next to the SQL in `status_counts()`. The command warns if the groups stop summing to the published total.

## Scheduler: Wikidata_Qid_Task

`plugins/lwtv-plugin/php/schedulers/class-wikidata-qid-task.php` resolves a newly saved actor's QID, so the death audit can identify them without anyone remembering to run a backfill. When an actor is saved, `CPTs\Actors::save_post_meta()` calls `lwtv_plugin()->queue_wikidata_qid()`. `Wikidata_Qid_Task::queue_post()` makes no HTTP request ([scheduling.md](scheduling.md#no-http-in-save_post)). It asks `should_check()` without `reverify`, so upgrading inherited QIDs stays a deliberate bulk pass. Then it appends the post ID to the `lwtv_wikidata_qid_queue` transient.

It uses the same queue-and-drain shape as `Imdb_Verify_Task` ([scheduling.md](scheduling.md#queue-and-drain)): 25 per run, rescheduled every 60 s while anything remains. `error` results are re-queued up to `MAX_ATTEMPTS` (3) ([scheduling.md](scheduling.md#retry-ceilings)). The attempts map (`lwtv_wikidata_qid_attempts`) is pruned to what is still queued, so a post queued again later starts with fresh attempts. Only the P345 lookup runs here.

## Death audit

`wp lwtv audit actors` (`WP_CLI_LWTV_Audit::audit_actor_deaths()`) calls `Debugger\Actors::check_actor_death()` for each actor. That method gathers the facts, and `Actor_Death_Rules::evaluate()` decides. It reports and never writes a death date.

### Refuse rather than guess

A death date is a fact about a real person, and the two possible errors cost very different amounts. Missing a death leaves a page out of date. Claiming a wrong one tells readers a living actor has died. So an unresolved identity, a failed fetch and a contradicting birth date each get their own verdict. None of them becomes "no death found", let alone a death claim.

Identity comes from `trusted_qid()`, not `resolve()`. Nobody reviews this reasoning before it declares someone dead, so a `name` or `legacy` QID doesn't count as an identity. Those actors are reported as `UNVERIFIED`, and `wp lwtv wikidata backfill --reverify` is the fix. The audit reads stored state and never resolves anything, so it can't make things worse.

### Verdicts

`Actor_Death_Rules::verdict()` checks in this order:

| Order | Verdict | Constant | Reported |
|---|---|---|---|
| 1 | `has-date` | `HAS_DATE`: we already hold a death date. | No |
| 2 | `ignored` | `IGNORED`: locked with an empty QID (see below). | No |
| 3 | `ambiguous-identity` | `AMBIGUOUS`: source `imdb-ambiguous`. | Yes, with `--unresolved` |
| 3 | `unverified-identity` | `UNVERIFIED`: an untrusted QID is held. | Yes, with `--unresolved` |
| 3 | `no-identity` | `NO_IDENTITY`: no QID at all. | Yes, with `--unresolved` |
| 4 | `no-wikidata` | `NO_DATA`: trusted QID, but the entity had no claims or couldn't be read. | Yes, with `--unresolved` |
| 5 | `alive` | `ALIVE`: no P570 claim. | No |
| 6 | `suspect-match` | `SUSPECT`: P570 present, but birth dates conflict. | Yes |
| 7 | `death-found` | `FOUND`: P570 present, nothing contradicts it. | Yes |

`HAS_DATE` comes first, so a stored date is never argued with here. Comparing dates is `check_actors_wikidata()`'s job. `Actor_Death_Rules::UNRESOLVED` lists the metadata-gap verdicts. There are thousands of them, and they would drown out the few rows that need action, so the CLI hides them unless you pass `--unresolved`. `reportable()` holds the advice text shown in the `action` column. The CLI skips the request and the throttle for `HAS_DATE` and `IGNORED`. It only throttles when an entity was actually fetched. So a full run's cost tracks the work that is actually left.

`AMBIGUOUS` can't come out of the audit itself, since it never resolves. The branch stays for any caller that resolves before asking.

The audit deliberately doesn't use the Audit baseline tracker. A death finding is acted on once, and as soon as the date is entered the actor hits `HAS_DATE`. The list empties itself.

### editor_says_stop()

`Actor_Death_Rules::editor_says_stop( $ignored, $qid )` is the one place where the lock means more than "no machine writes":

- **Locked, QID empty:** settled. This is how an editor says "this person has no WikiData item". The lock stops the backfill from ever filling it, so reporting it would re-open a gap the editor already closed. The verdict is `IGNORED`.
- **Locked, QID present:** audited normally on that QID. The editor pinned an identity, and using it is the point of pinning.
- **Unlocked:** audited normally.

`check_actor_death()` passes the raw stored QID here, not `trusted_qid()`. The question is whether the editor left the field empty, not whether we can vouch for its contents. A locked QID we can't vouch for comes out as `UNVERIFIED` and gets reported, because the lock means no backfill can fix it. Only a human can.

Without the first branch, an actor an editor had marked as having no item would come back as `NO_IDENTITY` on every run. A toggle that silences nothing is a toggle nobody trusts twice.

### Birth-date guard

`Actor_Death_Rules::birth_dates_conflict()` guards the whole audit. A stored QID can be wrong, and an IMDb ID can have been reassigned. Either way the result is a confident death date for a stranger. We already hold a birth date for most actors, so comparing birth dates is the cheapest way to notice.

Unknown parts never count as a conflict. WikiData often records birth dates to the year or month only. Treating `1976` against `1976-05-25` as a contradiction would hide exactly the correct matches the audit is looking for. Only two known parts that differ count. `date_parts()` reads three formats that all exist in the data:

- `Ymd`: what the ACF date_picker actually writes to postmeta, even though its return_format says `Y-m-d`;
- `Y-m-d`: WikiData, and `Debugger\Actors::format_our_date()`;
- `m/d/Y`: rows the ACF migration didn't convert.

A `00` month or day is WikiData saying "unknown", so it comes back empty. See also [meta-storage-quirks.md](meta-storage-quirks.md).

## Related

- The show-side equivalent is a warning, not a model: `Calendar\TVMaze::get_tvmaze_info_show()` writes a fuzzy name-search hit straight into `lezshows_tvmaze_id`. See [tvmaze.md](../integrations/tvmaze.md#lookup-chain).
- Tests: `tests/unit/Wikidata/QidTrustTest.php`, `tests/unit/Debugger/ActorDeathRulesTest.php`.
