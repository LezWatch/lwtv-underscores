# This Year → Characters On Air Rebuild — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Characters On Air view's three trend-callout cards with a navigable A–Z graph + filterable directory (By Name) and convert By Show cast pills into scannable rows, deriving roles from data the template already receives.

**Architecture:** Extract all view logic into a pure static transform class (`LWTV\This_Year\Build\Characters_On_Air`) beside the existing `build/` transforms, unit-tested with the repo's pure PHPUnit harness. The template becomes thin markup consuming that class. The graph is a CSS grid of anchors (no JS, no charting library); the JS filter layer is deferred and only its markup seams ship now.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, PHPUnit 11 (pure/no-WP harness), Bootstrap 5 pills, theme SCSS with `$lwtv-*` tokens.

## Global Constraints

- PHP 8.1+ minimum; passes `composer lint` (WordPress-Extra via `phpcs.xml.dist`).
- Class files named `class-*.php`; one class per file; namespace mirrors directory under `LWTV\`.
- Custom auto-escaped functions (`lwtv_plugin`, `get_symbolicon`, etc.) must **not** be wrapped in `esc_*`.
- All user-facing strings i18n-ready with the `'lwtv'` text domain (`__`, `_e`, `_n`, `esc_html_e`, …).
- **No new palette values except one:** a dark-mode pink foreground `#e86bac` in `_colors-dark.scss`. No new fonts.
- The view stays **strictly alphabetical** in both tabs. No pagination, no lazy loading, no charting library.
- Pure transform classes take/return plain arrays only — **no WordPress functions** (`__()`, `get_field()`, etc.) inside them, so the pure harness can load them. All i18n and WP calls live in the template.
- Role `type` values are exactly `regular`, `recurring`, `guest`. Translated labels already exist in `overview.php:512-514` — reuse that pattern in the template, never `ucfirst()` for display.
- Run `nvm use` before any `npm` command.
- Do **not** commit unless explicitly asked (project owner preference). Each task's "Commit" step is written but only run on request.

## File Structure

- **Create** `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php` — pure view transforms: `roles_by_strength()`, `bucket_for()`, `alphabet()`, `directory()`, `cast_for_show()`.
- **Create** `tests/unit/This_Year/CharactersOnAirTest.php` — unit tests for all five methods.
- **Modify** `tests/bootstrap.php` — `require_once` the new class.
- **Modify** `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php` — rebuild both tabs; `use` the new class; update docblock; remove callout-card markup.
- **Modify** `scss/addons/_stats.scss` — graph, directory, and By-Show-row styles near existing `.lwtv-ty-charname*` (~L1014) / `.lwtv-ty-charshow*` (~L1054). Do **not** remove `.lwtv-trend-callout*` (~L2620) or `.lwtv-ty-chip*` (~L1102).
- **Modify** `scss/partials/_colors-dark.scss` — dark pink foreground (~L686-711).
- **Rebuilt (generated)** `style.css`, `style.min.css`.

Reference: design spec at `docs/superpowers/specs/2026-07-28-thisyear-characters-on-air-rebuild-design.md`.

---

### Task 1: `Characters_On_Air` class + `roles_by_strength()`

Creates the class file, registers it in the test bootstrap, and implements the strongest-role
transform. **The precedence order and the shape of this method are the one genuine judgment call
in this build** — the reference implementation below is correct and complete, but at execution the
project owner may want to adjust it (see note after Step 3).

**Files:**
- Create: `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/This_Year/CharactersOnAirTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Characters_On_Air::ROLE_PRECEDENCE` — `array('regular','recurring','guest')`, strongest first.
  - `Characters_On_Air::roles_by_strength( array $shows ): array` — each `$shows[n]` is
    `{ name, url, type }`; returns a list of `{ type, show }` for tracked roles only, sorted
    strongest-first, `[]` when none.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/This_Year/CharactersOnAirTest.php`:

```php
<?php
/**
 * Unit tests for the Characters On Air view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Characters_On_Air;

class CharactersOnAirTest extends TestCase {

	// ---- roles_by_strength(): strongest-first, tracked roles only. ----

	public function test_roles_by_strength_orders_strongest_first(): void {
		$shows = array(
			array( 'name' => "Grey's Anatomy", 'type' => 'guest' ),
			array( 'name' => 'Station 19', 'type' => 'regular' ),
		);

		$this->assertSame(
			array(
				array( 'type' => 'regular', 'show' => 'Station 19' ),
				array( 'type' => 'guest', 'show' => "Grey's Anatomy" ),
			),
			Characters_On_Air::roles_by_strength( $shows )
		);
	}

	public function test_roles_by_strength_filters_unknown_and_missing_types(): void {
		$shows = array(
			array( 'name' => 'A', 'type' => 'cameo' ),
			array( 'name' => 'B' ),
			array( 'name' => 'C', 'type' => 'recurring' ),
		);

		$this->assertSame(
			array( array( 'type' => 'recurring', 'show' => 'C' ) ),
			Characters_On_Air::roles_by_strength( $shows )
		);
	}

	public function test_roles_by_strength_empty_input(): void {
		$this->assertSame( array(), Characters_On_Air::roles_by_strength( array() ) );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: FAIL — `Class "LWTV\This_Year\Build\Characters_On_Air" not found`.

- [ ] **Step 3: Create the class and register it in the bootstrap**

Create `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php`:

```php
<?php
/**
 * Characters On Air view transforms for This Year.
 *
 * Pure array-in / array-out helpers that shape the flat character list and the
 * by-show cast for the Characters On Air template. No WordPress runtime
 * dependency — all i18n and WP calls stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes character data for the Characters On Air view.
 */
class Characters_On_Air {

	/**
	 * Role types in precedence order, strongest first. Mirrors
	 * Breakdowns::ROLE_TYPES; a character on two shows displays the strongest.
	 *
	 * @var string[]
	 */
	public const ROLE_PRECEDENCE = array( 'regular', 'recurring', 'guest' );

	/**
	 * Every tracked role a character holds across their shows, strongest first.
	 *
	 * @param array $shows List of { name, url, type }.
	 * @return array List of { type, show } for tracked roles only, strongest first.
	 */
	public static function roles_by_strength( array $shows ): array {
		$roles = array();
		foreach ( $shows as $show ) {
			$type = $show['type'] ?? '';
			if ( in_array( $type, self::ROLE_PRECEDENCE, true ) ) {
				$roles[] = array(
					'type' => $type,
					'show' => (string) ( $show['name'] ?? '' ),
				);
			}
		}

		usort(
			$roles,
			static fn( $a, $b ) =>
				array_search( $a['type'], self::ROLE_PRECEDENCE, true )
				<=> array_search( $b['type'], self::ROLE_PRECEDENCE, true )
		);

		return $roles;
	}
}
```

Then add to `tests/bootstrap.php` after the existing `require_once` lines (after line 24):

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php';
```

> **Execution-time checkpoint (learning mode):** `roles_by_strength()` and `ROLE_PRECEDENCE`
> encode a representation decision — which role "wins" when a character is a regular on one show
> and a guest on another, and therefore which dot/label the directory shows. The reference above
> uses `regular > recurring > guest` (matching `Breakdowns::ROLE_TYPES`). Before implementing,
> confirm the project owner is happy with that precedence; if they prefer different wording or a
> different tie-break, adjust the method and its test together.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php tests/bootstrap.php tests/unit/This_Year/CharactersOnAirTest.php
git commit -m "feat(this-year): add Characters_On_Air::roles_by_strength transform"
```

---

### Task 2: `bucket_for()` + `alphabet()` graph model

The A–Z + `#` model the graph and directory subheads share. Includes the behavior change: a `#`
bucket for non-Latin initials so bars sum to the headline count.

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php`
- Test: `tests/unit/This_Year/CharactersOnAirTest.php`

**Interfaces:**
- Consumes: `Characters_On_Air` from Task 1.
- Produces:
  - `Characters_On_Air::bucket_for( string $name ): string` — uppercase Latin initial `A`–`Z`, or
    `#` for anything else (empty, numeric, accented, non-Latin).
  - `Characters_On_Air::alphabet( array $characters ): array` — each `$characters[n]` has a `name`.
    Returns `{ columns:[ { letter, count, empty, peak } … 27 entries A–Z then # ], max:int,
    top:string[], bottom:string[], unused:string[], in_use:int, hash:int }`. `max`/`top`/`bottom`/
    `unused`/`in_use` consider A–Z only (the `#` bucket never ties or counts as "in use").

- [ ] **Step 1: Write the failing tests**

Append to `CharactersOnAirTest.php`:

```php
	// ---- bucket_for(): Latin initial or #. ----

	public function test_bucket_for_latin_initial_uppercased(): void {
		$this->assertSame( 'A', Characters_On_Air::bucket_for( 'abby Littman' ) );
		$this->assertSame( 'Z', Characters_On_Air::bucket_for( 'Zoé Lee' ) );
	}

	public function test_bucket_for_non_latin_or_empty_is_hash(): void {
		$this->assertSame( '#', Characters_On_Air::bucket_for( 'Émile' ) );
		$this->assertSame( '#', Characters_On_Air::bucket_for( '9-Ball' ) );
		$this->assertSame( '#', Characters_On_Air::bucket_for( '' ) );
	}

	// ---- alphabet(): 27-column model; bars sum to the character count. ----

	public function test_alphabet_columns_sum_to_character_count(): void {
		$chars  = array(
			array( 'name' => 'Abby' ),
			array( 'name' => 'Alix' ),
			array( 'name' => 'Bea' ),
			array( 'name' => 'Émile' ), // non-Latin initial → # bucket
		);
		$result = Characters_On_Air::alphabet( $chars );

		$this->assertCount( 27, $result['columns'] );
		$this->assertSame( 'A', $result['columns'][0]['letter'] );
		$this->assertSame( '#', $result['columns'][26]['letter'] );

		$sum = array_sum( array_column( $result['columns'], 'count' ) );
		$this->assertSame( count( $chars ), $sum );
		$this->assertSame( 1, $result['hash'] );
	}

	public function test_alphabet_marks_peak_ties_and_unused(): void {
		$chars = array(
			array( 'name' => 'Abby' ),
			array( 'name' => 'Alix' ),  // A = 2
			array( 'name' => 'Max' ),
			array( 'name' => 'Mel' ),   // M = 2  → A and M tie for peak
			array( 'name' => 'Bea' ),   // B = 1
		);
		$result = Characters_On_Air::alphabet( $chars );

		$this->assertSame( 2, $result['max'] );
		$this->assertSame( array( 'A', 'M' ), $result['top'] );
		$this->assertSame( array( 'B' ), $result['bottom'] );
		$this->assertSame( 24, $result['in_use'] ); // 26 - 24 unused... wait: 3 letters used
		$this->assertContains( 'C', $result['unused'] );
		// A and M columns are flagged peak; B is not.
		$cols = array_column( $result['columns'], 'peak', 'letter' );
		$this->assertTrue( $cols['A'] );
		$this->assertTrue( $cols['M'] );
		$this->assertFalse( $cols['B'] );
	}

	public function test_alphabet_empty_input(): void {
		$result = Characters_On_Air::alphabet( array() );
		$this->assertSame( 0, $result['max'] );
		$this->assertSame( array(), $result['top'] );
		$this->assertSame( 0, $result['in_use'] );
		$this->assertCount( 26, $result['unused'] );
		$this->assertSame( 0, $result['hash'] );
	}
```

> Note: in `test_alphabet_marks_peak_ties_and_unused` only 3 letters (A, B, M) are used, so
> `in_use` is **3**, not 24. Fix the assertion to `$this->assertSame( 3, $result['in_use'] );`
> when writing the test — the `24` above is a deliberate reminder to compute it from the data, not
> copy the mock.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: FAIL — `Call to undefined method …::bucket_for()`.

- [ ] **Step 3: Implement `bucket_for()` and `alphabet()`**

Add to the class:

```php
	/**
	 * The graph/directory bucket for a name: its uppercase Latin initial, or #.
	 *
	 * @param string $name Character name.
	 * @return string One of A–Z, or '#'.
	 */
	public static function bucket_for( string $name ): string {
		$first = mb_strtoupper( mb_substr( trim( $name ), 0, 1 ) );
		return ( 1 === preg_match( '/^[A-Z]$/', $first ) ) ? $first : '#';
	}

	/**
	 * The A–Z (+ #) graph model. Bars sum to the character count because the #
	 * bucket captures every non-Latin initial the A–Z tally would drop.
	 *
	 * @param array $characters List of characters, each with a 'name'.
	 * @return array See the method's @return contract in the plan.
	 */
	public static function alphabet( array $characters ): array {
		$counts = array_fill_keys( range( 'A', 'Z' ), 0 );
		$hash   = 0;

		foreach ( $characters as $char ) {
			$bucket = self::bucket_for( (string) ( $char['name'] ?? '' ) );
			if ( '#' === $bucket ) {
				++$hash;
			} else {
				++$counts[ $bucket ];
			}
		}

		$nonzero = array_filter( $counts );
		$max     = $nonzero ? max( $nonzero ) : 0;
		$min     = $nonzero ? min( $nonzero ) : 0;
		$top     = $nonzero ? array_keys( $counts, $max, true ) : array();
		$bottom  = $nonzero ? array_keys( $counts, $min, true ) : array();
		$unused  = array_values( array_keys( $counts, 0, true ) );
		$in_use  = 26 - count( $unused );

		$columns = array();
		foreach ( range( 'A', 'Z' ) as $letter ) {
			$columns[] = array(
				'letter' => $letter,
				'count'  => $counts[ $letter ],
				'empty'  => 0 === $counts[ $letter ],
				'peak'   => $max > 0 && in_array( $letter, $top, true ),
			);
		}
		$columns[] = array(
			'letter' => '#',
			'count'  => $hash,
			'empty'  => 0 === $hash,
			'peak'   => false,
		);

		return array(
			'columns' => $columns,
			'max'     => $max,
			'top'     => $top,
			'bottom'  => $bottom,
			'unused'  => $unused,
			'in_use'  => $in_use,
			'hash'    => $hash,
		);
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: PASS (all tests green).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php tests/unit/This_Year/CharactersOnAirTest.php
git commit -m "feat(this-year): add alphabet graph model with # bucket"
```

---

### Task 3: `directory()` grouping

Groups the flat list into A–Z-then-`#` buckets, alphabetized within each, with the strongest role
attached per row.

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php`
- Test: `tests/unit/This_Year/CharactersOnAirTest.php`

**Interfaces:**
- Consumes: `roles_by_strength()`, `bucket_for()`.
- Produces: `Characters_On_Air::directory( array $characters ): array` — ordered list of
  `{ letter, count, rows:[ { slug, name, dead, shows, role, roles } ] }`, buckets in A–Z then `#`
  order (empty buckets skipped), rows sorted by name via `strnatcasecmp`. `role` is the strongest
  role slug (`''` if none); `roles` is the full strongest-first list from `roles_by_strength()`.

- [ ] **Step 1: Write the failing tests**

Append to `CharactersOnAirTest.php`:

```php
	// ---- directory(): bucketed, alphabetized, role attached. ----

	public function test_directory_buckets_alphabetically_with_hash_last(): void {
		$chars  = array(
			array( 'slug' => 'bea', 'name' => 'Bea', 'dead' => false, 'shows' => array( array( 'name' => 'Hightown', 'type' => 'guest' ) ) ),
			array( 'slug' => 'abby', 'name' => 'Abby', 'dead' => false, 'shows' => array( array( 'name' => 'Ginny', 'type' => 'recurring' ) ) ),
			array( 'slug' => 'emile', 'name' => 'Émile', 'dead' => false, 'shows' => array() ),
		);
		$result = Characters_On_Air::directory( $chars );

		$this->assertSame( array( 'A', 'B', '#' ), array_column( $result, 'letter' ) );
		$this->assertSame( 'Abby', $result[0]['rows'][0]['name'] );
		$this->assertSame( 1, $result[0]['count'] );
	}

	public function test_directory_attaches_strongest_role(): void {
		$chars  = array(
			array(
				'slug'  => 'x',
				'name'  => 'Xena',
				'dead'  => false,
				'shows' => array(
					array( 'name' => "Grey's", 'type' => 'guest' ),
					array( 'name' => 'Station 19', 'type' => 'regular' ),
				),
			),
		);
		$result = Characters_On_Air::directory( $chars );
		$row    = $result[0]['rows'][0];

		$this->assertSame( 'regular', $row['role'] );
		$this->assertSame( 'regular', $row['roles'][0]['type'] );
		$this->assertCount( 2, $row['roles'] );
	}

	public function test_directory_sorts_within_bucket(): void {
		$chars  = array(
			array( 'slug' => 'ariel', 'name' => 'Ariel', 'dead' => false, 'shows' => array() ),
			array( 'slug' => 'abby', 'name' => 'Abby', 'dead' => false, 'shows' => array() ),
		);
		$result = Characters_On_Air::directory( $chars );

		$this->assertSame( array( 'Abby', 'Ariel' ), array_column( $result[0]['rows'], 'name' ) );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: FAIL — `Call to undefined method …::directory()`.

- [ ] **Step 3: Implement `directory()`**

Add to the class:

```php
	/**
	 * Group the flat character list into A–Z (+ #) buckets for the directory,
	 * alphabetized within each bucket, strongest role attached per row.
	 *
	 * @param array $characters List of { slug, name, dead, shows:[{name,url,type}] }.
	 * @return array Ordered list of { letter, count, rows:[ { slug, name, dead, shows, role, roles } ] }.
	 */
	public static function directory( array $characters ): array {
		$buckets = array();
		foreach ( $characters as $char ) {
			$name   = (string) ( $char['name'] ?? '' );
			$bucket = self::bucket_for( $name );
			$roles  = self::roles_by_strength( $char['shows'] ?? array() );

			$buckets[ $bucket ][] = array(
				'slug'  => (string) ( $char['slug'] ?? '' ),
				'name'  => $name,
				'dead'  => (bool) ( $char['dead'] ?? false ),
				'shows' => array_values( $char['shows'] ?? array() ),
				'role'  => $roles[0]['type'] ?? '',
				'roles' => $roles,
			);
		}

		$ordered = array();
		foreach ( array_merge( range( 'A', 'Z' ), array( '#' ) ) as $letter ) {
			if ( empty( $buckets[ $letter ] ) ) {
				continue;
			}
			$rows = $buckets[ $letter ];
			usort( $rows, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );

			$ordered[] = array(
				'letter' => $letter,
				'count'  => count( $rows ),
				'rows'   => $rows,
			);
		}

		return $ordered;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: PASS.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php tests/unit/This_Year/CharactersOnAirTest.php
git commit -m "feat(this-year): add directory grouping transform"
```

---

### Task 4: `cast_for_show()` — By Show sort + nameless filter

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php`
- Test: `tests/unit/This_Year/CharactersOnAirTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Characters_On_Air::cast_for_show( array $characters ): array` — drops entries whose
  `name` is empty/whitespace (defensive guard for the dangling-reference crack), returns the rest
  sorted by `name` via `strnatcasecmp`, reindexed.

- [ ] **Step 1: Write the failing tests**

Append to `CharactersOnAirTest.php`:

```php
	// ---- cast_for_show(): filter nameless, sort alphabetically. ----

	public function test_cast_for_show_filters_nameless_and_sorts(): void {
		$cast = array(
			array( 'name' => 'Zoé Lee', 'type' => 'recurring' ),
			array( 'name' => '', 'type' => 'guest' ),        // dangling reference → dropped
			array( 'name' => '   ', 'type' => 'regular' ),   // whitespace only → dropped
			array( 'name' => 'Alix Kubdel', 'type' => 'recurring' ),
		);

		$result = Characters_On_Air::cast_for_show( $cast );

		$this->assertSame(
			array( 'Alix Kubdel', 'Zoé Lee' ),
			array_column( $result, 'name' )
		);
	}

	public function test_cast_for_show_empty_input(): void {
		$this->assertSame( array(), Characters_On_Air::cast_for_show( array() ) );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CharactersOnAirTest`
Expected: FAIL — `Call to undefined method …::cast_for_show()`.

- [ ] **Step 3: Implement `cast_for_show()`**

Add to the class:

```php
	/**
	 * A show's cast for the By Show tab: nameless entries filtered out
	 * (defensive guard for dangling show-group references), sorted by name.
	 *
	 * @param array $characters A show's characters, each with a 'name'.
	 * @return array Named characters, alphabetized, reindexed.
	 */
	public static function cast_for_show( array $characters ): array {
		$named = array_values(
			array_filter(
				$characters,
				static fn( $c ) => '' !== trim( (string) ( $c['name'] ?? '' ) )
			)
		);

		usort( $named, static fn( $a, $b ) => strnatcasecmp( (string) $a['name'], (string) $b['name'] ) );

		return $named;
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit`
Expected: PASS — the whole `unit` suite green (existing tests + all `CharactersOnAirTest`).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php tests/unit/This_Year/CharactersOnAirTest.php
git commit -m "feat(this-year): add by-show cast sort and nameless filter"
```

---

### Task 5: Template — By Name tab (graph + directory), remove callouts

Rewrite the top of the template and the By Name pane. The letter tally that lived inline is
replaced by `Characters_On_Air::alphabet()`; the three callout cards and the By Name grid are
replaced by the graph + directory. This task is verified in the browser and by `phpcs` (no unit
test — it is markup).

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php`

**Interfaces:**
- Consumes: `$characters_on_air`, `$characters_on_air_count`, `$this_year` (from `class-display.php`);
  `Characters_On_Air::alphabet()`, `::directory()`.
- Produces: the By Name markup — a `.lwtv-ty-coa-graph` card of A–Z(+#) anchors and a
  `.lwtv-ty-coa-directory` card with `id="coa-letter-{X}"` sticky subheads and per-row
  `data-role` / `data-letter` seams for the deferred filter.

- [ ] **Step 1: Add the `use` and the transform calls; update the docblock**

At the top of the file, below `if ( ! defined( 'ABSPATH' ) ) { exit; }` and the file docblock, add:

```php
use LWTV\This_Year\Build\Characters_On_Air;
```

Update the `@var` docblock line for `$characters_on_air` to document the role type the flat list
already carries:

```php
 * @var array $characters_on_air         Numeric list of { slug, name, dead, death_years, shows:[{name,url,type}] }.
```

Replace the inline tally block (current lines ~65-96, from `// Starting-letter popularity` through
the `$lwtv_coa_unused_list` assignment) with:

```php
// Graph model (A–Z + a # bucket) and the bucketed directory. All the letter
// math lives in the transform so the template stays markup; see
// Characters_On_Air::alphabet()/::directory().
$lwtv_coa_graph     = Characters_On_Air::alphabet( $characters_on_air );
$lwtv_coa_directory = Characters_On_Air::directory( $characters_on_air );

// Human list of unused letters for the state line, e.g. "U or X" / "Q, X, or Z".
$lwtv_coa_unused      = $lwtv_coa_graph['unused'];
$lwtv_coa_unused_n    = count( $lwtv_coa_unused );
$lwtv_coa_unused_list = '';
if ( 1 === $lwtv_coa_unused_n ) {
	$lwtv_coa_unused_list = $lwtv_coa_unused[0];
} elseif ( 2 === $lwtv_coa_unused_n ) {
	/* translators: 1 & 2: single letters joined as an either/or pair, e.g. "X or Z". */
	$lwtv_coa_unused_list = sprintf( __( '%1$s or %2$s', 'lwtv' ), $lwtv_coa_unused[0], $lwtv_coa_unused[1] );
} elseif ( $lwtv_coa_unused_n > 2 ) {
	$lwtv_coa_unused_last = end( $lwtv_coa_unused );
	$lwtv_coa_unused_head = implode( ', ', array_slice( $lwtv_coa_unused, 0, -1 ) );
	/* translators: 1: comma-separated letters, 2: the final letter, e.g. "Q, X, or Z". */
	$lwtv_coa_unused_list = sprintf( __( '%1$s, or %2$s', 'lwtv' ), $lwtv_coa_unused_head, $lwtv_coa_unused_last );
}

// Tie captions naming the letters, e.g. "A and M". Reused for the peak / rarest sentences.
$lwtv_coa_join = static function ( array $letters ): string {
	$letters = array_values( $letters );
	if ( count( $letters ) <= 1 ) {
		return implode( '', $letters );
	}
	$last = array_pop( $letters );
	/* translators: 1: comma-separated letters, 2: the final letter, e.g. "A, B and C". */
	return sprintf( __( '%1$s and %2$s', 'lwtv' ), implode( ', ', $letters ), $last );
};
```

Keep the empty-state guard (lines 18-32) and the By Show `usort` / `$lwtv_ty_coa_sort_key` helper
(lines 34-63) exactly as they are.

- [ ] **Step 2: Replace the callout cards with the graph**

Delete the entire `<?php if ( $lwtv_coa_has_letters ) : ?> … <?php endif; ?>` callout block
(current lines ~123-203). In its place, render the graph card:

```php
<?php if ( $lwtv_coa_count > 0 ) : ?>
<div class="lwtv-ty-coa-graph">
	<div class="lwtv-ty-coa-graph-head">
		<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Jump to a letter', 'lwtv' ); ?></span>
		<span class="lwtv-ty-coa-graph-hint">
			<?php
			printf(
				/* translators: %s: the total character count. */
				esc_html__( "Bar height is that letter's share of the %s", 'lwtv' ),
				esc_html( number_format_i18n( $lwtv_coa_count ) )
			);
			?>
		</span>
	</div>

	<div class="lwtv-ty-coa-bars" role="list">
		<?php foreach ( $lwtv_coa_graph['columns'] as $lwtv_coa_col ) : ?>
			<?php
			$lwtv_coa_letter = $lwtv_coa_col['letter'];
			$lwtv_coa_anchor = ( '#' === $lwtv_coa_letter ) ? 'coa-letter-hash' : 'coa-letter-' . $lwtv_coa_letter;
			$lwtv_coa_height = ( $lwtv_coa_graph['max'] > 0 && $lwtv_coa_col['count'] > 0 )
				? max( 3, (int) round( $lwtv_coa_col['count'] / $lwtv_coa_graph['max'] * 54 ) )
				: 3;
			$lwtv_coa_classes = 'lwtv-ty-coa-bar';
			if ( $lwtv_coa_col['empty'] ) {
				$lwtv_coa_classes .= ' is-empty';
			} elseif ( $lwtv_coa_col['peak'] ) {
				$lwtv_coa_classes .= ' is-peak';
			}
			?>
			<?php if ( $lwtv_coa_col['empty'] ) : ?>
				<span class="<?php echo esc_attr( $lwtv_coa_classes ); ?>" role="listitem" aria-disabled="true">
					<span class="lwtv-ty-coa-bar-count">&mdash;</span>
					<span class="lwtv-ty-coa-bar-fill" style="height:3px"></span>
					<span class="lwtv-ty-coa-bar-letter"><?php echo esc_html( $lwtv_coa_letter ); ?></span>
				</span>
			<?php else : ?>
				<a class="<?php echo esc_attr( $lwtv_coa_classes ); ?>" role="listitem"
					href="#<?php echo esc_attr( $lwtv_coa_anchor ); ?>"
					aria-label="<?php
					printf(
						/* translators: 1: a letter (or #), 2: number of characters under it. */
						esc_attr__( 'Jump to %1$s, %2$s characters', 'lwtv' ),
						esc_attr( $lwtv_coa_letter ),
						esc_attr( number_format_i18n( $lwtv_coa_col['count'] ) )
					);
					?>">
					<span class="lwtv-ty-coa-bar-count"><?php echo esc_html( number_format_i18n( $lwtv_coa_col['count'] ) ); ?></span>
					<span class="lwtv-ty-coa-bar-fill" style="height:<?php echo (int) $lwtv_coa_height; ?>px"></span>
					<span class="lwtv-ty-coa-bar-letter"><?php echo esc_html( $lwtv_coa_letter ); ?></span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<div class="lwtv-ty-coa-graph-foot">
		<span class="lwtv-ty-coa-graph-ties">
			<?php if ( $lwtv_coa_graph['max'] > 0 ) : ?>
				<span class="lwtv-ty-coa-tie">
					<?php
					printf(
						/* translators: 1: letter(s), 2: the shared count. */
						esc_html( _n( '%1$s has the most, %2$s', '%1$s tie for the most, %2$s each', count( $lwtv_coa_graph['top'] ), 'lwtv' ) ),
						'<strong>' . esc_html( $lwtv_coa_join( $lwtv_coa_graph['top'] ) ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( number_format_i18n( $lwtv_coa_graph['max'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</span>
		<span class="lwtv-ty-coa-graph-state">
			<?php
			if ( 0 === $lwtv_coa_unused_n ) {
				printf(
					/* translators: %s: number of letters in use. */
					esc_html__( '%s letters in use · every letter appears this year', 'lwtv' ),
					esc_html( number_format_i18n( $lwtv_coa_graph['in_use'] ) )
				);
			} else {
				printf(
					/* translators: 1: number of letters in use, 2: list of unused letters. */
					esc_html__( '%1$s letters in use · %2$s empty this year', 'lwtv' ),
					esc_html( number_format_i18n( $lwtv_coa_graph['in_use'] ) ),
					esc_html( $lwtv_coa_unused_list )
				);
			}
			?>
		</span>
	</div>
</div>
<?php endif; ?>
```

- [ ] **Step 3: Replace the By Name pane with the directory**

Replace the `<div class="tab-pane … " id="lwtv-ty-coa-byname" …>` block (current lines ~207-228)
with the directory table. Build a translated role-label map once, above the tab content:

```php
$lwtv_coa_role_labels = array(
	'regular'   => __( 'Regular', 'lwtv' ),
	'recurring' => __( 'Recurring', 'lwtv' ),
	'guest'     => __( 'Guest', 'lwtv' ),
);
```

Then the pane:

```php
<div class="tab-pane fade show active" id="lwtv-ty-coa-byname" role="tabpanel" aria-labelledby="lwtv-ty-coa-byname-tab">
	<div class="lwtv-ty-coa-directory">
		<div class="lwtv-ty-coa-dir-head" aria-hidden="true">
			<span><?php esc_html_e( 'Character', 'lwtv' ); ?></span>
			<span><?php esc_html_e( 'Show', 'lwtv' ); ?></span>
			<span><?php esc_html_e( 'Role', 'lwtv' ); ?></span>
		</div>

		<?php foreach ( $lwtv_coa_directory as $lwtv_coa_group ) : ?>
			<?php
			$lwtv_coa_gletter = $lwtv_coa_group['letter'];
			$lwtv_coa_ganchor = ( '#' === $lwtv_coa_gletter ) ? 'coa-letter-hash' : 'coa-letter-' . $lwtv_coa_gletter;
			?>
			<div class="lwtv-ty-coa-subhead" id="<?php echo esc_attr( $lwtv_coa_ganchor ); ?>">
				<span class="lwtv-ty-coa-subhead-letter"><?php echo esc_html( $lwtv_coa_gletter ); ?></span>
				<span class="lwtv-ty-coa-subhead-count"><?php echo esc_html( number_format_i18n( $lwtv_coa_group['count'] ) ); ?></span>
			</div>

			<?php foreach ( $lwtv_coa_group['rows'] as $lwtv_coa_row ) : ?>
				<?php
				// Tooltip listing every role, e.g. "Regular on Station 19, guest on Grey's Anatomy".
				$lwtv_coa_title_parts = array();
				foreach ( $lwtv_coa_row['roles'] as $lwtv_coa_i => $lwtv_coa_r ) {
					$lwtv_coa_label = $lwtv_coa_role_labels[ $lwtv_coa_r['type'] ] ?? ucfirst( $lwtv_coa_r['type'] );
					$lwtv_coa_label = ( 0 === $lwtv_coa_i ) ? $lwtv_coa_label : mb_strtolower( $lwtv_coa_label );
					$lwtv_coa_title_parts[] = ( '' !== $lwtv_coa_r['show'] )
						/* translators: 1: role label, 2: show name. */
						? sprintf( __( '%1$s on %2$s', 'lwtv' ), $lwtv_coa_label, $lwtv_coa_r['show'] )
						: $lwtv_coa_label;
				}
				$lwtv_coa_title = implode( ', ', $lwtv_coa_title_parts );
				?>
				<div class="lwtv-ty-coa-dir-row" data-letter="<?php echo esc_attr( $lwtv_coa_gletter ); ?>" data-role="<?php echo esc_attr( $lwtv_coa_row['role'] ); ?>">
					<span class="lwtv-ty-coa-dir-char">
						<a href="<?php echo esc_url( home_url( '/character/' . $lwtv_coa_row['slug'] . '/' ) ); ?>"><?php echo esc_html( $lwtv_coa_row['name'] ); ?></a>
						<?php if ( $lwtv_coa_row['dead'] ) : ?>
							<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="screen-reader-text"><?php esc_html_e( 'Died this year', 'lwtv' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="lwtv-ty-coa-dir-show">
						<?php
						$lwtv_coa_show_links = array();
						foreach ( $lwtv_coa_row['shows'] as $lwtv_coa_show ) {
							$lwtv_coa_show_links[] = '<a href="' . esc_url( $lwtv_coa_show['url'] ) . '">' . esc_html( $lwtv_coa_show['name'] ) . '</a>';
						}
						echo implode( ' · ', $lwtv_coa_show_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</span>
					<span class="lwtv-ty-coa-dir-role"<?php echo ( '' !== $lwtv_coa_title ) ? ' title="' . esc_attr( $lwtv_coa_title ) . '"' : ''; ?>>
						<?php if ( '' !== $lwtv_coa_row['role'] ) : ?>
							<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_coa_row['role'] ); ?>"></span>
							<?php echo esc_html( $lwtv_coa_role_labels[ $lwtv_coa_row['role'] ] ?? '' ); ?>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		<?php endforeach; ?>

		<div class="lwtv-ty-coa-dir-foot">
			<?php
			printf(
				/* translators: %s: total number of characters. */
				esc_html__( '%s characters, A to Z — no pagination.', 'lwtv' ),
				esc_html( number_format_i18n( $lwtv_coa_count ) )
			);
			?>
		</div>
	</div>
</div>
```

- [ ] **Step 4: Lint the PHP**

Run: `composer lint -- plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php`
Expected: no errors. (If `composer lint` takes no path arg, run `composer lint` and confirm this
file is clean.) Fix any spacing/escaping nits with `composer lint-fix`.

- [ ] **Step 5: Verify in the browser**

Load `https://lwtv.local/this-year/2025/characters-on-air/` (Chrome). Confirm: the three callout
cards are gone; the graph shows 27 columns (A–Z + #) with correct counts; the bar counts visibly
**sum to the headline number**; clicking an in-use letter jumps to its subhead; empty letters show
an em-dash and a struck label and don't navigate; the directory lists character → show(s) →
role with dead-marker skulls. Styling will be unpolished until Task 7 — you are checking structure
and data, not looks.

- [ ] **Step 6: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php
git commit -m "feat(this-year): rebuild By Name tab as graph + directory"
```

---

### Task 6: Template — By Show tab (rows, sorted cast, defensive filter)

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php`

**Interfaces:**
- Consumes: `$lwtv_ty_coa_by_show` (already article-sorted), `Characters_On_Air::cast_for_show()`,
  `$lwtv_coa_role_labels` (from Task 5).
- Produces: the By Show pane — a `Shows A–Z, articles ignored` pill and cast rendered as rows.

- [ ] **Step 1: Add the sort-explanation pill above the grid**

Immediately inside the `<div class="tab-pane fade" id="lwtv-ty-coa-byshow" …>`, before
`<div class="lwtv-ty-charshow">`, add:

```php
<div class="lwtv-ty-coa-sortnote">
	<span class="lwtv-ty-coa-sortpill"><?php esc_html_e( 'Shows A–Z, articles ignored', 'lwtv' ); ?></span>
	<span class="lwtv-ty-coa-sortnote-text"><?php esc_html_e( '“The Beast in Me” files under B; numeric titles like 9-1-1 lead.', 'lwtv' ); ?></span>
</div>
```

- [ ] **Step 2: Replace the chips with rows**

Replace the `<div class="lwtv-ty-charshow-chips"> … </div>` block (current lines ~251-264) with a
sorted, name-filtered row list:

```php
<div class="lwtv-ty-charshow-cast">
	<?php foreach ( Characters_On_Air::cast_for_show( $lwtv_ty_show['characters'] ) as $lwtv_ty_castmate ) : ?>
		<div class="lwtv-ty-charshow-castrow">
			<a href="<?php echo esc_url( $lwtv_ty_castmate['url'] ); ?>" class="lwtv-ty-charshow-castname">
				<?php echo esc_html( $lwtv_ty_castmate['name'] ); ?>
				<?php if ( ! empty( $lwtv_ty_castmate['dead'] ) ) : ?>
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '12' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Died this year', 'lwtv' ); ?></span>
				<?php endif; ?>
			</a>
			<span class="lwtv-ty-charshow-castrole">
				<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_ty_castmate['type'] ); ?>"></span>
				<?php echo esc_html( $lwtv_coa_role_labels[ $lwtv_ty_castmate['type'] ] ?? ucfirst( $lwtv_ty_castmate['type'] ) ); ?>
			</span>
		</div>
	<?php endforeach; ?>
</div>
```

- [ ] **Step 3: Lint the PHP**

Run: `composer lint` (confirm the template is clean). Fix with `composer lint-fix` if needed.

- [ ] **Step 4: Verify in the browser**

Reload `https://lwtv.local/this-year/2025/characters-on-air/`, switch to the **By Show** pill.
Confirm: the sort pill appears; each card's cast is alphabetized and rendered as rows (name left,
role dot + label right); no bare role labels appear; a show that previously showed a nameless pill
now simply omits it. Check a large-cast card (e.g. a show with 8+ characters).

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php
git commit -m "feat(this-year): rebuild By Show cast as sorted rows"
```

---

### Task 7: SCSS — graph, directory, By-Show rows, mobile chip variant

Style everything from Tasks 5–6 with theme tokens. Values come from the design spec's "Design
tokens" section.

**Files:**
- Modify: `scss/addons/_stats.scss`
- Rebuilt: `style.css`, `style.min.css`

**Interfaces:**
- Consumes: the classes emitted in Tasks 5–6.
- Produces: `.lwtv-ty-coa-graph*`, `.lwtv-ty-coa-directory*`, `.lwtv-ty-coa-dir-*`,
  `.lwtv-ty-coa-role-dot`, `.lwtv-ty-charshow-cast*`, `.lwtv-ty-coa-sort*` styles.

- [ ] **Step 1: Add the styles**

In `scss/addons/_stats.scss`, near the existing `.lwtv-ty-charshow*` region (~L1054), add (adjust
token names to the repo's actual `$lwtv-*` variables — confirm each exists in
`scss/partials/_colors.scss` before use):

```scss
	/* Characters On Air — A–Z graph */
	.lwtv-ty-coa-graph {
		border: 1px solid $lwtv-grey-border;
		border-radius: 14px;
		padding: 16px 18px 14px;
		margin-bottom: 14px;
	}

	.lwtv-ty-coa-graph-head {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		margin-bottom: 12px;

		.lwtv-ty-coa-graph-hint {
			font-size: 11px;
			color: $lwtv-medgrey;
		}
	}

	.lwtv-ty-coa-bars {
		display: grid;
		grid-template-columns: repeat(27, 1fr);
		align-items: end;
		gap: 2px;
	}

	.lwtv-ty-coa-bar {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-end;
		min-width: 24px;
		text-decoration: none;
		font-variant-numeric: tabular-nums;

		.lwtv-ty-coa-bar-count {
			font-size: 9px;
			font-weight: 700;
			color: $lwtv-medgrey;
		}

		.lwtv-ty-coa-bar-fill {
			width: 100%;
			background: $lwtv-ltpink;
			border-radius: 3px 3px 0 0;
			margin: 2px 0 4px;
		}

		.lwtv-ty-coa-bar-letter {
			font-size: 11px;
			font-weight: 700;
		}

		&.is-peak .lwtv-ty-coa-bar-fill {
			background: $lwtv-pink;
		}

		&.is-empty {
			.lwtv-ty-coa-bar-fill {
				background: $lwtv-grey2;
			}

			.lwtv-ty-coa-bar-letter {
				color: $lwtv-medgrey;
				text-decoration: line-through;
			}
		}

		&:not(.is-empty):hover .lwtv-ty-coa-bar-letter {
			color: $lwtv-purple;
		}
	}

	.lwtv-ty-coa-graph-foot {
		display: flex;
		justify-content: space-between;
		gap: 12px;
		margin-top: 10px;
		padding-top: 10px;
		border-top: 1px solid $lwtv-grey-border;
		font-size: 11px;
		color: $lwtv-medgrey;

		.lwtv-ty-coa-tie,
		.lwtv-ty-coa-graph-state {
			white-space: nowrap;
		}
	}

	/* Characters On Air — directory */
	.lwtv-ty-coa-directory {
		border: 1px solid $lwtv-grey-border;
		border-radius: 14px;
		overflow: hidden;
	}

	.lwtv-ty-coa-dir-head,
	.lwtv-ty-coa-dir-row {
		display: grid;
		grid-template-columns: 1.1fr 1fr 108px;
		gap: 14px;
		align-items: center;
	}

	.lwtv-ty-coa-dir-head {
		padding: 9px 18px;
		background: $lwtv-grey;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: $lwtv-medgrey;
	}

	.lwtv-ty-coa-subhead {
		position: sticky;
		top: 64px; /* app header offset */
		z-index: 2;
		display: flex;
		gap: 8px;
		padding: 6px 18px;
		background: $lwtv-ltpink;
		color: $lwtv-dkpink;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}

	.lwtv-ty-coa-dir-row {
		padding: 7px 18px;
		border-top: 1px solid $lwtv-grey-border;

		.lwtv-ty-coa-dir-char a {
			font-size: 13px;
			font-weight: 600;
		}

		.lwtv-ty-coa-dir-show {
			font-size: 12px;
			font-style: italic;
			color: $lwtv-medgrey;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.lwtv-ty-coa-dir-role {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 11px;
			font-weight: 600;
			color: $lwtv-medgrey;
		}
	}

	.lwtv-ty-coa-role-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		flex-shrink: 0;
		background: $lwtv-medgrey;

		&.role-regular { background: $lwtv-green; }
		&.role-recurring { background: $lwtv-dkblue; }
		&.role-guest { background: $lwtv-medgrey; }
	}

	.lwtv-ty-coa-dir-foot {
		padding: 9px 18px;
		border-top: 1px solid $lwtv-grey-border;
		font-size: 12px;
		color: $lwtv-medgrey;
	}

	/* Characters On Air — By Show sort note + cast rows */
	.lwtv-ty-coa-sortnote {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 12px;
		font-size: 12px;
		color: $lwtv-medgrey;

		.lwtv-ty-coa-sortpill {
			padding: 2px 10px;
			border-radius: 8px;
			background: $lwtv-ltpink;
			color: $lwtv-dkpink;
			font-weight: 700;
		}
	}

	.lwtv-ty-charshow-castrow {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 10px;
		padding: 6px 0;
		border-top: 1px solid $lwtv-grey-border;

		.lwtv-ty-charshow-castname {
			font-size: 13px;
			font-weight: 600;
		}

		.lwtv-ty-charshow-castrole {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 11px;
			font-weight: 600;
			color: $lwtv-medgrey;
			white-space: nowrap;
		}
	}

	/* Mobile: the 27-column graph is unreadable — swap bars for letter chips. */
	@media (max-width: 767px) {
		.lwtv-ty-coa-bars {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		.lwtv-ty-coa-bar {
			flex-direction: row;
			gap: 4px;
			height: 28px;
			padding: 0 10px;
			border: 1px solid $lwtv-grey-border;
			border-radius: 8px;

			.lwtv-ty-coa-bar-fill { display: none; }

			&.is-peak {
				background: $lwtv-pink;
				border-color: $lwtv-pink;

				.lwtv-ty-coa-bar-letter,
				.lwtv-ty-coa-bar-count { color: #fff; }
			}
		}

		.lwtv-ty-coa-dir-head,
		.lwtv-ty-coa-dir-row {
			grid-template-columns: 1fr 84px;
		}

		.lwtv-ty-coa-dir-show { display: none; }
	}
```

> Before writing, grep `scss/partials/_colors.scss` for each token (`$lwtv-grey-border`,
> `$lwtv-ltpink`, `$lwtv-dkpink`, `$lwtv-pink`, `$lwtv-grey2`, `$lwtv-grey`, `$lwtv-medgrey`,
> `$lwtv-green`, `$lwtv-dkblue`, `$lwtv-purple`). If any name differs, use the repo's actual name —
> do **not** invent a new token.

- [ ] **Step 2: Rebuild the theme CSS**

Run: `nvm use && npm run buildquick`
Confirm `style.css` / `style.min.css` changed.

- [ ] **Step 3: Lint the SCSS**

Run: `nvm use && npm run lint:css`
Expected: no errors.

- [ ] **Step 4: Verify in the browser (desktop + mobile)**

Reload the page. Desktop: graph bars have height proportional to counts, peak letter(s) in
`$lwtv-pink`, empty letters greyed + struck, subheads sticky under the header while scrolling,
role dots the right colours. Then narrow the window below 768px (or use device emulation): the
graph collapses to a wrapping row of letter+count chips, and the directory drops the Show column.

- [ ] **Step 5: Commit** *(only if asked)*

```bash
git add scss/addons/_stats.scss style.css style.min.css
git commit -m "style(this-year): style Characters On Air graph, directory, and cast rows"
```

---

### Task 8: Dark mode — the one net-new foreground token

**Files:**
- Modify: `scss/partials/_colors-dark.scss`
- Rebuilt: `style.css`, `style.min.css`

**Interfaces:**
- Consumes: the subhead + sort-pill classes from Tasks 5–7.
- Produces: an AA-contrast pink foreground for `.lwtv-ty-coa-subhead` and `.lwtv-ty-coa-sortpill`
  in dark mode.

- [ ] **Step 1: Add the dark override**

In `scss/partials/_colors-dark.scss`, in the same dark block that already overrides
`.lwtv-ty-charname-row` / `.lwtv-ty-chip` (~L686-711), add — matching the file's existing nesting
(the spec notes dark overrides need `#masthead` nesting; follow whatever the neighbouring rules
use):

```scss
	.lwtv-ty-coa-subhead,
	.lwtv-ty-coa-subhead-letter,
	.lwtv-ty-coa-subhead-count {
		color: #e86bac; /* AA (~5.44:1) on the dark plum subhead; matches the dark "new shows" family */
	}

	.lwtv-ty-coa-sortpill {
		color: #e86bac;
	}
```

- [ ] **Step 2: Rebuild + lint**

Run: `nvm use && npm run buildquick && npm run lint:css`
Expected: CSS rebuilt, no lint errors.

- [ ] **Step 3: Verify in the browser (dark)**

Reload, switch the site to Dark (the navbar Light/Dark/Auto control). Confirm the letter subheads
and the sort pill text are legibly pink against the dark plum background (not the old dark, hard-to-
read `$lwtv-dkpink`). Graph bars should still read as a light tint; empty ticks use the dark grey.

- [ ] **Step 4: Commit** *(only if asked)*

```bash
git add scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "style(this-year): add dark-mode pink foreground for COA subheads"
```

---

### Task 9: Full-view verification + lint gate

No new code — a whole-view cross-check before calling the feature done.

**Files:** none (verification only).

- [ ] **Step 1: Full unit suite**

Run: `vendor/bin/phpunit`
Expected: all green (existing This Year tests + `CharactersOnAirTest`).

- [ ] **Step 2: Full lint gate**

Run: `composer lint && nvm use && npm run lint:css && npm run lint:js`
Expected: all clean. Fix with `composer lint-fix` / `npm run fix:css` as needed, then re-run.

- [ ] **Step 3: Data-integrity check in the browser**

On `https://lwtv.local/this-year/2025/characters-on-air/`, add up a few bar counts vs. the headline
and confirm the graph totals the count (the `#` bucket makes this hold even with accented names).
Confirm the tie caption **names** the letters (e.g. "A and M tie for the most, 35 each"). Spot-check
one multi-show character: the role dot shows the strongest role and hovering the role cell reveals
the full "Regular on X, guest on Y" tooltip.

- [ ] **Step 4: Accessibility spot check**

Keyboard-tab through the graph and a few directory rows: every bar and link shows a visible focus
ring; empty-letter columns are skipped (they are `aria-disabled`, no `href`). Confirm a dead
character's row exposes "Died this year" to a screen reader (inspect the `.screen-reader-text`
span). Verify no horizontal page scroll at mobile width.

- [ ] **Step 5: Verify across years**

Load the same view for a second year (e.g. `/this-year/2024/characters-on-air/`) to confirm the
transforms hold with different data — especially a year whose letter distribution differs, and the
empty state (`?` a year with zero characters, if one exists) still shows the empty-state card.

- [ ] **Step 6: Commit** *(only if asked)* — nothing to commit unless a fix was made in Step 2.

---

## Self-Review

**Spec coverage:**

- Graph replacing callouts, `#` bucket, tie/unused captions → Tasks 2, 5. ✓
- Directory with sticky subheads, Role column, dead marker, footnote → Tasks 3, 5, 7. ✓
- Deferred search/chips, with markup seams (`id`, `data-role`, `data-letter`) → Task 5 (seams emitted; no inert controls rendered). ✓
- Strongest-role derivation + tooltip, user-contribution checkpoint → Tasks 1, 5. ✓
- By Show rows, alphabetized cast, nameless filter (collapse line dropped), sort pill → Tasks 4, 6. ✓
- Dark-mode pink foreground (`#e86bac`) → Task 8. ✓
- Mobile chip variant + Show-column drop → Task 7. ✓
- Accessibility (focus rings, aria-disabled, SR text), reduced motion (count-up untouched) → Tasks 5, 9. ✓
- Docblock update, keep callout/chip SCSS → Tasks 5, 7. ✓
- Lint gate (php/css/js) + unit suite → Task 9. ✓

**Placeholder scan:** No TBD/TODO. The one "24 vs 3" figure in Task 2's test is called out explicitly as a compute-from-data reminder, not a placeholder. Token-name and dark-nesting caveats are guardrails, not gaps.

**Type consistency:** `roles_by_strength()` returns `{type, show}` items — consumed as `roles[0]['type']` and `$r['show']` in Tasks 3 and 5. ✓ `directory()` rows expose `role`/`roles`/`shows`/`dead`/`slug`/`name`, all consumed in Task 5. ✓ `cast_for_show()` items keep `name`/`url`/`type`/`dead`, consumed in Task 6. ✓ `alphabet()` keys (`columns`/`max`/`top`/`in_use`/`hash`/`unused`) all consumed in Task 5. ✓ Anchor id convention (`coa-letter-{X}`, `coa-letter-hash`) is identical in graph and directory (Task 5). ✓
