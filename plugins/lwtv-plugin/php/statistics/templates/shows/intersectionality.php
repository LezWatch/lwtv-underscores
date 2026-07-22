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

$inter_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'intersections' );
$inter_data = ( is_array( $inter_raw ) && ! empty( $inter_raw ) ) ? (array) reset( $inter_raw ) : array();

// Callouts: coverage, then average + median intersections per show (across shows that have at least one).
$inter_stats   = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_intersections' );
$lwtv_callouts = array();
if ( (int) $inter_stats['shows'] > 0 && (int) $shows_count > 0 ) {
	$inter_with = (int) $inter_stats['shows'];
	$inter_pct  = round( ( $inter_with / (int) $shows_count ) * 100, 1 );
	$inter_avg  = (float) $inter_stats['average'];
	$inter_med  = (float) $inter_stats['median'];

	$lwtv_callouts[] = array(
		'label' => __( 'Shows with intersections', 'lwtv' ),
		'icon'  => 'chart-pie.svg',
		/* translators: %s: percentage of all shows carrying at least one intersection (one decimal). */
		'text'  => sprintf( __( '%s%% of all shows carry at least one intersection.', 'lwtv' ), number_format_i18n( $inter_pct, 1 ) ),
	);

	$lwtv_callouts[] = array(
		'label' => __( 'Average per show', 'lwtv' ),
		'icon'  => 'chart-bar.svg',
		/* translators: %s: average number of intersections per show that has at least one (one decimal). */
		'text'  => sprintf( __( 'Shows with intersections span %s of them on average.', 'lwtv' ), number_format_i18n( $inter_avg, 1 ) ),
	);

	if ( floor( $inter_med ) === $inter_med ) {
		/* translators: %s: median number of intersections per show. */
		$inter_med_text = sprintf( _n( 'The typical such show has %s intersection.', 'The typical such show has %s intersections.', (int) $inter_med, 'lwtv' ), number_format_i18n( $inter_med ) );
	} else {
		/* translators: %s: median number of intersections per show (one decimal). */
		$inter_med_text = sprintf( __( 'The typical such show has %s intersections.', 'lwtv' ), number_format_i18n( $inter_med, 1 ) );
	}
	$lwtv_callouts[] = array(
		'label' => __( 'Median per show', 'lwtv' ),
		'icon'  => 'scales.svg',
		'text'  => $inter_med_text,
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

$ranked = array(
	'rows'   => $inter_data,
	'total'  => (int) $shows_count,
	'family' => 'shows',
	'svg'    => 'user-heart.svg',
	'icon'   => 'svg-user',
	'title'  => __( 'Intersectionality Breakdown', 'lwtv' ),
	/* translators: %s: number of intersections. */
	'sub'    => sprintf( __( '%s intersections, by number of shows', 'lwtv' ), number_format_i18n( count( $inter_data ) ) ),
	'base'   => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
