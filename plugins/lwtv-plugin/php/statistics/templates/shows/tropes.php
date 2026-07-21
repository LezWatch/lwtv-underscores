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

$tropes_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'tropes' );
$tropes_data = ( is_array( $tropes_raw ) && ! empty( $tropes_raw ) ) ? (array) reset( $tropes_raw ) : array();

// Callouts: average + median tropes per show (across shows that have at least one).
$tropes_stats  = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_tropes', array( 'none' ) );
$lwtv_callouts = array();
if ( (int) $tropes_stats['shows'] > 0 ) {
	$tropes_avg = (float) $tropes_stats['average'];
	$tropes_med = (float) $tropes_stats['median'];

	$lwtv_callouts[] = array(
		'label' => __( 'Average per show', 'lwtv' ),
		'icon'  => 'chart-bar.svg',
		/* translators: %s: average number of tropes per show (one decimal). */
		'text'  => sprintf( __( 'The average show carries %s tropes.', 'lwtv' ), number_format_i18n( $tropes_avg, 1 ) ),
	);

	if ( floor( $tropes_med ) === $tropes_med ) {
		/* translators: %s: median number of tropes per show. */
		$tropes_med_text = sprintf( _n( 'The typical show has %s trope.', 'The typical show has %s tropes.', (int) $tropes_med, 'lwtv' ), number_format_i18n( $tropes_med ) );
	} else {
		/* translators: %s: median number of tropes per show (one decimal). */
		$tropes_med_text = sprintf( __( 'The typical show has %s tropes.', 'lwtv' ), number_format_i18n( $tropes_med, 1 ) );
	}
	$lwtv_callouts[] = array(
		'label' => __( 'Median per show', 'lwtv' ),
		'icon'  => 'scales.svg',
		'text'  => $tropes_med_text,
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

$ranked = array(
	'rows'   => $tropes_data,
	'total'  => (int) $shows_count,
	'family' => 'characters',
	'svg'    => 'tag.svg',
	'icon'   => 'svg-tag',
	'title'  => __( 'Trope Breakdown', 'lwtv' ),
	/* translators: %s: number of tropes. */
	'sub'    => sprintf( __( '%s tropes, by number of shows', 'lwtv' ), number_format_i18n( count( $tropes_data ) ) ),
	'base'   => '/trope/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
