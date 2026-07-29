# This Year → Dead Characters (By Date) Rebuild — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the Dead Characters **By Date** tab into a deaths-by-month graph (jump control) above a date-ordered timeline (month waypoints, per-death rows with role chips, dashed gap markers, unbroken rail, tail total), driven by a new pure transform class.

**Architecture:** All ordering/interleaving logic lives in a pure static class `LWTV\This_Year\Build\Dead_Characters`, unit-tested with the repo's pure PHPUnit harness. The template consumes it as thin markup. The graph is a CSS grid of anchors (no charting library); the JS month-filter is deferred — only markup seams ship now. The **By Show tab is not touched.**

**Tech Stack:** PHP 8.1+, WordPress 6.5+, PHPUnit 11 (pure/no-WP harness), Bootstrap 5 pills, theme SCSS with `colors.$lwtv-*` tokens.

## Global Constraints

- PHP 8.1+ minimum; passes `composer lint` (WordPress-Extra via `phpcs.xml.dist`).
- Class files named `class-*.php`; one class per file; namespace mirrors directory under `LWTV\`.
- Custom auto-escaped functions (`lwtv_plugin`, `get_symbolicon`) must NOT be wrapped in `esc_*`; keep their `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`.
- All user-facing strings i18n-ready with the `'lwtv'` text domain; translator comments on every placeholder `sprintf`/`_n`.
- Pure transform class takes/returns plain arrays only — **no WordPress functions** inside it (no `__()`, no `DAY_IN_SECONDS`; use the literal `86400`). All i18n, locale (`$GLOBALS['wp_locale']`), and `home_url()` stay in the template.
- SCSS colors must be existing `colors.$lwtv-…` tokens. **Zero net-new palette values** unless Task 8's dark-mode measurement proves one is required (then flag it).
- **Do not touch the By Show pane** (`#lwtv-ty-dc-byshow`) — it was already rebuilt. Keep the empty-state guard and the Deadliest Show derivation.
- The list stays in death-date order. No pagination. No new fonts.
- Build CSS on the pinned Node via nvm **sourced in-shell**: `export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r` then `npm run buildquick` (else webpack fails "crypto is not defined"). `node -v` must be v24.15.0.
- Do **not** commit unless explicitly asked (project owner preference). "Commit" steps are written but only run on request.

## File Structure

- **Create** `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php` — pure transforms: `normalize_date_key()`, `months()`, `longest_stretch()`, `timeline()`.
- **Create** `tests/unit/This_Year/DeadCharactersTest.php` — unit tests for all four methods.
- **Modify** `tests/bootstrap.php` — `require_once` the new class.
- **Modify** `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php` — `use` the transform; rebuild the callouts + the By Date pane (`#lwtv-ty-dc-bydate`) into graph + timeline; drop the inline month-tally + deadliest-month derivations. **By Show pane untouched.**
- **Modify** `scss/addons/_stats.scss` — new `.lwtv-ty-dc-graph*` / `.lwtv-ty-dc-timeline*` styles. Leave `.lwtv-ty-deathdate*` and `.lwtv-trend-callout*` in place.
- **Maybe modify** `scss/partials/_colors-dark.scss` — only if Task 8 measurement requires it.
- **Rebuilt (generated)** `style.css`, `style.min.css`.

Reference: design spec at `docs/superpowers/specs/2026-07-28-thisyear-dead-characters-bydate-design.md`.

Data shapes (already passed to the template):
- `$dead_by_date` — keyed by death-date string (`Y-m-d`, a few legacy `Ymd`) → list of `{ slug, name, dead, death_years, shows:[{name,url,type}] }`, `ksort`ed.
- `$dead_characters_count`, `$this_year`, `$dead_by_show` (By Show only).

---

### Task 1: `Dead_Characters` class + `normalize_date_key()`

Creates the class, registers it in the test bootstrap, and adds the date-normalization helper.

**Files:**
- Create: `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/This_Year/DeadCharactersTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Dead_Characters::normalize_date_key( string $key ): string` — trims, and converts an 8-char dashless `Ymd` to `Y-m-d`; returns any already-dashed value unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/This_Year/DeadCharactersTest.php`:

```php
<?php
/**
 * Unit tests for the Dead Characters (By Date) view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Dead_Characters;

class DeadCharactersTest extends TestCase {

	// ---- normalize_date_key(): legacy Ymd → Y-m-d. ----

	public function test_normalize_date_key_converts_legacy_ymd(): void {
		$this->assertSame( '2025-04-06', Dead_Characters::normalize_date_key( '20250406' ) );
	}

	public function test_normalize_date_key_leaves_dashed_dates(): void {
		$this->assertSame( '2025-04-06', Dead_Characters::normalize_date_key( ' 2025-04-06 ' ) );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: FAIL — `Class "LWTV\This_Year\Build\Dead_Characters" not found`.

- [ ] **Step 3: Create the class + register it**

Create `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`:

```php
<?php
/**
 * Dead Characters (By Date) view transforms for This Year.
 *
 * Pure array-in / array-out helpers that shape the death-date data into the
 * deaths-by-month graph model, the longest-stretch fact, and the ordered
 * timeline sequence. No WordPress runtime dependency — locale, i18n and
 * home_url() stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes death-date data for the Dead Characters By Date view.
 */
class Dead_Characters {

	/**
	 * Normalize a death-date key. A few legacy rows are stored dashless (Ymd);
	 * everything else is already Y-m-d.
	 *
	 * @param string $key The raw date key.
	 * @return string A Y-m-d string (best effort; unrecognized input trimmed only).
	 */
	public static function normalize_date_key( string $key ): string {
		$key = trim( $key );
		if ( false === strpos( $key, '-' ) && 8 === strlen( $key ) ) {
			return substr( $key, 0, 4 ) . '-' . substr( $key, 4, 2 ) . '-' . substr( $key, 6, 2 );
		}
		return $key;
	}
}
```

Then add to `tests/bootstrap.php` after the existing `require_once` lines:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php';
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php tests/bootstrap.php tests/unit/This_Year/DeadCharactersTest.php
git commit -m "feat(this-year): add Dead_Characters transform with date normalization"
```

---

### Task 2: `months()` — deaths-by-month graph model

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`
- Test: `tests/unit/This_Year/DeadCharactersTest.php`

**Interfaces:**
- Consumes: `normalize_date_key()`.
- Produces: `Dead_Characters::months( array $dead_by_date ): array` — an ordered Jan→Dec list of `{ num:int (1-12), count:int, peak:bool, empty:bool }`. Counts sum to the total deaths; `peak` marks the month(s) equal to the max (false for all when there are no deaths); `empty` marks zero-death months.

- [ ] **Step 1: Write the failing tests**

Append to `DeadCharactersTest.php`:

```php
	// ---- months(): 12-column model, counts sum to total. ----

	/** Build a $dead_by_date-shaped fixture: 'Y-m-d' => array of N character stubs. */
	private function deaths( array $by_date ): array {
		$out = array();
		foreach ( $by_date as $date => $n ) {
			$out[ $date ] = array_fill( 0, $n, array( 'name' => 'X', 'shows' => array( array( 'type' => 'regular' ) ) ) );
		}
		return $out;
	}

	public function test_months_tallies_twelve_columns_and_flags(): void {
		$result = Dead_Characters::months(
			$this->deaths(
				array(
					'2025-01-06' => 2,
					'2025-02-07' => 3,
					'2025-04-04' => 4,
					'2025-04-29' => 0, // no deaths recorded on a date is not a real case; treat as 0
				)
			)
		);

		$this->assertCount( 12, $result );
		$this->assertSame( 1, $result[0]['num'] );
		$this->assertSame( 12, $result[11]['num'] );

		$byNum = array_column( $result, null, 'num' );
		$this->assertSame( 4, $byNum[4]['count'] );  // April
		$this->assertTrue( $byNum[4]['peak'] );        // 4 is the max
		$this->assertFalse( $byNum[1]['peak'] );
		$this->assertTrue( $byNum[3]['empty'] );       // March: no deaths
		$this->assertSame( 0, $byNum[3]['count'] );

		$this->assertSame( 9, array_sum( array_column( $result, 'count' ) ) ); // 2+3+4
	}

	public function test_months_empty_input_has_no_peak(): void {
		$result = Dead_Characters::months( array() );
		$this->assertCount( 12, $result );
		$this->assertSame( 0, array_sum( array_column( $result, 'count' ) ) );
		$this->assertSame( array(), array_values( array_filter( $result, static fn( $m ) => $m['peak'] ) ) );
		$this->assertTrue( $result[5]['empty'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: FAIL — `Call to undefined method …::months()`.

- [ ] **Step 3: Implement `months()`**

Add to the class:

```php
	/**
	 * The 12-column deaths-by-month graph model (Jan→Dec).
	 *
	 * @param array $dead_by_date Keyed by death-date string → list of characters.
	 * @return array Ordered list of { num, count, peak, empty }.
	 */
	public static function months( array $dead_by_date ): array {
		$counts = array_fill( 1, 12, 0 );
		foreach ( $dead_by_date as $date_key => $chars ) {
			$ts = strtotime( self::normalize_date_key( (string) $date_key ) );
			if ( ! $ts ) {
				continue;
			}
			$counts[ (int) gmdate( 'n', $ts ) ] += count( (array) $chars );
		}

		$max = max( $counts );

		$months = array();
		for ( $n = 1; $n <= 12; $n++ ) {
			$months[] = array(
				'num'   => $n,
				'count' => $counts[ $n ],
				'peak'  => ( $max > 0 && $counts[ $n ] === $max ),
				'empty' => ( 0 === $counts[ $n ] ),
			);
		}
		return $months;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: PASS.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php tests/unit/This_Year/DeadCharactersTest.php
git commit -m "feat(this-year): add deaths-by-month graph model"
```

---

### Task 3: `longest_stretch()`

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`
- Test: `tests/unit/This_Year/DeadCharactersTest.php`

**Interfaces:**
- Consumes: `normalize_date_key()`.
- Produces: `Dead_Characters::longest_stretch( array $dead_by_date ): ?array` — the largest gap in days between consecutive (unique, normalized, sorted) death dates. Returns `{ days:int, from:string (Y-m-d), to:string (Y-m-d) }`, or `null` when fewer than 2 distinct dated deaths. Ties resolve to the earliest stretch. Measured only between two actual death dates.

- [ ] **Step 1: Write the failing tests**

Append to `DeadCharactersTest.php`:

```php
	// ---- longest_stretch(): largest gap between consecutive deaths. ----

	public function test_longest_stretch_picks_largest_gap(): void {
		$result = Dead_Characters::longest_stretch(
			$this->deaths(
				array(
					'2025-04-04' => 1,
					'2025-04-29' => 1,
					'2025-07-03' => 1, // 65-day gap from Apr 29
				)
			)
		);
		$this->assertSame( 65, $result['days'] );
		$this->assertSame( '2025-04-29', $result['from'] );
		$this->assertSame( '2025-07-03', $result['to'] );
	}

	public function test_longest_stretch_null_when_fewer_than_two_dates(): void {
		$this->assertNull( Dead_Characters::longest_stretch( $this->deaths( array( '2025-04-04' => 3 ) ) ) );
		$this->assertNull( Dead_Characters::longest_stretch( array() ) );
	}

	public function test_longest_stretch_ties_pick_earliest(): void {
		// Two equal 10-day gaps; the earlier one wins.
		$result = Dead_Characters::longest_stretch(
			$this->deaths(
				array(
					'2025-01-01' => 1,
					'2025-01-11' => 1, // gap 10
					'2025-01-16' => 1, // gap 5
					'2025-01-26' => 1, // gap 10
				)
			)
		);
		$this->assertSame( 10, $result['days'] );
		$this->assertSame( '2025-01-01', $result['from'] );
		$this->assertSame( '2025-01-11', $result['to'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: FAIL — `Call to undefined method …::longest_stretch()`.

- [ ] **Step 3: Implement `longest_stretch()`**

Add to the class (note: literal `86400`, not `DAY_IN_SECONDS` — the pure harness has no WP constants):

```php
	/**
	 * The longest gap, in days, between two consecutive death dates.
	 *
	 * @param array $dead_by_date Keyed by death-date string → list of characters.
	 * @return array|null { days, from, to } (ISO dates), or null if under two distinct dates.
	 */
	public static function longest_stretch( array $dead_by_date ): ?array {
		$dates = array();
		foreach ( array_keys( $dead_by_date ) as $key ) {
			$norm = self::normalize_date_key( (string) $key );
			$ts   = strtotime( $norm );
			if ( $ts ) {
				$dates[ $norm ] = $ts;
			}
		}
		if ( count( $dates ) < 2 ) {
			return null;
		}

		ksort( $dates );
		$keys = array_keys( $dates );

		$best = null;
		for ( $i = 1, $n = count( $keys ); $i < $n; $i++ ) {
			$days = (int) round( ( $dates[ $keys[ $i ] ] - $dates[ $keys[ $i - 1 ] ] ) / 86400 );
			if ( null === $best || $days > $best['days'] ) {
				$best = array(
					'days' => $days,
					'from' => $keys[ $i - 1 ],
					'to'   => $keys[ $i ],
				);
			}
		}
		return $best;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: PASS.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php tests/unit/This_Year/DeadCharactersTest.php
git commit -m "feat(this-year): add longest-stretch calculation"
```

---

### Task 4: `timeline()` — the ordered render sequence

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`
- Test: `tests/unit/This_Year/DeadCharactersTest.php`

**Interfaces:**
- Consumes: `normalize_date_key()`.
- Produces: `Dead_Characters::timeline( array $dead_by_date ): array` — an ordered list of items:
  - `{ type:'waypoint', month:int, count:int }` — emitted when the month changes; `count` = deaths that month.
  - `{ type:'gap', months:int[] }` — empty months strictly between two consecutive death-months.
  - `{ type:'death', date:string (Y-m-d), slug, name, shows:[{name,url,type}], role:string }` — one per character; same-day deaths each get one, date repeats; `role` = `shows[0]['type']` (or `''`).
  - `{ type:'tail', total:int, empty_month_count:int }` — always last; `empty_month_count` = 12 − distinct death-months.

- [ ] **Step 1: Write the failing tests**

Append to `DeadCharactersTest.php`:

```php
	// ---- timeline(): waypoints, deaths, gaps, tail in date order. ----

	private function char( string $name, string $slug, string $type, string $show = 'Show' ): array {
		return array(
			'slug'  => $slug,
			'name'  => $name,
			'shows' => array( array( 'name' => $show, 'url' => "/show/{$slug}/", 'type' => $type ) ),
		);
	}

	public function test_timeline_emits_waypoint_deaths_gap_and_tail(): void {
		$dead = array(
			'2025-04-04' => array( $this->char( 'Aa', 'aa', 'regular' ), $this->char( 'Ab', 'ab', 'guest' ) ),
			'2025-04-29' => array( $this->char( 'Ac', 'ac', 'recurring' ) ),
			'2025-07-03' => array( $this->char( 'Ba', 'ba', 'regular' ) ),
		);
		$items = Dead_Characters::timeline( $dead );
		$types = array_column( $items, 'type' );

		// April waypoint, 3 deaths, gap(May,Jun), July waypoint, 1 death, tail.
		$this->assertSame(
			array( 'waypoint', 'death', 'death', 'death', 'gap', 'waypoint', 'death', 'tail' ),
			$types
		);
		$this->assertSame( 4, $items[0]['month'] );
		$this->assertSame( 3, $items[0]['count'] );          // April total
		$this->assertSame( array( 5, 6 ), $items[4]['months'] ); // gap
		$this->assertSame( 'regular', $items[1]['role'] );    // role from shows[0]
		$this->assertSame( 4, $items[7]['total'] );
		$this->assertSame( 10, $items[7]['empty_month_count'] ); // 12 - 2 death-months
	}

	public function test_timeline_same_day_deaths_repeat_the_date(): void {
		$dead = array(
			'2025-05-10' => array( $this->char( 'Aa', 'aa', 'regular' ), $this->char( 'Ab', 'ab', 'guest' ) ),
		);
		$items = Dead_Characters::timeline( $dead );
		$deaths = array_values( array_filter( $items, static fn( $i ) => 'death' === $i['type'] ) );
		$this->assertCount( 2, $deaths );
		$this->assertSame( '2025-05-10', $deaths[0]['date'] );
		$this->assertSame( '2025-05-10', $deaths[1]['date'] );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter DeadCharactersTest`
Expected: FAIL — `Call to undefined method …::timeline()`.

- [ ] **Step 3: Implement `timeline()`**

Add to the class:

```php
	/**
	 * The ordered timeline render sequence: month waypoints, per-death rows,
	 * dashed gap markers for empty months between deaths, and a tail total.
	 *
	 * @param array $dead_by_date Keyed by death-date string → list of characters.
	 * @return array Ordered list of typed items (waypoint|gap|death|tail).
	 */
	public static function timeline( array $dead_by_date ): array {
		$rows = array();
		foreach ( $dead_by_date as $key => $chars ) {
			$ts = strtotime( self::normalize_date_key( (string) $key ) );
			if ( ! $ts ) {
				continue;
			}
			$rows[] = array(
				'ts'    => $ts,
				'date'  => gmdate( 'Y-m-d', $ts ),
				'month' => (int) gmdate( 'n', $ts ),
				'chars' => array_values( (array) $chars ),
			);
		}
		usort( $rows, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );

		$month_counts = array();
		$total        = 0;
		foreach ( $rows as $row ) {
			$count                         = count( $row['chars'] );
			$month_counts[ $row['month'] ] = ( $month_counts[ $row['month'] ] ?? 0 ) + $count;
			$total                        += $count;
		}
		$empty_month_count = 12 - count( $month_counts );

		$items      = array();
		$prev_month = null;
		foreach ( $rows as $row ) {
			if ( $row['month'] !== $prev_month ) {
				if ( null !== $prev_month && $row['month'] - $prev_month > 1 ) {
					$items[] = array(
						'type'   => 'gap',
						'months' => range( $prev_month + 1, $row['month'] - 1 ),
					);
				}
				$items[] = array(
					'type'  => 'waypoint',
					'month' => $row['month'],
					'count' => $month_counts[ $row['month'] ],
				);
				$prev_month = $row['month'];
			}

			foreach ( $row['chars'] as $char ) {
				$shows     = array_values( $char['shows'] ?? array() );
				$items[]   = array(
					'type'  => 'death',
					'date'  => $row['date'],
					'slug'  => (string) ( $char['slug'] ?? '' ),
					'name'  => (string) ( $char['name'] ?? '' ),
					'shows' => $shows,
					'role'  => $shows[0]['type'] ?? '',
				);
			}
		}

		$items[] = array(
			'type'              => 'tail',
			'total'             => $total,
			'empty_month_count' => $empty_month_count,
		);
		return $items;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit`
Expected: PASS — the whole `unit` suite green (existing + all `DeadCharactersTest`).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php tests/unit/This_Year/DeadCharactersTest.php
git commit -m "feat(this-year): add dead-characters timeline sequence"
```

---

### Task 5: Template — callouts + deaths-by-month graph

Rewrite the top derivations and the callout pair, and render the graph at the top of the By Date
pane. Verified by browser + `phpcs` (markup — no unit test).

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php`

**Interfaces:**
- Consumes: `$dead_by_date`, `$dead_characters_count`, `$this_year`, the existing Deadliest Show vars; `Dead_Characters::months()`, `::longest_stretch()`.
- Produces: the `DEADLIEST SHOW` + `LONGEST STRETCH` callouts and a `.lwtv-ty-dc-graph` card of 12 month anchors with `href="#lwtv-ty-dc-month-{n}"`.

- [ ] **Step 1: Add the `use`, transform calls, and drop the retired derivations**

Below the existing `use LWTV\This_Year\Build\Characters_On_Air;` (added by the By Show work), add:

```php
use LWTV\This_Year\Build\Dead_Characters;
```

**Delete** the inline month tally + deadliest-month block (the `$lwtv_dc_month_tally` loop and the
`$lwtv_dc_top_month_*` variables — currently the first derivation block after the empty-state
guard). **Keep** the Deadliest Show derivation (`$lwtv_dc_show_max` / `_top` / `_tiecnt` /
`_standout`). After the Deadliest Show block, add:

```php
// Deaths-by-month graph model + the longest-stretch fact (pure transforms).
$lwtv_dc_months          = Dead_Characters::months( $dead_by_date );
$lwtv_dc_stretch         = Dead_Characters::longest_stretch( $dead_by_date );

// Localized month helpers (locale is a WP concern — kept out of the transform).
$lwtv_dc_month_name = static function ( int $lwtv_dc_n ): string {
	return isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->get_month( $lwtv_dc_n ) : (string) $lwtv_dc_n;
};
$lwtv_dc_month_abbr = static function ( int $lwtv_dc_n ) use ( $lwtv_dc_month_name ): string {
	return isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->get_month_abbrev( $lwtv_dc_month_name( $lwtv_dc_n ) ) : (string) $lwtv_dc_n;
};

// Peak month + the "recorded none" list, both from the graph model.
$lwtv_dc_peak_count = 0;
$lwtv_dc_peak_nums  = array();
$lwtv_dc_empty_nums = array();
foreach ( $lwtv_dc_months as $lwtv_dc_m ) {
	if ( $lwtv_dc_m['peak'] ) {
		$lwtv_dc_peak_count = $lwtv_dc_m['count'];
		$lwtv_dc_peak_nums[] = $lwtv_dc_m['num'];
	}
	if ( $lwtv_dc_m['empty'] ) {
		$lwtv_dc_empty_nums[] = $lwtv_dc_m['num'];
	}
}

// Oxford-free "or" join of month names, capped at 3 then "the next N months".
$lwtv_dc_join_months = static function ( array $lwtv_dc_nums ) use ( $lwtv_dc_month_name ): string {
	$names = array_map( static fn( $lwtv_dc_n ) => $lwtv_dc_month_name( (int) $lwtv_dc_n ), $lwtv_dc_nums );
	if ( count( $names ) <= 1 ) {
		return implode( '', $names );
	}
	$last = array_pop( $names );
	/* translators: 1: comma-separated month names, 2: the final month name. */
	return sprintf( __( '%1$s or %2$s', 'lwtv' ), implode( ', ', $names ), $last );
};
```

- [ ] **Step 2: Replace the callout pair**

Replace the two `.lwtv-trend-callout` cards. When `$lwtv_dc_stretch` is null, render Deadliest
Show full-width and omit the stretch card:

```php
<div class="lwtv-trend-callouts<?php echo ( null === $lwtv_dc_stretch ) ? ' lwtv-trend-callouts--single' : ''; ?>">
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deadliest Show', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( $lwtv_dc_show_standout ) {
					printf(
						/* translators: 1: show name (emphasized), 2: number of that show's queer characters who died. */
						esc_html( _n( '%1$s lost %2$s queer character this year.', '%1$s lost %2$s queer characters this year.', $lwtv_dc_show_max, 'lwtv' ) ),
						'<em>' . esc_html( $lwtv_dc_show_top['name'] ) . '</em>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( number_format_i18n( $lwtv_dc_show_max ) )
					);
				} else {
					esc_html_e( 'No show stands out above the rest.', 'lwtv' );
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<?php if ( null !== $lwtv_dc_stretch ) : ?>
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Longest Stretch', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				printf(
					/* translators: 1: number of days, 2: start date, 3: end date. */
					esc_html( _n( '%1$s day passed without a death, from %2$s to %3$s.', '%1$s days passed without a death, from %2$s to %3$s.', $lwtv_dc_stretch['days'], 'lwtv' ) ),
					esc_html( number_format_i18n( $lwtv_dc_stretch['days'] ) ),
					esc_html( gmdate( 'F j', strtotime( $lwtv_dc_stretch['from'] ) ) ),
					esc_html( gmdate( 'F j', strtotime( $lwtv_dc_stretch['to'] ) ) )
				);
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<?php endif; ?>
</div>
```

- [ ] **Step 3: Render the graph at the top of the By Date pane**

Inside `<div class="tab-pane fade show active" id="lwtv-ty-dc-bydate" …>`, before the timeline
(Task 6), add the graph card:

```php
<div class="lwtv-ty-dc-graph">
	<div class="lwtv-ty-dc-graph-head">
		<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deaths by month', 'lwtv' ); ?></span>
		<span class="lwtv-ty-dc-graph-hint"><?php esc_html_e( 'Click a month to filter the timeline', 'lwtv' ); ?></span>
	</div>
	<div class="lwtv-ty-dc-bars" role="list">
		<?php
		$lwtv_dc_max = max( array_column( $lwtv_dc_months, 'count' ) );
		foreach ( $lwtv_dc_months as $lwtv_dc_col ) :
			$lwtv_dc_h = ( $lwtv_dc_max > 0 && $lwtv_dc_col['count'] > 0 )
				? max( 3, (int) round( $lwtv_dc_col['count'] / $lwtv_dc_max * 42 ) )
				: 3;
			$lwtv_dc_cls = 'lwtv-ty-dc-bar';
			if ( $lwtv_dc_col['empty'] ) {
				$lwtv_dc_cls .= ' is-empty';
			} elseif ( $lwtv_dc_col['peak'] ) {
				$lwtv_dc_cls .= ' is-peak';
			}
			?>
			<?php if ( $lwtv_dc_col['empty'] ) : ?>
				<span class="<?php echo esc_attr( $lwtv_dc_cls ); ?>" role="listitem" aria-disabled="true">
					<span class="lwtv-ty-dc-bar-count">&mdash;</span>
					<span class="lwtv-ty-dc-bar-fill" style="height:3px"></span>
					<span class="lwtv-ty-dc-bar-label"><?php echo esc_html( $lwtv_dc_month_abbr( $lwtv_dc_col['num'] ) ); ?></span>
				</span>
			<?php else : ?>
				<a class="<?php echo esc_attr( $lwtv_dc_cls ); ?>" role="listitem"
					href="#lwtv-ty-dc-month-<?php echo (int) $lwtv_dc_col['num']; ?>"
					aria-label="<?php
					printf(
						/* translators: 1: month name, 2: number of deaths that month. */
						esc_attr__( 'Jump to %1$s, %2$s deaths', 'lwtv' ),
						esc_attr( $lwtv_dc_month_name( $lwtv_dc_col['num'] ) ),
						esc_attr( number_format_i18n( $lwtv_dc_col['count'] ) )
					);
					?>">
					<span class="lwtv-ty-dc-bar-count"><?php echo esc_html( number_format_i18n( $lwtv_dc_col['count'] ) ); ?></span>
					<span class="lwtv-ty-dc-bar-fill" style="height:<?php echo (int) $lwtv_dc_h; ?>px"></span>
					<span class="lwtv-ty-dc-bar-label"><?php echo esc_html( $lwtv_dc_month_abbr( $lwtv_dc_col['num'] ) ); ?></span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<div class="lwtv-ty-dc-graph-foot">
		<span class="lwtv-ty-dc-graph-facts">
			<?php if ( $lwtv_dc_peak_count > 0 && 1 === count( $lwtv_dc_peak_nums ) ) : ?>
				<span class="lwtv-ty-dc-fact">
					<?php
					printf(
						/* translators: 1: month name, 2: number of deaths. */
						esc_html( _n( '%1$s was the deadliest month, %2$s death', '%1$s was the deadliest month, %2$s deaths', $lwtv_dc_peak_count, 'lwtv' ) ),
						'<strong>' . esc_html( $lwtv_dc_month_name( $lwtv_dc_peak_nums[0] ) ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( number_format_i18n( $lwtv_dc_peak_count ) )
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( ! empty( $lwtv_dc_empty_nums ) ) : ?>
				<span class="lwtv-ty-dc-fact">
					<?php
					printf(
						/* translators: %s: list of month names that recorded no deaths. */
						esc_html__( '%s recorded none', 'lwtv' ),
						esc_html( $lwtv_dc_join_months( $lwtv_dc_empty_nums ) )
					);
					?>
				</span>
			<?php endif; ?>
		</span>
		<span class="lwtv-ty-dc-graph-state">
			<?php
			printf(
				/* translators: %s: total number of months. */
				esc_html__( 'Showing all %s months', 'lwtv' ),
				esc_html( number_format_i18n( 12 ) )
			);
			?>
		</span>
	</div>
</div>
```

> Note: this pass ships the graph as no-JS anchor-jumps only. There is no "selected month" column
> state and no "Showing April only — clear" filtered link — those are the deferred JS layer. The
> footer state is the inert `<span>` above.

- [ ] **Step 4: Lint the PHP**

Run: `vendor/bin/phpcs plugins/lwtv-plugin/php/this-year/templates/dead-characters.php`
Expected: zero errors. Fix with `vendor/bin/phpcbf …` then re-run if needed.

- [ ] **Step 5: Verify in the browser**

Load `https://lwtv.local/this-year/2025/dead-characters/` (Chrome), By Date tab. Confirm: the old
Deadliest Month card is gone; Deadliest Show + Longest Stretch render (stretch text names the two
dates); the graph shows 12 month bars whose counts **sum to the headline**; peak month(s) darker;
empty months show em-dash + struck label and don't navigate; clicking a month jumps toward the
timeline (unstyled until Task 7). By Show tab unchanged.

- [ ] **Step 6: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/templates/dead-characters.php
git commit -m "feat(this-year): dead-characters callouts + deaths-by-month graph"
```

---

### Task 6: Template — the timeline

Replace the By Date list (`.lwtv-ty-deathdate`) with the timeline rendered from
`Dead_Characters::timeline()`.

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php`

**Interfaces:**
- Consumes: `Dead_Characters::timeline( $dead_by_date )`, the `$lwtv_dc_month_name` / `_abbr` /
  `_join_months` helpers and a role-label map.
- Produces: `.lwtv-ty-dc-timeline` with month waypoints (`id="lwtv-ty-dc-month-{n}"`), death rows
  (role chip + `data-month`), gap markers, and a tail row.

- [ ] **Step 1: Add the role-label map + build the timeline**

Immediately before the timeline markup (after the graph in the By Date pane), add:

```php
$lwtv_dc_role_labels = array(
	'regular'   => __( 'Regular', 'lwtv' ),
	'recurring' => __( 'Recurring', 'lwtv' ),
	'guest'     => __( 'Guest', 'lwtv' ),
);
$lwtv_dc_timeline = Dead_Characters::timeline( $dead_by_date );
```

- [ ] **Step 2: Replace the `.lwtv-ty-deathdate` block with the timeline**

Replace the entire old By Date list container with:

```php
<div class="lwtv-ty-dc-timeline">
	<?php foreach ( $lwtv_dc_timeline as $lwtv_dc_item ) : ?>
		<?php if ( 'waypoint' === $lwtv_dc_item['type'] ) : ?>
			<div class="lwtv-ty-dc-tl-waypoint" id="lwtv-ty-dc-month-<?php echo (int) $lwtv_dc_item['month']; ?>">
				<div class="lwtv-ty-dc-tl-gutter"><?php echo esc_html( $lwtv_dc_month_name( $lwtv_dc_item['month'] ) ); ?></div>
				<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-month"></span></div>
				<div class="lwtv-ty-dc-tl-content">
					<?php
					printf(
						/* translators: %s: number of deaths that month. */
						esc_html( _n( '%s death', '%s deaths', $lwtv_dc_item['count'], 'lwtv' ) ),
						esc_html( number_format_i18n( $lwtv_dc_item['count'] ) )
					);
					?>
				</div>
			</div>
		<?php elseif ( 'gap' === $lwtv_dc_item['type'] ) : ?>
			<div class="lwtv-ty-dc-tl-gap">
				<div class="lwtv-ty-dc-tl-gutter"></div>
				<div class="lwtv-ty-dc-tl-rail lwtv-ty-dc-tl-rail--dashed"></div>
				<div class="lwtv-ty-dc-tl-content">
					<?php
					if ( count( $lwtv_dc_item['months'] ) >= 4 ) {
						printf(
							/* translators: %s: number of consecutive months with no deaths. */
							esc_html( _n( 'No deaths for the next %s month', 'No deaths for the next %s months', count( $lwtv_dc_item['months'] ), 'lwtv' ) ),
							esc_html( number_format_i18n( count( $lwtv_dc_item['months'] ) ) )
						);
					} else {
						printf(
							/* translators: %s: a list of month names. */
							esc_html__( 'No deaths in %s', 'lwtv' ),
							esc_html( $lwtv_dc_join_months( $lwtv_dc_item['months'] ) )
						);
					}
					?>
				</div>
			</div>
		<?php elseif ( 'death' === $lwtv_dc_item['type'] ) : ?>
			<?php $lwtv_dc_ts = strtotime( $lwtv_dc_item['date'] ); ?>
			<div class="lwtv-ty-dc-tl-death" data-month="<?php echo (int) gmdate( 'n', $lwtv_dc_ts ); ?>">
				<div class="lwtv-ty-dc-tl-gutter lwtv-ty-dc-tl-date"><?php echo esc_html( gmdate( 'M j', $lwtv_dc_ts ) ); ?></div>
				<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-death"></span></div>
				<div class="lwtv-ty-dc-tl-content">
					<a class="lwtv-ty-dc-tl-name" href="<?php echo esc_url( home_url( '/character/' . $lwtv_dc_item['slug'] . '/' ) ); ?>"><?php echo esc_html( $lwtv_dc_item['name'] ); ?></a>
					<div class="lwtv-ty-dc-tl-meta">
						<em class="lwtv-ty-dc-tl-shows">
							<?php
							$lwtv_dc_show_links = array();
							foreach ( $lwtv_dc_item['shows'] as $lwtv_dc_show ) {
								$lwtv_dc_show_links[] = '<a href="' . esc_url( $lwtv_dc_show['url'] ) . '">' . esc_html( $lwtv_dc_show['name'] ) . '</a>';
							}
							echo implode( ' · ', $lwtv_dc_show_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</em>
						<?php if ( '' !== $lwtv_dc_item['role'] ) : ?>
							<span class="lwtv-ty-dc-chip">
								<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_dc_item['role'] ); ?>"></span>
								<?php echo esc_html( $lwtv_dc_role_labels[ $lwtv_dc_item['role'] ] ?? ucfirst( $lwtv_dc_item['role'] ) ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php elseif ( 'tail' === $lwtv_dc_item['type'] ) : ?>
			<div class="lwtv-ty-dc-tl-tail">
				<div class="lwtv-ty-dc-tl-gutter"></div>
				<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-tail"></span></div>
				<div class="lwtv-ty-dc-tl-content">
					<?php
					printf(
						/* translators: 1: total number of characters, 2: number of months with no deaths. */
						esc_html( _n( '%1$s character, in the order we lost them — %2$s months recorded none', '%1$s characters, in the order we lost them — %2$s months recorded none', $lwtv_dc_item['total'], 'lwtv' ) ),
						esc_html( number_format_i18n( $lwtv_dc_item['total'] ) ),
						esc_html( number_format_i18n( $lwtv_dc_item['empty_month_count'] ) )
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
```

- [ ] **Step 3: Lint the PHP**

Run: `vendor/bin/phpcs plugins/lwtv-plugin/php/this-year/templates/dead-characters.php` — zero errors (phpcbf if needed).

- [ ] **Step 4: Verify in the browser**

Reload the By Date tab. Confirm: rows in date order; month waypoints with their `{n} deaths`
lines; one row per death with character link, `·`-joined show(s), and a role chip; gap markers read
"No deaths in May or June" between the right months; the tail total is correct. Styling is
unpolished until Task 7 — check structure + copy. Click a graph month → it jumps to that month's
waypoint.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/templates/dead-characters.php
git commit -m "feat(this-year): dead-characters timeline markup"
```

---

### Task 7: SCSS — graph + timeline

Style everything from Tasks 5–6. Values from the design spec's tokens section.

**Files:**
- Modify: `scss/addons/_stats.scss`
- Rebuilt: `style.css`, `style.min.css`

**Interfaces:**
- Consumes: the classes emitted in Tasks 5–6.
- Produces: `.lwtv-ty-dc-graph*`, `.lwtv-ty-dc-bar*`, `.lwtv-ty-dc-timeline`, `.lwtv-ty-dc-tl-*`,
  `.lwtv-ty-dc-pip-*`, `.lwtv-ty-dc-chip`, `.lwtv-trend-callouts--single` styles.

- [ ] **Step 1: Add the styles**

In `scss/addons/_stats.scss`, near the existing `.lwtv-ty-deathdate*` region, add (confirm each
`colors.$lwtv-…` token exists in `scss/partials/_colors.scss` first — do NOT invent tokens or
hardcode hex):

```scss
	/* Dead Characters — single-callout row (no stretch card) */
	.lwtv-trend-callouts--single {
		grid-template-columns: 1fr;
	}

	/* Dead Characters — deaths-by-month graph */
	.lwtv-ty-dc-graph {
		border: 1px solid colors.$lwtv-grey-border;
		border-radius: 14px;
		padding: 16px 18px 14px;
		margin-bottom: 14px;
	}

	.lwtv-ty-dc-graph-head {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		margin-bottom: 12px;

		.lwtv-ty-dc-graph-hint {
			font-size: 11px;
			color: colors.$lwtv-grey-medium;
		}
	}

	.lwtv-ty-dc-bars {
		display: grid;
		grid-template-columns: repeat(12, 1fr);
		align-items: end;
		gap: 4px;
	}

	.lwtv-ty-dc-bar {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-end;
		min-width: 24px;
		text-decoration: none;
		font-variant-numeric: tabular-nums;

		.lwtv-ty-dc-bar-count {
			font-size: 10px;
			font-weight: 700;
			color: colors.$lwtv-grey-medium;
		}

		.lwtv-ty-dc-bar-fill {
			width: 100%;
			background: colors.$lwtv-pink-light;
			border-radius: 3px 3px 0 0;
			margin: 2px 0 4px;
		}

		.lwtv-ty-dc-bar-label {
			font-size: 11px;
			font-weight: 700;
		}

		&.is-peak .lwtv-ty-dc-bar-fill {
			background: colors.$lwtv-pink;
		}

		&.is-empty {
			.lwtv-ty-dc-bar-fill { background: colors.$lwtv-grey-medium; }

			.lwtv-ty-dc-bar-label {
				color: colors.$lwtv-grey-medium;
				text-decoration: line-through;
			}
		}

		&:not(.is-empty):hover .lwtv-ty-dc-bar-label { color: colors.$lwtv-purple; }
	}

	.lwtv-ty-dc-graph-foot {
		display: flex;
		justify-content: space-between;
		gap: 12px;
		margin-top: 10px;
		padding-top: 10px;
		border-top: 1px solid colors.$lwtv-grey-border;
		font-size: 11px;
		color: colors.$lwtv-grey-medium;

		.lwtv-ty-dc-fact { white-space: nowrap; }

		.lwtv-ty-dc-graph-facts { display: flex; gap: 14px; flex-wrap: wrap; }
	}

	@media (max-width: 479px) {
		.lwtv-ty-dc-bar .lwtv-ty-dc-bar-count { display: none; }
	}

	/* Dead Characters — timeline. The rail (2px column) stays unbroken top→tail. */
	.lwtv-ty-dc-timeline {
		border: 1px solid colors.$lwtv-grey-border;
		border-radius: 14px;
		padding: 6px 20px 18px;
	}

	.lwtv-ty-dc-tl-waypoint,
	.lwtv-ty-dc-tl-death,
	.lwtv-ty-dc-tl-gap,
	.lwtv-ty-dc-tl-tail {
		display: grid;
		grid-template-columns: 78px 20px 1fr;
		gap: 12px;
		align-items: start;
	}

	.lwtv-ty-dc-tl-gutter {
		text-align: right;
		font-variant-numeric: tabular-nums;
	}

	/* The rail cell draws a centred vertical line every row so it reads continuous. */
	.lwtv-ty-dc-tl-rail {
		position: relative;
		align-self: stretch;
		min-height: 44px;

		&::before {
			content: "";
			position: absolute;
			top: 0;
			bottom: 0;
			left: 50%;
			width: 1px;
			transform: translateX(-50%);
			background: colors.$lwtv-grey-border;
		}

		&.lwtv-ty-dc-tl-rail--dashed::before {
			background: none;
			border-left: 1px dashed colors.$lwtv-grey-border;
			width: 0;
		}
	}

	.lwtv-ty-dc-pip-month,
	.lwtv-ty-dc-pip-death,
	.lwtv-ty-dc-pip-tail {
		position: absolute;
		left: 50%;
		top: 6px;
		transform: translateX(-50%);
	}

	.lwtv-ty-dc-pip-month {
		width: 7px;
		height: 7px;
		border-radius: 2px;
		background: colors.$lwtv-pink;
	}

	.lwtv-ty-dc-pip-death {
		width: 9px;
		height: 9px;
		border-radius: 50%;
		background: colors.$lwtv-pink;
		box-shadow: 0 0 0 3px var(--lwtv-card-bg, #fff);
	}

	.lwtv-ty-dc-pip-tail {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: colors.$lwtv-grey-medium;
	}

	.lwtv-ty-dc-tl-waypoint {
		padding: 8px 0 2px;

		.lwtv-ty-dc-tl-gutter {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: colors.$lwtv-pink-deep;
		}

		.lwtv-ty-dc-tl-content {
			font-size: 11px;
			color: colors.$lwtv-grey-medium;
			align-self: center;
		}
	}

	.lwtv-ty-dc-tl-death {
		padding: 6px 0;

		.lwtv-ty-dc-tl-date {
			font-size: 12px;
			font-weight: 600;
			color: colors.$lwtv-grey-medium;
			padding-top: 1px;
		}

		.lwtv-ty-dc-tl-name {
			font-size: 15px;
			font-weight: 600;
			letter-spacing: -0.01em;
		}

		.lwtv-ty-dc-tl-meta {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 2px;
		}

		.lwtv-ty-dc-tl-shows {
			font-size: 12px;
			color: colors.$lwtv-grey-medium;
		}
	}

	.lwtv-ty-dc-chip {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		height: 19px;
		padding: 0 8px;
		border-radius: 6px;
		background: colors.$lwtv-grey;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: colors.$lwtv-grey-deep;
	}

	.lwtv-ty-dc-tl-gap .lwtv-ty-dc-tl-content {
		font-size: 11px;
		font-style: italic;
		color: colors.$lwtv-grey-medium;
		align-self: center;
	}

	.lwtv-ty-dc-tl-tail .lwtv-ty-dc-tl-content {
		font-size: 12px;
		color: colors.$lwtv-grey-medium;
		align-self: center;
	}

	@media (max-width: 639px) {
		.lwtv-ty-dc-tl-waypoint,
		.lwtv-ty-dc-tl-death,
		.lwtv-ty-dc-tl-gap,
		.lwtv-ty-dc-tl-tail {
			grid-template-columns: 62px 20px 1fr;
		}

		.lwtv-ty-dc-tl-death .lwtv-ty-dc-tl-name { font-size: 14px; }
	}
```

> The death pip's ring uses `var(--lwtv-card-bg, #fff)`. Grep `_stats.scss`/`_colors.scss` for an
> existing card-background custom property or token; if one exists use it, otherwise the `#fff`
> fallback is fine in light mode and Task 8 handles dark. Do not add a new palette token for it.

- [ ] **Step 2: Rebuild the theme CSS**

Run: `export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r; node -v` (must be
v24.15.0) then `npm run buildquick`. Confirm "compiled successfully" and that `style.css` changed.

- [ ] **Step 3: Lint the SCSS**

Run: `npm run lint:css` — zero errors (`npm run fix:css` for autofixable nits, then re-run).

- [ ] **Step 4: Verify in the browser (desktop + mobile)**

Reload the By Date tab. Desktop: graph bars proportional, peak darker, empty struck; timeline reads
as one continuous rail from the first waypoint through gap markers (dashed) to the tail dot — **no
breaks**; pips sit centred on the line; role chips styled. Narrow below 768px (graph keeps 12 bars,
count labels drop <480px) and below 640px (gutter narrows, rail intact). No horizontal page scroll.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add scss/addons/_stats.scss style.css style.min.css
git commit -m "style(this-year): dead-characters graph + timeline"
```

---

### Task 8: Dark mode — measure, then apply only what's needed

The handoff proposes net-new `#e86bac` (month labels) and `#6b2a4c` (bars). COA landed at zero
net-new palette; verify empirically here before adding anything.

**Files:**
- Maybe modify: `scss/partials/_colors-dark.scss`
- Rebuilt (if changed): `style.css`, `style.min.css`

**Interfaces:**
- Consumes: the classes from Tasks 5–7.
- Produces: dark-mode overrides (only as needed) so the graph, month waypoint labels, timeline
  names, and the death-pip ring are legible on the dark card.

- [ ] **Step 1: Measure in dark mode**

Load the By Date tab, switch to Dark (navbar control). Inspect, in the browser, the computed colors
vs. background for: the month bars (`.lwtv-ty-dc-bar-fill`), the waypoint labels
(`.lwtv-ty-dc-tl-waypoint .lwtv-ty-dc-tl-gutter`), the character name links
(`.lwtv-ty-dc-tl-name`), and the death-pip ring (`.lwtv-ty-dc-pip-death`). Record which, if any,
fall below AA (~4.5:1 for text). Note whether `$lwtv-pink-light` bars read on the dark card (they
did for COA).

- [ ] **Step 2: Apply the minimal fix (only where Step 1 flagged a failure)**

In `scss/partials/_colors-dark.scss`, inside the existing `@include mixins.color-mode(dark) { … }`
block, add overrides using EXISTING `colors.$lwtv-…` tokens wherever possible. Likely needs:
- month waypoint labels → a lighter pink token (e.g. `colors.$lwtv-pink-light`) if
  `$lwtv-pink-deep` fails on the dark gutter;
- the death-pip ring → the dark card background token (so the ring still reads);
- ensure timeline `.lwtv-ty-dc-tl-name` links inherit the theme's dark `$link-color`
  (`$lwtv-pink-light`), not light-mode `$lwtv-pink`.

Only if NO existing token clears AA for a given element, introduce one net-new value — and **stop
and report it to the controller/user first** with the measured ratio, rather than adding it
silently.

- [ ] **Step 3: Rebuild + lint (if any change)**

`export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use; hash -r; npm run buildquick && npm run lint:css`

- [ ] **Step 4: Verify in the browser (dark)**

Reload dark. Confirm bars, waypoint labels, names, chips, and the rail/pips are all legible; the
rail stays unbroken; the death-pip ring reads against the dark card.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "style(this-year): dead-characters dark-mode legibility"
```

---

### Task 9: Full-view verification + lint gate

No new code — a whole-view cross-check.

**Files:** none (verification only).

- [ ] **Step 1: Full unit suite** — `vendor/bin/phpunit` → all green (existing + `DeadCharactersTest`).
- [ ] **Step 2: Full lint gate** — `composer lint` and (nvm-sourced) `npm run lint:css && npm run lint:js` → all clean. Fix + re-run as needed.
- [ ] **Step 3: Data integrity (browser)** — On the By Date tab, confirm the month bars sum to the headline; the "deadliest month" caption names the peak; the "recorded none" list matches the empty months; the Longest Stretch dates match the largest gap; the tail total + empty-month count are right.
- [ ] **Step 4: Rail + accessibility** — Keyboard-tab the graph and timeline: every bar/link has a visible focus ring; empty months are skipped (aria-disabled). The rail is unbroken at both zoom levels and in dark. No horizontal scroll at mobile widths.
- [ ] **Step 5: Cross-year + edge cases** — Load a second year (e.g. `/this-year/2024/dead-characters/`) to confirm the transforms hold with different data. Confirm a year with fewer than 2 dated deaths drops the Longest Stretch card (Deadliest Show full-width) and never prints "0 days". Confirm the empty-state guard still fires for a year with zero deaths.
- [ ] **Step 6: By Show unchanged** — Switch to By Show; confirm it is visually identical to its current (COA-matched) state.
- [ ] **Step 7: Commit** *(only if asked)* — nothing unless a fix was made.

---

## Self-Review

**Spec coverage:**
- `normalize_date_key` / `months` / `longest_stretch` / `timeline` transforms → Tasks 1–4. ✓
- Deaths-by-month graph (anchor-jump, peak/empty, footer facts + inert state) → Tasks 5, 7. ✓
- Timeline (waypoints, death rows + role chip, gap markers, tail, unbroken rail) → Tasks 6, 7. ✓
- Callouts: retire Deadliest Month, keep Deadliest Show, add Longest Stretch + <2-deaths fallback → Task 5. ✓
- Deferred JS with seams (`id`, `data-month`, anchors; inert footer/tail) → Tasks 5, 6. ✓
- Dark mode measured, minimal, existing-token-first → Task 8. ✓
- By Show untouched; empty-state guard kept → Global Constraints, Tasks 5–6, verified Task 9. ✓
- Lint gate (php/css/js) + unit suite → Task 9. ✓

**Placeholder scan:** No TBD/TODO. The `var(--lwtv-card-bg, #fff)` and the dark-mode tokens are explicitly gated on a grep/measurement with a stated fallback, not blanks.

**Type consistency:** `months()` items expose `num`/`count`/`peak`/`empty` — consumed in Tasks 5 & 7. ✓ `longest_stretch()` returns `days`/`from`/`to` or null — consumed in Task 5 (null → drop card). ✓ `timeline()` items are typed `waypoint`/`gap`/`death`/`tail` with the fields each renderer reads in Task 6. ✓ Jump-target id `lwtv-ty-dc-month-{n}` is identical in the graph anchors (Task 5) and the waypoints (Task 6). ✓ Role dot reuses `.lwtv-ty-coa-role-dot .role-*` from the COA work. ✓
