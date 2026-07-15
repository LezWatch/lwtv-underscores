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

$inter_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'intersections' );
$ranked    = array(
	'rows'    => is_array( $inter_raw ) ? (array) reset( $inter_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'shows',
	'eyebrow' => __( 'Intersectionality Breakdown', 'lwtv' ),
	'base'    => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
