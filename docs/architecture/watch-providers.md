# Watch Providers

How a show's "Ways to Watch" links get their provider names, how hosts are matched to `lez_watch_urls` terms, and the admin tools that keep the two in step.

Code lives in [plugins/lwtv-plugin/php/cpts/shows/watching/](../../plugins/lwtv-plugin/php/cpts/shows/watching/), with rendering in `Theme\Ways_To_Watch`, admin tabs in `validator/`, and checks in `debugger/`.

## Data model

- A show lists its links in the ACF repeater `lezshows_waystowatch` (raw meta `lezshows_waystowatch_<N>_url`).
- A provider is a term in the `lez_watch_urls` taxonomy (`Theme\Ways_To_Watch::TAXONOMY`). The term holds the URLs that identify it in the ACF repeater `lezwatchurls_all` (raw meta `lezwatchurls_all_<N>_url`, field keys `Watch_Hosts::FIELD_REPEATER` / `FIELD_URL`), plus two term-wide ACF true/false settings:
  - `lezwatchurls_setting_hide_display`: do not render links to this provider.
  - `lezwatchurls_setting_confirmed_name` (`Watch_Hosts::META_CONFIRMED_NAME`): see [URL health](#url-health).
- **The term name is the display name**, used verbatim and never reformatted.
- Shows are not linked to provider terms by term relationship. The link is resolved at render time by matching hosts.

`Watch_Hosts` is the single source for "which hosts are in use" (`in_use()`, distinct published shows per host, most-used first) and "which term owns a host" (`term_for()`). The CLI and both admin tabs use it, so they cannot disagree about what is registered.

## Host matching

`Watch_Hosts::term_for( $host )` resolves a host to a term:

1. `Watch_Host_Map::build()` reduces every stored term URL (`Watch_Hosts::term_urls()`) to its normalised host, giving a `host => term_id` map.
2. `Watch_Host_Map::resolve()` tries each form from `Host_Name::host_candidates( $host )` in order and returns the first host in the map.

`host_candidates()` drops leading labels one at a time, stopping at the registrable domain so it never degrades to a bare public suffix like `co.uk`. Each form is offered with and without `www.`. So `gshow.globo.com` offers `globo.com` without anyone having listed `gshow.` anywhere, and a term on `abc.go.com` wins over one on `go.com` because the more specific form is tried first.

Matching on host rather than on an exact URL string means a term URL saved with a trailing slash, `http`, upper case or `www.` still matches.

### Contested hosts

When two terms have URLs that reduce to the same host, `Watch_Host_Map::build()` honours the **first** claim and records every claimant in `collisions`. `term_urls()` is ordered by term name, so the winner is whichever term sorts first by name: stable, but arbitrary. `Watch_Hosts::host_map()` logs each collision to the `shows` topic.

No code can decide which term is right, so there is no automatic fix. Resolve a collision by removing the URL from the wrong term, or with `wp lwtv waystowatch merge` when the two terms are the same service.

## Display names

`Theme\Ways_To_Watch::generate_links()` picks a label for each URL, best first:

1. **A `lez_watch_urls` term name.** Wins outright. Links on terms with Hide Display set are skipped.
2. **A name the host published about itself**, cached in `Watch_Host_Names` by [enrichment](#host-name-enrichment). Rendering only ever reads the cache, never makes a request.
3. **`Host_Name::guess()`**, derived from the hostname. Pure and unit-tested (`tests/unit/CPTs/HostNameTest.php`).

The fallback path is permanent. LWTV documents web series and each one lives on its own domain, so there will always be a long tail of hosts not worth a term.

### Limits of Host_Name

`Host_Name::guess()` gets from wrong to recognisable, not to right. Which label carries the brand is a semantic question no parser answers:

| Host | Where the brand is | Guess |
|---|---|---|
| `netflix.com` | the registrable label | "Netflix" |
| `abc.go.com` | the subdomain; `go.com` is Disney's registrable domain | "GO" |
| `gem.cbc.ca` | both; the product is "CBC Gem" | "CBC" |
| `onemorelesbian.com` | unsplittable without a dictionary | "Onemorelesbian" |

Labels of three characters or fewer are upper-cased (abc, cbs, hbo, ifc); longer ones get an initial capital. `GENERIC_SUBDOMAINS` (`www.`, `watch.`, `play.` ...) are dropped, and `COMPOUND_SUFFIXES` (`co.uk`, `com.au` ...) push the name one label further left.

When the guess is not good enough, give the host a term. That is what the taxonomy is for.

### Name decoding

WordPress stores term names entity-encoded: "U&Alibi" comes back as `U&amp;Alibi`. Every surface that renders a `lez_watch_urls` term name must pass it through `Theme\Ways_To_Watch::term_name()` before escaping, or `esc_html()` encodes the ampersand a second time and the reader sees `U&amp;Alibi`.

Decode on the way out rather than fixing the stored value: WordPress re-encodes on every term save, so a corrected name would not stay corrected. The helper is public and static on `Ways_To_Watch` because that class owns `TAXONOMY`, so every caller already imports it. Current callers are the front-end renderer, `Debugger\Watch_URLs`, `Debugger\Watch_Host_Collisions`, `Validator\Watch_Term_Check` and `Validator\Watch_Providers`.

## Host name enrichment

`Watch_Host_Names` ([class-watch-host-names.php](../../plugins/lwtv-plugin/php/cpts/shows/watching/class-watch-host-names.php)) caches names discovered from a host's own metadata, in the non-autoloaded option `lwtv_watch_host_names` (`host => { name, source, checked, attempts? }`). It is for hosts with no term. A term always wins.

`Watch_Hosts::discover_name()` fetches `https://<host>/` and reads `og:site_name`, then `application-name`, from the `<head>` (at most `MAX_BYTES`). `Watch_Host_Names::is_plausible_name()` rejects URLs, anything over `MAX_NAME_LENGTH` (40) characters or five words, and strings with no letter or digit.

Each lookup has one of three outcomes:

| Outcome | Recorded as | Asked again? |
|---|---|---|
| A usable name | `set( $host, $name, $source )` | No |
| Answered, nothing usable | `set( $host, '', SOURCE_NONE )` | No |
| Unreachable (transport error) | `fail( $host )`, `source` `error`, `attempts` +1 | Yes, until `MAX_ATTEMPTS` (3) |

`should_ask()` encodes this. A later successful answer replaces the record without `attempts`, so a future failure counts as the first strike, not the fourth. `--recheck` on the CLI asks everything again, and `wp lwtv waystowatch forget` clears the cache.

Enrichment runs from `wp lwtv waystowatch enrich` (weekly on cron, see [cron-schedule.md](../operations/cron-schedule.md#why-the-http-jobs-are-spread-out)) and from the bounded "Look up N names" button on the Watch Providers tab.

## Term URL audit

`Watch_Term_Url_Audit` inspects what is actually stored in term URL rows (`wp lwtv waystowatch termurls`). Each row gets flags:

| Kind | Flags | Meaning |
|---|---|---|
| Cosmetic | `trailing-slash`, `uppercase`, `http-scheme`, `no-scheme`, `www`, `port`, `duplicate` | Host matching absorbs these. The stored value is only untidy and safe to normalise. |
| Blocking | `path`, `query`, `fragment`, `credentials`, `unparseable` | A human must decide. A term registered for `youtube.com/c/something` means something narrower than `youtube.com`; host matching widens it to the whole host and would let one web series' term swallow every other YouTube URL on the site. |

Collisions are blocking for the same reason, from the other direction: host matching must pick a winner and nothing can pick one correctly.

`Watch_Term_Url_Audit::canonical_urls()` reduces a list to one bare `https://host` per distinct host, first occurrence winning. Unparseable values are dropped, since writing back a value that can never match would be worse than losing it. `Watch_Hosts::set_term_urls()` writes that list contiguously (row 0 to N−1, stale higher rows deleted, row-count meta updated) and resets the request memos. `merge`, `seturls` and the tab's assign action all go through it, so nobody has to hand-edit ACF repeater meta and get the row numbering wrong.

## Watch Providers tab

`Validator\Watch_Providers` ([class-watch-providers.php](../../plugins/lwtv-plugin/php/validator/class-watch-providers.php)) is the Data Validation tab for host-to-term problems. It shows two kinds, and they are read differently on purpose.

### Hosts with no term: a stored worklist

`Watch_Hosts::scan_unregistered()` stores its result under `Watch_Hosts::FINDINGS_UNREGISTERED` (`lwtv_watch_unregistered`) in `Findings_Store` and records the count under `Watch_Hosts::STATUS_KEY` for the tab badge. The tab uses the same Run Scan / Recheck form, nonce naming (`run_<tab>_clicked`), `rerun` / `recheck` field names and auto-scan on an empty store as `Validator\Report`, so it behaves like every other validator tab.

- **Run Scan** checks every host in use.
- **Recheck** re-tests only the listed hosts and drops those that now have a term, however they got one. It does not look for hosts that appeared since.

That makes the list a worklist: it shrinks as you work down it and does not grow under you. Consistency with the other tabs is the point. The saving is not, since host matching is two queries either way.

### Contested hosts: read live

Collisions come straight from `Watch_Hosts::host_collisions()`, a free byproduct of the host map the tab already builds. Caching them would only add staleness, and a contested host is urgent in a way a missing term is not. The tab never reads the `watchhosts` check's findings, so it cannot show a stale collision.

The `watchhosts` debugger check (`Debugger\Watch_Host_Collisions`, `wp lwtv debug watchhosts`) finds the same collisions for the weekly cron, the CLI and the badge, which need a stored number. In `Admin_Menu\Validation::TOOL_TABS` it has `show_tab => false` and `tab => 'watch_providers'`, so it has a status row but no page of its own. Its issue level, `watch_term`, is not one `Debugger\Repair` handles, so it has no repair button.

### Actions

| Action | Shape |
|---|---|
| Assign to an existing term / create a term | A local write, instant and safe in a request. One form per row posting to `admin-post.php` (`ACTION_ASSIGN`). The submit button's `do` value (`suggest`, `assign`, `create`) decides which it is, never which fields happen to be filled in. |
| Look up names | Fetches third-party hosts over HTTP, so it is hard-capped (`UI_BATCH`, `UI_TIMEOUT`, `UI_TIME_BUDGET`; see [scheduling.md](scheduling.md#admin-request-lookups)). The unbounded version is the CLI. |

Writing needs `manage_categories` (`CAP_MANAGE`). The admin-post handlers are hooked from code that runs on every admin request, not from `Admin_Menu\Validation::init()`, which fires on `admin_menu` and never runs for `admin-post.php`. `ACTION_CREATE` stays registered so that a form on a stale page still works.

**Suggestions.** `Watch_Term_Match::suggest()` offers a one-click "Assign to X" when an existing term looks like the host. `canonical()` lowercases, decodes entities (term names are stored encoded), maps `+` to `plus` and `&` to `and`, and strips everything but letters and digits. That is what lets "Paramount+" match `paramountplus.com` and "Seed&Spark" match `seedandspark.com`. Each host is tested in three forms: the proposed name, the registrable label (`watch.revry.tv` → `revry`) and the registrable domain without dots (`acorn.tv` → `acorntv`).

**Without JavaScript.** Term options are rendered once in a `<template>` and cloned into each row's select, instead of repeating every term in every row. The suggestion's term ID is in a server-rendered hidden field, so the one-click path works with no script. With no script, Create still works with the proposed name.

## URL health

`Debugger\Watch_URLs` (`wp lwtv debug watchurls`) probes every URL on every term and asks `Watch_Url_Health::classify()` whether it is still a working provider. It answers the other half of the Watch Providers tab: of the terms we have, do they still point anywhere useful?

A dead link is the easy case. The expensive case still returns HTTP 200, for example a shut-down service whose domain was resold. So after the status code, `classify()` checks three cheap signals:

| Signal | Status |
|---|---|
| 404 / 410, or no status code | `broken` |
| 401, 403, 407, 429, 451 | `blocked`: refused us specifically; usually bot-blocking, reported so a real problem is not hidden inside a 403 |
| Serves a parking or for-sale page (`PARKED_MARKERS`) | `broken` |
| Redirects to a different registrable domain | `review`: a rebrand to follow, or a domain lost |
| The site's published name does not resemble the term name | `review`, with reason `REASON_NAME_MISMATCH` |

`review` means "put a human in front of it", not proof. Names shorter than `MIN_NAME_KEY` (3) after normalising, like "GO", are treated as unjudgeable rather than flagged.

**Confirmed names.** When a name mismatch is a known, harmless drift (a provider now hosted on a platform that publishes its own name), an editor confirms the provider from the Watch Term Check tab. That sets `lezwatchurls_setting_confirmed_name` on the term, is term-wide, clears the name-mismatch review on every URL that term has, and suppresses it in future scans. `REASON_NAME_MISMATCH` is the only reason code, and it exists so this action can target exactly those rows.

**Rechecks.** A recheck re-probes only rows whose URL is still stored on the term, compared exactly (trimmed) rather than by host. A row often exists because it told an editor to remove that URL. Re-probing a URL nobody stores would fail forever and could never be cleared from the UI, so skipping it is what clears it. An edited URL is a different fact about the term and is picked up by the next full sweep. `recheck_one()` reports `stale` separately from "it passes now", so the notice does not claim a deleted URL works.

The full sweep runs on Sundays from cron, or on demand through `Schedulers\Watch_URLs_Task` (see [scheduling.md](scheduling.md#watch-url-sweep)).

## Watch Term Check tab

`Validator\Watch_Term_Check` renders the `watchurls` findings. They are keyed to terms, not posts, so the generic `Validation::table_content()` (which calls `get_the_title()` on `id`) cannot render them. Findings built with `Findings::make_for_term()` carry `object_kind => 'term'`, and `table_content()` skips any row that is not a post rather than rendering a plausible wrong one.

`Debugger\Format\Triage::by_impact()` splits the rows:

- **Affecting:** a published show reaches the term. This is the worklist.
- **Unused:** no published show reaches the term. Re-checking the URL cannot help, because nothing points at it either way. The fix is deciding whether the term should still exist. These rows sit in a closed `<details>`: real information ("this service is gone"), but not a worklist.

Run Scan on this tab queues `Watch_URLs_Task` rather than scanning inline, since a hundred-odd HTTP requests will not fit in a page request. The button is absent when Action Scheduler is not available.

### Term retirement guards

The Retire action (`ACTION_RETIRE`) is offered only on unused rows and **deletes the term**, so its guards matter more than the action:

1. The show count is re-derived live (`Watch_Hosts::shows_per_term()`), never read from the stored row. Findings are as of the last sweep, and a term that has gained shows since must not be deleted because of a stale zero.
2. A term that still holds object relationships is refused. `lez_watch_urls` is registered against shows (`CPTs\Shows::ALL_TAXONOMIES`) even though the front end resolves providers by host, so a term can be assigned to posts, and deleting it would silently drop those relationships. The check uses `get_objects_in_term()`, not `$term->count`: `_update_post_term_count()` counts published posts only, so a term assigned to a draft would read as zero on both counts.

Both refusals say what they found, because a disagreement between the report and the live count is the interesting part.

`wp lwtv waystowatch merge` (`Watch_Hosts::merge_terms()`) also deletes a term, but it moves the dropped term's relationships to the kept term first (`get_objects_in_term()` then `wp_set_object_terms( …, true )`), so no post loses its provider. It reads the relationships before writing anything and stops before the delete if any reassignment fails. `--dry-run` reports how many posts would move. Use it only for genuine duplicates.

## WP-CLI: wp lwtv waystowatch

Options and examples are in `wp help lwtv waystowatch`. The actions:

| Action | Writes? | Purpose |
|---|---|---|
| `hosts` | no | Hosts in use, show counts, term status and the name rendered today |
| `termurls` | no | The [term URL audit](#term-url-audit); ends with a verdict on whether host matching changes any meaning |
| `enrich` | option | [Host name enrichment](#host-name-enrichment); run weekly by cron |
| `forget` | option | Clear the enrichment cache |
| `merge` | terms | Fold one provider term (URLs and assigned posts) into another and delete it, for genuine duplicates ("Lesflicks" and "LezFlicks") |
| `seturls` | terms | Rewrite one term's URL rows, to repair a typo'd or dead host |

## Decisions

- **Match on host, not URL string.** Exact-string matching made every `www.` and trailing-slash variant a separate fact. The audit's blocking flags exist to catch the few rows where host matching would change meaning.
- **No parser for brand names.** A term is the fix for a bad guess. See [Limits of Host_Name](#limits-of-host_name).
- **Worklist stored, collisions live.** See [Watch Providers tab](#watch-providers-tab).
- **No HTTP at render time.** Discovered names are read from an option. Fetching happens only in the CLI, on cron, or behind a bounded admin button.
