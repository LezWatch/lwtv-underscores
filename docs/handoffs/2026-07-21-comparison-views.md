# Handoff: Comparison Views (year vs year, nation vs nation, network vs network)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin on `feat/cliche-stats`.
**Scope:** Let a reader put two subjects side by side — two years in *This Year*, or two nations / two networks in `/statistics/`. Layout + one new query var; no new data pipelines.

## Goal
The single-subject profile block from the redesign is already self-contained. Comparison = render it twice in a two-column grid and add a picker. High reader value ("how does 2019 stack up against 2024?", "UK vs US") for modest effort.

## Architecture
- Add a `compare` query var (register in `class-query-vars.php` alongside `nation`/`station`/`view`). Query param only — **no rewrite rule needed** (keep URLs like `/statistics/nations/?nation=united-kingdom&compare=united-states`).
- When `compare` is present and valid, the single-subject template resolves a **second** data set the same way it resolves the first, and wraps both profile blocks in `.lwtv-compare-grid` (new SCSS, `panels--2` style already exists — reuse it).
- *This Year* uses `?vs={year}` (its navigator already knows the valid year range 1961–current).

## Data mapping
| View | First subject | Second subject |
|---|---|---|
| Nations single | `generate_nation_statistics($nation, $view, ...)` | same call with `$compare` slug |
| Stations single | `generate_station_statistics($station, $view, ...)` | same call with `$compare` slug |
| This Year | count builders for `$this_year` (already computed) | same builders for `$vs_year` |

**No new queries** — every path reuses an existing builder call with a different argument. The redesign already computes prior-year deltas in *This Year*, proving the double-fetch is affordable (transient-cached DAY).

## Recommended phasing
1. **Phase 1 — This Year `?vs=`** (simplest; one template, no slug validation, navigator supplies years). Ship this first to prove the two-column shell.
2. **Phase 2 — Nations single.** Add the picker + slug validation.
3. **Phase 3 — Stations single** (mechanical port of Phase 2, same as the redesign's nations→stations port).

## UI
- A "Compare with…" `<select>` beside the existing subject dropdown, populated from the same option list, **auto-submitting on change** (matches the dropdown behaviour the launch post advertises). Exclude the currently-selected subject from its own compare list.
- Column headers name each subject; a thin delta column/badges between them are a nice-to-have, not required for v1.

## ⚠️ Verify first
1. **Single-view subject resolution:** read `stations/single.php` / `nations/single.php` to see exactly how the current subject slug is read and validated (`get_query_var('station')`, `ltrim($slug,'_')` gotcha noted in the Nations memory). Mirror it for `compare`.
2. **Profile block reuse:** confirm the profile markup can be included twice on one page without ID collisions (count-up `data-count-to` uses classes not IDs — check; SVG `<clipPath>`/gradient IDs must be uniquified per column or they'll cross-reference).
3. **View compatibility:** decide which sub-views support compare. `_on-air` (year-bars) and `_all` (profile) make sense; a full ranked breakdown side-by-side may overflow — start with the profile/overview view only.

## Edge cases
- `compare` equals the primary subject → ignore it, render single view.
- Invalid / non-existent `compare` slug → ignore, render single view (never fatal).
- One subject has data, the other has none for the chosen view → render the empty-state the single view already uses, per column.
- CSV: comparison is a *view* concern; the existing per-subject CSV downloads stay as-is (no "comparison CSV" in v1).

## Testing checklist
- [ ] `?vs=` on This Year renders two year columns; numbers match each year's standalone page.
- [ ] SVG gradients/clip-paths render correctly in BOTH columns (no shared-ID bleed).
- [ ] Compare picker excludes the current subject and auto-submits.
- [ ] Invalid/self compare slug degrades to single view.
- [ ] Dark mode + mobile: columns stack cleanly (`.lwtv-compare-grid` collapses to 1-col under the panels breakpoint).
- [ ] `composer lint` clean.

## Out of scope
- 3+ way comparison.
- A dedicated "/compare/" landing page (query params on existing pages only).
- Comparison CSVs.
