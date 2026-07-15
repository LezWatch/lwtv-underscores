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

$genres_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'genres' );
$ranked     = array(
	'rows'    => ( is_array( $genres_raw ) && ! empty( $genres_raw ) ) ? (array) reset( $genres_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'actors',
	'eyebrow' => __( 'Genre Breakdown', 'lwtv' ),
	'base'    => '/genre/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
