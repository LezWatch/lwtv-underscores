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
$ranked      = array(
	'rows'   => $genres_data,
	'total'  => (int) $shows_count,
	'family' => 'actors',
	'svg'    => 'theater_masks.svg',
	'icon'   => 'svg-theater-masks',
	'title'  => __( 'Genre Breakdown', 'lwtv' ),
	/* translators: %s: number of genres. */
	'sub'    => sprintf( __( '%s genres, by number of shows', 'lwtv' ), number_format_i18n( count( $genres_data ) ) ),
	'base'   => '/genre/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
