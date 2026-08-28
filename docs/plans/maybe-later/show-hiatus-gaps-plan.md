# Plan: Off-Air Hiatus Gaps for Revival Shows

**Goal:** Stop shows with a revival gap (e.g. The X-Files, off air 2003–2015 under the
same IMDb record) from being counted as "on air" during years they weren't actually
airing. Fixes two confirmed, real bugs: (1) This Year in Review stats
(`Shows_Builder::get_shows_for_year()`) list gap-year shows as airing that year; (2)
current on-air status (`lezshows_on_air`, the on-air debugger, and the
onair/onairscore stat counters) can be wrong for a show presently mid-hiatus.

**Approach (and why):** Add one new, fully optional ACF repeater field —
`lezshows_hiatus_periods` — that lists off-air gap years. It is purely additive:

- A show with zero gap rows behaves in every way exactly as it does today.
- `lezshows_airdates_start` / `_finish` / `_seasons` keep their current meaning and
  are untouched. So are the ~12 files that read them for display, FacetWP decade
  faceting, the two REST exporters, custom columns, and the raw SQL decade joins in
  `class-taxonomy-optimized.php`. None of that surface area changes.
- Only the handful of call sites that actually ask "was this show on air in year Y"
  get a small AND-gate added: `on_air_in_year && ! in_a_listed_gap`.

This was chosen over turning the show into a repeater of full airing eras (the
"textbook correct" fix, and the same pattern already used for characters via
`lezchars_show_group.appears`) because that would require rewriting every one of
those ~12 consumers and the raw SQL joins that assume one scalar per show. The show
count of shows with an actual revival gap is small; the blast radius of touching
those 12 files is not. This plan gets the same correctness for on-air/year-membership
logic at a fraction of the risk.

**Non-goals (deliberate):**

- Does **not** change how `lezshows_seasons` (a manually-entered lump total) or the
  displayed `airdates.php` range are computed. The X-Files show page still reads
  "1993 – 2018 (11 seasons)" — this plan fixes what counts as "on air" behind the
  scenes, not the display copy. (A follow-up could add a "(revival)" note to the
  template; not in scope here.)
- Does **not** handle a show *currently* in an open-ended, unresolved hiatus (not
  officially cancelled, revival not yet confirmed). That's an editorial call: set
  `lezshows_airdates_finish` to the last year it actually aired rather than
  `current`, and only add a hiatus row (with a real end year) once the revival
  happens. This plan tracks *closed, known* gaps, not live ambiguity.
- Does **not** rewrite `Shows_Builder::get_shows_for_year()`'s SQL query source.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, ACF Pro (JSON-synced field groups),
PHPUnit 11 for the pure logic. Lint via `composer lint` (phpcs, WordPress-Extra).

## Global Constraints

- Namespace `LWTV\`; class files named `class-*.php`; one class per file.
- New class lives at `plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php`,
  namespace `LWTV\CPTs\Shows` (same namespace as `class-calculations.php`, so no
  `use` import is needed there).
- Meta/field naming: `lezshows_hiatus_periods` (repeater), sub-fields `gap_start`,
  `gap_end` — consistent with the `lezshows_` show-meta prefix convention.
- `composer lint` must pass (0 errors) after every task.
- `vendor/bin/phpunit` must pass after Task 1.
- All user-facing strings (ACF labels/instructions, validation messages) are
  i18n-ready with the `'lwtv'` text domain where they're PHP-rendered (ACF field
  JSON labels themselves are admin-only config, not templated output, so they're not
  wrapped in `__()` — consistent with how the existing `lezshows_airdates_*` field
  labels are written).
- **Do not run `git commit` unless the user explicitly asks.** Commit steps below
  are checkpoints: stage the files, show the message, wait for go-ahead.
- **Do not run `wp lwtv migrate` / other data-mutating WP-CLI commands** against
  production without explicit approval — this plan's manual verification steps are
  read-only or scoped to a single test show.

## File Structure

- **Create** `plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php` — `LWTV\CPTs\Shows\Hiatus`.
- **Create** `tests/unit/CPTs/Shows/HiatusTest.php` — pure-logic tests.
- **Modify** `tests/bootstrap.php` — require the new class.
- **Modify** `plugins/lwtv-plugin/acf-json/group_lwtv_shows_details.json` — add the
  `lezshows_hiatus_periods` repeater.
- **Modify** `plugins/lwtv-plugin/php/plugins/class-acf.php` — dynamic year choices
  for the two new sub-fields, export-strip registration, per-row validation.
- **Modify** `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php` — gap-aware
  `lezshows_on_air` recalculation.
- **Modify** `plugins/lwtv-plugin/php/debugger/class-onair.php` — gap-aware
  `check_if_on_air()` / `fix_on_air_status()`.
- **Modify** `plugins/lwtv-plugin/php/statistics/class-stats-counter.php` — gap-aware
  `onair` / `onairscore` counts.
- **Modify** `plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php` —
  gap-aware `check_show_on_air_in_year()`.

---

## Task 1: `Hiatus` class + unit tests

**Files:**
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php`
- Create: `tests/unit/CPTs/Shows/HiatusTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Produces: `LWTV\CPTs\Shows\Hiatus` with `year_in_gap( array $gaps, int $year ): bool`
  (pure), `get_gaps( int $show_id ): array` (reads ACF), `on_air_in_year( int $show_id, int $year, bool $base_on_air ): bool`.

- [ ] **Step 1: Create the class**

```php
<?php
/**
 * Off-air hiatus gaps for shows with a revival (same IMDb record, resumed after a break).
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hiatus.
 *
 * Purely additive: a show with no `lezshows_hiatus_periods` rows behaves exactly as
 * before. Existing start/finish/seasons meta, and every consumer that reads them
 * (display, FacetWP, exports, decade stats), is untouched by this class.
 */
class Hiatus {

	/**
	 * Does $year fall inside any of the given gap ranges?
	 *
	 * No WordPress calls -- pure array logic, unit-testable directly.
	 *
	 * @param array $gaps Normalized gap ranges: array of array{start:int,end:int}.
	 * @param int   $year Year to check.
	 * @return bool
	 */
	public static function year_in_gap( array $gaps, int $year ): bool {
		foreach ( $gaps as $gap ) {
			$start = (int) ( $gap['start'] ?? 0 );
			$end   = (int) ( $gap['end'] ?? 0 );

			if ( $start && $end && $start <= $year && $year <= $end ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read a show's hiatus gaps, normalized to array{start:int,end:int}.
	 *
	 * Rows missing either year (shouldn't happen -- both sub-fields are required)
	 * are skipped defensively rather than treated as an unbounded gap.
	 *
	 * @param int $show_id Show post ID.
	 * @return array
	 */
	public static function get_gaps( int $show_id ): array {
		$rows = get_field( 'lezshows_hiatus_periods', $show_id );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$gaps = array();
		foreach ( $rows as $row ) {
			$start = (int) ( $row['gap_start'] ?? 0 );
			$end   = (int) ( $row['gap_end'] ?? 0 );

			if ( $start && $end ) {
				$gaps[] = array( 'start' => $start, 'end' => $end );
			}
		}

		return $gaps;
	}

	/**
	 * Narrow an existing on-air-in-year result by the show's hiatus gaps.
	 *
	 * Callers keep their own start/finish (and legacy-fallback) logic exactly as it
	 * is today and pass in that result; this only ever turns a 'yes' into a 'no' for
	 * a year that falls inside a listed gap. It never turns a 'no' into a 'yes'.
	 *
	 * @param int  $show_id     Show post ID.
	 * @param int  $year        Year being checked.
	 * @param bool $base_on_air The show's on-air-in-$year result before gaps.
	 * @return bool
	 */
	public static function on_air_in_year( int $show_id, int $year, bool $base_on_air ): bool {
		if ( ! $base_on_air ) {
			return false;
		}

		return ! self::year_in_gap( self::get_gaps( $show_id ), $year );
	}
}
```

- [ ] **Step 2: Add the PHPUnit test file**

```php
<?php
/**
 * Unit tests for LWTV\CPTs\Shows\Hiatus.
 *
 * Only the pure logic (year_in_gap(), and the short-circuit branch of
 * on_air_in_year()) is covered here. get_gaps() calls get_field() and is verified
 * against the running site instead (see Task 5) -- consistent with this project's
 * "no WP glue in unit tests" rule.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs\Shows;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Hiatus;

class HiatusTest extends TestCase {

	public function test_year_in_gap_true_inside_range(): void {
		$gaps = array( array( 'start' => 2003, 'end' => 2015 ) );
		$this->assertTrue( Hiatus::year_in_gap( $gaps, 2010 ) );
	}

	public function test_year_in_gap_true_on_boundaries(): void {
		$gaps = array( array( 'start' => 2003, 'end' => 2015 ) );
		$this->assertTrue( Hiatus::year_in_gap( $gaps, 2003 ) );
		$this->assertTrue( Hiatus::year_in_gap( $gaps, 2015 ) );
	}

	public function test_year_in_gap_false_outside_range(): void {
		$gaps = array( array( 'start' => 2003, 'end' => 2015 ) );
		$this->assertFalse( Hiatus::year_in_gap( $gaps, 1998 ) );
		$this->assertFalse( Hiatus::year_in_gap( $gaps, 2018 ) );
	}

	public function test_year_in_gap_false_for_no_gaps(): void {
		$this->assertFalse( Hiatus::year_in_gap( array(), 2010 ) );
	}

	public function test_year_in_gap_skips_malformed_rows(): void {
		// A zero/missing end must not be treated as an unbounded, open-ended gap.
		$gaps = array( array( 'start' => 2003, 'end' => 0 ) );
		$this->assertFalse( Hiatus::year_in_gap( $gaps, 2050 ) );
	}

	public function test_year_in_gap_handles_multiple_gaps(): void {
		// e.g. a show with two separate revivals.
		$gaps = array(
			array( 'start' => 2003, 'end' => 2010 ),
			array( 'start' => 2014, 'end' => 2016 ),
		);
		$this->assertTrue( Hiatus::year_in_gap( $gaps, 2005 ) );
		$this->assertTrue( Hiatus::year_in_gap( $gaps, 2015 ) );
		$this->assertFalse( Hiatus::year_in_gap( $gaps, 2012 ) );
	}

	public function test_on_air_in_year_never_promotes_no_to_yes(): void {
		// base_on_air = false short-circuits before get_gaps()/get_field() is ever
		// called, so this is safe to run without a WP bootstrap.
		$this->assertFalse( Hiatus::on_air_in_year( 1, 1990, false ) );
	}
}
```

- [ ] **Step 3: Register the class in the PHPUnit bootstrap**

In `tests/bootstrap.php`, add (near the other `cpts/shows/` require):

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php';
```

- [ ] **Step 4: Lint + test**

Run:
```bash
composer lint -- plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php
vendor/bin/phpunit --filter Hiatus
```
Expected: `0 ERRORS`; all `HiatusTest` tests green.

- [ ] **Step 5: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/cpts/shows/class-hiatus.php tests/unit/CPTs/Shows/HiatusTest.php tests/bootstrap.php
git commit -m "feat: add Hiatus class for off-air gap tracking on shows"
```

---

## Task 2: ACF field — `lezshows_hiatus_periods`

**Files:**
- Modify: `plugins/lwtv-plugin/acf-json/group_lwtv_shows_details.json`
- Modify: `plugins/lwtv-plugin/php/plugins/class-acf.php`

**Interfaces:**
- Produces: field `lezshows_hiatus_periods` (repeater; sub-fields `gap_start`,
  `gap_end`), populated year-select choices, per-row `gap_end >= gap_start`
  validation, export-clean JSON.

- [ ] **Step 1: Add the repeater field to the "Airing Details" tab**

In `group_lwtv_shows_details.json`, insert between the `field_lwtv_lezshows_airdates_finish`
field (ends `"save_options": 0` then `},`) and the `field_lwtv_lezshows_seasons` field:

```json
        {
            "key": "field_lwtv_lezshows_hiatus_periods",
            "label": "Off-Air Gaps (Hiatus)",
            "name": "lezshows_hiatus_periods",
            "aria-label": "",
            "type": "repeater",
            "instructions": "Only for shows that went off air and came back years later under the SAME IMDb record (e.g. The X-Files, 1993–2002 then 2016–2018 — one show record, an 11-year gap). Leave empty if the show aired continuously. List each gap where it was NOT airing between the Start and End years above. If the revival got its own IMDb/show record, don't use this — make a separate show instead, per convention.",
            "required": 0,
            "conditional_logic": 0,
            "wrapper": {
                "width": "",
                "class": "",
                "id": ""
            },
            "layout": "table",
            "pagination": 0,
            "min": 0,
            "max": 0,
            "collapsed": "",
            "button_label": "Add A Gap",
            "rows_per_page": 20,
            "sub_fields": [
                {
                    "key": "field_lwtv_lezshows_hiatus_periods_gap_start",
                    "label": "Gap Start",
                    "name": "gap_start",
                    "aria-label": "",
                    "type": "select",
                    "instructions": "First year the show was off air.",
                    "required": 1,
                    "conditional_logic": 0,
                    "wrapper": {
                        "width": "50",
                        "class": "",
                        "id": ""
                    },
                    "choices": [],
                    "default_value": false,
                    "return_format": "value",
                    "multiple": 0,
                    "allow_null": 0,
                    "allow_in_bindings": 0,
                    "ui": 0,
                    "ajax": 0,
                    "placeholder": "",
                    "create_options": 0,
                    "save_options": 0,
                    "parent_repeater": "field_lwtv_lezshows_hiatus_periods"
                },
                {
                    "key": "field_lwtv_lezshows_hiatus_periods_gap_end",
                    "label": "Gap End",
                    "name": "gap_end",
                    "aria-label": "",
                    "type": "select",
                    "instructions": "Last year the show was off air (inclusive), before it came back.",
                    "required": 1,
                    "conditional_logic": 0,
                    "wrapper": {
                        "width": "50",
                        "class": "",
                        "id": ""
                    },
                    "choices": [],
                    "default_value": false,
                    "return_format": "value",
                    "multiple": 0,
                    "allow_null": 0,
                    "allow_in_bindings": 0,
                    "ui": 0,
                    "ajax": 0,
                    "placeholder": "",
                    "create_options": 0,
                    "save_options": 0,
                    "parent_repeater": "field_lwtv_lezshows_hiatus_periods"
                }
            ]
        },
```

(Keep the trailing comma so it precedes the existing `field_lwtv_lezshows_seasons` object.)

- [ ] **Step 2: Populate the year-select choices**

In `class-acf.php`, near the other `lezshows_airdates_*` choice filters in the
constructor, add:

```php
		// Shows: populate year dropdowns for hiatus gap start/end (reuses the same
		// range as the airdates start dropdown -- no 'current' option; a gap must
		// have a known end).
		add_filter( 'acf/load_field/key=field_lwtv_lezshows_hiatus_periods_gap_start', array( $this, 'load_airdates_start_choices' ) );
		add_filter( 'acf/load_field/key=field_lwtv_lezshows_hiatus_periods_gap_end', array( $this, 'load_airdates_start_choices' ) );

		// Shows: validate each hiatus row's end year against its own start year.
		add_filter( 'acf/validate_value/key=field_lwtv_lezshows_hiatus_periods_gap_end', array( $this, 'validate_hiatus_gap_end' ), 10, 4 );
```

- [ ] **Step 3: Add the per-row validation method**

Add near `validate_airdate_finish()`:

```php
	/**
	 * Validate that a hiatus row's gap end year is not earlier than its gap start.
	 *
	 * Reads the sibling gap_start value from the same repeater row by extracting
	 * the row index out of the ACF input name (acf[[repeater_key]][row_index][...]).
	 *
	 * @param bool|string $valid      True if valid, or an error message string.
	 * @param mixed       $value      The gap_end value being saved.
	 * @param array       $field      ACF field definition.
	 * @param string      $input_name HTML input name.
	 * @return bool|string
	 */
	public function validate_hiatus_gap_end( $valid, $value, array $field, string $input_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $valid || empty( $value ) ) {
			return $valid;
		}

		if ( ! preg_match( '/\[(\d+)\]\[field_lwtv_lezshows_hiatus_periods_gap_end\]$/', $input_name, $matches ) ) {
			return $valid;
		}

		$row_index = $matches[1];

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- ACF handles nonce verification before this hook fires
		$start = isset( $_POST['acf']['field_lwtv_lezshows_hiatus_periods'][ $row_index ]['field_lwtv_lezshows_hiatus_periods_gap_start'] )
			? (int) sanitize_text_field( wp_unslash( $_POST['acf']['field_lwtv_lezshows_hiatus_periods'][ $row_index ]['field_lwtv_lezshows_hiatus_periods_gap_start'] ) )
			: 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $start && (int) $value < $start ) {
			return __( 'The gap end year cannot be earlier than the gap start year.', 'lwtv' );
		}

		return $valid;
	}
```

- [ ] **Step 4: Register the two sub-fields for export-cleaning**

In `strip_dynamic_choices_for_export()`, add to the `$dynamic_keys` static array:

```php
			'field_lwtv_lezshows_hiatus_periods_gap_start',
			'field_lwtv_lezshows_hiatus_periods_gap_end',
```

(No loop changes needed -- the existing `sub_fields` branch of that method already
walks any field's sub-fields generically.)

- [ ] **Step 5: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/plugins/class-acf.php`
Expected: `0 ERRORS`.

- [ ] **Step 6: Verify on lwtv.local**

1. Edit any published show in wp-admin. Confirm the "Off-Air Gaps (Hiatus)" field
   appears under the Airing Details tab, empty, with an "Add A Gap" button.
2. Add a row: Gap Start = 2003, Gap End = 2000. Save. Expected: validation error,
   "The gap end year cannot be earlier than the gap start year."
3. Correct it to Gap Start = 2003, Gap End = 2015. Save successfully.
4. `wp eval 'print_r( get_field( "lezshows_hiatus_periods", <ID> ) );'` — expect one
   row: `array( 'gap_start' => '2003', 'gap_end' => '2015' )`.
5. Remove the test row and save again (cleanup), unless this is your real X-Files-style test show.

- [ ] **Step 7: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/acf-json/group_lwtv_shows_details.json plugins/lwtv-plugin/php/plugins/class-acf.php
git commit -m "feat: add Off-Air Gaps (Hiatus) repeater field to shows"
```

---

## Task 3: Gap-aware *current* on-air status

**Files:**
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`
- Modify: `plugins/lwtv-plugin/php/debugger/class-onair.php`

**Interfaces:**
- Consumes: `Hiatus::on_air_in_year()`.
- Produces: `lezshows_on_air` meta and `OnAir::check_if_on_air()` /
  `OnAir::fix_on_air_status()` now return `'no'` for a show presently inside a listed
  gap, even though its finish year hasn't arrived yet.

- [ ] **Step 1: `class-calculations.php`**

Same namespace as `Hiatus` (`LWTV\CPTs\Shows`) -- no `use` import needed. Replace:

```php
		// If there is no finish date, or the finish date is current, it's on air.
		if ( empty( $finish ) || 'current' === lcfirst( $finish ) ) {
			$on_air = 'yes';
		}

		// If there is a finish date and it's in the future, it's on air.
		if ( ! empty( $finish ) && $finish >= gmdate( 'Y' ) ) {
			$on_air = 'yes';
		}

		update_post_meta( $post_id, 'lezshows_on_air', $on_air );
```

with:

```php
		// If there is no finish date, or the finish date is current, it's on air.
		if ( empty( $finish ) || 'current' === lcfirst( $finish ) ) {
			$on_air = 'yes';
		}

		// If there is a finish date and it's in the future, it's on air.
		if ( ! empty( $finish ) && $finish >= gmdate( 'Y' ) ) {
			$on_air = 'yes';
		}

		// A show presently inside a listed hiatus gap is not on air, even though
		// its overall finish year hasn't arrived (or is 'current').
		if ( 'yes' === $on_air && Hiatus::on_air_in_year( $post_id, (int) gmdate( 'Y' ), true ) === false ) {
			$on_air = 'no';
		}

		update_post_meta( $post_id, 'lezshows_on_air', $on_air );
```

- [ ] **Step 2: `class-onair.php`**

Add the import after the existing `use` lines:

```php
use LWTV\CPTs\Shows\Hiatus;
```

In `check_if_on_air()`, replace the final `return`:

```php
		return ( $start <= $year && $finish >= $year ) ? 'yes' : 'no';
```

with:

```php
		$in_range = ( $start <= $year && $finish >= $year );

		return Hiatus::on_air_in_year( $show_id, $year, $in_range ) ? 'yes' : 'no';
```

In `fix_on_air_status()`, replace the final `update_post_meta` call:

```php
		update_post_meta( $show_id, 'lezshows_on_air', ( $start <= $year && $finish >= $year ) ? 'yes' : 'no' );
		return true;
```

with:

```php
		$in_range = ( $start <= $year && $finish >= $year );

		update_post_meta( $show_id, 'lezshows_on_air', Hiatus::on_air_in_year( $show_id, $year, $in_range ) ? 'yes' : 'no' );
		return true;
```

- [ ] **Step 3: Lint**

Run:
```bash
composer lint -- plugins/lwtv-plugin/php/cpts/shows/class-calculations.php
composer lint -- plugins/lwtv-plugin/php/debugger/class-onair.php
```
Expected: `0 ERRORS`.

- [ ] **Step 4: Verify on lwtv.local**

Using the test show from Task 2 (Start=1993, Finish=2018 -- pick an already-ended
show and temporarily set its dates to simulate this, or use a real revival show if
you have one), with the gap 2003–2015 saved:

```bash
wp eval '$o = new \LWTV\Debugger\OnAir(); var_dump( $o->check_if_on_air( <ID> ) );'
```

Set the show's Finish year to `current` temporarily and set gap end to next year
minus one (simulating "presently in hiatus"), re-save, then:
```bash
wp eval '$o = new \LWTV\Debugger\OnAir(); var_dump( $o->check_if_on_air( <ID> ) );'
```
Expected: `'no'` (gap covers the current year), even though Finish = current.
Restore the show's real values afterward.

- [ ] **Step 5: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/cpts/shows/class-calculations.php plugins/lwtv-plugin/php/debugger/class-onair.php
git commit -m "feat: make current on-air status hiatus-gap aware"
```

---

## Task 4: Gap-aware year-membership stats

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-counter.php`
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php`

**Interfaces:**
- Consumes: `Hiatus::on_air_in_year()`.
- Produces: `Stats_Counter::count_shows( 'onair' | 'onairscore', ... )` and
  `Shows_Builder::get_shows_for_year( $year )` (and everything built on top of it --
  show counts, started/ended flags, shows-by-name/format/nation) now exclude a show
  for any year inside one of its listed gaps.

- [ ] **Step 1: `class-stats-counter.php`**

Add the import after the existing `use` lines:

```php
use LWTV\CPTs\Shows\Hiatus;
```

In the `'onair'` case, replace:

```php
						if ( ! empty( $end ) && ( 'current' === lcfirst( $end ) || $end >= $date ) ) {
							++$onair;
						}
```

with:

```php
						$in_range = ( ! empty( $end ) && ( 'current' === lcfirst( $end ) || $end >= $date ) );
						if ( Hiatus::on_air_in_year( $show->ID, (int) $date, $in_range ) ) {
							++$onair;
						}
```

In the `'onairscore'` case, apply the same replacement to its structurally identical
`if ( ! empty( $end ) && ( 'current' === lcfirst( $end ) || $end >= $date ) )` block
(there are two occurrences in this file total; both get this treatment).

- [ ] **Step 2: `class-shows-builder.php`**

Add the import after the namespace declaration:

```php
use LWTV\CPTs\Shows\Hiatus;
```

Change `check_show_on_air_in_year()`'s signature and body to take the show ID:

```php
	/**
	 * Check if a show was on air during a specific year based on their airdates data
	 *
	 * @param int   $show_id       Show post ID (for hiatus-gap lookup).
	 * @param array $airdates_data The serialized airdates data
	 * @param int   $year The year to check for
	 * @return array Array with on_air, started, and ended boolean values
	 */
	private function check_show_on_air_in_year( int $show_id, array $airdates_data, int $year ): array {
		$start_year  = (int) $airdates_data['start'];
		$finish_year = $airdates_data['finish'];

		// Handle 'current' finish year
		if ( 'current' === $finish_year ) {
			$finish_year = gmdate( 'Y' );
		} else {
			$finish_year = (int) $finish_year;
		}

		// Check if year falls within the show's airing period (inclusive), minus
		// any listed hiatus gap covering this year.
		$in_range = ( $year >= $start_year && $year <= $finish_year );
		$on_air   = Hiatus::on_air_in_year( $show_id, $year, $in_range );

		// Check if show started in the specified year
		$started = ( $start_year === $year );

		// Check if show ended in the specified year
		$ended = ( $finish_year === $year );

		return array(
			'on_air'  => $on_air,
			'started' => $started,
			'ended'   => $ended,
		);
	}
```

Update its one call site:

```php
				$on_air_data = $this->check_show_on_air_in_year( $airdates_data, $year );
```

to:

```php
				$on_air_data = $this->check_show_on_air_in_year( (int) $row['ID'], $airdates_data, $year );
```

- [ ] **Step 3: Lint**

Run:
```bash
composer lint -- plugins/lwtv-plugin/php/statistics/class-stats-counter.php
composer lint -- plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php
```
Expected: `0 ERRORS`.

- [ ] **Step 4: Verify on lwtv.local**

Using the Task 2 test show (Start=1993, Finish=2018, gap 2003–2015):

```bash
wp eval '$b = new \LWTV\This_Year\Build\Shows_Builder(); $shows_2010 = $b->get_shows_for_year( 2010 ); $shows_1998 = $b->get_shows_for_year( 1998 ); $title = get_the_title( <ID> ); echo "1998 includes it: "; var_dump( in_array( $title, wp_list_pluck( $shows_1998, "name" ), true ) ); echo "2010 includes it: "; var_dump( in_array( $title, wp_list_pluck( $shows_2010, "name" ), true ) );'
```
Expected: `true` for 1998, `false` for 2010.

**Cache note:** `get_shows_for_year()` caches each year for `DAY_IN_SECONDS` under
`lwtv_shows_year_<year>`, and that key is *not* one of the patterns
`invalidate_statistics_cache()` clears on save (pre-existing behavior, unrelated to
this change -- confirmed by reading `class-transients.php`). After editing a show's
hiatus gaps, either wait for the daily expiry or manually clear it before testing:
```bash
wp transient delete lwtv_shows_year_1998
wp transient delete lwtv_shows_year_2010
```

- [ ] **Step 5: Full lint gate**

Run: `composer lint`
Expected: `0 ERRORS` across the project.

- [ ] **Step 6: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/statistics/class-stats-counter.php plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php
git commit -m "feat: make This Year and onair-count stats hiatus-gap aware"
```

---

## Task 5: Real-show rollout pass

**Files:** none (data entry + verification only).

- [ ] **Step 1: Identify candidate shows**

You already know of at least a "meaningful handful." For each: confirm on IMDb/TVMaze
that the revival shares the *same* record as the original run (per your existing
convention, if it has its own record it should already be a separate show post and
needs no gap row).

- [ ] **Step 2: Add the gap row(s) to each real show**

Via wp-admin, on each affected show: Off-Air Gaps → Add A Gap → set the off-air
years. Leave `lezshows_airdates_start`/`_finish`/`_seasons` exactly as they are today.

- [ ] **Step 3: Spot-check each one**

```bash
wp eval '$o = new \LWTV\Debugger\OnAir(); var_dump( $o->check_if_on_air( <ID> ) );'
```
Expected: matches the show's real current status.

- [ ] **Step 4: Clear the affected This-Year transients**

For every year spanned by each show's gap (plus its start/finish years), run:
```bash
wp transient delete lwtv_shows_year_<year>
```
Then spot-check a couple of `/this-year/<year>/` pages that used to (incorrectly)
list the show.

---

## Self-Review

- Fixes the two confirmed bugs (This-Year year-membership; current on-air status
  during a live hiatus) -- Tasks 3 & 4. ✓
- Zero changes to `lezshows_airdates_start/_finish/_seasons`, the legacy
  `lezshows_airdates` sync, FacetWP indexing, both REST exporters, custom columns,
  or the raw SQL decade joins in `class-taxonomy-optimized.php` -- confirmed none of
  those files appear in the File Structure list above. ✓
- Pure logic (`year_in_gap()`) is unit-tested; WP-dependent logic (`get_gaps()`,
  the short-circuit branch of `on_air_in_year()`) is verified manually against
  lwtv.local, consistent with this project's existing test philosophy (nothing that
  touches `get_field()`/postmeta is asserted in the pure PHPUnit suite). ✓
- `on_air_in_year()` can only turn `true` → `false`, never the reverse -- verified
  by `test_on_air_in_year_never_promotes_no_to_yes` and by inspection of every call
  site (each ANDs the existing result, never ORs). ✓
- New field is fully optional (`min: 0`) and additive; no migration/backfill step
  exists or is needed. ✓
- Per-row validation (`gap_end >= gap_start`) mirrors the existing
  `validate_airdate_finish()` pattern for consistency. ✓
- Known, called-out limitation: an open-ended/unresolved hiatus on a show still
  marked `finish = current` is not caught (documented under Non-goals) -- this is an
  editorial-process gap, not a code gap, and deliberately out of scope. ✓
- Cache staleness on `lwtv_shows_year_*` after an edit is a pre-existing condition
  (not introduced by this plan) -- called out in Task 4 with a manual workaround
  rather than silently surprising whoever rolls this out. ✓
