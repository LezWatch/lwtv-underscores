# Statistics on Shows Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the ten `/statistics/shows/` views into a shared shell (promoted primary tab bar + a redesigned sub-nav) with one purpose-built, server-rendered visualisation per view (donuts, ranked bars, an area trendline, and a reused metric-card Overview), driven by existing data and the theme's existing tokens/components.

**Architecture:** Keep the existing render path (`statistics.php` → `generate_stats_block` → `shows.php` → `switch($view)` → per-view partial). Promote the existing `tabbar.php` into the page shell so every stats section shows it. Add two reusable server-rendered chart partials (`partials/donut.php`, `partials/trendline.php`) and reuse the committed Overview components (metric cards, pull-stat band, ranked bars, count-up JS, `$lwtv-stats-*` tokens) for everything else. No new data layer and no Chart.js for these views.

**Tech Stack:** PHP 8.1+ (WordPress theme + plugin, `LWTV\` PSR-4), Bootstrap 5 utilities, SCSS (Dart Sass via `@wordpress/scripts`), inline SVG, existing vanilla-JS animation driver, Symbolicons sprite. No PHPUnit harness — verification is PHPCS lint + SCSS build + `curl`/`wp eval` + browser checks.

## Global Constraints

- **Reuse mandate (user, commit `d23d3186`):** reuse existing components and tokens; do NOT hardcode hex, do NOT use the handoff README's literal hex, do NOT revert the user's color/size tweaks. Color tokens (in `scss/partials/_colors.scss`): `$lwtv-stats-{green,yellow,red,blue}` + `…-background` + `…-border`; `$lwtv-stats-progressbar`; `$lwtv-pink`/`$lwtv-ltpink`/`$lwtv-dkpink`; `$lwtv-gold`/`$lwtv-silver`/`$lwtv-bronze`; `$lwtv-red`/`$lwtv-yellow`; `$lwtv-medgrey`/`$lwtv-dkgrey`/`$lwtv-bordergrey`/`$lwtv-ltgrey`.
- **New color values** allowed ONLY for the two Triggers mid-ramp steps, via SCSS `color.mix()` — never pasted hex. (`@use "sass:color";` is already at the top of `scss/addons/_stats.scss`.)
- **SCSS token access:** in `scss/addons/_stats.scss` use `colors.$lwtv-…` (file has `@use "../partials/colors"`); in `scss/partials/_colors-dark.scss` use `colors.$lwtv-…` EXCEPT `$lwtv-medgrey`, which is locally overridden to `#404040` there and must stay **bare**. Match surrounding lines.
- **PHP:** 8.1+; WordPress-Extra PHPCS clean (`composer lint` / `composer lint-fix`). Class/template conventions per repo.
- **Auto-escaped funcs** (do NOT wrap in `esc_*`): `lwtv_plugin`, `get_symbolicon`. Every `get_symbolicon()` echo carries `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`.
- **i18n:** all user-facing strings via `__()`/`esc_html__()`/`_n()` with the `'lwtv'` text domain; `number_format_i18n()` for displayed numbers.
- **Data integrity:** guard every divisor against zero; never divide by zero; on empty builder data render a graceful fallback, not a broken chart. No new query vars; keep existing sanitization.
- **Animation contract (existing JS `statistics-overview.js`):** count-up elements carry `data-count-to="<int>"` and their visible text is the final `number_format_i18n()` value; growable bars carry `data-grow-to="<pct>"` + inline `style="width:0"`. One 1100ms `easeOutCubic` driver; reduced-motion renders finals. **Donut rings render at final proportions immediately** (no `data-grow-to` on ring segments — only the center figure and legend count up / grow).
- **Build:** `npm run buildquick` requires **Node 24** (`.nvmrc`); Node 18 fails with `crypto is not defined`. Run `source ~/.nvm/nvm.sh; nvm use` first. Never edit `blocks/build/` or `inc/dist/`.
- **Scope:** Shows section only. Other sections gain ONLY the shared tab bar (Task 1); their bodies are untouched. No routing/URL/scoring changes.

## Environment — how to run things (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix` from repo root.
- **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick` (Node 24).
- **Live site (Local):** `https://lwtv.local/statistics/shows/` (self-signed → `curl -sk`).
- **wp-cli** (homebrew can't reach Local's DB by default — use this exact form):
  ```
  php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>
  ```

## Data shapes (verified live)

`lwtv_plugin()->generate_shows_statistics('array', $type)` returns a **single-key wrapper**; unwrap with `reset()`:

- `formats` → `['shows' => [ 0=>['slug','name','count'], … ]]` — 0-indexed, **sorted by count desc**.
- `tropes`/`genres`/`intersections` → `['shows' => ['<slug>'=>['name','count','url'], … ]]` — **slug-keyed, unsorted** (sort by count desc for ranked bars).
- `stars` → `['shows' => ['anti'=>…, 'bronze'=>…, 'gold'=>…, 'silver'=>…]]` (no "no star" key — compute None = total − sum).
- `triggers` → `['shows' => ['high'=>…, 'low'=>…, 'medium'=>…]]` (compute None = total − sum).
- `worth-it` → `['worth_it' => ['yes'=>…, 'no'=>…, 'meh'=>…, 'tbd'=>…]]`.
- `we-love-it` → `['we_love_it' => ['we_love'=>…, 'we_do_not_love'=>…]]`.

Each leaf is `['count'=>int, 'name'=>string, 'url'=>string(optional), 'slug'=>string(optional)]`.
`pct = round(count / total * 100, 1)` where `total = generate_total_counts('shows')`.

## Symbolicon ids (verified present in the sprite)

`tv`, `tag` (Tropes), `theater_masks` (Genres), `star` (Stars), `warning` (Triggers), `heart` (We Love It), `graph-line` (On Air). Call `get_symbolicon( svg: '<id>.svg', icon: 'svg-<fallback>' )`; the sprite is primary. After building, confirm a real `<use href="…#<id>">` renders (not an `<i>` fallback).

## File Structure

**New**
- `plugins/lwtv-plugin/php/statistics/templates/shows/subnav.php` — Shows sub-nav (replaces `navbar.php`).
- `plugins/lwtv-plugin/php/statistics/templates/partials/sparkline.php` — shared `lwtv_stats_sparkline_points()`.
- `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php` — reusable donut (ring + legend).
- `plugins/lwtv-plugin/php/statistics/templates/partials/trendline.php` — reusable area trendline.

**Modified**
- `page-templates/statistics.php` — render the primary tab bar in the shell.
- `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php` — active tab from `$statstype`.
- `plugins/lwtv-plugin/php/statistics/templates/main.php` — drop its tabbar include.
- `plugins/lwtv-plugin/php/statistics/templates/main/overview.php` — use the shared sparkline include.
- `plugins/lwtv-plugin/php/statistics/templates/shows.php` — container wrapper, sub-nav include.
- `plugins/lwtv-plugin/php/statistics/templates/shows/{overview,formats,tropes,genres,intersectionality,stars,triggers,on-air,worth-it,we-love-it}.php` — rewrites.
- `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php` — enqueue the stats JS on Shows too.
- `scss/addons/_stats.scss` — sub-nav, donut, trendline, ranked-bar family, pull-stat 2-up (light).
- `scss/partials/_colors-dark.scss` — dark variants for the above.

**Removed**
- `plugins/lwtv-plugin/php/statistics/templates/shows/navbar.php`.

**Renderability invariant:** every task leaves `/statistics/shows/` and all its views rendering without fatal error. Views not yet rewritten keep their current Chart.js bodies until their task.

---

### Task 1: Promote the primary tab bar into the page shell

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php`
- Modify: `page-templates/statistics.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main.php`

**Interfaces:**
- Consumes: `$statstype` (set in `statistics.php`: one of `main|shows|characters|actors|nations|stations|death|formats`).
- Produces: the tab bar rendered once per stats page, active tab derived from `$statstype`.

- [ ] **Step 1: Generalize `tabbar.php` active state**

Replace the active-detection block in `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php`. Change the docblock line `Statistics section tab bar (overview view).` → `Statistics section tab bar (shared across stats views).`, then map `$statstype` to the active URL. Replace the `<nav>…</nav>` block with:

```php
<?php
// Map the current stats section to its tab URL for active-state.
$lwtv_stats_active = home_url( '/statistics/' );
switch ( $statstype ?? 'main' ) {
	case 'shows':
	case 'characters':
	case 'actors':
	case 'nations':
	case 'stations':
	case 'death':
		$lwtv_stats_active = home_url( '/statistics/' . $statstype . '/' );
		break;
}
?>
<nav class="lwtv-stats-tabs" aria-label="<?php esc_attr_e( 'Statistics sections', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_stats_tabs as $lwtv_stats_tab ) {
		$lwtv_is_active   = ( $lwtv_stats_active === $lwtv_stats_tab['url'] );
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

(The `$lwtv_stats_tabs` array above it is unchanged.)

- [ ] **Step 2: Render the tab bar in the page shell**

In `page-templates/statistics.php`, inside `<div class="statistics">` and BEFORE the `if ( 'main' === $statstype ) { the_content(); }` line, include the tab bar. Find this block:

```php
					<div class="statistics">
						<?php
						if ( 'main' === $statstype ) {
							the_content();
						}
```

and change it to:

```php
					<div class="statistics">
						<?php
						// Shared stats section tab bar (all views).
						// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
						include LWTV_PLUGIN_PATH . '/php/statistics/templates/main/tabbar.php';

						if ( 'main' === $statstype ) {
							the_content();
						}
```

Verify `LWTV_PLUGIN_PATH` is the correct constant for the plugin base path: run `grep -rn "define( 'LWTV_PLUGIN_PATH'" plugins/`. If the constant differs (e.g. `LWTV_PLUGIN_DIR`), use the one that exists. If none resolves cleanly from the theme, use the absolute include the plugin already uses elsewhere: `plugin_dir_path()` is not available here, so prefer the plugin path constant. (Confirm before writing.)

- [ ] **Step 3: Remove the tab bar include from `main.php`**

In `plugins/lwtv-plugin/php/statistics/templates/main.php`, delete the two lines that include the tab bar inside `.lwtv-stats-overview` (the `// phpcs:ignore …` + `include $stats_partials . 'tabbar.php';`). Leave the rest (overview.php, bury-your-gays.php, panels) intact. The `.lwtv-stats-overview` wrapper `<div>` stays.

- [ ] **Step 4: Lint + build**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```
Expected: PHP lint clean; build completes.

- [ ] **Step 5: Verify tab bar + active state across sections**

```bash
for s in "" shows/ characters/ actors/ death/; do
  echo "== /statistics/$s =="
  curl -sk "https://lwtv.local/statistics/$s" | grep -o 'lwtv-stats-tab is-active[^>]*>[^<]*' | head -1
done
```
Expected: `/statistics/` → Overview active; `/statistics/shows/` → Shows active; `characters/`→Characters; `actors/`→Actors; `death/`→Death. Also confirm the tab bar appears exactly once on the overview (not duplicated): `curl -sk https://lwtv.local/statistics/ | grep -c 'class="lwtv-stats-tabs"'` → `1`.

- [ ] **Step 6: Commit**

```bash
git add page-templates/statistics.php plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php plugins/lwtv-plugin/php/statistics/templates/main.php style.css style.min.css
git commit -m "feat(stats): promote primary tab bar into the page shell

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Shows shell — sub-nav, container, JS enqueue

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/shows/subnav.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php`
- Modify: `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `$view` (current shows view slug), `$baseurl` (`/statistics/shows/`).
- Produces: the `.lwtv-stats-subnav` markup + `.lwtv-stats-overview` container around shows output; stats JS loaded on shows pages.

- [ ] **Step 1: Create `subnav.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the shows stats section.
 */

$lwtv_shows_subnav = array(
	'overview'          => __( 'Overview', 'lwtv' ),
	'formats'           => __( 'Formats', 'lwtv' ),
	'tropes'            => __( 'Tropes', 'lwtv' ),
	'genres'            => __( 'Genres', 'lwtv' ),
	'intersectionality' => __( 'Intersectionality', 'lwtv' ),
	'stars'             => __( 'Stars', 'lwtv' ),
	'triggers'          => __( 'Triggers', 'lwtv' ),
	'on-air'            => __( 'On Air', 'lwtv' ),
	'worth-it'          => __( 'Worth It', 'lwtv' ),
	'we-love-it'        => __( 'We Love It', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Shows statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_shows_subnav as $lwtv_slug => $lwtv_label ) {
		$lwtv_is_active = ( $view === $lwtv_slug );
		$lwtv_url       = ( 'overview' === $lwtv_slug ) ? $baseurl : $baseurl . $lwtv_slug . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$lwtv_is_active ? ' is-active' : '',
			esc_url( $lwtv_url ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_label )
		);
	}
	?>
</nav>
```

- [ ] **Step 2: Rewrite `shows.php` head to use the sub-nav + container**

In `plugins/lwtv-plugin/php/statistics/templates/shows.php`, replace the block from `?>` after `$shows_count`/overview data through the `include … shows/navbar.php` and the `<p>&nbsp;</p>`, i.e. replace:

```php
?>
<h2>
	<a href="/shows/">Total Shows</a> (<?php echo (int) $shows_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'shows/navbar.php';
?>

<p>&nbsp;</p>

<?php
```

with:

```php
?>
<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __FILE__ ) . 'shows/subnav.php';
	?>
<?php
```

Then, at the very end of `shows.php`, AFTER the `switch ( $view ) { … }` block and the WP_DEBUG comment, close the wrapper — add:

```php
?>
</div><!-- .lwtv-stats-overview -->
<?php
```

(Keep the existing `switch`/routing and the `$shows_count`/overview-data computation above untouched.)

- [ ] **Step 3: Enqueue the stats JS on Shows views**

In `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php`, the overview enqueue currently fires only when `'none' === $statistics`. Broaden it to also cover Shows. Replace:

```php
		// Overview page only: count-up + bar-grow animations. No jQuery dependency.
		if ( 'none' === $statistics ) {
```

with:

```php
		// Overview + Shows: count-up + bar-grow animations. No jQuery dependency.
		if ( in_array( $statistics, array( 'none', 'shows' ), true ) ) {
```

(The `$statistics = sanitize_key( get_query_var( 'statistics', 'none' ) );` line already exists above.)

- [ ] **Step 4: Sub-nav SCSS (light) — append inside the `.statistics { … }` overview block in `scss/addons/_stats.scss`**

```scss
	.lwtv-stats-subnav {
		display: flex;
		gap: 20px;
		margin-bottom: 1.5rem;
		overflow-x: auto;
		border-bottom: 1px solid colors.$lwtv-bordergrey;
	}

	.lwtv-stats-subnav-item {
		padding: 8px 2px;
		font-size: 0.875rem;
		white-space: nowrap;
		color: colors.$lwtv-medgrey;
		text-decoration: none;
		border-bottom: 2px solid transparent;
		margin-bottom: -1px;

		&:hover {
			color: colors.$lwtv-purple;
		}

		&.is-active {
			color: colors.$lwtv-dkgrey;
			border-bottom-color: colors.$lwtv-pink;
		}
	}
```

- [ ] **Step 5: Sub-nav SCSS (dark) — inside the dark `.statistics` block in `scss/partials/_colors-dark.scss`**

```scss
		.lwtv-stats-subnav-item {
			color: colors.$lwtv-ltpink;

			&.is-active {
				color: colors.$white;
				border-bottom-color: colors.$lwtv-pink;
			}
		}
```

- [ ] **Step 6: Lint + build**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```

- [ ] **Step 7: Verify**

```bash
curl -sk https://lwtv.local/statistics/shows/formats/ | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'   # -> Formats
curl -sk https://lwtv.local/statistics/shows/ | grep -c 'lwtv-stats-subnav\b'                                   # -> 1
curl -sk https://lwtv.local/statistics/shows/ | grep -o 'statistics-overview.js[^"]*'                            # -> present (JS enqueued)
```
Expected: sub-nav renders with the right active item; the JS is enqueued on shows; the page still renders its (old) view body below with no PHP fatal.

- [ ] **Step 8: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/shows/subnav.php plugins/lwtv-plugin/php/statistics/templates/shows.php plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): shows sub-nav, container, and animation enqueue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Shared sparkline helper + Shows Overview view

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/partials/sparkline.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main/overview.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows.php` (overview data already computed there)
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/overview.php`
- Modify: `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `generate_growth_series('shows')`; `$shows_count`, `$count_tropes`, `$count_genres`, `$top_tropes`, `$top_genres` (already computed in `shows.php` overview branch); `lwtv_stats_sparkline_points()`.
- Produces: `lwtv_stats_sparkline_points()` as a shared include.

- [ ] **Step 1: Create the shared sparkline include**

Create `plugins/lwtv-plugin/php/statistics/templates/partials/sparkline.php` with exactly the guarded function currently living in `main/overview.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shared helper: cumulative series -> SVG polyline points.
 *
 * @package LezWatch.TV
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
```

- [ ] **Step 2: Point `main/overview.php` at the shared include**

In `plugins/lwtv-plugin/php/statistics/templates/main/overview.php`, delete the entire `if ( ! function_exists( 'lwtv_stats_sparkline_points' ) ) { … }` block (the function definition) and replace it with:

```php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';
```

(This preserves behavior — the function is now defined by the shared include. The rest of `main/overview.php` is unchanged.)

- [ ] **Step 3: Compute the growth series in `shows.php` overview branch**

In `plugins/lwtv-plugin/php/statistics/templates/shows.php`, inside the existing `if ( 'overview' === $view ) { … }` block, after `$count_genres = count( $genres_data );`, add:

```php
	// Growth series for the Shows metric-card sparkline (real, cumulative).
	$shows_growth = lwtv_plugin()->generate_growth_series( 'shows' );
```

- [ ] **Step 4: Rewrite `shows/overview.php`**

Replace the entire contents of `plugins/lwtv-plugin/php/statistics/templates/shows/overview.php` with:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows overview: metric cards + trope-gap pull-stats + top tropes/genres panels.
 *
 * @package LezWatch.TV
 *
 * @var int   $shows_count
 * @var int   $count_tropes
 * @var int   $count_genres
 * @var array $top_tropes    slug => ['name','count', …], top 10 by count.
 * @var array $top_genres    slug => ['name','count', …], top 10 by count.
 * @var array $shows_growth  cumulative growth series for shows.
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

// A fixed, gently-rising representative sparkline for term-count cards
// (Tropes/Genres have no real time series — decorative only).
$shows_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$shows_cards = array(
	array(
		'type'    => 'shows',
		'label'   => __( 'Shows', 'lwtv' ),
		'count'   => (int) $shows_count,
		'caption' => __( 'TV series & films', 'lwtv' ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-television',
		'points'  => lwtv_stats_sparkline_points( $shows_growth ),
	),
	array(
		'type'    => 'characters', // green family (Tropes).
		'label'   => __( 'Tropes', 'lwtv' ),
		'count'   => (int) $count_tropes,
		'caption' => __( 'Distinct tropes tracked', 'lwtv' ),
		'svg'     => 'tag.svg',
		'icon'    => 'svg-tag',
		'points'  => lwtv_stats_sparkline_points( $shows_rep_series ),
	),
	array(
		'type'    => 'actors', // amber family (Genres).
		'label'   => __( 'Genres', 'lwtv' ),
		'count'   => (int) $count_genres,
		'caption' => __( 'Distinct genres tracked', 'lwtv' ),
		'svg'     => 'theater_masks.svg',
		'icon'    => 'svg-theater-masks',
		'points'  => lwtv_stats_sparkline_points( $shows_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Shows at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $shows_cards as $shows_card ) {
		?>
		<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $shows_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $shows_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $shows_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $shows_card['svg'], icon: $shows_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $shows_card['count']; ?>"><?php echo esc_html( number_format_i18n( $shows_card['count'] ) ); ?></span>
			<?php if ( '' !== $shows_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polyline points="<?php echo esc_attr( $shows_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $shows_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
// Top tropes / top genres panels (leader bars).
$shows_panels = array(
	array(
		'eyebrow' => __( 'Top Tropes', 'lwtv' ),
		'family'  => 'characters',
		'rows'    => $top_tropes,
		'base'    => '/trope/',
		'more'    => array( 'label' => $count_tropes, 'url' => $baseurl . 'tropes/' ),
	),
	array(
		'eyebrow' => __( 'Top Genres', 'lwtv' ),
		'family'  => 'actors',
		'rows'    => $top_genres,
		'base'    => '/genre/',
		'more'    => array( 'label' => $count_genres, 'url' => $baseurl . 'genres/' ),
	),
);
?>
<div class="lwtv-panels">
	<?php
	foreach ( $shows_panels as $shows_panel ) {
		$shows_top = ! empty( $shows_panel['rows'] ) ? max( array_map( fn( $r ) => (int) $r['count'], $shows_panel['rows'] ) ) : 0;
		?>
		<section class="lwtv-panel bg-light">
			<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $shows_panel['eyebrow'] ); ?></p>
			<div class="lwtv-bars lwtv-bars--<?php echo esc_attr( $shows_panel['family'] ); ?>">
				<?php
				foreach ( $shows_panel['rows'] as $shows_slug => $shows_row ) {
					$shows_pct   = ( $shows_count > 0 ) ? round( ( (int) $shows_row['count'] / $shows_count ) * 100, 1 ) : 0;
					$shows_width = ( $shows_top > 0 ) ? round( ( (int) $shows_row['count'] / $shows_top ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-bar-row">
						<a class="lwtv-bar-name" href="<?php echo esc_url( site_url( $shows_panel['base'] . $shows_slug ) ); ?>"><?php echo esc_html( $shows_row['name'] ); ?></a>
						<div class="progress lwtv-bar-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( $shows_width ); ?>" aria-valuenow="<?php echo esc_attr( (int) $shows_row['count'] ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $shows_top ); ?>"></div>
						</div>
						<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( (int) $shows_row['count'] ) . ' · ' . $shows_pct . '%' ); ?></span>
					</div>
					<?php
				}
				?>
			</div>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $shows_panel['more']['url'] ); ?>">
				<?php
				printf(
					/* translators: %s: total count. */
					esc_html__( 'View all %s →', 'lwtv' ),
					esc_html( number_format_i18n( (int) $shows_panel['more']['label'] ) )
				);
				?>
			</a>
		</section>
		<?php
	}
	?>
</div>
```

> The "two pull-stats" (Bury Your Queers / Happy Endings) from the handoff are deferred: they require identifying the specific `lez_tropes` slugs for buried vs. happy-ending shows. **This task ships the counters + Top-Tropes/Top-Genres panels; the pull-stat band is added in Task 3b below** once the slugs are confirmed. If confirming the slugs is quick during this task, fold 3b in; otherwise ship without and do 3b next.

- [ ] **Step 5: SCSS — 3-up metric grid + ranked-bar family fill (light), in `scss/addons/_stats.scss` `.statistics` block**

```scss
	.lwtv-metric-grid--3 {
		grid-template-columns: repeat(3, 1fr);
	}

	// Ranked-bar family fills (override the default teal progressbar within a family list).
	.lwtv-bars--characters .progress-bar {
		background-color: colors.$lwtv-stats-green !important;
	}

	.lwtv-bars--actors .progress-bar {
		background-color: colors.$lwtv-stats-yellow !important;
	}

	.lwtv-bars--shows .progress-bar {
		background-color: colors.$lwtv-stats-blue !important;
	}
```

Add the matching narrow-screen rule if not already covered by the existing `@media (max-width:767px){ .lwtv-metric-grid{grid-template-columns:repeat(2,1fr) } }` — the `--3` modifier inherits it (2-up then 1-up), which is fine.

- [ ] **Step 6: SCSS (dark) — in `scss/partials/_colors-dark.scss` `.statistics` block**

The family fills should stay legible in dark. Add:

```scss
		.lwtv-bars--characters .progress-bar,
		.lwtv-bars--actors .progress-bar,
		.lwtv-bars--shows .progress-bar {
			background-color: colors.$lwtv-ltpink !important;
		}
```

(Keeps dark bars on the theme's pink, matching the existing dark `.statistics .progress-bar` behaviour.)

- [ ] **Step 7: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/shows/ | grep -o 'lwtv-metric-card[^>]*' | head -3
curl -sk https://lwtv.local/statistics/shows/ | grep -o 'data-count-to="[0-9]*"' | head -3
curl -sk https://lwtv.local/statistics/shows/ | grep -o '#tag\|#theater_masks\|#tv' | sort -u
```
Expected: 3 metric cards (shows/characters/actors families); count-to values for shows/tropes/genres; real sprite `<use>` ids `#tv`, `#tag`, `#theater_masks` (no `<i>` fallback); Top Tropes/Genres panels render with bars.

- [ ] **Step 8: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/sparkline.php plugins/lwtv-plugin/php/statistics/templates/main/overview.php plugins/lwtv-plugin/php/statistics/templates/shows.php plugins/lwtv-plugin/php/statistics/templates/shows/overview.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): shows overview cards and top tropes/genres panels

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3b: Trope Gap pull-stats band

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows.php` (compute the two counts)
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/overview.php` (render the 2-up band)
- Modify: `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `lez_tropes` term counts (already in `$tropes_data` in `shows.php`).
- Produces: nothing downstream.

- [ ] **Step 1: Identify the two trope slugs**

Run:
```bash
php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" term list lez_tropes --fields=slug,name,count --format=csv 2>/dev/null | grep -iE "dead|bury|buried|happy|ending"
```
Pick the slug whose name is the "buried/dead queers" trope (Bury Your Queers stat) and the "happy ending" trope (Happy Endings stat). Record both slugs. If neither exists cleanly, STOP and report — do not invent a stat.

- [ ] **Step 2: Compute the two counts in `shows.php`**

In the `if ( 'overview' === $view )` block of `shows.php`, after `$shows_growth = …;`, add (substituting the confirmed slugs for `SLUG_BURIED` / `SLUG_HAPPY`):

```php
	// Trope Gap pull-stats: counts for the buried vs. happy-ending tropes.
	$trope_buried = isset( $tropes_data['SLUG_BURIED'] ) ? (int) $tropes_data['SLUG_BURIED']['count'] : 0;
	$trope_happy  = isset( $tropes_data['SLUG_HAPPY'] ) ? (int) $tropes_data['SLUG_HAPPY']['count'] : 0;
```

- [ ] **Step 3: Render the 2-up band in `shows/overview.php`**

Immediately after the closing `</div>` of `.lwtv-metric-grid--3` and BEFORE the `$shows_panels` block, insert:

```php
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Trope Gap', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-byg card-header dead-characters">
		<div class="lwtv-byg-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Queers', 'lwtv' ); ?></span>
			<p class="lwtv-byg-line"><strong data-count-to="<?php echo (int) $trope_buried; ?>"><?php echo esc_html( number_format_i18n( $trope_buried ) ); ?></strong> <?php esc_html_e( 'shows kill a queer character.', 'lwtv' ); ?></p>
		</div>
	</div>
	<div class="lwtv-byg card-header characters">
		<div class="lwtv-byg-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Happy Endings', 'lwtv' ); ?></span>
			<p class="lwtv-byg-line"><strong data-count-to="<?php echo (int) $trope_happy; ?>"><?php echo esc_html( number_format_i18n( $trope_happy ) ); ?></strong> <?php esc_html_e( 'shows give them a happy ending.', 'lwtv' ); ?></p>
		</div>
	</div>
</div>
```

Add `@var int $trope_buried` and `@var int $trope_happy` to the file's docblock.

- [ ] **Step 4: SCSS — 2-up pull-stats grid (light), `.statistics` block**

```scss
	.lwtv-pullstats {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		margin-bottom: 1.5rem;

		@media (max-width: 575px) {
			grid-template-columns: 1fr;
		}
	}
```

(The `.lwtv-byg` band styling is reused as-is; the green variant gets its family colors from `.card-header.characters`. No dark SCSS needed — the `.card-header.*` families already carry dark variants.)

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/shows/ | grep -c 'lwtv-byg'   # -> 2
```
Expected: two pull-stat bands (red + green) with count-up numbers.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/shows.php plugins/lwtv-plugin/php/statistics/templates/shows/overview.php scss/addons/_stats.scss
git commit -m "feat(stats): shows overview trope-gap pull-stats

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Reusable donut partial + Formats view

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/formats.php`
- Modify: `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: a `$donut` array in scope (see contract below).
- Produces: the donut markup contract that Task 6 reuses:
  `$donut = [ 'segments' => [ ['label'=>string,'count'=>int,'pct'=>float,'class'=>string], … ], 'center'=>int, 'center_sub'=>string, 'eyebrow'=>string, 'headline'=>string, 'description'=>string ]`.
  `class` is a CSS modifier suffix (e.g. `dkpink`, `pink`, `mid`, `ltpink`, `green`, `amber`, `red`, `grey`, `gold`, `silver`, `bronze`, `sev-med`, `sev-low`) selecting a stroke/dot color.

- [ ] **Step 1: Create `partials/donut.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable donut chart: SVG ring (pathLength=100) + legend with mini-bars.
 * Ring renders at final proportions; center figure + legend counts count up.
 *
 * @package LezWatch.TV
 *
 * @var array $donut {
 *   @type array  $segments   Ordered [ ['label','count','pct','class'], … ].
 *   @type int    $center     Center headline figure.
 *   @type string $center_sub Center sublabel.
 *   @type string $eyebrow    Section eyebrow.
 *   @type string $headline   Headline sentence.
 *   @type string $description Supporting sentence.
 * }
 */

$donut_segments = $donut['segments'] ?? array();
$donut_offset   = 0.0; // cumulative share for stroke-dashoffset.
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $donut['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-donut-card bg-light">
	<div class="lwtv-donut-figure">
		<svg class="lwtv-donut" viewBox="0 0 120 120" role="img" aria-label="<?php echo esc_attr( $donut['eyebrow'] ?? '' ); ?>">
			<g transform="rotate(-90 60 60)">
				<circle class="lwtv-donut-track" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" />
				<?php
				foreach ( $donut_segments as $donut_seg ) {
					$donut_share = max( 0, (float) $donut_seg['pct'] );
					printf(
						'<circle class="lwtv-donut-seg lwtv-donut-seg--%1$s" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" stroke-dasharray="%2$s %3$s" stroke-dashoffset="%4$s" />',
						esc_attr( $donut_seg['class'] ),
						esc_attr( (string) $donut_share ),
						esc_attr( (string) ( 100 - $donut_share ) ),
						esc_attr( (string) ( -1 * $donut_offset ) )
					);
					$donut_offset += $donut_share;
				}
				?>
			</g>
		</svg>
		<div class="lwtv-donut-center">
			<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) ( $donut['center'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $donut['center'] ?? 0 ) ) ); ?></span>
			<span class="lwtv-donut-center-sub"><?php echo esc_html( $donut['center_sub'] ?? '' ); ?></span>
		</div>
	</div>

	<div class="lwtv-donut-body">
		<h2 class="lwtv-donut-headline"><?php echo esc_html( $donut['headline'] ?? '' ); ?></h2>
		<?php if ( ! empty( $donut['description'] ) ) : ?>
			<p class="lwtv-donut-desc"><?php echo esc_html( $donut['description'] ); ?></p>
		<?php endif; ?>
		<ul class="lwtv-donut-legend">
			<?php
			foreach ( $donut_segments as $donut_seg ) {
				?>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>"></span>
					<span class="lwtv-donut-legend-name"><?php echo esc_html( $donut_seg['label'] ); ?></span>
					<div class="progress lwtv-donut-legend-track">
						<div class="progress-bar lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $donut_seg['pct'] ); ?>" aria-valuenow="<?php echo esc_attr( (int) $donut_seg['count'] ); ?>" aria-valuemin="0" aria-valuemax="100"></div>
					</div>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( (int) $donut_seg['count'] ) . ' · ' . $donut_seg['pct'] . '%' ); ?></span>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
</section>
```

- [ ] **Step 2: Rewrite `shows/formats.php` to build the donut**

Replace the entire contents of `plugins/lwtv-plugin/php/statistics/templates/shows/formats.php` with:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Formats: donut of format breakdown (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$formats_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'formats' );
$formats_data = is_array( $formats_raw ) ? (array) reset( $formats_raw ) : array();
$formats_total = (int) $shows_count;

// Raspberry ramp classes, darkest (largest) first.
$formats_ramp = array( 'dkpink', 'pink', 'mid', 'ltpink', 'ltpink' );

$formats_segments = array();
$formats_i        = 0;
foreach ( $formats_data as $formats_row ) {
	$formats_count      = (int) $formats_row['count'];
	$formats_segments[] = array(
		'label' => $formats_row['name'],
		'count' => $formats_count,
		'pct'   => ( $formats_total > 0 ) ? round( ( $formats_count / $formats_total ) * 100, 1 ) : 0,
		'class' => $formats_ramp[ min( $formats_i, count( $formats_ramp ) - 1 ) ],
	);
	++$formats_i;
}

// Headline from the leading slice.
$formats_lead = $formats_segments[0] ?? array( 'pct' => 0 );
$formats_in10 = ( $formats_lead['pct'] > 0 ) ? (int) round( $formats_lead['pct'] / 10 ) : 0;

$donut = array(
	'segments'    => $formats_segments,
	'center'      => $formats_total,
	'center_sub'  => __( 'shows', 'lwtv' ),
	'eyebrow'     => __( 'Format Breakdown', 'lwtv' ),
	/* translators: %d: "N in ten" figure for the leading format. */
	'headline'    => ( $formats_in10 > 0 ) ? sprintf( __( '%d in ten are full TV series', 'lwtv' ), $formats_in10 ) : __( 'Format breakdown', 'lwtv' ),
	'description' => __( 'Feature films and short-form web series make up most of the rest; true mini-series stay rare.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 3: Donut SCSS (light), `.statistics` block in `scss/addons/_stats.scss`**

```scss
	.lwtv-donut-card {
		display: flex;
		flex-wrap: wrap;
		gap: 28px;
		align-items: center;
		padding: 24px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;
	}

	.lwtv-donut-figure {
		position: relative;
		flex: 0 0 auto;
		width: 180px;
		height: 180px;
	}

	.lwtv-donut {
		width: 100%;
		height: 100%;
	}

	.lwtv-donut-track {
		stroke: colors.$lwtv-ltgrey;
	}

	.lwtv-donut-center {
		position: absolute;
		inset: 0;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}

	.lwtv-donut-center-num {
		font-size: 1.875rem;
		font-weight: 700;
		line-height: 1;
		font-variant-numeric: tabular-nums;
		color: colors.$lwtv-dkgrey;
	}

	.lwtv-donut-center-sub {
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-donut-body {
		flex: 1 1 320px;
	}

	.lwtv-donut-headline {
		margin: 0 0 0.25rem;
		font-size: 1.25rem;
		font-weight: 700;
	}

	.lwtv-donut-desc {
		margin: 0 0 1rem;
		font-size: 0.8125rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-donut-legend {
		padding: 0;
		margin: 0;
		list-style: none;
	}

	.lwtv-donut-legend-row {
		display: grid;
		grid-template-columns: 12px 96px 1fr 96px;
		gap: 10px;
		align-items: center;
		padding: 3px 0;
		font-size: 0.8125rem;
	}

	.lwtv-donut-dot {
		width: 12px;
		height: 12px;
		border-radius: 50%;
	}

	.lwtv-donut-legend-track {
		height: 8px;
		border-radius: 999px;

		.progress-bar {
			border-radius: 999px;
			transition: none;
		}
	}

	.lwtv-donut-legend-val {
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
		font-variant-numeric: tabular-nums;
		text-align: right;
		white-space: nowrap;
	}

	// Segment/dot colors — stroke for ring, background for dots/mini-bars.
	.lwtv-donut-seg--dkpink { stroke: colors.$lwtv-dkpink; background-color: colors.$lwtv-dkpink; }
	.lwtv-donut-seg--pink   { stroke: colors.$lwtv-pink;   background-color: colors.$lwtv-pink; }
	.lwtv-donut-seg--mid    { stroke: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 55%); background-color: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 55%); }
	.lwtv-donut-seg--ltpink { stroke: colors.$lwtv-ltpink; background-color: colors.$lwtv-ltpink; }
	.lwtv-donut-seg--green  { stroke: colors.$lwtv-stats-green;  background-color: colors.$lwtv-stats-green; }
	.lwtv-donut-seg--amber  { stroke: colors.$lwtv-stats-yellow; background-color: colors.$lwtv-stats-yellow; }
	.lwtv-donut-seg--red    { stroke: colors.$lwtv-red;    background-color: colors.$lwtv-red; }
	.lwtv-donut-seg--grey   { stroke: colors.$lwtv-medgrey; background-color: colors.$lwtv-medgrey; }
	.lwtv-donut-seg--gold   { stroke: colors.$lwtv-gold;   background-color: colors.$lwtv-gold; }
	.lwtv-donut-seg--silver { stroke: colors.$lwtv-silver; background-color: colors.$lwtv-silver; }
	.lwtv-donut-seg--bronze { stroke: colors.$lwtv-bronze; background-color: colors.$lwtv-bronze; }
	.lwtv-donut-seg--sev-med { stroke: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 65%); background-color: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 65%); }
	.lwtv-donut-seg--sev-low { stroke: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 25%); background-color: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 25%); }

	// Legend mini-bar fill must use the segment color, overriding the base teal.
	.lwtv-donut-legend-track .progress-bar {
		&.lwtv-donut-seg--dkpink { background-color: colors.$lwtv-dkpink !important; }
		&.lwtv-donut-seg--pink   { background-color: colors.$lwtv-pink !important; }
		&.lwtv-donut-seg--mid    { background-color: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 55%) !important; }
		&.lwtv-donut-seg--ltpink { background-color: colors.$lwtv-ltpink !important; }
		&.lwtv-donut-seg--green  { background-color: colors.$lwtv-stats-green !important; }
		&.lwtv-donut-seg--amber  { background-color: colors.$lwtv-stats-yellow !important; }
		&.lwtv-donut-seg--red    { background-color: colors.$lwtv-red !important; }
		&.lwtv-donut-seg--grey   { background-color: colors.$lwtv-medgrey !important; }
		&.lwtv-donut-seg--gold   { background-color: colors.$lwtv-gold !important; }
		&.lwtv-donut-seg--silver { background-color: colors.$lwtv-silver !important; }
		&.lwtv-donut-seg--bronze { background-color: colors.$lwtv-bronze !important; }
		&.lwtv-donut-seg--sev-med { background-color: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 65%) !important; }
		&.lwtv-donut-seg--sev-low { background-color: color.mix(colors.$lwtv-red, colors.$lwtv-yellow, 25%) !important; }
	}
```

- [ ] **Step 4: Donut SCSS (dark), `.statistics` block in `scss/partials/_colors-dark.scss`**

```scss
		.lwtv-donut-track {
			stroke: colors.$lwtv-medgrey;
		}

		.lwtv-donut-center-num {
			color: colors.$white;
		}
```

(Segment colors are token-based and read acceptably in dark; the `--grey`/`--ltpink` neutrals already sit on the dark surface. No per-segment dark overrides.)

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/shows/formats/ | grep -o 'lwtv-donut-seg--[a-z]*' | sort | uniq -c
curl -sk https://lwtv.local/statistics/shows/formats/ | grep -o 'stroke-dasharray="[0-9.]* [0-9.]*"' | head
curl -sk https://lwtv.local/statistics/shows/formats/ | grep -o 'lwtv-donut-center-num[^>]*>[^<]*'
```
Expected: donut ring with segments (dkpink/pink/mid/ltpink), dash arrays summing to ~100, center figure = total shows, legend rows with counts·pct. Confirm visually in the browser (light + dark) that the ring renders and legend mini-bars grow.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/donut.php plugins/lwtv-plugin/php/statistics/templates/shows/formats.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): reusable donut partial and shows formats view

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Ranked-bar views — Tropes, Genres, Intersectionality

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/{tropes,genres,intersectionality}.php`

**Interfaces:**
- Consumes: `generate_shows_statistics('array', <type>)`; `$shows_count`; the existing `.lwtv-panel` / `.lwtv-bar-row` / `.lwtv-bars--<family>` classes (from committed overview + Task 3).
- Produces: nothing downstream.

Each view is the same pattern: unwrap raw data, sort by count desc, render a full-width `.lwtv-panel` of `.lwtv-bar-row`s in the family color. To stay DRY, add ONE shared renderer partial and three thin view files.

- [ ] **Step 1: Create the shared ranked-bar partial `partials/ranked-bars.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable ranked horizontal-bar list (full-width panel).
 *
 * @package LezWatch.TV
 *
 * @var array  $ranked {
 *   @type array  $rows    slug => ['name','count','url'(optional)].
 *   @type int    $total   Denominator for pct (all shows).
 *   @type string $family  Color family: characters|actors|shows.
 *   @type string $eyebrow Section eyebrow.
 *   @type string $base    URL base for row links (e.g. '/trope/'); '' to use row 'url'.
 * }
 */

$ranked_rows = $ranked['rows'] ?? array();
uasort( $ranked_rows, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$ranked_top   = ! empty( $ranked_rows ) ? max( array_map( fn( $r ) => (int) $r['count'], $ranked_rows ) ) : 0;
$ranked_total = (int) ( $ranked['total'] ?? 0 );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $ranked['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-panel bg-light">
	<div class="lwtv-bars lwtv-bars--<?php echo esc_attr( $ranked['family'] ?? 'shows' ); ?>">
		<?php
		foreach ( $ranked_rows as $ranked_slug => $ranked_row ) {
			$ranked_count = (int) $ranked_row['count'];
			$ranked_pct   = ( $ranked_total > 0 ) ? round( ( $ranked_count / $ranked_total ) * 100, 1 ) : 0;
			$ranked_width = ( $ranked_top > 0 ) ? round( ( $ranked_count / $ranked_top ) * 100, 1 ) : 0;
			$ranked_href  = ( ! empty( $ranked['base'] ) ) ? site_url( $ranked['base'] . $ranked_slug ) : ( $ranked_row['url'] ?? '#' );
			?>
			<div class="lwtv-bar-row">
				<a class="lwtv-bar-name" href="<?php echo esc_url( $ranked_href ); ?>"><?php echo esc_html( $ranked_row['name'] ); ?></a>
				<div class="progress lwtv-bar-track">
					<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $ranked_width ); ?>" aria-valuenow="<?php echo esc_attr( (string) $ranked_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $ranked_top ); ?>"></div>
				</div>
				<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( $ranked_count ) . ' · ' . $ranked_pct . '%' ); ?></span>
			</div>
			<?php
		}
		?>
	</div>
</section>
```

- [ ] **Step 2: Rewrite `shows/tropes.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Tropes: ranked bars (green).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$tropes_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'tropes' );
$ranked     = array(
	'rows'    => is_array( $tropes_raw ) ? (array) reset( $tropes_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'characters',
	'eyebrow' => __( 'Trope Breakdown', 'lwtv' ),
	'base'    => '/trope/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```

- [ ] **Step 3: Rewrite `shows/genres.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Genres: ranked bars (amber). Shares add up past 100% (multi-value taxonomy).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$genres_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'genres' );
$ranked     = array(
	'rows'    => is_array( $genres_raw ) ? (array) reset( $genres_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'actors',
	'eyebrow' => __( 'Genre Breakdown', 'lwtv' ),
	'base'    => '/genre/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```

- [ ] **Step 4: Rewrite `shows/intersectionality.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Intersectionality: ranked bars (blue).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$inter_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'intersections' );
$ranked    = array(
	'rows'    => is_array( $inter_raw ) ? (array) reset( $inter_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'shows',
	'eyebrow' => __( 'Intersectionality Breakdown', 'lwtv' ),
	'base'    => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
for v in tropes genres intersectionality; do
  echo "== $v =="; curl -sk "https://lwtv.local/statistics/shows/$v/" | grep -c 'lwtv-bar-row'
done
```
Expected: each view renders many ranked `.lwtv-bar-row`s, correctly sorted (largest first), family-colored (green/amber/blue in light; pink in dark), with `data-grow-to` widths. No PHP fatal.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/ranked-bars.php plugins/lwtv-plugin/php/statistics/templates/shows/tropes.php plugins/lwtv-plugin/php/statistics/templates/shows/genres.php plugins/lwtv-plugin/php/statistics/templates/shows/intersectionality.php
git commit -m "feat(stats): ranked-bar views for tropes, genres, intersectionality

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Donut views — Stars, Triggers, Worth It, We Love It

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/{stars,triggers,worth-it,we-love-it}.php`

**Interfaces:**
- Consumes: `generate_shows_statistics('array', <type>)`; `$shows_count`; the donut partial + segment classes (Task 4).
- Produces: nothing downstream.

- [ ] **Step 1: Rewrite `shows/stars.php`** (medals; center = No Star; compute None = total − sum)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Stars: donut of star ratings (medal colors).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$stars_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'stars' );
$stars_data = is_array( $stars_raw ) ? (array) reset( $stars_raw ) : array();
$stars_total = (int) $shows_count;

$stars_order = array(
	'gold'   => array( __( 'Gold', 'lwtv' ), 'gold' ),
	'silver' => array( __( 'Silver', 'lwtv' ), 'silver' ),
	'bronze' => array( __( 'Bronze', 'lwtv' ), 'bronze' ),
	'anti'   => array( __( 'Anti', 'lwtv' ), 'red' ),
);

$stars_segments = array();
$stars_sum      = 0;
foreach ( $stars_order as $stars_key => $stars_meta ) {
	$stars_count      = isset( $stars_data[ $stars_key ] ) ? (int) $stars_data[ $stars_key ]['count'] : 0;
	$stars_sum       += $stars_count;
	$stars_segments[] = array(
		'label' => $stars_meta[0],
		'count' => $stars_count,
		'pct'   => ( $stars_total > 0 ) ? round( ( $stars_count / $stars_total ) * 100, 1 ) : 0,
		'class' => $stars_meta[1],
	);
}
$stars_none = max( 0, $stars_total - $stars_sum );
// "No Star" leads the legend (largest); prepend.
array_unshift(
	$stars_segments,
	array(
		'label' => __( 'No Star', 'lwtv' ),
		'count' => $stars_none,
		'pct'   => ( $stars_total > 0 ) ? round( ( $stars_none / $stars_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	)
);

$donut = array(
	'segments'    => $stars_segments,
	'center'      => $stars_none,
	'center_sub'  => __( 'no star', 'lwtv' ),
	'eyebrow'     => __( 'Star Ratings', 'lwtv' ),
	'headline'    => __( 'Only a small share earn a star at all', 'lwtv' ),
	'description' => __( 'A star is a mark of distinction, so most shows carry none. Of those that do, bronze is the most common — and only a handful earn an “anti” flag.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 2: Rewrite `shows/triggers.php`** (red severity ramp; center = None)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Triggers: donut of trigger-warning severity.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$trig_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'triggers' );
$trig_data = is_array( $trig_raw ) ? (array) reset( $trig_raw ) : array();
$trig_total = (int) $shows_count;

$trig_order = array(
	'high'   => array( __( 'High', 'lwtv' ), 'red' ),
	'medium' => array( __( 'Medium', 'lwtv' ), 'sev-med' ),
	'low'    => array( __( 'Low', 'lwtv' ), 'sev-low' ),
);

$trig_segments = array();
$trig_sum      = 0;
foreach ( $trig_order as $trig_key => $trig_meta ) {
	$trig_count      = isset( $trig_data[ $trig_key ] ) ? (int) $trig_data[ $trig_key ]['count'] : 0;
	$trig_sum       += $trig_count;
	$trig_segments[] = array(
		'label' => $trig_meta[0],
		'count' => $trig_count,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_count / $trig_total ) * 100, 1 ) : 0,
		'class' => $trig_meta[1],
	);
}
$trig_none = max( 0, $trig_total - $trig_sum );
array_unshift(
	$trig_segments,
	array(
		'label' => __( 'None', 'lwtv' ),
		'count' => $trig_none,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_none / $trig_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	)
);

$donut = array(
	'segments'    => $trig_segments,
	'center'      => $trig_none,
	'center_sub'  => __( 'no warning', 'lwtv' ),
	'eyebrow'     => __( 'Trigger Warnings', 'lwtv' ),
	'headline'    => __( 'About half carry no warning at all', 'lwtv' ),
	'description' => __( 'Where a show does carry a content warning, it is most often a low-severity note rather than a high one.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 3: Rewrite `shows/worth-it.php`** (semantic; center = Yes)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Worth It: donut of worth-it ratings (semantic).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$worth_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'worth-it' );
$worth_data = is_array( $worth_raw ) ? (array) reset( $worth_raw ) : array();
$worth_total = (int) $shows_count;

$worth_order = array(
	'yes' => array( __( 'Yes', 'lwtv' ), 'green' ),
	'meh' => array( __( 'Meh', 'lwtv' ), 'amber' ),
	'no'  => array( __( 'No', 'lwtv' ), 'red' ),
	'tbd' => array( __( 'TBD', 'lwtv' ), 'grey' ),
);

$worth_segments = array();
$worth_yes      = 0;
foreach ( $worth_order as $worth_key => $worth_meta ) {
	$worth_count      = isset( $worth_data[ $worth_key ] ) ? (int) $worth_data[ $worth_key ]['count'] : 0;
	if ( 'yes' === $worth_key ) {
		$worth_yes = $worth_count;
	}
	$worth_segments[] = array(
		'label' => $worth_meta[0],
		'count' => $worth_count,
		'pct'   => ( $worth_total > 0 ) ? round( ( $worth_count / $worth_total ) * 100, 1 ) : 0,
		'class' => $worth_meta[1],
	);
}

$donut = array(
	'segments'    => $worth_segments,
	'center'      => $worth_yes,
	'center_sub'  => __( 'rated “Yes”', 'lwtv' ),
	'eyebrow'     => __( 'Worth It Ratings', 'lwtv' ),
	'headline'    => __( 'Just under half are a clear yes', 'lwtv' ),
	'description' => __( 'Our editors rate every show. Roughly one in ten is a hard “no” — the rest sit somewhere in the middle or await review.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 4: Rewrite `shows/we-love-it.php`** (binary progress ring; center = loved)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → We Love It: binary "progress ring" of loved vs. everything else.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$love_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'we-love-it' );
$love_data = is_array( $love_raw ) ? (array) reset( $love_raw ) : array();
$love_total = (int) $shows_count;

$love_loved  = isset( $love_data['we_love'] ) ? (int) $love_data['we_love']['count'] : 0;
$love_others = isset( $love_data['we_do_not_love'] ) ? (int) $love_data['we_do_not_love']['count'] : max( 0, $love_total - $love_loved );

$donut = array(
	'segments'    => array(
		array(
			'label' => __( 'Shows we love', 'lwtv' ),
			'count' => $love_loved,
			'pct'   => ( $love_total > 0 ) ? round( ( $love_loved / $love_total ) * 100, 1 ) : 0,
			'class' => 'pink',
		),
		array(
			'label' => __( 'Everything else', 'lwtv' ),
			'count' => $love_others,
			'pct'   => ( $love_total > 0 ) ? round( ( $love_others / $love_total ) * 100, 1 ) : 0,
			'class' => 'grey',
		),
	),
	'center'      => $love_loved,
	'center_sub'  => __( 'we love', 'lwtv' ),
	'eyebrow'     => __( 'Shows We Love', 'lwtv' ),
	'headline'    => __( 'A rare and deliberate honor', 'lwtv' ),
	'description' => __( '“Shows We Love” is hand-picked, so it stays a small fraction of the whole database.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
for v in stars triggers worth-it we-love-it; do
  echo "== $v =="
  curl -sk "https://lwtv.local/statistics/shows/$v/" | grep -o 'lwtv-donut-center-num[^>]*>[^<]*'
  curl -sk "https://lwtv.local/statistics/shows/$v/" | grep -o 'stroke-dasharray="[0-9.]* [0-9.]*"' | wc -l
done
```
Expected: each donut renders; center figures = No Star / None / Yes / loved respectively; segment count matches (stars 5 incl. No Star, triggers 4 incl. None, worth-it 4, we-love-it 2); dash shares sum to ~100. Verify in browser (light + dark) — rings render final immediately, numbers count up.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/shows/stars.php plugins/lwtv-plugin/php/statistics/templates/shows/triggers.php plugins/lwtv-plugin/php/statistics/templates/shows/worth-it.php plugins/lwtv-plugin/php/statistics/templates/shows/we-love-it.php
git commit -m "feat(stats): donut views for stars, triggers, worth-it, we-love-it

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: On Air area trendline

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/partials/trendline.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/shows/on-air.php`
- Modify: `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `generate_shows_statistics('array', 'on-air')` → year series; `$trend` array in scope.
- Produces: nothing downstream.

- [ ] **Step 1: Confirm the On Air data shape**

```bash
php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" eval '$d=lwtv_plugin()->generate_shows_statistics("array","on-air"); echo substr(print_r($d,true),0,600);' 2>/dev/null
```
Expected: a wrapper (likely `['on_air' => [ <year> => ['name'=>year,'count'=>N,'url'=>…], … ]]`). Note the exact wrapper key and leaf shape; the code below unwraps with `reset()` and reads `name`(year)/`count`. If the shape differs, adjust the unwrap accordingly and note it.

- [ ] **Step 2: Create `partials/trendline.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable area trendline (SVG). Line + area render immediately; the
 * current-year headline figure counts up.
 *
 * @package LezWatch.TV
 *
 * @var array $trend {
 *   @type array  $points   Ordered [ ['year'=>int,'count'=>int], … ].
 *   @type string $eyebrow  Section eyebrow.
 *   @type string $headline Headline sentence.
 *   @type string $description Supporting sentence.
 *   @type int    $current  Current-year figure (counts up).
 *   @type int    $current_year Label year for the current figure.
 * }
 */

$trend_points = $trend['points'] ?? array();
$trend_w      = 800;
$trend_h      = 240; // baseline y for area.
$trend_pad    = 8;
$trend_counts = array_map( fn( $p ) => (int) $p['count'], $trend_points );
$trend_n      = count( $trend_counts );
$trend_max    = $trend_n ? max( $trend_counts ) : 0;
$trend_peak_i = 0;
foreach ( $trend_counts as $trend_i => $trend_c ) {
	if ( $trend_c === $trend_max ) {
		$trend_peak_i = $trend_i;
		break;
	}
}

$trend_xy = array();
foreach ( $trend_counts as $trend_i => $trend_c ) {
	$trend_x    = ( $trend_n > 1 ) ? round( ( $trend_i / ( $trend_n - 1 ) ) * $trend_w, 2 ) : 0;
	$trend_y    = ( $trend_max > 0 ) ? round( $trend_h - ( $trend_c / $trend_max ) * ( $trend_h - $trend_pad ), 2 ) : $trend_h;
	$trend_xy[] = array( $trend_x, $trend_y );
}
$trend_line = implode( ' ', array_map( fn( $p ) => $p[0] . ',' . $p[1], $trend_xy ) );
$trend_area = $trend_n ? ( '0,' . $trend_h . ' ' . $trend_line . ' ' . $trend_w . ',' . $trend_h ) : '';
$trend_peak = $trend_xy[ $trend_peak_i ] ?? array( 0, $trend_h );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $trend['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-trend-card bg-light">
	<div class="lwtv-trend-head">
		<div>
			<h2 class="lwtv-trend-headline"><?php echo esc_html( $trend['headline'] ?? '' ); ?></h2>
			<?php if ( ! empty( $trend['description'] ) ) : ?>
				<p class="lwtv-trend-desc"><?php echo esc_html( $trend['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="lwtv-trend-current">
			<span class="lwtv-trend-current-num" data-count-to="<?php echo (int) ( $trend['current'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $trend['current'] ?? 0 ) ) ); ?></span>
			<span class="lwtv-trend-current-sub">
				<?php
				printf(
					/* translators: %d: year. */
					esc_html__( 'on air in %d', 'lwtv' ),
					(int) ( $trend['current_year'] ?? 0 )
				);
				?>
			</span>
		</div>
	</div>

	<?php if ( '' !== $trend_area ) : ?>
		<svg class="lwtv-trend-svg" viewBox="0 0 <?php echo (int) $trend_w; ?> 280" preserveAspectRatio="none" role="img" aria-label="<?php echo esc_attr( $trend['eyebrow'] ?? '' ); ?>">
			<polygon class="lwtv-trend-area" points="<?php echo esc_attr( $trend_area ); ?>" />
			<polyline class="lwtv-trend-line" points="<?php echo esc_attr( $trend_line ); ?>" fill="none" stroke-width="2.5" />
			<circle class="lwtv-trend-peak" cx="<?php echo esc_attr( (string) $trend_peak[0] ); ?>" cy="<?php echo esc_attr( (string) $trend_peak[1] ); ?>" r="4" />
		</svg>
	<?php endif; ?>
</section>
```

- [ ] **Step 3: Rewrite `shows/on-air.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → On Air: area trendline of shows-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'on-air' );
$onair_data = is_array( $onair_raw ) ? (array) reset( $onair_raw ) : array();

$onair_points = array();
foreach ( $onair_data as $onair_row ) {
	$onair_points[] = array(
		'year'  => (int) ( $onair_row['name'] ?? 0 ),
		'count' => (int) ( $onair_row['count'] ?? 0 ),
	);
}
$onair_last = end( $onair_points ) ?: array( 'year' => (int) gmdate( 'Y' ), 'count' => 0 );

$trend = array(
	'points'       => $onair_points,
	'eyebrow'      => __( 'Shows On Air per Year', 'lwtv' ),
	'headline'     => __( 'More queer shows are on air than ever', 'lwtv' ),
	'description'  => __( 'The count climbed steadily for two decades and peaked recently; the latest dip reflects the current contraction in scripted TV.', 'lwtv' ),
	'current'      => (int) $onair_last['count'],
	'current_year' => (int) $onair_last['year'],
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/trendline.php';
```

- [ ] **Step 4: Trendline SCSS (light), `.statistics` block**

```scss
	.lwtv-trend-card {
		padding: 24px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;
	}

	.lwtv-trend-head {
		display: flex;
		gap: 16px;
		align-items: flex-start;
		justify-content: space-between;
		margin-bottom: 1rem;
	}

	.lwtv-trend-headline {
		margin: 0 0 0.25rem;
		font-size: 1.25rem;
		font-weight: 700;
	}

	.lwtv-trend-desc {
		margin: 0;
		max-width: 46ch;
		font-size: 0.8125rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-trend-current {
		flex: 0 0 auto;
		text-align: right;
	}

	.lwtv-trend-current-num {
		display: block;
		font-size: 2rem;
		font-weight: 700;
		line-height: 1;
		font-variant-numeric: tabular-nums;
		color: colors.$lwtv-pink;
	}

	.lwtv-trend-current-sub {
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-trend-svg {
		width: 100%;
		height: 220px;
	}

	.lwtv-trend-area {
		fill: colors.$lwtv-pink;
		opacity: 0.12;
	}

	.lwtv-trend-line {
		stroke: colors.$lwtv-pink;
	}

	.lwtv-trend-peak {
		fill: colors.$lwtv-pink;
	}
```

- [ ] **Step 5: Trendline SCSS (dark), `.statistics` block in `_colors-dark.scss`**

```scss
		.lwtv-trend-current-num {
			color: colors.$lwtv-pink;
		}
```

(Line/area/peak stay pink in dark per the handoff — token-based, no override needed beyond the current-num which would otherwise inherit `#main` white.)

- [ ] **Step 6: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/shows/on-air/ | grep -o 'lwtv-trend-current-num[^>]*>[^<]*'
curl -sk https://lwtv.local/statistics/shows/on-air/ | grep -o 'class="lwtv-trend-line"[^/]*points="[^"]\{0,60\}'
```
Expected: current-year figure present with `data-count-to`; a polyline with many points; area polygon; peak dot. Verify in browser (light + dark): pink line + faint area, peak dot, right-aligned current figure counting up.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/trendline.php plugins/lwtv-plugin/php/statistics/templates/shows/on-air.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): on-air area trendline view

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Remove old sub-nav + full verification

**Files:**
- Delete: `plugins/lwtv-plugin/php/statistics/templates/shows/navbar.php`

- [ ] **Step 1: Confirm `navbar.php` is unreferenced**

```bash
grep -rn "shows/navbar" plugins/ page-templates/
```
Expected: no matches (Task 2 replaced the include with `subnav.php`).

- [ ] **Step 2: Delete it**

```bash
git rm plugins/lwtv-plugin/php/statistics/templates/shows/navbar.php
```

- [ ] **Step 3: Full lint + build**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```
Expected: all clean.

- [ ] **Step 4: Full verification pass**

At `https://lwtv.local/statistics/shows/` and each of the 10 views, confirm against `design_handoff_statistics_shows/screenshots/`:
- Overview (`01-shows.png`/`01-dark.png`): 3 metric cards, Trope Gap pull-stats, Top Tropes/Genres panels.
- Formats (`02-shows.png`): raspberry donut. Stars (`03-shows.png`): medal donut, center "No Star". On Air (`04-shows.png`): pink trendline. Worth It (`02-dark.png` dark): semantic donut.
- Every view: primary tab bar (Shows active) + sub-nav (correct active item); light + dark; reduced-motion renders finals; narrow viewport (cards/legend/panels stack, sub-nav scrolls); no JS console errors; counts cross-check against the shows pages.
- Regression: `/statistics/` overview still renders correctly; `/statistics/characters/` (not redesigned) still renders, now with the shared tab bar.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(stats): remove superseded shows navbar partial

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- Primary tab bar → shell → Task 1. ✓
- Shows sub-nav + container + JS enqueue → Task 2. ✓
- Shared sparkline helper → Task 3 (Step 1-2). ✓
- Shows Overview (cards w/ real+representative sparklines, panels) → Task 3; pull-stats → Task 3b. ✓
- Donut partial + Formats → Task 4; Stars/Triggers/Worth It/We Love It → Task 6. ✓
- Ranked bars (Tropes/Genres/Intersectionality) → Task 5. ✓
- On Air trendline → Task 7. ✓
- Remove `navbar.php` → Task 8. ✓
- Reuse tokens/components; no hardcoded hex (donut/severity via `color.mix()` + tokens) → enforced in every SCSS step + Global Constraints. ✓
- Count-up + donut-rings-static + reduced-motion → existing JS reused; contract restated. ✓
- Dark mode via `.card-header.*`/token classes → dark SCSS steps per task. ✓
- i18n + escaping + divisor guards → in every view's code. ✓

**Placeholder scan:** The only deferred items are (a) the two Trope Gap trope slugs (Task 3b Step 1 resolves them via `wp term list`, with a STOP instruction if absent) and (b) the exact On Air wrapper key (Task 7 Step 1 confirms it live before coding). Both are explicit confirm-then-code steps, not vague placeholders. The representative sparkline series is concrete. All view code is complete.

**Type consistency:** The donut `$donut` contract (segments[label,count,pct,class], center, center_sub, eyebrow, headline, description) is defined in Task 4 and consumed identically in Task 6. The ranked `$ranked` contract (rows, total, family, eyebrow, base) is defined in Task 5's partial and used by all three views. The `$trend` contract (points, eyebrow, headline, description, current, current_year) is defined and consumed in Task 7. Segment `class` suffixes match the SCSS `.lwtv-donut-seg--*` set defined in Task 4 (dkpink/pink/mid/ltpink/green/amber/red/grey/gold/silver/bronze/sev-med/sev-low). `data-count-to`/`data-grow-to` match the existing JS.

## Known follow-ups (out of scope)
- The `.lwtv-bars--{family}` fills use `!important` to override the base `.statistics .progress-bar` teal; acceptable given the existing rule also uses `!important`. If that base rule is ever de-`!important`ed, revisit.
- Donut ring assumes segment shares sum to ~100 (they do, since Stars/Triggers add a computed None and the others are exhaustive); a large rounding drift would leave a hairline gap — cosmetic only.
