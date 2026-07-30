# Statistics on Death Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/statistics/death/` (7 single-page views) on the shared stats shell — server-rendered counters, donuts, ranked bars, a new year bar chart, and a sortable death record — replacing the last Chart.js output.

**Architecture:** Keep `death.php`'s `$valid_views` routing; swap `death/navbar.php` for the shared `.lwtv-stats-subnav`; per-view templates render existing partials (`donut`, `ranked-bars`) + one new `year-bars` partial + a real `tablesorter` table. Data via `generate_dead_statistics(…, 'array'|'time'|'average')`.

**Tech Stack:** PHP 8.1+ (`LWTV\` PSR-4, `lwtv_plugin()`), Bootstrap 5, SCSS, inline SVG, count-up JS, jQuery tablesorter (already enqueued), Symbolicons. No PHPUnit — gates are PHPCS + build + browser.

## Global Constraints

- **Reuse mandate:** reuse existing components/tokens; NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Death's primary accent is **red** (`dead-characters` family).
- **Family map:** death figures/counters/who-dies/peak → red (`dead-characters`); character/show/station/nation labels + neutral bars → green (`characters`); links + sub-nav underline → pink; ramp segments → `$ramp-1..5`.
- **Peak year is `max()`-derived**, never hardcoded; reuse it in every peak mention.
- **PHP:** WordPress-Extra PHPCS clean. `get_symbolicon` echoes carry `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`; all other output escaped; `esc_url` character links. i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts. Guard divisors; harden single-key/empty unwrap.
- **Animation:** count-up `data-count-to` + visible final value; donut legend bars `data-grow-to` + `width:0`. Donut rings, **year bars, and the List table are static** (no grow). Reduced-motion → finals (shared JS).
- **Build:** `npm run buildquick` needs Node 24 (`source ~/.nvm/nvm.sh; nvm use`). Regenerates `style.css`/`style.min.css` — include when SCSS changed.
- **Editor hazard:** stylelint fix-on-save can mangle `_stats.scss` (known pre-existing garbled comment near line ~209 is NOT yours). After SCSS edits + build, `git diff` and confirm only intended changes.
- **Commit hygiene:** stage ONLY the files a task names (explicit paths). NEVER `git add -A` — the user edits this branch in parallel. Never pass a `git rm`'d path to `git add` (stage deletions via `git rm`).
- **Chart.js:** enqueue untouched (This Year still uses it). Death simply stops emitting charts.

## Environment (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`.
- **Site:** Local, `https://lwtv.local` (self-signed → `curl -sk`). Views: `/statistics/death/`, `.../death/characters/`, `.../shows/`, `.../stations/`, `.../nations/`, `.../years/`, `.../list/`.
- **wp-cli:** `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>`

## Data shapes (verified live)

- `generate_dead_statistics('characters','all','array')` (**after Task 1's fix**) → date-keyed `[ 'YYYY-MM-DD' => ['date'=>str,'chars'=>[ id=>['name','url'] ],'since'=>str-days,'most'=>int], … ]`, newest-first (581 groups).
- `generate_dead_statistics('characters','all','time')` → `['most'=>['count'=>int,'date'=>str],'time'=>int-days,'start'=>str,'end'=>str]`.
- `generate_dead_statistics('characters','years','array')` → sparse `[ ['death_year'=>int,'death_count'=>int], … ]` ascending (earliest ~1973).
- `generate_dead_statistics('characters','years','average')` → numeric string (e.g. "9.97").
- `generate_dead_statistics('characters', 'sexuality'|'gender'|'role', 'array')` → `slug=>['name','count']` (desc).
- `generate_dead_statistics('shows','per-show','array')` → `['all_dead'|'some_dead'|'no_dead'=>['name','count']]`.
- `generate_dead_statistics('shows','stations'|'nations','array')` → `[ ['term_slug','term_name','count'], … ]` (desc).
- `generate_total_dead('characters'|'shows')`, `generate_total_counts('characters'|'shows')` → ints.

## File structure

**New:** `plugins/lwtv-plugin/php/statistics/templates/partials/year-bars.php`.
**Modified:** `death.php`; `death/{overview,characters,shows,stations,nations,years,list}.php`; `build/class-dead.php`; `class-stats-enqueues.php`; `scss/addons/_stats.scss`; `scss/partials/_colors-dark.scss`.
**Removed:** `death/navbar.php`.

**Renderability invariant:** each task leaves all 7 death views + other sections rendering. Not-yet-redesigned views keep their current (Chart.js) bodies until their task; Chart.js stays enqueued.

---

### Task 1: Data-layer `array` route + shell (sub-nav, enqueue, datasets)

**Files:** modify `build/class-dead.php`, `death.php`, `class-stats-enqueues.php`; remove `death/navbar.php`.

- [ ] **Step 1: Route the `array` format to the record list** — in `build/class-dead.php` `generate_all()`:

```php
	public function generate_all( $format ) {
		switch ( $format ) {
			case 'array':
			case 'list':
			case 'time':
				return $this->generate_list( $format );
			default:
				return array();
		}
	}
```

- [ ] **Step 2: `death.php` — sub-nav + container + datasets.** Replace the `death/navbar.php` include with the shared sub-nav and wrap output; add the new dataset pre-loads. Change the top of `death.php` after `$view` is resolved to also load the year series (overview/years) and the records (list):

```php
// Year series (overview + years) — sparse [ ['death_year','death_count'], … ] ascending.
$dead_years_series = null;
if ( in_array( $view, array( 'overview', 'years' ), true ) ) {
	$dead_years_average = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'average' );
	$dead_years_series  = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'array' );
}

// Full record (list) — date-keyed groups, newest first.
$dead_records = null;
if ( 'list' === $view ) {
	$deadchars_with_stats = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'time' );
	$dead_records         = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'array' );
}
```
(Keep the existing overview counts block. Remove the now-duplicated `$dead_years_average` line from the old `years`/`overview` gate if present — consolidate into the block above.)

Replace the navbar include + switch wrapper:

```php
?>
<div class="lwtv-stats-overview">
	<?php
	$baseurl     = '/statistics/death/';
	$death_subnav = array_merge( array( 'overview' => 1 ), array_fill_keys( $valid_views, 1 ) );
	echo '<nav class="lwtv-stats-subnav" aria-label="' . esc_attr__( 'Death statistics views', 'lwtv' ) . '">';
	foreach ( array_keys( $death_subnav ) as $death_v ) {
		$death_is  = ( $view === $death_v );
		$death_url = ( 'overview' === $death_v ) ? $baseurl : $baseurl . $death_v . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$death_is ? ' is-active' : '',
			esc_url( $death_url ),
			$death_is ? ' aria-current="page"' : '',
			esc_html( ucwords( str_replace( '-', ' ', $death_v ) ) )
		);
	}
	echo '</nav>';

	switch ( $view ) {
		// … existing case includes unchanged …
	}
	?>
</div><!-- .lwtv-stats-overview -->
<?php
```

Remove the `include … 'death/navbar.php';` line.

- [ ] **Step 3: Remove the old navbar**

```bash
git rm plugins/lwtv-plugin/php/statistics/templates/death/navbar.php
```

- [ ] **Step 4: Enqueue — count-up on death + list sort default** — in `class-stats-enqueues.php`:

Add `'death'` to the count-up gate:
```php
		if ( in_array( $statistics, array( 'none', 'shows', 'characters', 'actors', 'nations', 'stations', 'death' ), true ) ) {
```

In the `case 'death':` block, scope the list table init to the list view with a date-desc default (column index 1 = Date):
```php
			case 'death':
				if ( 'list' === $stat_view ) {
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#DeadCharactersTable").tablesorter({ theme:"bootstrap", sortList:[[1,1]] }); });' );
				}
				if ( 'characters' === $stat_view ) {
					// (leave existing sexuality/gender/role inits — harmless; those tables go away after Task 3, selectors then match nothing)
				}
				break;
```
(Keep the existing stations/nations sub-view inits or drop them once Task 4 replaces those tables — a non-matching selector is harmless. Minimal: just add the list init gated to `list`.)

- [ ] **Step 5: Lint + verify shell**

```bash
composer lint-fix && composer lint
php -l plugins/lwtv-plugin/php/statistics/templates/death.php
php -l plugins/lwtv-plugin/php/statistics/build/class-dead.php
curl -sk https://lwtv.local/statistics/death/ | grep -c 'lwtv-stats-subnav\b'                          # -> 1
curl -sk "https://lwtv.local/statistics/death/years/" | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'  # -> Years
curl -sk https://lwtv.local/statistics/death/ | grep -c 'statistics-overview.js'                        # -> 1 (count-up now enqueued)
# array route works:
S="/Users/ipstenu/Websites/Local/lwtv-new/app/public"; SOCK="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock"
php -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$S" eval 'echo count(lwtv_plugin()->generate_dead_statistics("characters","all","array"));' 2>/dev/null  # -> ~581
```
Expected: sub-nav renders (7 items, correct active), count-up JS enqueued, `array` route returns records. Old view bodies still render below.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/build/class-dead.php plugins/lwtv-plugin/php/statistics/templates/death.php plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php plugins/lwtv-plugin/php/statistics/templates/death/navbar.php
git commit -m "feat(stats): death shell — sub-nav, array route, count-up/list enqueue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `year-bars` partial + Overview

**Files:** create `partials/year-bars.php`; modify `death/overview.php`, `scss/addons/_stats.scss`.

- [ ] **Step 1: Create `partials/year-bars.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable vertical bar-per-year chart. Static heights; the peak bar is
 * highlighted. Renders whatever $yearbars provides.
 *
 * @package LezWatch.TV
 *
 * @var array $yearbars {
 *   @type array  $rows        Dense [ ['year'=>int,'count'=>int], … ] ascending.
 *   @type int    $peak_year
 *   @type int    $peak_count
 *   @type string $average     Optional per-year average (numeric string).
 *   @type string $eyebrow
 *   @type string $headline
 *   @type string $description
 * }
 */

$yb_rows  = $yearbars['rows'] ?? array();
$yb_peak  = max( 1, (int) ( $yearbars['peak_count'] ?? 0 ) );
$yb_pyear = (int) ( $yearbars['peak_year'] ?? 0 );
$yb_first = ! empty( $yb_rows ) ? (int) $yb_rows[0]['year'] : 0;
$yb_last  = ! empty( $yb_rows ) ? (int) $yb_rows[ count( $yb_rows ) - 1 ]['year'] : 0;
?>
<section class="lwtv-yearbars-card bg-light">
	<div class="lwtv-yearbars-head">
		<div>
			<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $yearbars['eyebrow'] ?? '' ); ?></p>
			<h2 class="lwtv-yearbars-headline"><?php echo esc_html( $yearbars['headline'] ?? '' ); ?></h2>
			<?php if ( ! empty( $yearbars['description'] ) ) : ?>
				<p class="lwtv-yearbars-desc"><?php echo esc_html( $yearbars['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( isset( $yearbars['average'] ) && '' !== $yearbars['average'] ) : ?>
			<div class="lwtv-yearbars-avg">
				<span class="lwtv-yearbars-avg-num" data-count-to="<?php echo (int) round( (float) $yearbars['average'] ); ?>"><?php echo esc_html( number_format_i18n( (int) round( (float) $yearbars['average'] ) ) ); ?></span>
				<span class="lwtv-yearbars-avg-sub"><?php esc_html_e( 'per year on average', 'lwtv' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
	<div class="lwtv-yearbars" role="img" aria-label="<?php echo esc_attr( $yearbars['eyebrow'] ?? '' ); ?>">
		<?php
		foreach ( $yb_rows as $yb ) {
			$yb_year    = (int) $yb['year'];
			$yb_count   = (int) $yb['count'];
			$yb_height  = round( ( $yb_count / $yb_peak ) * 100, 1 );
			$yb_is_peak = ( $yb_year === $yb_pyear );
			printf(
				'<span class="lwtv-yearbar%1$s" style="height:%2$s%%" title="%3$s"><span class="lwtv-yearbar-val">%4$s</span></span>',
				$yb_is_peak ? ' lwtv-yearbar--peak' : '',
				esc_attr( (string) max( 2, $yb_height ) ),
				esc_attr( $yb_year . ' — ' . number_format_i18n( $yb_count ) ),
				esc_html( number_format_i18n( $yb_count ) )
			);
		}
		?>
	</div>
	<?php if ( $yb_first && $yb_last ) : ?>
		<div class="lwtv-yearbars-axis">
			<span><?php echo esc_html( (string) $yb_first ); ?></span>
			<?php if ( $yb_pyear && $yb_pyear !== $yb_first && $yb_pyear !== $yb_last ) : ?>
				<span class="lwtv-yearbars-axis-peak"><?php echo esc_html( (string) $yb_pyear ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( (string) $yb_last ); ?></span>
		</div>
	<?php endif; ?>
</section>
```

- [ ] **Step 2: A shared "build year-bars data" helper.** Both Overview and Years dense-fill + find the peak from the sparse series. Add to `partials/phrases.php` (the shared helpers file) a function:

```php
if ( ! function_exists( 'lwtv_stats_year_series' ) ) {
	/**
	 * Dense-fill a sparse [ ['death_year','death_count'], … ] series and find the peak.
	 *
	 * @param array $sparse Ascending sparse per-year rows.
	 * @return array [ 'rows' => [ ['year'=>int,'count'=>int], … ] dense, 'peak_year'=>int, 'peak_count'=>int ]
	 */
	function lwtv_stats_year_series( $sparse ) {
		$map = array();
		$min = 0;
		$max = 0;
		foreach ( (array) $sparse as $row ) {
			$y = (int) ( $row['death_year'] ?? 0 );
			$c = (int) ( $row['death_count'] ?? 0 );
			if ( $y <= 0 ) {
				continue;
			}
			$map[ $y ] = $c;
			$min       = ( 0 === $min ) ? $y : min( $min, $y );
			$max       = max( $max, $y );
		}
		$now = (int) gmdate( 'Y' );
		$max = max( $max, $now );
		$rows       = array();
		$peak_year  = $min;
		$peak_count = 0;
		for ( $y = $min; $y <= $max; $y++ ) {
			$c      = $map[ $y ] ?? 0;
			$rows[] = array(
				'year'  => $y,
				'count' => $c,
			);
			if ( $c > $peak_count ) {
				$peak_count = $c;
				$peak_year  = $y;
			}
		}
		return array(
			'rows'       => $rows,
			'peak_year'  => $peak_year,
			'peak_count' => $peak_count,
		);
	}
}
```

- [ ] **Step 3: Rewrite `death/overview.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Overview: the toll (3 counters) + the year chart.
 *
 * @package LezWatch.TV
 *
 * @var int    $deadchars
 * @var int    $deadchar_percent
 * @var int    $deadshows
 * @var int    $deadshow_percent
 * @var int    $allchars
 * @var int    $allshows
 * @var string $dead_years_average
 * @var array  $dead_years_series
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$death_cards = array(
	array(
		'label'   => __( 'Characters Who Die', 'lwtv' ),
		'value'   => $deadchar_percent . '%',
		'count'   => (int) $deadchar_percent,
		'suffix'  => '%',
		/* translators: 1: dead, 2: total characters. */
		'caption' => sprintf( __( '%1$s of %2$s queer characters', 'lwtv' ), number_format_i18n( $deadchars ), number_format_i18n( $allchars ) ),
		'svg'     => 'skull.svg',
		'icon'    => 'svg-skull',
	),
	array(
		'label'   => __( 'Shows That Kill', 'lwtv' ),
		'count'   => (int) $deadshow_percent,
		'suffix'  => '%',
		/* translators: 1: dead shows, 2: total shows. */
		'caption' => sprintf( __( '%1$s of %2$s shows kill a queer character', 'lwtv' ), number_format_i18n( $deadshows ), number_format_i18n( $allshows ) ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-tv',
	),
	array(
		'label'   => __( 'Deaths Per Year', 'lwtv' ),
		'count'   => (int) round( (float) $dead_years_average ),
		'suffix'  => '',
		'caption' => __( 'On average, including quiet years', 'lwtv' ),
		'svg'     => 'calendar-alt.svg',
		'icon'    => 'svg-calendar',
	),
);
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Toll', 'lwtv' ); ?></p>
<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $death_cards as $death_card ) {
		?>
		<div class="lwtv-metric-card card-header dead-characters">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $death_card['label'] ); ?></span>
				<span class="lwtv-metric-icon dead"><?php echo lwtv_plugin()->get_symbolicon( svg: $death_card['svg'], icon: $death_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $death_card['count']; ?>" data-count-suffix="<?php echo esc_attr( $death_card['suffix'] ); ?>"><?php echo esc_html( number_format_i18n( $death_card['count'] ) . $death_card['suffix'] ); ?></span>
			<span class="lwtv-metric-caption"><?php echo esc_html( $death_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$death_ys = lwtv_stats_year_series( $dead_years_series );
$yearbars = array(
	'rows'        => $death_ys['rows'],
	'peak_year'   => $death_ys['peak_year'],
	'peak_count'  => $death_ys['peak_count'],
	'eyebrow'     => __( 'Deaths By Year', 'lwtv' ),
	/* translators: %s: the deadliest year. */
	'headline'    => sprintf( __( 'Deaths peaked in %s — and have fallen since', 'lwtv' ), number_format_i18n( $death_ys['peak_year'] ) ),
	/* translators: %s: the deadliest year. */
	'description' => sprintf( __( '%s was the deadliest year on record for queer women on TV.', 'lwtv' ), number_format_i18n( $death_ys['peak_year'] ) ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
```

(Note: `number_format_i18n( $year )` would add a thousands separator to a year — use plain `(string)` for years in the copy. Correct the headline/description/peak-year to `esc_html( (string) $death_ys['peak_year'] )` in a `sprintf` with `%s`, NOT `number_format_i18n`. Fix during implementation: years never get a thousands separator.)

- [ ] **Step 4: SCSS — year-bars** (in `_stats.scss` under `.statistics`):

```scss
	.lwtv-yearbars-card {
		padding: 20px 24px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;
	}

	.lwtv-yearbars-head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		margin-bottom: 16px;
	}

	.lwtv-yearbars-headline {
		margin: 2px 0 0;
		font-size: 1.125rem;
		font-weight: 700;
	}

	.lwtv-yearbars-desc {
		margin: 4px 0 0;
		font-size: 0.9rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-yearbars-avg {
		text-align: right;
		flex: 0 0 auto;
	}

	.lwtv-yearbars-avg-num {
		display: block;
		font-size: 2rem;
		font-weight: 700;
		line-height: 1;
		color: colors.$lwtv-stats-red;
		font-variant-numeric: tabular-nums;
	}

	.lwtv-yearbars-avg-sub {
		font-size: 0.7rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-yearbars {
		display: flex;
		align-items: flex-end;
		gap: 2px;
		height: 220px;
	}

	.lwtv-yearbar {
		position: relative;
		flex: 1 1 0;
		min-width: 3px;
		background-color: rgba(colors.$lwtv-stats-red, 0.35);
		border-radius: 3px 3px 0 0;

		.lwtv-yearbar-val {
			position: absolute;
			top: -1.1rem;
			left: 50%;
			transform: translateX(-50%);
			font-size: 0.65rem;
			color: colors.$lwtv-medgrey;
			opacity: 0;
		}

		&:hover .lwtv-yearbar-val {
			opacity: 1;
		}
	}

	.lwtv-yearbar--peak {
		background-color: colors.$lwtv-stats-red;

		.lwtv-yearbar-val {
			opacity: 1;
			font-weight: 700;
			color: colors.$lwtv-stats-red;
		}
	}

	.lwtv-yearbars-axis {
		display: flex;
		justify-content: space-between;
		margin-top: 6px;
		font-size: 0.7rem;
		font-variant-numeric: tabular-nums;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-yearbars-axis-peak {
		color: colors.$lwtv-stats-red;
		font-weight: 700;
	}
```

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/death/ | grep -c 'lwtv-metric-card'      # -> 3
curl -sk https://lwtv.local/statistics/death/ | grep -c 'lwtv-yearbar\b'         # -> many (one per year)
curl -sk https://lwtv.local/statistics/death/ | grep -oE 'lwtv-yearbar--peak|Deaths peaked in [0-9]{4}'
curl -sk https://lwtv.local/statistics/death/ | grep -c '<canvas'               # -> 0
```
Expected: 3 red counters, a year-bar chart with exactly one `--peak` bar, dynamic "Deaths peaked in {year}", no canvas.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/year-bars.php plugins/lwtv-plugin/php/statistics/templates/partials/phrases.php plugins/lwtv-plugin/php/statistics/templates/death/overview.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): death overview — counters + year-bars chart

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Characters view (donuts + dynamic headline)

**Files:** modify `death/characters.php`.

- [ ] **Step 1: Rewrite `death/characters.php`** — three donuts from the `array` breakdowns, reusing `partials/donut.php` and `partials/phrases.php`. Build a small local segment helper (top-N ramp + grey Other), mirroring `nations/single.php`'s `$lwtv_build_segments`. Sexuality donut headline = "{TopName} characters die most" from the largest segment, with its share via `lwtv_stats_fraction_phrase()`; centre = total dead.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Characters: who dies, by sexuality / gender / role.
 *
 * @package LezWatch.TV
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$dc_ramp = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );

$dc_build = function ( $data, $topn = 5, $grey_slug = '' ) use ( $dc_ramp ) {
	$data  = is_array( $data ) ? $data : array();
	$total = 0;
	foreach ( $data as $r ) {
		$total += (int) $r['count'];
	}
	$segments = array();
	$grey_val = 0;
	if ( '' !== $grey_slug && isset( $data[ $grey_slug ] ) ) {
		$grey_val = (int) $data[ $grey_slug ]['count'];
		$segments[] = array(
			'label' => $data[ $grey_slug ]['name'],
			'count' => $grey_val,
			'pct'   => ( $total > 0 ) ? round( ( $grey_val / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
		unset( $data[ $grey_slug ] );
	}
	uasort( $data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
	$named = $grey_val;
	$i     = 0;
	$top   = array( 'name' => '', 'count' => 0, 'pct' => 0 );
	foreach ( $data as $r ) {
		if ( 0 === $i ) {
			$top = array(
				'name'  => $r['name'],
				'count' => (int) $r['count'],
				'pct'   => ( $total > 0 ) ? round( ( (int) $r['count'] / $total ) * 100, 1 ) : 0,
			);
		}
		if ( $i >= $topn || (int) $r['count'] <= 0 ) {
			break;
		}
		$c          = (int) $r['count'];
		$named     += $c;
		$segments[] = array(
			'label' => $r['name'],
			'count' => $c,
			'pct'   => ( $total > 0 ) ? round( ( $c / $total ) * 100, 1 ) : 0,
			'class' => $dc_ramp[ $i ],
		);
		++$i;
	}
	$other = max( 0, $total - $named );
	if ( $other > 0 ) {
		$segments[] = array(
			'label' => __( 'Other', 'lwtv' ),
			'count' => $other,
			'pct'   => ( $total > 0 ) ? round( ( $other / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
	}
	return array( $segments, $total, $top );
};

// Sexuality.
$dc_sex = lwtv_plugin()->generate_dead_statistics( 'characters', 'sexuality', 'array' );
list( $dc_sex_seg, $dc_sex_total, $dc_sex_top ) = $dc_build( $dc_sex, 5 );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Dies — By Sexual Orientation', 'lwtv' ); ?></p>
<?php
$donut = array(
	'segments'    => $dc_sex_seg,
	'center'      => $dc_sex_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Sexual Orientation', 'lwtv' ),
	/* translators: %s: the orientation with the most deaths. */
	'headline'    => sprintf( __( '%s characters die most', 'lwtv' ), $dc_sex_top['name'] ),
	/* translators: 1: fraction phrase, 2: orientation. */
	'description' => sprintf( __( '%1$s of all queer deaths are %2$s characters.', 'lwtv' ), lwtv_stats_fraction_phrase( $dc_sex_top['pct'] ), strtolower( $dc_sex_top['name'] ) ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// Gender (cisgender grey).
$dc_gen = lwtv_plugin()->generate_dead_statistics( 'characters', 'gender', 'array' );
list( $dc_gen_seg, $dc_gen_total, $dc_gen_top ) = $dc_build( $dc_gen, 4, 'cisgender' );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Dies — By Gender', 'lwtv' ); ?></p>
<?php
$donut = array(
	'segments'    => $dc_gen_seg,
	'center'      => $dc_gen_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Gender Identity', 'lwtv' ),
	'headline'    => __( 'Gender of the dead', 'lwtv' ),
	'description' => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// Role.
$dc_role = lwtv_plugin()->generate_dead_statistics( 'characters', 'role', 'array' );
list( $dc_role_seg, $dc_role_total, $dc_role_top ) = $dc_build( $dc_role, 3 );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Dies — By Role', 'lwtv' ); ?></p>
<?php
$donut = array(
	'segments'    => $dc_role_seg,
	'center'      => $dc_role_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Role', 'lwtv' ),
	'headline'    => __( 'Regulars, recurring, and guests', 'lwtv' ),
	'description' => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 2: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/death/characters/ | grep -c 'lwtv-donut-card'        # -> 3
curl -sk https://lwtv.local/statistics/death/characters/ | grep -oE 'lwtv-donut-headline">[^<]*' | head
curl -sk https://lwtv.local/statistics/death/characters/ | grep -c '<canvas'                # -> 0
```
Expected: 3 donuts, dynamic sexuality headline ("{Top} characters die most"), no canvas.

- [ ] **Step 3: Commit** (`death/characters.php` + `style.css`/`style.min.css` if changed — likely not, no SCSS).

```bash
git add plugins/lwtv-plugin/php/statistics/templates/death/characters.php
git commit -m "feat(stats): death characters — sexuality/gender/role donuts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Shows + Stations + Nations views

**Files:** modify `death/shows.php`, `death/stations.php`, `death/nations.php`.

- [ ] **Step 1: `death/shows.php` — dead-by-show buckets donut**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Shows: how many shows kill all / some / no queer characters.
 *
 * @package LezWatch.TV
 */

$ds_data = lwtv_plugin()->generate_dead_statistics( 'shows', 'per-show', 'array' );
$ds_map  = array(
	'no_dead'   => array( __( 'No deaths', 'lwtv' ), 'green' ),
	'some_dead' => array( __( 'Some deaths', 'lwtv' ), 'amber' ),
	'all_dead'  => array( __( 'All die', 'lwtv' ), 'red' ),
);
$ds_total = 0;
foreach ( $ds_data as $ds_row ) {
	$ds_total += (int) $ds_row['count'];
}
$ds_seg = array();
foreach ( $ds_map as $ds_key => $ds_meta ) {
	$ds_c     = isset( $ds_data[ $ds_key ] ) ? (int) $ds_data[ $ds_key ]['count'] : 0;
	$ds_seg[] = array(
		'label' => $ds_meta[0],
		'count' => $ds_c,
		'pct'   => ( $ds_total > 0 ) ? round( ( $ds_c / $ds_total ) * 100, 1 ) : 0,
		'class' => $ds_meta[1],
	);
}
$ds_alldead = isset( $ds_data['all_dead'] ) ? (int) $ds_data['all_dead']['count'] : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Which Shows Kill', 'lwtv' ); ?></p>
<?php
$donut = array(
	'segments'    => $ds_seg,
	'center'      => $ds_alldead,
	'center_sub'  => __( 'kill everyone', 'lwtv' ),
	'eyebrow'     => __( 'Deaths Per Show', 'lwtv' ),
	'headline'    => __( 'Most shows keep their queer characters alive', 'lwtv' ),
	'description' => __( 'Raw per-show death counts track how large a show\'s cast is — a big ensemble will show more deaths than a two-hander.', 'lwtv' ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```
(Refine the headline dynamically if desired — e.g. via the fraction phrase on the `no_dead` share; keep static acceptable.)

- [ ] **Step 2: `death/stations.php` — ranked bars of the dead by network**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Stations: dead characters by network.
 *
 * @package LezWatch.TV
 */

$dst_raw  = lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'array' );
$dst_raw  = is_array( $dst_raw ) ? $dst_raw : array();
$dst_rows = array();
$dst_tot  = 0;
foreach ( $dst_raw as $dst_r ) {
	$dst_tot += (int) $dst_r['count'];
}
foreach ( $dst_raw as $dst_r ) {
	$dst_rows[] = array(
		'name'  => $dst_r['term_name'],
		'count' => (int) $dst_r['count'],
		'url'   => site_url( '/network/' . $dst_r['term_slug'] ),
	);
}
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Deaths By Network', 'lwtv' ); ?></p>
<?php
$ranked = array(
	'rows'   => array_slice( $dst_rows, 0, 15 ),
	'total'  => $dst_tot,
	'family' => 'dead-characters',
	'title'  => __( 'Networks with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'Bigger catalogues carry more deaths — this ranks raw totals, not rates.', 'lwtv' ),
	'svg'    => 'satellite-signal.svg',
	'icon'   => 'svg-satellite-signal',
	'base'   => '',
	'mode'   => 'share',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```
NOTE: confirm the `.lwtv-bars--dead-characters` family fill exists in `_stats.scss`; if `ranked-bars.php` only supports `shows|characters|actors|sexuality|gender`, use `family => 'characters'` (green) or add a `dead-characters`/`dead` bars family in SCSS (small rule). Decide during implementation — prefer an existing family to avoid new SCSS; the caveat/label stays the same.

- [ ] **Step 3: `death/nations.php`** — same as stations with `nations` data + `/country/` links:

```php
$dn_raw = lwtv_plugin()->generate_dead_statistics( 'shows', 'nations', 'array' );
// … identical shape … rows: name=term_name, url = site_url('/country/'.term_slug) …
$ranked = array(
	'rows'   => array_slice( $dn_rows, 0, 15 ),
	'total'  => $dn_tot,
	'family' => 'dead-characters', // or 'characters' if no dead bars family
	'title'  => __( 'Countries with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'Tracks catalogue size by country, not a death rate.', 'lwtv' ),
	'svg'    => 'globe.svg',
	'icon'   => 'svg-globe',
	'base'   => '',
	'mode'   => 'share',
);
```
(Full file mirrors stations.php with the nation variables + eyebrow "Deaths By Country".)

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
for v in shows stations nations; do
  echo "== $v =="; curl -sk "https://lwtv.local/statistics/death/$v/" | grep -c '<canvas'   # -> 0 each
done
curl -sk https://lwtv.local/statistics/death/shows/ | grep -c 'lwtv-donut-card'              # -> 1
curl -sk https://lwtv.local/statistics/death/stations/ | grep -c 'lwtv-leader-row'           # -> >0
curl -sk https://lwtv.local/statistics/death/nations/ | grep -c 'lwtv-leader-row'            # -> >0
```
Expected: shows donut (3 buckets), stations/nations ranked bars, all canvas=0.

- [ ] **Step 5: Commit** (only if a bars-family SCSS rule was added, include `_stats.scss`+built css).

```bash
git add plugins/lwtv-plugin/php/statistics/templates/death/shows.php plugins/lwtv-plugin/php/statistics/templates/death/stations.php plugins/lwtv-plugin/php/statistics/templates/death/nations.php
git commit -m "feat(stats): death shows/stations/nations breakdowns

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Years view (full year-bars)

**Files:** modify `death/years.php`.

- [ ] **Step 1: Rewrite `death/years.php`** — reuse the `year-bars` partial + the `lwtv_stats_year_series()` helper, with the average + dynamic title/subtitle.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Years: one bar per year.
 *
 * @package LezWatch.TV
 *
 * @var string $dead_years_average
 * @var array  $dead_years_series
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$dy = lwtv_stats_year_series( $dead_years_series );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Deaths By Year', 'lwtv' ); ?></p>
<?php
$first_year = ! empty( $dy['rows'] ) ? (int) $dy['rows'][0]['year'] : 0;
$last_year  = ! empty( $dy['rows'] ) ? (int) $dy['rows'][ count( $dy['rows'] ) - 1 ]['year'] : 0;
$yearbars   = array(
	'rows'        => $dy['rows'],
	'peak_year'   => $dy['peak_year'],
	'peak_count'  => $dy['peak_count'],
	'average'     => $dead_years_average,
	'eyebrow'     => __( 'Deaths By Year', 'lwtv' ),
	/* translators: 1: first year, 2: last year. */
	'headline'    => sprintf( __( 'Every year, %1$s–%2$s', 'lwtv' ), (string) $first_year, (string) $last_year ),
	/* translators: %s: the deadliest year. */
	'description' => sprintf( __( 'One bar per year. %s towers over the rest — and nothing since has come close.', 'lwtv' ), (string) $dy['peak_year'] ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
```

- [ ] **Step 2: Lint + verify**

```bash
composer lint-fix && composer lint
curl -sk https://lwtv.local/statistics/death/years/ | grep -oE 'Every year, [0-9]{4}–[0-9]{4}|lwtv-yearbars-avg-num|lwtv-yearbar--peak'
curl -sk https://lwtv.local/statistics/death/years/ | grep -c '<canvas'   # -> 0
```
Expected: dynamic "Every year, {first}–{last}", per-year avg shown, one peak bar, no canvas.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/death/years.php
git commit -m "feat(stats): death years — full bar-per-year chart

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: List view (gap cards + sortable table)

**Files:** modify `death/list.php`, `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`.

- [ ] **Step 1: Rewrite `death/list.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → List: derived gap cards + the full sortable record.
 *
 * @package LezWatch.TV
 *
 * @var array $deadchars_with_stats  'time' summary.
 * @var array $dead_records          date-keyed records (newest first).
 */

if ( empty( $dead_records ) ) {
	lwtv_plugin()->debug_log( 'death', 'Dead records empty' );
	return;
}

$dl_time  = (int) ( $deadchars_with_stats['time'] ?? 0 );
$dl_most  = (int) ( $deadchars_with_stats['most']['count'] ?? 0 );

$dl_cards = array(
	array( 'label' => __( 'Longest Gap', 'lwtv' ), 'count' => $dl_time, 'unit' => __( 'days', 'lwtv' ), 'caption' => __( 'Between two consecutive deaths', 'lwtv' ) ),
	array( 'label' => __( 'Shortest Gap', 'lwtv' ), 'count' => 0, 'unit' => __( 'days', 'lwtv' ), 'caption' => __( 'Multiple have died the same day', 'lwtv' ) ),
	array( 'label' => __( 'Most In One Day', 'lwtv' ), 'count' => $dl_most, 'unit' => '', 'caption' => __( 'Characters killed on a single date', 'lwtv' ) ),
);

// Flatten date groups → one row per dead character (a date's gap applies to each).
$dl_rows = array();
foreach ( $dead_records as $dl_date => $dl_group ) {
	$dl_since = isset( $dl_group['since'] ) ? (int) $dl_group['since'] : -1; // -1 => oldest row, show "—".
	foreach ( (array) $dl_group['chars'] as $dl_char ) {
		$dl_rows[] = array(
			'name'  => $dl_char['name'],
			'url'   => $dl_char['url'],
			'date'  => $dl_group['date'],
			'since' => $dl_since,
		);
	}
}
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Record', 'lwtv' ); ?></p>
<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $dl_cards as $dl_c ) {
		?>
		<div class="lwtv-metric-card card-header dead-characters">
			<span class="lwtv-stats-eyebrow"><?php echo esc_html( $dl_c['label'] ); ?></span>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $dl_c['count']; ?>"><?php echo esc_html( number_format_i18n( $dl_c['count'] ) ); ?></span>
			<?php if ( '' !== $dl_c['unit'] ) : ?><span class="lwtv-death-gap-unit"><?php echo esc_html( $dl_c['unit'] ); ?></span><?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $dl_c['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<p class="lwtv-death-list-intro">
	<?php
	printf(
		/* translators: %s: number of dead characters. */
		esc_html( _n( '%s character, newest first. Click a column heading to sort.', '%s characters, newest first. Click a column heading to sort.', count( $dl_rows ), 'lwtv' ) ),
		esc_html( number_format_i18n( count( $dl_rows ) ) )
	);
	?>
</p>
<div class="lwtv-death-list-wrap">
	<table id="DeadCharactersTable" class="tablesorter lwtv-death-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'lwtv' ); ?></th>
				<th><?php esc_html_e( 'Date', 'lwtv' ); ?></th>
				<th class="lwtv-death-list-num"><?php esc_html_e( 'Days Since Prev', 'lwtv' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $dl_rows as $dl_r ) {
				?>
				<tr>
					<td><a href="<?php echo esc_url( $dl_r['url'] ); ?>"><?php echo esc_html( $dl_r['name'] ); ?></a></td>
					<td><?php echo esc_html( $dl_r['date'] ); ?></td>
					<td class="lwtv-death-list-num" data-text="<?php echo esc_attr( $dl_r['since'] >= 0 ? (string) $dl_r['since'] : '-1' ); ?>"><?php echo ( $dl_r['since'] >= 0 ) ? esc_html( number_format_i18n( $dl_r['since'] ) ) : '—'; ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
```
NOTES: the Date column is raw `YYYY-MM-DD` (sorts correctly as text, tablesorter). The gap column uses `data-text`/numeric content so tablesorter sorts numerically and the oldest row (`—`, `-1`) sorts to the extreme. Confirm the date strings are `Y-m-d`; if the raw ACF meta is `Ymd`, format to `Y-m-d` for display + sorting (`gmdate('Y-m-d', strtotime(...))` or insert dashes). The tablesorter init (`#DeadCharactersTable`, `sortList:[[1,1]]`) was added in Task 1.

- [ ] **Step 2: SCSS — list table + gap unit** (in `_stats.scss` under `.statistics`):

```scss
	.lwtv-death-gap-unit {
		font-size: 0.8rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-death-list-intro {
		margin: 8px 0 12px;
		font-size: 0.85rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-death-list-wrap {
		overflow-x: auto;
	}

	.lwtv-death-list {
		width: 100%;
		min-width: 600px;
		border-collapse: collapse;
		font-size: 0.85rem;

		th, td {
			padding: 8px 10px;
			border-bottom: 1px solid colors.$lwtv-bordergrey;
			text-align: left;
		}

		thead th {
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: colors.$lwtv-medgrey;
			cursor: pointer;
		}

		td a {
			color: colors.$lwtv-pink;

			&:hover {
				color: colors.$lwtv-purple;
			}
		}
	}

	.lwtv-death-list-num {
		text-align: right !important;
		font-variant-numeric: tabular-nums;
	}
```

- [ ] **Step 3: Dark SCSS** — in `_colors-dark.scss` `.statistics` block, add row borders + header contrast for the list:

```scss
		.lwtv-death-list th,
		.lwtv-death-list td {
			border-bottom-color: rgba(255, 255, 255, 0.12);
		}
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/death/list/ | grep -c 'lwtv-metric-card'                # -> 3 (gap cards)
curl -sk https://lwtv.local/statistics/death/list/ | grep -c 'id="DeadCharactersTable"'         # -> 1
curl -sk https://lwtv.local/statistics/death/list/ | grep -c '<tr>'                             # -> many (581+ rows)
curl -sk https://lwtv.local/statistics/death/list/ | grep -o 'tablesorter[^"]*'| head -1
curl -sk https://lwtv.local/statistics/death/list/ | grep -c '<canvas'                          # -> 0
```
Browser-verify: 3 red gap cards; the table sorts on Name/Date/Gap click (date-desc default); `—` on the oldest row; horizontal scroll on narrow.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/death/list.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): death list — gap cards + sortable record table

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Full verification + visual QA

**Files:** none expected (fixes only).

- [ ] **Step 1: Lint + build clean** (`composer lint`, `npm run lint:css`, `npm run buildquick`).

- [ ] **Step 2: Render + regression sweep**

```bash
for u in "" characters shows stations nations years list; do
  code=$(curl -sk -o /tmp/d.html -w "%{http_code}" "https://lwtv.local/statistics/death/$u/")
  err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/d.html)
  canv=$(grep -c '<canvas' /tmp/d.html)
  echo "death/$u -> HTTP $code, php-errors=$err, canvas=$canv"
done
for u in "statistics/" "statistics/shows/" "statistics/characters/" "statistics/actors/" "statistics/nations/" "statistics/stations/"; do
  echo -n "$u -> "; curl -sk -o /dev/null -w "HTTP %{http_code}\n" "https://lwtv.local/$u"
done
echo "this-year still chartjs: $(curl -sk https://lwtv.local/statistics/this-year/ 2>/dev/null | grep -c 'chart.min.js')"
```
Expected: every death view HTTP 200 / 0 php-errors / **canvas=0**; other sections 200; This Year still loads Chart.js.

- [ ] **Step 3: Browser QA** (light + dark) against `design_handoff_statistics_death/screenshots/`:
  - Overview (3 red counters + year chart peak headline), Characters (3 donuts, dynamic headline), Shows (bucket donut), Stations/Nations (ranked bars), Years (full bar chart + avg + peak bar), List (3 gap cards + sortable table).
  - **Sort check:** click Name/Date/Gap headings — sorts + flips; default is date-desc; oldest row shows `—`.
  - Count-up (counters/averages) + donut-legend bars animate; year bars + table static. Reduced-motion. Sub-nav active states. Primary tab bar shows Death active.
  - Regression: other stats sections + Nations/Stations unchanged.

- [ ] **Step 4: Commit** (only if Step 3 required fixes).

---

## Self-Review

**Spec coverage:** data-array route + shell + enqueue → T1; year-bars partial + Overview → T2; Characters donuts → T3; Shows/Stations/Nations → T4; Years → T5; List (gap cards + sortable table) → T6; verification → T7. Reuse mandate, red family, peak-via-`max()`, i18n/escaping, divisor guards, animation contract, Chart.js-untouched — enforced per task + Global Constraints. New code: `year-bars.php` + `lwtv_stats_year_series()` (T2), year-bars/list SCSS (T2/T6), `generate_all` `array` case (T1). ✓

**Placeholder scan:** no TBD/TODO. Flagged for implementation: use plain `(string)` for YEAR values (never `number_format_i18n`, which would comma-separate a year); confirm the `dead-characters` ranked-bars family exists or fall back to `characters`; normalize the list Date to `Y-m-d` if the raw meta is `Ymd`. Peak year `max()`-derived. ✓

**Type consistency:** `$donut` (segments[label,count,pct,class], center, center_sub, eyebrow, headline, description) and `$ranked` (rows[name,count,url], total, family, title, sub, svg, icon, base, mode) match the partials; new `$yearbars` (rows[year,count], peak_year, peak_count, average, eyebrow, headline, description) matches `year-bars.php`; `lwtv_stats_year_series()` output feeds it. `generate_dead_statistics(...,'array')` shapes match the verified data. tablesorter init id `#DeadCharactersTable` matches the List table id. ✓

## Known follow-ups (out of scope)
- After Death, only This Year uses Chart.js → narrow the enqueue to This-Year-only (or redesign This Year) in a later round.
- The pre-existing "None of X of Y's shows" grammar bug in nations/stations `single.php` (shared follow-up).
- Year-bar range floor (real earliest ~1973 vs a 1998 editorial floor) — owner may tune.
- Characters headline uses the top term's display name ("Homosexual"); owner may prefer a "Lesbian" label mapping.
