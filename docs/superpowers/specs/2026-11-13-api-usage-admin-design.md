# API Usage admin page — design

Date: 2026-11-13. Status: approved. Source brief: the "WP admin page for LWTV API usage" handoff.

## Intent

A read-only admin page that summarizes `lwtv/v1` REST API usage from the daily rollup CSV
(`/home/wp_bg3hrq/lwtv-api-usage/usage.csv`, written by `cron/api-roundup.sh`), so the owner can see
what is broken, what is used, what has had no hits, and who to contact. Not an analytics replacement.

## Goals / non-goals

- Goals: route-grouped stats with broken routes first; per-consumer view; editable known-consumers list
  with an internal flag; stale-cron warning; zero-hit routes listed with honest wording.
- Non-goals: charts, CSV export, retention/rollup, editing the CSV, changes to the awk classifier.
- Anti-goals: storing IPs or raw UAs; per-request PHP tracking; calling a route "unused" or "safe to remove".

## Constraints

- Web requests can read the CSV (probe 2026-11-13: readable, no `open_basedir`), so the page reads it
  directly. No import table.
- Every CSV field is untrusted (derived from request headers). Escape everything.
- Capability `activate_plugins` (matches Exclusion Checker). Assumed admins-only; co-dev access unconfirmed.

## Approaches

1. **Parse the CSV in the page, cache on mtime** — chosen. File is small (tens of thousands of rows/year)
   and readable from web context.
2. Cron imports CSV into a custom table — only needed if the web side couldn't read the file. Rejected.
3. Per-request tracking in PHP — rejected by the brief (misses cached responses, adds overhead).

## Design

Location: `plugins/lwtv-plugin/php/admin-menu/`, submenu "API Usage" under the LezWatch.TV menu.

Pure transforms in `admin-menu/build/` (unit-tested, no WP):

- `Api_Usage_Csv` — lines → rows; skips header/malformed lines; sums duplicate
  `(date, endpoint, consumer, status)` keys.
- `Api_Route_Matcher` — normalizes paths (strip `/wp-json` prefix, trailing slash); matches against
  registered route regexes; **most-specific match wins** (most literal characters), deviating from the
  brief's first-match so generic routes don't swallow specific ones; unmatched paths grouped visibly.
- `Api_Usage_Report` — rows + date range + internal labels + route map → per-route table
  (hits, 2xx/4xx/5xx/499, error rate = (4xx+5xx)/hits, bytes, distinct consumers, last seen),
  per-consumer table (hits, routes, error rate, first/last seen, new flag within 14 days),
  zero-hit route list.

WordPress glue (verified live): transient keyed on file mtime; staleness warning at >36h on mtime or
newest date; tabs By Route (default sort 5xx desc) / By Consumer / No Hits In This Range; date range
(default last 30 days, or all); internal toggle; known consumers in a non-autoloaded option, edited via
`admin-post` with nonce, pre-seeded with `uptime-kuma` and `WP:https://www.thetracyl.com` (internal) and
the three named crawlers (no contacts).

## Left to the builder

Exact markup, sort UI, option key names, and test fixture contents.
