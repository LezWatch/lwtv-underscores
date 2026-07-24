# Design: Audit Tracking (diff + ignore + summary)

**Date:** 2026-07-24
**Status:** Approved (pending spec review)
**Scope:** Add run-to-run diffing, an acknowledge/ignore mechanism, and a triage
summary to the `wp lwtv audit` command, so the quad-monthly audit becomes a
*tracker* (memory of what was flagged and what was judged) rather than a
stateless snapshot.

## Background

`wp lwtv audit` (in `plugins/lwtv-plugin/php/wp-cli/cli-audit.php`) compares
on-air shows and their characters against TVMaze and emits a CSV of only the
items needing human attention. Today every run is stateless:

1. It cannot distinguish problems that are **new** this cycle from ones that
   were flagged last cycle and are **still open**, or confirm which got
   **resolved**.
2. Known-acceptable flags (a regular written out mid-year but not dead; sparse
   guest-cast data on old episodes) recur every run with no way to acknowledge
   them, so the review list never converges and trust erodes.
3. Output ends in a flat count with no breakdown to triage against.

This design adds all three, and places the reusable logic where a future
wp-admin surface can consume it.

## Goals

- Tag each finding `new` / `open` / `resolved` against a per-scope baseline.
- Let an operator acknowledge a specific finding so it stops recurring.
- End each run with a triage summary (by issue type, by new/open/resolved).
- Keep the logic reusable by a future wp-admin UI, not trapped in the CLI class.

## Non-goals

- No wp-admin UI in this iteration (the class is built so one can be added later).
- No `--fix` / auto-write behavior. Output stays a review list, by design.
- No change to what the audit *checks* or how it talks to TVMaze.

## Architecture

### Reusable class: `LWTV\Debugger\Audit`

New file `plugins/lwtv-plugin/php/debugger/class-audit.php`, following the
conventions of its neighbours (`LWTV\Debugger\Shows`, `…\Characters`): a plain
class, instance `public function` methods, `const` arrays for vocabulary, and
plain associative arrays for findings (no value objects). Auto-loaded by the
existing `LWTV` autoloader (namespace `LWTV\Debugger` → `php/debugger/`,
`Audit` → `class-audit.php`); no component registration needed since it hooks
nothing.

It carries no WP-CLI dependency, so a future dashboard widget instantiates
`new Audit()` and calls the same methods.

Method groups:

**Vocabulary**
- `const ISSUE_TYPES` — the five issue types (below), each with `level`
  (`show` | `character`) and a human label.

**Ignore** (wraps character meta `lezchars_audit_ignore`)
- `is_ignored( int $char_id, int $show_id, string $issue_type ): bool`
- `add_ignore( int $char_id, int $show_id, string $issue_type ): bool`
- `remove_ignore( int $char_id, int $show_id, string $issue_type ): bool`
- `get_ignores( int $char_id ): array` — the character's current ignore keys

**Baseline / diff** (wraps per-scope options)
- `load_baseline( string $scope ): array` — key → stored finding
- `save_baseline( string $scope, array $findings ): void`
- `diff( string $scope, array $findings ): array` — returns findings with a
  `status` of `new` or `open`, plus reconstructed `resolved` findings (present
  in baseline, absent now)
- `reset_baseline( string $scope = '' ): void` — one scope, or all when empty
- `list_scopes(): array` — from the index option

**Orchestration**
- `finalize( string $scope, array $findings ): array` — the single entry point
  the CLI (and a future UI) calls. It:
  1. runs `diff()` on the **raw** finding set (ignore is *not* applied here),
  2. `save_baseline()` with the raw current set,
  3. partitions into `rows` (new + open, ignore-filtered), `resolved`, and a
     `summary` count block,
  4. returns `[ 'rows' => [...], 'resolved' => [...], 'summary' => [...] ]`.

  Ignore is a **display filter only** — it never touches diff or baseline, so
  toggling an ignore can never corrupt new/open/resolved detection.

### CLI: `cli-audit.php` stays the thin presentation layer

- `build_row()` is retained but its output array gains the identity keys
  (`scope`, `show_id`, `char_id`, `issue_type`, `year`) alongside the existing
  display keys (see Finding shape).
- `audit_catalog()` / `audit_single()` collect finding arrays, derive the scope
  string, call `new Audit()->finalize( $scope, $findings )`, then render
  `rows` (+ `resolved` when `--show-resolved`) and emit the summary.
- New subcommands routed in `__invoke()`:
  - `wp lwtv audit ignore <char_id> --show=<id> --issue=<type>` (`--remove` undoes)
  - `wp lwtv audit ignores <char_id>` — list a character's ignores
  - `wp lwtv audit reset [<scope>]` — clear one/all baselines (confirm required)

## Data model

### Issue types (`const ISSUE_TYPES`)

| key            | level     | source action text                          |
|----------------|-----------|----------------------------------------------|
| `no-match`     | show      | "Add IMDb/TVMaze ID or audit manually"       |
| `ended`        | show      | "Set end year …"                             |
| `tbd`          | show      | "Review: show in limbo on TVMaze"            |
| `missing-year` | character | "Add {year} to Years Appears" / "TVMaze shows {year} -- add?" |
| `verify-year`  | character | "Verify {year} -- no TVMaze appearance found" |

Only character-level types (`missing-year`, `verify-year`) are valid targets
for `ignore`; the subcommand validates this.

### Finding array shape

Identity + display in one array:

```php
array(
    // identity
    'scope'         => 'catalog_regular',
    'show_id'       => 123,
    'char_id'       => 456,        // 0 for show-level findings
    'issue_type'    => 'missing-year',
    'year'          => 2026,       // 0 for show-level findings
    // display (rendered columns)
    'show'          => 'Show Title',
    'tvmaze_status' => 'Running',
    'tvmaze_ended'  => '',
    'character'     => 'Character Name',
    'actor'         => 'Actor Name',
    'role'          => 'regular',
    'action'        => 'Add 2026 to Years Appears',
    // added by diff()
    'status'        => 'new',      // 'new' | 'open' | 'resolved'
)
```

**Finding key** (identity within a scope):
`"{show_id}:{char_id}:{issue_type}:{year}"`.

### Scope string

The scope encodes **every input that changes which findings are produced**, so
a baseline is only ever diffed against a run of the same shape:

- Catalog, whole: `catalog_<roles>` (e.g. `catalog_regular`, `catalog_none`)
- Catalog, one letter: `catalog_<letter>_<roles>` (e.g. `catalog_a_regular`,
  `catalog_num_regular`, `catalog_intl_regular`). The scope uses the flag token
  (`a`–`z` / `num` / `intl`), never the raw marker (`#` / `-`), to keep option
  names shell- and storage-safe.
- Deep audit: `show_<id>_<rolesig>[_all]` where `rolesig` is the additive roles
  joined by `-` (e.g. `show_123_regular`, `show_123_regular-recurring_all`)

Rationale: without this, running `--roles=none` one cycle would make every
character finding look `resolved` against a `regular` baseline, and a `--all`
run would flood a current-year baseline with historical `new` rows. Cost: a few
more baselines, all cheap and pruneable via `reset`.

### Storage

- **Baseline** — one non-autoloaded option per scope,
  `lwtv_audit_baseline_<scope>`, value = `key => finding-array`. `autoload` is
  `'no'` so baselines never touch the front-end load path.
- **Baseline index** — option `lwtv_audit_baselines`,
  value = `scope => array( 'last_run' => <unix ts>, 'count' => <int> )`. Lets
  `list_scopes()` / `reset` enumerate without scanning `wp_options`, and gives a
  future UI a cheap directory of what's been audited.
- **Ignore** — character post meta `lezchars_audit_ignore`, value = array of
  `"{show_id}:{issue_type}"` strings. Travels with the character; removed
  automatically if the character is deleted. Prefix follows the `lezchars_`
  convention.

This mirrors the existing debugger split (`Shows` stores its item list in a
transient and a summary in the `lwtv_debugger_status` option) — here the durable
baseline is an **option**, not a transient, because a transient's Redis eviction
between the ~3-month runs would silently wipe the baseline and make everything
look `new`.

## Output & summary

- A `status` column is added: `new` / `open`.
- **Resolved rows stay out of the main CSV**, preserving the tool's "everything
  in the file needs action" contract. They surface only with `--show-resolved`
  (status `resolved`), or as a count in the summary.
- The summary is written directly to **STDERR** via `fwrite( STDERR, … )`, so
  it never corrupts a `> audit.csv` redirect regardless of `--format`.
  (Note: `WP_CLI::success()` writes to STDOUT, not STDERR — only
  `WP_CLI::warning()`/`error()` use STDERR — so the summary cannot go through
  `success()`.) It reports:
  - total needing attention,
  - breakdown by issue type,
  - `X new / Y still open / Z resolved since <last-run date>`,
  - `N acknowledged (hidden)` — for coverage honesty.
- First run of any scope has no baseline: everything is `new`, and the summary
  says so.

## Approved judgment calls

- **(a)** No `Finding` value object — plain associative arrays, matching the
  debugger's style.
- **(b)** Resolved rows are excluded from the main CSV; `--show-resolved` opts in.
- **(c)** Ignore is **year-independent** — an ignore on `missing-year` /
  `verify-year` silences that character+show for future years too. Easy to make
  per-year later by appending `:{year}` to the ignore key.

## Error handling

- `ignore` / `ignores` validate the character ID (must be a published
  `post_type_characters`) and `--issue` (must be a character-level issue type);
  invalid input exits non-zero via `WP_CLI::error` (consistent with the recent
  exit-code fix in `__invoke`).
- `reset <scope>` requires confirmation (`--yes` to skip), matching the existing
  large-job confirm.
- A malformed or missing baseline option is treated as an empty baseline (all
  `new`), never a fatal error.

## Testing

The logic now lives in a WP-CLI-free class, so it is unit-testable, but this
repository has no PHP test harness today. Verification for this iteration is
**manual on `lwtv.local`** plus `composer lint`:

1. Run a letter bucket twice with no changes → second run shows all `open`,
   `0 new`, `0 resolved`.
2. Fix one flagged item, rerun → that row is absent, summary reports `1 resolved`;
   `--show-resolved` lists it.
3. `wp lwtv audit ignore <char_id> --show=<id> --issue=missing-year`, rerun →
   row is hidden, summary reports `1 acknowledged`; `--remove` restores it.
4. `wp lwtv audit reset catalog_regular` → next run is all `new` again.
5. Confirm CSV redirect (`--format=csv > f.csv`) contains no summary text.

If a minimal test harness for the three method groups is wanted, that is a
separate, scoped follow-up.

## Out of scope / future

- wp-admin dashboard widget rendering audit findings through the shared
  debugger template (the class is built to enable this).
- Scheduled + emailed catalog run (Action Scheduler) — previously discussed,
  deferred.
- Per-year ignore granularity (append `:{year}` to the ignore key).
