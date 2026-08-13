<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Stations: dead characters by network, plus three standout
 * highlights (total dead count, share of networks with any death, and the
 * deadliest-by-rate network) shared with Death → Nations via
 * partials/death-taxonomy-highlights.php.
 *
 * @package LezWatch.TV
 */

$dtx_taxonomy    = 'lez_stations';
$dtx_url_base    = '/station/';
$dtx_noun_plural = __( 'networks', 'lwtv' );
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/death-taxonomy-highlights.php';

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
	'rows'   => array_slice( $dst_rows, 0, 10 ),
	'total'  => $dst_tot,
	'family' => 'characters',
	'title'  => __( 'Top Ten Networks with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'More shows on a network means more deaths.', 'lwtv' ),
	'svg'    => 'satellite-signal.svg',
	'icon'   => 'svg-satellite-signal',
	'base'   => '',
	'mode'   => 'share',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';

$download_csv = array(
	'page'  => __( 'network', 'lwtv' ),
	'title' => __( 'Deaths by network', 'lwtv' ),
	'count' => count( $dst_raw ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';

