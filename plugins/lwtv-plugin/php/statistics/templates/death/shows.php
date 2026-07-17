<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Shows: how many shows kill all / some / no queer characters.
 *
 * @package LezWatch.TV
 */

$ds_data  = lwtv_plugin()->generate_dead_statistics( 'shows', 'per-show', 'array' );
$ds_map   = array(
	'no_dead'   => array( __( 'No deaths', 'lwtv' ), 'green' ),
	'some_dead' => array( __( 'Some deaths', 'lwtv' ), 'amber' ),
	'all_dead'  => array( __( 'All die', 'lwtv' ), 'red' ),
);
$ds_total = 0;
foreach ( $ds_data as $ds_row ) {
	$ds_total += (int) $ds_row['count'];
}
$ds_seg = array();
foreach ( $ds_map as $ds_key => $ds_meta ) {
	$ds_c     = isset( $ds_data[ $ds_key ] ) ? (int) $ds_data[ $ds_key ]['count'] : 0;
	$ds_seg[] = array(
		'label' => $ds_meta[0],
		'count' => $ds_c,
		'pct'   => ( $ds_total > 0 ) ? round( ( $ds_c / $ds_total ) * 100, 1 ) : 0,
		'class' => $ds_meta[1],
	);
}
$ds_alldead = isset( $ds_data['all_dead'] ) ? (int) $ds_data['all_dead']['count'] : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Which Shows Kill', 'lwtv' ); ?></p>
<?php
$donut = array(
	'segments'    => $ds_seg,
	'center'      => $ds_alldead,
	'center_sub'  => __( 'kill everyone', 'lwtv' ),
	'eyebrow'     => __( 'Deaths Per Show', 'lwtv' ),
	'headline'    => __( 'Most shows keep their queer characters alive', 'lwtv' ),
	'description' => __( 'Raw per-show death counts track how large a show\'s cast is — a big ensemble will show more deaths than a two-hander.', 'lwtv' ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
