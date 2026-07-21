# This Year Redesign + Chart.js Removal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-skin `/this-year/` to match the redesigned statistics sections (shared shell, server-rendered HTML, theme tokens), then remove Chart.js from the codebase entirely.

**Architecture:** Edit the plugin's `This_Year\Display` + its `templates/*.php`; add `.lwtv-ty-*` SCSS to `scss/addons/_stats.scss` (+ dark overrides in `scss/partials/_colors-dark.scss`); reuse the existing stats shell partials and the `data-count-to` count-up. No data-layer query changes. Chart.js removal is the final phase (it is already functionally dead — see spec).

**Tech Stack:** PHP 8.1+ (WordPress-Extra phpcs), Bootstrap 5, Dart Sass via `@wordpress/scripts`, Symbolicons sprite, vanilla count-up (`statistics-overview.js`).

**Spec:** `docs/superpowers/specs/2026-07-20-statistics-thisyear-design.md`
**Design reference (exact markup/layout):** `~/Downloads/LWTV-This-Year.zip` → `design_handoff_statistics_thisyear/Statistics This Year.dc.html` + `screenshots/`.

## Global Constraints

- **Year floor** `LWTV_FIRST_YEAR` (1961, `plugins/index.php`); ceiling = `gmdate('Y')`. Navigator reaches 1961 (NOT the handoff's 2016).
- **No hardcoded hex** except where a redesigned section already established a sanctioned bespoke palette; reuse `scss/partials/_colors.scss` tokens (`$lwtv-stats-green/red/blue/yellow`, `$lwtv-dkpink`, `$lwtv-pink`, `$lwtv-purple`, greys, `$lwtv-bordergrey`). 5-counter split: Characters=green, Dead=red, Shows=blue, New=pink/`$lwtv-dkpink`, Canceled=amber/`$lwtv-stats-yellow`.
- **i18n:** all user strings via `__()/_e()/_n()` with the `'lwtv'` text domain. Years printed as bare `(string)` — never `number_format_i18n()` (avoids "2,026").
- **Escaping:** `esc_html`/`esc_url`/`esc_attr`; `get_symbolicon` output is auto-escaped (do NOT wrap) — use the `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` inline comment as existing templates do.
- **By-X grouping** stays client-side via **Bootstrap nav-pills + tab-panes** (Bootstrap JS is loaded site-wide; the current templates already use it). No new toggle JS. VIEW switching stays on the existing `/this-year/{year}/{view}/` URLs.
- **Count-up:** ribbon + header counts use `data-count-to="<int>"`; `statistics-overview.js` must be enqueued on This Year. Count-up FREEZES on the automation tab — verify counts via the `data-count-to` attribute, not screenshot numbers.
- **Build:** `npm run buildquick` fails on Node 18 unless run as `NODE_OPTIONS=--experimental-global-webcrypto npm run buildquick`. **Lint:** `composer lint`.
- **Show/character links:** use canonical permalinks. Characters → `/character/{slug}/` (singular, per `get_post_type_archive`/existing usage); shows → the canonical `/show/{slug}/`. Normalize the `/show/` vs `/shows/` inconsistency to the canonical singular form the CPT uses; verify one real link resolves (no 301).
- **Verification per task:** `composer lint` clean; `NODE_OPTIONS=--experimental-global-webcrypto npm run buildquick` clean (only when SCSS changed); load the named URL(s) on `https://lwtv.local` and confirm layout + real data (via `data-count-to` for animated numbers). Site is Local; wp-cli needs a socket override, so verify via the browser, not wp-cli.

---

## Task 1: Shell & navigation (tab bar + year navigator + sub-nav + count-up)

Delivers the redesigned page chrome on every view: the shared stats tab bar (This Year active), the year navigator, and the This Year sub-nav — plus the count-up enqueue. View bodies stay as-is (old templates) until later tasks; that is fine and independently verifiable.

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php` (add This-Year active-state)
- Modify: `plugins/lwtv-plugin/php/this-year/class-display.php` (restructure output order; compute `$ty_baseurl`, floor/ceiling; pass `$statstype`)
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/navigation.php` (→ include tab bar + render sub-nav)
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/navigation-year.php` (→ year navigator)
- Modify: `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php` (enqueue `statistics-overview.js` on this-year)
- Modify: `scss/addons/_stats.scss` (year navigator styles; sub-nav already styled by `.lwtv-stats-subnav`)

**Interfaces (produced for later tasks):**
- In-scope template vars from `Display::make()`: `$this_year` (int), `$view` (string slug, one of overview/characters-on-air/dead-characters/shows-on-air/new-shows/canceled-shows), `$ty_baseurl` (string, `'/this-year/'` for current year else `'/this-year/{year}/'`), plus the existing count/collection vars.
- Sub-nav + view templates read `$view` and `$ty_baseurl`.

- [ ] **Step 1: Add This-Year active-state to the shared tab bar**

In `main/tabbar.php`, the active-state `switch ( $statstype ?? 'main' )` currently handles shows/characters/actors/nations/stations/death. Add a `this-year` case:

```php
	case 'this-year':
		$lwtv_stats_active = home_url( '/this-year/' );
		break;
```

(Place it inside the existing `switch`, before `}`.) This makes `include tabbar.php` with `$statstype = 'this-year'` mark the This Year tab active.

- [ ] **Step 2: Restructure `Display::make()` output order + compute nav vars**

Replace the render block (current lines 51-93, from `?>` after the `if ( ! in_array... )` through the closing `<?php }`) so the order is: tab bar → year navigator → sub-nav → view. Compute `$ty_baseurl` and pass `$statstype`. Keep all the data-building lines (35-46) and the view `switch` bodies (the per-view formatter calls) intact.

```php
		if ( ! in_array( $view, $valid_views, true ) ) {
			$view = 'overview';
		}

		$this_year   = (int) $this_year;
		$current_year = (int) gmdate( 'Y' );
		$first_year   = (int) ( defined( 'LWTV_FIRST_YEAR' ) ? LWTV_FIRST_YEAR : 1961 );
		$ty_baseurl  = ( $this_year === $current_year ) ? '/this-year/' : '/this-year/' . $this_year . '/';
		$statstype   = 'this-year';
		?>
		<div class="container statistics thisyear">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include LWTV_PLUGIN_PATH . '/php/statistics/templates/main/tabbar.php';
			include_once 'templates/navigation-year.php'; // year navigator
			include_once 'templates/navigation.php';       // sub-nav
			?>

			<?php
			switch ( $view ) {
				case 'overview':
					include_once 'templates/overview.php';
					break;
				case 'characters-on-air':
					include_once 'templates/characters-on-air.php';
					break;
				case 'dead-characters':
					$dead_by_date = ( new Dead_Characters_Formatter() )->format_by_date_for_year( $this_year, $characters_on_air );
					$dead_by_show = ( new Dead_Characters_Formatter() )->format_by_show_for_year( $this_year, $characters_on_air_by_show );
					include_once 'templates/dead-characters.php';
					break;
				case 'shows-on-air':
					include_once 'templates/shows-on-air.php';
					break;
				case 'new-shows':
					$new_shows_by_name    = ( new New_Shows_Formatter() )->format_by_name_for_year( $this_year, $shows_by_name );
					$new_shows_by_format  = ( new New_Shows_Formatter() )->format_by_format_for_year( $this_year, $shows_by_format );
					$new_shows_by_country = ( new New_Shows_Formatter() )->format_by_country_for_year( $this_year, $shows_by_country );
					include_once 'templates/new-shows.php';
					break;
				case 'canceled-shows':
					$canceled_shows_by_name    = ( new Canceled_Shows_Formatter() )->format_by_name_for_year( $this_year, $shows_by_name );
					$canceled_shows_by_format  = ( new Canceled_Shows_Formatter() )->format_by_format_for_year( $this_year, $shows_by_format );
					$canceled_shows_by_country = ( new Canceled_Shows_Formatter() )->format_by_country_for_year( $this_year, $shows_by_country );
					include_once 'templates/canceled-shows.php';
					break;
				default:
					include_once 'templates/overview.php';
			}
			?>
		</div>
		<?php
```

Confirm `LWTV_PLUGIN_PATH` is defined (it is, used across the plugin). Keep the `use` imports at top.

- [ ] **Step 3: Rewrite `navigation.php` as the sub-nav**

Replace the whole file with a `.lwtv-stats-subnav` mirroring `statistics/templates/shows/subnav.php`, driven by `$view` + `$ty_baseurl`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This Year sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view       Current view slug.
 * @var string $ty_baseurl Base URL ('/this-year/' or '/this-year/{year}/').
 */

$lwtv_ty_subnav = array(
	'overview'          => __( 'Overview', 'lwtv' ),
	'characters-on-air' => __( 'Characters On Air', 'lwtv' ),
	'dead-characters'   => __( 'Dead Characters', 'lwtv' ),
	'shows-on-air'      => __( 'Shows On Air', 'lwtv' ),
	'new-shows'         => __( 'New Shows', 'lwtv' ),
	'canceled-shows'    => __( 'Canceled Shows', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'This Year views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_ty_subnav as $lwtv_slug => $lwtv_label ) {
		$lwtv_is_active = ( $view === $lwtv_slug );
		$lwtv_url       = ( 'overview' === $lwtv_slug ) ? $ty_baseurl : $ty_baseurl . $lwtv_slug . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$lwtv_is_active ? ' is-active' : '',
			esc_url( home_url( $lwtv_url ) ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_label )
		);
	}
	?>
</nav>
```

- [ ] **Step 4: Rewrite `navigation-year.php` as the year navigator**

Prev/Next arrow links + year `<select>` (onchange navigate) + `<noscript>` Go + Live chip + delta caption. Floor `$first_year`, ceiling `$current_year`. Preserve the `$view` suffix in year links (blank for overview).

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This Year year navigator: prev/next + dropdown + live chip + delta caption.
 *
 * @package LezWatch.TV
 *
 * @var int    $this_year
 * @var int    $current_year
 * @var int    $first_year
 * @var string $view
 */

$lwtv_view_suffix = ( 'overview' === $view ) ? '' : $view . '/';
$lwtv_year_url    = function ( $yr ) use ( $current_year, $lwtv_view_suffix ) {
	$base = ( (int) $yr === (int) $current_year ) ? '/this-year/' : '/this-year/' . (int) $yr . '/';
	return home_url( $base . $lwtv_view_suffix );
};
$lwtv_at_min = ( $this_year <= $first_year );
$lwtv_at_max = ( $this_year >= $current_year );
?>
<div class="lwtv-ty-yearnav">
	<div class="lwtv-ty-yearnav-controls">
		<?php if ( ! $lwtv_at_min ) : ?>
			<a class="lwtv-ty-yearnav-arrow" href="<?php echo esc_url( $lwtv_year_url( $this_year - 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Previous year', 'lwtv' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: 'chevron-left.svg', icon: 'svg-chevron-left', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php else : ?>
			<span class="lwtv-ty-yearnav-arrow is-disabled" aria-hidden="true"><?php echo lwtv_plugin()->get_symbolicon( svg: 'chevron-left.svg', icon: 'svg-chevron-left', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>

		<form class="lwtv-ty-yearnav-form" method="get" action="<?php echo esc_url( home_url( '/this-year/' ) ); ?>" onchange="if(this.dataset.auto){window.location=this.querySelector('select').dataset.base.replace('%y', this.querySelector('select').value)}" data-auto="1">
			<label class="screen-reader-text" for="lwtv-ty-year"><?php esc_html_e( 'Choose year', 'lwtv' ); ?></label>
			<select id="lwtv-ty-year" class="lwtv-ty-yearnav-select" data-base="<?php echo esc_attr( home_url( '/this-year/%y/' . $lwtv_view_suffix ) ); ?>" onchange="window.location=this.dataset.base.replace('%y', this.value)">
				<?php for ( $lwtv_y = $current_year; $lwtv_y >= $first_year; $lwtv_y-- ) : ?>
					<option value="<?php echo (int) $lwtv_y; ?>"<?php selected( $lwtv_y, $this_year ); ?>><?php echo esc_html( (string) $lwtv_y ); ?></option>
				<?php endfor; ?>
			</select>
			<noscript><button type="submit" class="lwtv-ty-yearnav-go"><?php esc_html_e( 'Go', 'lwtv' ); ?></button></noscript>
		</form>

		<?php if ( ! $lwtv_at_max ) : ?>
			<a class="lwtv-ty-yearnav-arrow" href="<?php echo esc_url( $lwtv_year_url( $this_year + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Next year', 'lwtv' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: 'chevron-right.svg', icon: 'svg-chevron-right', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php else : ?>
			<span class="lwtv-ty-yearnav-arrow is-disabled" aria-hidden="true"><?php echo lwtv_plugin()->get_symbolicon( svg: 'chevron-right.svg', icon: 'svg-chevron-right', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>

		<?php if ( $this_year === $current_year ) : ?>
			<span class="lwtv-ty-yearnav-live"><?php esc_html_e( 'Live · current year', 'lwtv' ); ?></span>
		<?php endif; ?>
	</div>
	<?php if ( $this_year > $first_year ) : ?>
		<div class="lwtv-ty-yearnav-caption">
			<?php
			/* translators: %s: the prior year. */
			printf( esc_html__( 'Deltas compare against %s', 'lwtv' ), esc_html( (string) ( $this_year - 1 ) ) );
			?>
		</div>
	<?php endif; ?>
</div>
```

Notes for the implementer: (a) Simplify the `<form>` — the `<select onchange>` inline `window.location` navigation is the primary path; the `<form onchange>` wrapper is only for the `<noscript>` Go fallback (keep the form `action` + a hidden approach so no-JS submits to a year). If a clean no-JS fallback is awkward, a plain `<select onchange="window.location=...">` plus `<noscript>`-only links to ±1 year is acceptable — the JS path is what ships. Confirm `chevron-left.svg`/`chevron-right.svg` exist in the sprite (`grep 'id="chevron-left"' _build_scripts/tmp-icons/symbolicons/output/sprite.symbol.svg`); if not, pick the nearest caret glyph (the old `navigation-year.php` used a caret symbolicon — reuse that name).

- [ ] **Step 5: Enqueue count-up on This Year**

In `class-statistics-optimized.php::enqueue_scripts()`, add an enqueue so `statistics-overview.js` loads on the This Year page. After the existing actor block, add:

```php
		if ( is_page( array( 'this-year' ) ) ) {
			wp_enqueue_script( 'lwtv-stats-overview', LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js', array(), self::VERSIONING['stats-overview'], true );
		}
```

(Do NOT touch the Chart.js block yet — that is Task 6.)

- [ ] **Step 6: Add year-navigator SCSS**

In `scss/addons/_stats.scss`, add `.lwtv-ty-yearnav` styles: a flex row (`justify-content: space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:22px`). `.lwtv-ty-yearnav-controls` flex `align-items:center; gap:8px`. `.lwtv-ty-yearnav-arrow` 34×34 grid-centered, `border:1px solid colors.$lwtv-bordergrey; border-radius:8px`, `&.is-disabled{opacity:.35}`. `.lwtv-ty-yearnav-select` styled like a compact select (font-weight:700, tabular-nums, radius 8px, border). `.lwtv-ty-yearnav-live` a green chip (`background: rgba(colors.$lwtv-stats-green, .12); color: colors.$lwtv-stats-green; font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:5px 9px; border-radius:8px`). `.lwtv-ty-yearnav-caption` `font-size:.75rem; color: colors.$lwtv-medgrey`. Add a dark override in `_colors-dark.scss` if the arrow border / live chip need lifting (mirror the existing `.lwtv-stats-subnav` dark treatment).

- [ ] **Step 7: Lint, build, verify**

Run `composer lint` (expect clean). Run `NODE_OPTIONS=--experimental-global-webcrypto npm run buildquick` (expect clean). Load `https://lwtv.local/this-year/` and `https://lwtv.local/this-year/2020/`: confirm the shared tab bar shows with **This Year** active, the year navigator shows arrows + dropdown + (on current year) the Live chip + the "Deltas compare against {prevYear}" caption, and the sub-nav lists the 6 views with the correct one active. Confirm prev arrow is hidden/disabled at `/this-year/1961/` and next arrow hidden/disabled on the current year. The dropdown navigates on change.

- [ ] **Step 8: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/main/tabbar.php plugins/lwtv-plugin/php/this-year/class-display.php plugins/lwtv-plugin/php/this-year/templates/navigation.php plugins/lwtv-plugin/php/this-year/templates/navigation-year.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(this-year): shared stats shell + year navigator + sub-nav"
```

---

## Task 2: Overview (lead card + 5-metric ribbon + highlights)

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/class-display.php` (compute prev-year counts + highlight data for the overview case only)
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/overview.php`
- Modify: `scss/addons/_stats.scss` (+ `scss/partials/_colors-dark.scss`)

**Interfaces (consumed):** `$this_year`, `$first_year`, `$current_year`, the 5 count scalars, `$characters_on_air`, `$characters_on_air_by_show`, `$shows_by_name`, `$shows_by_country`. **Produces:** overview view; no downstream consumers.

- [ ] **Step 1: Compute overview-only derived data in `Display`**

In the `case 'overview':` (and `default:`) branch of `Display::make()`, before `include_once 'templates/overview.php';`, compute prev-year counts + highlight inputs:

```php
				case 'overview':
					$prev_year = $this_year - 1;
					$has_prev  = ( $this_year > $first_year );
					$prev_counts = array(
						'coa'      => $has_prev ? ( new Characters_Builder() )->get_character_count_for_year( $prev_year ) : null,
						'dead'     => $has_prev ? ( new Characters_Builder() )->get_dead_character_count_for_year( $prev_year ) : null,
						'soa'      => $has_prev ? ( new Shows_Builder() )->get_show_count_for_year( $prev_year ) : null,
						'new'      => $has_prev ? ( new Shows_Builder() )->get_started_show_count_for_year( $prev_year ) : null,
						'canceled' => $has_prev ? ( new Shows_Builder() )->get_ended_show_count_for_year( $prev_year ) : null,
					);
					$new_by_country = ( new New_Shows_Formatter() )->format_by_country_for_year( $this_year, $shows_by_country );
					$new_by_name    = ( new New_Shows_Formatter() )->format_by_name_for_year( $this_year, $shows_by_name );
					$dead_by_date_ov = ( new Dead_Characters_Formatter() )->format_by_date_for_year( $this_year, $characters_on_air );
					include_once 'templates/overview.php';
					break;
```

Update `default:` to also `include_once 'templates/overview.php';` after the same block (or route default through the overview case). Keep it DRY by extracting the overview-prep into the `overview`/`default` shared branch.

- [ ] **Step 2: Write `overview.php` — lead card**

Compute `leadStat` + `narrative` from the counts (see spec copy). Zero-death branch keys off `$dead_characters_count === 0`. Render:

```php
<div class="lwtv-ty-lead">
	<p class="lwtv-stats-eyebrow"><?php printf( esc_html__( '%s in review', 'lwtv' ), esc_html( (string) $this_year ) ); ?></p>
	<p class="lwtv-ty-lead-stat"><?php echo esc_html( $lead_stat ); ?></p>
	<p class="lwtv-ty-lead-narrative"><?php echo esc_html( $narrative ); ?></p>
</div>
```

Build `$lead_stat` / `$narrative` in PHP above the markup using `number_format_i18n()` for counts (they are counts, not years) and bare years. Example:

```php
$coa = (int) $characters_on_air_count; $dead = (int) $dead_characters_count;
$soa = (int) $shows_on_air_count; $new = (int) $new_shows_count; $canceled = (int) $canceled_shows_count;
if ( 0 === $dead ) {
	$lead_stat = sprintf( __( '%1$s characters on air, %2$s new shows, and not a single death — so far.', 'lwtv' ), number_format_i18n( $coa ), number_format_i18n( $new ) );
} else {
	$lead_stat = sprintf( __( '%1$s queer characters on air, %2$s premieres, and %3$s we lost.', 'lwtv' ), number_format_i18n( $coa ), number_format_i18n( $new ), number_format_i18n( $dead ) );
}
// trend word from coa delta (guard $has_prev):
```

Implement `trendWord` exactly per spec (first-tracked / up N / down N / flat) and assemble `$narrative`.

- [ ] **Step 3: Write `overview.php` — 5-metric ribbon**

A `$ty_metrics` array of 5 entries `{ label, family, count, prev }`, then a grid. Each card: family label, `data-count-to` number, delta line. Delta helper:

```php
$ty_delta = function ( $now, $prev ) {
	if ( null === $prev ) {
		return __( 'first tracked', 'lwtv' );
	}
	$d = (int) $now - (int) $prev;
	$arrow = ( $d > 0 ) ? '↑' : ( ( $d < 0 ) ? '↓' : '–' );
	/* translators: 1: arrow glyph, 2: absolute delta, 3: prior year. */
	return sprintf( __( '%1$s %2$s vs %3$s', 'lwtv' ), $arrow, number_format_i18n( abs( $d ) ), '' ) ; // see note
};
```

Note: build the delta text so the arrow + number render, and the "vs {prevYear}" uses the bare year. Family → modifier class: `lwtv-ty-metric--green|red|blue|pink|amber`. Markup per card:

```php
<div class="lwtv-ty-metric lwtv-ty-metric--<?php echo esc_attr( $m['family'] ); ?>">
	<span class="lwtv-ty-metric-label"><?php echo esc_html( $m['label'] ); ?></span>
	<span class="lwtv-ty-metric-num" data-count-to="<?php echo (int) $m['count']; ?>"><?php echo esc_html( number_format_i18n( (int) $m['count'] ) ); ?></span>
	<span class="lwtv-ty-metric-delta"><?php echo esc_html( $ty_delta_text ); ?></span>
</div>
```

Counter order + families: Characters On Air (green), Dead Characters (red), Shows On Air (blue), New Shows (pink), Canceled Shows (amber). Wrap in `<div class="lwtv-ty-ribbon">`.

- [ ] **Step 4: Write `overview.php` — highlights**

Derive the 3 highlights (guard every fallback per spec):
- **Biggest premiere:** filter `$new_by_name` groups → flat list of new shows; for each, count characters from `$characters_on_air_by_show` (match by show name or slug); pick max. Title = show name; desc uses its `format`/`country` + char count.
- **Leading nation:** from `$new_by_country`, the group with the most entries (count members across the grouped shape). Title = country; desc = count.
- **In memoriam:** `$dead_by_date_ov` is keyed by date ascending; the last key = most recent death; its first character + show. Zero-deaths → green "Nobody died — yet" card.

Render 3 `.lwtv-ty-highlight` cards with an icon chip (family-colored), kicker, title, desc. Icons: premiere→`star.svg` (or nearest), nation→`globe.svg`, memoriam→`heart.svg` (or nearest); confirm sprite names, fall back to available glyphs. Wrap in `<div class="lwtv-ty-highlights">` preceded by an eyebrow "Highlights of the year".

- [ ] **Step 5: Add overview SCSS**

`.lwtv-ty-lead` (card: `bg-light`-style, radius 14, padding 32, ring border, margin-bottom 20). `.lwtv-ty-lead-stat` (1.6rem/700, line-height 1.28, max-width ~22ch). `.lwtv-ty-lead-narrative` (0.95rem, line-height 1.6, `colors.$lwtv-medgrey`, max-width ~62ch). `.lwtv-ty-ribbon` grid `repeat(5,1fr); gap:10px` → `repeat(2,1fr)` under 991px, `1fr` under 575px. `.lwtv-ty-metric` (padding 14, radius 10, ring border). `.lwtv-ty-metric-label` 0.65rem/700 uppercase colored per `--family`; `.lwtv-ty-metric-num` 1.6rem/700 tabular; `.lwtv-ty-metric-delta` 0.7rem `colors.$lwtv-medgrey`. Family modifiers set the label color: green `$lwtv-stats-green`, red `$lwtv-stats-red`, blue `$lwtv-stats-blue`, pink `$lwtv-dkpink`, amber `$lwtv-stats-yellow`. `.lwtv-ty-highlights` grid `repeat(3,1fr); gap:16px` → 1fr on mobile. `.lwtv-ty-highlight` (card, padding 20). `.lwtv-ty-highlight-chip` 36×36 rounded, family bg+fg. Dark overrides in `_colors-dark.scss` for the label colors (mirror the `.lwtv-donut-center-num--*` dark treatment) + lead/metric card borders.

- [ ] **Step 6: Lint, build, verify**

`composer lint`; `NODE_OPTIONS=--experimental-global-webcrypto npm run buildquick`. Load `https://lwtv.local/this-year/` (current year) and `https://lwtv.local/this-year/2019/`. Confirm: lead card sentence matches counts; 5 ribbon cards with correct family colors + deltas (`↑/↓ N vs {prevYear}`; "first tracked" at 1961); 3 highlight cards with real derived values; the In-memoriam card flips to the green "Nobody died — yet" variant on a zero-death year (test a year with 0 deaths if one exists; else force-check by reasoning). Verify numbers via `data-count-to`.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/this-year/class-display.php plugins/lwtv-plugin/php/this-year/templates/overview.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(this-year): editorial Overview — lead, ribbon, highlights"
```

---

## Task 3: Characters On Air (By Name / By Show)

**Files:**
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php`
- Modify: `scss/addons/_stats.scss` (+ dark)

**Interfaces (consumed):** `$this_year`, `$characters_on_air_count`, `$characters_on_air` (list of `{name,url,shows:[{name,url}]}`), `$characters_on_air_by_show` (list of `{name,url,characters:[{name,url,type}],nations:[{name}],formats:[{name}]}`).

- [ ] **Step 1: Header + Bootstrap pill pair**

Header row: `<h2>` "{count} characters on air in {year}" (count via `data-count-to`) + a Bootstrap `nav nav-pills` pair (By Name / By Show) targeting two `tab-pane`s. Subtitle line. Use `nav-pills` + `tab-content`/`tab-pane fade` exactly like the current `shows-on-air.php` pill pattern (Bootstrap JS handles switching). Style the pills to the compact segmented look via a wrapper class `.lwtv-ty-pills`.

- [ ] **Step 2: By Name pane**

`<div class="lwtv-ty-charname">` grid; one `.lwtv-ty-charname-row` per `$characters_on_air` entry: character link (`/character/{slug}/` — use `$c['url']`) left; right a stacked list of the character's shows (`$c['shows']` each `{name,url}`). Multi-show characters list every show.

- [ ] **Step 3: By Show pane**

`<div class="lwtv-ty-charshow">` grid of cards; iterate `$characters_on_air_by_show` (sort by `count($show['characters'])` desc). Each card: show link + unique char count; meta line "country · format" from `$show['nations'][0]['name']` · `$show['formats'][0]['name']`; character chips, each `{name,url,type}` with the role as a small tag. Header count = `count($show['characters'])`.

- [ ] **Step 4: SCSS**

`.lwtv-ty-pills` (segmented pill styling on `.nav-pills`). `.lwtv-ty-charname` grid `repeat(2,1fr); gap:10px 16px` → 1fr mobile; `.lwtv-ty-charname-row` flex space-between, card look (ring border, radius 10, padding 11px 14px); show links right-aligned, muted, stacked. `.lwtv-ty-charshow` grid `repeat(2,1fr); gap:16px`; `.lwtv-ty-charshow-card` (radius 14, padding 18-20, ring border); char chips `.lwtv-ty-chip` (muted bg, radius, small) with a `.lwtv-ty-chip-role` muted suffix. Dark overrides as needed.

- [ ] **Step 5: Lint, build, verify**

`composer lint`; build. Load `https://lwtv.local/this-year/characters-on-air/`. Toggle By Name / By Show pills. Confirm the 2-col grid, multi-show characters listing each show, By-Show cards sorted by cast size with role-tagged chips and correct meta. Verify a character link resolves (no 301).

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(this-year): Characters On Air — By Name grid + By Show cards"
```

---

## Task 4: Dead Characters (By Date / By Show / empty state)

**Files:**
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php`
- Modify: `scss/addons/_stats.scss` (+ dark)

**Interfaces (consumed):** `$this_year`, `$dead_characters_count`, `$dead_by_date` (keyed by death-date string → list of `{name,url,shows:[{name,url,type}]}`), `$dead_by_show` (keyed by show_id → `{show:{name,url,nations,formats},characters:[{name,url}]}`).

- [ ] **Step 1: Empty state (guard first)**

If `0 === (int) $dead_characters_count`: render `<div class="lwtv-ty-empty">` — a centered green check chip (`check.svg` or nearest), `<h2>` "No characters died this year", and the warm copy "I know! We're surprised too. Fingers crossed it stays that way — check back through the year." Then `return;`.

- [ ] **Step 2: Header + pills**

`<h2>` (red) "{count} characters died in {year}" (count-up) + By Date / By Show Bootstrap pills. Subtitle with the "See the full death statistics →" link to `/statistics/death/`.

- [ ] **Step 3: By Date pane**

`.lwtv-ty-deathdate` column; one `.lwtv-ty-deathdate-row` per `$dead_by_date` key (red left rule): the formatted date (`gmdate('M j', strtotime($date_key))`) + a stacked list of `{name (link), — show}`. Each character's show comes from its `shows[0]['name']` (+ link).

- [ ] **Step 4: By Show pane**

`.lwtv-ty-deathshow` 2-col grid of cards (red top rule): show link + death count + "country · format" meta (`$row['show']['nations'][0]['name']` · `$row['show']['formats'][0]['name']`) + character list.

- [ ] **Step 5: SCSS**

`.lwtv-ty-empty` (centered card, padding 56 32; green check chip 56×56 round `background: rgba($lwtv-stats-green,.14); color:$lwtv-stats-green`). `.lwtv-ty-deathdate-row` (grid `120px 1fr`, `border-left:3px solid $lwtv-stats-red`, padding, ring border/bg). date colored red/700. `.lwtv-ty-deathshow` grid `repeat(2,1fr)`; `.lwtv-ty-deathshow-card` (`border-top:3px solid $lwtv-stats-red`, radius 14, padding). Dark overrides: red rules use `$lwtv-stats-red` (dark bright variant flips via `_colors-dark.scss`).

- [ ] **Step 6: Lint, build, verify**

`composer lint`; build. Load `https://lwtv.local/this-year/dead-characters/` (a year with deaths, e.g. current or 2019). Confirm By Date rows with red rule + By Show cards. Then load a **zero-death** year if one exists (or reason about the guard) to confirm the green empty state. Verify the death-stats link + a character link resolve.

- [ ] **Step 7: Commit**

```bash
git add plugins/lwtv-plugin/php/this-year/templates/dead-characters.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(this-year): Dead Characters — By Date / By Show / empty state"
```

---

## Task 5: Shared Shows / New / Canceled block (two-column group cards)

**Files:**
- Create: `plugins/lwtv-plugin/php/this-year/templates/partials/show-block.php` (shared block)
- Rewrite: `plugins/lwtv-plugin/php/this-year/templates/shows-on-air.php`, `new-shows.php`, `canceled-shows.php` (each sets accent/title/desc/foot + data, then includes the shared partial)
- Modify: `scss/addons/_stats.scss` (+ dark)

**Interfaces (consumed):** grouped show shapes — `$shows_by_name/_by_format/_by_country` (on-air), `$new_shows_by_name/_by_format/_by_country`, `$canceled_shows_by_*`. Each grouped: `[ groupKey => [ showName => {url,name,country,format,airdates:{start,finish}} ] ]` (name grouping adds the marker layer). **Produces:** the shared `show-block.php` partial contract (below).

- [ ] **Step 1: Define the shared partial contract + write `show-block.php`**

The three view templates set these vars then `include` the partial:
- `$sb_accent` — `'blue'|'pink'|'amber'`
- `$sb_title` — e.g. "{count} shows on air in {year}"
- `$sb_desc` — subtitle
- `$sb_foot` — footnote
- `$sb_by_name`, `$sb_by_format`, `$sb_by_country` — the three grouped datasets
- `$sb_count` — the header count (for `data-count-to`)

`show-block.php` renders: header (`<h2 class="lwtv-ty-block-title lwtv-ty-block-title--{accent}">` with count-up) + By Name/Format/Country Bootstrap pills + three `tab-pane`s. Each pane renders a grid of **two-column group cards**: for each group, a `.lwtv-ty-group-card` with a header (group key + member count) and an inner `.lwtv-ty-group-list` (`columns:2`). Each item: show link + inline `(meta)`. Meta by grouping: By Name → "country · format"; By Format → "country"; By Country → "format". Flatten the By-Name marker layer (iterate markers then show groups) into letter-headed cards.

Handle empty datasets gracefully (a pane with no groups shows a muted "None this year." line).

- [ ] **Step 2: Rewrite the three view templates**

Each is thin: set the vars and include the partial. Example `new-shows.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$sb_accent    = 'pink';
$sb_count     = (int) $new_shows_count;
/* translators: %s: year. */
$sb_title     = sprintf( _n( '%1$s show premiered in %2$s', '%1$s shows premiered in %2$s', $sb_count, 'lwtv' ), number_format_i18n( $sb_count ), (string) $this_year );
$sb_desc      = __( 'Series with a queer woman or non-binary character that started airing this year.', 'lwtv' );
$sb_foot      = __( 'A show counts as new the year its first episode aired.', 'lwtv' );
$sb_by_name   = $new_shows_by_name;
$sb_by_format = $new_shows_by_format;
$sb_by_country= $new_shows_by_country;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/partials/show-block.php';
```

`shows-on-air.php` (accent blue, `$shows_by_*`, title "{n} shows on air in {year}") and `canceled-shows.php` (accent amber, `$canceled_shows_by_*`, title "{n} shows ended in {year}") mirror it. Keep the `_n()` singular/plural forms.

- [ ] **Step 3: SCSS**

`.lwtv-ty-block-title--blue|pink|amber` (color = blue/pink/amber tokens). `.lwtv-ty-group-grid` `repeat(2,1fr); gap:16px` → 1fr mobile. `.lwtv-ty-group-card` (radius 14, padding 16 20, ring border). `.lwtv-ty-group-head` (flex baseline, group key bold colored by accent + muted count, bottom border). `.lwtv-ty-group-list { columns: 2; column-gap: 28px; }` items `.lwtv-ty-group-item { break-inside: avoid; margin-bottom: 8px; }` with a muted `(meta)`. Accent passed via a modifier on the card grid wrapper (`.lwtv-ty-group-grid--{accent}`) so the group-head key color follows the view. Dark overrides for accents via `_colors-dark.scss`.

- [ ] **Step 4: Lint, build, verify**

`composer lint`; build. Load all three: `https://lwtv.local/this-year/shows-on-air/`, `/new-shows/`, `/canceled-shows/`. In each, toggle By Name / By Format / By Country. Confirm the two-column group cards flow (a large country like USA splits into two columns, not one tall stack), the accent color per view (blue/pink/amber), correct meta per grouping, and the count-up header. Verify a show link resolves.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/this-year/templates/partials/show-block.php plugins/lwtv-plugin/php/this-year/templates/shows-on-air.php plugins/lwtv-plugin/php/this-year/templates/new-shows.php plugins/lwtv-plugin/php/this-year/templates/canceled-shows.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(this-year): shared Shows/New/Canceled block with two-column group cards"
```

---

## Task 6: Remove Chart.js entirely

Chart.js is already functionally dead (see spec audit); this removes it with zero broken callers. Do this AFTER Tasks 1–5 verify, so the redesigned This Year is confirmed rendering with Chart.js gone.

**Files (delete):** `class-piecharts-optimized.php`, `class-barcharts-optimized.php`, `class-trendline-optimized.php` (in `statistics/format/`); `post_type_actors.php` (in `statistics/templates/`); assets `chart.min.js`, `chart.min.js.map`, `chart.umd.js.map`, `chartjs-plugin-trendline.min.js`, `palette.min.js`, `palette.js` (in `plugins/lwtv-plugin/assets/js/`).
**Files (edit):** `class-statistics-optimized.php`, `class-stats-handler.php`, `class-gutenberg-ssr.php`.

- [ ] **Step 1: Delete the dead files**

```bash
git rm plugins/lwtv-plugin/php/statistics/format/class-piecharts-optimized.php \
       plugins/lwtv-plugin/php/statistics/format/class-barcharts-optimized.php \
       plugins/lwtv-plugin/php/statistics/format/class-trendline-optimized.php \
       plugins/lwtv-plugin/php/statistics/templates/post_type_actors.php \
       plugins/lwtv-plugin/assets/js/chart.min.js \
       plugins/lwtv-plugin/assets/js/chart.min.js.map \
       plugins/lwtv-plugin/assets/js/chart.umd.js.map \
       plugins/lwtv-plugin/assets/js/chartjs-plugin-trendline.min.js \
       plugins/lwtv-plugin/assets/js/palette.min.js \
       plugins/lwtv-plugin/assets/js/palette.js
```

- [ ] **Step 2: Edit `class-stats-handler.php`**

Remove the three `use` imports for `Barcharts_Optimized`/`Piecharts_Optimized`/`Trendline_Optimized` (L14-16), and remove the `switch` cases `'barchart'`, `'trendline'`, `'piechart'` (L38-43). Keep `'percentage'`, `'list'`, and `default` (returns raw `$data`).

- [ ] **Step 3: Edit `class-statistics-optimized.php`**

Remove `VERSIONING` entries `'chartjs'`, `'chartjs-plugin-trendline'`, `'palette'` (L24-26). Remove the enqueue block `if ( $is_stats_page ) { chartjs / chartjs-plugin-trendline / palette }` (L80-85). Keep the `$is_stats_page` variable (used by the early-return guard) and the `Stats_Enqueues` gate. Change `generate_individual_actors` default format `'piechart'` → `'array'` (L266) and update the `'barchart'/'trendline'/'piechart'` docblock mentions (L181/195/243). Leave the Task-1 `this-year` count-up enqueue intact.

- [ ] **Step 4: Edit `class-gutenberg-ssr.php`**

`mini_stats()` (~L52-65) is now orphaned (its only template, `post_type_actors.php`, is deleted). Remove `mini_stats()` and the `generate_stats_block_actor` facade wiring in `class-statistics-optimized.php` (the template-tag registration ~L57 and the method ~L153-154). If `generate_stats_block_actor`/`mini_stats` are referenced nowhere else (grep to confirm), delete them; if a stray reference exists, convert it to the server-rendered donut path (`template-parts/overlays/statistics-actors.php`) — but the audit found none.

- [ ] **Step 5: Verify no references remain**

```bash
grep -rniE 'chartjs|chart\.min|palette\.(min\.)?js|new Chart\(|<canvas|Barcharts_Optimized|Piecharts_Optimized|Trendline_Optimized|mini_stats|generate_stats_block_actor|post_type_actors' \
  --include='*.php' --include='*.js' plugins/ inc/ template-parts/ page-templates/ | grep -v node_modules | grep -v '/build/'
```
Expect zero hits (aside from this plan/spec docs). Fix any stragglers.

- [ ] **Step 6: Lint, build, verify pages**

`composer lint` (clean). `NODE_OPTIONS=--experimental-global-webcrypto npm run buildquick` (clean). Load `https://lwtv.local/this-year/`, `https://lwtv.local/statistics/`, `https://lwtv.local/statistics/death/`, and an actor page (e.g. `https://lwtv.local/actor/<known>/`): open the browser console/network and confirm **no 404s** for `chart.min.js` / `palette.min.js` and no `Chart is not defined` errors. Confirm the actor Character Statistics overlay (server-rendered donut) still renders.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore(stats): remove Chart.js — last consumers gone, fully server-rendered"
```

---

## Self-review notes

- **Spec coverage:** shell/year-nav/sub-nav (T1), overview lead+ribbon+highlights+deltas (T2), characters (T3), dead+empty (T4), shared show block + two-column cards (T5), color split (T2/T3/T4/T5 via `.lwtv-ty-*` families), Chart.js full removal + kill-list (T6). Count-up enqueue (T1). 1961 floor (T1 navigator). All covered.
- **Type consistency:** `$ty_baseurl`/`$this_year`/`$view`/`$first_year`/`$current_year` produced in T1's `Display` edit, consumed by nav + view templates. Grouped show shapes + character shapes cited from the spec's data-shape reference.
- **Known deferrals (bounded, implementer decides):** exact Symbolicon glyph names (chevrons, star, globe, heart, check) — verify against the sprite, fall back to nearest; tab-bar reuse (`include tabbar.php` with `$statstype='this-year'`, enabled by T1 Step 1). Bootstrap tooltip on show links optional.
