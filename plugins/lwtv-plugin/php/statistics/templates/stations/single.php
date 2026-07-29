<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Single-station statistics: profile bar + one view.
 *
 * @package LezWatch.TV
 *
 * @var array  $all_stations_data
 * @var array  $character_counts
 * @var array  $show_counts
 * @var string $station  Station slug, '_'-prefixed.
 * @var string $view    View, '_'-prefixed.
 */

use LWTV\Statistics\Build\Overview_Factsheet;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

$lwtv_slug    = ltrim( $station, '_' );
$lwtv_vslug   = ltrim( $view, '_' );
$lwtv_ndata   = $all_stations_data[ $lwtv_slug ] ?? array(
	'name'  => __( 'Station', 'lwtv' ),
	'count' => 0,
);
$lwtv_name    = $lwtv_ndata['name'];
$lwtv_shows   = (int) ( $show_counts[ $lwtv_slug ]['total'] ?? $lwtv_ndata['count'] ?? 0 );
$lwtv_onair   = (int) ( $show_counts[ $lwtv_slug ]['onair'] ?? 0 );
$lwtv_score   = (float) ( $show_counts[ $lwtv_slug ]['score'] ?? 0 );
$lwtv_oascore = (float) ( $show_counts[ $lwtv_slug ]['onairscore'] ?? 0 );
$lwtv_chars   = (int) ( $character_counts[ $lwtv_slug ]['total'] ?? 0 );
$lwtv_dead    = (int) ( $character_counts[ $lwtv_slug ]['dead'] ?? 0 );

/**
 * Build donut segments from a [name,count,...] list: top N ramp + grey remainder.
 *
 * @param array  $items      Items with 'name' + 'count'.
 * @param int    $topn       Number of ramped segments before folding into Other.
 * @param string $grey_match Optional lowercase name to force into the grey slot first (e.g. 'cisgender').
 * @return array [ segments, total ]
 */
$lwtv_build_segments = function ( $items, $topn, $grey_match = '' ) {
	$items = is_array( $items ) ? $items : array();
	$total = 0;
	foreach ( $items as $it ) {
		$total += (int) $it['count'];
	}
	$ramp     = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );
	$segments = array();
	$grey_val = 0;

	// Pull the grey-matched item (cisgender) out first, if present.
	if ( '' !== $grey_match ) {
		foreach ( $items as $k => $it ) {
			if ( strtolower( $it['name'] ) === $grey_match ) {
				$grey_val = (int) $it['count'];
				unset( $items[ $k ] );
				break;
			}
		}
	}

	uasort( $items, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

	if ( '' !== $grey_match ) {
		$segments[] = array(
			'label' => ucfirst( $grey_match ),
			'count' => $grey_val,
			'pct'   => ( $total > 0 ) ? round( ( $grey_val / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
	}

	$i     = 0;
	$named = $grey_val;
	foreach ( $items as $it ) {
		if ( $i >= $topn || (int) $it['count'] <= 0 ) {
			break;
		}
		$c          = (int) $it['count'];
		$named     += $c;
		$segments[] = array(
			'label' => $it['name'],
			'count' => $c,
			'pct'   => ( $total > 0 ) ? round( ( $c / $total ) * 100, 1 ) : 0,
			'class' => $ramp[ $i ],
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
	return array( $segments, $total );
};
?>
<?php if ( '_all' !== $view ) : ?>
<div class="lwtv-fact-masthead">
	<div class="lwtv-fact-masthead-lead">
		<span class="lwtv-nation-profile-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '19' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Station Profile', 'lwtv' ); ?></span>
			<h2 class="lwtv-fact-masthead-name"><?php echo esc_html( $lwtv_name ); ?></h2>
		</div>
	</div>
	<div class="lwtv-nation-profile-figs">
		<span><strong data-count-to="<?php echo (int) $lwtv_shows; ?>"><?php echo esc_html( number_format_i18n( $lwtv_shows ) ); ?></strong><em><?php esc_html_e( 'shows', 'lwtv' ); ?></em></span>
		<span><strong data-count-to="<?php echo (int) $lwtv_chars; ?>"><?php echo esc_html( number_format_i18n( $lwtv_chars ) ); ?></strong><em><?php esc_html_e( 'characters', 'lwtv' ); ?></em></span>
		<span class="lwtv-nation-profile-dead"><strong data-count-to="<?php echo (int) $lwtv_dead; ?>"><?php echo esc_html( number_format_i18n( $lwtv_dead ) ); ?></strong><em><?php esc_html_e( 'dead', 'lwtv' ); ?></em></span>
	</div>
</div>
<?php endif; ?>

<?php
switch ( $view ) {
	case '_all':
		// ── Data ────────────────────────────────────────────────────────
		// Rank: reproduce the leaderboard sort (all.php) and find this station's place.
		$lwtv_rank   = null;
		$lwtv_ranked = array();
		foreach ( $all_stations_data as $lwtv_rslug => $lwtv_rdata ) {
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
		$lwtv_fy_map   = ( new Build_Taxonomy_Optimized() )->get_bulk_first_years( 'lez_stations', array( $lwtv_slug ) );
		$lwtv_first_yr = (int) ( $lwtv_fy_map[ $lwtv_slug ] ?? 0 );
		$lwtv_first_yr = ( $lwtv_first_yr > 0 ) ? $lwtv_first_yr : null;

		// Best-scoring show.
		$lwtv_top_map  = ( new Build_Taxonomy_Optimized() )->get_bulk_top_shows( 'lez_stations', array( $lwtv_slug ) );
		$lwtv_top_show = $lwtv_top_map[ $lwtv_slug ] ?? null;

		// Global characters-per-show average (site-wide, cached upstream).
		$lwtv_g_chars   = (int) lwtv_plugin()->generate_total_counts( 'characters' );
		$lwtv_g_shows   = (int) lwtv_plugin()->generate_total_counts( 'shows' );
		$lwtv_global_av = Overview_Factsheet::ratio( $lwtv_g_chars, $lwtv_g_shows );

		// Composition inputs (same calls the donut tabs make).
		$lwtv_sex_raw = lwtv_plugin()->generate_station_statistics( $station, 'sexuality', 'array' );
		$lwtv_gen_raw = lwtv_plugin()->generate_station_statistics( $station, 'gender', 'array' );
		$lwtv_fmt_raw = lwtv_plugin()->generate_station_statistics( $station, 'formats', 'array' );
		$lwtv_sex_raw = is_array( $lwtv_sex_raw ) ? $lwtv_sex_raw : array();
		$lwtv_gen_raw = is_array( $lwtv_gen_raw ) ? $lwtv_gen_raw : array();
		$lwtv_fmt_raw = is_array( $lwtv_fmt_raw ) ? $lwtv_fmt_raw : array();

		// Derived facts.
		$lwtv_narr     = Overview_Factsheet::narrative( $lwtv_rank, $lwtv_first_yr, $lwtv_shows );
		$lwtv_density  = Overview_Factsheet::ratio( $lwtv_chars, $lwtv_shows );
		$lwtv_deathpct = Overview_Factsheet::death_rate( $lwtv_dead, $lwtv_chars );
		$lwtv_alive    = max( 0, $lwtv_chars - $lwtv_dead );

		// Best year (reuse the on-air series the on-air tab loads).
		$lwtv_oaraw    = lwtv_plugin()->generate_station_statistics( $station, 'on-air', 'array' );
		$lwtv_oaraw    = is_array( $lwtv_oaraw ) ? $lwtv_oaraw : array();
		$lwtv_oapoints = array();
		foreach ( $lwtv_oaraw as $lwtv_oa_item ) {
			$lwtv_oapoints[] = array(
				'year'  => (int) $lwtv_oa_item['name'],
				'count' => (int) $lwtv_oa_item['count'],
			);
		}
		$lwtv_best_yr = Overview_Factsheet::best_year( $lwtv_oapoints );

		// Tiles reuse the vibrant palette (unchanged from the old Overview).
		$lwtv_ov_cards = array(
			array(
				'variant' => 'teal',
				'label'   => __( 'Shows', 'lwtv' ),
				'count'   => $lwtv_shows,
				'svg'     => 'tv.svg',
				'icon'    => 'svg-tv',
			),
			array(
				'variant' => 'amber',
				'label'   => __( 'On Air Now', 'lwtv' ),
				'count'   => $lwtv_onair,
				'svg'     => 'satellite-signal.svg',
				'icon'    => 'svg-satellite-signal',
			),
			array(
				'variant' => 'green',
				'label'   => __( 'Characters', 'lwtv' ),
				'count'   => $lwtv_chars,
				'svg'     => 'man-woman.svg',
				'icon'    => 'svg-man-woman',
			),
			array(
				'variant' => 'rose',
				'label'   => __( 'Dead', 'lwtv' ),
				'count'   => $lwtv_dead,
				'svg'     => 'skull.svg',
				'icon'    => 'svg-skull',
			),
		);

		// Composition bars: [ key, label, mode, segments|text ]. Colour by index.
		$lwtv_seg_class = array( 'teal', 'amber', 'green', 'rose' );

		// Bars 1–3 (folded).
		$lwtv_sex_fold = Overview_Factsheet::fold_top(
			array_map(
				fn( $r ) => array(
					'label' => $r['name'],
					'count' => (int) $r['count'],
				),
				$lwtv_sex_raw
			),
			4,
			true
		);
		$lwtv_gen_fold = Overview_Factsheet::fold_top(
			array_map(
				fn( $r ) => array(
					'label' => $r['name'],
					'count' => (int) $r['count'],
				),
				$lwtv_gen_raw
			),
			4,
			false
		);
		$lwtv_fmt_fold = Overview_Factsheet::fold_top(
			array_map(
				fn( $r ) => array(
					'label' => $r['name'],
					'count' => (int) $r['count'],
				),
				$lwtv_fmt_raw
			),
			4,
			false
		);
		?>

		<!-- 1 — Masthead -->
		<div class="lwtv-fact-masthead">
			<div class="lwtv-fact-masthead-lead">
				<span class="lwtv-nation-profile-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '19' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Station Profile', 'lwtv' ); ?></span>
					<h2 class="lwtv-fact-masthead-name"><?php echo esc_html( $lwtv_name ); ?></h2>
				</div>
			</div>
			<p class="lwtv-fact-masthead-narrative">
				<?php
				if ( 'ranked' === $lwtv_narr['mode'] ) {
					printf(
						/* translators: 1: ordinal rank (e.g. 3rd), 2: first tracked year. */
						esc_html__( '%1$s busiest network on the site. Steady output since %2$s.', 'lwtv' ),
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
								/* translators: 1: year, 2: station name, 3: number of shows on air. */
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
				$lwtv_aria    = function ( $segs ) {
					$parts = array();
					foreach ( $segs as $s ) {
						$parts[] = $s['label'] . ' ' . $s['pct'] . '%';
					}
					return implode( ', ', $parts );
				};

				// Bar 1 — Sexuality (pct, has grey tail).
				$lwtv_sex_segs = $lwtv_fold_segments( $lwtv_sex_fold );
				$lwtv_sex_mode = Overview_Factsheet::finalize_bar( array_column( $lwtv_sex_segs, 'count' ), Overview_Factsheet::collapse_for_chars( $lwtv_chars ) );
				$lwtv_render_bar( __( 'Sexuality', 'lwtv' ), $lwtv_sex_mode, $lwtv_sex_segs, $lwtv_sum_pct( $lwtv_sex_segs ), $lwtv_aria( $lwtv_sex_segs ) );

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
					array(
						'label' => __( 'on air', 'lwtv' ),
						'count' => $lwtv_onair,
						'pct'   => ( $lwtv_shows > 0 ) ? round( $lwtv_onair / $lwtv_shows * 100, 1 ) : 0,
						'class' => 'amber',
					),
					array(
						'label' => __( 'finished', 'lwtv' ),
						'count' => $lwtv_finished,
						'pct'   => ( $lwtv_shows > 0 ) ? round( $lwtv_finished / $lwtv_shows * 100, 1 ) : 0,
						'class' => 'teal',
					),
				);
				$lwtv_b4_mode  = Overview_Factsheet::finalize_bar( array( $lwtv_onair, $lwtv_finished ), Overview_Factsheet::collapse_for_shows( $lwtv_shows ) );
				$lwtv_render_bar( __( 'Shows total vs on air', 'lwtv' ), $lwtv_b4_mode, $lwtv_b4_segs, $lwtv_sum_cnt( $lwtv_b4_segs ), $lwtv_aria( $lwtv_b4_segs ) );

				// Bar 5 — Alive or dead (counts).
				$lwtv_b5_segs = array(
					array(
						'label' => __( 'alive', 'lwtv' ),
						'count' => $lwtv_alive,
						'pct'   => ( $lwtv_chars > 0 ) ? round( $lwtv_alive / $lwtv_chars * 100, 1 ) : 0,
						'class' => 'green',
					),
					array(
						'label' => __( 'dead', 'lwtv' ),
						'count' => $lwtv_dead,
						'pct'   => ( $lwtv_chars > 0 ) ? round( $lwtv_dead / $lwtv_chars * 100, 1 ) : 0,
						'class' => 'rose',
					),
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
					<span class="lwtv-fact-num lwtv-fact-num--green"><?php echo esc_html( number_format_i18n( $lwtv_density, 1 ) ); ?></span>
					<div class="lwtv-fact-caption">
						<?php
						if ( null !== $lwtv_global_av ) {
							printf(
								/* translators: %s: global average characters per show. */
								esc_html__( 'characters per show, against a global average of %s', 'lwtv' ),
								esc_html( number_format_i18n( $lwtv_global_av, 1 ) )
							);
						} else {
							esc_html_e( 'characters per show', 'lwtv' );
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( null !== $lwtv_deathpct ) : ?>
				<div class="lwtv-fact">
					<span class="lwtv-fact-num lwtv-fact-num--rose"><?php echo esc_html( number_format_i18n( $lwtv_deathpct, 1 ) ); ?>%</span>
					<div class="lwtv-fact-caption">
						<?php esc_html_e( 'Of its characters have died on screen', 'lwtv' ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		break;

	case '_on-air':
		$lwtv_oaraw  = lwtv_plugin()->generate_station_statistics( $station, ltrim( $view, '_' ), 'array' );
		$lwtv_oaraw  = ( is_array( $lwtv_oaraw ) && ! empty( $lwtv_oaraw ) ) ? $lwtv_oaraw : array();
		$lwtv_points = array();
		foreach ( $lwtv_oaraw as $lwtv_oa_item ) {
			$lwtv_points[] = array(
				'year'  => (int) $lwtv_oa_item['name'],
				'count' => (int) $lwtv_oa_item['count'],
			);
		}
		$lwtv_last = ! empty( $lwtv_points ) ? end( $lwtv_points ) : array(
			'year'  => 0,
			'count' => 0,
		);

		// Best year = highest on-air count; on a tie, the most recent year (points
		// are ordered ascending, so >= lets a later equal year win).
		$lwtv_best_year  = 0;
		$lwtv_best_count = 0;
		foreach ( $lwtv_points as $lwtv_pt ) {
			if ( (int) $lwtv_pt['count'] >= $lwtv_best_count ) {
				$lwtv_best_count = (int) $lwtv_pt['count'];
				$lwtv_best_year  = (int) $lwtv_pt['year'];
			}
		}

		$lwtv_callouts = array();
		if ( $lwtv_best_count > 0 ) {
			$lwtv_callouts[] = array(
				'label' => __( 'Best Year', 'lwtv' ),
				'svg'   => 'fireworks.svg',
				'icon'  => 'svg-fireworks',
				// Raw values — the callout partial escapes the assembled text with esc_html().
				'text'  => sprintf(
					/* translators: 1: year, 2: nation name, 3: number of shows on air. */
					_n( 'In %1$s, %2$s had %3$s show on air.', 'In %1$s, %2$s had %3$s shows on air.', $lwtv_best_count, 'lwtv' ),
					(string) $lwtv_best_year,
					$lwtv_name,
					number_format_i18n( $lwtv_best_count )
				),
			);
		}

		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';
		$lwtv_oa_series = lwtv_stats_year_series( $lwtv_points, 'year', 'count' );

		$yearbars = array(
			'rows'        => $lwtv_oa_series['rows'],
			'peak_year'   => $lwtv_oa_series['peak_year'],
			'peak_count'  => $lwtv_oa_series['peak_count'],
			'stat_num'    => (int) $lwtv_last['count'],
			/* translators: %s: the latest year (4-digit, never thousands-formatted). */
			'stat_sub'    => sprintf( __( 'on air in %s', 'lwtv' ), (string) $lwtv_last['year'] ),
			'eyebrow'     => __( 'Shows On Air Per Year', 'lwtv' ),
			'headline'    => __( 'On-air over time', 'lwtv' ),
			'description' => __( 'Shows on air in each year, from the first tracked episode to today.', 'lwtv' ),
			'callouts'    => $lwtv_callouts,
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';

		$download_csv = array(
			'page'  => __( 'year', 'lwtv' ),
			/* translators: %s: station name. */
			'title' => sprintf( __( '%s: shows on air by year', 'lwtv' ), $lwtv_name ),
			'count' => count( $lwtv_oa_series['rows'] ),
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';

		break;

	case '_tropes':
		$lwtv_traw  = lwtv_plugin()->generate_station_statistics( $station, ltrim( $view, '_' ), 'array' );
		$lwtv_trows = ( is_array( $lwtv_traw ) && ! empty( $lwtv_traw ) ) ? $lwtv_traw : array();
		$ranked     = array(
			'rows'   => $lwtv_trows,
			'total'  => $lwtv_shows,
			'family' => 'characters',
			'title'  => __( 'Most common tropes', 'lwtv' ),
			'sub'    => __( 'Shows can carry several, so shares add past 100%.', 'lwtv' ),
			'svg'    => 'tag.svg',
			'icon'   => 'svg-tag',
			'base'   => '',
			'mode'   => 'share',
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
		break;

	case '_sexuality':
	case '_gender':
	case '_formats':
		$lwtv_raw  = lwtv_plugin()->generate_station_statistics( $station, ltrim( $view, '_' ), 'array' );
		$lwtv_list = ( is_array( $lwtv_raw ) && ! empty( $lwtv_raw ) ) ? $lwtv_raw : array();

		if ( '_gender' === $view ) {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 4, 'cisgender' );
			$lwtv_eyebrow                 = __( 'Character Gender', 'lwtv' );
			$lwtv_headline                = __( 'Gender identities', 'lwtv' );
			$lwtv_sub                     = __( 'characters', 'lwtv' );
		} elseif ( '_formats' === $view ) {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 5 );
			$lwtv_eyebrow                 = __( 'Show Formats', 'lwtv' );
			$lwtv_headline                = __( 'How these shows are made', 'lwtv' );
			$lwtv_sub                     = __( 'shows', 'lwtv' );
		} else {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 5 );
			$lwtv_eyebrow                 = __( 'Character Sexual Orientation ', 'lwtv' );
			$lwtv_headline                = __( 'Sexual orientations', 'lwtv' );
			$lwtv_sub                     = __( 'characters', 'lwtv' );
		}

		$donut = array(
			'segments'    => $lwtv_segs,
			'center'      => $lwtv_tot,
			'center_sub'  => $lwtv_sub,
			'eyebrow'     => $lwtv_eyebrow,
			'headline'    => $lwtv_headline,
			'description' => '',
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
		break;

	default:
		// Unreachable for valid views (all/sexuality/gender/tropes/formats/on-air
		// are all handled above) — nothing left to render here.
		break;
}
