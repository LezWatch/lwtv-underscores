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

$tropes_raw = lwtv_plugin()->generate_shows_statistics( 'array', 'tropes' );
$ranked     = array(
	'rows'    => ( is_array( $tropes_raw ) && ! empty( $tropes_raw ) ) ? (array) reset( $tropes_raw ) : array(),
	'total'   => (int) $shows_count,
	'family'  => 'characters',
	'eyebrow' => __( 'Trope Breakdown', 'lwtv' ),
	'base'    => '/trope/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
