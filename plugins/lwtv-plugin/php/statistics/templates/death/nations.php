<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Nations: dead characters by country, plus three standout
 * highlights (total dead count, share of countries with any death, and the
 * deadliest-by-rate country) shared with Death → Stations via
 * partials/death-taxonomy-highlights.php.
 *
 * @package LezWatch.TV
 */

$dtx_taxonomy    = 'lez_country';
$dtx_url_base    = '/country/';
$dtx_noun_plural = __( 'countries', 'lwtv' );
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/death-taxonomy-highlights.php';

$dn_raw  = lwtv_plugin()->generate_dead_statistics( 'shows', 'nations', 'array' );
$dn_raw  = is_array( $dn_raw ) ? $dn_raw : array();
$dn_rows = array();
$dn_tot  = 0;
foreach ( $dn_raw as $dn_r ) {
	$dn_tot += (int) $dn_r['count'];
}
foreach ( $dn_raw as $dn_r ) {
	$dn_rows[] = array(
		'name'  => $dn_r['term_name'],
		'count' => (int) $dn_r['count'],
		'url'   => site_url( '/country/' . $dn_r['term_slug'] ),
	);
}
?>

<?php
$ranked = array(
	'rows'   => array_slice( $dn_rows, 0, 10 ),
	'total'  => $dn_tot,
	'family' => 'characters',
	'title'  => __( 'Top Ten Countries with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'The more shows in a nation, the more death. It\'s just math.', 'lwtv' ),
	'svg'    => 'globe.svg',
	'icon'   => 'svg-globe',
	'base'   => '',
	'mode'   => 'share',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';

$download_csv = array(
	'page'  => __( 'country', 'lwtv' ),
	'title' => __( 'Deaths by country', 'lwtv' ),
	'count' => count( $dn_raw ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';

