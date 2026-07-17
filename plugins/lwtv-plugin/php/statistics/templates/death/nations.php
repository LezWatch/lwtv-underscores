<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Nations: dead characters by country.
 *
 * @package LezWatch.TV
 */

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
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Deaths By Country', 'lwtv' ); ?></p>
<?php
$ranked = array(
	'rows'   => array_slice( $dn_rows, 0, 15 ),
	'total'  => $dn_tot,
	'family' => 'characters',
	'title'  => __( 'Countries with the most on-screen deaths', 'lwtv' ),
	'sub'    => __( 'Tracks catalogue size by country, not a death rate.', 'lwtv' ),
	'svg'    => 'globe.svg',
	'icon'   => 'svg-globe',
	'base'   => '',
	'mode'   => 'share',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
