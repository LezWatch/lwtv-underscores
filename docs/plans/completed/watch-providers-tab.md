# Plan: Watch Providers validation tab — assign, resolve, cache

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`).
**Page:** `admin.php?page=lwtv_data_check&tab=tab_watch_providers`
**Scope:** the `lez_watch_urls` ↔ Ways-to-Watch host resolution path, admin *and* front end. No change to show scoring, statistics, or the CPT relationships.

## Goal

Four asks, one root cause between them:

1. List **every host that still has a problem** — the table's contents should be the complete
   problem set, not a subset of it.
2. A check / re-check button, tri-state like the rest of the validator tabs.
3. The action column should offer **an existing term** as well as "create new".
4. A host that gets a term should leave the list, no matter who put it there.

(3) is missing functionality. (2) is cheap once the N+1 query is gone. (1) and (4) are the same
bug wearing two hats, and it is not a caching bug — see Diagnosis.

A host that resolves to a term is not a problem and does not belong in the table. The reason the
current "130 of 154" line reads as though rows are missing is that **the set of 130 is wrong**:
exact-URL matching means hosts that genuinely do have a term are listed as though they don't.
Pressing CREATE TERM on one of those either quietly heals it (same name → `term_exists` → the
bare URL gets appended to the right term) or **creates a duplicate provider term** (any other
name). Fix the matcher and the problem list becomes truthful, which is the actual ask.

---

## Diagnosis

### The real bug: term matching is an exact string comparison

`Theme\Ways_To_Watch::get_term_by_url()` (`php/theme/class-ways-to-watch.php:149`) matches
`lezwatchurls_all_N_url` with `meta_value IN (...)`. The candidates it builds are always bare
`scheme://host`:

```php
$candidates[] = $one_scheme . '://' . $one_host;   // line 127
```

So a term URL only ever matches if it is stored as *exactly* `https://hulu.com` or
`http://hulu.com` (± `www.`). Anything a human would actually type into the ACF term field
fails:

| stored by hand | resolves? |
|---|---|
| `https://hulu.com` | yes |
| `https://hulu.com/` | **no** — trailing slash |
| `https://hulu.com/welcome` | **no** — path |
| `https://Hulu.com` | **no** — case |
| `https://hulu.com:443` | **no** — port |

The page is not stale. `Watch_Hosts` holds no persistent cache at all — `$in_use`, `$terms` and
`$term_urls` are request-level memos (`class-watch-hosts.php:80-98`). The match genuinely
fails, on the front end too: that host keeps guessing its name from the hostname forever, and
the tab keeps listing it. **Ask (4) is this bug, not a cache-invalidation problem.**

Only `Watch_Hosts::create_term()` writes the bare form (`class-watch-hosts.php:483`), which is
why the CREATE TERM button appears to work and hand-editing does not.

### The missing feature: no way to attach a host to an existing term

`create_term()` refuses outright when the host already resolves:

```php
if ( self::term_for( $host ) ) {
    return new \WP_Error( 'lwtv_host_registered', ... );   // line 459
}
```

and the only writer, `add_url_to_term()`, is `private` (line 498). The one path to an existing
term is accidental: `wp_insert_term()` collides on name, and the `term_exists` error data is
reused (lines 471-481). So you can join a host to an existing term *only* by retyping that
term's name exactly. There is no way to say "netflix.co.uk is the Netflix term".

### The performance shape

`unregistered()` calls `term_for()` per host, and each of those is one `get_term_by_url()`
query — **~154 queries every time the tab renders**, which is why the tab carries no count
badge (`admin-menu/class-validation.php:190-196`, comment on line 193). The front end pays the
same per watch link.

`term_urls()` already fetches every term URL in **one** query (`class-watch-hosts.php:214`).
Resolving from that instead of querying per host fixes the bug, kills the N+1, and makes
the problem list correct. That single change carries asks 1, 2 and 4.

### Latent bug worth fixing while we are in here

`add_url_to_term()` derives the next repeater index from ACF's row-count meta:

```php
$existing = (int) get_term_meta( $term_id, self::META_REPEATER, true );
$index    = max( 0, $existing );
```

`term_urls()` deliberately reads the subfield rows instead, *"so a deleted row can't leave a
phantom URL behind"* (line 210). The two disagree after a human deletes a row in ACF: the count
says 3, rows 0 and 2 exist, and the next write lands on index 3 — or worse, ACF renumbers and
the count now points at an occupied slot. Derive the index from the actual `_N_url` rows.

---

## Decisions taken

| Question | Decision |
|---|---|
| Matching | Normalised **host** matching, front end and admin, **after** a Phase 0 audit of what is actually stored. |
| Recheck button | **Built as Pattern A.** Stored worklist, tri-state Run Scan / Recheck, same nonce and field names as `Report`. Recheck re-tests only the listed hosts. |
| Term dropdown | One `<select>` per row, options cloned from a single shared `<template>`. Inline script, matching the tab picker on the same screen, rather than a new enqueued asset. |
| Table contents | Problems only. A host with a term is not a problem and is not rendered. No "show resolved" view. |
| Scan shape | **Split.** Hosts needing a term are a stored worklist behind the button. Collisions are live — free from the map the scan already builds. |
| Stored scheme | `https://` only, one row per host, written by `Watch_Hosts::set_term_urls()`. The matcher is scheme-blind after Phase 1. |
| URL-less terms | Legitimate (prep for a coming network). `Watch_URLs::terms_without_urls()` gets deleted. |
| `$min_shows` | Stays at 1. No threshold, no `?min=` arg — a floor would make the problem set silently incomplete. |

Every question this plan opened is answered. Nothing below is waiting on a decision.

**One reversal, and then a reversal of the reversal.** Phase 1 removed the per-host query, so I
dropped the cache and the button as unjustified. That was wrong, for a reason performance analysis
could not see: the button's value is a worklist that **holds still** while an editor works down it,
and consistency with ten other tabs that behave exactly this way. A live list recomputes under you
and cannot shrink as you fix things. Rebuilt as Pattern A.

The perf argument against caching was correct and irrelevant. Kept here because the cache's
docblock now has to say *why* it exists, and "it is faster" would be a lie.

---

## Phase 0 — Audit before touching the matcher (blocking)

**Status: built, awaiting a run against real data.**

| | |
|---|---|
| `cpts/shows/class-watch-term-url-audit.php` | the pure inspector — flags, blocking/cosmetic split, collision detection |
| `wp-cli/cli-waystowatch.php` | `termurls` action; two queries, everything after is pure |
| `tests/unit/CPTs/WatchTermUrlAuditTest.php` | 24 cases |

```bash
vendor/bin/phpunit --filter WatchTermUrlAudit
wp lwtv waystowatch termurls --all
wp lwtv waystowatch termurls --blocking --all    # just the rows needing a decision
```

### Result — run 2026-08-27, DB current to ~2026-07

```
116 URL rows across 80 terms, reducing to 110 distinct hosts.
  trailing-slash  17   cosmetic
  www             12   cosmetic
  duplicate        5   cosmetic
  BLOCKING         0
  collisions       1   lesflicks.com -> Lesflicks (#28134) vs LezFlicks (#28671)
```

**Zero blocking rows. The path fear was unfounded** — not one term URL carries a path, query,
fragment or credentials. The YouTube worry in particular: term #28128 stores only
`https://youtube.com` and `https://youtu.be`, both bare. **Phase 1's host matching is safe to
ship**, with the collision fixed as data first.

**The collision is a duplicate term, not a semantics problem.** #28134 "Lesflicks" holds
`lesflicksvod.vhx.tv` + `www.lesflicks.com/`; #28671 "LezFlicks" holds `lesflicksvod.com` +
`lesflicks.com`. Same service, two spellings — the real company is *Lesflicks*. All four hosts
are at **0 shows**, so merging is zero-risk. This does not argue for the path-wins variant; the
gate's concern was path semantics, and there are none.

Worth noting what host matching would do if left alone: `build_host_map()` breaks ties on
`t.name ASC`, and `"Lesflicks" < "LezFlicks"` (`s` < `z`), so the correctly-spelled term happens
to win. Correct by luck, which is precisely why it is flagged rather than tolerated.

### What the bug is actually costing, measured

A term URL fails today only when *every* row for that host is non-bare. `Host_Name::host_candidates()`
never emits a `www.` variant (it drops leading labels down to the registrable floor — a 2-label
host yields exactly itself), and `normalise()` strips `www.` from the *show's* host, so a stored
`www.` row can never match. Terms carrying both forms are the `duplicate` rows — those resolve
today via their bare row, and become redundant after Phase 1.

**14 hosts across 51 show-links are rendering a guessed name over a perfectly good term:**

| host | shows | renders as | should be |
|---|---|---|---|
| `paramountplus.com` | 16 | Paramountplus | **Paramount+** |
| `acorn.tv` | 11 | Acorn | **AcornTV** |
| `iq.com` | 6 | IQ | **iQIYI** |
| `ondisneyplus.disney.com` | 5 | Disney | **Disney+** |
| `freeform.com` | 2 | Freeform | Freeform |
| `watch.revry.tv` | 2 | Revry | Revry |
| `tf1.fr` | 2 | TF1 | TFI+ *(term name is itself a typo — see below)* |
| `hallmarkmoviesandmysteries.com` | 1 | Hallmarkmoviesandmysteries | **Hallmark Channel** |
| `sundancetv.com` | 1 | Sundancetv | **Sundance** |
| `pbskids.org` | 1 | Pbskids | **PBS** |
| `premium.atresplayer.com` | 1 | Atresplayer | ATRESPlayer |
| `vix.com` | 1 | VIX | ViX |
| `showtime.com` | 1 | Showtime | Showtime |
| `freeform.go.com` | 1 | Freeform | Freeform |

Phase 1 fixes all fourteen. The unresolved count drops by 14 — modest against ~130, but these are
the biggest providers on the site, so the visible quality win is disproportionate.

### Separate bugs the audit exposed (not blockers, not this plan's scope)

Filed here because the data is in front of us; each is independent of Phases 1-5.

1. **Term names are stored entity-encoded and the theme does not decode them.** #28702 is literally
   `Seed&amp;Spark` and #28705 is `U&amp;Alibi` in the database. `Theme\Ways_To_Watch::build_link()`
   runs `esc_html( $term->name )` with no decode, so the front end renders "Seed&amp;amp;Spark".
   `Debugger\Watch_URLs::term_text()` already exists to solve exactly this and documents why —
   the theme needs the same treatment. **Verify on a live show page.**
2. **#28673 is named "Roster Teeth"** — should be *Rooster Teeth* (its URL is `roosterteeth.com`).
   The term name is the display name, used verbatim, on 4 shows.
3. **#28201 is named "TFI+"** — should be *TF1+*. Letter I for digit 1. 2 shows.
4. **#28663 "paus" stores `https://watch.paus`** — not a valid host; almost certainly
   `watch.paus.tv`. Parses, so the audit passed it as cosmetic-free, but it can never match
   anything. 0 shows.
5. **#28137 "GlobalTV" stores `https://www.globaltv.co.ca/`** — `.co.ca` looks like a typo. 0 shows.
6. **#28668 "FX" (`fxnetwork.com`, 0 shows) vs #28689 "FX Networks" (`fxnetworks.com`, 2 shows)** —
   the same duplicate-term problem as Lesflicks, missed by collision detection only because the
   hosts differ by one letter. Merge candidate.

### Fixes built alongside the audit (2026-08-27)

Not part of Phases 1-5, but they had to land before Phase 1 and the repeater writer is Phase 4's
foundation anyway.

| | |
|---|---|
| `Watch_Term_Url_Audit::canonical_urls()` | pure; a URL list → one bare `https://host` per distinct host. 6 new tests. |
| `Watch_Hosts::term_url_rows()` | reads the real `_N_url` subfield rows, walking past gaps rather than stopping at the first one. |
| `Watch_Hosts::set_term_urls()` | **rewrites the repeater contiguously.** Replaces the append-at-the-count approach — this is the latent-bug fix from the Diagnosis, done in the right place. |
| `Watch_Hosts::attach_host()` | Phase 4a's model method, needed here too. Refuses a host already resolving to a *different* term, naming it. |
| `Watch_Hosts::merge_terms()` | fold one term into another and delete it. Canonicalises before deleting, so a failure leaves both intact. |
| `Theme\Ways_To_Watch::term_name()` | `html_entity_decode` on the way out, fixing the `U&amp;Alibi` double-encode. Twin of `Debugger\Watch_URLs::term_name()`. |
| `wp lwtv waystowatch merge` / `seturls` | both dry-run capable; `merge` also asks `WP_CLI::confirm()` before deleting. |

`create_term()` and `attach_host()` now both route through `set_term_urls()`, so
`add_url_to_term()` is gone and there is exactly one writer of that repeater.

**Why decode rather than correct the stored names:** WordPress re-encodes on every term save, so a
hand-corrected `Seed&Spark` would not stay corrected. The typos (`Roster Teeth`, `TFI+`) are
different — those are genuinely wrong text and get fixed in the database.

### The normalise/cleanup pass

Now scoped by the result: 17 trailing slashes, 12 `www.` prefixes, 5 fully-redundant duplicate
rows, 0 `http://`. **All cosmetic, and all made harmless by Phase 1** — so this is optional
tidying, not a prerequisite. Worth doing after Phase 1 to shrink 116 rows toward ~95 and stop the
`duplicate` rows implying that both forms are still needed. Not blocking anything.


The reasoning that made this blocking, kept for the record: host matching is looser than
exact-URL matching, so a term that had deliberately registered a *path*
(`youtube.com/c/somechannel` was the plausible one — LWTV documents web series and several live on
YouTube channels) would have started swallowing every URL on that host. **The audit found none.**

New read-only CLI subcommand, `wp lwtv waystowatch termurls`, in
`php/wp-cli/cli-waystowatch.php` alongside `hosts` / `enrich` / `forget`. Walks
`Watch_Hosts::term_urls()` and reports per row:

- `term_id`, `name`, `url`, parsed `host`
- flags: `path` (anything past `/`), `query`, `fragment`, `port`, `trailing-slash`, `uppercase`, `no-scheme`, `unparseable`
- **`collision`** — two or more distinct `term_id`s whose URLs normalise to the same host

Supports `--format=table|csv|json` and `--flagged` to show only rows needing attention.

**Gate — passed.** 0 blocking rows; the single collision was a duplicate term with 0 shows on
every host, which is a data fix rather than a reason for the "path wins" variant. Phase 1b is not
needed.

**Collision resolved 2026-08-27:** `wp lwtv waystowatch merge 28134 28671` folded "LezFlicks" into
"Lesflicks" — 4 rows in, 3 canonical out, `www.lesflicks.com/` and `lesflicks.com` collapsing to
one. Phase 1 is unblocked.

Companion cleanup, dry-run by default, in the existing migrate command family:
`wp lwtv migrate watchtermurls-normalise [--dry-run] [--live]`. Rewrites cosmetically-dirty
rows to bare `https://host`. **Leaves path-bearing rows alone** for a human. This is
belt-and-braces — Phase 1 makes them match anyway — but it keeps the stored data honest and
keeps `Debugger\Watch_URLs` probing something sensible.

---

## Phase 1 — Host-based resolution, one query

**Status: built 2026-08-27, untested against the running site.**

| | |
|---|---|
| `cpts/shows/class-watch-host-map.php` | **new, pure.** `build()` → `{map, collisions}`; `resolve()` walks `host_candidates()` against the map. |
| `tests/unit/CPTs/WatchHostMapTest.php` | 20 cases, including the real failing rows from the audit |
| `Watch_Hosts::host_map()` / `host_collisions()` | memoised, logs collisions once per request |
| `Watch_Hosts::term_for()` | rewritten to resolve from the map. Same signature, same memo. |
| `Theme\Ways_To_Watch::generate_links()` | calls `Watch_Hosts::term_for()` |
| deleted | `Ways_To_Watch::get_term_by_url()` and `url_candidates()` (~3.2kB) |

**Dependency direction reversed from what this plan originally said.** The plan put a
`get_term_for_host()` on the theme class and had `Watch_Hosts` keep delegating to it. That would
have made the two classes mutually dependent, since `Watch_Hosts` owns the map. Instead the theme
calls `Watch_Hosts::term_for()` — which already existed with the right signature and memo — so the
theme depends on the CPT layer and nothing depends back. One matcher, one direction, one owner.



### 1a. A pure map builder (test-first)

Per the build → format → templates discipline in `CLAUDE.md`, the decision logic goes in a pure
function with a unit test, and the query stays at the edge.

```php
// class-watch-hosts.php — pure. Input is term_urls() rows, output is host => term_id.
public static function build_host_map( array $term_urls ): array
```

Rules, in order:

1. `wp_parse_url()` each row; skip anything with no host.
2. `Host_Name::normalise()` the host (lowercases, strips a leading `www.`, trims dots).
3. First writer wins per host. `term_urls()` already orders `t.name ASC, tm.meta_key ASC`, so
   the winner is deterministic and stable.
4. On a collision (a second, *different* `term_id` for a host already mapped) record it in a
   returned `collisions` bucket rather than silently dropping it.

Return shape: `array{ map: array<string,int>, collisions: array<string, array<int>> }`.

Tests in `tests/unit/CPTs/WatchHostsMapTest.php` — mirroring `HostNameTest.php`, no WP
bootstrap. Cover: bare, trailing slash, path, uppercase, `www.`, port, subdomain, two hosts on
one term, two terms on one host, junk.

### 1b. The WP-facing accessor

```php
// class-watch-hosts.php — memoised build_host_map( term_urls() )
public static function host_map(): array          // host => term_id
public static function host_collisions(): array   // host => array<term_id>
```

Collisions are **not** swallowed: `host_collisions()` feeds problem class 2 in Phase 3, and
`lwtv_plugin()->debug_log( 'shows', ... )` records them once per request for the front-end path
where there is no table to render them into. Phase 0 should have emptied this set; both exist so
a later collision is visible rather than silent.

```php
// theme/class-ways-to-watch.php — new, replaces get_term_by_url()
public static function get_term_for_host( string $host ): ?\WP_Term
```

Walks `Host_Name::host_candidates( $host )` — already ordered most-specific-first, so
`abc.go.com` still beats `go.com` (`class-host-name.php:283`) — and returns the first hit in the
map.

`get_term_by_url()` has exactly two callers:

- `Theme\Ways_To_Watch::generate_links()` — line 74
- `Watch_Hosts::term_for()` — line 194

Rewrite both to `get_term_for_host()` and delete `get_term_by_url()` and the now-unused
`url_candidates()`. Update the stale doc comments that name it:
`debugger/class-watch-urls.php:191`, `cpts/shows/class-ways-to-watch.php:66,125`.

**Keep intact:** `lezwatchurls_setting_hide_display` (`theme/class-ways-to-watch.php:85`), the
term name being used verbatim (line 90), and the guess fallback for hosts with no term.

### 1c. Consequences to check

- A show page with 4 watch links: 4 queries → 1.
- The providers tab: ~154 queries → 1.
- `Watch_Hosts::shows_per_term()` (line 263) becomes cheap; its doc comment saying otherwise
  needs updating, and `Debugger\Watch_URLs` can stop storing the totals alongside findings if
  we want (optional, not required).

---

## Phases 2-4 — built 2026-08-27

Phase 1 changed what Phase 2 was for. The button is not a performance feature; it is what makes
the list a worklist.

### What got dropped, and why

| Planned | Outcome |
|---|---|
| Transient snapshot of `in_use()` | **Built**, as a worklist of *unresolved hosts* rather than a cache of `in_use()`. Not for speed — host matching made this two queries either way — but so the list holds still while it is worked down. |
| Tri-state `Run Scan` / `Recheck` button | **Built**, matching `Report` exactly: `run_watch_providers_clicked`, `rerun` / `recheck`, auto-scan on a cold cache. Recheck re-tests only stored hosts and drops any that now have a term, however they got one. |
| Prune-the-worklist on assign | **Built.** `Watch_Hosts::forget_unregistered()` drops the one row after a create or assign, so the row you just fixed goes away without a Recheck. Prunes one entry rather than dropping the transient, per `Repair::prune()`. |
| Collisions through `Scan::finish()` *for display* | **Split.** The tab renders them live from `host_collisions()`, a free byproduct of the map it already builds — so contested hosts are current even when the worklist is a week old. The check still exists for cron/CLI/badge, which need a stored number. |
| `'option' => ''` (no badge) | **Reversed.** The badge now counts *contested hosts*, not the ~130. A badge that is nearly always 0 and spikes on something real is worth reading; one permanently at 130 is not. |

So the tab has two freshness models on purpose: a worklist you refresh deliberately, and a
collision list that is always right.

### What shipped

| | |
|---|---|
| `debugger/class-watch-host-collisions.php` | the `watchhosts` check. One finding per (term, host) pair, `identity` = host, always a full scan. |
| `Issue_Registry['watch-host-collision']` | level `watch_term`, so `Repair` refuses it — correct, nothing can auto-fix a collision. |
| `Issue_Registry['watch-term-no-urls']` | retired with a note; kept declared so week-old cached rows still render a message. |
| `Watch_URLs::terms_without_urls()` | **deleted**, with its call site. It advised deleting legitimate placeholder terms. |
| `cli-debug.php` / `cli-generate.php` | `wp lwtv debug watchhosts`, and Wednesday's cron slot beside the other plain-SQL checks. |
| `Watch_Providers::render_summary()` | accounts for every host in use, so the table can be problems-only. |
| `Watch_Providers::render_collisions()` | live, marks which term wins, links both edit screens, offers no fix. |
| `Watch_Providers::render_term_options()` | one `<template>` of ~80 options cloned into every select; inline script, `<noscript>` fallback. |
| `ACTION_ASSIGN` + `handle_assign()` | `term_id` 0 means create. Shares `create_and_notify()` with the old handler. |
| `handle_create()` | kept registered — a page loaded before this shipped still posts the old form. |

## Suggesting an existing term (added 2026-08-27)

The tab offered "create a term" for every unregistered host, and the easy mistake was creating one
that already existed under a slightly different spelling. That is not hypothetical — it is how the
live data grew **"Lesflicks" beside "LezFlicks"** and **"FX" beside "FX Networks"**, both of which
this work had to clean up by hand.

So the row now looks before it offers to create.

| | |
|---|---|
| `cpts/shows/class-watch-term-match.php` | pure; `canonical()`, `candidates()`, `suggest()` |
| `tests/unit/CPTs/WatchTermMatchTest.php` | 21 cases, half of them things that must *not* match |
| `Watch_Providers::render_row()` | primary button becomes **Assign to “X”** when a term matches |
| handler | third intent, `do=suggest` |

### The matching rule

Exact equality after canonicalisation — lowercase, `+`→`plus`, `&`→`and`, then strip everything
non-alphanumeric. Three candidates per host, best evidence first:

1. the proposed name (often the site's own `og:site_name`),
2. the registrable label — `hbomax.com` → `hbomax`, and it survives generic subdomains so
   `watch.revry.tv` → `revry`,
3. the registrable domain with dots removed — `acorn.tv` → `acorntv`, which the label alone
   (`acorn`) would miss.

**The `+` rule is the one that earns its keep.** Seven providers in the data spell themselves with a
trailing plus — Paramount+, Disney+, BET+, MGM+, Apple TV+, M6+, SVTV+ — and every one owns a domain
that writes it out. Without that substitution the most valuable match never fires.

**Deliberately not fuzzy.** No edit distance, no prefix matching, no similarity threshold. A wrong
suggestion pre-selects a dropdown and points a host at the wrong provider, so a false positive costs
more than a miss. `tubitv.com` therefore does *not* suggest the existing "Tubi", and
`fxnetwork.com` does not suggest "FX Networks" — both are probably right, and neither is provable by
a rule you can hold in your head. Tests assert both non-matches so nobody "improves" this into a
guess later.

### The interaction

- Matched row: primary button reads **Assign to “HBO Max”**, and the Renders-as cell says *Looks
  like the existing term “HBO Max”.* One click does the right thing.
- The term arrives in a **server-rendered hidden field**, not from the select, so the one-click path
  works with no JavaScript — the select is empty until the script fills it.
- Refusing the suggestion stays one click away: the folded panel always carries its own **Create**
  button beside the rename field, because on a suggested row the primary is no longer Create.
- `attach_host()` still validates the ID against the taxonomy. A POSTed term ID is a POSTed term ID
  however it arrived.

## Extracting `term_name()` (2026-08-27)

WordPress stores term names entity-encoded, so `U&Alibi` comes back as `U&amp;Alibi`. Every surface
that renders one has to decode before escaping, or `esc_html()` encodes the ampersand a second time
and the reader sees the entity. That was a live front-end bug earlier in this work.

By the end there were **seven places** doing it: three private `term_name()` twins (theme,
`Watch_URLs`, `Watch_Host_Collisions`) and four inline `html_entity_decode()` calls in
`Watch_Providers`. Each surface that forgot shipped the bug, which is a poor thing to leave to
memory.

Now one: **`Theme\Ways_To_Watch::term_name()`**, public and static. It lives there because that
class owns `const TAXONOMY`, so all six other call sites already imported it — the extraction added
no imports and no files.

`Watch_Term_Match::canonical()` still calls `html_entity_decode()` itself, and that is deliberate:
it is a step inside a *comparison*, not a rendering decision, and the class is pure — it runs in the
unit suite with no WordPress bootstrap, so it cannot reach into a theme class. There is a comment
saying so, because it otherwise looks like the one that got missed.

## Phase 5 — Name lookup: unreachable hosts stop nagging

Reported from use: after `wp lwtv waystowatch enrich --all`, the tab still said *"2 still to
check"* — and it said so forever.

```
Asking 2 host(s)...
0 named, 0 published nothing usable, 2 unreachable (will retry next run).
```

**Failures were deliberately not recorded**, so a blip could retry. That was the right instinct with
a consequence nobody had followed through: a host that is *permanently* gone fails every run,
never gets recorded, and therefore stays on the "still to check" list for good — re-fetched by every
cron run and every button press, while the copy promised one more attempt would clear it.

The fix keeps the blip-retry and ends the nagging by counting instead of flagging:

| | |
|---|---|
| `Watch_Host_Names::MAX_ATTEMPTS` | 3. Weekly rotation, so ~3 weeks of genuine unavailability before we stop asking. |
| `Watch_Host_Names::fail()` | records `source: error` and increments `attempts`. |
| `Watch_Host_Names::should_ask()` | the question callers actually have. False once we have a name, once the host answered with nothing usable, or after MAX_ATTEMPTS failures. |
| `set()` | writes no `attempts`, so a host that comes back does not carry old strikes into its next failure. |

`should_ask()` replaced `is_checked()` at all three call sites — the admin batch, its pending count,
and the CLI. `is_checked()` remains as a distinct question ("has this ever been looked at") with no
callers, which is fine; it is the honest name for what it does.

Escape hatches already existed and are now the documented way back:
`wp lwtv waystowatch enrich --recheck` ignores the cap, `forget` clears it.

Two smaller things fixed alongside:

- **Provenance has three states, not two.** "Guessed" alone does not say whether asking the site
  would help. A host that has stopped answering now reads *guessed — host does not answer*.
- **Backticks in admin copy became `<code>`.** Four strings across two classes, with the command
  passed as a `%s` argument so the translatable string stays free of markup. Both notice renderers
  moved from `esc_html()` to `wp_kses_post()`, which is safe because the messages are ours — no user
  input reaches them.

## Phase 5 (original) — Name lookup, unchanged

`render_lookup_form()` / `handle_lookup()` stay as they are: bounded HTTP, `UI_BATCH` +
`UI_TIME_BUDGET`, separate button. Only change: read the pending count from the snapshot rather
than recomputing `unregistered()`.

---

## ⚠️ Verify first

1. **Run Phase 0 on the live database before writing Phase 1.** If a term legitimately owns a
   path, or two terms collide on a host, this plan's matching decision is wrong and needs the
   path-wins variant.
2. **`Debugger\Watch_URLs` probes term URLs directly** (`class-watch-urls.php:133,209`).
   Normalising stored URLs changes what gets fetched — confirm `find_bad_watch_urls()` still
   probes something meaningful, and that the "term with no URLs" check still fires.
3. **The ACF repeater shape is unverified in production.** `DEBUGGER-REVIEW.md:50` flags the
   create-term button as untested, and lines 937-940 name the failure mode: wrong meta shape
   means the term looks fine in the list and matches nothing. After the first `attach_host()`,
   open that term in the ACF term editor and confirm the URL rows appear and re-save cleanly.
4. **`Watch_Providers::make()` is called statically** from `Validation`'s switch
   (`class-validation.php:189-226`). Anything needing instance state will fatal;
   `Watch_Term_Check` works around it at line 92. Do not add a constructor dependency.
5. **Front-end regression:** pick three shows whose watch links currently render from a term and
   three that render from a guess, and confirm all six are unchanged after Phase 1.
6. Confirm `lezwatchurls_setting_hide_display` still suppresses a link on the new path.

## Testing checklist

- [ ] `vendor/bin/phpunit --filter WatchHostsMap` green; covers trailing slash, path, case, `www.`, port, subdomain precedence, two-hosts-one-term, two-terms-one-host, junk.
- [ ] `wp lwtv waystowatch termurls --flagged` reports zero collisions before Phase 1 ships.
- [ ] Hand-add `https://hulu.com/` to a term in ACF → host leaves the tab on the next page load, with no button press. (This is the ask-4 acceptance test.)
- [ ] Assign a second host to an existing term via the dropdown → both hosts render that term's name on the front end.
- [ ] Create-new via the dropdown behaves exactly as today's CREATE TERM.
- [ ] Assigning a host that resolves to a *different* term gives a named error, writes nothing.
- [ ] Empty transient shows **Run Scan**; populated shows **Recheck**.
- [ ] Row count in the table equals the unresolved count in the header, exactly. No resolved host appears anywhere in the table.
- [ ] Deliberately point two terms at one host → a collision row appears naming both; remove one → it goes away.
- [ ] Query count on the tab drops from ~155 to a handful (Query Monitor).
- [ ] Query count on a show page with 3+ watch links drops accordingly.
- [ ] JS disabled: create-new still works, `<noscript>` notice present.
- [ ] Keyboard-only pass through one row: select → name field → submit; labels announced.
- [ ] A user with `upload_files` but not `manage_categories` sees the list and no forms.
- [ ] `attach_host()` writes exactly one `https://` row per host; no `http://` rows remain after Phase 0's normalise pass.
- [ ] `terms_without_urls()` gone; `tab_watch_term_check` no longer reports URL-less terms and its badge count reflects that.
- [ ] `composer lint` and `npm run lint:js` clean.

## Out of scope

- REST or AJAX. The plugin has none, and a page reload per assignment is fine at this volume.
- Pagination or sorting of the table. ~130 rows is one screen-and-a-bit.
- Any "resolved hosts" view. Resolved is not a problem; the `lez_watch_urls` taxonomy screen is
  where you go to look at terms.
- Merging duplicate provider terms, or a term-level "hosts pointing here" view.
- The `tab_watch_term_check` URL-health tab, `Watch_Url_Health`, and the cron scan.
- Show-score, statistics, or any CPT-relationship surface. None is touched.

### Deliberately not problems

Two term-side states look like findings and are not. **Neither should ever be reported by this
tab, and this is a decision, not an oversight:**

- **A term with no URL rows.** Legitimate: prepping a term for a network we know is coming.
  Inert until it has a URL, which is the correct behaviour. Visible in the `lez_watch_urls`
  listing already (`cpts/shows/class-ways-to-watch.php` renders a URL-count column), so it needs
  no second home.
- **A term whose URLs match no host currently in use.** Legitimate: history. A provider that
  shut down, or a show that dropped its link, should not cost us the term.

Anything added here later needs to survive both of those, so a future "orphan terms" report
would have to be opt-in and framed as inventory, not as a problem list.

**Consequence: `terms_without_urls()` comes out.**
`Debugger\Watch_URLs::terms_without_urls()` (`php/debugger/class-watch-urls.php:196-229`) reports
every URL-less term as `STATUS_REVIEW` with the text *"This term has no URLs, so nothing can ever
match it. Add a URL or delete the term."* — telling an editor to delete terms that are legitimate
prep. **Delete the method and its call site.** The taxonomy listing's URL-count column already
shows which terms have none.

Watch for: the findings count on `tab_watch_term_check` will drop, and `Debugger\Status::record()`
writes that count into `lwtv_debugger_status`, so the tab's badge changes. That is correct, not a
regression. Update the method's doc comment references and the `find_bad_watch_urls()` caller
rather than leaving a dangling private method. This is a small edit to a file the rest of this
plan does not touch — call it out explicitly in the PR.

## Minimum show count — stays at 1

`Watch_Hosts::unregistered()` takes a `$min_shows` argument (`class-watch-hosts.php:390`) and the
tab keeps passing the default of `1`. A domain used by exactly one show earns a row.

**Do not add a threshold, a `?min=` arg, or a default of 2.** The table is the complete problem
set, and a floor would make it silently incomplete — the same objection that killed the "show
resolved" view. The rows are already sorted most-used-first (`in_use()` returns them `arsort`ed),
so the hosts worth a term are at the top without hiding anything below them. The existing framing
paragraph about web series leaving a permanent long tail (`class-watch-providers.php:103`) is what
does the work here, and it stays.

`wp lwtv waystowatch hosts --min-shows` remains for CLI triage. The tab does not need it.
Nothing a reader sees depends on the stored scheme: the front end links the *show's* own URL and
only borrows the term's name (`theme/class-ways-to-watch.php:90`). The term URL is used for
matching and for `Debugger\Watch_URLs` probes, so forcing https means an http-only provider gets
reported as broken — which is the correct outcome, not a false positive.
