# Retire Dead Chart-Format Plumbing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the now-unreachable `trendline`/`barchart`/`piechart`/`percentage`/`list`-as-handler-format branches (and the classes/params that only existed to serve them) left over from the Chart.js removal, with zero behavior change on any live page.

**Architecture:** This is pure deletion, not new logic — there is nothing to TDD. Every task instead follows a **verify → remove → re-verify** cycle: confirm via `grep` that nothing in the repo calls the code with the dead value, delete it, then grep again to prove the dead string is gone (except where it survives in an unrelated, still-live context). `composer lint` and a manual smoke test replace unit tests here, matching this codebase's own rule that WP-glue code (globals, `$wpdb`, transients — which is what all the touched classes actually do, despite living under `build/`) is verified against the running site, not unit-tested.

**Tech Stack:** PHP 8.1+, WordPress-Extra phpcs, no build step, no PHPUnit changes (no existing unit coverage touches any of these classes — confirmed by grep before writing this plan).

## Global Constraints

- **Zero behavior change.** Every removal must be proven dead first — grep the *whole* repo (`plugins/`, `template-parts/`, `cron/`, `rest-api/`), not just `statistics/`. Do not delete on suspicion.
- **Do not touch:** `class-calculations.php` (show scoring), what death/actor pages actually render, or `jquery.tablesorter.min.js`'s use in `death/list.php`.
- WordPress-Extra via `phpcs.xml.dist`; run `composer lint` after every task; fix with `composer lint-fix` if it only flags formatting.
- All edits are PHP; no JS/CSS touched, so no `nvm use` / `npm run` steps needed.
- **Do not commit.** Leave all changes unstaged/uncommitted — the user reviews and commits the whole diff themselves at the end.
- Current branch at time of writing: `stats/infographic` (the original handoff assumed `feat/cliche-stats`, which is already merged — the file paths and dead-code findings below were re-verified against the actual current tree, not the handoff's estimates).

---

## Pre-flight verification already done (do not re-verify — reuse these findings)

This plan was written after auditing every candidate against the live tree. Line numbers below are exact as of this writing; if the file has changed since, re-grep before trusting a line number.

| Item | Verdict | Evidence |
|---|---|---|
| `class-dead.php` 10 `trendline`/`barchart`/`piechart` case labels + `format_years_trendline()` | DEAD | No caller anywhere passes those strings into `generate_dead`/`generate_characters`/`generate_shows`/`generate_years`/the four `format_*_results` helpers; only `array`/`average`/`time`/`count`/`percentage`/`list` are ever used for the surviving cases. |
| `generate_dead()`'s two `in_array($format, ['piechart','percentage'/'barchart'])` blocks (`class-stats-generator.php:239,246`) | DEAD | `format='percentage'` is also never passed into `generate_dead()` in practice (see `Stats_Handler` row below), so the reassignment is unreachable too. |
| `generate_individual_actors( $format = 'piechart' )` default (`class-stats-generator.php:286`) | DEAD default | Only 3 call sites repo-wide; all pass `'array'` explicitly. |
| `We_Love_It::generate()` / `Queer_IRL::generate()` / `Formats::generate()` / `Worth_It::generate()` — `case 'piechart':` + `format_piechart()` | DEAD | Every traced call chain (template → `generate_*_statistics()` → `Stats_Generator::generate_shows()`/`generate_characters()` → `Build_*::generate()`) passes literal `'array'`. No `barchart`/`trendline` cases exist in these 4 files. |
| `Stats_Handler::handle()` `case 'percentage'`/`case 'list'` → `Percentage_Optimized`/`Lists_Optimized` | DEAD given current call sites | No live call into `handle()` ever passes `format='percentage'` or `format='list'`; `death/list.php` builds `#DeadCharactersTable` standalone from raw `array`/`time` data, never touching `Lists_Optimized`'s `#listTable`. |
| `Percentage_Optimized`, `Lists_Optimized`, `Shared` (format classes) | DEAD | `class-stats-handler.php` is the only consumer of the first two repo-wide; `Shared::sort_data()` is only called from those same two classes, so it becomes fully orphaned once they're gone. |
| `Stats_Handler::handle()`'s `$custom_data`/`$bar_direction` params | DEAD end-to-end | Not referenced anywhere inside `handle()`'s own body (not just unreached branches — literally unused). Traced to the root: none of the 7 call sites in `class-stats-generator.php`, nor any of their own callers (`class-statistics-optimized.php` facade, templates, CSV download, REST API), ever supply a non-default value. |

---

### Task 1: Remove dead branches in `class-dead.php` + the two dead conditionals in `class-stats-generator.php`

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-dead.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-generator.php`

**Interfaces:** No signature changes — every touched method keeps its existing `($view, $format)` / `($format)` signature. This task only removes unreachable branches inside method bodies.

- [ ] **Step 1: Confirm dead-ness one more time (cheap insurance before deleting)**

Run:
```bash
grep -rn "'trendline'\|'barchart'\|'piechart'" --include='*.php' .
```
Expected: matches only inside `class-dead.php` (the 10 case labels below) and the docblocks that will be fixed in Task 6 — no hits in `templates/`, `rest-api/`, `cron/`, or `_components/`.

- [ ] **Step 2: `class-dead.php` — remove `case 'trendline':` fallthrough in `generate_characters()`**

In the `switch ( $view )` block of `generate_characters( $view, $format )`, change:
```php
			case 'years':
			case 'trendline':
				$return = $this->generate_years( $format );
				break;
```
to:
```php
			case 'years':
				$return = $this->generate_years( $format );
				break;
```

- [ ] **Step 3: `class-dead.php` — remove `trendline`/`barchart` cases from `generate_years()`**

Change:
```php
			switch ( $format ) {
				case 'average':
					$return = number_format( array_sum( array_column( $years, 'death_count' ) ) / count( $total_years ), 2 );
					break;
				case 'trendline':
					$return = array(
						'years' => $this->format_years_trendline( $years, $total_years ),
					);
					break;
				case 'barchart':
					$return = $this->format_years_trendline( $years, $total_years );
					break;
				case 'percentage':
					$return = array( 'death' => $this->format_years_percentage( $years, $total_years ) );
					break;
				default:
					$return = $years;
					break;
			}
```
to:
```php
			switch ( $format ) {
				case 'average':
					$return = number_format( array_sum( array_column( $years, 'death_count' ) ) / count( $total_years ), 2 );
					break;
				case 'percentage':
					$return = array( 'death' => $this->format_years_percentage( $years, $total_years ) );
					break;
				default:
					$return = $years;
					break;
			}
```

- [ ] **Step 4: `class-dead.php` — delete `format_years_trendline()` entirely**

Delete this method (including its docblock) now that Step 3 removed its only two callers:
```php
	/**
	 * Generate trendline data
	 *
	 * We need to make sure we have all years in the trendline, so we need to add 0 for
	 * years that are not in the years data.
	 *
	 * @param array $years Years data
	 * @param array $total_years Total years data
	 *
	 * @return array Trendline data
	 */
	public function format_years_trendline( $years, $total_years ) {
		// Map year => death count for lookup. Keys are cast to string so the
		// integer years from range() match the string years parsed from meta.
		$counts_by_year = array();
		foreach ( $years as $year ) {
			$counts_by_year[ (string) $year['death_year'] ] = $year['death_count'] ?? 0;
		}

		// One entry per year across the full range, zero-filled where nobody died.
		// $total_years is range( LWTV_FIRST_YEAR, current year ), so this yields a
		// single row per year — no duplicates.
		$trendline = array();
		foreach ( $total_years as $year ) {
			$trendline[] = array(
				'name'  => $year,
				'count' => $counts_by_year[ (string) $year ] ?? 0,
			);
		}

		return $trendline;
	}

```

- [ ] **Step 5: `class-dead.php` — remove `case 'piechart':`/`case 'barchart':` from `format_taxonomy_results()`**

Delete these two case blocks (keep `count`, `percentage`, `list`, `array`, `default` untouched — they are still live):
```php
			case 'piechart':
				// Return data formatted for pie charts
				$formatted = array();
				foreach ( $results as $result ) {
					$percentage  = $total_count > 0 ? round( ( $result['count'] / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'name'       => $result['term_name'],
						'count'      => (int) $result['count'],
						'percentage' => $percentage,
					);
				}
				return array( 'death' => $formatted );

			case 'barchart':
				// Return data formatted for bar charts
				$formatted = array();
				foreach ( $results as $result ) {
					$formatted[] = array(
						'name'  => $result['term_name'],
						'count' => (int) $result['count'],
					);
				}
				return $formatted;

```

- [ ] **Step 6: `class-dead.php` — remove `case 'piechart':`/`case 'barchart':` from `format_role_results()`**

Delete (keep `count`, `percentage`, `list`, `array`/`default`):
```php
			case 'piechart':
				// Return data formatted for pie charts
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$percentage  = $total_count > 0 ? round( ( $count / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'name'       => ucfirst( $role ),
						'count'      => $count,
						'percentage' => $percentage,
					);
				}
				return array( 'death' => $formatted );
			case 'barchart':
				// Return data formatted for bar charts
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$formatted[] = array(
						'name'  => ucfirst( $role ),
						'count' => $count,
					);
				}
				return $formatted;
```

- [ ] **Step 7: `class-dead.php` — remove `case 'piechart':`/`case 'barchart':` from `format_shows_by_characters_results()`**

Delete (keep `count`, `percentage`, `list`, `array`/`default`):
```php
			case 'piechart':
				// Return data formatted for pie charts
				$all_percentage  = $total_shows > 0 ? round( ( $all_dead_count / $total_shows ) * 100, 1 ) : 0;
				$some_percentage = $total_shows > 0 ? round( ( $some_dead_count / $total_shows ) * 100, 1 ) : 0;
				$no_percentage   = $total_shows > 0 ? round( ( $no_dead_count / $total_shows ) * 100, 1 ) : 0;

				return array(
					'death' => array(
						array(
							'name'       => 'All characters are dead',
							'count'      => $all_dead_count,
							'percentage' => $all_percentage,
						),
						array(
							'name'       => 'Some characters are dead',
							'count'      => $some_dead_count,
							'percentage' => $some_percentage,
						),
						array(
							'name'       => 'No characters are dead',
							'count'      => $no_dead_count,
							'percentage' => $no_percentage,
						),
					),
				);

			case 'barchart':
				// Return data formatted for bar charts
				return array(
					array(
						'name'  => 'All characters are dead',
						'count' => $all_dead_count,
					),
					array(
						'name'  => 'Some characters are dead',
						'count' => $some_dead_count,
					),
					array(
						'name'  => 'No characters are dead',
						'count' => $no_dead_count,
					),
				);

```

- [ ] **Step 8: `class-dead.php` — remove `case 'barchart':` from `format_shows_by_taxonomy_results()`**

This method has no `piechart` case (only `count`/`barchart`/`percentage`/default existed). Delete just the `barchart` block — read the method first (`grep -n "format_shows_by_taxonomy_results" -A 40 plugins/lwtv-plugin/php/statistics/build/class-dead.php`) to get its exact current body before deleting, since line numbers shift after Steps 2–7. Remove:
```php
			case 'barchart':
				foreach ( $results as $result ) {
					$formatted_results[] = array(
						'name'  => $result['term_name'],
						'count' => (int) $result['count'],
					);
				}
				break;
```
Confirm the remaining `switch` still has a `default` (or equivalent) branch producing a sane return — do not leave a dangling `switch` with a case that falls through into nothing.

- [ ] **Step 9: `class-stats-generator.php` — remove the two dead `in_array()` reassignments in `generate_dead()`**

Change:
```php
		switch ( $subject ) {
			case 'characters':
				$all_data = ( new Build_Dead() )->generate_characters( $view, $format );
				if ( in_array( $format, array( 'piechart', 'percentage' ), true ) ) {
					$context = $view;
					$view    = 'death';
				}
				break;
			case 'shows':
				$all_data = ( new Build_Dead() )->generate_shows( $view, $format );
				if ( in_array( $format, array( 'piechart', 'percentage', 'barchart' ), true ) ) {
					$context = $view;
					$view    = 'death';
				}
				$bar_direction = 'horizontal';
				break;
		}
```
to:
```php
		switch ( $subject ) {
			case 'characters':
				$all_data = ( new Build_Dead() )->generate_characters( $view, $format );
				break;
			case 'shows':
				$all_data = ( new Build_Dead() )->generate_shows( $view, $format );
				$bar_direction = 'horizontal';
				break;
		}
```

- [ ] **Step 10: Re-verify**

Run:
```bash
grep -n "'trendline'\|'barchart'\|'piechart'" plugins/lwtv-plugin/php/statistics/build/class-dead.php
grep -n "format_years_trendline" -r plugins/lwtv-plugin/php --include='*.php'
```
Expected: zero matches in `class-dead.php` for the first grep (docblocks fixed in Task 6 will still show — that's fine for now, revisit after Task 6), zero matches at all for the second.

- [ ] **Step 11: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/statistics/build/class-dead.php plugins/lwtv-plugin/php/statistics/class-stats-generator.php`
Expected: no new errors. If `phpcbf` would only reformat whitespace from the deletions, run `composer lint-fix` on just these two files.

---

### Task 2: Fix `generate_individual_actors()`'s stale `'piechart'` default

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-generator.php`

**Interfaces:** No behavior change — the method is never called without an explicit `$format` today, so this only corrects a misleading signature.

- [ ] **Step 1: Change the default**

```php
	public function generate_individual_actors( $actor_id, $format = 'piechart', $type = 'roles' ) {
```
to:
```php
	public function generate_individual_actors( $actor_id, $format = 'array', $type = 'roles' ) {
```

- [ ] **Step 2: Verify no caller relied on the old default**

Run:
```bash
grep -rn "generate_individual_actors" --include='*.php' .
```
Confirm (already true as of this plan): the only 3 call sites are `class-stats-generator.php` itself, `_components/class-statistics-optimized.php:250-251` (facade, already defaults to `'array'`), and `template-parts/overlays/statistics-actors.php:33,35` (both pass `'array'` explicitly). None depend on the `Stats_Generator`-level default.

- [ ] **Step 3: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/statistics/class-stats-generator.php`

---

### Task 3: Remove dead `'piechart'` case + `format_piechart()` from the 4 sibling build classes

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-we-love-it.php`
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-queer-irl.php`
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-formats.php`
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-worth-it.php`

**Interfaces:** No signature changes. Each file's `generate( $format = 'array' )` keeps its `count`/`percentage`/`default` cases; only `piechart` and its backing `format_piechart()` method go.

- [ ] **Step 1: `class-we-love-it.php`**

In `generate()`, change:
```php
		switch ( $format ) {
			case 'count':
				return count( $all_data );
			case 'piechart':
				return $this->format_piechart( $all_data );
			case 'percentage':
				return $this->format_percentage( $all_data );
			default:
				return $all_data;
		}
```
to:
```php
		switch ( $format ) {
			case 'count':
				return count( $all_data );
			case 'percentage':
				return $this->format_percentage( $all_data );
			default:
				return $all_data;
		}
```
Then delete the now-unused method:
```php
	/**
	 * Format piechart
	 *
	 * @param array $all_data All we love it data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		lwtv_plugin()->debug_log( 'statistics', 'Piechart data: ' . wp_json_encode( $data ) );
		return $data;
	}

```

- [ ] **Step 2: `class-queer-irl.php`**

Same switch edit (remove `case 'piechart': return $this->format_piechart( $all_data );`), then delete:
```php
	/**
	 * Format piechart
	 *
	 * @param array $all_data All queer IRL data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		return $data;
	}

```

- [ ] **Step 3: `class-formats.php`**

Same switch edit (note the parameter name here is `$all_formats_data`, not `$all_data` — remove `case 'piechart': return $this->format_piechart( $all_formats_data );`), then delete:
```php
	/**
	 * Format piechart
	 *
	 * @param array $all_formats_data All formats data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_formats_data ) {
		$data = array();
		foreach ( $all_formats_data as $format ) {
			$data[] = array(
				'name'  => $format['name'],
				'count' => $format['count'],
			);
		}
		return $data;
	}

```

- [ ] **Step 4: `class-worth-it.php`**

Same switch edit, then delete:
```php
	/**
	 * Format piechart
	 *
	 * @param array $all_data All worth it data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		lwtv_plugin()->debug_log( 'statistics', 'Piechart data: ' . wp_json_encode( $data ) );
		return $data;
	}

```

- [ ] **Step 5: Re-verify**

Run:
```bash
grep -rn "'piechart'\|format_piechart" plugins/lwtv-plugin/php/statistics/build/class-we-love-it.php plugins/lwtv-plugin/php/statistics/build/class-queer-irl.php plugins/lwtv-plugin/php/statistics/build/class-formats.php plugins/lwtv-plugin/php/statistics/build/class-worth-it.php
```
Expected: zero matches.

- [ ] **Step 6: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/statistics/build/class-we-love-it.php plugins/lwtv-plugin/php/statistics/build/class-queer-irl.php plugins/lwtv-plugin/php/statistics/build/class-formats.php plugins/lwtv-plugin/php/statistics/build/class-worth-it.php`

---

### Task 4: Delete `Percentage_Optimized`, `Lists_Optimized`, `Shared` and strip the handler's dead cases

**Files:**
- Delete: `plugins/lwtv-plugin/php/statistics/format/class-percentage-optimized.php`
- Delete: `plugins/lwtv-plugin/php/statistics/format/class-lists-optimized.php`
- Delete: `plugins/lwtv-plugin/php/statistics/format/class-shared.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-handler.php`

**Interfaces:** `Stats_Handler::handle()` keeps its full parameter list for now (that's Task 5) — only its `switch` shrinks to a single `default: return $data;` passthrough.

- [ ] **Step 1: Confirm `Shared` has no other consumer**

Run:
```bash
grep -rn "Shared::" --include='*.php' .
grep -rln "use LWTV\\\\Statistics\\\\Format\\\\Shared" --include='*.php' .
```
Expected: the only two hits are inside `class-percentage-optimized.php` and `class-lists-optimized.php`, both being deleted in this task.

- [ ] **Step 2: Delete the three format files**

```bash
rm plugins/lwtv-plugin/php/statistics/format/class-percentage-optimized.php
rm plugins/lwtv-plugin/php/statistics/format/class-lists-optimized.php
rm plugins/lwtv-plugin/php/statistics/format/class-shared.php
```

- [ ] **Step 3: Strip `class-stats-handler.php` down to the live passthrough**

Change the whole file body from:
```php
use LWTV\Statistics\Format\Percentage_Optimized;
use LWTV\Statistics\Format\Lists_Optimized;

class Stats_Handler {

	/**
	 * Handle the output of the statistics
	 *
	 * @param array $data The data to handle
	 * @param string $context The context of the data
	 * @param string $view The view of the data
	 * @param string $format The format of the data
	 * @param string $source_type The source type of the data
	 * @param array $custom_data The custom data. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @param string $bar_direction The direction of the bar chart. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @return array The handled data
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction ) {
		switch ( $format ) {
			case 'percentage':
				return ( new Percentage_Optimized() )->format( $data, $context, $view, $source_type );
			case 'list':
				if ( 'death' === $source_type ) {
					return ( new Lists_Optimized() )->format_dead_list( $data, $context, $view, $source_type );
				}
				return ( new Lists_Optimized() )->format( $data, $context, $view, $source_type );
			default:
				return $data;
		}
	}
}
```
to:
```php
class Stats_Handler {

	/**
	 * Handle the output of the statistics
	 *
	 * @param array $data The data to handle
	 * @param string $context The context of the data
	 * @param string $view The view of the data
	 * @param string $format The format of the data
	 * @param string $source_type The source type of the data
	 * @param array $custom_data The custom data. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @param string $bar_direction The direction of the bar chart. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @return array The handled data
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction ) {
		return $data;
	}
}
```
(The `$format`/`$context`/`$view`/`$source_type` params are now unused too, but leave them exactly as-is here — Task 5 removes/reshapes params for this method deliberately and separately, so don't pre-empt it.)

- [ ] **Step 4: Re-verify**

Run:
```bash
grep -rn "Percentage_Optimized\|Lists_Optimized\|Format\\\\Shared" --include='*.php' .
```
Expected: zero matches anywhere in the repo.

- [ ] **Step 5: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/statistics/class-stats-handler.php`

---

### Task 5: Drop `Stats_Handler::handle()`'s dead `$custom_data`/`$bar_direction` params end-to-end

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-handler.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-generator.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php`
- Modify: `plugins/lwtv-plugin/php/class-plugin.php`

**Interfaces:** `Stats_Handler::handle()` shrinks from 7 params to 5: `handle( $data, $context, $view, $format, $source_type )`. `Stats_Generator::generate_nations()` and `generate_stations()` shrink from 5 params to 3: `( $nation, $view = 'all', $format = 'array' )` / `( $station, $view = 'all', $format = 'array' )`. The facade methods `Statistics_Optimized::generate_nation_statistics()`/`generate_station_statistics()` shrink the same way. This is the one task in this plan that changes a method's public call shape — do it last so Tasks 1-4 aren't blocked if this one gets deferred.

- [ ] **Step 1: Re-confirm no caller ever supplies these params**

Run:
```bash
grep -rn "generate_nation_statistics\|generate_station_statistics" --include='*.php' .
```
For every call site found, confirm it passes at most 3 arguments (`$nation`/`$station`, `$view`, `$format`) — none should pass a 4th or 5th argument. (Already true as of this plan; the full list was: `class-csv-download.php:107,120,169,179`, `templates/nations.php:43`, `templates/stations.php:43`, `templates/nations/all.php:125`, `templates/stations/all.php:134`, `templates/nations/single.php` (7 sites), `templates/stations/single.php` (6 sites), `rest-api/class-stats-json.php:637`.)

- [ ] **Step 2: `class-stats-handler.php` — drop the two params**

```php
	public function handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction ) {
		return $data;
	}
```
to:
```php
	public function handle( $data, $context, $view, $format, $source_type ) {
		return $data;
	}
```
Also remove the now-inapplicable `@param $custom_data` / `@param $bar_direction` docblock lines and the `// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed` comment above the method (no longer needed once `$format`/`$context`/`$view`/`$source_type` are legitimately used... note they're currently unused too since Step 3 of Task 4 made `handle()` a pure passthrough — re-check with `phpcs` in Step 6 below whether the ignore comment is still required for the remaining params, and keep it if so).

- [ ] **Step 3: `class-stats-generator.php` — update all 7 `->handle()` call sites and the 2 methods with `$custom_data`/`$bar_direction` in their own signature**

Change each of these 7 call sites (drop the trailing two arguments):
```php
		return ( new Stats_Handler() )->handle( $data, $nation, $view, $format, 'nation', $custom_data, $bar_direction );
```
→
```php
		return ( new Stats_Handler() )->handle( $data, $nation, $view, $format, 'nation' );
```
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'shows', array(), 'horizontal' );
```
→
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'shows' );
```
```php
		return ( new Stats_Handler() )->handle( $data, $station, $view, $format, 'station', $custom_data, $bar_direction );
```
→
```php
		return ( new Stats_Handler() )->handle( $data, $station, $view, $format, 'station' );
```
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'characters', array(), 'vertical' );
```
→
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'characters' );
```
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'actors', array(), 'horizontal' );
```
→
```php
		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'actors' );
```
```php
		return ( new Stats_Handler() )->handle( $all_data, $context, $view, $format, 'death', array(), $bar_direction );
```
→
```php
		return ( new Stats_Handler() )->handle( $all_data, $context, $view, $format, 'death' );
```
```php
		return ( new Stats_Handler() )->handle( $all_data, $actor_id, $view, $format, 'actors', array(), 'horizontal' );
```
→
```php
		return ( new Stats_Handler() )->handle( $all_data, $actor_id, $view, $format, 'actors' );
```

Then update `generate_nations()`'s and `generate_stations()`'s own signatures (drop the now-orphaned params they only used to forward):
```php
	public function generate_nations( $nation, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
```
→
```php
	public function generate_nations( $nation, $view = 'all', $format = 'array' ) {
```
```php
	public function generate_stations( $station, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
```
→
```php
	public function generate_stations( $station, $view = 'all', $format = 'array' ) {
```
`generate_dead()` also declares `$bar_direction = 'vertical'` as a local variable (not a param) that only feeds its own `->handle()` call — delete that local variable declaration too:
```php
		$all_data      = array();
		$context       = 'all';
		$bar_direction = 'vertical';
```
→
```php
		$all_data = array();
		$context  = 'all';
```
and remove the now-dead `$bar_direction = 'horizontal';` reassignment inside the `case 'shows':` branch (added back in Task 1 Step 9 — remove it again here since `$bar_direction` no longer exists as a variable at all):
```php
			case 'shows':
				$all_data = ( new Build_Dead() )->generate_shows( $view, $format );
				$bar_direction = 'horizontal';
				break;
```
→
```php
			case 'shows':
				$all_data = ( new Build_Dead() )->generate_shows( $view, $format );
				break;
```

- [ ] **Step 4: `class-statistics-optimized.php` — update the two facade methods**

```php
	public function generate_station_statistics( $station, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		return ( new Stats_Generator() )->generate_stations( $station, $view, $format, $custom_data, $bar_direction );
	}
```
→
```php
	public function generate_station_statistics( $station, $view = 'all', $format = 'array' ) {
		return ( new Stats_Generator() )->generate_stations( $station, $view, $format );
	}
```
```php
	public function generate_nation_statistics( $nation, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		return ( new Stats_Generator() )->generate_nations( $nation, $view, $format, $custom_data, $bar_direction );
	}
```
→
```php
	public function generate_nation_statistics( $nation, $view = 'all', $format = 'array' ) {
		return ( new Stats_Generator() )->generate_nations( $nation, $view, $format );
	}
```
Also trim the `@param $custom_data`/`@param $bar_direction` lines from both methods' docblocks.

- [ ] **Step 5: `class-plugin.php` — update the `@method` magic-method docblock**

```php
 * @method mixed  generate_nation_statistics( $nation, $view, $format, $custom_data, $bar_direction )     \_Components\Statistics
 * @method mixed  generate_station_statistics( $station, $view, $format, $custom_data, $bar_direction )   \_Components\Statistics
```
→
```php
 * @method mixed  generate_nation_statistics( $nation, $view, $format )     \_Components\Statistics
 * @method mixed  generate_station_statistics( $station, $view, $format )   \_Components\Statistics
```

- [ ] **Step 6: Re-verify + lint**

Run:
```bash
grep -rn "custom_data\|bar_direction" plugins/lwtv-plugin/php/statistics/class-stats-handler.php plugins/lwtv-plugin/php/statistics/class-stats-generator.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php plugins/lwtv-plugin/php/class-plugin.php
```
Expected: zero matches.

```bash
composer lint -- plugins/lwtv-plugin/php/statistics/class-stats-handler.php plugins/lwtv-plugin/php/statistics/class-stats-generator.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php plugins/lwtv-plugin/php/class-plugin.php
```
Expected: no new errors (phpcs may still flag `$data`/`$context`/`$view`/`$format`/`$source_type` as unused in `handle()` since Task 4 made it a pure passthrough — that's an existing, pre-Task-5 condition, not something this task introduces; leave the `// phpcs:ignore` comment in place if lint flags it).

---

### Task 6: Update stale `@param` docblocks

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-dead.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-generator.php`

**Interfaces:** Documentation only — no executable change.

- [ ] **Step 1: `class-dead.php` — fix the 7 docblocks enumerating dead format values**

At each of these locations, change `Format type (array/count/percentage/piechart/barchart/trendline/list)` to `Format type (array/count/percentage/list)` (verify current line numbers first, since Task 1's deletions shift them — search with `grep -n "piechart/barchart/trendline" plugins/lwtv-plugin/php/statistics/build/class-dead.php`):
- `generate_characters()`'s docblock
- `generate_all()`'s docblock
- `generate_shows()`'s docblock
- `generate_years()`'s docblock
- `generate_list()`'s docblock
- `generate_stats()`'s docblock
- `generate_characters_taxonomy()`'s docblock

- [ ] **Step 2: `class-stats-generator.php` — fix the remaining stale docblocks**

`generate_dead()`'s docblock: change `Format type (array/count/percentage/piechart/barchart/trendline/list)` to `Format type (array/count/percentage/list)`.

`generate_nations()`'s and `generate_stations()`'s docblocks: change `Output format ('array', 'barchart', 'trendline', etc.)` to `Output format ('array', 'count', etc.)` (both methods already special-case `'count'` today; `barchart`/`trendline` were never real values here even before this cleanup, since these two methods route through `Stats_Handler` which never had a `barchart`/`trendline` case).

- [ ] **Step 3: Final full-repo re-verify**

Run:
```bash
grep -rn "'trendline'\|'barchart'\|'piechart'" --include='*.php' .
```
Expected: **zero matches anywhere in the repo.** This is the handoff's own final acceptance check — if anything still matches, stop and investigate before proceeding to smoke testing.

---

### Task 7: Lint + smoke test

**Files:** None (verification only).

- [ ] **Step 1: Full lint pass**

Run: `composer lint`
Expected: no errors introduced by this plan (pre-existing unrelated warnings, if any, are not this task's concern).

- [ ] **Step 2: Smoke test `/statistics/death/` — all sub-views**

Visit (on the local WP install) `/statistics/death/`, then each sub-view: years, list, characters, stations, nations. Confirm every view renders the same numbers/tables as before this plan (no blank sections, no PHP warnings/notices in `debug.log`).

- [ ] **Step 3: Smoke test `/statistics/death/list/` sorting**

Confirm `#DeadCharactersTable` still sorts by clicking column headers (tablesorter still wired up — untouched by this plan, but confirm nothing broke).

- [ ] **Step 4: Smoke test `/statistics/actors/` and a single actor page**

Visit `/statistics/actors/`, then any individual actor's overlay ("Character Statistics"). Confirm the roles/dead donut still renders with correct data (exercises `generate_individual_actors()` after Task 2's default-value fix).

- [ ] **Step 5: Smoke test the 4 sibling views**

Visit `/statistics/shows/formats/`, `/statistics/shows/we-love-it/`, `/statistics/shows/worth-it/`, `/statistics/characters/queer-irl/`. Confirm each renders identically to before (exercises Task 3's removals).

- [ ] **Step 6: Smoke test nations/stations pages**

Visit `/statistics/nations/`, `/statistics/nations/usa/` (or any populated nation), `/statistics/stations/`, `/statistics/stations/cbs/` (or any populated station). Confirm all render correctly (exercises Task 5's param removal on `generate_nations()`/`generate_stations()`).

- [ ] **Step 7: Check debug log for warnings**

Tail the site's `debug.log` (or WP debug output) while performing Steps 2–6. Confirm no new `Undefined variable`, `Too few arguments`, or similar PHP warnings appear — these would indicate a missed call site from Task 5.

---

## Self-review notes (from writing this plan)

- **Spec coverage:** every item in the original handoff's CONFIRMED table, NEEDS-VERIFICATION list, and "also clean up" section has a task. The two scope additions the user approved (4 sibling files, deleting the format classes outright) are Tasks 3 and 4. Task 5 (the handler's unused params) was explicitly flagged "lower certainty" in the handoff but is now fully confirmed dead root-to-leaf, so it's included as its own task, ordered last, so it can be skipped independently without blocking the rest.
- **Out of scope, confirmed still out of scope:** `format_taxonomy_results()`'s/`format_role_results()`'s/`format_shows_by_characters_results()`'s `'list'` cases were *not* touched — those still return real data structures independent of the dead `Lists_Optimized` handler routing, and removing them wasn't asked for or verified.
- **Known follow-up, not in this plan:** `format_shows_by_taxonomy_results()`'s `'list'`/`'array'`/`'percentage'` cases were read but not modified — only its dead `'barchart'` case (Step 8, Task 1). Same reasoning as above.
