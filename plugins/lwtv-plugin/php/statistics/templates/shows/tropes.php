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
$ranked      = array(
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
