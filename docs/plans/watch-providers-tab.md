# Plan: Watch Providers validation tab — assign, resolve, cache

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`).
**Page:** `admin.php?page=lwtv_data_check&tab=tab_watch_providers`
**Scope:** the `lez_watch_urls` ↔ Ways-to-Watch host resolution path, admin *and* front end. No change to show scoring, statistics, or the CPT relationships.

## Goal

Four asks, one root cause between them:

1. List **every host that still has a problem** — the table's contents should be the complete
   problem set, not a subset of it.
2. A check / re-check button, tri-state like the other nine validator tabs.
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
badge (`admin-menu/class-validation.php:85-90`). The front end pays the same per watch link.

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
| Recheck button | Cache the host scan in a transient; tri-state `Run Scan` / `Recheck`, following the Pattern A used by the other nine tabs. |
| Term dropdown | One `<select>` per row, options cloned from a single shared `<template>` by a small admin JS file. |
| Table contents | Problems only. A host with a term is not a problem and is not rendered. No "show resolved" view. |
| Stored scheme | `https://` only, one row per host. The matcher is scheme-blind after Phase 1, so a second row is dead weight. Phase 0 rewrites stored `http://` rows. |
| URL-less terms | Legitimate (prep for a coming network). `Watch_URLs::terms_without_urls()` gets deleted. |
| `$min_shows` | Stays at 1. No threshold, no `?min=` arg — a floor would make the problem set silently incomplete. |

Every question this plan opened is answered. Nothing below is waiting on a decision.

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

The normalise/cleanup pass described below is **deliberately not written yet** — what it should
rewrite depends on what the audit finds.


Host matching is looser than exact-URL matching. If any term has deliberately registered a
*path* (`youtube.com/c/somechannel` is the plausible one — LWTV documents web series, and
several live on YouTube channels), host matching would make that term swallow every YouTube URL
on the site. Find out before changing anything.

New read-only CLI subcommand, `wp lwtv waystowatch termurls`, in
`php/wp-cli/cli-waystowatch.php` alongside `hosts` / `enrich` / `forget`. Walks
`Watch_Hosts::term_urls()` and reports per row:

- `term_id`, `name`, `url`, parsed `host`
- flags: `path` (anything past `/`), `query`, `fragment`, `port`, `trailing-slash`, `uppercase`, `no-scheme`, `unparseable`
- **`collision`** — two or more distinct `term_id`s whose URLs normalise to the same host

Supports `--format=table|csv|json` and `--flagged` to show only rows needing attention.

**Gate:** if `collision` is non-empty, or any `path` row is intentional, stop and revisit the
matching decision — the "path wins, host falls back" variant becomes necessary and this plan
needs a Phase 1b. If the only flags are trailing slashes, case and ports, proceed.

Companion cleanup, dry-run by default, in the existing migrate command family:
`wp lwtv migrate watchtermurls-normalise [--dry-run] [--live]`. Rewrites cosmetically-dirty
rows to bare `https://host`. **Leaves path-bearing rows alone** for a human. This is
belt-and-braces — Phase 1 makes them match anyway — but it keeps the stored data honest and
keeps `Debugger\Watch_URLs` probing something sensible.

---

## Phase 1 — Host-based resolution, one query

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

## Phase 2 — Cache the scan, tri-state the button

The expensive half is now `in_use()`: one big `REGEXP` walk of `wp_postmeta`
(`class-watch-hosts.php:127`). Cache that; resolve terms live from the Phase 1 map.

**This is what delivers ask (4) with no invalidation hooks at all.** The snapshot holds *hosts
in use*, which only changes when a show is edited. Term resolution is recomputed on every
render from one cheap query — so a term created in the ACF UI, by CLI, or by another editor is
reflected on the very next page load, regardless of who did it.

- Transient `lwtv_watch_providers`, via `lwtv_plugin()->set_transient()`, `WEEK_IN_SECONDS`,
  matching `Debugger\Watch_URLs::TRANSIENT_PROBLEMS`.
- Payload: `array{ generated: int, hosts: array<string,int> }` — every host in use with its
  distinct-show count, `arsort`ed as `in_use()` already returns it.
- `Watch_Hosts::scan(): array` builds and stores it. `wp lwtv waystowatch hosts` primes it, so
  the daily cron already warms the page.
- Tri-state render, copying `Watch_Term_Check::make()` (`validator/class-watch-term-check.php:88-107`):
  `false === $snapshot` → never run, button reads **Run Scan**; populated → button reads
  **Recheck**. Same `rerun` / `recheck` field naming as the other tabs so the page is
  predictable.
- Handler: keep this one on `admin-post.php` with the notice plumbing already in this class
  (`set_notice()` / `redirect_back()`, lines 367-415) rather than the self-POST of Pattern A —
  the class is already wired into `_Components\Admin_Menu::init()` for exactly this reason
  (`_components/class-admin-menu.php:45-47`).
- **No time budget needed.** This is local SQL, unlike the name lookup. Do not merge the two
  buttons.
- The count badge in `Validation::TOOL_TABS` can now be turned on if wanted (reading a
  transient is free) — but that requires a `Debugger\Status::record()` call, so leave it off and
  keep the comment at `class-validation.php:85-90` accurate. Out of scope.

Deliberately **not** hooking `save_post_post_type_shows` to bust the snapshot: a show save
would make the next admin view do the full scan, and the button plus a week's TTL is enough.
Render the `generated` timestamp so the age is visible.

---

## Phase 3 — The table

**One row per problem, every problem, nothing else.** A host that resolves to a term is done and
is not rendered — no resolved section, no toggle, no `?show=` variants.

Header line, for scale rather than as an invitation to see the rest:

> **130** of 154 hosts in use have no provider term. Scanned 2 hours ago.

Columns: `Host` | `Shows` | `Renders as` | `Provider term`.

Two problem classes belong in this table, and only the first exists today:

1. **No term.** The host resolves to nothing; the front end is guessing its name. Row offers the
   assign-or-create control from Phase 4.
2. **Host claimed by two terms.** Surfaced for the first time by the Phase 1 map, which has to
   pick a winner and now knows when it did. The front end renders *something* here, so it is not
   broken — but which term wins is an accident of alphabetical order, and that is a problem.
   Row names both terms, links both edit screens, and offers no one-click fix: resolving it means
   deciding which term is right and deleting a URL row by hand. Phase 0's audit should mean this
   section is empty on day one; it exists so a later collision is visible instead of silent.

Keep the existing "from the site itself" / "guessed from the hostname" provenance line under
`Renders as` (`class-watch-providers.php:160-164`) — it tells an editor whether the prefilled
name is worth trusting.

Keep the framing paragraph about web series always leaving a long tail (line 103). It is true
and it stops this list reading as a failure.

---

## Phase 4 — Assign-or-create

### 4a. Model

```php
// class-watch-hosts.php — new, public
public static function attach_host( int $term_id, string $host )   // int|WP_Error
```

- Normalise the host; reject empty.
- Verify `$term_id` is a real term **in `lez_watch_urls`** — `get_term( $id, TAXONOMY )`,
  `instanceof WP_Term`. Never trust a POSTed term ID.
- If the host already resolves *to this same term*, succeed idempotently.
- If it resolves to a **different** term, return `WP_Error` naming that term — that is a
  collision an editor needs to see, not something to paper over.
- Otherwise `add_url_to_term( $term_id, 'https://' . $host )` and `unset( self::$terms[ $host ] )`.

Make `add_url_to_term()` derive its index from the real subfield rows (see Latent bug above):
scan `lezwatchurls_all_N_url` upward until a gap, write there, then set the count meta to the
true row count. Existing duplicate-URL guard stays.

`create_term()` keeps its `lwtv_host_registered` guard — with the assign path existing, that
error is now correct advice rather than a dead end. Reword the message to point at the
dropdown.

### 4b. UI

One form per row, one new admin-post action `lwtv_watch_assign_term`, per-host nonce and
`CAP_MANAGE` exactly as `handle_create()` does (`class-watch-providers.php:236-243`):

```html
<select name="term_id">
  <option value="0">— Create a new term —</option>
  <!-- cloned from #lwtv-watch-term-options -->
</select>
<input type="text" name="provider_name" value="{proposed}">   <!-- shown only when term_id = 0 -->
<button type="submit">Assign</button>
```

Terms fetched **once** per render:
`get_terms( array( 'taxonomy' => 'lez_watch_urls', 'hide_empty' => false, 'fields' => 'id=>name', 'orderby' => 'name' ) )`.
Rendered once into a `<template id="lwtv-watch-term-options">`.

New `assets/js/lwtv-watch-providers.js` — the plugin's first admin JS, so keep it tiny and
dependency-free:

1. On `DOMContentLoaded`, clone the template's options into every `select.lwtv-watch-term`.
2. On `change`, show/hide the row's `provider_name` field and flip the button label between
   *Create term* and *Assign*.

Enqueue in `_Components\Admin_Menu::admin_enqueue_scripts()` (`_components/class-admin-menu.php:134-139`),
gated on both `lezwatch-tv_page_lwtv_data_check` **and**
`'tab_watch_providers' === ( $_GET['tab'] ?? '' )` — no reason to ship it to the other ten tabs.
Version constant beside the CSS's `1.2.0`.

**Degradation:** with JS off, the select holds only "Create a new term" and the name field is
visible, so today's behaviour survives exactly. A `<noscript>` line says assignment needs JS and
links to the `lez_watch_urls` taxonomy screen. Accepted tradeoff of the shared-template choice.

**Accessibility:** the select needs its own `screen-reader-text` label per row (the existing
name input already has one, line 172), and the button label change must not be the only signal —
keep the field's visible label accurate too.

### 4c. Handler

`handle_assign()`: nonce → cap → `term_id > 0` ? `attach_host()` : `create_term()`. Both
outcomes reuse the existing `set_notice()` / `redirect_back()` plumbing and the "Edit the term"
link. On the not-yours-collision error, name both terms in the notice.

---

## Phase 5 — Name lookup, unchanged

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
