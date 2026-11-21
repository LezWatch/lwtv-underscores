# Handoff: Rate-Based Stats (what's left)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin.
**Scope:** Finish showing *rates* next to totals. Totals favour big networks and countries ("Netflix kills the most" because it *has* the most), so rates are where the real signal is.

## Already shipped (don't redo)

- **Trope gap callout**: Bury Your Queers vs Happy Ending, in `templates/shows/overview.php` (counts loaded in `templates/shows.php`).
- **Death rate on single nation/station pages**: `Build\Overview_Factsheet::death_rate( $dead, $chars )` (pure, null-safe for 0 characters) feeds the "Of its queer characters have died on screen" fact in `templates/nations/single.php` and `templates/stations/single.php`.

## What's left

### 1. "Deadliest by rate" on all-nations / all-stations

- **Where:** `templates/nations/all.php`, `templates/stations/all.php`, rendered through `partials/callouts.php`.
- **Data:** `Build\Nations::get_nation_summaries()` / `Build\Stations::get_station_summaries()` already return `{name, show_count, character_count, dead_count}`. No new query is needed. Compute the rate with `Overview_Factsheet::death_rate()`.
- **Minimum sample:** a nation with 2 characters, both dead, comes out at "100%". Only rank subjects with at least N characters (e.g. 20). Decide N with the owner and make it a filterable constant. If nobody clears the threshold, fall back to the count leaderboard that already ships.
- **Logic location:** the ranking and threshold are a pure transform, so put them in `build/` and write a test first (see CLAUDE.md, build → format → templates).

### 2. `Dead %` CSV column

- Add a `Dead %` column to the all-nations and all-stations exports in `class-csv-download.php` (the `generate_nation_statistics( 'all', 'all', 'array' )` and `generate_station_statistics( 'all', 'all', 'array' )` branches, with headers around lines 172 and 182).
- Numbers only (injection-safe), blank when the subject has 0 characters.

### 3. Site-average comparison on single views

- The single-view death-rate fact shows the percentage but doesn't compare it to anything. Add "{above/below} the site average of Y%", the same way the characters-per-show fact already says "against a global average of %s".
- Baseline: `generate_total_counts( 'characters' )` + `generate_total_dead()`. Confirm both exist and are cached before relying on them.
- A rate of 0 should read as a good result ("none lost"), not an empty state.

## Testing checklist

- [ ] All-nations/stations rate callout matches `dead_count / character_count` for 3 spot-checked subjects.
- [ ] Sub-threshold subjects are excluded, and changing the threshold filter changes the list.
- [ ] `Dead %` CSV column is present, correct, and numeric only.
- [ ] Single-view above/below line matches the site average computed by hand.
- [ ] No divide-by-zero warnings on empty data; `composer lint` and `vendor/bin/phpunit` clean.

## Out of scope

- Per-trope death likelihood (needs a trope × death join; see `cross-cpt-depth.md` Phase 2).
- Any change to how totals are computed.
