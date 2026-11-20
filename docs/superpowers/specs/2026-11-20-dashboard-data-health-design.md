# Dashboard "LezWatch.TV Tools" widget: Data Health

Date: 2026-11-20 · Size: small change · Status: draft, awaiting approval

## Why

The widget works but only lists non-zero counts and one "last run" time. It should be
at-a-glance useful: tell an editor *what to fix first* and tell an admin *whether the
machinery is still running*.

## Goals

- Every line links to its Data Validation tab.
- Lines are grouped by severity: **Fix now**, **Needs attention**, **Backlog**.
- Each line shows its trend: `32 · +5 new, 3 fixed`.
- A check that has stopped running is called out.
- Admins see failed/overdue background jobs.
- The widget's numbers always match the Data Validation screen.

## Non-goals

- No new storage, snapshots or cron steps.
- No Site Pulse content (on-air this week, momentum, score movers): separate later work.
- No change to what the checks themselves find.

## Anti-goals

- Editors seeing job failures they cannot act on.
- The widget disagreeing with the Tools page (today it reads `Status` counts, which never
  expire, while the Tools page counts findings).

## Audience (decided)

- Everyone who sees the widget today (`upload_files`): data groups, quiet "last checked" date.
- Admins only (`manage_options`, **my call**): red stale warnings naming the stalled check,
  and the Background Jobs block.

## Severity tiers (decided)

| Tier | Checks (TOOL_TABS key) |
|------|------------------------|
| Fix now | `dupe_checker`, `queer_checker`, `onair_checker`, `watch_hosts`, `watch_term_check` |
| Needs attention | `byq_checker`, `show_checker`, `character_checker`, `actor_checker`, `watch_providers` |
| Backlog | `actor_empty`, `actor_imdb`, `show_imdb` |

Empty tiers are hidden. If every tier is empty, keep the existing celebration line.

## Approaches

- **A. Pure "health report" builder + template, shared counting (chosen).** Fits the
  build → format → templates pattern; ordering/stale rules are unit-tested; one widget.
- B. Two widgets (data for all, jobs for admins). Rejected: splits stale warnings (a job
  problem) from the counts they qualify; more boxes for the same information.
- C. Precompute in nightly cron. Rejected: goes stale after a Recheck, which is the drift
  we are removing; the live cost is ~a dozen option reads plus one job query.

## Design

1. **Severity lives in `Validation::TOOL_TABS`** as a new `'severity'` key per entry, so
   adding a check means classifying it in one place.
2. **Shared counting.** `Validation::tab_counts()` becomes public (still memoised) so the
   widget reuses the Tools page's numbers: `count` and `new` from findings, `last` from
   status. `resolved` comes from the check's status `summary` (last full run).
3. **Pure builder** `LWTV\Admin_Menu\Build\Health_Report` (under `admin-menu/build/`):
   input = tab registry (name, severity, link tab), per-tab counts, job counts, `now`;
   output = `{ tiers: [ tier => [ rows ] ], stale: [ names ], last_checked: int, jobs: {...} }`.
   - Rows with `count` 0 are dropped; within a tier, sorted by count descending.
   - **Stale** = a check whose `last` is 0 or older than **8 days** (checks rotate by
     weekday, see `docs/operations/cron-schedule.md`). Stale checks are reported even if
     their count is 0.
   - `last_checked` = newest `last`, for the editors' quiet line.
   - Link target: `watch_hosts` uses its `tab` (`watch_providers`); others link to themselves.
4. **Job counts** (admin only, live glue, not unit-tested): Action Scheduler actions in
   groups `lwtv` and `lwtv_byq` with status **failed** in the last 7 days, and **pending**
   whose scheduled date is more than 1 hour past (**my call** on both windows). Links to
   `tools.php?page=action-scheduler` filtered to failed.
5. **Template** renders tiers as headed lists, each row
   `Name ...... 32 · +5 new, 3 fixed →` linking to
   `admin.php?page=lwtv_data_check&tab=tab_<key>`. The new/fixed part is omitted when both are 0.

## My calls (please check)

- Admin = `manage_options`.
- Jobs windows: failed in last 7 days; overdue = pending > 1 hour late.
- Row label = the TOOL_TABS `name`, so it matches the tab picker you land on
  (today's widget shows the status label, e.g. "Actors without IMDb").
- Trend = new/fixed from the baseline instead of ↑/↓ arrows.

## Left to the builder

Markup/CSS details (stay with core admin styles), i18n strings with the `lwtv-underscores`
domain, test cases for the builder (tiering, sorting, zero-dropping, stale edge at
exactly 8 days, never-run checks, the `watch_hosts` link redirect).
