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
		'label'  => __( 'Shows with intersections', 'lwtv' ),
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of all shows carrying at least one intersection (one decimal). */
		'text'   => sprintf( __( '%s%% of all shows carry at least one intersection.', 'lwtv' ), number_format_i18n( $inter_pct, 1 ) ),
		'family' => 'intersections',
	);

	$lwtv_callouts[] = array(
		'label'  => __( 'Average per show', 'lwtv' ),
		'icon'   => 'chart-bar.svg',
		/* translators: %s: average number of intersections per show that has at least one (one decimal). */
		'text'   => sprintf( __( 'Shows with intersections span %s of them on average.', 'lwtv' ), number_format_i18n( $inter_avg, 1 ) ),
		'family' => 'intersections',
	);

	if ( floor( $inter_med ) === $inter_med ) {
		/* translators: %s: median number of intersections per show. */
		$inter_med_text = sprintf( _n( 'The typical such show has %s intersection.', 'The typical such show has %s intersections.', (int) $inter_med, 'lwtv' ), number_format_i18n( $inter_med ) );
	} else {
		/* translators: %s: median number of intersections per show (one decimal). */
		$inter_med_text = sprintf( __( 'The typical such show has %s intersections.', 'lwtv' ), number_format_i18n( $inter_med, 1 ) );
	}
	$lwtv_callouts[] = array(
		'label'  => __( 'Median per show', 'lwtv' ),
		'icon'   => 'scales.svg',
		'text'   => $inter_med_text,
		'family' => 'intersections',
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

$ranked = array(
	'rows'   => $inter_data,
	'total'  => (int) $shows_count,
	'family' => 'intersections',
	'svg'    => 'statue-of-liberty.svg',
	'icon'   => 'svg-statue-of-liberty',
	'title'  => __( 'Intersectionality Breakdown', 'lwtv' ),
	/* translators: %s: number of intersections. */
	'sub'    => sprintf( __( '%s intersections, by number of shows', 'lwtv' ), number_format_i18n( count( $inter_data ) ) ),
	'base'   => '',
	'mode'   => 'lollipop',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';

// Common pairings: which intersections appear together on the same show.
// Pure counting lives in Build\Intersection_Pairs (unit-tested); the term
// map and names here are the WP glue.
$pair_map = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_shows', 'lez_intersections' );
$pairs    = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs(
	\LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $pair_map ),
	8,
	2
);

if ( ! empty( $pairs ) ) {
	$pair_names = array();
	$pair_terms = get_terms(
		array(
			'taxonomy'   => 'lez_intersections',
			'hide_empty' => true,
		)
	);
	foreach ( $pair_terms as $pair_term ) {
		$pair_names[ $pair_term->slug ] = $pair_term->name;
	}

	$pair_rows = array();
	foreach ( $pairs as $pair ) {
		list( $pair_a, $pair_b ) = $pair['slugs'];
		// Link each pairing to the shows archive with both facet values selected.
		$pair_rows[ $pair_a . '+' . $pair_b ] = array(
			'name'  => ( $pair_names[ $pair_a ] ?? $pair_a ) . ' + ' . ( $pair_names[ $pair_b ] ?? $pair_b ),
			'count' => (int) $pair['count'],
			'url'   => site_url( '/shows/?fwp_show_intersectionality=' . rawurlencode( $pair_a . ',' . $pair_b ) ),
		);
	}

	$ranked = array(
		'rows'   => $pair_rows,
		'total'  => (int) $shows_count,
		'family' => 'intersections',
		'svg'    => 'vest-patches.svg',
		'icon'   => 'svg-vest-patches',
		'title'  => __( 'Common Pairings', 'lwtv' ),
		'sub'    => __( 'Intersections that appear together on the same show, by number of shows', 'lwtv' ),
		'base'   => '',
		'mode'   => 'lollipop',
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
}
