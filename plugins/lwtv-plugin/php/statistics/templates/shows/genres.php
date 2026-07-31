<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Genres: ranked bars (amber). Shares add up past 100% (multi-value taxonomy).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$genres_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'genres' );
$genres_data = ( is_array( $genres_raw ) && ! empty( $genres_raw ) ) ? (array) reset( $genres_raw ) : array();

// Callouts: average + median genres per show (across shows that have at least one).
$genres_stats  = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_genres' );
$lwtv_callouts = array();
if ( (int) $genres_stats['shows'] > 0 ) {
	$genres_avg = (float) $genres_stats['average'];
	$genres_med = (float) $genres_stats['median'];

	$lwtv_callouts[] = array(
		'label'  => __( 'Average per show', 'lwtv' ),
		'icon'   => 'chart-bar.svg',
		/* translators: %s: average number of genres per show (one decimal). */
		'text'   => sprintf( __( 'The average show spans %s genres.', 'lwtv' ), number_format_i18n( $genres_avg, 1 ) ),
		'family' => 'genres',
	);

	if ( floor( $genres_med ) === $genres_med ) {
		/* translators: %s: median number of genres per show. */
		$genres_med_text = sprintf( _n( 'The typical show has %s genre.', 'The typical show has %s genres.', (int) $genres_med, 'lwtv' ), number_format_i18n( $genres_med ) );
	} else {
		/* translators: %s: median number of genres per show (one decimal). */
		$genres_med_text = sprintf( __( 'The typical show has %s genres.', 'lwtv' ), number_format_i18n( $genres_med, 1 ) );
	}
	$lwtv_callouts[] = array(
		'label'  => __( 'Median per show', 'lwtv' ),
		'icon'   => 'scales.svg',
		'text'   => $genres_med_text,
		'family' => 'genres',
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

$ranked = array(
	'rows'   => $genres_data,
	'total'  => (int) $shows_count,
	'family' => 'genres',
	'svg'    => 'theater_masks.svg',
	'icon'   => 'svg-theater-masks',
	'title'  => __( 'Genre Breakdown', 'lwtv' ),
	/* translators: %s: number of genres. */
	'sub'    => sprintf( __( '%s genres, by number of shows', 'lwtv' ), number_format_i18n( count( $genres_data ) ) ),
	'base'   => '/genre/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
