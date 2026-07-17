<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * All-stations statistics: counters + ranked leaderboard.
 *
 * @package LezWatch.TV
 *
 * @var array $all_stations_data
 * @var array $character_counts
 * @var array $show_counts
 * @var int   $all_shows_count
 * @var int   $count
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Stations with at least one show, ranked by show count (desc).
$lwtv_ranked = array();
foreach ( $all_stations_data as $lwtv_slug => $lwtv_data ) {
	if ( (int) $lwtv_data['count'] > 0 ) {
		$lwtv_ranked[ $lwtv_slug ] = $lwtv_data;
	}
}
uasort( $lwtv_ranked, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

$lwtv_station_total = count( $lwtv_ranked );

// Derived counters (compute, don't store).
$lwtv_depth = 0; // stations with >= 10 shows.
foreach ( $lwtv_ranked as $lwtv_data ) {
	if ( (int) $lwtv_data['count'] >= 10 ) {
		++$lwtv_depth;
	}
}

// Biggest platform = the top station's share of all shows.
$lwtv_top_count = ! empty( $lwtv_ranked ) ? (int) reset( $lwtv_ranked )['count'] : 0;
$lwtv_top_name  = ! empty( $lwtv_ranked ) ? reset( $lwtv_ranked )['name'] : '';
$lwtv_topshare  = ( $all_shows_count > 0 ) ? round( ( $lwtv_top_count / $all_shows_count ) * 100 ) : 0;

// New since 2020 = stations whose earliest show started 2020 or later.
$lwtv_first_years = ( new Build_Taxonomy_Optimized() )->get_bulk_first_years( 'lez_stations', array_keys( $lwtv_ranked ) );
$lwtv_new_2020    = 0;
foreach ( $lwtv_ranked as $lwtv_slug => $lwtv_data ) {
	$lwtv_fy = $lwtv_first_years[ ltrim( $lwtv_slug, '_' ) ] ?? 0;
	if ( $lwtv_fy >= 2020 ) {
		++$lwtv_new_2020;
	}
}

$lwtv_cards = array(
	array(
		'family'  => 'shows',
		'label'   => __( 'Stations', 'lwtv' ),
		'count'   => $lwtv_station_total,
		'suffix'  => '',
		'caption' => __( 'Networks & platforms tracked', 'lwtv' ),
		'svg'     => 'satellite-signal.svg',
		'icon'    => 'svg-satellite-signal',
	),
	array(
		'family'  => 'characters',
		'label'   => __( 'Have 10+ Shows', 'lwtv' ),
		'count'   => $lwtv_depth,
		'suffix'  => '',
		'caption' => __( 'A real depth of catalogue', 'lwtv' ),
		'svg'     => 'library.svg',
		'icon'    => 'svg-library',
	),
	array(
		'family'  => 'actors',
		'label'   => __( 'Biggest Platform', 'lwtv' ),
		'count'   => $lwtv_topshare,
		'suffix'  => '%',
		/* translators: %s: top station name. */
		'caption' => $lwtv_top_name ? sprintf( __( '%s leads the pack.', 'lwtv' ), $lwtv_top_name ) : __( 'No single network dominates', 'lwtv' ),
		'svg'     => 'location-target.svg',
		'icon'    => 'svg-location-target',
	),
	array(
		'family'  => 'nations-new',
		'label'   => __( 'New Since 2020', 'lwtv' ),
		'count'   => $lwtv_new_2020,
		'suffix'  => '',
		'caption' => __( 'Aired their first queer show', 'lwtv' ),
		'svg'     => 'graph-line.svg',
		'icon'    => 'svg-graph-line',
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Across the Dial', 'lwtv' ); ?></p>
<div class="lwtv-metric-grid lwtv-metric-grid--4">
	<?php
	foreach ( $lwtv_cards as $lwtv_card ) {
		?>
		<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $lwtv_card['family'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $lwtv_card['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_card['svg'], icon: $lwtv_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $lwtv_card['count']; ?>" data-count-suffix="<?php echo esc_attr( $lwtv_card['suffix'] ); ?>"><?php echo esc_html( number_format_i18n( $lwtv_card['count'] ) . $lwtv_card['suffix'] ); ?></span>
			<span class="lwtv-metric-caption"><?php echo esc_html( $lwtv_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
// Ranked station leaderboard (shared partial).
$leaderboard_rows     = $lwtv_ranked;
$leaderboard_chars    = $character_counts;
$leaderboard_all      = (int) $all_shows_count;
$leaderboard_base     = '/statistics/stations/';
$leaderboard_qvar     = 'station';
$leaderboard_title    = __( 'Stations by number of shows', 'lwtv' );
$leaderboard_col      = __( 'Station', 'lwtv' );
$leaderboard_items    = __( 'stations', 'lwtv' );
$leaderboard_icon_svg = 'satellite-signal.svg';
$leaderboard_icon_fa  = 'svg-satellite-signal';
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/leaderboard.php';
