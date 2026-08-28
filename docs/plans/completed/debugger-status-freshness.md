# Plan: make the badge and the report agree

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`).
**Symptom:** the tab picker reads `Watch Term Check (47)` while the tab itself says *"No results yet — this check has not run, or its results have expired."*
**Scope:** `Debugger\Status`, `Baseline_Store`, `Validator\Report`, `Validator\Watch_Term_Check`, and the transient TTLs. No scanner logic changes.

## The bug

Three stores, two permanent and one not:

| Store | Holds | Lifetime |
|---|---|---|
| `lwtv_debugger_status_{scope}` (option) | `name`, `count`, `last`, `summary` | **forever** |
| `lwtv_debug_baselines` (option) | per scope: `last_run`, `count` | **forever** |
| `lwtv_debug_{check}` (transient) | the findings themselves | **one week** |

The tab picker badges `$options[ option ]['count']` (`admin-menu/class-validation.php:277-281`). The tab body renders the transient. When the transient goes and the option stays, the badge advertises findings that no longer exist anywhere.

### Why only one tab shows it

`Report::items()` (`validator/class-report.php:82-97`) treats a missing transient as a cue to scan:

```php
if ( ( isset( $_POST['rerun'] ) && check_admin_referer( $nonce ) ) || false === $items ) {
    $items = self::scan( $scanner );
}
```

So for the nine `Report`-driven tabs, opening a tab with a cold cache silently rebuilds it and the numbers agree again. The divergence is real there too — it is just repaired before anyone sees it.

`Watch_Term_Check` cannot do that, deliberately: its scan makes one HTTP request per provider URL, and running it on a page load would hang the request for minutes. So it has its own tri-state renderer with a genuine "never run" branch (`validator/class-watch-term-check.php:97-107`), and it is the only tab where the mismatch is visible.

**That makes this a reporting bug, not a storage bug.** The count is not wrong — 47 really was the finding count when the check last ran. What is wrong is presenting it as current, and then having nothing to show.

### How it surfaced

A fresh production database copy. Options come across in a dump; transients are routinely excluded or already expired. So every check's badge arrived and no check's findings did — and the one tab that does not auto-scan said so.

The same thing happens without a DB copy: **the transient TTL is `WEEK_IN_SECONDS` and the cron period is also a week.** `wp lwtv generate debug sun` runs `find_bad_watch_urls()` on Sundays, and the transient it writes expires as the next run is due. One missed cron — a deploy, a slow night, a failed run — and the tab spends up to seven days claiming 47 findings it cannot show. There is no slack in that arrangement at all.

## Decision: the badge counts what the page will render

One rule, and it makes the contradiction structurally impossible rather than merely explained:

| Findings transient | Page | Badge |
|---|---|---|
| Has rows | the table, plus a **Recheck** button | the row count |
| Empty or missing | a **Run Scan** button | **no badge** |

The badge stops reading `lwtv_debugger_status_{scope}` and counts the transient the tab itself
renders. Same store, same number, no staleness marker, no three-way copy, nothing to keep in sync.

**This costs nothing.** `Status::all()` already does one `get_option()` per check
(`debugger/class-status.php:82-92`) and a transient *is* a non-autoloaded option, so reading eleven
transients is the same work as reading eleven status options. My earlier objection to this — "eleven
transient reads on every render" — was wrong; it was already paying that price for a number that
could lie.

**"N new" moves too.** Rows carry their own `status` stamp from the baseline diff, so the new-count
now comes from the same rows as the total instead of from `summary['new']`. Note this is a real
change of meaning: `summary['new']` counts *findings*, rows count *posts*. The old badge mixed the
two — "4 new / 41" had 4 findings against 41 rows — so this makes them comparable for the first
time.

**Status keeps its other jobs.** `last_run()` on each report, `current_status()` on the intro, and
the CLI all legitimately want history that outlives the findings. Only the badge changes source.

### Findings TTL — built

Not needed for correctness any more — a missing transient now simply means no badge and a Run Scan
button, which is honest. But `Scan::finish()` writes `WEEK_IN_SECONDS` and the cron rotation is
weekly, so the cache expires exactly as the next run comes due. One missed run and a report blanks
until the following week for no reason.

```php
private const FINDINGS_TTL = 10 * DAY_IN_SECONDS;

public static function store( string $transient, array $items ): array;
```

Ten days survives one missed weekly run and not much more — the expiry should still mean "nobody
has looked at this in a fortnight, rebuild it".

### One door, not four agreements

**A transient's expiry is a property of each write, not of the key.** `set_transient()` on an
existing key replaces whatever time was left rather than preserving it. So a report's lifetime
belongs to whichever code path touched it last — and four paths write findings, only one of which
is a scan:

| Writer | Fires when |
|---|---|
| `Scan::finish()` | a check runs — cron, CLI, or a Run Scan button |
| `Repair::prune()` | a per-finding Fix button is clicked |
| `Actors::flag_shadow_sync_failure()` | a shadow-taxonomy sync fails, via hook |
| `Watch_Hosts::scan_unregistered()` / `forget_unregistered()` | the Watch Providers worklist |

The last three read the payload, modify it, and write it back to the same key. While they disagreed
with `Scan`, **repairing a finding silently moved the report's expiry** — earlier if you fixed
something soon after a scan, later if you fixed it a week on. The expiry became a function of when
somebody last pressed a button, which nobody would find by reading one file.

Making all four use the same constant fixed the symptom by convention. `Scan::store()` fixes it
structurally: **`FINDINGS_TTL` is private**, so the expiry is not a number a caller passes but a
fact about findings, and the only way to write findings is to come through the one door. A fifth
writer cannot invent its own lifetime without deliberately reaching past the seam.

`store()` returns its rows, so a caller can `return Scan::store( … )`.

Not routed through it: the three per-user admin-notice transients (`Repair`, `Watch_Term_Check`,
`Watch_Providers`), which are five-minute one-shot messages rather than findings, and correctly own
their own short life.

### The intro page, redesigned

From the `Debug-Intro.zip` handoff. The old intro carried a bulleted list of links *and* a separate
"Current Status" list that named some of the same checks differently ("Queer Checker" vs "QIRL
Characters have Queer Actors"), so reading it meant matching the two up by eye. One row per check,
with its count in the row, removes that step.

| | |
|---|---|
| `Validation::tab_counts()` | new; one place that turns a tab's transient into `{count, new, cached}`. The picker badge and this table both read it, memoised. |
| `Validation::tab_introduction()` | rebuilt: intro prose, info callout, `Current Status` table of Checker / Issues Found / View report. |
| `Validation::current_status()` | **deleted.** The table supersedes it. |
| `lwtv-tools.css` | `.lwtv-tools-callout`, `.lwtv-tools-checkers*`, `.lwtv-tools-pill`; version 1.4.0. |

Three deviations from the handoff, each deliberate:

1. **`widefat striped`, not `WP_List_Table`.** The handoff suggests extending it "ideally". Every
   other table in this plugin is a hand-rolled `widefat`, and `WP_List_Table` earns its keep on
   sortable, paginated, bulk-actionable lists — this is twelve static rows. Following the local
   convention beats following the framework here.
2. **Four states in the Issues Found column, not two.** The handoff shows an em-dash for "no data
   or zero issues", and those are different things:

   | State | Shown |
   |---|---|
   | Findings, count > 0 | filled red pill |
   | Findings, count 0 | em-dash — ran, found nothing |
   | No findings, stored count > 0 | **outlined** pill + "as of 3 days ago" |
   | No findings, stored count 0, but a `last` timestamp | em-dash + "as of 3 days ago" |
   | No `last` at all | *Not run* |

   **`last` is the "has it run" signal, not the count.** A first version tested `stored > 0`, which
   reported any check that ran and found nothing as *Not run* — wrong, and alarming in the wrong
   direction. Shows missing IMDb is the case that caught it: it found zero, so `stored` was 0 against
   a perfectly good timestamp.

   The third state remains the fallback for when the findings really are gone: a fresh database copy
   brings options across but not transients, and the transient expires while the option does not.

   The pill is outlined rather than filled, so a recorded number reads as a record and not as a
   current alarm. The **tab picker still badges only the live count** — it has no room to date a
   number, so it shows nothing rather than something undated.
3. **The optional "hide checkers with no issues" filter is not built.** The handoff flags it as
   needing confirmation. Twelve rows fit on one screen; say the word if it earns its place.

Also fixed in passing: the old intro called `self::last_run( 'intro' )` bare, and `last_run()`
*returns* its markup rather than echoing it — so that line has been printing nothing at all. It is
now echoed, below the table.

**Checks with no tab of their own get a row anyway.** `watch_hosts` (contested hosts) is the case:
a real check with a real count, run by cron and `wp lwtv debug watchhosts`, whose findings render
inside the Watch Providers tab rather than on a page of their own — because a contested host and a
host with no term are the same editor's problem in the same sitting.

It lives in `TOOL_TABS` like everything else, with two new keys:

| Key | Meaning |
|---|---|
| `show_tab => false` | omitted from the tab picker — a dropdown option leading nowhere is worse than no option |
| `tab => 'watch_providers'` | where its row links, and where a hand-typed URL for it is sent |

One registry, not two. An earlier version of this had a separate `EXTRA_CHECKS` array and a
`checker_rows()` merge; a flag on the existing array is less machinery for the same result, and it
keeps the ordering problem solved by array position rather than by code.

`settings_page()` also resolves the `tab` pointer before dispatch, so a bookmarked
`tab=tab_watch_hosts` lands on Watch Providers instead of falling through to `Report::make()` with
no scanner and no copy.

The upshot: the overview is once again an exhaustive list of recorded checks, which is what
`current_status()` was for. Anything that records a count has somewhere to be seen, and a `show_tab`
flag is the one-line way to keep that true.

### `LWTV_DISABLE_TRANSIENTS`: findings are a store, not a cache

The flag made every check read as never run on a development machine. `Transients::get_transient()`
returns false unconditionally when it is set (`_components/class-transients.php:128-134`), which is
right for what the flag was written for — statistics, where a stale cached value getting in the way
is the problem and recomputing is always possible.

The debugger's findings are not that. `Watch_URLs` costs a hundred-odd HTTP requests, so a read
that always fails does not mean "recompute", it means the report is permanently empty and the tab
picker permanently unbadged.

**`Transients::get_stored()`** reads regardless of the flag. The distinction lives in the method
name rather than in an argument, so a caller has to decide which of the two it is holding. Seven
read sites moved across — `Report`, `Repair`, `Watch_Term_Check` (×2), `Watch_Providers`,
`Validation::tab_counts()`, `cli-debug` — and nothing else changed. The `this-year` trends read
stays on `get_transient()`, because that genuinely is a cache.

**A real bug fell out of this.** `Debugger\Actors::flag_shadow_sync_failure()` did a
read-modify-write on the findings transient. With the flag set the read returned false, so the
append **replaced the entire findings list with a single row** rather than adding to it — silent
data loss on every dev environment, every time a shadow-taxonomy sync failed. Now on `get_stored()`.

**The write asymmetry stays, documented.** `set_transient()` deliberately ignores the flag: a dev
database stays production-shaped so `wp transient get` shows what the site would serve, and turning
the flag off gives a warm cache rather than a cold one. The flag means "do not let a cached value
hide fresh data from me", not "do not keep records". That is now written down in the method, because
the next person to find the asymmetry will otherwise read it as a bug — as I did.

### Built already

- `Validation::TOOL_TABS` — `watch_providers` and `watch_term_check` now declare their `transient`; the ten Report tabs already did.
- `Validation::settings_page()` — badge counts the transient's rows, and its `new` from row status.
- `Watch_Hosts::scan_unregistered()` / `forget_unregistered()` — record a `watch_providers` status count, so the Watch Providers badge exists at all and falls when a term is created.

### The Run Scan button on Watch Term Check — queued, not inline

Rule 2 wants a Run Scan button when there is nothing cached. Nine tabs satisfy it without one,
because `Report::items()` scans on a cold cache and they never sit in a no-content state.
`Watch_Term_Check` cannot: ~116 HTTP requests will not fit in a page request.

**So the button queues the sweep and says so.** Two rejected alternatives, for the record:

- *A bounded inline scan.* Buildable — the machinery exists — but a 15-second budget probes about
  five URLs and returns ~111 rows marked `watch-url-deferred`. Roughly twenty-three button presses,
  and a first screen that is mostly noise.
- *A plain WP-Cron single event.* `find_bad_watch_urls()` writes its findings only at the very end,
  so a pass killed by `max_execution_time` banks nothing and the whole run is wasted. WP-Cron also
  fires on a page request, which is the wrong runner for minutes of work.

Action Scheduler is the runner the other batch tasks already use, and
`_Components\Scheduler::initialize_task_handlers()` already gates AS-dependent tasks behind
`function_exists( 'as_schedule_single_action' )`.

| | |
|---|---|
| `schedulers/class-watch-urls-task.php` | `Watch_URLs_Task` — `queue()`, `is_queued()`, `available()`, `run()` |
| `_components/class-scheduler.php` | registered alongside the other AS tasks |
| `Watch_Term_Check::ACTION_SCAN` + `handle_scan()` | queues it, reports whether this press did the queueing |
| `Watch_Term_Check::render_never_run()` | third state: **Scan running** when a pass is queued, with no button |

Three details that are load-bearing:

1. **Each pass is bounded at four minutes even though nothing is waiting.** Not for politeness —
   `find_bad_watch_urls()` only writes at the end, so an unbounded pass that hits a time limit
   stores nothing. Stopping ourselves first means every pass banks its work and re-queues for the
   rest.
2. **`queue()` is idempotent.** Two concurrent passes would fetch every URL twice and race to write
   the same transient.
3. **The button is absent, not disabled, when Action Scheduler is missing** — and `handle_scan()`
   checks again, because the form could have been rendered before AS went away.

**Not verified:** whether a pass reliably completes inside four minutes on production. ~116 URLs at
`Watch_Hosts::TIMEOUT` of 6s is 11 minutes worst case, so a slow night takes two or three passes.
That works, but if it turns out to be routine, the budget or the timeout wants revisiting rather
than the pass count.

## ⚠️ Verify first

1. ~~Do not make the badge read transients.~~ **Superseded** — that was the objection, and it was
   wrong on cost (see the decision above). Kept struck through so nobody re-derives it.
2. **Do not make findings permanent options.** Several hundred rows across eleven checks, and the
   expiry is deliberate — a check nobody has looked at for a fortnight should rebuild, not persist
   stale rows forever.
3. **`Status::record()` auto-registers the scope** in `lwtv_debugger_check_keys`
   (`debugger/class-status.php:128-152`), so a scope that appears once stays in `current_status()`
   until `forget()`. The badge no longer reads it, but the intro's Current Status list still does —
   so that list can still show a count whose findings have expired. Decide whether it should carry
   the same rule or is legitimately a history view.
4. **`Status::all()` derives `timestamp` as `max()` of every `last`** (`class-status.php:82-110`),
   so it reports the most recent run of *any* check. It is not a per-check freshness signal and must
   not be used as one.
5. **An empty array and a missing transient now render identically** — no badge, Run Scan button.
   That is the rule, but it does lose a distinction the tri-state renderer was built to keep: "ran
   and found nothing" deserves "Excellent!", not "Run Scan". Confirm the Report tabs still say
   Excellent on a genuinely clean check, because `Report` reaches `render_clean()` on `empty()`,
   which catches both.

## Testing checklist

- [ ] Delete `lwtv_debug_watch_urls_v2` by hand → Watch Term Check loses its badge entirely and offers Run Scan.
- [ ] Let a Report tab's transient expire → badge gone; open the tab → it auto-scans and the badge returns with a plain count.
- [ ] A genuinely clean check still renders "Excellent!" rather than looking like it never ran.
- [ ] A check with new findings shows `(N new / M)` where both numbers count rows.
- [ ] Watch Providers: create a term → badge and row count both drop by one, no Recheck needed.
- [ ] Watch Providers: cold transient → auto-scans on render, badge appears.
- [ ] `composer lint` clean.

## Out of scope

- Making `Watch_Term_Check` auto-scan. It is slow on purpose; that is the constraint, not the bug.
- Any scanner's logic, thresholds, or finding shapes.
- The `Repair` flow.
- Whether the weekly cron rotation is the right cadence — worth asking separately, since raising the TTL only buys slack for a schedule nobody has revisited.
