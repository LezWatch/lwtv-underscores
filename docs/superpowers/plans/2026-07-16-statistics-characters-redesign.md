# Statistics on Characters Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the seven `/statistics/characters/` views into the shared stats shell (existing primary tab bar + a Characters sub-nav) with server-rendered visualisations — metric-card Overview + callouts + leader/tail panels, ranked bars, a Most-Clichés leaderboard, three donuts, and an area trendline — reusing the components and tokens built in the Overview and Shows rounds.

**Architecture:** Preserve the render path (`statistics.php` shell → `characters.php` `switch($view)` → per-view partial). Reuse `partials/{donut,trendline,ranked-bars,sparkline}.php`, the `.lwtv-metric-card` / `.lwtv-tropegap` / `.lwtv-panel`+leader/tail / `.lwtv-stats-subnav` classes, and the `$lwtv-stats-*` / `$ramp-*` tokens. The only genuinely new code is a **leaderboard mode** added to `ranked-bars.php` (for Most Clichés) and two small donut-segment colors. No Chart.js for these views.

**Tech Stack:** PHP 8.1+ (WordPress theme + plugin, `LWTV\` PSR-4), Bootstrap 5 utilities, SCSS (Dart Sass via `@wordpress/scripts`), inline SVG, the existing vanilla count-up JS, Symbolicons sprite. No PHPUnit harness — verification is PHPCS lint + SCSS build + `curl`/`wp eval` + browser checks.

## Global Constraints

- **Reuse mandate:** reuse existing components + tokens; NO hardcoded hex; NO handoff-README literal hex; do NOT revert the user's committed color/size tweaks.
- **Tokens:** `$lwtv-stats-{green,yellow,red,blue}[-background|-border]`, `$lwtv-stats-progressbar`, `$lwtv-pink`/`$lwtv-ltpink`/`$lwtv-dkpink`, `$lwtv-gold/silver/bronze`, `$lwtv-red/yellow`, `$lwtv-medgrey/dkgrey/bordergrey/ltgrey`. Donut ramp seg classes already defined: `.lwtv-donut-seg--{dkpink,pink,mid,ltpink,green,amber,red,grey,gold,silver,bronze,sev-med,sev-low}`. New color values only via SCSS `color.mix()`; `@use "sass:color";` is already at the top of `_stats.scss`.
- **SCSS access:** in `scss/addons/_stats.scss` use `colors.$lwtv-…`; in `scss/partials/_colors-dark.scss` use `colors.$lwtv-…` EXCEPT `$lwtv-medgrey` (locally overridden to `#404040` — bare). Match surrounding lines.
- **Family map:** Characters/Clichés → **green** (`.card-header.characters`); Sexual Orientations → **blue** (`.card-header.sexuality`); Gender → **yellow** (`.card-header.gender`); Dead → **red** (`.card-header.dead-characters`).
- **PHP:** 8.1+; WordPress-Extra PHPCS clean (`composer lint`/`composer lint-fix`). Auto-escaped funcs (`lwtv_plugin`, `get_symbolicon`) not wrapped in `esc_*`; every `get_symbolicon()` echo carries `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`.
- **i18n:** user-facing strings via `__()`/`esc_html__()`/`_n()` with `'lwtv'`; `number_format_i18n()` for numbers.
- **Animation contract (existing JS):** count-up = `data-count-to="<int>"` + visible final text; growable bars = `data-grow-to="<pct>"` + inline `style="width:0"`; donut RINGS static (no `data-grow-to`). 1100ms `easeOutCubic`, one driver, reduced-motion → finals.
- **Data integrity:** guard every divisor against zero; skip zero-count rows in ranked (share) lists; harden the single-key unwrap against empty results: `$data = ( is_array($raw) && ! empty($raw) ) ? (array) reset($raw) : array();`.
- **Build:** `npm run buildquick` needs **Node 24** (`.nvmrc`; Node 18 fails `crypto is not defined`) → `source ~/.nvm/nvm.sh; nvm use` first. Never edit `blocks/build/` or `inc/dist/`. Do NOT bump the theme version or touch CHANGELOG/package.json/functions.php.
- **Editor hazard:** a stylelint fix-on-save intermittently mangles `//` comments / drops lines in `scss/addons/_stats.scss`. After any SCSS edit + build, `git diff` the SCSS and confirm ONLY intended changes (no space-stripped jammed-comment garbage on new lines, no dropped rules). A pre-existing mangled comment near `_stats.scss:~205` is not yours.
- **Scope:** Characters section only. Other sections unchanged. The primary tab bar already renders (Characters active) — do not re-add it.

## Environment — how to run things (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`.
- **Site (Local):** `https://lwtv.local/statistics/characters/` (self-signed → `curl -sk`).
- **wp-cli** (homebrew can't reach Local's DB by default):
  ```
  php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>
  ```

## Data shapes (verified live)

`generate_characters_statistics('array', $type)` → single-key wrapper (unwrap with `reset()`):
- `gender` / `sexuality` / `cliches` → `['characters' => ['<slug>' => ['count','name','url'], … ]]` (slug-keyed, unsorted).
- `most-cliches` → `['characters' => [ <char_id> => ['name','count','url'], … ]]` (character-keyed, already ranked desc).
- `queer-irl` → `['queer_irl' => ['queer' => ['name','count','url'], 'not_queer' => [...]]]` — **only two buckets** (queer / not_queer), summing to total. No "unknown".
- `on-air` → `['on_air' => [ <year> => ['name'=>year,'count','url'], … ]]`.

Live sanity: total characters **7100**; growth series 13 yrs; dead cliché **622** (→ `1 in round(7100/622)=11`); cisgender **6180**; queer-irl queer **1629** / not_queer **5471**; gender terms 22, sexuality 12, clichés 41.

## Metric-card icons (verified present in sprite)

Characters `group.svg`, Sexual Orientations `heart.svg`, Gender Identities `venus-double.svg`, Clichés `tag.svg`. Verify real `<use href="…#id">` renders (not `<i>` fallback) after building.

## File structure

**New:** `characters/subnav.php`.
**Modified:** `characters.php`; `characters/{overview,cliches,most-cliches,gender,sexuality,queer-irl,on-air}.php`; `partials/ranked-bars.php` (leaderboard mode); `class-stats-enqueues.php`; `scss/addons/_stats.scss` (2 seg colors + rank style); `scss/partials/_colors-dark.scss` (if needed).
**Removed:** `characters/navbar.php`.

**Renderability invariant:** every task leaves `/statistics/characters/` and all views rendering. Views not yet rewritten keep their current Chart.js bodies until their task.

---

### Task 1: Characters shell — sub-nav, container, JS enqueue

**Files:** Create `characters/subnav.php`; modify `characters.php`, `class-stats-enqueues.php`.

- [ ] **Step 1: Create `characters/subnav.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the characters stats section.
 */

$lwtv_char_subnav = array(
	'overview'     => __( 'Overview', 'lwtv' ),
	'cliches'      => __( 'Clichés', 'lwtv' ),
	'most-cliches' => __( 'Most Clichés', 'lwtv' ),
	'gender'       => __( 'Gender', 'lwtv' ),
	'sexuality'    => __( 'Sexuality', 'lwtv' ),
	'queer-irl'    => __( 'Queer IRL', 'lwtv' ),
	'on-air'       => __( 'On Air', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Characters statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_char_subnav as $lwtv_slug => $lwtv_label ) {
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

- [ ] **Step 2: Rewrite the `characters.php` head to use the sub-nav + container**

In `plugins/lwtv-plugin/php/statistics/templates/characters.php`, replace:

```php
?>

<h2>
	<a href="/characters/">Total Characters</a> (<?php echo (int) $character_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'characters/navbar.php';
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
	include plugin_dir_path( __FILE__ ) . 'characters/subnav.php';
	?>
<?php
switch ( $view ) {
```

Then at the very end of `characters.php`, after the WP_DEBUG comment block, close the wrapper — add:

```php
?>
</div><!-- .lwtv-stats-overview -->
<?php
```

(Keep the `$valid_views`/`$view`/`$character_count` logic and the `overview`-only pre-compute untouched for now.)

- [ ] **Step 3: Enqueue the stats JS on Characters**

In `plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php`, broaden the overview-JS gate:

```php
		if ( in_array( $statistics, array( 'none', 'shows', 'characters' ), true ) ) {
```

(The line currently reads `array( 'none', 'shows' )`.)

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/characters/gender/ | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'   # -> Gender
curl -sk https://lwtv.local/statistics/characters/ | grep -c 'lwtv-stats-subnav\b'                                 # -> 1
curl -sk https://lwtv.local/statistics/characters/ | grep -o 'statistics-overview.js[^"]*'                          # -> present
```
Expected: sub-nav renders with correct active item; JS enqueued; old view bodies still render (no fatal).

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/subnav.php plugins/lwtv-plugin/php/statistics/templates/characters.php plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php style.css style.min.css
git commit -m "feat(stats): characters sub-nav, container, and animation enqueue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Overview — metric cards + callouts

**Files:** modify `characters.php` (overview data), `characters/overview.php` (rewrite), `scss/addons/_stats.scss` (only if a needed rule is missing — likely none).

**Interfaces produced:** the `$char_*` overview variables consumed by the panels in Task 3.

- [ ] **Step 1: Extend the overview data pre-compute in `characters.php`**

Inside the existing `if ( 'overview' === $view ) { … }` block, change the three `array_slice` lines to keep **10** rows each (5 leaders + 5 tail) and add the growth series, dead-cliché count, and queer-irl counts. Replace:

```php
	$top_genders     = array_slice( $character_gender_data, 0, 5, true );
	$top_sexualities = array_slice( $character_sexuality_data, 0, 5, true );
	$top_cliches     = array_slice( $character_cliches_data, 0, 14, true );

	// Get total counts efficiently
	$count_genders     = count( $character_gender_data );
	$count_sexualities = count( $character_sexuality_data );
	$count_cliches     = count( $character_cliches_data );
```

with:

```php
	$top_genders     = array_slice( $character_gender_data, 0, 10, true );
	$top_sexualities = array_slice( $character_sexuality_data, 0, 10, true );
	$top_cliches     = array_slice( $character_cliches_data, 0, 10, true );

	// Get total counts efficiently
	$count_genders     = count( $character_gender_data );
	$count_sexualities = count( $character_sexuality_data );
	$count_cliches     = count( $character_cliches_data );

	// Redesign overview extras.
	$char_growth = lwtv_plugin()->generate_growth_series( 'characters' );
	$char_dead   = isset( $character_cliches_data['dead'] ) ? (int) $character_cliches_data['dead']['count'] : 0;

	$char_queer_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'queer-irl' );
	$char_queer_data  = ( is_array( $char_queer_raw ) && ! empty( $char_queer_raw ) ) ? (array) reset( $char_queer_raw ) : array();
	$char_queer_yes   = isset( $char_queer_data['queer'] ) ? (int) $char_queer_data['queer']['count'] : 0;
	$char_queer_no    = isset( $char_queer_data['not_queer'] ) ? (int) $char_queer_data['not_queer']['count'] : 0;
```

- [ ] **Step 2: Rewrite `characters/overview.php` — cards + callouts (panels added in Task 3)**

Replace the entire contents of `characters/overview.php` with the following. (Task 3 appends the panels block after the callouts.)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters overview: metric cards + callouts + top panels.
 *
 * @package LezWatch.TV
 *
 * @var int   $character_count
 * @var int   $count_sexualities
 * @var int   $count_genders
 * @var int   $count_cliches
 * @var array $top_cliches
 * @var array $top_sexualities
 * @var array $top_genders
 * @var array $char_growth
 * @var int   $char_dead
 * @var int   $char_queer_yes
 * @var int   $char_queer_no
 * @var string $baseurl
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

// Representative decorative sparkline for term-count cards (no real time series).
$char_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$char_cards = array(
	array(
		'type'    => 'characters',
		'label'   => __( 'Characters', 'lwtv' ),
		'count'   => (int) $character_count,
		'caption' => __( 'Queer & trans, all time', 'lwtv' ),
		'svg'     => 'group.svg',
		'icon'    => 'svg-users',
		'points'  => lwtv_stats_sparkline_points( $char_growth ),
	),
	array(
		'type'    => 'sexuality',
		'label'   => __( 'Sexual Orientations', 'lwtv' ),
		'count'   => (int) $count_sexualities,
		'caption' => __( 'Distinct orientations tracked', 'lwtv' ),
		'svg'     => 'heart.svg',
		'icon'    => 'svg-heart',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
	array(
		'type'    => 'gender',
		'label'   => __( 'Gender Identities', 'lwtv' ),
		'count'   => (int) $count_genders,
		'caption' => __( 'Distinct identities tracked', 'lwtv' ),
		'svg'     => 'venus-double.svg',
		'icon'    => 'svg-venus-double',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
	array(
		'type'    => 'dead-characters',
		'label'   => __( 'Clichés', 'lwtv' ),
		'count'   => (int) $count_cliches,
		'caption' => __( 'Recurring character tropes', 'lwtv' ),
		'svg'     => 'tag.svg',
		'icon'    => 'svg-tag',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Characters at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid">
	<?php
	foreach ( $char_cards as $char_card ) {
		// Icon-tile background class uses the type modifier; the .dead-characters
		// family maps to the "dead" icon-tile modifier.
		$char_icon_mod = ( 'dead-characters' === $char_card['type'] ) ? 'dead' : $char_card['type'];
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $char_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $char_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $char_icon_mod ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $char_card['svg'], icon: $char_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $char_card['count']; ?>"><?php echo esc_html( number_format_i18n( $char_card['count'] ) ); ?></span>
			<?php if ( '' !== $char_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polygon class="lwtv-sparkline-area" points="<?php echo esc_attr( $char_card['points'] . ' 120,26 0,26' ); ?>" fill="currentColor" fill-opacity="0.15" stroke="none" />
					<polyline points="<?php echo esc_attr( $char_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $char_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$char_dead_ratio = ( $char_dead > 0 ) ? (int) round( $character_count / $char_dead ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Stories We Keep Telling', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_dead; ?>"><?php echo esc_html( number_format_i18n( $char_dead ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of dead characters. */
				esc_html__( 'characters carry the Dead cliché — that\'s roughly one in %d.', 'lwtv' ),
				(int) $char_dead_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/cliche/dead/' ) ); ?>"><?php esc_html_e( 'See these characters', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Played by Queer Actors', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'user-heart.svg', icon: 'svg-user', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_queer_yes; ?>"><?php echo esc_html( number_format_i18n( $char_queer_yes ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: 1: queer-actor count, 2: non-queer count. */
				esc_html__( 'characters are played by queer actors; %2$s by straight or cis actors.', 'lwtv' ),
				esc_html( number_format_i18n( $char_queer_yes ) ),
				esc_html( number_format_i18n( $char_queer_no ) )
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'queer-irl/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>
```

> The `%1$s` placeholder in the "Played by Queer Actors" description is intentionally unused in the visible sentence (the big number already shows it); it keeps the translator context of both figures. If PHPCS objects to an unused arg, drop `%1$s`/arg 1 and renumber to a single `%s` for the non-queer count.

- [ ] **Step 2b: SCSS check**

The metric grid, `.lwtv-metric-icon.{characters,gender,dead}` tiles, `.lwtv-tropegap*`, and `.lwtv-pullstats` all already exist. `.lwtv-metric-icon.sexuality` does **not** exist (only shows/actors/characters/dead were defined for the Shows/Overview cards). Add it (blue tile) so the Sexual Orientations card icon tile matches its family — in `scss/addons/_stats.scss`, next to the other `.lwtv-metric-icon.*` rules:

```scss
	.lwtv-metric-icon.sexuality {
		background-color: colors.$lwtv-stats-blue-background;
	}
```

Also confirm the eyebrow/number/sparkline pick up the family color: the card carries `.card-header.sexuality` (blue) and `.card-header.gender` (yellow) — both already defined in `_stats.scss`/`_colors-dark.scss`, so the eyebrow (`color: inherit`) and sparkline (`currentColor`) inherit correctly. No other SCSS needed.

- [ ] **Step 3: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/characters/ | grep -oE '#group|#heart|#venus-double|#tag' | sort -u
curl -sk https://lwtv.local/statistics/characters/ | grep -c 'lwtv-metric-card'   # -> 4
curl -sk https://lwtv.local/statistics/characters/ | grep -c 'lwtv-tropegap'      # -> 2
curl -sk https://lwtv.local/statistics/characters/ | grep -o 'data-count-to="622"'  # dead callout
```
Expected: 4 cards (green/blue/yellow/red families) with real sprite icons; 2 callouts (dead 622 "one in 11"; queer 1,629). No PHP notice/fatal.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters.php plugins/lwtv-plugin/php/statistics/templates/characters/overview.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): characters overview cards and callouts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Overview — three leader/tail panels

**Files:** modify `characters/overview.php` (append the panels block).

- [ ] **Step 1: Append the panels block** at the end of `characters/overview.php` (after the `.lwtv-pullstats` closing `</div>` from Task 2):

```php
<?php
$char_panels = array(
	array(
		'title'  => __( 'Top Clichés', 'lwtv' ),
		'family' => 'characters',
		'svg'    => 'tag.svg',
		'icon'   => 'svg-tag',
		'rows'   => $top_cliches,
		'base'   => '/cliche/',
		'count'  => (int) $count_cliches,
		/* translators: %s: total clichés. */
		'sub'    => sprintf( __( '%s clichés tracked', 'lwtv' ), number_format_i18n( (int) $count_cliches ) ),
		/* translators: %s: total clichés. */
		'all'    => sprintf( __( 'View all %s clichés →', 'lwtv' ), number_format_i18n( (int) $count_cliches ) ),
		'more'   => $baseurl . 'cliches/',
	),
	array(
		'title'  => __( 'Top Sexual Orientations', 'lwtv' ),
		'family' => 'sexuality',
		'svg'    => 'heart.svg',
		'icon'   => 'svg-heart',
		'rows'   => $top_sexualities,
		'base'   => '/sexuality/',
		'count'  => (int) $count_sexualities,
		/* translators: %s: total orientations. */
		'sub'    => sprintf( __( '%s orientations tracked', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		/* translators: %s: total orientations. */
		'all'    => sprintf( __( 'View all %s orientations →', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		'more'   => $baseurl . 'sexuality/',
	),
	array(
		'title'  => __( 'Top Gender Identities', 'lwtv' ),
		'family' => 'gender',
		'svg'    => 'venus-double.svg',
		'icon'   => 'svg-venus-double',
		'rows'   => $top_genders,
		'base'   => '/gender/',
		'count'  => (int) $count_genders,
		/* translators: %s: total identities. */
		'sub'    => sprintf( __( '%s identities tracked', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		/* translators: %s: total identities. */
		'all'    => sprintf( __( 'View all %s identities →', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		'more'   => $baseurl . 'gender/',
	),
);
?>
<div class="lwtv-panels lwtv-panels--3">
	<?php
	foreach ( $char_panels as $char_panel ) {
		$char_rows    = is_array( $char_panel['rows'] ) ? $char_panel['rows'] : array();
		$char_leaders = array_slice( $char_rows, 0, 5, true );
		$char_tail    = array_slice( $char_rows, 5, 5, true );
		?>
		<section class="lwtv-panel bg-light">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon <?php echo esc_attr( $char_panel['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $char_panel['svg'], icon: $char_panel['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php echo esc_html( $char_panel['title'] ); ?></h2>
					<p class="lwtv-panel-sub"><?php echo esc_html( $char_panel['sub'] ); ?></p>
				</div>
			</header>
			<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $char_panel['family'] ); ?>">
				<?php
				foreach ( $char_leaders as $char_slug => $char_row ) {
					$char_row_count = (int) $char_row['count'];
					$char_pct       = ( $character_count > 0 ) ? round( ( $char_row_count / $character_count ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-leader-row">
						<div class="lwtv-leader-head">
							<a class="lwtv-leader-name" href="<?php echo esc_url( site_url( $char_panel['base'] . $char_slug ) ); ?>"><?php echo esc_html( $char_row['name'] ); ?></a>
							<span class="lwtv-leader-value"><?php echo esc_html( number_format_i18n( $char_row_count ) . ' · ' . $char_pct . '%' ); ?></span>
						</div>
						<div class="progress lwtv-leader-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $char_pct ); ?>" aria-valuenow="<?php echo esc_attr( (string) $char_row_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $character_count ); ?>"></div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
			<?php if ( ! empty( $char_tail ) ) : ?>
				<ul class="lwtv-tail">
					<?php
					foreach ( $char_tail as $char_slug => $char_row ) {
						?>
						<li class="lwtv-tail-row">
							<a class="lwtv-tail-name" href="<?php echo esc_url( site_url( $char_panel['base'] . $char_slug ) ); ?>"><?php echo esc_html( $char_row['name'] ); ?></a>
							<span class="lwtv-tail-count"><?php echo esc_html( number_format_i18n( (int) $char_row['count'] ) ); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			<?php endif; ?>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $char_panel['more'] ); ?>"><?php echo esc_html( $char_panel['all'] ); ?></a>
		</section>
		<?php
	}
	?>
</div>
```

- [ ] **Step 2: Add the 3-up panel grid + sexuality/gender family fills (SCSS)**

The existing `.lwtv-panels` is `1.5fr 1fr` (two-column, from the site Overview). Add a **3-up** modifier and the sexuality/gender leader fills + tracks (only `characters`/`actors`/`shows` families were defined before). In `scss/addons/_stats.scss` under `.statistics`:

```scss
	.lwtv-panels--3 {
		grid-template-columns: repeat(3, 1fr);

		@media (max-width: 991px) {
			grid-template-columns: 1fr;
		}
	}

	.lwtv-bars--sexuality .progress-bar {
		background-color: colors.$lwtv-stats-blue !important;
	}

	.lwtv-bars--gender .progress-bar {
		background-color: colors.$lwtv-stats-yellow !important;
	}

	.lwtv-bars--sexuality .lwtv-leader-track {
		background-color: colors.$lwtv-stats-blue-background;
	}

	.lwtv-bars--gender .lwtv-leader-track {
		background-color: colors.$lwtv-stats-yellow-background;
	}

	.lwtv-panel-icon.sexuality {
		color: colors.$lwtv-stats-blue;
	}

	.lwtv-panel-icon.gender {
		color: colors.$lwtv-stats-yellow;
	}
```

- [ ] **Step 3: Dark SCSS** — in `scss/partials/_colors-dark.scss` `.statistics` block, extend the existing dark leader-fill / leader-track / panel-icon rules to include the new families:

```scss
		.lwtv-bars--sexuality .progress-bar,
		.lwtv-bars--gender .progress-bar {
			background-color: colors.$lwtv-ltpink !important;
		}

		.lwtv-bars--sexuality .lwtv-leader-track,
		.lwtv-bars--gender .lwtv-leader-track {
			background-color: $lwtv-medgrey;
		}

		.lwtv-panel-icon.sexuality {
			color: #3498db;
		}

		.lwtv-panel-icon.gender {
			color: #f1c40f;
		}
```

(The dark hex `#3498db`/`#f1c40f` mirror the existing dark `.card-header.shows`/`.card-header.gender` family colors already in this file — not new palette values.)

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/characters/ | grep -c 'lwtv-panel\b'    # -> 3
curl -sk https://lwtv.local/statistics/characters/ | grep -oE 'lwtv-bars--(characters|sexuality|gender)' | sort -u
```
Expected: 3 panels (green/blue/yellow), each with leader bars + tail rows + footer link; Queer IRL appears as the #1 cliché.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/overview.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(stats): characters overview top-clichés/orientations/genders panels

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Clichés view — ranked bars (green)

**Files:** modify `characters/cliches.php`.

- [ ] **Step 1: Rewrite `characters/cliches.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Clichés: ranked bars (green).
 *
 * @package LezWatch.TV
 */

$cliches_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'cliches' );
$cliches_data = ( is_array( $cliches_raw ) && ! empty( $cliches_raw ) ) ? (array) reset( $cliches_raw ) : array();
$ranked       = array(
	'rows'   => $cliches_data,
	'total'  => (int) $character_count,
	'family' => 'characters',
	'svg'    => 'tag.svg',
	'icon'   => 'svg-tag',
	'title'  => __( 'All Clichés, Ranked', 'lwtv' ),
	/* translators: %s: number of clichés. */
	'sub'    => sprintf( __( '%s clichés, by number of characters. A character can carry several, so shares add up past 100%%.', 'lwtv' ), number_format_i18n( count( $cliches_data ) ) ),
	'base'   => '/cliche/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```

- [ ] **Step 2: Lint + verify**

```bash
composer lint-fix && composer lint
curl -sk https://lwtv.local/statistics/characters/cliches/ | grep -c 'lwtv-leader-row'   # many
curl -sk https://lwtv.local/statistics/characters/cliches/ | grep -o 'lwtv-panel-title">[^<]*'
```
Expected: ranked green leader bars, descending; header "All Clichés, Ranked"; Queer IRL leads, Dead second. No fatal.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/cliches.php
git commit -m "feat(stats): characters clichés ranked-bar view

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Most Clichés — leaderboard mode

**Files:** modify `partials/ranked-bars.php` (add leaderboard mode), `characters/most-cliches.php` (rewrite), `scss/addons/_stats.scss` (rank style).

**Interfaces:** `$ranked['mode']` = `'share'` (default) | `'leaderboard'`. In leaderboard mode rows are individual items ranked by `count`; bar width is relative to the top count; label is the raw count (no pct); a rank index is shown; links use each row's `url`.

- [ ] **Step 1: Add leaderboard mode to `partials/ranked-bars.php`**

Update the docblock to add `@type string $mode  'share' (default) | 'leaderboard'.`, then change the compute + loop. Replace:

```php
$ranked_rows = $ranked['rows'] ?? array();
uasort( $ranked_rows, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$ranked_total = (int) ( $ranked['total'] ?? 0 );
```

with:

```php
$ranked_rows  = $ranked['rows'] ?? array();
uasort( $ranked_rows, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$ranked_total = (int) ( $ranked['total'] ?? 0 );
$ranked_mode  = ( isset( $ranked['mode'] ) && 'leaderboard' === $ranked['mode'] ) ? 'leaderboard' : 'share';
$ranked_top   = ! empty( $ranked_rows ) ? max( array_map( fn( $r ) => (int) $r['count'], $ranked_rows ) ) : 0;
$ranked_rank  = 0;
```

Then replace the loop body (from `$ranked_count = (int) $ranked_row['count'];` through the closing `</div>` of `.lwtv-leader-row`) with:

```php
			$ranked_count = (int) $ranked_row['count'];
			// Share mode skips empty terms; leaderboard keeps every ranked row.
			if ( 'share' === $ranked_mode && $ranked_count <= 0 ) {
				continue;
			}
			++$ranked_rank;
			if ( 'leaderboard' === $ranked_mode ) {
				// Bar relative to the top count; label is the raw count.
				$ranked_width = ( $ranked_top > 0 ) ? round( ( $ranked_count / $ranked_top ) * 100, 1 ) : 0;
				$ranked_label = number_format_i18n( $ranked_count );
			} else {
				// Bar is the true share of the total; label is count · pct%.
				$ranked_width = ( $ranked_total > 0 ) ? round( ( $ranked_count / $ranked_total ) * 100, 1 ) : 0;
				$ranked_label = number_format_i18n( $ranked_count ) . ' · ' . $ranked_width . '%';
			}
			$ranked_href = ( ! empty( $ranked['base'] ) ) ? site_url( $ranked['base'] . $ranked_slug ) : ( $ranked_row['url'] ?? '#' );
			?>
			<div class="lwtv-leader-row">
				<div class="lwtv-leader-head">
					<span class="lwtv-leader-name">
						<?php if ( 'leaderboard' === $ranked_mode ) : ?>
							<span class="lwtv-leader-rank"><?php echo esc_html( number_format_i18n( $ranked_rank ) ); ?></span>
						<?php endif; ?>
						<a href="<?php echo esc_url( $ranked_href ); ?>"><?php echo esc_html( $ranked_row['name'] ); ?></a>
					</span>
					<span class="lwtv-leader-value"><?php echo esc_html( $ranked_label ); ?></span>
				</div>
				<div class="progress lwtv-leader-track">
					<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $ranked_width ); ?>" aria-valuenow="<?php echo esc_attr( (string) $ranked_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) ( 'leaderboard' === $ranked_mode ? $ranked_top : $ranked_total ) ); ?>"></div>
				</div>
			</div>
			<?php
```

> Note: the share-mode `$ranked_width` is now the pct (same as before), so the label reuses it — behavior for the existing Shows/Clichés share views is unchanged (true-share bar + `count · pct%`). Verify the Shows tropes/genres pages still render identically after this edit.

- [ ] **Step 2: Rewrite `characters/most-cliches.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Most Clichés: leaderboard of individual characters.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;

$cliche_leaders = ( new Build_Cliche_Leaders() )->generate();
$leader_rows    = is_array( $cliche_leaders ) ? $cliche_leaders : array();

$ranked = array(
	'rows'   => $leader_rows,
	'total'  => (int) $character_count,
	'family' => 'characters',
	'mode'   => 'leaderboard',
	'svg'    => 'medal.svg',
	'icon'   => 'svg-trophy',
	'title'  => __( 'Most Clichés', 'lwtv' ),
	/* translators: %s: number of characters shown. */
	'sub'    => sprintf( __( '%s characters carrying the most distinct clichés', 'lwtv' ), number_format_i18n( count( $leader_rows ) ) ),
	'base'   => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
```

> `Build_Cliche_Leaders()->generate()` returns an ordered list of `['name','count','url']` (character page URL). `base` is `''` so `ranked-bars.php` links via each row's `url`. If `medal.svg`/`trophy` is not in the sprite, use `tag.svg`/`svg-tag` (confirm during implementation; a `<i>` fallback means the id is wrong).

- [ ] **Step 3: Rank SCSS** — in `scss/addons/_stats.scss` under `.statistics`, add:

```scss
	.lwtv-leader-name {
		display: inline-flex;
		align-items: baseline;
		gap: 8px;
	}

	.lwtv-leader-rank {
		min-width: 1.5em;
		font-variant-numeric: tabular-nums;
		font-weight: 700;
		color: colors.$lwtv-medgrey;
	}
```

> `.lwtv-leader-name` currently has only `font-size`/`font-weight`; adding `display:inline-flex` + gap lets the rank sit before the link without affecting share-mode rows (which have no rank child).

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/characters/most-cliches/ | grep -c 'lwtv-leader-rank'   # e.g. 25
curl -sk https://lwtv.local/statistics/characters/most-cliches/ | grep -o 'lwtv-leader-rank">[0-9]*<' | head -3
# regression: shows share view unchanged
curl -sk https://lwtv.local/statistics/shows/tropes/ | grep -oE 'data-grow-to="[0-9.]+"' | head -1   # -> 21
```
Expected: leaderboard with rank numbers 1,2,3…, character-name links, count labels (no pct), bars relative to top; the Shows tropes share view still shows true-share bars (21%). No fatal.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/ranked-bars.php plugins/lwtv-plugin/php/statistics/templates/characters/most-cliches.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): most-clichés leaderboard (ranked-bars leaderboard mode)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Donut views — Gender, Sexuality, Queer IRL

**Files:** modify `characters/{gender,sexuality,queer-irl}.php`; `scss/addons/_stats.scss` (one new `--mid2` seg color for a 5-stop sexuality ramp).

- [ ] **Step 1: Add a 5th raspberry donut-segment color** in `scss/addons/_stats.scss`, next to the other `.lwtv-donut-seg--*` rules (both the stroke set and the legend-mini-bar set):

```scss
	.lwtv-donut-seg--mid2 { stroke: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 25%); background-color: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 25%); }
```

And in the legend mini-bar override group (`.lwtv-donut-legend-track .progress-bar { … }`):

```scss
		&.lwtv-donut-seg--mid2 { background-color: color.mix(colors.$lwtv-pink, colors.$lwtv-ltpink, 25%) !important; }
```

- [ ] **Step 2: Rewrite `characters/sexuality.php`** (raspberry ramp + grey "Other")

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Sexuality: donut (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$sex_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $character_count;

uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$sex_ramp     = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );
$sex_segments = array();
$sex_named    = 0;
$sex_i        = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 5 || (int) $sex_row['count'] <= 0 ) {
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
		'class' => 'grey',
	);
}

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'characters', 'lwtv' ),
	'eyebrow'     => __( 'Sexual Orientation', 'lwtv' ),
	'headline'    => __( 'Two in three are lesbian or bisexual', 'lwtv' ),
	'description' => __( 'Lesbian and bisexual characters make up the bulk of the catalogue. The long tail — pansexual, asexual, demisexual and more — is where the fastest growth is happening.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 3: Rewrite `characters/gender.php`** (grey cisgender + raspberry-ramp minorities)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Gender: donut (grey cisgender + raspberry-ramp minorities).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$gen_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $character_count;

$gen_cis      = isset( $gen_data['cisgender'] ) ? (int) $gen_data['cisgender']['count'] : 0;
$gen_cis_name = isset( $gen_data['cisgender'] ) ? $gen_data['cisgender']['name'] : __( 'Cisgender', 'lwtv' );
unset( $gen_data['cisgender'] );

$gen_segments = array(
	array(
		'label' => $gen_cis_name,
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);

uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$gen_ramp  = array( 'dkpink', 'pink', 'mid', 'ltpink' );
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
	'eyebrow'     => __( 'Gender Identity', 'lwtv' ),
	'headline'    => __( 'Most characters are cisgender — but not all', 'lwtv' ),
	'description' => __( 'Cisgender characters dominate, but the database tracks a growing range of trans, non-binary and genderqueer identities.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 4: Rewrite `characters/queer-irl.php`** (2-segment: pink / grey)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Queer IRL: donut (played-by-queer vs. not).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$qirl_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'queer-irl' );
$qirl_data = ( is_array( $qirl_raw ) && ! empty( $qirl_raw ) ) ? (array) reset( $qirl_raw ) : array();

$qirl_yes = isset( $qirl_data['queer'] ) ? (int) $qirl_data['queer']['count'] : 0;
$qirl_no  = isset( $qirl_data['not_queer'] ) ? (int) $qirl_data['not_queer']['count'] : 0;
$qirl_tot = $qirl_yes + $qirl_no;

$donut = array(
	'segments'    => array(
		array(
			'label' => __( 'Played by queer actors', 'lwtv' ),
			'count' => $qirl_yes,
			'pct'   => ( $qirl_tot > 0 ) ? round( ( $qirl_yes / $qirl_tot ) * 100, 1 ) : 0,
			'class' => 'pink',
		),
		array(
			'label' => __( 'Straight or cis actors', 'lwtv' ),
			'count' => $qirl_no,
			'pct'   => ( $qirl_tot > 0 ) ? round( ( $qirl_no / $qirl_tot ) * 100, 1 ) : 0,
			'class' => 'grey',
		),
	),
	'center'      => $qirl_yes,
	'center_sub'  => __( 'queer actors', 'lwtv' ),
	'eyebrow'     => __( 'Queer IRL', 'lwtv' ),
	'headline'    => __( 'Fewer than a third are played by queer actors', 'lwtv' ),
	'description' => __( 'Most queer and trans characters are still played by straight or cisgender actors.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
```

- [ ] **Step 5: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
for v in sexuality gender queer-irl; do
  echo "== $v =="
  curl -sk "https://lwtv.local/statistics/characters/$v/" | grep -o 'lwtv-donut-center-num[^>]*>[^<]*'
  curl -sk "https://lwtv.local/statistics/characters/$v/" | grep -oE 'lwtv-donut-seg--[a-z0-9]+' | sort | uniq -c
done
```
Expected: Sexuality centre = total (7,100), ramp+grey segments; Gender centre = cisgender (6,180), grey+ramp; Queer IRL centre = 1,629, pink+grey. Dash shares ~100. Verify in browser (light + dark) — rings render, numbers count up, "played by queer" arc stays pink in dark.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/gender.php plugins/lwtv-plugin/php/statistics/templates/characters/sexuality.php plugins/lwtv-plugin/php/statistics/templates/characters/queer-irl.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): characters gender/sexuality/queer-irl donuts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: On Air trendline

**Files:** modify `characters/on-air.php`.

- [ ] **Step 1: Rewrite `characters/on-air.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → On Air: area trendline of characters-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
$onair_data = ( is_array( $onair_raw ) && ! empty( $onair_raw ) ) ? (array) reset( $onair_raw ) : array();

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
	'eyebrow'      => __( 'Characters On Air per Year', 'lwtv' ),
	'headline'     => __( 'More queer characters on screen than ever', 'lwtv' ),
	'description'  => __( 'The number of queer and trans characters on air each year climbed steadily for two decades before the recent contraction in scripted TV.', 'lwtv' ),
	'current'      => (int) $onair_last['count'],
	'current_year' => (int) $onair_last['year'],
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/trendline.php';
```

- [ ] **Step 2: Lint + verify**

```bash
composer lint-fix && composer lint
curl -sk https://lwtv.local/statistics/characters/on-air/ | grep -o 'lwtv-trend-current-num[^>]*>[^<]*'
curl -sk https://lwtv.local/statistics/characters/on-air/ | grep -oc 'class="lwtv-trend-line"'
```
Expected: pink area trendline with many points, peak dot, right-aligned current-year figure. No fatal.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/on-air.php
git commit -m "feat(stats): characters on-air area trendline

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Remove old navbar + full verification

**Files:** delete `characters/navbar.php`.

- [ ] **Step 1: Confirm unreferenced, then delete**

```bash
grep -rn "characters/navbar" plugins/ page-templates/    # expect no matches
git rm plugins/lwtv-plugin/php/statistics/templates/characters/navbar.php
```

- [ ] **Step 2: Full lint + build**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```

- [ ] **Step 3: Full verification pass** at `https://lwtv.local/statistics/characters/` and each view, against `design_handoff_statistics_characters/screenshots/`:
  - Overview (4 cards, 2 callouts, 3 panels), Clichés (green ranked), Most Clichés (leaderboard), Gender (grey+ramp donut), Sexuality (ramp donut), Queer IRL (pink/grey donut), On Air (pink trendline).
  - Every view: primary tab bar (Characters active) + sub-nav (correct active item); light + dark; reduced-motion; narrow layout; counts cross-check; no JS console errors.
  - Regression: `/statistics/` , `/statistics/shows/` (incl. tropes/genres share bars), and one non-redesigned section still render.

  ```bash
  for v in "" cliches most-cliches gender sexuality queer-irl on-air; do
    code=$(curl -sk -o /tmp/c.html -w "%{http_code}" "https://lwtv.local/statistics/characters/$v/")
    err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/c.html)
    echo "$v -> HTTP $code, php-errors=$err"
  done
  ```
  Expect every view HTTP 200, php-errors=0.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore(stats): remove superseded characters navbar partial

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:** sub-nav + shell → T1; 4 cards + 2 callouts → T2; 3 leader/tail panels → T3; Clichés ranked → T4; Most Clichés leaderboard + ranked-bars mode → T5; Gender/Sexuality/Queer-IRL donuts → T6; On Air trendline → T7; remove navbar → T8. Reuse mandate, families, tokens, i18n/escaping, divisor guards, animation contract, dark mode — enforced per task + Global Constraints. ✓

**Placeholder scan:** no TBD/TODO. Deferred confirmations are explicit verify-then-code steps: metric-card + most-clichés icon ids (T2/T5 verify real `<use>` not `<i>`), and each data shape (already verified live and embedded). The `%1$s` unused-arg note and the `medal.svg` fallback note give concrete fallbacks. ✓

**Type consistency:** `$ranked` contract (rows, total, family, title, sub, svg, icon, base, mode) — T5 extends it with `mode`; T4/T3-panels use share mode (no `mode` key → defaults to share). `$donut` contract (segments[label,count,pct,class], center, center_sub, eyebrow, headline, description) — T6 matches donut.php exactly; segment `class` values (dkpink/pink/mid/mid2/ltpink/grey) map to `.lwtv-donut-seg--*` (mid2 added in T6). `$trend` contract matches trendline.php. `data-count-to`/`data-grow-to` match the JS. `$char_*` overview vars defined in `characters.php` (T2) match `overview.php` usage (T2/T3). ✓

## Known follow-ups (out of scope)
- Chart.js can be deprecated only after Actors/Nations/Stations/Death (+ individual actor blocks) also migrate.
- The `medal`/leaderboard icon and the exact callout copy are easy content tweaks for the site owner.
