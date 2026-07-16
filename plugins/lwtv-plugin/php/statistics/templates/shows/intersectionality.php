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
$ranked     = array(
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
