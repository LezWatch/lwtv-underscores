# Watch URL debugging: link findings to the affected shows

Date: 2026-09-01
Status: Planned
Branch context: work happens on a feature branch; PRs target `production`.

## Goal

When the Watch Term Check or Watch Providers tab flags a provider URL or an
unmapped host, an editor can expand the row's "Shows" count into a list of the
actual affected shows, each linked to its editor — replacing today's workflow
of hand-querying `wp_postmeta` for the URL and copying post IDs.

## Decisions made with the user

- Surface: expandable list inline in the existing "Shows" column (`<details>`),
  built from post IDs captured at scan time. No new admin-list filter.
- No cap: store all affected post IDs (ints are cheap), resolve titles and
  edit links lazily at render.
- Scope: both tabs — Watch Term Check (`tab_watch_term_check`) and Watch
  Providers (`tab_watch_providers`).
- CLI: unaffected. `wp lwtv debug watchurls` keeps its current columns
  (columns are explicit at `php/wp-cli/cli-debug.php:358`, so the new row key
  cannot leak into terminal output).

## Scope

**In**

- Preserve the per-host post-ID sets that `Watch_Hosts::in_use()` currently
  builds and discards.
- Attach `show_ids` to Watch URL findings (per term URL) and to the Watch
  Providers unregistered-host worklist (per host), surviving both tabs'
  re-check paths.
- Expandable, linked show lists in both tabs' "Shows" columns, with cache
  priming so rendering is not N queries.
- Unit tests for the new pure transforms (per repo convention: pure only, no
  WP bootstrap).

**Out**

- CLI output changes.
- New admin-list filters on `edit.php`.
- Any change to the contested-hosts (collisions) table, the Show Score, or
  scan scheduling/cron.
- Findings key bump: `lwtv_debug_watch_urls_v2` stays. Old cached rows simply
  lack `show_ids` and render as bare counts until the next sweep.

## Current pipeline (all paths verified against source)

1. `Watch_Hosts::in_use()` — `php/cpts/shows/watching/class-watch-hosts.php:131-194`.
   Builds `$seen[$host][$post_id]` (line 182), collapses to counts (186-188),
   discards the IDs.
2. `Watch_Hosts::shows_per_term()` (327-341) sums those counts per term. Note:
   a show reaching one term through two hosts is counted twice today.
3. `Watch_Hosts::scan_unregistered()` (474-523) stores the Watch Providers
   worklist as `{host, shows}` rows under `FINDINGS_UNREGISTERED`; counts are
   re-derived from `in_use()` on both scan and re-check.
4. `Debugger\Watch_URLs::all_targets()` / `finding()`
   (`php/debugger/class-watch-urls.php:155-169, 264-283`) attach the count to
   each term-URL finding's context. `targets_from_items()` (182-211) carries
   `shows` verbatim through the tab's bounded re-check — any new data must be
   carried here too or it vanishes on first re-check.
5. `Debugger\Format\Rows::from_term_findings()`
   (`php/debugger/format/class-rows.php:78-111`) lifts `url|term|shows|health`
   from context to the row.
6. Renderers: `Validator\Watch_Term_Check::render_row()`
   (`php/validator/class-watch-term-check.php:312-345`, Shows column at 337)
   and `Validator\Watch_Providers::render_row()`
   (`php/validator/class-watch-providers.php:387-536`, count at 408).
7. Storage: `Debugger\Findings_Store` writes autoload-false options, so
   attaching integer ID arrays (a few hundred at worst for Netflix-sized
   terms) is a few KB — no storage concern.

## Tasks

Ordering: 1 → (2, 3 in either order) → (5, 6 after 4) → 7. Tasks 2 and 3 are
independent of each other; task 4 is independent of 1-3.

### 1. Preserve the ID sets in `Watch_Hosts` (foundation)

Files: `php/cpts/shows/watching/class-watch-hosts.php`,
`php/cpts/shows/watching/class-watch-host-map.php`,
`tests/unit/CPTs/WatchHostMapTest.php`.

- In `in_use()`, memoise the `$seen` map (as `host => int[]`, values
  `array_keys( $post_ids )`) in a new static alongside `$in_use`; keep
  `in_use()`'s return contract (host => count, sorted desc) untouched — it has
  other consumers (`cli-waystowatch.php`, `class-watch-host-collisions.php`,
  `class-watch-providers.php`, `class-watch-term-url-audit.php`).
- New accessor `Watch_Hosts::show_ids_by_host(): array<string, int[]>`.
- New pure static `Watch_Host_Map::ids_per_term( array $ids_by_host, array $map ): array<int, int[]>`
  — resolves each host through the existing pure `resolve()`, merges and
  dedupes IDs per term (write its test first; `WatchHostMapTest.php` already
  covers this class).
- Reimplement `shows_per_term()` on top of it:
  `array_map( 'count', ids_per_term( ... ) )`, and add
  `Watch_Hosts::show_ids_per_term(): array<int, int[]>`. This deliberately
  fixes the double-count noted above so the count always equals the length of
  the rendered list.

Done when: `vendor/bin/phpunit --filter WatchHostMap` passes with the new
merge cases (dedupe across hosts, unresolved hosts skipped) and nothing else
in the suite breaks.

### 2. Carry `show_ids` through the Watch URL findings pipeline

Files: `php/debugger/class-watch-urls.php`,
`php/debugger/format/class-rows.php`, new `tests/unit/Debugger/RowsTest.php`.

- `all_targets()`: attach `'show_ids' => $ids_per_term[ $term_id ] ?? array()`
  and derive `'shows'` from `count()` of that list.
- `finding()`: pass `show_ids` in the `$context` array
  (`Findings::make_for_term()` stores context verbatim — no change there).
- `targets_from_items()`: carry
  `'show_ids' => array_map( 'intval', (array) ( $item['show_ids'] ?? array() ) )`
  exactly as `shows` is carried, so the re-check path keeps the list.
- `Rows::from_term_findings()`: add `show_ids` to the lifted-keys list
  (line 96).
- Test (pure, TDD): `from_term_findings()` lifts `show_ids` when present and
  omits the key when absent (old cached findings) — `from_term_findings()`
  calls no WP functions, so it loads under the non-WP bootstrap.

Done when: RowsTest passes; a full-sweep finding and a re-checked finding both
carry `show_ids`.

### 3. Attach `show_ids` to the unregistered-hosts worklist

File: `php/cpts/shows/watching/class-watch-hosts.php` (`scan_unregistered()`).

- Each `$found` row gains `'show_ids' => $ids_by_host[ $host ] ?? array()`,
  re-derived on both scan and re-check paths, same as counts ("a row never
  shows a count from a previous week" applies equally here).
- `forget_unregistered()` and `unregistered()` need no changes (verbatim
  keep / live view respectively).

Done when: after Run Scan on the Watch Providers tab, the stored
`lwtv_watch_unregistered` rows carry ID arrays (inspect via
`wp option get` on lwtv.local).

### 4. Shared "affected shows" cell renderer

New file: `php/validator/class-affected-shows.php` (`LWTV\Validator\Affected_Shows`).

- `prime( array $items, string $key = 'show_ids' ): void` — flatten all rows'
  ID lists and `_prime_post_caches( $ids, false, false )` once per page render.
- `cell( array $show_ids, int $count ): void` — echoes:
  - bare count when `$show_ids` is empty (old cached findings, or zero shows);
  - otherwise `<details><summary>` carrying the count via
    `_n( '%d show', '%d shows', ... , 'lwtv' )` and a `<ul>` of links:
    `get_edit_post_link( $id )` + `get_the_title( $id )`; skip IDs whose post
    no longer exists or is no longer published (stale since the scan); fall
    back to plain title text when there is no edit link.
- All output escaped internally (`esc_url`/`esc_html`) — this class is NOT in
  the phpcs auto-escaped allowlist. All strings i18n'd with `'lwtv'`.
- No JavaScript: native `<details>` matches the no-JS-degradation stance the
  Watch Providers tab already takes.

Done when: class exists, lints clean (`composer lint`), and renders correctly
when wired in tasks 5-6.

### 5. Wire into Watch Term Check

File: `php/validator/class-watch-term-check.php`.

- `render_findings()`: call `Affected_Shows::prime( $items )` before the row
  loop.
- `render_row()`: replace the Shows cell (line 337) with
  `Affected_Shows::cell( (array) ( $item['show_ids'] ?? array() ), (int) ( $item['shows'] ?? 0 ) )`.

Done when: on lwtv.local, after `wp lwtv debug watchurls --force`, a flagged
row's Shows count expands to linked show titles, each opening the show editor;
a row from a pre-change cache renders the bare count; pressing "Re-check these
URLs" keeps the lists on still-flagged rows.

### 6. Wire into Watch Providers

File: `php/validator/class-watch-providers.php`.

- `make()`: keep building `$unregistered` but preserve each item's `show_ids`
  (the current loop flattens to `host => count`; carry the full row instead),
  and call `Affected_Shows::prime()` before the table.
- `render_row()`: signature gains the ID list (or takes the row array); Shows
  cell (line 408) becomes `Affected_Shows::cell( ... )`.

Done when: on lwtv.local, Run Scan then expand a host row to see the shows
pointing at it; Recheck preserves the behaviour; assigning a term still prunes
the row.

### 7. Full verification pass

- `composer lint` clean; `vendor/bin/phpunit` green.
- Live on lwtv.local (theme is symlinked; see local wp-cli setup memory):
  full sweep, both tabs, expand/collapse, links land on the right editors,
  re-check paths, and one Netflix-sized term to eyeball the long-list DOM.
- Visual check of `<details>` inside `.widefat` tables in light and dark
  admin schemes; only if it needs styling, add it to
  `plugins/lwtv-plugin/assets/css/lwtv-tools.css` (where the existing
  `.lwtv-tools-table` styles live).
- No commits without explicit approval; leave the branch open for manual
  testing (workflow constraints in CLAUDE.md).

## Risks and open questions

- **Count semantics shift slightly.** Deduping per-term IDs (task 1) corrects
  today's double-counting of shows that reach a term via two hosts, so some
  "Shows" numbers may drop a little after the next sweep. This is a fix, but
  worth a sentence in the PR description.
- **Stale IDs between sweeps.** A show edited after the scan may no longer use
  the URL; the renderer skips deleted/unpublished posts but cannot see meta
  changes, so the expanded list is "as of the last scan" — same staleness the
  count already has. Acceptable; the re-check button refreshes term findings.
- **Old cached findings.** Rows stored before this change have no `show_ids`;
  both renderers must treat the missing key as "count only" (task 4 does).
  No findings-key bump needed — the row shape is an additive superset, the
  same philosophy documented atop `format/class-rows.php`.
- **Option size.** Worst case is `FINDINGS_UNREGISTERED` (~130 hosts, long
  tail of 1-2 shows each) plus a few hundred ints on big terms — a few KB in
  an autoload-false option. Not a concern, but noted.
- **`shows_per_term()` reimplementation** touches the sort order of findings
  (`Watch_URLs::sort_findings()` sorts by count). Order may shift where
  dedupe changes counts; the severity-first sort is unaffected.
