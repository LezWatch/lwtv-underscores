# Handoff: Rate-Based Stats (not just counts)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin on `feat/cliche-stats`.
**Scope:** Surface *rates* (per-capita / share) alongside the existing totals, as callouts and CSV columns. No visual framework changes — reuse `partials/callouts.php` and the CSV mechanism.

## Goal
Totals structurally favour big networks/countries ("Netflix kills the most" — because it *has* the most). Add rate metrics that reveal real signal:

1. **Death rate** — `dead ÷ characters` per nation and per network.
2. **Trope "gap"** — the ratio between a harmful trope and its counterpart (e.g. Bury Your Queers vs Happy Ending), stated as a callout.
3. (stretch) **Per-trope death likelihood** — of shows carrying trope X, what share killed a queer.

## Architecture
No new visual components. Rates are derived in the template (or a small builder helper) and rendered via the existing `$lwtv_callouts` contract + optional new CSV columns. Phrasing via `partials/phrases.php` (`lwtv_stats_ratio_phrase`, `lwtv_stats_fraction_phrase`).

## Data mapping
| Rate | Source (already exists) | Formula |
|---|---|---|
| Death rate per nation | `Build\Nations::get_nation_summaries()` → `{name, show_count, character_count, dead_count}` | `dead_count / character_count` |
| Death rate per network | `Build\Stations::get_station_summaries()` (same shape) | same |
| Trope gap | `generate_shows_statistics('array','tropes')` tally (per-term `count`) | `count(bury-your-queers) / count(happy-ending)` |

The nation/station summaries **already carry all three numbers** — no new query for rates 1–2. The trope gap reads two rows from the existing tally.

## Where it renders
- **All-nations / all-stations** (`nations/all.php`, `stations/all.php`): add a "Deadliest by rate" callout (via `partials/callouts.php`) and a **`Dead %`** column to the CSV (extend `CSV_Download::summary_rows()` + the headers in `resolve()`).
- **Single nation/station** (`single.php`, `_all` view): a rate line in the profile ("X% of {name}'s queer characters die — {above/below} the site average of Y%").
- **Shows → Tropes** (`shows/tropes.php`): a "trope gap" callout ("Shows are 2.7× more likely to bury a queer than grant a happy ending"). This is the exact stat the launch post highlights.

## ⚠️ Verify first
1. **Divide-by-zero:** guard `character_count === 0` (emit no rate, not a fatal / NaN).
2. **Small-sample noise:** a nation with 2 characters, both dead, is "100%". Any *rate leaderboard* must apply a minimum-sample threshold (e.g. ≥ 20 characters) before ranking — otherwise tiny nations dominate. Decide the floor with the owner; make it a filterable constant.
3. **Site-average baseline:** confirm a global "characters vs dead" total exists (`generate_total_counts('characters')` + `generate_total_dead()`) to compute the average the single-view line compares against.
4. **Trope term slugs:** confirm the counterpart trope's exact slug (`happy-ending`? `happy-endings`?) from the live tally before hard-coding the gap pair; make the pair a filterable array.

## Edge cases
- Nation/network with 0 deaths → "0% — none lost" (a *good* callout, keep it).
- Trope gap when the counterpart trope has 0 shows → skip the ratio, show the raw count.
- Rate leaderboard below the sample threshold → fall back to the count leaderboard we already ship.

## Testing checklist
- [ ] Death-rate callout numbers match `dead_count / character_count` from the summaries for 3 spot-checked nations.
- [ ] Rate leaderboard excludes sub-threshold subjects; toggling the threshold changes the list.
- [ ] Trope-gap callout matches the live tally ratio (currently 410 / 154 = 2.7×).
- [ ] `Dead %` CSV column present and correct; injection-safe (numbers only).
- [ ] `composer lint` clean; no divide-by-zero warnings on an empty-data year.

## Out of scope
- Per-trope death likelihood (rate 3) — that needs a trope × death join; treat as a follow-up (see the cross-CPT handoff).
- Any change to how totals are computed.

## Interaction with in-flight work
Builds entirely on the redesign's summaries + callout/CSV kit. No data-layer migration surface.
