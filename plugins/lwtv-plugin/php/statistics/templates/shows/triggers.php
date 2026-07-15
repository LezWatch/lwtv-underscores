<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Triggers: donut of trigger-warning severity.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$trig_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'triggers' );
$trig_data  = ( is_array( $trig_raw ) && ! empty( $trig_raw ) ) ? (array) reset( $trig_raw ) : array();
$trig_total = (int) $shows_count;

$trig_order = array(
	'high'   => array( __( 'High', 'lwtv' ), 'red' ),
	'medium' => array( __( 'Medium', 'lwtv' ), 'sev-med' ),
	'low'    => array( __( 'Low', 'lwtv' ), 'sev-low' ),
);

$trig_segments = array();
$trig_sum      = 0;
foreach ( $trig_order as $trig_key => $trig_meta ) {
	$trig_count      = isset( $trig_data[ $trig_key ] ) ? (int) $trig_data[ $trig_key ]['count'] : 0;
	$trig_sum       += $trig_count;
	$trig_segments[] = array(
		'label' => $trig_meta[0],
		'count' => $trig_count,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_count / $trig_total ) * 100, 1 ) : 0,
		'class' => $trig_meta[1],
	);
}
$trig_none = max( 0, $trig_total - $trig_sum );
array_unshift(
	$trig_segments,
	array(
		'label' => __( 'None', 'lwtv' ),
		'count' => $trig_none,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_none / $trig_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	)
);

$donut = array(
	'segments'    => $trig_segments,
	'center'      => $trig_none,
	'center_sub'  => __( 'no warning', 'lwtv' ),
	'eyebrow'     => __( 'Trigger Warnings', 'lwtv' ),
	'headline'    => __( 'About half carry no warning at all', 'lwtv' ),
	'description' => __( 'Where a show does carry a content warning, it is most often a low-severity note rather than a high one.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
