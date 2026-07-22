# Statistics on Actors Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the three `/statistics/actors/` views into the shared stats shell (existing primary tab bar + an Actors sub-nav) with server-rendered visualisations — a metric-card Overview (3 cards + 2 callouts + 2 leader/tail panels) and two demographic donuts (Sexuality, Gender) — reusing the components built in the Overview/Shows/Characters rounds.

**Architecture:** Preserve the render path (`statistics.php` shell → `actors.php` `switch($view)` → per-view partial). Reuse `partials/{donut,sparkline}.php`, `.lwtv-stats-subnav`, `.lwtv-metric-card`, `.lwtv-tropegap`/`.lwtv-pullstats`, `.lwtv-panel`+leader/tail, the donut ramp. Only new code: two small SCSS additions (`.lwtv-donut-seg--bordergrey`, `.lwtv-panels--2`) and per-view PHP configs. No Chart.js.

**Tech Stack:** PHP 8.1+ (`LWTV\` PSR-4), Bootstrap 5, SCSS (Dart Sass via `@wordpress/scripts`), inline SVG, existing count-up JS, Symbolicons sprite. No PHPUnit — verification is PHPCS + build + `curl`/`wp eval` + browser.

## Global Constraints

- **Reuse mandate:** reuse components + tokens; NO hardcoded hex; do NOT revert the user's committed color/size tweaks.
- **Family map:** Actors → **yellow** (`.card-header.actors` + `.lwtv-metric-icon.actors`); Sexual Orientations → **blue** (`sexuality` family classes); Gender → **green** — reuse the existing **`characters`** family classes (`.card-header.characters`, `.lwtv-metric-icon.characters`, `.lwtv-bars--characters`, `.lwtv-panel-icon.characters`), since actor-gender == green and those classes already provide green in light + dark (avoids new `actor_gender` family SCSS).
- **Tokens:** `$lwtv-stats-*`, `$lwtv-pink`/`$lwtv-ltpink`, `$lwtv-medgrey`/`$lwtv-dkgrey`/`$lwtv-bordergrey`; donut seg classes `dkpink/pink/mid/mid2/ltpink/grey` exist; new `bordergrey` added in Task 3. `@use "sass:color";` already at top of `_stats.scss`.
- **SCSS access:** `_stats.scss` uses `colors.$lwtv-…`; `_colors-dark.scss` uses `colors.$lwtv-…` except locally-overridden `$lwtv-medgrey` (bare). Match surrounding lines.
- **PHP:** 8.1+; WordPress-Extra PHPCS clean. Auto-escaped funcs (`lwtv_plugin`, `get_symbolicon`) not wrapped; `get_symbolicon` echoes carry `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`. i18n `'lwtv'`; `number_format_i18n()`.
- **Animation contract:** count-up `data-count-to` + visible final text; growable bars `data-grow-to` + `style="width:0"`; donut rings static. Guard every divisor; harden unwrap (`is_array && ! empty`).
- **Build:** `npm run buildquick` needs **Node 24** (`.nvmrc`; Node 18 fails `crypto is not defined`) → `source ~/.nvm/nvm.sh; nvm use` first. Never edit `blocks/build/`/`inc/dist/`; no version/CHANGELOG churn.
- **Editor hazard:** stylelint fix-on-save may mangle `_stats.scss` — after SCSS edits + build, `git diff` and confirm ONLY intended changes (no jammed-comment garbage / dropped rules). Pre-existing mangled comment near `_stats.scss:~205` is not yours.
- **Scope:** Actors section only; primary tab bar already in the shell; leave the `roles` switch case untouched.

## Environment (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`.
- **Site:** `https://lwtv.local/statistics/actors/` (self-signed → `curl -sk`).
- **wp-cli:** `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>`

## Data shapes (verified live)

`generate_actors_statistics('array', $type)` → `['actors' => ['<slug>'=>['count','name','url'], …]]` (unwrap guarded `reset()`), for both `gender` and `sexuality`. Total actors **5916**; growth series 9 yrs.
- Gender slugs: `cis-woman` 5264, `cis-man` 195, `cisgender` 9 (→ **Cisgender** 5468); trans/NB: `trans-woman` 135, `non-binary` 104, `trans-man` 63, `non-binary-transgender` 25, `genderfluid` 23, `genderqueer` 10, …; unknown: `unknown` 14, `undefined` 20.
- Sexuality slugs: `heterosexual` 3153 (**Straight**), `unknown` 1987 (**Unknown**), then queer: `homosexual` 256, `queer` 246, `bisexual` 155, `pansexual` 43, `lgbtq` 25, `non-heterosexual` 19, `gay` 10, `asexual` 9, …
- Groups: **cis** = `cis-woman`,`cis-man`,`cisgender`; **gender-unknown** = `unknown`,`undefined`; **straight** = `heterosexual`; **sexuality-unknown** = `unknown`.
- **LGBTQ+** = total − straight − sexuality-unknown (≈ 776). **Trans & NB** = total − cis − gender-unknown (≈ 414).

## Metric-card / callout / panel icons (verified in sprite)

Actors `user.svg`, Sexual Orientations `heart.svg`, Gender `venus-double.svg`, Openly-LGBTQ+ callout `rainbow.svg`, Trans&NB callout `group.svg`. Panels: Sexual Orientations `heart.svg`, Gender `venus-double.svg`. After building, confirm real `<use href="…#id">` (not `<i>`).

## File structure

**New:** `actors/subnav.php`. **Modified:** `actors.php`; `actors/{overview,sexuality,gender}.php`; `class-stats-enqueues.php`; `scss/addons/_stats.scss`. **Removed:** `actors/navbar.php`.

**Renderability invariant:** every task leaves `/statistics/actors/` + views rendering (old chart bodies remain until rewritten).

---

### Task 1: Actors shell — sub-nav, container, JS enqueue

**Files:** create `actors/subnav.php`; modify `actors.php`, `class-stats-enqueues.php`.

- [ ] **Step 1: Create `actors/subnav.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the actors stats section.
 */

$lwtv_actor_subnav = array(
	'overview'  => __( 'Overview', 'lwtv' ),
	'sexuality' => __( 'Sexuality', 'lwtv' ),
	'gender'    => __( 'Gender', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Actors statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_actor_subnav as $lwtv_slug => $lwtv_label ) {
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

- [ ] **Step 2: Rewrite the `actors.php` head to use the sub-nav + container**

In `plugins/lwtv-plugin/php/statistics/templates/actors.php`, replace:

```php
?>
<h2>
	<a href="/actors/">Total Actors</a> (<?php echo (int) $actor_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'actors/navbar.php';
?>

<p>&nbsp;</p>

<?php

switch ( $view ) {
```

with:

```php
?>
<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __FILE__ ) . 'actors/subnav.php';
	?>
<?php
switch ( $view ) {
```

Then at the very end of `actors.php`, after the WP_DEBUG comment block, add:

```php
?>
</div><!-- .lwtv-stats-overview -->
<?php
```

(Keep the `$valid_views`/`$view`/`$actor_count` logic and the taxonomy pre-compute untouched.)

- [ ] **Step 3: Enqueue the stats JS on Actors** — in `class-stats-enqueues.php`, broaden the gate:

```php
		if ( in_array( $statistics, array( 'none', 'shows', 'characters', 'actors' ), true ) ) {
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/actors/gender/ | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'   # -> Gender
curl -sk https://lwtv.local/statistics/actors/ | grep -c 'lwtv-stats-subnav\b'                                  # -> 1
curl -sk https://lwtv.local/statistics/actors/ | grep -o 'statistics-overview.js[^"]*'                           # -> present
```

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/actors/subnav.php plugins/lwtv-plugin/php/statistics/templates/actors.php plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php style.css style.min.css
git commit -m "feat(stats): actors sub-nav, container, and animation enqueue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Overview — cards + callouts + 2 panels

**Files:** modify `actors.php` (overview data), `actors/overview.php` (rewrite), `scss/addons/_stats.scss` (`.lwtv-panels--2`).

- [ ] **Step 1: Add overview extras to `actors.php`**

After the existing `$count_genders = count( $actor_gender_data );` line (and before `?>`), add:

```php
// Redesign overview extras.
$actor_growth = lwtv_plugin()->generate_growth_series( 'actors' );

// Group sums (stable slugs) for the donut/callout roll-ups.
$actor_cis_slugs      = array( 'cis-woman', 'cis-man', 'cisgender' );
$actor_gunknown_slugs = array( 'unknown', 'undefined' );
$actor_cis_sum        = 0;
$actor_gunknown_sum   = 0;
foreach ( $actor_gender_data as $actor_g_slug => $actor_g_row ) {
	if ( in_array( $actor_g_slug, $actor_cis_slugs, true ) ) {
		$actor_cis_sum += (int) $actor_g_row['count'];
	} elseif ( in_array( $actor_g_slug, $actor_gunknown_slugs, true ) ) {
		$actor_gunknown_sum += (int) $actor_g_row['count'];
	}
}
$actor_straight = isset( $actor_sexuality_data['heterosexual'] ) ? (int) $actor_sexuality_data['heterosexual']['count'] : 0;
$actor_sunknown = isset( $actor_sexuality_data['unknown'] ) ? (int) $actor_sexuality_data['unknown']['count'] : 0;

// Callout figures = "the rest", computed not stored.
$actor_lgbtq  = max( 0, (int) $actor_count - $actor_straight - $actor_sunknown );
$actor_transnb = max( 0, (int) $actor_count - $actor_cis_sum - $actor_gunknown_sum );
```

- [ ] **Step 2: Rewrite `actors/overview.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors overview: metric cards + representation callouts + top panels.
 *
 * @package LezWatch.TV
 *
 * @var int    $actor_count
 * @var int    $count_sexualities
 * @var int    $count_genders
 * @var array  $top_sexualities
 * @var array  $top_genders
 * @var array  $actor_growth
 * @var int    $actor_lgbtq
 * @var int    $actor_transnb
 * @var string $baseurl
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

$actor_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$actor_cards = array(
	array(
		'type'    => 'actors',
		'label'   => __( 'Actors', 'lwtv' ),
		'count'   => (int) $actor_count,
		'caption' => __( 'Who\'ve played a queer role', 'lwtv' ),
		'svg'     => 'user.svg',
		'icon'    => 'svg-user',
		'points'  => lwtv_stats_sparkline_points( $actor_growth ),
	),
	array(
		'type'    => 'sexuality',
		'label'   => __( 'Sexual Orientations', 'lwtv' ),
		'count'   => (int) $count_sexualities,
		'caption' => __( 'Distinct orientations tracked', 'lwtv' ),
		'svg'     => 'heart.svg',
		'icon'    => 'svg-heart',
		'points'  => lwtv_stats_sparkline_points( $actor_rep_series ),
	),
	array(
		'type'    => 'characters', // green family for actor gender.
		'label'   => __( 'Gender Identities', 'lwtv' ),
		'count'   => (int) $count_genders,
		'caption' => __( 'Distinct identities tracked', 'lwtv' ),
		'svg'     => 'venus-double.svg',
		'icon'    => 'svg-venus-double',
		'points'  => lwtv_stats_sparkline_points( $actor_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Actors at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $actor_cards as $actor_card ) {
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $actor_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $actor_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $actor_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $actor_card['svg'], icon: $actor_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $actor_card['count']; ?>"><?php echo esc_html( number_format_i18n( $actor_card['count'] ) ); ?></span>
			<?php if ( '' !== $actor_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polygon class="lwtv-sparkline-area" points="<?php echo esc_attr( $actor_card['points'] . ' 120,26 0,26' ); ?>" fill="currentColor" fill-opacity="0.15" stroke="none" />
					<polyline points="<?php echo esc_attr( $actor_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $actor_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$actor_lgbtq_ratio  = ( $actor_lgbtq > 0 ) ? (int) round( (int) $actor_count / $actor_lgbtq ) : 0;
$actor_trans_ratio  = ( $actor_transnb > 0 ) ? (int) round( (int) $actor_count / $actor_transnb ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Plays the Roles', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Openly LGBTQ+', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'rainbow.svg', icon: 'svg-rainbow', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_lgbtq; ?>"><?php echo esc_html( number_format_i18n( $actor_lgbtq ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of openly-LGBTQ+ actors. */
				esc_html__( 'actors are openly LGBTQ+ — about one in %d.', 'lwtv' ),
				(int) $actor_lgbtq_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'sexuality/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header sexuality">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Trans &amp; Non-binary', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'group.svg', icon: 'svg-users', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_transnb; ?>"><?php echo esc_html( number_format_i18n( $actor_transnb ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of trans/non-binary actors. */
				esc_html__( 'actors are trans or non-binary — roughly one in %d.', 'lwtv' ),
				(int) $actor_trans_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'gender/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>

<?php
$actor_panels = array(
	array(
		'title'  => __( 'Top Sexual Orientations', 'lwtv' ),
		'family' => 'sexuality',
		'svg'    => 'heart.svg',
		'icon'   => 'svg-heart',
		'rows'   => $top_sexualities,
		'base'   => '/actor_sexuality/',
		/* translators: %s: total orientations. */
		'sub'    => sprintf( __( '%s orientations tracked', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		/* translators: %s: total orientations. */
		'all'    => sprintf( __( 'View all %s orientations →', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		'more'   => $baseurl . 'sexuality/',
	),
	array(
		'title'  => __( 'Top Gender Identities', 'lwtv' ),
		'family' => 'characters', // green.
		'svg'    => 'venus-double.svg',
		'icon'   => 'svg-venus-double',
		'rows'   => $top_genders,
		'base'   => '/actor_gender/',
		/* translators: %s: total identities. */
		'sub'    => sprintf( __( '%s identities tracked', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		/* translators: %s: total identities. */
		'all'    => sprintf( __( 'View all %s identities →', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		'more'   => $baseurl . 'gender/',
	),
);
?>
<div class="lwtv-panels lwtv-panels--2">
	<?php
	foreach ( $actor_panels as $actor_panel ) {
		$actor_rows    = is_array( $actor_panel['rows'] ) ? $actor_panel['rows'] : array();
		$actor_leaders = array_slice( $actor_rows, 0, 5, true );
		$actor_tail    = array_slice( $actor_rows, 5, 5, true );
		?>
		<section class="lwtv-panel bg-light">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon <?php echo esc_attr( $actor_panel['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $actor_panel['svg'], icon: $actor_panel['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php echo esc_html( $actor_panel['title'] ); ?></h2>
					<p class="lwtv-panel-sub"><?php echo esc_html( $actor_panel['sub'] ); ?></p>
				</div>
			</header>
			<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $actor_panel['family'] ); ?>">
				<?php
				foreach ( $actor_leaders as $actor_slug => $actor_row ) {
					$actor_row_count = (int) $actor_row['count'];
					$actor_pct       = ( $actor_count > 0 ) ? round( ( $actor_row_count / (int) $actor_count ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-leader-row">
						<div class="lwtv-leader-head">
							<a class="lwtv-leader-name" href="<?php echo esc_url( home_url( $actor_panel['base'] . $actor_slug ) ); ?>"><?php echo esc_html( $actor_row['name'] ); ?></a>
							<span class="lwtv-leader-value"><?php echo esc_html( number_format_i18n( $actor_row_count ) . ' · ' . $actor_pct . '%' ); ?></span>
						</div>
						<div class="progress lwtv-leader-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $actor_pct ); ?>" aria-valuenow="<?php echo esc_attr( (string) $actor_row_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $actor_count ); ?>"></div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
			<?php if ( ! empty( $actor_tail ) ) : ?>
				<ul class="lwtv-tail">
					<?php
					foreach ( $actor_tail as $actor_slug => $actor_row ) {
						?>
						<li class="lwtv-tail-row">
							<a class="lwtv-tail-name" href="<?php echo esc_url( home_url( $actor_panel['base'] . $actor_slug ) ); ?>"><?php echo esc_html( $actor_row['name'] ); ?></a>
							<span class="lwtv-tail-count"><?php echo esc_html( number_format_i18n( (int) $actor_row['count'] ) ); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			<?php endif; ?>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $actor_panel['more'] ); ?>"><?php echo esc_html( $actor_panel['all'] ); ?></a>
		</section>
		<?php
	}
	?>
</div>
```

- [ ] **Step 3: Add `.lwtv-panels--2` SCSS** in `scss/addons/_stats.scss` under `.statistics` (next to `.lwtv-panels--3`):

```scss
	.lwtv-panels--2 {
		grid-template-columns: 1fr 1fr;

		@media (max-width: 767px) {
			grid-template-columns: 1fr;
		}
	}
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/actors/ | grep -oE '#user|#heart|#venus-double|#rainbow|#group' | sort -u
curl -sk https://lwtv.local/statistics/actors/ | grep -c 'lwtv-metric-card'   # -> 3
curl -sk https://lwtv.local/statistics/actors/ | grep -c 'lwtv-tropegap'      # -> 2
curl -sk https://lwtv.local/statistics/actors/ | grep -c 'lwtv-panel\b'       # -> 2
```
Expected: 3 cards (yellow/blue/green), real sprite icons; 2 callouts (LGBTQ+ ≈ 776, trans/nb ≈ 414); 2 panels (blue Straight leads / green Cisgender leads). No fatal.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/actors.php plugins/lwtv-plugin/php/statistics/templates/actors/overview.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): actors overview cards, callouts, and panels

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Donut views — Sexuality, Gender

**Files:** modify `actors/sexuality.php`, `actors/gender.php`; `scss/addons/_stats.scss` (`.lwtv-donut-seg--bordergrey`).

- [ ] **Step 1: Add `.lwtv-donut-seg--bordergrey`** in `scss/addons/_stats.scss` — in the seg-color group (next to `--grey`):

```scss
	.lwtv-donut-seg--bordergrey { stroke: colors.$lwtv-bordergrey; background-color: colors.$lwtv-bordergrey; }
```

and in the `.lwtv-donut-legend-track .progress-bar { … }` legend override group:

```scss
		&.lwtv-donut-seg--bordergrey { background-color: colors.$lwtv-bordergrey !important; }
```

- [ ] **Step 2: Rewrite `actors/sexuality.php`** (grey Straight + queer ramp + labeled Unknown + Other)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Sexuality: donut (grey straight + queer ramp + unknown).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

$sex_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $actor_count;

$sex_straight = isset( $sex_data['heterosexual'] ) ? (int) $sex_data['heterosexual']['count'] : 0;
$sex_unknown  = isset( $sex_data['unknown'] ) ? (int) $sex_data['unknown']['count'] : 0;
unset( $sex_data['heterosexual'], $sex_data['unknown'] );

// Remaining = queer orientations; rank and ramp the top 4, fold the rest into "Other".
uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$sex_ramp     = array( 'dkpink', 'pink', 'mid', 'mid2' );
$sex_segments = array(
	array(
		'label' => __( 'Straight', 'lwtv' ),
		'count' => $sex_straight,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_straight / $sex_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$sex_named = $sex_straight + $sex_unknown;
$sex_i     = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 4 || (int) $sex_row['count'] <= 0 ) {
		break;
	}
	$sex_count      = (int) $sex_row['count'];
	$sex_named     += $sex_count;
	$sex_segments[] = array(
		'label' => $sex_row['name'],
		'count' => $sex_count,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_count / $sex_total ) * 100, 1 ) : 0,
		'class' => $sex_ramp[ $sex_i ],
	);
	++$sex_i;
}
$sex_other = max( 0, $sex_total - $sex_named );
if ( $sex_other > 0 ) {
	$sex_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $sex_other,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_other / $sex_total ) * 100, 1 ) : 0,
		'class' => 'ltpink',
	);
}
$sex_segments[] = array(
	'label' => __( 'Unknown', 'lwtv' ),
	'count' => $sex_unknown,
	'pct'   => ( $sex_total > 0 ) ? round( ( $sex_unknown / $sex_total ) * 100, 1 ) : 0,
	'class' => 'bordergrey',
);

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'actors', 'lwtv' ),
	'eyebrow'     => __( 'Actor Sexual Orientation', 'lwtv' ),
	'headline'    => __( 'More than half the actors are straight', 'lwtv' ),
	'description' => __( 'Queer roles are still mostly played by straight actors, with openly-LGBTQ+ performers making up the rest — and a large share whose orientation is unrecorded.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 3: Rewrite `actors/gender.php`** (grey Cisgender + trans/NB ramp + Other)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Gender: donut (grey cisgender + trans/non-binary ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

$gen_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $actor_count;

$gen_cis_slugs = array( 'cis-woman', 'cis-man', 'cisgender' );
$gen_cis       = 0;
foreach ( $gen_cis_slugs as $gen_cis_slug ) {
	if ( isset( $gen_data[ $gen_cis_slug ] ) ) {
		$gen_cis += (int) $gen_data[ $gen_cis_slug ]['count'];
		unset( $gen_data[ $gen_cis_slug ] );
	}
}

// Remaining = trans / non-binary / unknown; ramp the top 4, fold the rest into "Other".
uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$gen_ramp     = array( 'dkpink', 'pink', 'mid', 'ltpink' );
$gen_segments = array(
	array(
		'label' => __( 'Cisgender', 'lwtv' ),
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$gen_named = $gen_cis;
$gen_i     = 0;
foreach ( $gen_data as $gen_row ) {
	if ( $gen_i >= 4 || (int) $gen_row['count'] <= 0 ) {
		break;
	}
	$gen_count      = (int) $gen_row['count'];
	$gen_named     += $gen_count;
	$gen_segments[] = array(
		'label' => $gen_row['name'],
		'count' => $gen_count,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_count / $gen_total ) * 100, 1 ) : 0,
		'class' => $gen_ramp[ $gen_i ],
	);
	++$gen_i;
}
$gen_other = max( 0, $gen_total - $gen_named );
if ( $gen_other > 0 ) {
	$gen_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $gen_other,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_other / $gen_total ) * 100, 1 ) : 0,
		'class' => 'mid2',
	);
}

$donut = array(
	'segments'    => $gen_segments,
	'center'      => $gen_cis,
	'center_sub'  => __( 'cisgender', 'lwtv' ),
	'eyebrow'     => __( 'Actor Gender Identity', 'lwtv' ),
	'headline'    => __( 'Nine in ten actors are cisgender', 'lwtv' ),
	'description' => __( 'Trans and non-binary actors remain a small share of the total — a figure worth watching as casting for trans and non-binary roles evolves.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
for v in sexuality gender; do
  echo "== $v =="
  curl -sk "https://lwtv.local/statistics/actors/$v/" | grep -o 'lwtv-donut-center-num[^>]*>[^<]*'
  curl -sk "https://lwtv.local/statistics/actors/$v/" | grep -oE 'lwtv-donut-seg--[a-z0-9]+' | sort | uniq -c
done
```
Expected: Sexuality centre = total (5,916), segments incl. `grey`(Straight) + ramp + `bordergrey`(Unknown ~34%); Gender centre = cisgender (5,468), `grey` + ramp + `mid2`(Other). Dash shares ~100. Verify in browser (light + dark): rings render, numbers count up.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/actors/sexuality.php plugins/lwtv-plugin/php/statistics/templates/actors/gender.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): actors sexuality and gender donuts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Remove old navbar + full verification

**Files:** delete `actors/navbar.php`.

- [ ] **Step 1: Confirm unreferenced, then delete**

```bash
grep -rn "actors/navbar" plugins/ page-templates/    # expect no matches
git rm plugins/lwtv-plugin/php/statistics/templates/actors/navbar.php
```

- [ ] **Step 2: Full lint + build**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```

- [ ] **Step 3: Full verification pass** at `https://lwtv.local/statistics/actors/` and each view, against `design_handoff_statistics_actors/screenshots/`:
  - Overview (3 cards, 2 callouts, 2 panels), Sexuality donut (grey straight + ramp + unknown), Gender donut (grey cis + ramp).
  - Each view: primary tab bar (Actors active) + sub-nav (correct active item); light + dark; reduced-motion; narrow layout; counts cross-check; no JS console errors.
  - Regression: `/statistics/`, `/statistics/shows/`, `/statistics/characters/` still render; one non-redesigned section (e.g. `/statistics/death/`) still renders.

  ```bash
  for v in "" sexuality gender; do
    code=$(curl -sk -o /tmp/a.html -w "%{http_code}" "https://lwtv.local/statistics/actors/$v/")
    err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/a.html)
    echo "$v -> HTTP $code, php-errors=$err"
  done
  ```
  Expect every view HTTP 200, php-errors=0.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore(stats): remove superseded actors navbar partial

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:** sub-nav + shell → T1; 3 cards + 2 callouts + 2 panels → T2; Sexuality + Gender donuts (incl. labeled Unknown) → T3; remove navbar → T4. Reuse mandate, family map (actors=yellow, sexuality=blue, gender=green via `characters` classes), tokens, i18n/escaping, divisor guards, animation contract, dark mode — enforced per task + Global Constraints. New SCSS limited to `.lwtv-panels--2` (T2) + `.lwtv-donut-seg--bordergrey` (T3). ✓

**Placeholder scan:** no TBD/TODO. Deferred: verify the five sprite ids render real `<use>` (T2/T4 curl checks); the callout "1 in N" copy is derived + i18n. Group-sum slugs (`cis-woman`/`cis-man`/`cisgender`, `heterosexual`, `unknown`/`undefined`) are explicit and verified live. ✓

**Type consistency:** `$donut` contract (segments[label,count,pct,class], center, center_sub, eyebrow, headline, description) matches `donut.php`; segment classes (grey/dkpink/pink/mid/mid2/ltpink/bordergrey) map to `.lwtv-donut-seg--*` (bordergrey added T3). `$actor_*` overview vars defined in `actors.php` (T2 Step 1) match `overview.php` usage. `data-count-to`/`data-grow-to` match the JS. Panels reuse `.lwtv-leaders`/`.lwtv-leader-*`/`.lwtv-tail-*` + `.lwtv-bars--{sexuality,characters}` (both exist). ✓

## Known follow-ups (out of scope)
- Callout/donut headline copy is derived/handoff-illustrative — owner may tune wording.
- With Actors migrated, only Nations/Stations/Death (+ individual actor blocks) remain before Chart.js can be dropped.
