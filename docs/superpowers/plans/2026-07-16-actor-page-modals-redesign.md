# Actor-page Modals Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the bodies of the two actor-page Bootstrap modals — **Character Statistics** (Chart.js pies → two server-rendered compact donuts with count-up) and **Related Articles** (bare list → styled article cards) — in the statistics-redesign visual language, and drop Chart.js from actor pages.

**Architecture:** Keep every piece of Bootstrap modal plumbing (trigger cards, `.modal`, `data-bs-toggle`, Esc/backdrop). Extend the shared `partials/donut.php` with a `compact` layout + optional %-centre (default `full` output stays byte-identical). Generalize the count-up JS into a scoped runner fired on `shown.bs.modal`. Enqueue the count-up JS on actor singular pages and remove the Chart.js enqueue there.

**Tech Stack:** PHP 8.1+ (`LWTV\` PSR-4, `lwtv_plugin()` facade), Bootstrap 5 modals, SCSS (Dart Sass via `@wordpress/scripts`), inline SVG, vanilla-JS count-up, Symbolicons sprite (+ FA fallback). No Chart.js on actor pages; no PHPUnit — gates are PHPCS + build + browser.

## Global Constraints

- **Reuse mandate:** reuse components/tokens from the statistics rounds. NO hardcoded hex for named colors (deliberate `rgba()` / the existing dark brights in `_colors-dark.scss` are the only exceptions, matching that file's established pattern). Do NOT revert the user's committed color/size/copy tweaks.
- **Byte-identical `full` donut:** every existing `/statistics/` caller of `donut.php` passes no `layout`, so the `full` (default) render path MUST stay unchanged. Implement `compact` as an early branch that `return`s before the existing markup; leave the existing markup untouched.
- **Bootstrap plumbing untouched:** `.modal` / `.modal-dialog.modal-lg` / `.modal-content` / `.modal-header` / triggers / `data-bs-toggle` / `data-bs-target` / `data-modal-type` stay. Only modal-body content changes. The empty/error fallback (`rose.gif` + "statistics will be right back!") is preserved.
- **PHP:** WordPress-Extra PHPCS clean. `get_symbolicon` echoes carry `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` and are not wrapped in `esc_*`. All other output escaped. i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts. Guard every divisor; harden single-key unwrap (`is_array && ! empty`).
- **Animation contract:** count-up `data-count-to` (+ optional `data-count-suffix`) with a visible server-side final value; donut rings static; `prefers-reduced-motion` → finals.
- **Build:** `npm run buildquick` needs **Node 24** (`source ~/.nvm/nvm.sh; nvm use` first; Node 18 fails `crypto is not defined`). Building regenerates `style.css`/`style.min.css` — include them in the commit. Never edit `blocks/build/`/`inc/dist/`.
- **Editor hazard:** stylelint fix-on-save can mangle `scss/addons/_stats.scss` (jams `//` comments, drops declarations). After SCSS edits + build, `git diff` the SCSS and confirm ONLY intended changes.
- **Commit hygiene:** stage ONLY the files a task names (explicit paths). NEVER `git add -A` — the working tree may hold the user's uncommitted edits; leave anything you didn't touch. (The user commits to this branch in parallel; expect interleaved commits.)

## Environment (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`. **CSS lint:** `npm run lint:css`.
- **Site:** Local, `https://lwtv.local` (self-signed → `curl -sk`). Test actor: `https://lwtv.local/actor/ali-liebert/` (10 characters; 6 regular / 0 recurring / 5 guest roles; none dead).
- **wp-cli:** `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>`
- Dark mode = `html[data-bs-theme="dark"]`; toggle in the site header. Bootstrap flips modal chrome (surface/text) for free; the custom `.lwtv-donut-*` colors do NOT flip and need explicit dark rules (Task 2).

## Data shapes (verified live)

- `lwtv_plugin()->generate_individual_actors( $id, 'array', 'roles' )` → `[ 'roles' => [ ['name'=>'Regular','count'=>N], ['name'=>'Recurring','count'=>N], ['name'=>'Guest','count'=>N] ] ]` (fixed order regular, recurring, guest). Unwrap with guarded `reset()`.
- `lwtv_plugin()->generate_individual_actors( $id, 'array', 'dead' )` → `[ 'dead' => [ ['name'=>'Alive','count'=>N], ['name'=>'Dead','count'=>N] ] ]` (fixed order alive, dead).
- Character count: `$cl = get_post_meta( $id, 'lezactors_char_list', true ); $n = is_array( $cl ) ? count( $cl ) : 0;`
- Related articles: `lwtv_plugin()->get_cpt_related_posts( (int) $id, $max, 'overlay' )` → `[ 'posts' => [ <post id|WP_Post>, … ], 'total' => int ]`.
- Overlays are included from `template-parts/partials/actors/additional.php`: Statistics via `get_template_part( 'template-parts/overlays/statistics', 'actors', compact( 'actor_id' ) )` → `$args['actor_id']`; Related via `... 'related-articles', '', array( 'to_show' => $actor_id )` → `$args['to_show']`.

## File structure

**Modified:** `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php`; `plugins/lwtv-plugin/assets/js/statistics-overview.js`; `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php`; `template-parts/overlays/statistics-actors.php`; `template-parts/overlays/related-articles.php`; `scss/addons/_stats.scss`; `scss/partials/_colors-dark.scss`; `style.css`; `style.min.css`.
**New:** none.

---

### Task 1: Reusable compact donut + scoped count-up runner

**Files:** `partials/donut.php`, `assets/js/statistics-overview.js`, `scss/addons/_stats.scss`.

- [ ] **Step 1: Add the `compact` branch to `donut.php`**

At the top of `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php`, immediately AFTER the existing `$donut_offset = 0.0;` line and its `?>`, insert a compact branch that returns before the existing full markup. Change the lines:

```php
$donut_segments = $donut['segments'] ?? array();
$donut_offset   = 0.0; // cumulative share for stroke-dashoffset.
?>
```

to:

```php
$donut_segments = $donut['segments'] ?? array();
$donut_offset   = 0.0; // cumulative share for stroke-dashoffset.
$donut_layout   = $donut['layout'] ?? 'full';

if ( 'compact' === $donut_layout ) :
	$donut_has_pct = isset( $donut['center_pct'] );
	$donut_family  = $donut['center_family'] ?? '';
	?>
	<section class="lwtv-donut-card lwtv-donut-card--compact bg-light">
		<p class="lwtv-stats-eyebrow"><?php echo esc_html( $donut['eyebrow'] ?? '' ); ?></p>
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
				<?php if ( $donut_has_pct ) : ?>
					<span class="lwtv-donut-center-num lwtv-donut-center-num--<?php echo esc_attr( $donut_family ); ?>" data-count-to="<?php echo (int) $donut['center_pct']; ?>" data-count-suffix="%"><?php echo esc_html( number_format_i18n( (int) $donut['center_pct'] ) ); ?>%</span>
				<?php else : ?>
					<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) ( $donut['center'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $donut['center'] ?? 0 ) ) ); ?></span>
				<?php endif; ?>
				<span class="lwtv-donut-center-sub"><?php echo esc_html( $donut['center_sub'] ?? '' ); ?></span>
			</div>
		</div>
		<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
			<?php
			foreach ( $donut_segments as $donut_seg ) {
				?>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>"></span>
					<span class="lwtv-donut-legend-name"><?php echo esc_html( $donut_seg['label'] ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( (int) $donut_seg['count'] ) . ' · ' . $donut_seg['pct'] . '%' ); ?></span>
				</li>
				<?php
			}
			?>
		</ul>
	</section>
	<?php
	return;
endif;
?>
```

Leave everything below (the existing `full` markup) exactly as-is.

- [ ] **Step 2: Generalize `statistics-overview.js`**

Replace the entire body of `plugins/lwtv-plugin/assets/js/statistics-overview.js` with:

```js
/**
 * Statistics animations: count-up numbers and grow-in bars.
 * Reads targets from data attributes; respects prefers-reduced-motion.
 * Exposes window.lwtvStatsCountUp(root) so modals can replay on open.
 */
( function () {
	'use strict';

	var DURATION = 1100;

	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function finalText( el ) {
		var target = parseInt( el.getAttribute( 'data-count-to' ), 10 ) || 0;
		return target.toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
	}

	function animate( root ) {
		root = root || document;
		var reduce  = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var numbers = Array.prototype.slice.call( root.querySelectorAll( '[data-count-to]' ) );
		var bars    = Array.prototype.slice.call( root.querySelectorAll( '[data-grow-to]' ) );

		if ( reduce ) {
			bars.forEach( function ( el ) {
				el.style.width = parseFloat( el.getAttribute( 'data-grow-to' ) ) + '%';
			} );
			numbers.forEach( function ( el ) {
				el.textContent = finalText( el );
			} );
			return;
		}

		numbers.forEach( function ( el ) {
			el.textContent = ( 0 ).toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
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
				el.textContent = Math.round( e * target ).toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
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

	window.lwtvStatsCountUp = animate;

	function init() {
		// Static pages (e.g. /statistics/): animate the whole document once.
		animate( document );

		// Bootstrap modals (e.g. actor Character Statistics): replay scoped to
		// the modal each time it opens.
		document.addEventListener( 'shown.bs.modal', function ( ev ) {
			if ( ev.target && ev.target.querySelector( '[data-count-to],[data-grow-to]' ) ) {
				animate( ev.target );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
```

- [ ] **Step 3: SCSS — new `blue` segment + compact layout + centre families**

In `scss/addons/_stats.scss`, add the blue donut segment in the seg-color group (right after the `.lwtv-donut-seg--pink { … }` rule):

```scss
	.lwtv-donut-seg--blue {
		stroke: colors.$lwtv-stats-blue;
		background-color: colors.$lwtv-stats-blue;
	}
```

and in the `.lwtv-donut-legend-track .progress-bar { … }` legend-override group (next to the other `&.lwtv-donut-seg--*`):

```scss
		&.lwtv-donut-seg--blue { background-color: colors.$lwtv-stats-blue !important; }
```

Then, right after the existing `.lwtv-donut-legend-val { … }` rule (end of the donut layout block, before the seg-color group), add the compact + centre-family styles:

```scss
	.lwtv-donut-center-num--green { color: colors.$lwtv-stats-green; }
	.lwtv-donut-center-num--red { color: colors.$lwtv-red; }

	.lwtv-donut-card--compact {
		flex-direction: column;
		align-items: stretch;
		gap: 16px;
		padding: 20px;

		.lwtv-stats-eyebrow {
			margin: 0;
		}

		.lwtv-donut-figure {
			align-self: center;
			width: 150px;
			height: 150px;
		}
	}

	.lwtv-donut-legend--compact .lwtv-donut-legend-row {
		grid-template-columns: 12px 1fr auto;
	}

	.lwtv-actor-stats-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 20px;

		@media (max-width: 575px) {
			grid-template-columns: 1fr;
		}
	}

	.lwtv-actor-stats-caption {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 16px;
		font-size: 0.9rem;
	}

	.lwtv-actor-stats-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background-color: colors.$lwtv-stats-green;
	}
```

- [ ] **Step 4: Lint + build + verify no regression to the full donut**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
git diff scss/addons/_stats.scss   # confirm ONLY the intended additions; no mangled comments/dropped lines
# Full donut path unchanged — a /statistics/ donut page still renders its segments:
curl -sk https://lwtv.local/statistics/actors/sexuality/ | grep -oE 'lwtv-donut-seg--[a-z0-9]+' | sort | uniq -c
# JS: new runner is exposed and file parses:
grep -c 'window.lwtvStatsCountUp' plugins/lwtv-plugin/assets/js/statistics-overview.js   # -> 1
node --check plugins/lwtv-plugin/assets/js/statistics-overview.js && echo "JS OK"
```
Expected: build clean; the stats donut page still shows its `grey`/pink-ramp segments (full path intact); `window.lwtvStatsCountUp` present; JS parses.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/donut.php plugins/lwtv-plugin/assets/js/statistics-overview.js scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(actor-modals): compact donut layout + scoped count-up runner

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Character Statistics modal body + enqueue swap (Chart.js → count-up)

**Files:** `template-parts/overlays/statistics-actors.php`, `plugins/lwtv-plugin/php/_components/class-statistics-optimized.php`, `scss/partials/_colors-dark.scss`.

- [ ] **Step 1: Rewrite the Statistics modal body**

In `template-parts/overlays/statistics-actors.php`, keep the trigger card + the `<div class="modal fade" … id="statistics" …>` wrapper (header, `.modal-dialog.modal-lg`, `.modal-content`, `.modal-header` with title + `.btn-close`). Replace the entire `<div class="modal-body"> … </div>` with:

```php
			<div class="modal-body lwtv-actor-stats-modal">
				<?php
				$lwtv_char_list  = get_post_meta( $this_id, 'lezactors_char_list', true );
				$lwtv_char_count = is_array( $lwtv_char_list ) ? count( $lwtv_char_list ) : 0;

				$lwtv_roles_raw  = lwtv_plugin()->generate_individual_actors( $this_id, 'array', 'roles' );
				$lwtv_roles_data = ( is_array( $lwtv_roles_raw ) && ! empty( $lwtv_roles_raw ) ) ? (array) reset( $lwtv_roles_raw ) : array();
				$lwtv_dead_raw   = lwtv_plugin()->generate_individual_actors( $this_id, 'array', 'dead' );
				$lwtv_dead_data  = ( is_array( $lwtv_dead_raw ) && ! empty( $lwtv_dead_raw ) ) ? (array) reset( $lwtv_dead_raw ) : array();

				if ( 0 === $lwtv_char_count && empty( $lwtv_roles_data ) ) {
					?>
					<p><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/rose.gif" alt="Rose revealing herself by peeling off a face mask in Jane the Virgin" class="alignleft"/></p>
					<p>What're the odds? Don't worry, the statistics will be right back!</p>
					<?php
				} else {
					// ---- Roles donut (Regular pink / Recurring blue / Guest amber). ----
					$lwtv_role_classes = array( 'pink', 'blue', 'amber' );
					$lwtv_total_roles  = 0;
					foreach ( $lwtv_roles_data as $lwtv_role ) {
						$lwtv_total_roles += (int) $lwtv_role['count'];
					}
					$lwtv_role_segments = array();
					foreach ( array_values( $lwtv_roles_data ) as $lwtv_i => $lwtv_role ) {
						$lwtv_rc              = (int) $lwtv_role['count'];
						$lwtv_role_segments[] = array(
							'label' => $lwtv_role['name'],
							'count' => $lwtv_rc,
							'pct'   => ( $lwtv_total_roles > 0 ) ? round( ( $lwtv_rc / $lwtv_total_roles ) * 100, 1 ) : 0,
							'class' => $lwtv_role_classes[ $lwtv_i ] ?? 'grey',
						);
					}

					// ---- Status donut (Alive green / Dead red). ----
					$lwtv_alive = isset( $lwtv_dead_data[0] ) ? (int) $lwtv_dead_data[0]['count'] : 0;
					$lwtv_dead  = isset( $lwtv_dead_data[1] ) ? (int) $lwtv_dead_data[1]['count'] : 0;
					$lwtv_total_status = $lwtv_alive + $lwtv_dead;
					$lwtv_dominant     = max( $lwtv_alive, $lwtv_dead );
					$lwtv_status_pct   = ( $lwtv_total_status > 0 ) ? round( ( $lwtv_dominant / $lwtv_total_status ) * 100, 1 ) : 0;
					$lwtv_status_fam   = ( 0 === $lwtv_dead ) ? 'green' : 'red';
					$lwtv_status_sub   = ( $lwtv_alive >= $lwtv_dead ) ? __( 'alive', 'lwtv' ) : __( 'dead', 'lwtv' );
					$lwtv_status_segments = array(
						array(
							'label' => isset( $lwtv_dead_data[0] ) ? $lwtv_dead_data[0]['name'] : __( 'Alive', 'lwtv' ),
							'count' => $lwtv_alive,
							'pct'   => ( $lwtv_total_status > 0 ) ? round( ( $lwtv_alive / $lwtv_total_status ) * 100, 1 ) : 0,
							'class' => 'green',
						),
						array(
							'label' => isset( $lwtv_dead_data[1] ) ? $lwtv_dead_data[1]['name'] : __( 'Dead', 'lwtv' ),
							'count' => $lwtv_dead,
							'pct'   => ( $lwtv_total_status > 0 ) ? round( ( $lwtv_dead / $lwtv_total_status ) * 100, 1 ) : 0,
							'class' => 'red',
						),
					);
					?>
					<p class="lwtv-actor-stats-caption">
						<?php esc_html_e( 'Statistics are updated daily.', 'lwtv' ); ?>
						<span class="lwtv-actor-stats-dot" aria-hidden="true"></span>
						<strong>
						<?php
						/* translators: %s: number of characters played. */
						printf( esc_html( _n( '%s character', '%s characters', $lwtv_char_count, 'lwtv' ) ), esc_html( number_format_i18n( $lwtv_char_count ) ) );
						?>
						</strong>
					</p>
					<div class="lwtv-actor-stats-grid">
						<?php
						$donut = array(
							'layout'     => 'compact',
							'segments'   => $lwtv_role_segments,
							'center'     => $lwtv_total_roles,
							'center_sub' => __( 'roles', 'lwtv' ),
							'eyebrow'    => __( 'Roles', 'lwtv' ),
						);
						// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
						include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/donut.php';

						$donut = array(
							'layout'        => 'compact',
							'segments'      => $lwtv_status_segments,
							'center_pct'    => (int) round( $lwtv_status_pct ),
							'center_family' => $lwtv_status_fam,
							'center_sub'    => $lwtv_status_sub,
							'eyebrow'       => __( 'Status', 'lwtv' ),
						);
						// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
						include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/donut.php';
						?>
					</div>
					<p><em><small><?php esc_html_e( 'Note: character roles may exceed the number of characters played, if the character appeared on multiple TV shows.', 'lwtv' ); ?></small></em></p>
					<?php
				}
				?>
			</div>
```

Confirm `LWTV_PLUGIN_PATH` is the correct constant for the plugin base (grep an existing `include LWTV_PLUGIN_PATH` in the stats templates — the statistics page shell uses it; if the overlay context doesn't define it, use `WP_PLUGIN_DIR . '/lwtv-plugin'` equivalent already in use, or `plugin_dir_path()` against a known plugin file). Verify the include resolves before finishing.

- [ ] **Step 2: Enqueue swap in `class-statistics-optimized.php`**

Replace the `enqueue_scripts()` method body (currently lines ~71–87) with:

```php
	public function enqueue_scripts() {
		$is_stats_page = is_page( array( 'statistics' ) ) || is_page( array( 'this-year' ) );
		$is_actor      = CPT_Actors::SLUG === get_post_type();

		// If it's not any of our pages, return.
		if ( ! $is_stats_page && ! $is_actor ) {
			return;
		}

		// Chart.js only where charts still render (statistics + this-year).
		if ( $is_stats_page ) {
			wp_enqueue_script( 'chartjs', LWTV_PLUGIN_URL . '/assets/js/chart.min.js', array( 'jquery' ), self::VERSIONING['chartjs'], false );
			wp_enqueue_script( 'chartjs-plugin-trendline', LWTV_PLUGIN_URL . '/assets/js/chartjs-plugin-trendline.min.js', array( 'chartjs' ), self::VERSIONING['chartjs-plugin-trendline'], false );
			wp_enqueue_script( 'palette', LWTV_PLUGIN_URL . '/assets/js/palette.min.js', array(), self::VERSIONING['palette'], false );
		}

		// Custom extra for the statistics landing pages.
		if ( is_page( array( 'statistics' ) ) ) {
			( new Stats_Enqueues() )->enqueue_scripts( self::VERSIONING );
		}

		// Actor pages: server-rendered donut modals use count-up, no Chart.js.
		if ( $is_actor ) {
			wp_enqueue_script( 'lwtv-stats-overview', LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js', array(), self::VERSIONING['stats-overview'], true );
		}
	}
```

Then bump the count-up version so caches pick up the Task-1 JS change: in the `VERSIONING` const, change `'stats-overview' => '1.0.0',` to `'stats-overview' => '1.1.0',`.

- [ ] **Step 3: Dark-mode donut rules for the modal**

In `scss/partials/_colors-dark.scss`, add a focused block for the actor stats modal (Bootstrap flips the modal chrome for free; these cover the custom donut colors, and brighten the segments to match the handoff dark screenshot — the same brights already used by the family icons in this file). Place it near the other `[data-bs-theme=dark]` `.statistics` donut rules:

```scss
	.lwtv-actor-stats-modal {
		.lwtv-donut-track {
			stroke: colors.$lwtv-medgrey;
		}

		.lwtv-donut-card {
			border-color: rgba(255, 255, 255, 0.12);
		}

		.lwtv-donut-center-num,
		.lwtv-donut-center-sub,
		.lwtv-donut-legend-name,
		.lwtv-donut-legend-val {
			color: colors.$white;
		}

		.lwtv-donut-center-num--green {
			color: #2ecc71;
		}

		.lwtv-donut-center-num--red {
			color: #e74c3c;
		}

		.lwtv-donut-seg--blue {
			stroke: #3498db;
			background-color: #3498db;
		}

		.lwtv-donut-seg--amber {
			stroke: #f1c40f;
			background-color: #f1c40f;
		}

		.lwtv-donut-seg--green {
			stroke: #2ecc71;
			background-color: #2ecc71;
		}

		.lwtv-donut-seg--red {
			stroke: #e74c3c;
			background-color: #e74c3c;
		}
	}
```

Match the file's existing dark-block nesting/selector style (this file scopes rules under a `[data-bs-theme="dark"]` / `.statistics`-style wrapper — place `.lwtv-actor-stats-modal` at the same nesting level as the existing `.statistics` block, NOT inside it). Confirm by reading the surrounding structure before inserting.

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
git diff scss/addons/_stats.scss   # should be empty this task; if stylelint touched it, checkout & rebuild
# Statistics modal renders two compact donuts, no Chart.js on actor pages:
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'lwtv-donut-card--compact'      # -> 2
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -oE 'lwtv-donut-seg--(pink|blue|amber|green|red)' | sort | uniq -c
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'lwtv-actor-stats-caption'      # -> 1
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'chart.min.js'                  # -> 0  (Chart.js gone from actor pages)
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'statistics-overview.js'        # -> 1  (count-up present)
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'lwtv-stats-spinner'            # -> 0  (spinner removed)
# Regression: stats pages still get Chart.js:
curl -sk https://lwtv.local/statistics/shows/ | grep -c 'chart.min.js'                    # -> 1
```
Expected: 2 compact donuts, segments pink/blue/amber (roles) + green/red (status), caption present, spinner gone, Chart.js absent on actor page but present on `/statistics/`. Roles centre = total roles (11 for Ali), Status centre = `100%` `alive`.

- [ ] **Step 5: Commit**

```bash
git add template-parts/overlays/statistics-actors.php plugins/lwtv-plugin/php/_components/class-statistics-optimized.php scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(actor-modals): server-rendered Character Statistics donuts; drop Chart.js on actor pages

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Related Articles modal body + article-card styles

**Files:** `template-parts/overlays/related-articles.php`, `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`.

- [ ] **Step 1: Rewrite the Related Articles modal body**

In `template-parts/overlays/related-articles.php`, keep the trigger card + modal wrapper/header. Replace the entire `<div class="modal-body"> … </div>` with:

```php
			<div class="modal-body lwtv-actor-articles-modal">
				<?php
				$lwtv_max_posts     = 6;
				$lwtv_related_posts = lwtv_plugin()->get_cpt_related_posts( (int) $this_id, $lwtv_max_posts, 'overlay' );
				$lwtv_total_posts   = (int) ( $lwtv_related_posts['total'] ?? 0 );
				$lwtv_cat_class_map = array(
					'news'        => 'pink',
					'site'        => 'blue',
					'queer-beats' => 'dkpink',
				);
				?>
				<p class="lwtv-articles-intro">
					<?php
					/* translators: %s: number of related articles. */
					printf( esc_html( _n( '%s article tagged with this actor on the LezWatch.TV blog.', '%s articles tagged with this actor on the LezWatch.TV blog.', $lwtv_total_posts, 'lwtv' ) ), esc_html( number_format_i18n( $lwtv_total_posts ) ) );
					?>
				</p>

				<div class="lwtv-article-list">
					<?php
					foreach ( $lwtv_related_posts['posts'] as $lwtv_related_post ) {
						$lwtv_post_obj = get_post( $lwtv_related_post );
						if ( ! $lwtv_post_obj ) {
							continue;
						}
						$lwtv_pid   = $lwtv_post_obj->ID;
						$lwtv_link  = get_the_permalink( $lwtv_pid );
						$lwtv_cats  = get_the_category( $lwtv_pid );
						$lwtv_cat   = ! empty( $lwtv_cats ) ? $lwtv_cats[0] : null;
						$lwtv_ccls  = ( $lwtv_cat && isset( $lwtv_cat_class_map[ $lwtv_cat->slug ] ) ) ? $lwtv_cat_class_map[ $lwtv_cat->slug ] : 'grey';
						$lwtv_thumb = get_the_post_thumbnail( $lwtv_pid, 'medium', array( 'class' => 'lwtv-article-thumb-img', 'loading' => 'lazy' ) );
						?>
						<article class="lwtv-article-card">
							<a class="lwtv-article-thumb" href="<?php echo esc_url( $lwtv_link ); ?>" tabindex="-1" aria-hidden="true">
								<?php
								if ( $lwtv_thumb ) {
									echo $lwtv_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
								} else {
									?>
									<span class="lwtv-article-thumb-empty"><?php echo lwtv_plugin()->get_symbolicon( svg: 'image.svg', icon: 'svg-image', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php
								}
								if ( $lwtv_cat ) {
									?>
									<span class="lwtv-article-tag lwtv-article-tag--<?php echo esc_attr( $lwtv_ccls ); ?>"><?php echo esc_html( $lwtv_cat->name ); ?></span>
									<?php
								}
								?>
							</a>
							<div class="lwtv-article-info">
								<h4 class="lwtv-article-title"><a href="<?php echo esc_url( $lwtv_link ); ?>"><?php echo esc_html( get_the_title( $lwtv_pid ) ); ?></a></h4>
								<p class="lwtv-article-excerpt"><?php echo esc_html( get_the_excerpt( $lwtv_pid ) ); ?></p>
								<p class="lwtv-article-date">
									<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar', max_size: '13' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span><?php echo esc_html( get_the_date( get_option( 'date_format' ), $lwtv_pid ) ); ?></span>
								</p>
							</div>
						</article>
						<?php
					}

					if ( $lwtv_total_posts > $lwtv_max_posts ) {
						$lwtv_slug = get_post_field( 'post_name', get_post( $this_id ) );
						$lwtv_tag  = term_exists( $lwtv_slug, 'post_tag' );
						if ( ! is_null( $lwtv_tag ) && is_array( $lwtv_tag ) ) {
							?>
							<a class="lwtv-articles-foot" href="<?php echo esc_url( get_tag_link( $lwtv_tag['term_id'] ) ); ?>"><?php esc_html_e( 'See all related coverage', 'lwtv' ); ?> <span aria-hidden="true">&rarr;</span></a>
							<?php
						}
					}
					?>
				</div>
			</div>
```

Verify the `image.svg` / `calendar-alt.svg` sprite ids resolve to real `<use>` (they render via `get_symbolicon` with FA fallbacks `svg-image` / `svg-calendar` if the sprite lacks them — acceptable either way; confirm no broken glyph). If `image.svg` is absent, keep the FA fallback (do NOT import Lucide).

- [ ] **Step 2: Article-card SCSS (light)**

In `scss/addons/_stats.scss`, after the compact/grid rules from Task 1, add:

```scss
	.lwtv-articles-intro {
		margin-bottom: 16px;
		font-size: 0.9rem;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-article-list {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.lwtv-article-card {
		display: flex;
		gap: 16px;
		padding: 12px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 10px;
		transition: transform 0.15s ease, box-shadow 0.15s ease;

		&:hover {
			transform: translateY(-1px);
			box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
		}
	}

	.lwtv-article-thumb {
		position: relative;
		flex: 0 0 auto;
		width: 132px;
		height: 88px;
		overflow: hidden;
		border-radius: 8px;
		background-color: colors.$lwtv-ltgrey;

		.lwtv-article-thumb-img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
	}

	.lwtv-article-thumb-empty {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100%;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-article-tag {
		position: absolute;
		top: 6px;
		left: 6px;
		padding: 2px 8px;
		font-size: 0.625rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: colors.$white;
		border-radius: 6px;
		background-color: colors.$lwtv-medgrey;
	}

	.lwtv-article-tag--pink { background-color: colors.$lwtv-pink; }
	.lwtv-article-tag--blue { background-color: colors.$lwtv-stats-blue; }
	.lwtv-article-tag--dkpink { background-color: colors.$lwtv-dkpink; }

	.lwtv-article-info {
		flex: 1 1 auto;
		min-width: 0;
	}

	.lwtv-article-title {
		margin: 0 0 4px;
		font-size: 1rem;
		font-weight: 700;
		line-height: 1.3;

		a {
			color: colors.$lwtv-pink;
			text-decoration: none;

			&:hover {
				color: colors.$lwtv-purple;
			}
		}
	}

	.lwtv-article-excerpt {
		margin: 0 0 6px;
		font-size: 0.8125rem;
		line-height: 1.5;
		color: colors.$lwtv-medgrey;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.lwtv-article-date {
		display: flex;
		align-items: center;
		gap: 5px;
		margin: 0;
		font-size: 0.75rem;
		color: colors.$lwtv-medgrey;
	}
```

- [ ] **Step 3: Article-card dark mode**

In `scss/partials/_colors-dark.scss`, in the same dark scope as the modal block from Task 2, add:

```scss
	.lwtv-actor-articles-modal {
		.lwtv-article-card {
			border-color: rgba(255, 255, 255, 0.12);

			&:hover {
				box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
			}
		}

		.lwtv-articles-intro,
		.lwtv-article-excerpt,
		.lwtv-article-date {
			color: colors.$lwtv-ltgrey;
		}

		.lwtv-article-title a {
			color: colors.$lwtv-ltpink;
		}
	}
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'lwtv-article-card'     # -> up to 6
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -oE 'lwtv-article-tag--[a-z]+' | sort | uniq -c
curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'lwtv-articles-intro'   # -> 1
```
Expected: article cards with category tags (pink/blue/dkpink/grey), intro line with the correct pluralized count, calendar icon on dates. (If Ali has no related posts, test an actor that does; note it.)

- [ ] **Step 5: Commit**

```bash
git add template-parts/overlays/related-articles.php scss/addons/_stats.scss scss/partials/_colors-dark.scss style.css style.min.css
git commit -m "feat(actor-modals): styled Related Articles cards

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Full verification + polish

**Files:** none expected (fixes only if verification finds issues).

- [ ] **Step 1: Lint + build clean**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```

- [ ] **Step 2: Full render + regression sweep**

```bash
for u in "actor/ali-liebert/" "statistics/" "statistics/shows/" "statistics/characters/" "statistics/actors/" "statistics/death/"; do
  code=$(curl -sk -o /tmp/m.html -w "%{http_code}" "https://lwtv.local/$u")
  err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/m.html)
  echo "$u -> HTTP $code, php-errors=$err"
done
# Chart.js: gone on actor pages, present on stats:
echo "actor chartjs:"; curl -sk https://lwtv.local/actor/ali-liebert/ | grep -c 'chart.min.js'
echo "stats chartjs:"; curl -sk https://lwtv.local/statistics/shows/ | grep -c 'chart.min.js'
```
Expected: every URL HTTP 200, php-errors=0; actor chartjs=0, stats chartjs=1.

- [ ] **Step 3: Browser QA** on `https://lwtv.local/actor/ali-liebert/` against the four handoff screenshots (`design_handoff_actor_modals/screenshots/`):
  - Open **Character Statistics**: two compact donuts (Roles pink/blue/amber, centre "11 roles"; Status green, centre "100% alive"); caption "Statistics are updated daily. ● 10 characters"; footnote present; **numbers count up on open and replay on reopen**; reduced-motion → finals.
  - Open **Related Articles**: intro count, article cards (thumbnail + category tag + pink title + 2-line excerpt + calendar date), footer link when > 6.
  - Both modals in **light + dark** (Bootstrap flips chrome; donut segs bright in dark per screenshot); Esc + backdrop close; narrow-screen stacking.
  - Confirm the browser Network panel shows **no `chart.min.js`** on the actor page, and no JS console errors.
  - Regression: a `/statistics/` donut page looks unchanged (full donut path intact).

- [ ] **Step 4: Commit** (only if Step 3 required fixes; otherwise skip)

```bash
git add <changed paths>
git commit -m "fix(actor-modals): <what> from verification

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:** compact donut + %-centre → T1; Statistics modal (caption + two donuts + footnote, spinner dropped) → T2; Chart.js removal + count-up enqueue on actor pages → T2; Related Articles cards → T3; count-up-on-open/replay via scoped runner + `shown.bs.modal` → T1 (JS) + used in T2; new `blue` seg + compact/article SCSS + dark coverage → T1/T2/T3; full verification → T4. Reuse mandate, byte-identical `full` donut, Bootstrap plumbing preserved, i18n/escaping, divisor guards, animation contract — enforced per task + Global Constraints. ✓

**Placeholder scan:** no TBD/TODO. Deferred confirmations flagged inline: the `LWTV_PLUGIN_PATH` include constant (T2 S1), the `image.svg`/`calendar-alt.svg` sprite ids (FA fallback is fine), and the exact `_colors-dark.scss` nesting level (T2 S3) — each has a concrete verify step. ✓

**Type consistency:** `$donut` compact contract (`layout`, `segments[label,count,pct,class]`, `center` OR `center_pct`+`center_family`, `center_sub`, `eyebrow`) matches the Task-1 `donut.php` branch; segment classes (pink/blue/amber/green/red) all map to `.lwtv-donut-seg--*` (blue added T1). `data-count-to`/`data-count-suffix`/`data-grow-to` match the Task-1 JS runner. `generate_individual_actors($id,'array',$type)` unwrap shape matches the verified data. `get_cpt_related_posts` `['posts','total']` shape matches T3 usage. Overlay vars (`$this_id` from `$args['actor_id']` / `$args['to_show']`) unchanged. ✓

## Known follow-ups (out of scope)
- Status-donut color rule ("green iff no deaths, else red") and centre/label copy are handoff-illustrative — owner may tune.
- Dark-mode donut segments are brightened **only** inside the actor modal to match the handoff screenshot; the `/statistics/` donuts keep their existing (muted) dark segment tokens. If the owner wants the two contexts identical, unify later.
- After this lands, `/statistics/nations|stations|death` (+ `this-year`) are the only remaining Chart.js consumers.
