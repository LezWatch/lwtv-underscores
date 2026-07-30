# Nations & Stations Overview fact-sheet — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the `_all` (Overview) view of the single-nation and single-station statistics
pages into a fact sheet — 2×2 metric tiles + a Composition panel of five 100% bars + three headline
facts — driven by a pure, unit-tested transform.

**Architecture:** A new pure transform (`LWTV\Statistics\Build\Overview_Factsheet`) owns every
non-trivial derivation (segment folding, thin-data collapse, narrative descriptor, ratios, best
year). The two `single.php` templates gather live data, call the transform, and render markup with
escaping and i18n at the edge. One new cached query (`get_bulk_top_shows`) supplies the best-scoring
show. New SCSS reuses the existing `$vibrant-toll` tile palette and adds the fact-sheet layout.

**Tech Stack:** PHP 8.1+, WordPress theme + bundled plugin, PSR-4-style autoload (namespace →
`class-*.php`), PHPUnit 11 pure-unit harness (no WP bootstrap), SCSS (`scss/addons/_stats.scss`,
`scss/partials/_colors-dark.scss`), Bootstrap 5 utility classes where they already fit.

## Global Constraints

- **PHP 8.1+**, WordPress-Extra coding standard (`composer lint` / `composer lint-fix`). Class files
  named `class-*.php`, one class per file, namespace mirrors directory under `LWTV\`.
- **Pure transforms in `build/` only:** no WordPress globals, `$wpdb`, meta reads, output, or i18n
  (`__()`, `number_format_i18n`, `esc_*`). Those live in the template.
- **Zero net-new palette values, no new fonts.** Every colour is an existing `colors.$lwtv-*` token;
  every font size in `rem`; nothing below `0.75rem` except the existing `.lwtv-toll-eyebrow`
  (`0.7rem`), matched not changed. Structural values (padding, gap, radius, height) in `px`.
- **All user-facing strings i18n-ready** with the `'lwtv'` text domain.
- **Custom auto-escaped functions** — do NOT wrap in `esc_*`: `lwtv_plugin`, `get_symbolicon`.
- **Tabular numerals** (`font-variant-numeric: tabular-nums`) on every rendered number.
- **Dark mode:** overrides in `_colors-dark.scss` via `mixins.color-mode(dark)`, nested under
  `#masthead` where the dark-switcher pattern requires it; verify both modes at ≥ WCAG AA.
- **No new interactive behaviour / no JS.** Bars are not links. `prefers-reduced-motion: reduce`
  renders final values immediately (inherited from the existing count-up).
- **Run linters before every commit:** `composer lint` (PHP), and after SCSS work `nvm use && npm run
  lint:css`. Run the unit suite with `vendor/bin/phpunit`.

---

## File Structure

- **Create** `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php` — pure
  transform, all static methods. Autoloads at runtime with no wiring
  (`LWTV\Statistics\Build\Overview_Factsheet` → this path).
- **Create** `tests/unit/Statistics/OverviewFactsheetTest.php` — pure unit suite.
- **Modify** `tests/bootstrap.php` — add one `require_once` for the new build class.
- **Modify** `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php` — add
  `get_bulk_top_shows()`.
- **Modify** `plugins/lwtv-plugin/php/statistics/templates/nations/single.php` — rebuild the `_all`
  case; gate the shared profile header to non-`_all` views.
- **Modify** `plugins/lwtv-plugin/php/statistics/templates/stations/single.php` — mirror the same.
- **Modify** `scss/addons/_stats.scss` — `.lwtv-toll--2x2`, masthead, Composition, headline-facts,
  segment colours, sub-nav wrap.
- **Modify** `scss/partials/_colors-dark.scss` — dark overrides for the new surfaces.

---

## Task 1: Composition transform core

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php`
- Test: `tests/unit/Statistics/OverviewFactsheetTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Produces:
  - `Overview_Factsheet::fold_top( array $items, int $take = 4, bool $with_tail = false ): array`
    — `$items` is `[ ['label'=>string,'count'=>int], … ]` in any order. Returns
    `[ 'segments' => [ ['label'=>string,'count'=>int,'pct'=>float], … up to $take, count>0, desc ],
    'tail' => ['count'=>int,'pct'=>float]|null, 'total'=>int ]`. `pct` is `round(count/total*100,1)`
    against the **true total** of all items. `tail` is the leftover after the taken segments, present
    only when `$with_tail` is true AND the leftover is > 0.
  - `Overview_Factsheet::finalize_bar( array $counts, bool $force_text = false ): string` — `$counts`
    a flat int list. Returns `'text'` when `$force_text` is true OR fewer than two of `$counts` are
    > 0; otherwise `'track'`. (This is the "never render a single 100% segment" guard.)
  - `Overview_Factsheet::collapse_for_chars( int $chars ): bool` — `$chars < 5`.
  - `Overview_Factsheet::collapse_for_shows( int $shows ): bool` — `$shows < 3`.

- [ ] **Step 1: Register the class in the test bootstrap**

Add after the last `require_once` line in `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php';
```

- [ ] **Step 2: Write the failing test**

Create `tests/unit/Statistics/OverviewFactsheetTest.php`:

```php
<?php
/**
 * Unit tests for the Overview fact-sheet transform: composition segment
 * folding, the single-segment / thin-data collapse guards, the narrative
 * descriptor, ratio facts, and best-year selection.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Overview_Factsheet;

class OverviewFactsheetTest extends TestCase {

	private function items( array $pairs ): array {
		$out = array();
		foreach ( $pairs as $label => $count ) {
			$out[] = array(
				'label' => $label,
				'count' => $count,
			);
		}
		return $out;
	}

	public function test_fold_top_sorts_desc_and_computes_pct(): void {
		$in  = $this->items(
			array(
				'bi'      => 26,
				'lesbian' => 40,
				'gay'     => 14,
			)
		);
		$out = Overview_Factsheet::fold_top( $in, 4, false );

		$this->assertSame( 80, $out['total'] );
		$this->assertSame( 'lesbian', $out['segments'][0]['label'] );
		$this->assertSame( 40, $out['segments'][0]['count'] );
		$this->assertSame( 50.0, $out['segments'][0]['pct'] );
		$this->assertNull( $out['tail'] );
	}

	public function test_fold_top_emits_grey_tail_only_when_requested_and_nonzero(): void {
		$in = $this->items(
			array(
				'a' => 40,
				'b' => 26,
				'c' => 14,
				'd' => 8,
				'e' => 12, // leftover after top 4
			)
		);

		$with = Overview_Factsheet::fold_top( $in, 4, true );
		$this->assertCount( 4, $with['segments'] );
		$this->assertNotNull( $with['tail'] );
		$this->assertSame( 12, $with['tail']['count'] );

		$without = Overview_Factsheet::fold_top( $in, 4, false );
		$this->assertNull( $without['tail'] );

		// Tail requested but nothing left over -> still null.
		$exact = Overview_Factsheet::fold_top( $this->items( array( 'a' => 5, 'b' => 5 ) ), 4, true );
		$this->assertNull( $exact['tail'] );
	}

	public function test_fold_top_handles_zero_total(): void {
		$out = Overview_Factsheet::fold_top( $this->items( array( 'a' => 0 ) ), 4, true );
		$this->assertSame( 0, $out['total'] );
		$this->assertSame( array(), $out['segments'] );
		$this->assertNull( $out['tail'] );
	}

	public function test_finalize_bar_collapses_single_segment_to_text(): void {
		// Two non-zero counts -> track.
		$this->assertSame( 'track', Overview_Factsheet::finalize_bar( array( 183, 31 ) ) );
		// Only one non-zero count -> would be a single 100% segment -> text.
		$this->assertSame( 'text', Overview_Factsheet::finalize_bar( array( 183, 0 ) ) );
		// External thin-data override forces text even with two non-zero counts.
		$this->assertSame( 'text', Overview_Factsheet::finalize_bar( array( 4, 1 ), true ) );
	}

	public function test_collapse_thresholds(): void {
		$this->assertTrue( Overview_Factsheet::collapse_for_chars( 4 ) );
		$this->assertFalse( Overview_Factsheet::collapse_for_chars( 5 ) );
		$this->assertTrue( Overview_Factsheet::collapse_for_shows( 2 ) );
		$this->assertFalse( Overview_Factsheet::collapse_for_shows( 3 ) );
	}
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: FAIL — class `LWTV\Statistics\Build\Overview_Factsheet` not found.

- [ ] **Step 4: Write the minimal implementation**

Create `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php`:

```php
<?php
/**
 * Overview fact-sheet view transforms for the single-nation and
 * single-station statistics pages.
 *
 * Pure array-in / array-out helpers. No WordPress runtime dependency — every
 * query, meta read, permalink, and i18n string stays in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes the fact-sheet model shared by nations/single.php and stations/single.php.
 */
class Overview_Factsheet {

	/**
	 * Fold a labelled count list into the top N segments plus an optional grey tail.
	 *
	 * @param array $items     [ ['label'=>string,'count'=>int], … ] in any order.
	 * @param int   $take      Number of ramped segments to keep before the tail.
	 * @param bool  $with_tail Emit the leftover as a grey tail segment (only when > 0).
	 * @return array [ 'segments'=>[['label','count','pct'], …], 'tail'=>['count','pct']|null, 'total'=>int ]
	 */
	public static function fold_top( array $items, int $take = 4, bool $with_tail = false ): array {
		$total = 0;
		foreach ( $items as $it ) {
			$total += (int) $it['count'];
		}

		usort( $items, static fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

		$segments = array();
		$taken    = 0;
		foreach ( $items as $it ) {
			if ( count( $segments ) >= $take || (int) $it['count'] <= 0 ) {
				break;
			}
			$count      = (int) $it['count'];
			$taken     += $count;
			$segments[] = array(
				'label' => (string) $it['label'],
				'count' => $count,
				'pct'   => ( $total > 0 ) ? round( $count / $total * 100, 1 ) : 0.0,
			);
		}

		$tail      = null;
		$remainder = $total - $taken;
		if ( $with_tail && $remainder > 0 ) {
			$tail = array(
				'count' => $remainder,
				'pct'   => ( $total > 0 ) ? round( $remainder / $total * 100, 1 ) : 0.0,
			);
		}

		return array(
			'segments' => $segments,
			'tail'     => $tail,
			'total'    => $total,
		);
	}

	/**
	 * Decide whether a bar renders as a track or a text fallback.
	 *
	 * A track needs at least two non-zero segments; anything less would be a
	 * single 100% bar, which is visually useless. $force_text carries the
	 * external thin-data rule (too few characters or shows).
	 *
	 * @param array $counts     Flat list of segment counts.
	 * @param bool  $force_text Force the text fallback regardless of counts.
	 * @return string 'track' or 'text'.
	 */
	public static function finalize_bar( array $counts, bool $force_text = false ): string {
		if ( $force_text ) {
			return 'text';
		}
		$nonzero = 0;
		foreach ( $counts as $count ) {
			if ( (int) $count > 0 ) {
				++$nonzero;
			}
		}
		return ( $nonzero >= 2 ) ? 'track' : 'text';
	}

	/**
	 * Character composition bars (sexuality, gender, alive/dead) collapse below 5 characters.
	 *
	 * @param int $chars Character count.
	 * @return bool
	 */
	public static function collapse_for_chars( int $chars ): bool {
		return $chars < 5;
	}

	/**
	 * Show composition bars (format, on-air vs total) collapse below 3 shows.
	 *
	 * @param int $shows Show count.
	 * @return bool
	 */
	public static function collapse_for_shows( int $shows ): bool {
		return $shows < 3;
	}
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: PASS (5 tests).

- [ ] **Step 6: Lint**

Run: `composer lint`
Expected: no errors on the new/changed files (fix with `composer lint-fix` if needed).

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php tests/unit/Statistics/OverviewFactsheetTest.php tests/bootstrap.php
git commit -m "feat: add Overview_Factsheet composition transform"
```

---

## Task 2: Narrative descriptor + ordinal

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php`
- Test: `tests/unit/Statistics/OverviewFactsheetTest.php`

**Interfaces:**
- Consumes: nothing from Task 1 (same class, new methods).
- Produces:
  - `Overview_Factsheet::narrative( ?int $rank, ?int $first_year, int $shows ): array` — returns a
    descriptor the template renders into an i18n sentence:
    - `[ 'mode'=>'ranked', 'rank'=>int, 'first_year'=>int ]` when `$rank` and `$first_year` are both
      non-null and `$shows >= 3`.
    - `[ 'mode'=>'since', 'shows'=>int, 'first_year'=>int ]` when `$first_year` is non-null but the
      entity is unranked or has `< 3` shows.
    - `[ 'mode'=>'bare', 'shows'=>int ]` when `$first_year` is null.
  - `Overview_Factsheet::ordinal( int $n ): string` — English ordinal (`1`→`1st`, `2`→`2nd`,
    `3`→`3rd`, `4`→`4th`, `11`/`12`/`13`→`th`, `21`→`21st`). The site is English-only (`en_US`); this
    is number formatting, not translatable copy.

- [ ] **Step 1: Write the failing test**

Append to `OverviewFactsheetTest`:

```php
	public function test_narrative_ranked_when_ranked_and_deep(): void {
		$out = Overview_Factsheet::narrative( 3, 1996, 68 );
		$this->assertSame( 'ranked', $out['mode'] );
		$this->assertSame( 3, $out['rank'] );
		$this->assertSame( 1996, $out['first_year'] );
	}

	public function test_narrative_since_when_unranked_or_thin(): void {
		$unranked = Overview_Factsheet::narrative( null, 2015, 40 );
		$this->assertSame( 'since', $unranked['mode'] );
		$this->assertSame( 2015, $unranked['first_year'] );

		$thin = Overview_Factsheet::narrative( 50, 2021, 2 );
		$this->assertSame( 'since', $thin['mode'] );
		$this->assertSame( 2, $thin['shows'] );
	}

	public function test_narrative_bare_when_no_year(): void {
		$out = Overview_Factsheet::narrative( 5, null, 4 );
		$this->assertSame( 'bare', $out['mode'] );
		$this->assertSame( 4, $out['shows'] );
	}

	public function test_ordinal(): void {
		$this->assertSame( '1st', Overview_Factsheet::ordinal( 1 ) );
		$this->assertSame( '2nd', Overview_Factsheet::ordinal( 2 ) );
		$this->assertSame( '3rd', Overview_Factsheet::ordinal( 3 ) );
		$this->assertSame( '4th', Overview_Factsheet::ordinal( 4 ) );
		$this->assertSame( '11th', Overview_Factsheet::ordinal( 11 ) );
		$this->assertSame( '12th', Overview_Factsheet::ordinal( 12 ) );
		$this->assertSame( '13th', Overview_Factsheet::ordinal( 13 ) );
		$this->assertSame( '21st', Overview_Factsheet::ordinal( 21 ) );
		$this->assertSame( '22nd', Overview_Factsheet::ordinal( 22 ) );
		$this->assertSame( '113th', Overview_Factsheet::ordinal( 113 ) );
	}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: FAIL — `narrative` / `ordinal` not defined.

- [ ] **Step 3: Write the implementation**

Add these methods to the class (before the closing brace):

```php
	/**
	 * Build the masthead narrative descriptor. The template turns this into a
	 * translated sentence — keeping the words out of the transform.
	 *
	 * @param int|null $rank       1-based rank among entities with shows, or null.
	 * @param int|null $first_year Earliest tracked show year, or null.
	 * @param int      $shows      Total shows for this entity.
	 * @return array Descriptor keyed by 'mode'.
	 */
	public static function narrative( ?int $rank, ?int $first_year, int $shows ): array {
		if ( null === $first_year ) {
			return array(
				'mode'  => 'bare',
				'shows' => $shows,
			);
		}
		if ( null !== $rank && $shows >= 3 ) {
			return array(
				'mode'       => 'ranked',
				'rank'       => $rank,
				'first_year' => $first_year,
			);
		}
		return array(
			'mode'       => 'since',
			'shows'      => $shows,
			'first_year' => $first_year,
		);
	}

	/**
	 * English ordinal suffix for a positive integer (site is en_US).
	 *
	 * @param int $n Number.
	 * @return string e.g. '3rd'.
	 */
	public static function ordinal( int $n ): string {
		$mod100 = $n % 100;
		if ( $mod100 >= 11 && $mod100 <= 13 ) {
			return $n . 'th';
		}
		switch ( $n % 10 ) {
			case 1:
				return $n . 'st';
			case 2:
				return $n . 'nd';
			case 3:
				return $n . 'rd';
			default:
				return $n . 'th';
		}
	}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: PASS.

- [ ] **Step 5: Lint & commit**

```bash
composer lint
git add plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php tests/unit/Statistics/OverviewFactsheetTest.php
git commit -m "feat: add narrative descriptor + ordinal to Overview_Factsheet"
```

---

## Task 3: Ratio facts + best year

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php`
- Test: `tests/unit/Statistics/OverviewFactsheetTest.php`

**Interfaces:**
- Produces:
  - `Overview_Factsheet::ratio( int $numerator, int $denominator ): ?float` — `round(n/d, 1)`, or
    `null` when `$denominator <= 0`. Used for both cast density (`chars / shows`) and the global
    characters-per-show average (`total_chars / total_shows`).
  - `Overview_Factsheet::death_rate( int $dead, int $chars ): ?float` — `round(dead/chars*100, 1)`,
    or `null` when `$chars <= 0`.
  - `Overview_Factsheet::best_year( array $points ): ?array` — `$points` is
    `[ ['year'=>int,'count'=>int], … ]` ascending by year. Returns the entry with the highest
    `count` (most recent year on a tie), or `null` for an empty list. Does **not** apply the
    "skip when peak is 1 or fewer than 3 shows" display rule — the template gates that.

- [ ] **Step 1: Write the failing test**

Append to `OverviewFactsheetTest`:

```php
	public function test_ratio_rounds_and_guards_zero(): void {
		$this->assertSame( 3.1, Overview_Factsheet::ratio( 214, 68 ) );
		$this->assertSame( 2.6, Overview_Factsheet::ratio( 26, 10 ) );
		$this->assertNull( Overview_Factsheet::ratio( 10, 0 ) );
	}

	public function test_death_rate_rounds_and_guards_zero(): void {
		$this->assertSame( 14.5, Overview_Factsheet::death_rate( 31, 214 ) );
		$this->assertNull( Overview_Factsheet::death_rate( 0, 0 ) );
	}

	public function test_best_year_picks_peak_most_recent_on_tie(): void {
		$points = array(
			array(
				'year'  => 2018,
				'count' => 4,
			),
			array(
				'year'  => 2020,
				'count' => 6,
			),
			array(
				'year'  => 2022,
				'count' => 6, // ties 2020; most recent wins
			),
			array(
				'year'  => 2024,
				'count' => 3,
			),
		);
		$best = Overview_Factsheet::best_year( $points );
		$this->assertSame( 2022, $best['year'] );
		$this->assertSame( 6, $best['count'] );
	}

	public function test_best_year_null_when_empty(): void {
		$this->assertNull( Overview_Factsheet::best_year( array() ) );
	}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: FAIL — `ratio` / `death_rate` / `best_year` not defined.

- [ ] **Step 3: Write the implementation**

Add to the class:

```php
	/**
	 * One-decimal ratio, or null when the denominator is not positive.
	 *
	 * @param int $numerator   Top of the ratio.
	 * @param int $denominator Bottom of the ratio.
	 * @return float|null
	 */
	public static function ratio( int $numerator, int $denominator ): ?float {
		if ( $denominator <= 0 ) {
			return null;
		}
		return round( $numerator / $denominator, 1 );
	}

	/**
	 * Percentage of characters that are dead, one decimal, or null when there
	 * are no characters.
	 *
	 * @param int $dead  Dead characters.
	 * @param int $chars Total characters.
	 * @return float|null
	 */
	public static function death_rate( int $dead, int $chars ): ?float {
		if ( $chars <= 0 ) {
			return null;
		}
		return round( $dead / $chars * 100, 1 );
	}

	/**
	 * Peak of a per-year on-air series. Most recent year wins a tie.
	 *
	 * @param array $points [ ['year'=>int,'count'=>int], … ] ascending by year.
	 * @return array|null ['year'=>int,'count'=>int] or null for an empty list.
	 */
	public static function best_year( array $points ): ?array {
		$best = null;
		foreach ( $points as $point ) {
			$count = (int) $point['count'];
			// >= lets a later equal year overwrite an earlier one (points ascend).
			if ( null === $best || $count >= $best['count'] ) {
				$best = array(
					'year'  => (int) $point['year'],
					'count' => $count,
				);
			}
		}
		return $best;
	}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter OverviewFactsheet`
Expected: PASS (full suite green).

- [ ] **Step 5: Lint & commit**

```bash
composer lint
git add plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php tests/unit/Statistics/OverviewFactsheetTest.php
git commit -m "feat: add ratio, death-rate, and best-year helpers to Overview_Factsheet"
```

---

## Task 4: Best-scoring-show query

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php`

**Interfaces:**
- Produces:
  - `Taxonomy_Optimized::get_bulk_top_shows( string $taxonomy, array $terms ): array` — returns
    `[ term_slug => [ 'id'=>int, 'score'=>float ] ]` for the highest-scoring published show in each
    term. Terms may carry a leading `_`; keys in the result are the `ltrim`ed slug. Cached with a
    `DAY_IN_SECONDS` transient. Empty `$terms` → `array()`.

**Note:** This is live WP glue — **not** unit-tested (it reads `$wpdb`). Verify it against the running
site in Step 3. The Overview only ever passes a single term (the current entity), so fetching that
term's scored shows and reducing in PHP is cheap and exact.

- [ ] **Step 1: Add the method**

Insert after `get_bulk_show_counts()` in `class-taxonomy-optimized.php`:

```php
	/**
	 * Highest-scoring published show per taxonomy term.
	 *
	 * Returns the winning show's ID and score for each requested term. Unlike
	 * get_bulk_show_counts() (which averages), this names a single show so the
	 * fact-sheet Overview can link to it. Reduced in PHP because the Overview
	 * only ever asks for one term at a time.
	 *
	 * @param string $taxonomy Taxonomy slug (e.g. 'lez_country', 'lez_stations').
	 * @param array  $terms    Term slugs (leading '_' tolerated).
	 * @return array [ slug => [ 'id'=>int, 'score'=>float ] ].
	 */
	public function get_bulk_top_shows( $taxonomy, $terms ) {
		if ( empty( $terms ) ) {
			return array();
		}

		$cache_key   = 'bulk_top_shows_' . $taxonomy . '_' . md5( wp_json_encode( $terms ) );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;

		$slugs             = array_map( 'sanitize_text_field', array_map( static fn( $s ) => ltrim( (string) $s, '_' ), $terms ) );
		$term_placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		$parameters        = array_merge( array( $taxonomy ), $slugs );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT t.slug AS slug, shows.ID AS id, CAST( scores.meta_value AS DECIMAL(6,2) ) AS score
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->posts} shows ON tr.object_id = shows.ID
			INNER JOIN {$wpdb->postmeta} scores ON shows.ID = scores.post_id AND scores.meta_key = 'lezshows_the_score'
			WHERE tt.taxonomy = %s
			AND shows.post_type = 'post_type_shows'
			AND shows.post_status = 'publish'
			AND scores.meta_value != ''
			AND t.slug IN ($term_placeholders)",
			$parameters
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Bulk top shows query failed: ' . $wpdb->last_error );
			return array();
		}

		$top = array();
		foreach ( $results as $row ) {
			$slug  = $row['slug'];
			$score = (float) $row['score'];
			if ( ! isset( $top[ $slug ] ) || $score > $top[ $slug ]['score'] ) {
				$top[ $slug ] = array(
					'id'    => (int) $row['id'],
					'score' => $score,
				);
			}
		}

		lwtv_plugin()->set_transient( $cache_key, $top, DAY_IN_SECONDS );

		return $top;
	}
```

- [ ] **Step 2: Lint**

Run: `composer lint`
Expected: no errors.

- [ ] **Step 3: Verify against the running site**

In the `lwtv.local` shell (see the local wp-cli setup), evaluate the method for a known nation and
confirm it names a plausible show with a 0–100 score:

```bash
wp eval '$r = ( new LWTV\Statistics\Build\Taxonomy_Optimized() )->get_bulk_top_shows( "lez_country", array( "canada" ) ); var_dump( $r["canada"] ?? null ); if ( ! empty( $r["canada"]["id"] ) ) { echo get_the_title( $r["canada"]["id"] ), PHP_EOL; }'
```

Expected: an `id` + `score` for Canada, and the printed title matches a real Canadian show. Repeat
with a `lez_stations` slug to confirm the query is taxonomy-agnostic.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php
git commit -m "feat: add get_bulk_top_shows for the best-scoring show fact"
```

---

## Task 5: Fact-sheet SCSS

**Files:**
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Produces the CSS classes the templates in Tasks 6–7 render: `.lwtv-toll--2x2`,
  `.lwtv-fact-masthead` (+ `-lead`, `-name`, `-narrative`), `.lwtv-fact-row`, `.lwtv-comp` (+ `-bar`,
  `-head`, `-label`, `-summary`, `-track`, `-seg`, `-seg--{teal,amber,green,rose,grey}`, `-rule`,
  `-text`), `.lwtv-facts` (+ `.lwtv-fact`, `-num`, `-suffix`, `-caption`, `-num--{teal,green,rose}`).

**Note:** Visual verification happens in Tasks 6–7 once markup exists. This task compiles the styles
and lints them.

- [ ] **Step 1: Add the layout + component styles**

In `scss/addons/_stats.scss`, immediately after the `.lwtv-toll-chip { … }` block (the vibrant tile
styles), add:

```scss
	// ── Overview fact sheet (single nation / station "_all" view) ───────
	// 2×2 tile block that sits beside the Composition panel. Stays 2×2 to
	// 375px — a full-width tile holding one number reads as empty, and the
	// longest eyebrow (ON AIR NOW) still fits beside the 34px chip at 375px.
	.lwtv-toll--2x2 {
		grid-template-columns: repeat(2, 1fr);
		gap: 14px;

		.lwtv-toll-tile {
			justify-content: space-between;
			height: 118px;
			padding: 15px 16px;
		}

		.lwtv-toll-chip {
			width: 34px;
			height: 34px;

			svg {
				width: 18px;
				height: 18px;
			}
		}
	}

	.lwtv-fact-masthead {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		flex-wrap: wrap;
		gap: 20px;
		padding-bottom: 16px;
		margin-bottom: 20px;
		border-bottom: 3px solid colors.$lwtv-teal-deep;
	}

	.lwtv-fact-masthead-lead {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	.lwtv-fact-masthead-name {
		font-family: fonts.$headingfontfamily;
		font-size: 2.25rem;
		font-weight: 700;
		letter-spacing: -0.02em;
		line-height: 1;
	}

	.lwtv-fact-masthead-narrative {
		max-width: 42ch;
		margin: 0;
		font-size: 0.812rem;
		text-wrap: pretty;
	}

	.lwtv-fact-row {
		display: grid;
		grid-template-columns: 400px 1fr;
		gap: 20px;
		margin-bottom: 20px;

		@media (max-width: 991px) {
			grid-template-columns: 1fr;
		}
	}

	.lwtv-fact-tiles {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 14px;
		align-content: start;

		// partials/callouts.php wraps the Best Year box in .lwtv-trend-callouts,
		// so the wrapper is the grid child that must span both tile columns.
		.lwtv-trend-callouts {
			grid-column: 1 / -1;
			margin: 0;
		}
	}

	.lwtv-comp {
		display: flex;
		flex-direction: column;
		gap: 16px;
		padding: 18px 20px;
		border: 1px solid colors.$lwtv-grey-border;
		border-radius: 14px;
	}

	.lwtv-comp-head {
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;
		gap: 2px 12px;
		margin-bottom: 5px;
		font-size: 0.75rem;
	}

	.lwtv-comp-label {
		font-weight: 600;
	}

	.lwtv-comp-track {
		display: flex;
		gap: 2px;
		height: 14px;
		border-radius: 4px;
		overflow: hidden;
	}

	.lwtv-comp-seg {
		&--teal { background-color: colors.$lwtv-teal-deep; }
		&--amber { background-color: colors.$lwtv-yellow-deep; }
		&--green { background-color: colors.$lwtv-gq-green; }
		&--rose { background-color: colors.$lwtv-crimson-deep; }
		&--grey { background-color: colors.$lwtv-grey-alt; }
	}

	.lwtv-comp-rule {
		height: 1px;
		background-color: colors.$lwtv-grey-border;
	}

	.lwtv-comp-text {
		font-size: 0.75rem;
	}

	.lwtv-facts {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 20px;
		padding-top: 18px;
		border-top: 1px solid colors.$lwtv-grey-border;

		@media (max-width: 767px) {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}

	.lwtv-fact-num {
		font-family: fonts.$headingfontfamily;
		font-size: 2.25rem;
		font-weight: 700;
		line-height: 1;
		font-variant-numeric: tabular-nums;

		&--teal { color: colors.$lwtv-teal-deep; }
		&--green { color: colors.$lwtv-gq-green; }
		&--rose { color: colors.$lwtv-crimson-deep; }
	}

	.lwtv-fact-suffix {
		font-size: 1.125rem;
		font-weight: 500;
	}

	.lwtv-fact-caption {
		margin-top: 4px;
		font-size: 0.812rem;
		text-wrap: pretty;
	}
```

If `fonts.` is not already imported at the top of `_stats.scss`, use the module alias already used
in the file for the Oswald family (check the file head — it references `fonts.font-*` mixins, so the
`fonts` namespace is available).

- [ ] **Step 2: Add the dark-mode overrides**

In `scss/partials/_colors-dark.scss`, inside the existing `mixins.color-mode(dark)` block under the
`#masthead` nesting used by the other stats dark rules, add:

```scss
			.lwtv-comp {
				border-color: rgba(colors.$white, 0.12);
			}

			.lwtv-comp-rule {
				background-color: rgba(colors.$white, 0.12);
			}

			.lwtv-comp-seg--grey {
				// $lwtv-grey-alt would be the brightest thing in the bar in dark;
				// match the donut bordergrey neutral instead.
				background-color: rgba(colors.$white, 0.22);
			}

			// Accent-text uses of the deep tokens are too dark on the dark surface.
			// The tile FILLS keep the deep tokens (handled by $vibrant-toll, no change).
			.lwtv-fact-num--teal { color: colors.$lwtv-blue-light; }
			.lwtv-fact-num--green { color: colors.$lwtv-green-light; }
			.lwtv-fact-num--rose { color: colors.$lwtv-red-light; }

			.lwtv-fact-masthead .lwtv-stats-eyebrow {
				color: colors.$lwtv-blue-light;
			}
```

Confirm `$lwtv-blue-light`, `$lwtv-green-light`, `$lwtv-red-light` are defined inside the dark
color-mode block (they are the dark locals referenced by the handoff). If the surrounding dark rules
do not nest under `#masthead`, match whatever selector depth the adjacent stats dark rules use — the
goal is that these override the light values in `_stats.scss`.

- [ ] **Step 3: Compile and lint**

```bash
nvm use
npm run lint:css
npm run buildquick
```

Expected: CSS lints clean; `style.css` / `style.min.css` rebuild with no Sass errors. (If
`lint:css` flags declaration order, run `npm run fix`.)

- [ ] **Step 4: Commit**

```bash
git add scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat: add Overview fact-sheet styles (light + dark)"
```

---

## Task 6: Rebuild the nation Overview

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/nations/single.php`

**Interfaces:**
- Consumes: `Overview_Factsheet::{fold_top, finalize_bar, collapse_for_chars, collapse_for_shows,
  narrative, ordinal, ratio, death_rate, best_year}` (Tasks 1–3), and
  `Taxonomy_Optimized::get_bulk_top_shows()` (Task 4). Existing in-scope variables:
  `$all_nations_data`, `$character_counts`, `$show_counts`, `$nation`, `$view`, and the page-shell
  `$all_shows_count` (available via the including template).

**Note:** This template is live WP glue — verified against the running site, not unit-tested. The
transform it calls is already covered.

- [ ] **Step 1: Gate the shared profile header to non-Overview views**

The profile header block (`<div class="lwtv-nation-profile lwtv-nation-profile--vibrant"> … </div>`,
lines ~99–132) currently renders for every view. The fact sheet draws its own masthead, so wrap that
block so it renders only when the view is **not** `_all`:

```php
<?php if ( '_all' !== $view ) : ?>
<div class="lwtv-nation-profile lwtv-nation-profile--vibrant">
	<!-- …existing header markup, unchanged… -->
</div>
<?php endif; ?>
```

- [ ] **Step 2: Add the imports and transform use at the top of the file**

Below the file docblock's variable list, add the `use` statements (the closure
`$lwtv_build_segments` stays — the other tabs still use it):

```php
use LWTV\Statistics\Build\Overview_Factsheet;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
```

- [ ] **Step 3: Replace the `_all` case body**

Replace everything inside `case '_all':` (from after the `case '_all':` line up to its `break;`) with
the block below. It gathers data, calls the transform, and renders the four fact-sheet blocks.

```php
		// ── Data ────────────────────────────────────────────────────────
		// Rank: reproduce the leaderboard sort (all.php) and find this nation's place.
		$lwtv_rank    = null;
		$lwtv_ranked  = array();
		foreach ( $all_nations_data as $lwtv_rslug => $lwtv_rdata ) {
			if ( (int) $lwtv_rdata['count'] > 0 ) {
				$lwtv_ranked[ $lwtv_rslug ] = (int) $lwtv_rdata['count'];
			}
		}
		arsort( $lwtv_ranked );
		$lwtv_pos = array_search( $lwtv_slug, array_keys( $lwtv_ranked ), true );
		if ( false !== $lwtv_pos ) {
			$lwtv_rank = $lwtv_pos + 1;
		}

		// First tracked year (0 => unknown => null for the transform).
		$lwtv_fy_map    = ( new Build_Taxonomy_Optimized() )->get_bulk_first_years( 'lez_country', array( $lwtv_slug ) );
		$lwtv_first_yr  = (int) ( $lwtv_fy_map[ $lwtv_slug ] ?? 0 );
		$lwtv_first_yr  = ( $lwtv_first_yr > 0 ) ? $lwtv_first_yr : null;

		// Best-scoring show.
		$lwtv_top_map   = ( new Build_Taxonomy_Optimized() )->get_bulk_top_shows( 'lez_country', array( $lwtv_slug ) );
		$lwtv_top_show  = $lwtv_top_map[ $lwtv_slug ] ?? null;

		// Global characters-per-show average (site-wide, cached upstream).
		$lwtv_g_chars   = (int) lwtv_plugin()->generate_total_counts( 'characters' );
		$lwtv_g_shows   = (int) lwtv_plugin()->generate_total_counts( 'shows' );
		$lwtv_global_av = Overview_Factsheet::ratio( $lwtv_g_chars, $lwtv_g_shows );

		// Composition inputs (same calls the donut tabs make).
		$lwtv_sex_raw   = lwtv_plugin()->generate_nation_statistics( $nation, 'sexuality', 'array' );
		$lwtv_gen_raw   = lwtv_plugin()->generate_nation_statistics( $nation, 'gender', 'array' );
		$lwtv_fmt_raw   = lwtv_plugin()->generate_nation_statistics( $nation, 'formats', 'array' );
		$lwtv_sex_raw   = is_array( $lwtv_sex_raw ) ? $lwtv_sex_raw : array();
		$lwtv_gen_raw   = is_array( $lwtv_gen_raw ) ? $lwtv_gen_raw : array();
		$lwtv_fmt_raw   = is_array( $lwtv_fmt_raw ) ? $lwtv_fmt_raw : array();

		// Derived facts.
		$lwtv_narr      = Overview_Factsheet::narrative( $lwtv_rank, $lwtv_first_yr, $lwtv_shows );
		$lwtv_density   = Overview_Factsheet::ratio( $lwtv_chars, $lwtv_shows );
		$lwtv_deathpct  = Overview_Factsheet::death_rate( $lwtv_dead, $lwtv_chars );
		$lwtv_alive     = max( 0, $lwtv_chars - $lwtv_dead );

		// Best year (reuse the on-air series the on-air tab loads).
		$lwtv_oaraw     = lwtv_plugin()->generate_nation_statistics( $nation, 'on-air', 'array' );
		$lwtv_oaraw     = is_array( $lwtv_oaraw ) ? $lwtv_oaraw : array();
		$lwtv_oapoints  = array();
		foreach ( $lwtv_oaraw as $lwtv_oa_item ) {
			$lwtv_oapoints[] = array(
				'year'  => (int) $lwtv_oa_item['name'],
				'count' => (int) $lwtv_oa_item['count'],
			);
		}
		$lwtv_best_yr = Overview_Factsheet::best_year( $lwtv_oapoints );

		// Tiles reuse the vibrant palette (unchanged from the old Overview).
		$lwtv_ov_cards = array(
			array( 'variant' => 'teal',  'label' => __( 'Shows', 'lwtv' ),      'count' => $lwtv_shows, 'svg' => 'tv.svg',               'icon' => 'svg-tv' ),
			array( 'variant' => 'amber', 'label' => __( 'On Air Now', 'lwtv' ), 'count' => $lwtv_onair, 'svg' => 'satellite-signal.svg', 'icon' => 'svg-satellite-signal' ),
			array( 'variant' => 'green', 'label' => __( 'Characters', 'lwtv' ), 'count' => $lwtv_chars, 'svg' => 'man-woman.svg',        'icon' => 'svg-man-woman' ),
			array( 'variant' => 'rose',  'label' => __( 'Dead', 'lwtv' ),       'count' => $lwtv_dead,  'svg' => 'skull.svg',            'icon' => 'svg-skull' ),
		);

		// Composition bars: [ key, label, mode, segments|text ]. Colour by index.
		$lwtv_seg_class = array( 'teal', 'amber', 'green', 'rose' );

		// Bars 1–3 (folded).
		$lwtv_sex_fold  = Overview_Factsheet::fold_top( array_map( fn( $r ) => array( 'label' => $r['name'], 'count' => (int) $r['count'] ), $lwtv_sex_raw ), 4, true );
		$lwtv_gen_fold  = Overview_Factsheet::fold_top( array_map( fn( $r ) => array( 'label' => $r['name'], 'count' => (int) $r['count'] ), $lwtv_gen_raw ), 4, false );
		$lwtv_fmt_fold  = Overview_Factsheet::fold_top( array_map( fn( $r ) => array( 'label' => $r['name'], 'count' => (int) $r['count'] ), $lwtv_fmt_raw ), 4, false );
		?>

		<!-- 1 — Masthead -->
		<div class="lwtv-fact-masthead">
			<div class="lwtv-fact-masthead-lead">
				<span class="lwtv-nation-profile-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'globe.svg', icon: 'svg-globe', max_size: '19' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Nation Profile', 'lwtv' ); ?></span>
					<h2 class="lwtv-fact-masthead-name"><?php echo esc_html( $lwtv_name ); ?></h2>
				</div>
			</div>
			<p class="lwtv-fact-masthead-narrative">
				<?php
				if ( 'ranked' === $lwtv_narr['mode'] ) {
					printf(
						/* translators: 1: ordinal rank (e.g. 3rd), 2: first tracked year. */
						esc_html__( '%1$s busiest nation on the site. Steady output since %2$s.', 'lwtv' ),
						esc_html( Overview_Factsheet::ordinal( $lwtv_narr['rank'] ) ),
						esc_html( (string) $lwtv_narr['first_year'] )
					);
				} elseif ( 'since' === $lwtv_narr['mode'] ) {
					printf(
						/* translators: 1: show count, 2: first tracked year. */
						esc_html( _n( '%1$s tracked show since %2$s.', '%1$s tracked shows since %2$s.', $lwtv_narr['shows'], 'lwtv' ) ),
						esc_html( number_format_i18n( $lwtv_narr['shows'] ) ),
						esc_html( (string) $lwtv_narr['first_year'] )
					);
				} else {
					printf(
						/* translators: %s: show count. */
						esc_html( _n( '%s tracked show.', '%s tracked shows.', $lwtv_narr['shows'], 'lwtv' ) ),
						esc_html( number_format_i18n( $lwtv_narr['shows'] ) )
					);
				}
				?>
			</p>
		</div>

		<!-- 2/3 — Tiles + Best Year callout, and Composition -->
		<div class="lwtv-fact-row">
			<div class="lwtv-toll lwtv-toll--2x2 lwtv-fact-tiles">
				<?php foreach ( $lwtv_ov_cards as $lwtv_c ) : ?>
					<div class="lwtv-toll-tile lwtv-toll-tile--<?php echo esc_attr( $lwtv_c['variant'] ); ?>">
						<div class="lwtv-toll-top">
							<span class="lwtv-toll-eyebrow"><?php echo esc_html( $lwtv_c['label'] ); ?></span>
							<span class="lwtv-toll-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_c['svg'], icon: $lwtv_c['icon'], max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</div>
						<span class="lwtv-toll-num" data-count-to="<?php echo (int) $lwtv_c['count']; ?>"><?php echo esc_html( number_format_i18n( $lwtv_c['count'] ) ); ?></span>
					</div>
				<?php endforeach; ?>

				<?php
				// Best Year callout — reuses partials/callouts.php (label/text/icon,
				// where icon is the svg filename). Skip when the peak is 1, or fewer
				// than 3 shows.
				if ( null !== $lwtv_best_yr && $lwtv_best_yr['count'] > 1 && ! Overview_Factsheet::collapse_for_shows( $lwtv_shows ) ) {
					$lwtv_callouts = array(
						array(
							'label' => __( 'Best Year', 'lwtv' ),
							'icon'  => 'fireworks.svg',
							'text'  => sprintf(
								/* translators: 1: year, 2: nation name, 3: number of shows on air. */
								_n( 'In %1$s, %2$s had %3$s show on air.', 'In %1$s, %2$s had %3$s shows on air.', $lwtv_best_yr['count'], 'lwtv' ),
								(string) $lwtv_best_yr['year'],
								$lwtv_name,
								number_format_i18n( $lwtv_best_yr['count'] )
							),
						),
					);
					// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
					include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
				}
				?>
			</div>

			<div class="lwtv-comp">
				<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Composition', 'lwtv' ); ?></span>
				<?php
				// Bar renderer: shared inline closure so all five bars format identically.
				// $segments: [ ['label','count','pct','class'], … ]; $summary_html pre-escaped.
				$lwtv_render_bar = function ( $label, $mode, $segments, $summary_html, $aria ) {
					?>
					<div>
						<div class="lwtv-comp-head">
							<span class="lwtv-comp-label"><?php echo esc_html( $label ); ?></span>
							<span class="lwtv-comp-summary"><?php echo wp_kses_post( $summary_html ); ?></span>
						</div>
						<?php if ( 'track' === $mode ) : ?>
							<div class="lwtv-comp-track" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
								<?php foreach ( $segments as $seg ) : ?>
									<span class="lwtv-comp-seg lwtv-comp-seg--<?php echo esc_attr( $seg['class'] ); ?>" style="flex:<?php echo (int) $seg['count']; ?>"></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php
				};

				// Helpers to assemble segments + summary for the folded bars (1–3).
				$lwtv_fold_segments = function ( $fold ) use ( $lwtv_seg_class ) {
					$segs = array();
					foreach ( $fold['segments'] as $i => $s ) {
						$segs[] = array(
							'label' => $s['label'],
							'count' => $s['count'],
							'pct'   => $s['pct'],
							'class' => $lwtv_seg_class[ $i ] ?? 'grey',
						);
					}
					if ( null !== $fold['tail'] ) {
						$segs[] = array(
							'label' => __( 'Other', 'lwtv' ),
							'count' => $fold['tail']['count'],
							'pct'   => $fold['tail']['pct'],
							'class' => 'grey',
						);
					}
					return $segs;
				};

				// Summary builders: pct style (top 3) vs count style (top 3).
				$lwtv_sum_pct = function ( $segs ) {
					$parts = array();
					foreach ( array_slice( $segs, 0, 3 ) as $s ) {
						$parts[] = esc_html( $s['label'] . ' ' . $s['pct'] . '%' );
					}
					return implode( ' &middot; ', $parts );
				};
				$lwtv_sum_cnt = function ( $segs ) {
					$parts = array();
					foreach ( array_slice( $segs, 0, 3 ) as $s ) {
						$parts[] = esc_html( $s['label'] . ' ' . number_format_i18n( $s['count'] ) );
					}
					return implode( ' &middot; ', $parts );
				};
				$lwtv_aria = function ( $segs ) {
					$parts = array();
					foreach ( $segs as $s ) {
						$parts[] = $s['label'] . ' ' . $s['pct'] . '%';
					}
					return implode( ', ', $parts );
				};

				// Bar 1 — Sexuality (pct, has grey tail).
				$lwtv_sex_segs = $lwtv_fold_segments( $lwtv_sex_fold );
				$lwtv_sex_mode = Overview_Factsheet::finalize_bar( array_column( $lwtv_sex_segs, 'count' ), Overview_Factsheet::collapse_for_chars( $lwtv_chars ) );
				if ( 'track' === $lwtv_sex_mode ) {
					$lwtv_render_bar( __( 'Sexuality', 'lwtv' ), 'track', $lwtv_sex_segs, $lwtv_sum_pct( $lwtv_sex_segs ), $lwtv_aria( $lwtv_sex_segs ) );
				} else {
					$lwtv_render_bar( __( 'Sexuality', 'lwtv' ), 'text', array(), $lwtv_sum_pct( $lwtv_sex_segs ), '' );
				}

				// Bar 2 — Gender (pct, no tail).
				$lwtv_gen_segs = $lwtv_fold_segments( $lwtv_gen_fold );
				$lwtv_gen_mode = Overview_Factsheet::finalize_bar( array_column( $lwtv_gen_segs, 'count' ), Overview_Factsheet::collapse_for_chars( $lwtv_chars ) );
				$lwtv_render_bar( __( 'Gender', 'lwtv' ), $lwtv_gen_mode, $lwtv_gen_segs, $lwtv_sum_pct( $lwtv_gen_segs ), $lwtv_aria( $lwtv_gen_segs ) );

				// Bar 3 — Format (counts, no tail).
				$lwtv_fmt_segs = $lwtv_fold_segments( $lwtv_fmt_fold );
				$lwtv_fmt_mode = Overview_Factsheet::finalize_bar( array_column( $lwtv_fmt_segs, 'count' ), Overview_Factsheet::collapse_for_shows( $lwtv_shows ) );
				$lwtv_render_bar( __( 'Format', 'lwtv' ), $lwtv_fmt_mode, $lwtv_fmt_segs, $lwtv_sum_cnt( $lwtv_fmt_segs ), $lwtv_aria( $lwtv_fmt_segs ) );
				?>

				<div class="lwtv-comp-rule" aria-hidden="true"></div>

				<?php
				// Bar 4 — Shows total vs on air (leads with amber on-air slice, counts).
				$lwtv_finished = max( 0, $lwtv_shows - $lwtv_onair );
				$lwtv_b4_segs  = array(
					array( 'label' => __( 'on air', 'lwtv' ),   'count' => $lwtv_onair,     'pct' => ( $lwtv_shows > 0 ) ? round( $lwtv_onair / $lwtv_shows * 100, 1 ) : 0, 'class' => 'amber' ),
					array( 'label' => __( 'finished', 'lwtv' ), 'count' => $lwtv_finished,  'pct' => ( $lwtv_shows > 0 ) ? round( $lwtv_finished / $lwtv_shows * 100, 1 ) : 0, 'class' => 'teal' ),
				);
				$lwtv_b4_mode  = Overview_Factsheet::finalize_bar( array( $lwtv_onair, $lwtv_finished ), Overview_Factsheet::collapse_for_shows( $lwtv_shows ) );
				$lwtv_render_bar( __( 'Shows total vs on air', 'lwtv' ), $lwtv_b4_mode, $lwtv_b4_segs, $lwtv_sum_cnt( $lwtv_b4_segs ), $lwtv_aria( $lwtv_b4_segs ) );

				// Bar 5 — Alive or dead (counts).
				$lwtv_b5_segs = array(
					array( 'label' => __( 'alive', 'lwtv' ), 'count' => $lwtv_alive, 'pct' => ( $lwtv_chars > 0 ) ? round( $lwtv_alive / $lwtv_chars * 100, 1 ) : 0, 'class' => 'green' ),
					array( 'label' => __( 'dead', 'lwtv' ),  'count' => $lwtv_dead,  'pct' => ( $lwtv_chars > 0 ) ? round( $lwtv_dead / $lwtv_chars * 100, 1 ) : 0, 'class' => 'rose' ),
				);
				$lwtv_b5_mode = Overview_Factsheet::finalize_bar( array( $lwtv_alive, $lwtv_dead ), Overview_Factsheet::collapse_for_chars( $lwtv_chars ) );
				$lwtv_render_bar( __( 'Alive or dead', 'lwtv' ), $lwtv_b5_mode, $lwtv_b5_segs, $lwtv_sum_cnt( $lwtv_b5_segs ), $lwtv_aria( $lwtv_b5_segs ) );
				?>
			</div>
		</div>

		<!-- 4 — Headline facts -->
		<div class="lwtv-facts">
			<?php if ( null !== $lwtv_top_show && ! empty( $lwtv_top_show['id'] ) ) : ?>
				<div class="lwtv-fact">
					<span class="lwtv-fact-num lwtv-fact-num--teal"><?php echo esc_html( number_format_i18n( round( $lwtv_top_show['score'] ) ) ); ?><span class="lwtv-fact-suffix"><?php esc_html_e( '/ 100', 'lwtv' ); ?></span></span>
					<div class="lwtv-fact-caption">
						<?php
						// Generic phrasing (no entity name) so it reads cleanly for
						// nations and networks alike — sidesteps "the United States".
						printf(
							/* translators: %s: linked show title. */
							esc_html__( 'Best-scoring show: %s', 'lwtv' ),
							'<a href="' . esc_url( (string) get_permalink( $lwtv_top_show['id'] ) ) . '">' . esc_html( get_the_title( $lwtv_top_show['id'] ) ) . '</a>'
						);
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( null !== $lwtv_density ) : ?>
				<div class="lwtv-fact">
					<span class="lwtv-fact-num lwtv-fact-num--green"><?php echo esc_html( number_format_i18n( $lwtv_density ) ); ?></span>
					<div class="lwtv-fact-caption">
						<?php
						if ( null !== $lwtv_global_av ) {
							printf(
								/* translators: %s: global average characters per show. */
								esc_html__( 'Queer characters per show, against a global average of %s', 'lwtv' ),
								esc_html( number_format_i18n( $lwtv_global_av ) )
							);
						} else {
							esc_html_e( 'Queer characters per show', 'lwtv' );
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( null !== $lwtv_deathpct ) : ?>
				<div class="lwtv-fact">
					<span class="lwtv-fact-num lwtv-fact-num--rose"><?php echo esc_html( number_format_i18n( $lwtv_deathpct ) ); ?>%</span>
					<div class="lwtv-fact-caption">
						<?php esc_html_e( 'Of its queer characters have died on screen', 'lwtv' ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		break;
```

**Note on the trend-callout include:** confirm the partial path. The spec reuses the network Best
Year callout (`.lwtv-trend-callout`). Check `templates/partials/` for the existing callout partial
name (the `_on-air` case builds `$lwtv_callouts` and the year-bars partial renders them). If there is
no standalone `partials/trend-callout.php`, render the callout inline instead using the same class:

```php
<div class="lwtv-trend-callout">
	<div>
		<span class="lwtv-stats-eyebrow"><?php echo esc_html( $callout['label'] ); ?></span>
		<p class="lwtv-trend-callout-text"><?php echo esc_html( $callout['text'] ); ?></p>
	</div>
	<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: $callout['svg'], icon: $callout['icon'], max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
</div>
```

- [ ] **Step 2: Lint**

Run: `composer lint`
Expected: no errors on `nations/single.php`.

- [ ] **Step 3: Verify against the running site (light, dark, mobile)**

Load `https://lwtv.local/statistics/nations/` and pick a large nation (e.g. Canada) so the Overview
is the default `_all` view. Confirm:
- Masthead: globe chip, "NATION PROFILE", nation name, narrative sentence, 3px teal rule.
- 2×2 tiles show Shows / On Air Now / Characters / Dead with the vibrant fills; Best Year callout
  spans both columns below them.
- Composition panel: five bars in order, 1px rule between Format and "Shows total vs on air"; bar 4
  leads with the amber on-air slice.
- Headline facts: best show (links to the show), cast density (with global-average clause), death
  rate %.
- Toggle dark mode (segmented control): panel border + rule dim, grey segment is the muted neutral,
  fact numbers switch to the light accent tokens, tile fills unchanged, ≥ AA contrast.
- Resize to 375px: tiles stay 2×2, facts stack to 1-up, composition row heads wrap (summary drops
  below the label), no horizontal scroll.
- Load a tiny nation (< 5 characters / < 3 shows, e.g. a single-show country): the thin bars render
  as text summaries, not single-segment tracks; the Best Year callout and the best-show fact are
  absent where the data doesn't support them.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/single.php
git commit -m "feat: rebuild nation Overview as a fact sheet"
```

---

## Task 7: Mirror the station Overview

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/stations/single.php`

**Interfaces:**
- Consumes: the same transform + query as Task 6. In-scope variables use the station names:
  `$all_stations_data`, `$station`, and the station's `generate_station_statistics()`.

**Deltas from Task 6 (everything else is identical):**
- Taxonomy is `'lez_stations'` (not `'lez_country'`) in both `get_bulk_first_years()` and
  `get_bulk_top_shows()`.
- Rank iterates `$all_stations_data`.
- Composition/on-air data comes from `lwtv_plugin()->generate_station_statistics( $station, …, 'array' )`.
- Masthead chip icon is `tv.svg` / `svg-tv`; eyebrow reads **"Station Profile"**.
- Narrative noun is **"network"**: the ranked string is
  `'%1$s busiest network on the site. Steady output since %2$s.'`.
- The best-show and death-rate captions are **generic** (no entity name) and therefore identical to
  the nation template — no station-specific wording needed there.

- [ ] **Step 1: Apply the same gate + rebuild with the deltas above**

Gate the shared profile header to `'_all' !== $view` (Task 6 Step 1), add the two `use` statements
(Task 6 Step 2), and replace the `_all` case body with the Task 6 block edited per the deltas. The
station `_all` case currently mirrors the nation one line-for-line, so the diff is mechanical.

- [ ] **Step 2: Lint**

Run: `composer lint`
Expected: no errors on `stations/single.php`.

- [ ] **Step 3: Verify against the running site**

Load `https://lwtv.local/statistics/stations/`, pick a large station, and run the same light / dark /
375px / thin-data checks as Task 6 Step 3, confirming the "network" narrative noun, the tv chip, and
the "Best-scoring show on {station}" caption.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/stations/single.php
git commit -m "feat: rebuild station Overview as a fact sheet"
```

---

## Task 8: Shared sub-nav wrap

**Files:**
- Modify: `scss/addons/_stats.scss` (the `.lwtv-stats-subnav` + `.lwtv-stats-subnav-item` rules near
  line 2746).

**Interfaces:** none — pure CSS change to a shared class used by every statistics page.

- [ ] **Step 1: Change the sub-nav from scroll to wrap**

In the `.lwtv-stats-subnav` rule, replace the horizontal-scroll behaviour with wrapping:

```scss
	.lwtv-stats-subnav {
		display: flex;
		flex-wrap: wrap;
		gap: 0 2px;
		border-bottom: 1px solid colors.$lwtv-grey-border;
		// (drop the previous overflow-x: auto / -webkit-overflow-scrolling rules)
	}

	.lwtv-stats-subnav-item {
		// …existing styles kept…
		white-space: nowrap; // never break a label mid-word
		// Remove the active item's `margin-bottom: -1px`: it only merges the
		// underline with the container rule on a single row, and reads the same
		// sitting on the item itself when the nav wraps to two rows.
	}
```

Read the current rules first and preserve every unrelated declaration (padding, colour, active
underline colour, hover). Only the overflow behaviour, the `flex-wrap`, and the active-item
`margin-bottom: -1px` change.

- [ ] **Step 2: Compile, lint, and check the widest sub-nav**

```bash
nvm use
npm run lint:css
npm run buildquick
```

Then load the statistics page with the **most** sub-nav items (Shows —
`https://lwtv.local/statistics/shows/`) at desktop and 375px:
- Desktop: the sub-nav sits on one row exactly as before (wrap is inert at wide widths).
- 375px: items wrap to multiple rows with no horizontal scroll; the active underline reads correctly
  on whichever row it lands.
Spot-check Nations, Stations, Characters, Actors, Death, and This Year sub-navs at 375px too.

- [ ] **Step 3: Commit**

```bash
git add scss/addons/_stats.scss style.css style.min.css
git commit -m "feat: wrap the statistics sub-nav instead of horizontal scroll"
```

---

## Self-Review

**Spec coverage:**
- Fact-sheet layout (masthead / tiles / composition / facts) → Tasks 6–7 markup + Task 5 SCSS. ✓
- Composition folding, fixed colour order, grey-tail-only-when-nonzero, "never single 100% segment",
  thin-data collapse → Task 1 transform + Task 6 rendering. ✓
- Bar 4 leads with amber on-air slice → Task 6 (`$lwtv_b4_segs` order + `finalize_bar`). ✓
- Narrative rank + first-year formula with fallbacks, unified across both views → Task 2 + Tasks 6/7.
  ✓ (Nation superlative deliberately dropped per spec.)
- Headline facts: best show (new query), cast density vs global average (clause dropped when null),
  death rate → Tasks 3, 4, 6. ✓
- Best year peak (most-recent-on-tie, skip when peak 1 / < 3 shows) → Task 3 + Task 6 gate. ✓
- Shared profile header gated to non-Overview views → Tasks 6/7 Step 1. ✓
- SCSS: `.lwtv-toll--2x2` with **no** 575px 1-up collapse, masthead, composition, facts, accent-text
  vars, dark overrides → Task 5. ✓
- Shared sub-nav wrap with Shows check → Task 8. ✓
- Accessibility: `role="img"` + `aria-label` per track; text alternative summary on every bar →
  Task 6 renderer. ✓
- Responsive breakpoints → Task 5 media queries + Tasks 6/7 verification. ✓
- Dark-mode traps (fills unchanged, accent text swapped, grey tail muted) → Task 5 Step 2. ✓
- Tests-first pure transform in the no-WP harness → Tasks 1–3. ✓
- One new cached query, no other DB cost → Task 4. ✓

**Deviations flagged for the implementer (decided during planning, not silent):**
1. **Gender summary** shows the actual top gender terms with pct (mirroring Sexuality), not the
   handoff's hand-written "cis women 85% · trans + nb 15%" rollup — that rollup needs a domain
   grouping the data model does not define ([[feedback_acf_data_model]]). If a two-bucket rollup is
   wanted, it needs its own spec'd grouping rule.
2. **Best-show / death-rate captions are generic** — "Best-scoring show: {title}" and "Of its queer
   characters have died on screen" — with no entity name. This is deliberate: demonyms ("Canadian")
   aren't derivable, and injecting the raw name creates article/possessive problems ("the United
   States's"). The entity is already named in the masthead directly above, so "its" is unambiguous.
   Fact 2 (cast density) was already name-free, so all three facts now read consistently.

**Placeholder scan:** no TBD/TODO; every code step has real code. ✓

**Type consistency:** `fold_top` returns `segments`/`tail`/`total`; templates read exactly those keys
and map `tail` → a grey segment. `finalize_bar` returns `'track'|'text'`; templates branch on those
literals. `narrative` modes `ranked|since|bare` match the template's branches. `best_year` returns
`year`/`count`; Task 6 reads both. `get_bulk_top_shows` returns `id`/`score`; Task 6 reads both. ✓

**Open item the implementer must confirm (not a blocker):** the trend-callout partial path in Task 6
Step 1 — reuse the existing partial if present, otherwise the inline fallback is provided.
