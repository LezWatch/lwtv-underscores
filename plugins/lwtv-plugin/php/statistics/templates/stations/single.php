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
<div class="lwtv-nation-profile lwtv-nation-profile--vibrant bg-light">
	<div class="lwtv-nation-profile-row">
		<div class="lwtv-nation-profile-lead">
			<span class="lwtv-nation-profile-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '19' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="lwtv-nation-profile-id">
				<span class="lwtv-stats-eyebrow sexuality"><?php esc_html_e( 'Station Profile', 'lwtv' ); ?></span>
				<h2 class="lwtv-nation-profile-name"><?php echo esc_html( $lwtv_name ); ?></h2>
			</div>
		</div>
		<div class="lwtv-nation-profile-figs">
			<span><strong data-count-to="<?php echo (int) $lwtv_shows; ?>"><?php echo esc_html( number_format_i18n( $lwtv_shows ) ); ?></strong><em><?php esc_html_e( 'shows', 'lwtv' ); ?></em></span>
			<span><strong data-count-to="<?php echo (int) $lwtv_chars; ?>"><?php echo esc_html( number_format_i18n( $lwtv_chars ) ); ?></strong><em><?php esc_html_e( 'characters', 'lwtv' ); ?></em></span>
			<span class="lwtv-nation-profile-dead"><strong data-count-to="<?php echo (int) $lwtv_dead; ?>"><?php echo esc_html( number_format_i18n( $lwtv_dead ) ); ?></strong><em><?php esc_html_e( 'dead', 'lwtv' ); ?></em></span>
		</div>
	</div>
	<p class="lwtv-nation-profile-intro">
		<?php
		if ( $lwtv_onair > 0 ) {
			printf(
				/* translators: 1: on-air count, 2: station name, 3: total shows. */
				esc_html( _n( '%1$s of %2$s\'s %3$s shows is currently on air.', '%1$s of %2$s\'s %3$s shows are currently on air.', $lwtv_onair, 'lwtv' ) ),
				esc_html( number_format_i18n( $lwtv_onair ) ),
				esc_html( $lwtv_name ),
				esc_html( number_format_i18n( $lwtv_shows ) )
			);
		} else {
			/* translators: 1: station name, 2: total shows. */
			printf( esc_html__( 'None of %1$s\'s %2$s shows are currently on air.', 'lwtv' ), esc_html( $lwtv_name ), esc_html( number_format_i18n( $lwtv_shows ) ) );
		}
		echo ' ';
		esc_html_e( 'Use the tabs to break its catalogue down by sexuality, gender, tropes, formats, and shows on air.', 'lwtv' );
		?>
	</p>
</div>

<?php
switch ( $view ) {
	case '_all':
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

		// On-air share drives the score-footer meter fill (0–100).
		$lwtv_oapct = ( $lwtv_shows > 0 ) ? (int) round( $lwtv_onair / $lwtv_shows * 100 ) : 0;
		?>
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'At a Glance', 'lwtv' ); ?></p>
		<div class="lwtv-toll lwtv-toll--4">
			<?php
			foreach ( $lwtv_ov_cards as $lwtv_c ) {
				?>
				<div class="lwtv-toll-tile lwtv-toll-tile--<?php echo esc_attr( $lwtv_c['variant'] ); ?>">
					<div class="lwtv-toll-top">
						<span class="lwtv-toll-eyebrow"><?php echo esc_html( $lwtv_c['label'] ); ?></span>
						<span class="lwtv-toll-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_c['svg'], icon: $lwtv_c['icon'], max_size: '17' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
					<span class="lwtv-toll-num" data-count-to="<?php echo (int) $lwtv_c['count']; ?>"><?php echo esc_html( number_format_i18n( $lwtv_c['count'] ) ); ?></span>
				</div>
				<?php
			}
			?>
		</div>

		<div class="lwtv-metric-card bg-light">
			<p class="lwtv-stats-eyebrow"><?php esc_html_e( 'Average Show Score', 'lwtv' ); ?></p>
			<div class="lwtv-station-score">
				<div class="lwtv-station-scores">
					<span class="lwtv-station-score-num" data-count-to="<?php echo (int) round( $lwtv_score ); ?>"><?php echo esc_html( number_format_i18n( round( $lwtv_score ) ) ); ?></span>
					<span class="lwtv-station-score-sub"><?php esc_html_e( '/ 100 all shows', 'lwtv' ); ?></span>
					<span class="lwtv-station-score-div" aria-hidden="true"></span>
					<span class="lwtv-station-score-num lwtv-station-score-num--onair" data-count-to="<?php echo (int) round( $lwtv_oascore ); ?>"><?php echo esc_html( number_format_i18n( round( $lwtv_oascore ) ) ); ?></span>
					<span class="lwtv-station-score-sub"><?php esc_html_e( '/ 100 on air', 'lwtv' ); ?></span>
				</div>
				<div class="lwtv-station-meter">
					<div class="lwtv-station-meter-cap">
						<span><?php esc_html_e( 'On air', 'lwtv' ); ?></span>
						<span><?php printf( '%1$s / %2$s', esc_html( number_format_i18n( $lwtv_onair ) ), esc_html( number_format_i18n( $lwtv_shows ) ) ); ?></span>
					</div>
					<div class="lwtv-station-meter-track">
						<div class="lwtv-station-meter-fill" style="width:<?php echo (int) $lwtv_oapct; ?>%"></div>
					</div>
				</div>
			</div>
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
				// Raw values — the trendline partial escapes the assembled text with esc_html().
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
