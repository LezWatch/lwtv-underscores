<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Stations: dead characters by network.
 *
 * @package LezWatch.TV
 */

$dst_raw  = lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'array' );
$dst_raw  = is_array( $dst_raw ) ? $dst_raw : array();
$dst_rows = array();
$dst_tot  = 0;
foreach ( $dst_raw as $dst_r ) {
	$dst_tot += (int) $dst_r['count'];
}
foreach ( $dst_raw as $dst_r ) {
	$dst_rows[] = array(
		'name'  => $dst_r['term_name'],
		'count' => (int) $dst_r['count'],
		'url'   => site_url( '/station/' . $dst_r['term_slug'] ),
	);
}
?>

<?php
$ranked = array(
	'rows'   => array_slice( $dst_rows, 0, 15 ),
	'total'  => $dst_tot,
	'family' => 'characters',
	'title'  => __( 'Networks with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'More shows on a network means more deaths.', 'lwtv' ),
	'svg'    => 'satellite-signal.svg',
	'icon'   => 'svg-satellite-signal',
	'base'   => '',
	'mode'   => 'share',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
