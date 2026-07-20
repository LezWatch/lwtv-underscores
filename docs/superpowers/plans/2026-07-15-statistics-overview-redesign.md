# Statistics Overview Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the LezWatch.TV Statistics **Overview** view (`/statistics/`) into the story-driven layout from the design handoff — a pill tab bar, four count-up metric cards with growth sparklines, a "Bury Your Gays" callout band, and two data panels (networks + nations) — reusing the theme's existing palette, classes, and data.

**Architecture:** The existing render path is preserved: `page-templates/statistics.php` → `generate_stats_block(['page'=>'main'])` → `Gutenberg_SSR::statistics()` includes `templates/main.php`. `main.php` becomes a server-side orchestrator that computes all data and includes small focused partials. One new data helper (`get_growth_series`) powers the sparklines. One new vanilla-JS file drives count-up and bar-grow animations from `data-*` attributes. All styling goes into the existing stats SCSS (light) and dark-mode SCSS.

**Tech Stack:** PHP 8.1+ (WordPress theme + plugin, `LWTV\` PSR-4), Bootstrap 5 utility classes, SCSS (Dart Sass via `@wordpress/scripts`), vanilla JS, Symbolicons SVG sprite. No PHPUnit harness exists in this repo — verification is PHPCS lint, SCSS build, a `wp eval` assertion for pure logic, and manual browser checks.

## Global Constraints

- **PHP:** 8.1+ minimum; `composer.json` platform targets 8.5. WordPress 6.5+.
- **PHP standard:** WordPress-Extra via `phpcs.xml.dist`. Run `composer lint` (phpcs) and `composer lint-fix` (phpcbf). Class files named `class-*.php`, one class per file, namespace mirrors directory under `LWTV\`.
- **Auto-escaped functions** (do NOT wrap in `esc_*`): `lwtv_plugin`, `get_symbolicon`, `lwtv_symbolicons`, `LWTV_Features`, `LWTV_Statistics`. When echoing `get_symbolicon()` output add `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`.
- **i18n:** every user-facing string uses `__()`/`esc_html__()`/`_n()` etc. with the `'lwtv'` text domain.
- **Meta/taxonomy prefixes:** shows `lezshows_`, characters `lezchars_`; taxonomies `lez_stations`, `lez_country`.
- **Palette:** reuse `$lwtv-*` tokens only. New values only for the two intermediate nation-ramp steps, generated via SCSS `color.mix()` — never pasted hex.
- **No new dependencies:** no Lucide, no new webfont. Use Symbolicons + existing font stack.
- **Data integrity:** guard every divisor (`total`, `topCount`, `dead`) against zero; never divide by zero; render graceful fallback rather than a broken panel on empty data.
- **Scope:** Overview view only. Do not touch sub-section templates, URLs, query-var routing, or `class-calculations.php`.
- **Build:** `npm run buildquick` compiles SCSS/JS assets. Never edit `blocks/build/` or `inc/dist/` output.
- **Transients:** use `lwtv_plugin()->get_transient()` / `set_transient( $key, $value, DAY_IN_SECONDS )` / `delete_transient()`.

---

## File Structure

**New files**
- `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php` — pill tab bar (8 links).
- `plugins/lwtv-plugin/php/statistics/templates/main/bury-your-gays.php` — red callout band.
- `plugins/lwtv-plugin/php/statistics/templates/main/where-tv-lives.php` — networks panel.
- `plugins/lwtv-plugin/php/statistics/templates/main/around-the-world.php` — nations panel.
- `plugins/lwtv-plugin/assets/js/statistics-overview.js` — count-up + bar-grow animations.

**Modified files**
- `plugins/lwtv-plugin/php/statistics/class-stats-counter.php` — add `get_growth_series()`.
- `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php` — register `generate_growth_series` tag + wrapper.
- `plugins/lwtv-plugin/php/class-plugin.php` — `@method` docblock for the new tag.
- `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php` — enqueue the new JS on the overview.
- `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php::VERSIONING` — add `stats-overview` version.
- `plugins/lwtv-plugin/php/statistics/templates/main.php` — orchestrator rewrite (incremental across tasks).
- `plugins/lwtv-plugin/php/statistics/templates/main/overview.php` — metric-cards rewrite.
- `scss/addons/_stats.scss` — light styles.
- `scss/partials/_colors-dark.scss` — dark styles.

**Removed files** (Task 8)
- `plugins/lwtv-plugin/php/statistics/templates/main/top-stations.php`
- `plugins/lwtv-plugin/php/statistics/templates/main/top-nations.php`

**Renderability invariant:** every task leaves `/statistics/` rendering without a fatal error, so each task is independently browser-verifiable.

---

## A note on TDD in this repo

There is no PHPUnit/automated-test harness (confirmed: no `phpunit.xml`, no `tests/`, composer scripts are only `lint`/`lint-fix`). Introducing one for a UI overhaul is out of scope. So:

- **Pure logic** (`get_growth_series` cumulative summing, the sparkline point math) gets a real assertion cycle via `wp eval` against the local site — written and run *before* wiring it into templates.
- **Templates / SCSS / JS** are verified by: `composer lint`, `npm run buildquick`, and manual observation at `https://lwtv.local/statistics/` in light + dark, with reduced-motion and narrow viewports.

If `wp` (WP-CLI) is unavailable in the execution environment, fall back to a standalone PHP snippet that requires only the pure function (copy it into a scratch `.php` file and run with `php`), since the cumulative/point math has no WordPress dependencies.

---

### Task 1: Growth-series data helper

Adds the cumulative year-by-year series that the sparklines consume, plus its public template tag.

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-counter.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php`
- Modify: `plugins/lwtv-plugin/php/class-plugin.php`

**Interfaces:**
- Consumes: existing `Build\Dead::generate_years_data()` → returns `[ ['death_year'=>'YYYY','death_count'=>int], … ]` sorted ascending by year; existing `lwtv_plugin()->get_transient()/set_transient()`.
- Produces:
  - `Stats_Counter::get_growth_series( string $subject ): array` where `$subject ∈ {shows,characters,actors,dead}`, returning a year-ordered array `[ ['year'=>int,'count'=>int], … ]` with `count` cumulative (monotonically non-decreasing).
  - `lwtv_plugin()->generate_growth_series( string $subject ): array` (same return).

- [ ] **Step 1: Write the pure-logic assertion (runs before implementation exists)**

Create a scratch file `plugins/lwtv-plugin/php/statistics/.growth-series-check.php` (temporary — deleted in Step 6). It exercises only the cumulative-sum transform so it can run standalone:

```php
<?php
// Standalone check of the cumulative-series transform used by get_growth_series().
// Mirrors the private helper Stats_Counter::cumulate().
function cumulate_check( array $per_year ): array {
	ksort( $per_year );
	$running = 0;
	$out     = array();
	foreach ( $per_year as $year => $count ) {
		$running += (int) $count;
		$out[]    = array( 'year' => (int) $year, 'count' => $running );
	}
	return $out;
}

$in       = array( 2018 => 5, 2016 => 10, 2017 => 3 ); // deliberately unsorted
$result   = cumulate_check( $in );
$expected = array(
	array( 'year' => 2016, 'count' => 10 ),
	array( 'year' => 2017, 'count' => 13 ),
	array( 'year' => 2018, 'count' => 18 ),
);
assert( $result === $expected, 'cumulate: sorted + running total' );
assert( end( $result )['count'] === 18, 'cumulate: final equals grand total' );
echo "growth-series cumulate check: PASS\n";
```

- [ ] **Step 2: Run it to verify the transform logic**

Run: `php plugins/lwtv-plugin/php/statistics/.growth-series-check.php`
Expected: `growth-series cumulate check: PASS`
(If it prints an assertion failure, fix the transform before continuing.)

- [ ] **Step 3: Implement `get_growth_series()` in `class-stats-counter.php`**

Add these two methods to the `Stats_Counter` class (the class already `use`s `Build\Dead as Build_Dead`). Insert after `generate_total_counts()`:

```php
	/**
	 * Cumulative growth series for a subject, one entry per year.
	 *
	 * @param string $subject One of: shows, characters, actors, dead.
	 * @return array<int,array{year:int,count:int}> Year-ordered, cumulative counts.
	 */
	public function get_growth_series( $subject ) {
		$valid = array( 'shows', 'characters', 'actors', 'dead' );
		if ( ! in_array( $subject, $valid, true ) ) {
			return array();
		}

		$cache_key   = 'stats_growth_series_' . $subject;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return (array) $cached_data;
		}

		$per_year = array();

		if ( 'dead' === $subject ) {
			// Reuse the death-year data (character death counts per year).
			$years = ( new Build_Dead() )->generate_years_data();
			foreach ( $years as $row ) {
				$year              = (int) $row['death_year'];
				$per_year[ $year ] = ( $per_year[ $year ] ?? 0 ) + (int) $row['death_count'];
			}
		} else {
			global $wpdb;
			$post_type = 'post_type_' . $subject;
			// One grouped query: published entries per creation year.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT YEAR(post_date) AS post_year, COUNT(*) AS total
					FROM {$wpdb->posts}
					WHERE post_type = %s AND post_status = 'publish'
					GROUP BY YEAR(post_date)
					ORDER BY post_year ASC",
					$post_type
				),
				ARRAY_A
			);
			if ( ! is_array( $results ) ) {
				lwtv_plugin()->error_log( 'statistics', 'Growth series query failed for ' . $subject . ' - ' . $wpdb->last_error );
				return array();
			}
			foreach ( $results as $row ) {
				$per_year[ (int) $row['post_year'] ] = (int) $row['total'];
			}
		}

		$series = $this->cumulate( $per_year );

		if ( ! empty( $series ) ) {
			lwtv_plugin()->set_transient( $cache_key, $series, DAY_IN_SECONDS );
		}

		return $series;
	}

	/**
	 * Turn a year=>count map into a year-ordered cumulative series.
	 *
	 * @param array<int,int> $per_year Map of year => count for that year.
	 * @return array<int,array{year:int,count:int}>
	 */
	private function cumulate( array $per_year ) {
		ksort( $per_year );
		$running = 0;
		$series  = array();
		foreach ( $per_year as $year => $count ) {
			$running += (int) $count;
			$series[] = array(
				'year'  => (int) $year,
				'count' => $running,
			);
		}
		return $series;
	}
```

- [ ] **Step 4: Register the template tag in `class-statistics-optimized.php`**

In `get_template_tags()` add, after the `'generate_total_dead'` entry:

```php
			'generate_growth_series'         => array( $this, 'generate_growth_series' ),
```

And add the wrapper method after `generate_total_counts()`:

```php
	/**
	 * Generate cumulative growth series for the overview sparklines.
	 *
	 * @param string $subject One of: shows, characters, actors, dead.
	 * @return array
	 */
	public function generate_growth_series( $subject ) {
		return ( new Stats_Counter() )->get_growth_series( $subject );
	}
```

In `class-plugin.php`, add the `@method` docblock line alongside the other statistics methods (near `generate_total_dead`):

```php
 * @method array  generate_growth_series( $subject )                                                     \_Components\Statistics
```

- [ ] **Step 5: Verify against live data with `wp eval`**

Run:
```bash
wp eval 'foreach (["shows","characters","actors","dead"] as $s){ $r = lwtv_plugin()->generate_growth_series($s); $c = array_column($r,"count"); $mono = $c === array_values(array_filter($c)) ? true : true; $ok = $c == call_user_func(function($c){$p=null;foreach($c as $v){if($p!==null && $v<$p) return ["BAD"];$p=$v;}return $c;},$c); echo $s.": ".count($r)." years, final=".(end($c)?:0).", monotonic=".($ok?"yes":"NO")."\n"; }'
```
Expected: four lines, each with a non-zero `years` count and `monotonic=yes`; the `final` for `shows`/`characters`/`actors` should match the totals shown by `generate_total_counts()`. (Cross-check one: `wp eval 'echo lwtv_plugin()->generate_total_counts("shows");'`.)

- [ ] **Step 6: Delete the scratch file and lint**

```bash
rm plugins/lwtv-plugin/php/statistics/.growth-series-check.php
composer lint-fix
composer lint
```
Expected: `composer lint` exits clean (0 errors) for the two modified PHP files.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/class-stats-counter.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php plugins/lwtv-plugin/php/class-plugin.php
git commit -m "feat(stats): add cumulative growth series for overview sparklines

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Animation JS + enqueue

The shared vanilla-JS driver for count-up numbers and bar/segment growth, enqueued only on the overview.

**Files:**
- Create: `plugins/lwtv-plugin/assets/js/statistics-overview.js`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php` (VERSIONING)

**Interfaces:**
- Produces the DOM contract that later template tasks MUST emit:
  - Count-up element: any element with `data-count-to="<integer>"`. Its visible text is the final formatted number (server-rendered for no-JS/reduced-motion). JS animates 0→target only when motion is allowed.
  - Growable bar/segment: any element with `data-grow-to="<number>"` (a percentage). JS animates inline `width` from `0%`→`<number>%`. Server renders it with `style="width:0"` plus the final value in `data-grow-to`.
  - Both animate on one 1100ms `easeOutCubic` driver; reduced-motion sets finals immediately.

- [ ] **Step 1: Create `statistics-overview.js`**

```js
/**
 * Statistics Overview animations: count-up numbers and grow-in bars.
 * Reads targets from data attributes; respects prefers-reduced-motion.
 */
( function () {
	'use strict';

	var DURATION = 1100;

	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function run() {
		var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		var numbers = Array.prototype.slice.call( document.querySelectorAll( '[data-count-to]' ) );
		var bars    = Array.prototype.slice.call( document.querySelectorAll( '[data-grow-to]' ) );

		if ( reduce ) {
			bars.forEach( function ( el ) {
				el.style.width = parseFloat( el.getAttribute( 'data-grow-to' ) ) + '%';
			} );
			// Numbers already contain their final text server-side; leave as-is.
			return;
		}

		// Reset numbers to 0 before animating.
		numbers.forEach( function ( el ) {
			el.textContent = ( 0 ).toLocaleString();
		} );

		var start = null;
		function step( ts ) {
			if ( null === start ) {
				start = ts;
			}
			var p = Math.min( ( ts - start ) / DURATION, 1 );
			var e = easeOutCubic( p );

			numbers.forEach( function ( el ) {
				var target = parseInt( el.getAttribute( 'data-count-to' ), 10 ) || 0;
				el.textContent = Math.round( e * target ).toLocaleString();
			} );
			bars.forEach( function ( el ) {
				var target = parseFloat( el.getAttribute( 'data-grow-to' ) ) || 0;
				el.style.width = ( e * target ) + '%';
			} );

			if ( p < 1 ) {
				window.requestAnimationFrame( step );
			}
		}
		window.requestAnimationFrame( step );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
```

- [ ] **Step 2: Add a version constant**

In `class-statistics-optimized.php`, add to the `VERSIONING` const array:

```php
		'stats-overview'           => '1.0.0',
```

- [ ] **Step 3: Enqueue on the overview only**

In `class-stats-enqueues.php::enqueue_scripts()`, after the existing `$stat_view` line, add a block that enqueues the overview script only when we're on the main overview (the `statistics` query var defaults to `none` there):

```php
		// Overview page only: count-up + bar-grow animations. No jQuery dependency.
		if ( 'none' === $statistics ) {
			wp_enqueue_script(
				'lwtv-stats-overview',
				LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js',
				array(),
				$versioning['stats-overview'],
				true
			);
		}
```

- [ ] **Step 4: Lint and build**

```bash
composer lint-fix && composer lint
npm run buildquick
```
Expected: `composer lint` clean; build completes without error. (Note: repo `lint:js` only globs `blocks/src/**`, so it will not lint this file — keep it clean by hand.)

- [ ] **Step 5: Smoke-test the enqueue**

Load `https://lwtv.local/statistics/` and confirm in the browser Network/Sources panel that `statistics-overview.js` loads with `?ver=1.0.0`. It will no-op until markup exists — that's expected.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/assets/js/statistics-overview.js plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php
git commit -m "feat(stats): add overview count-up and bar-grow animation script

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Orchestrator rewrite + tab bar

Rewrites `main.php` to compute all data and render the tab bar. Cards/panels stay in their current form this task (page keeps rendering); later tasks swap them.

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main.php`
- Create: `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php`
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `lwtv_plugin()->generate_total_counts()`, `generate_total_dead()`, `generate_growth_series()` (Task 1), `Build\Stations::get_top_stations()`, `Build\Nations::get_top_nations()`.
- Produces: the `$stats_*` variables the later partials read (documented in each partial's docblock). `tabbar.php` needs no variables.

- [ ] **Step 1: Create `tabbar.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Statistics section tab bar (overview view).
 *
 * @package LezWatch.TV
 */

$lwtv_stats_tabs = array(
	array(
		'label' => __( 'Overview', 'lwtv' ),
		'url'   => home_url( '/statistics/' ),
	),
	array(
		'label' => __( 'Shows', 'lwtv' ),
		'url'   => home_url( '/statistics/shows/' ),
	),
	array(
		'label' => __( 'Characters', 'lwtv' ),
		'url'   => home_url( '/statistics/characters/' ),
	),
	array(
		'label' => __( 'Actors', 'lwtv' ),
		'url'   => home_url( '/statistics/actors/' ),
	),
	array(
		'label' => __( 'Nations', 'lwtv' ),
		'url'   => home_url( '/statistics/nations/' ),
	),
	array(
		'label' => __( 'Stations', 'lwtv' ),
		'url'   => home_url( '/statistics/stations/' ),
	),
	array(
		'label' => __( 'Death', 'lwtv' ),
		'url'   => home_url( '/statistics/death/' ),
	),
	array(
		'label' => __( 'This Year', 'lwtv' ),
		'url'   => home_url( '/this-year/' ),
	),
);
?>
<nav class="lwtv-stats-tabs" aria-label="<?php esc_attr_e( 'Statistics sections', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_stats_tabs as $lwtv_stats_tab ) {
		// Overview is the active tab on this view.
		$lwtv_is_active   = ( home_url( '/statistics/' ) === $lwtv_stats_tab['url'] );
		$lwtv_tab_classes = 'lwtv-stats-tab' . ( $lwtv_is_active ? ' is-active' : '' );
		printf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $lwtv_tab_classes ),
			esc_url( $lwtv_stats_tab['url'] ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_stats_tab['label'] )
		);
	}
	?>
</nav>
```

- [ ] **Step 2: Rewrite `main.php` as the orchestrator**

Replace the entire contents of `templates/main.php` with:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * The main statistics overview page — redesigned.
 *
 * Computes all server-side data, then includes focused partials.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Stations as Build_Stations;
use LWTV\Statistics\Build\Nations as Build_Nations;

// Totals.
$stats_shows      = (int) lwtv_plugin()->generate_total_counts( 'shows' );
$stats_characters = (int) lwtv_plugin()->generate_total_counts( 'characters' );
$stats_actors     = (int) lwtv_plugin()->generate_total_counts( 'actors' );
$stats_dead       = (int) lwtv_plugin()->generate_total_dead( 'characters' );

// Growth series for the sparklines.
$stats_series = array(
	'shows'      => lwtv_plugin()->generate_growth_series( 'shows' ),
	'characters' => lwtv_plugin()->generate_growth_series( 'characters' ),
	'actors'     => lwtv_plugin()->generate_growth_series( 'actors' ),
	'dead'       => lwtv_plugin()->generate_growth_series( 'dead' ),
);

// Panels data.
$stats_top_stations   = ( new Build_Stations() )->get_top_stations( 7 );
$stats_top_nations    = ( new Build_Nations() )->get_top_nations( 4 );
$stats_total_stations = (int) wp_count_terms( array( 'taxonomy' => 'lez_stations' ) );
$stats_total_nations  = (int) wp_count_terms( array( 'taxonomy' => 'lez_country' ) );

// Derived: "1 in N" ratio for the death band (guard against divide-by-zero).
$stats_dead_ratio = ( $stats_dead > 0 ) ? (int) round( $stats_characters / $stats_dead ) : 0;

$stats_partials = plugin_dir_path( __FILE__ ) . 'main/';
?>

<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'tabbar.php';
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'overview.php';
	// Death band + panels are added in later tasks:
	// include $stats_partials . 'bury-your-gays.php';
	// include $stats_partials . 'where-tv-lives.php';
	// include $stats_partials . 'around-the-world.php';
	?>
</div>
```

> The two old table includes (`top-nations.php` / `top-stations.php`) are intentionally dropped from `main.php` here; the old `overview.php` still renders the four cards until Task 4. This leaves a working page (tab bar + old cards). The old table partials remain on disk until Task 8.

- [ ] **Step 3: Add tab-bar SCSS (light) to `scss/addons/_stats.scss`**

Append inside the file (top level, after the existing `.statistics { … }` block — the overview wrapper lives under `.statistics`):

```scss
/* Statistics Overview redesign */

.statistics {

	.lwtv-stats-overview {
		max-width: 1120px;
		margin: 0 auto;
	}

	.lwtv-stats-tabs {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		padding: 4px;
		margin-bottom: 1.5rem;
		background-color: colors.$lwtv-grey;
		border-radius: 10px;
	}

	.lwtv-stats-tab {
		padding: 6px 14px;
		font-size: 0.875rem;
		font-weight: 500;
		color: colors.$lwtv-medgrey;
		text-decoration: none;
		border-radius: 8px;

		&:hover {
			color: colors.$lwtv-purple;
		}

		&.is-active {
			color: colors.$lwtv-dkgrey;
			background-color: colors.$white;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
		}
	}
}
```

- [ ] **Step 4: Add tab-bar SCSS (dark) to `scss/partials/_colors-dark.scss`**

Inside the existing `.statistics { … }` dark block (near the other stats overrides), add:

```scss
		.lwtv-stats-tabs {
			background-color: $lwtv-medgrey;
		}

		.lwtv-stats-tab {
			color: $lwtv-ltpink;

			&.is-active {
				color: $lwtv-dkgrey;
				background-color: $lwtv-ltpink;
			}
		}
```

- [ ] **Step 5: Lint + build**

```bash
composer lint-fix && composer lint
npm run buildquick
```
Expected: PHP lint clean; SCSS compiles.

- [ ] **Step 6: Manual verify**

Load `https://lwtv.local/statistics/` in light and dark mode: the tab bar renders as a pill row with "Overview" active; the four old cards still show below it; every tab links to the correct URL; no console errors.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main.php plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php scss/addons/_stats.scss scss/partials/_colors-dark.scss
git commit -m "feat(stats): overview orchestrator and section tab bar

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Metric cards + sparklines

Rewrites `overview.php` into the four metric cards with count-up numbers, icon tiles, and inline SVG sparklines. Wires the Task 2 JS contract.

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main/overview.php`
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `$stats_shows`, `$stats_characters`, `$stats_actors`, `$stats_dead`, `$stats_series`, `$stats_dead_ratio` (from `main.php`, Task 3); the JS `data-count-to` contract (Task 2).
- Produces: nothing consumed downstream (leaf partial).

- [ ] **Step 1: Verify the sparkline point math (pure logic)**

Create scratch `plugins/lwtv-plugin/php/statistics/.sparkline-check.php`:

```php
<?php
function lwtv_stats_sparkline_points( array $series, int $w = 120, int $h = 26 ): string {
	$counts = array_column( $series, 'count' );
	$n      = count( $counts );
	if ( $n < 2 ) {
		return '';
	}
	$max   = max( $counts );
	$min   = min( $counts );
	$range = ( $max - $min ) ?: 1;
	$pts   = array();
	foreach ( array_values( $counts ) as $i => $c ) {
		$x     = round( ( $i / ( $n - 1 ) ) * $w, 2 );
		$y     = round( $h - ( ( $c - $min ) / $range ) * $h, 2 );
		$pts[] = $x . ',' . $y;
	}
	return implode( ' ', $pts );
}

$series = array(
	array( 'year' => 2016, 'count' => 0 ),
	array( 'year' => 2017, 'count' => 50 ),
	array( 'year' => 2018, 'count' => 100 ),
);
$pts = lwtv_stats_sparkline_points( $series );
// First point at left/bottom, last at right/top.
assert( str_starts_with( $pts, '0,26' ), 'sparkline: starts bottom-left' );
assert( str_ends_with( $pts, '120,0' ), 'sparkline: ends top-right' );
assert( lwtv_stats_sparkline_points( array( array( 'year' => 2020, 'count' => 5 ) ) ) === '', 'sparkline: single point → empty' );
echo "sparkline points check: PASS\n";
```

Run: `php plugins/lwtv-plugin/php/statistics/.sparkline-check.php`
Expected: `sparkline points check: PASS`

- [ ] **Step 2: Rewrite `overview.php`**

Replace the entire contents of `templates/main/overview.php` with:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Overview metric cards — redesigned.
 *
 * @package LezWatch.TV
 *
 * @var int   $stats_shows
 * @var int   $stats_characters
 * @var int   $stats_actors
 * @var int   $stats_dead
 * @var array $stats_series      Keyed shows|characters|actors|dead => growth series.
 * @var int   $stats_dead_ratio  "1 in N".
 */

if ( ! function_exists( 'lwtv_stats_sparkline_points' ) ) {
	/**
	 * Convert a cumulative series into SVG polyline points within a viewBox.
	 *
	 * @param array $series Cumulative series [ ['year'=>int,'count'=>int], … ].
	 * @param int   $w      viewBox width.
	 * @param int   $h      viewBox height.
	 * @return string Space-separated "x,y" pairs, or '' if fewer than 2 points.
	 */
	function lwtv_stats_sparkline_points( array $series, int $w = 120, int $h = 26 ): string {
		$counts = array_column( $series, 'count' );
		$n      = count( $counts );
		if ( $n < 2 ) {
			return '';
		}
		$max   = max( $counts );
		$min   = min( $counts );
		$range = ( $max - $min ) ?: 1;
		$pts   = array();
		foreach ( array_values( $counts ) as $i => $c ) {
			$x     = round( ( $i / ( $n - 1 ) ) * $w, 2 );
			$y     = round( $h - ( ( $c - $min ) / $range ) * $h, 2 );
			$pts[] = $x . ',' . $y;
		}
		return implode( ' ', $pts );
	}
}

$stats_cards = array(
	array(
		'type'    => 'shows',
		'class'   => 'shows',
		'label'   => __( 'Shows', 'lwtv' ),
		'count'   => $stats_shows,
		'caption' => __( 'TV series & films', 'lwtv' ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-television',
	),
	array(
		'type'    => 'characters',
		'class'   => 'characters',
		'label'   => __( 'Characters', 'lwtv' ),
		'count'   => $stats_characters,
		'caption' => __( 'Queer characters tracked', 'lwtv' ),
		'svg'     => 'user.svg',
		'icon'    => 'svg-user',
	),
	array(
		'type'    => 'actors',
		'class'   => 'actors',
		'label'   => __( 'Actors', 'lwtv' ),
		'count'   => $stats_actors,
		'caption' => __( 'Who played them', 'lwtv' ),
		'svg'     => 'film-strip.svg',
		'icon'    => 'svg-film',
	),
	array(
		'type'    => 'dead',
		'class'   => 'dead-characters',
		'label'   => __( 'Dead', 'lwtv' ),
		'count'   => $stats_dead,
		/* translators: %d is the "1 in N" ratio of dead characters. */
		'caption' => ( $stats_dead_ratio > 0 ) ? sprintf( __( '1 in %d characters', 'lwtv' ), $stats_dead_ratio ) : __( 'Characters lost', 'lwtv' ),
		'svg'     => 'skull.svg',
		'icon'    => 'svg-skull',
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Database, Live', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid">
	<?php
	foreach ( $stats_cards as $stats_card ) {
		$stats_points = lwtv_stats_sparkline_points( $stats_series[ $stats_card['type'] ] ?? array() );
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $stats_card['class'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $stats_card['label'] ); ?></span>
				<span class="lwtv-metric-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $stats_card['svg'], icon: $stats_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $stats_card['count']; ?>"><?php echo esc_html( number_format_i18n( $stats_card['count'] ) ); ?></span>
			<?php if ( '' !== $stats_points ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" role="img" aria-hidden="true">
					<polyline points="<?php echo esc_attr( $stats_points ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $stats_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>
```

> Icon fallback classes (`svg-television`, etc.) are the Font-Awesome fallback only used when the sprite lacks the id; the SVG sprite (`tv`, `user`, `film-strip`, `skull`) is the real source. If any fallback `<i>` shows instead of the SVG, confirm the sprite id via `symbolicons.json` and correct the `svg:`/`icon:` pair.

- [ ] **Step 3: Card SCSS (light) in `scss/addons/_stats.scss`**

Add inside the `.statistics { … }` overview block from Task 3:

```scss
	.lwtv-stats-eyebrow {
		font-size: 0.625rem;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: colors.$lwtv-medgrey;

		&--section {
			display: block;
			margin-bottom: 0.75rem;
		}
	}

	.lwtv-metric-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 16px;
		margin-bottom: 1.5rem;
	}

	.lwtv-metric-card {
		display: flex;
		flex-direction: column;
		padding: 18px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;

		// .card-header base sets display:block etc.; reassert what we need.
		.lwtv-stats-eyebrow {
			color: inherit; // inherits the card-header type color (shows/characters/etc).
		}
	}

	.lwtv-metric-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.lwtv-metric-icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 38px;
		height: 38px;
		border-radius: 8px;
		background-color: rgba(0, 0, 0, 0.06);
	}

	.lwtv-metric-number {
		font-size: 2.5rem;
		font-weight: 700;
		line-height: 1.1;
		font-variant-numeric: tabular-nums;
		color: colors.$lwtv-dkgrey;
	}

	.lwtv-sparkline {
		width: 100%;
		height: 26px;
		margin: 6px 0;
		// stroke uses currentColor => the card-header type color.
	}

	.lwtv-metric-caption {
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
	}

	@media (max-width: 767px) {

		.lwtv-metric-grid {
			grid-template-columns: repeat(2, 1fr);
		}
	}

	@media (max-width: 420px) {

		.lwtv-metric-grid {
			grid-template-columns: 1fr;
		}
	}
```

> The `.card-header` class supplies the type color as the card's `color`, so the eyebrow (via `color: inherit`) and the sparkline stroke (via `currentColor`) both pick it up. The card surface stays `.bg-light`; the `.card-header` background is neutralized in the next step so the whole card doesn't take the tint.

- [ ] **Step 4: Neutralize the card-header background for metric cards, both modes**

Because `.lwtv-metric-card` also carries `.card-header.<type>` (to get the type color), its `background-color`/`border-color` from `_stats.scss` would tint the whole card. Override the surface while keeping the text color. In `scss/addons/_stats.scss` inside `.statistics`:

```scss
	.card-header.lwtv-metric-card {
		background-color: transparent; // .bg-light provides the surface.
		border-color: colors.$lwtv-bordergrey;
	}
```

Add the equivalent in the dark `.statistics` block in `_colors-dark.scss`:

```scss
		.card-header.lwtv-metric-card {
			background-color: transparent;
			border-color: $lwtv-bordergrey;
		}

		.lwtv-metric-number {
			color: $white;
		}

		.lwtv-metric-icon {
			background-color: rgba(255, 255, 255, 0.08);
		}
```

- [ ] **Step 5: Lint + build**

```bash
rm plugins/lwtv-plugin/php/statistics/.sparkline-check.php
composer lint-fix && composer lint
npm run buildquick
```
Expected: clean.

- [ ] **Step 6: Manual verify**

At `https://lwtv.local/statistics/`: four cards render with correct type colors on eyebrow + icon + sparkline; numbers count up 0→target once on load (~1.1s); reduced-motion (emulate in DevTools) shows final numbers with no animation; dark mode keeps numbers readable (white) and sparklines colored; narrow viewport collapses to 2×2 then 1-up. Cross-check the four numbers against the sub-section pages.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main/overview.php scss/addons/_stats.scss scss/partials/_colors-dark.scss
git commit -m "feat(stats): metric cards with count-up and growth sparklines

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Bury Your Gays band

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/main/bury-your-gays.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main.php` (enable the include)
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `$stats_dead`, `$stats_dead_ratio` (from `main.php`).
- Produces: nothing downstream.

- [ ] **Step 1: Create `bury-your-gays.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Bury Your Gays" death callout band.
 *
 * @package LezWatch.TV
 *
 * @var int $stats_dead
 * @var int $stats_dead_ratio
 */

if ( $stats_dead <= 0 ) {
	return;
}
?>
<div class="lwtv-byg card-header dead-characters">
	<span class="lwtv-byg-icon">
		<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</span>
	<div class="lwtv-byg-body">
		<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?></span>
		<p class="lwtv-byg-line">
			<?php
			printf(
				/* translators: 1: number of dead characters, 2: the "1 in N" ratio. */
				wp_kses_post( __( '<strong data-count-to="%1$d">%3$s</strong> characters &mdash; 1 in %2$d &mdash; have been killed off.', 'lwtv' ) ),
				(int) $stats_dead,
				(int) $stats_dead_ratio,
				esc_html( number_format_i18n( $stats_dead ) )
			);
			?>
		</p>
		<p class="lwtv-byg-desc"><?php esc_html_e( 'The most-tracked trope in queer TV, quantified across the whole database.', 'lwtv' ); ?></p>
	</div>
	<a class="lwtv-byg-btn btn" href="<?php echo esc_url( home_url( '/statistics/death/' ) ); ?>">
		<?php esc_html_e( 'Death Statistics', 'lwtv' ); ?>
		<?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right.svg', icon: 'svg-arrow-right', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
</div>
```

> `%3$s` (the pre-formatted number) is the visible text; `%1$d` populates `data-count-to` for the JS. `wp_kses_post` keeps the `<strong data-count-to>` attribute (data-* is allowed by kses on standard elements).

- [ ] **Step 2: Enable the include in `main.php`**

In `templates/main.php`, uncomment / add the band include right after `overview.php`:

```php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'bury-your-gays.php';
```

- [ ] **Step 3: Band SCSS (light) in `scss/addons/_stats.scss`** (inside `.statistics`)

```scss
	.lwtv-byg {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 16px 20px;
		margin-bottom: 1.5rem;
		border: 1px solid; // border-color comes from .card-header.dead-characters.
		border-left-width: 3px;
		border-radius: 14px;
	}

	.lwtv-byg-icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 38px;
		height: 38px;
		border-radius: 8px;
		background-color: rgba(0, 0, 0, 0.06);
		flex: 0 0 auto;
	}

	.lwtv-byg-body {
		flex: 1 1 auto;
	}

	.lwtv-byg-line {
		margin: 0.15rem 0;
		font-size: 1.125rem;
		font-weight: 600;
	}

	.lwtv-byg-desc {
		margin: 0;
		font-size: 0.8125rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-byg-btn {
		flex: 0 0 auto;
		color: colors.$white;
		background-color: colors.$lwtv-dkpink;
		border-color: colors.$lwtv-dkpink;

		&:hover {
			color: colors.$white;
			background-color: colors.$lwtv-pink;
		}
	}

	@media (max-width: 575px) {

		.lwtv-byg {
			flex-wrap: wrap;
		}
	}
```

- [ ] **Step 4: Band SCSS (dark) in `_colors-dark.scss`** (inside dark `.statistics`)

```scss
		.lwtv-byg-icon {
			background-color: rgba(255, 255, 255, 0.08);
		}

		.lwtv-byg-desc {
			color: $lwtv-ltpink;
		}
```

> The band tint/border/text come from the existing dark `.card-header.dead-characters` rule (`#e74c3c` / `#2e0d0d` / `#4d1a1a`), so no extra work there.

- [ ] **Step 5: Lint + build**

```bash
composer lint-fix && composer lint
npm run buildquick
```

- [ ] **Step 6: Manual verify**

Band appears below the cards: red scheme in both modes, left accent border, skull icon, headline with the dead count counting up in sync with the cards, and a right-aligned "Death Statistics →" button linking to `/statistics/death/`. On very narrow widths it wraps cleanly.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main/bury-your-gays.php plugins/lwtv-plugin/php/statistics/templates/main.php scss/addons/_stats.scss scss/partials/_colors-dark.scss
git commit -m "feat(stats): Bury Your Gays death callout band

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: "Where queer TV lives" panel

Replaces the old top-stations table with the networks bar panel.

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/main/where-tv-lives.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main.php`
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `$stats_top_stations` (array of `['slug','name','count']`, from `get_top_stations(7)`), `$stats_shows`, `$stats_total_stations`.
- Produces: opens a two-column panel row `.lwtv-panels` that Task 7 also uses.

- [ ] **Step 1: Create `where-tv-lives.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Where queer TV lives" — top networks panel.
 *
 * @package LezWatch.TV
 *
 * @var array $stats_top_stations
 * @var int   $stats_shows
 * @var int   $stats_total_stations
 */

$wtl_stations = is_array( $stats_top_stations ) ? $stats_top_stations : array();
$wtl_top      = ! empty( $wtl_stations ) ? max( array_map( fn( $s ) => (int) $s['count'], $wtl_stations ) ) : 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'satellite-signal.svg', icon: 'svg-bullhorn', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Where queer TV lives', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: number shown (7), 2: total networks. */
					esc_html__( 'Shows by network — top %1$d of %2$s stations & networks', 'lwtv' ),
					(int) count( $wtl_stations ),
					esc_html( number_format_i18n( $stats_total_stations ) )
				);
				?>
			</p>
		</div>
	</header>

	<div class="lwtv-bars">
		<?php
		foreach ( $wtl_stations as $wtl_station ) {
			$wtl_count = (int) $wtl_station['count'];
			$wtl_pct   = ( $stats_shows > 0 ) ? round( ( $wtl_count / $stats_shows ) * 100, 1 ) : 0;
			$wtl_width = ( $wtl_top > 0 ) ? round( ( $wtl_count / $wtl_top ) * 100, 1 ) : 0;
			?>
			<div class="lwtv-bar-row">
				<a class="lwtv-bar-name" href="<?php echo esc_url( home_url( '/statistics/stations/?station=' . $wtl_station['slug'] ) ); ?>"><?php echo esc_html( $wtl_station['name'] ); ?></a>
				<div class="progress lwtv-bar-track">
					<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( $wtl_width ); ?>" aria-valuenow="<?php echo esc_attr( $wtl_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $wtl_top ); ?>"></div>
				</div>
				<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( $wtl_count ) . ' · ' . $wtl_pct . '%' ); ?></span>
			</div>
			<?php
		}
		?>
	</div>

	<a class="lwtv-panel-foot" href="<?php echo esc_url( home_url( '/statistics/stations/' ) ); ?>">
		<?php
		printf(
			/* translators: %s: total number of networks. */
			esc_html__( 'View all %s networks →', 'lwtv' ),
			esc_html( number_format_i18n( $stats_total_stations ) )
		);
		?>
	</a>
</section>
```

- [ ] **Step 2: Open the panels row in `main.php`**

Replace the commented panel lines in `templates/main.php` with a two-column wrapper:

```php
	echo '<div class="lwtv-panels">';
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'where-tv-lives.php';
	// around-the-world.php added in Task 7.
	echo '</div>';
```

- [ ] **Step 3: Panels + bars SCSS (light) in `scss/addons/_stats.scss`** (inside `.statistics`)

```scss
	.lwtv-panels {
		display: grid;
		grid-template-columns: 1.5fr 1fr;
		gap: 16px;
	}

	.lwtv-panel {
		padding: 20px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;
	}

	.lwtv-panel-head {
		display: flex;
		gap: 10px;
		align-items: flex-start;
		margin-bottom: 1rem;
	}

	.lwtv-panel-icon {
		color: colors.$lwtv-dkpink;
	}

	.lwtv-panel-title {
		margin: 0;
		font-size: 1rem;
		font-weight: 600;
	}

	.lwtv-panel-sub {
		margin: 0;
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-bar-row {
		display: grid;
		grid-template-columns: 88px 1fr auto;
		gap: 10px;
		align-items: center;
		margin-bottom: 0.6rem;
	}

	.lwtv-bar-name {
		font-size: 0.8125rem;
		font-weight: 500;
	}

	.lwtv-bar-track {
		height: 8px;
		border-radius: 999px;

		.progress-bar {
			border-radius: 999px;
			transition: none; // width is JS-driven.
		}
	}

	.lwtv-bar-label {
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
	}

	.lwtv-panel-foot {
		display: inline-block;
		margin-top: 0.75rem;
		font-size: 0.8125rem;
	}

	@media (max-width: 767px) {

		.lwtv-panels {
			grid-template-columns: 1fr;
		}
	}
```

> The `.lwtv-bar-track` reuses Bootstrap `.progress` + `.progress-bar`; the teal fill (light) / `$lwtv-ltpink` (dark) already come from the existing `.statistics .progress-bar` rules — no color declared here.

- [ ] **Step 4: Dark tweak in `_colors-dark.scss`** (inside dark `.statistics`)

```scss
		.lwtv-panel-icon {
			color: $lwtv-ltpink;
		}
```

- [ ] **Step 5: Lint + build**

```bash
composer lint-fix && composer lint
npm run buildquick
```

- [ ] **Step 6: Manual verify**

Left panel shows 7 network rows; bars animate 0→width on load in sync with the counters; the right label reads "`count · pct%`"; the footer links to `/statistics/stations/`. Panel sits in a 1.5fr/1fr grid (the right cell is empty until Task 7). Dark mode: bars are pink, text readable.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main/where-tv-lives.php plugins/lwtv-plugin/php/statistics/templates/main.php scss/addons/_stats.scss scss/partials/_colors-dark.scss
git commit -m "feat(stats): 'Where queer TV lives' networks panel

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: "Around the world" panel + nations ramp

Replaces the old top-nations table with the stacked-share nations panel.

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/main/around-the-world.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main.php`
- Modify: `scss/addons/_stats.scss`
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `$stats_top_nations` (from `get_top_nations(4)`), `$stats_shows`, `$stats_total_nations`.
- Produces: nothing downstream. Closes the `.lwtv-panels` row.

- [ ] **Step 1: Create `around-the-world.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Around the world" — nations stacked-share panel.
 *
 * @package LezWatch.TV
 *
 * @var array $stats_top_nations
 * @var int   $stats_shows
 * @var int   $stats_total_nations
 */

$atw_nations = is_array( $stats_top_nations ) ? $stats_top_nations : array();

// Build legend rows: up to 4 named nations + an aggregated remainder to 100%.
$atw_rows      = array();
$atw_named_pct = 0.0;
foreach ( $atw_nations as $atw_nation ) {
	$atw_pct        = ( $stats_shows > 0 ) ? round( ( (int) $atw_nation['count'] / $stats_shows ) * 100, 1 ) : 0;
	$atw_named_pct += $atw_pct;
	$atw_rows[]     = array(
		'name' => $atw_nation['name'],
		'pct'  => $atw_pct,
	);
}
$atw_other_count = max( 0, $stats_total_nations - count( $atw_rows ) );
$atw_other_pct   = max( 0, round( 100 - $atw_named_pct, 1 ) );
if ( $atw_other_count > 0 && $atw_other_pct > 0 ) {
	$atw_rows[] = array(
		/* translators: %s: number of remaining nations. */
		'name' => sprintf( _n( '%s other nation', '%s other nations', $atw_other_count, 'lwtv' ), number_format_i18n( $atw_other_count ) ),
		'pct'  => $atw_other_pct,
	);
}

// Raspberry ramp order: darkest (largest) → lightest (others). Matches SCSS nth-child.
$atw_top_pct  = $atw_rows[0]['pct'] ?? 0;
$atw_top_name = $atw_rows[0]['name'] ?? '';
$atw_n_in_ten = ( $atw_top_pct > 0 ) ? (int) round( $atw_top_pct / 10 ) : 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'globe.svg', icon: 'svg-globe', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Around the world', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: total shows, 2: total nations. */
					esc_html__( '%1$s shows across %2$s nations', 'lwtv' ),
					esc_html( number_format_i18n( $stats_shows ) ),
					esc_html( number_format_i18n( $stats_total_nations ) )
				);
				?>
			</p>
		</div>
	</header>

	<?php if ( $atw_n_in_ten > 0 && '' !== $atw_top_name ) : ?>
		<p class="lwtv-atw-headline">
			<?php
			printf(
				/* translators: 1: "N in 10" numerator, 2: top nation name. */
				esc_html__( 'Nearly %1$d in 10 shows come from %2$s.', 'lwtv' ),
				(int) $atw_n_in_ten,
				esc_html( $atw_top_name )
			);
			?>
		</p>
	<?php endif; ?>

	<div class="lwtv-share-bar" role="img" aria-label="<?php esc_attr_e( 'Share of shows by nation', 'lwtv' ); ?>">
		<?php
		foreach ( $atw_rows as $atw_index => $atw_row ) {
			printf(
				'<span class="lwtv-share-seg" style="width:0" data-grow-to="%1$s"></span>',
				esc_attr( $atw_row['pct'] )
			);
		}
		?>
	</div>

	<ul class="lwtv-legend">
		<?php
		foreach ( $atw_rows as $atw_row ) {
			printf(
				'<li class="lwtv-legend-row"><span class="lwtv-legend-dot"></span><span class="lwtv-legend-name">%1$s</span><span class="lwtv-legend-pct">%2$s%%</span></li>',
				esc_html( $atw_row['name'] ),
				esc_html( $atw_row['pct'] )
			);
		}
		?>
	</ul>

	<a class="lwtv-panel-foot" href="<?php echo esc_url( home_url( '/statistics/nations/' ) ); ?>">
		<?php
		printf(
			/* translators: %s: total number of nations. */
			esc_html__( 'View all %s nations →', 'lwtv' ),
			esc_html( number_format_i18n( $stats_total_nations ) )
		);
		?>
	</a>
</section>
```

- [ ] **Step 2: Add the include in `main.php`**

Inside the `.lwtv-panels` wrapper (Task 6 Step 2), after the `where-tv-lives.php` include and before `echo '</div>';`:

```php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'around-the-world.php';
```

- [ ] **Step 3: Share bar + legend SCSS (light) in `scss/addons/_stats.scss`**

At the top of `_stats.scss`, ensure the Sass color module is available (add if not already present at the very top of the file):

```scss
@use "sass:color";
```

Then inside `.statistics` add. The ramp uses the tokens + two `color.mix()` steps (no pasted hex), and colors segments/dots by position:

```scss
	.lwtv-atw-headline {
		margin: 0 0 0.75rem;
		font-size: 1.25rem;
		font-weight: 700;
		line-height: 1.25;
	}

	.lwtv-share-bar {
		display: flex;
		width: 100%;
		height: 14px;
		overflow: hidden;
		border-radius: 999px;
		margin-bottom: 1rem;
	}

	.lwtv-share-seg {
		height: 100%;
		transition: none; // width is JS-driven.
	}

	.lwtv-legend {
		padding: 0;
		margin: 0 0 0.5rem;
		list-style: none;
	}

	.lwtv-legend-row {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 3px 0;
		font-size: 0.8125rem;
	}

	.lwtv-legend-dot {
		width: 10px;
		height: 10px;
		border-radius: 50%;
		flex: 0 0 auto;
	}

	.lwtv-legend-name {
		flex: 1 1 auto;
	}

	.lwtv-legend-pct {
		color: colors.$lwtv-medgrey;
		font-variant-numeric: tabular-nums;
	}

	// Raspberry ramp: darkest (largest share) → lightest (others).
	$ramp-1: colors.$lwtv-dkpink;
	$ramp-2: colors.$lwtv-pink;
	$ramp-3: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 55%);
	$ramp-4: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 25%);
	$ramp-5: colors.$lwtv-ltpink;

	.lwtv-share-seg:nth-child(1),
	.lwtv-legend-row:nth-child(1) .lwtv-legend-dot {
		background-color: $ramp-1;
	}

	.lwtv-share-seg:nth-child(2),
	.lwtv-legend-row:nth-child(2) .lwtv-legend-dot {
		background-color: $ramp-2;
	}

	.lwtv-share-seg:nth-child(3),
	.lwtv-legend-row:nth-child(3) .lwtv-legend-dot {
		background-color: $ramp-3;
	}

	.lwtv-share-seg:nth-child(4),
	.lwtv-legend-row:nth-child(4) .lwtv-legend-dot {
		background-color: $ramp-4;
	}

	.lwtv-share-seg:nth-child(5),
	.lwtv-legend-row:nth-child(5) .lwtv-legend-dot {
		background-color: $ramp-5;
	}
```

- [ ] **Step 4: Dark share bar in `_colors-dark.scss`**

The ramp reads fine on dark; only the "others" lightest step can wash out. Inside dark `.statistics` add a border to the lightest dot/segment for contrast:

```scss
		.lwtv-share-seg:nth-child(5) {
			outline: 1px solid rgba(255, 255, 255, 0.15);
			outline-offset: -1px;
		}
```

- [ ] **Step 5: Lint + build**

```bash
composer lint-fix && composer lint
npm run buildquick
```

- [ ] **Step 6: Manual verify**

Right panel shows the headline ("Nearly N in 10 shows come from {top nation}."), a single 100%-wide stacked bar that grows in on load, and a ranked legend whose dots match the bar segments and whose percentages sum to ~100%. Footer links to `/statistics/nations/`. Confirm the ramp goes dark→light and the "N other nations" row is present. Check both modes and narrow (panels stack).

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main/around-the-world.php plugins/lwtv-plugin/php/statistics/templates/main.php scss/addons/_stats.scss scss/partials/_colors-dark.scss
git commit -m "feat(stats): 'Around the world' nations share panel

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Remove dead partials + full verification

**Files:**
- Delete: `plugins/lwtv-plugin/php/statistics/templates/main/top-stations.php`
- Delete: `plugins/lwtv-plugin/php/statistics/templates/main/top-nations.php`

**Interfaces:**
- Consumes/Produces: none — cleanup + final gate.

- [ ] **Step 1: Confirm the old partials are unreferenced**

Run: `grep -rn "top-stations\|top-nations" plugins/ page-templates/ scss/`
Expected: no matches (only `main.php` used them, and it no longer does).

- [ ] **Step 2: Delete the files**

```bash
git rm plugins/lwtv-plugin/php/statistics/templates/main/top-stations.php plugins/lwtv-plugin/php/statistics/templates/main/top-nations.php
```

- [ ] **Step 3: Full lint + build**

```bash
composer lint-fix && composer lint
npm run lint:css
npm run buildquick
```
Expected: all clean. (`npm run lint:js` covers only `blocks/src/**`; run it too to confirm nothing there regressed.)

- [ ] **Step 4: Full manual verification pass**

At `https://lwtv.local/statistics/`, confirm the complete Overview against `design_handoff_statistics_overview/screenshots/`:
- Tab bar, "THE DATABASE, LIVE" eyebrow, four metric cards (count-up + sparklines), Bury Your Gays band, and the two panels — in that vertical order.
- Light mode matches `overview-light.png` / `overview-light-panels.png`; dark mode matches `overview-dark.png` / `overview-dark-panels.png`.
- Numbers cross-check against sub-section pages (Shows/Characters/Actors totals; Death count).
- Reduced-motion (DevTools emulate): everything renders at final values instantly, no animation.
- Responsive: at ≤767px the metric grid is 2×2 and panels stack; at ≤420px cards are 1-up.
- No JS console errors; `statistics-overview.js` loads.
- Visit `/statistics/shows/` (and one other sub-page) to confirm those views are unchanged (regression check).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(stats): remove superseded overview table partials

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- Tab bar → Task 3. ✓
- Metric cards + count-up + sparklines → Tasks 1 (data), 2 (JS), 4 (markup/style). ✓
- Bury Your Gays band → Task 5. ✓
- Where queer TV lives → Task 6. ✓
- Around the world (ramp via `color.mix()`) → Task 7. ✓
- Growth series data helper + template tag + docblock → Task 1. ✓
- Symbolicons (not Lucide) → Tasks 4–7 use `get_symbolicon`. ✓
- Palette reuse / dark mode via existing classes → every SCSS step scopes under `.statistics` and reuses `.card-header.*`, `.bg-light`, `.progress-bar`, `$lwtv-*`. ✓
- Reduced-motion + same-driver animation → Task 2. ✓
- Divide-by-zero guards → `main.php` ratio, panel percentages, sparkline range. ✓
- Remove old table partials → Task 8. ✓
- i18n throughout → all strings wrapped with `'lwtv'`. ✓
- No URL/routing/scoring changes → confirmed; sub-pages untouched (Task 8 regression check). ✓

**Placeholder scan:** No TBD/TODO left; every code step contains full code. The only deferred item from the spec (dead-card sparkline source) is resolved here: it reuses `Build\Dead::generate_years_data()` cumulatively (Task 1, Step 3). Nation split resolved: top 4 named + aggregated remainder to 100% (Task 7).

**Type consistency:** `get_growth_series($subject)`/`generate_growth_series($subject)` return `[['year'=>int,'count'=>int],…]` — consumed identically by `main.php` and `lwtv_stats_sparkline_points()`. `data-count-to`/`data-grow-to` attribute names match between Task 2 (JS) and Tasks 4/5/6/7 (markup). `$stats_*` variable names defined in `main.php` (Task 3) match every partial's docblock and usage.

## Known follow-ups (out of scope)

- If the sprite lacks `film-strip`/`user`/`tv`/`skull` ids under those exact names, the FA fallback `<i>` renders; adjust the `svg:`/`icon:` pairs after checking `symbolicons.json` (flagged inline in Task 4).
- Nation shares can slightly overlap because a show may have multiple nations; the panel normalizes the remainder to 100% by design. If exact non-overlapping shares are later required, that's a separate data change.
