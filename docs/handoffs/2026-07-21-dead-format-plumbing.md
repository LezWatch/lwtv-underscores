# Handoff: Retire Dead Chart-Format Plumbing

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin on `feat/cliche-stats`.
**Scope:** Remove the now-inert `trendline` / `barchart` / `piechart` format branches and their helper methods from the statistics data/format layer, left behind after Chart.js was removed. **Pure dead-code removal — zero behaviour change intended.**

## Goal
Chart.js is gone. The format *classes* that produced its data (`class-barcharts-optimized.php`, `class-piecharts-optimized.php`, `class-trendline-optimized.php`) were already deleted. But the **branches that fed them** still live inside the builders, the generator, and the handler — plus a couple of format classes whose tables the redesign replaced. This handoff removes that plumbing as one coherent pass so the format-routing chain reflects reality.

## ⚠️ This touches protected code — read first
`class-dead.php` is in the death-statistics data layer, which `CLAUDE.md` flags as load-bearing ("don't short-circuit queries in ways that silently drop data"). These branches are **inert** (no live caller), so removal drops nothing from any rendered path — but every deletion must be proven dead end-to-end before it's cut. Do not remove a branch on suspicion; grep it to zero first.

## What's CONFIRMED dead (verified this session)
No consumer anywhere passes `'trendline'`, `'barchart'`, or `'piechart'` as a format. The only in-repo references are the generator's internal grouping conditionals and one default parameter (below). Confirmed removable:

| Item | File | Notes |
|---|---|---|
| `case 'trendline':` + `case 'barchart':` in `generate_years()` | `build/class-dead.php` (~324–331) | both call `format_years_trendline()` |
| `format_years_trendline()` method | `build/class-dead.php` (~413–433) | only reached by the two cases above; the live year-bars path uses `default: $return = $years` + template-side `lwtv_stats_year_series()` |
| Any other `case 'trendline'` / `case 'barchart'` in sibling `generate_*` methods | `build/class-dead.php` (also ~234) | grep the whole file — several methods share the pattern |
| Deleted orphan partial (already done) | ~~`templates/partials/trendline.php`~~ | removed in the cleanup batch; listed for context |

## What NEEDS VERIFICATION before removal (the audit)
These are *probably* dead but sit on public-facing seams — confirm each to zero before cutting:

1. **`generate_individual_actors( $actor_id, $format = 'piechart', $type = 'roles' )`** — `class-stats-generator.php:286`, exposed via the facade (`class-statistics-optimized.php:58, 251`). The actor pages dropped Chart.js (compact server-rendered donut now). **Verify:** does any actor template / REST endpoint still call it, and with what format? If unused → the `piechart` default + the `piechart`/`barchart` conditionals at `class-stats-generator.php:239, 246` go; if still used with a live format (e.g. `array`), keep the method but drop only the dead `piechart`/`barchart` arms.
2. **`Percentage_Optimized` / `Lists_Optimized` format classes** — routed by `class-stats-handler.php` (`case 'percentage'`, `case 'list'`). Grep confirmed **no template renders `#listTable` or a percentage table** — only `#DeadCharactersTable`, which `death/list.php` renders itself (not via `Lists_Optimized`). **Verify:** whether `death/list.php` uses the handler's `list` format at all, or renders standalone. If nothing consumes `percentage`/`list` → both classes + their handler cases are dead. This is the least-certain item; treat cautiously.
3. **The handler's "kept for call-site compatibility" params** — `class-stats-handler.php:27–28` docblock notes `$custom_data` / `$bar_direction` are unused since the chart formats went. If truly unreferenced, drop them (and update every call site in the same commit).

## Verification method (do this, don't assume)
For each candidate, before deleting:
```bash
# Prove no caller passes the format / calls the method — search the WHOLE repo, not just statistics/
grep -rn "'trendline'\|'barchart'\|'piechart'" --include='*.php' .
grep -rn "generate_individual_actors\|Percentage_Optimized\|Lists_Optimized" --include='*.php' .
```
Include `plugins/lwtv-plugin/php/rest-api/`, the theme templates, `cron/`, and any block code — the facade + REST are the realistic external consumers. A hit inside a docblock/`@param` is not a caller; a hit in a template or endpoint is.

## Also clean up (doc-only, safe)
Stale `@param` docblocks across `class-dead.php` and `class-stats-generator.php` still read `Format type (array/count/percentage/piechart/barchart/trendline/list)`. Once the dead formats are gone, update these to the surviving set (`array/count/percentage/list`) so the docs stop advertising formats that no longer exist.

## Suggested task order
1. Run the verification greps; write down the confirmed-dead set (start from the CONFIRMED table, expand with anything that greps to zero).
2. Remove `class-dead.php` trendline/barchart branches + `format_years_trendline()`.
3. Resolve `generate_individual_actors` (remove or trim to live formats).
4. Resolve `Percentage_Optimized` / `Lists_Optimized` + handler cases **only if** step-1 greps prove them dead.
5. Trim the handler's unused params if unreferenced.
6. Update the stale `@param` docblocks.
7. `composer lint`; smoke-test `/statistics/death/` (all sub-views), `/statistics/actors/`, an actor page, and `/statistics/death/list/` sorting.

## Testing checklist
- [ ] `/statistics/death/` years / list / characters / stations / nations all render identically to before.
- [ ] `/statistics/death/list/` table still sorts (DeadCharactersTable untouched).
- [ ] Actor page character-statistics donut renders (if `generate_individual_actors` was touched).
- [ ] `grep -rn "'trendline'\|'barchart'\|'piechart'"` returns only intentional remnants (ideally zero).
- [ ] `composer lint` clean.

## Out of scope
- Any change to what the death/actor pages actually display.
- The show-score calculation (`class-calculations.php`) — untouched.
- Removing `jquery.tablesorter.min.js` itself — still used by `death/list`.

## Interaction with in-flight work
Follows the cleanup batch that deleted `partials/trendline.php`, pruned the tablesorter enqueue, and fixed the char-on-air / Ymd bugs. Independent of the five feature handoffs.
