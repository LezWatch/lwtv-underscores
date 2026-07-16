<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Worth It: donut of worth-it ratings (semantic).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$worth_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'worth-it' );
$worth_data  = ( is_array( $worth_raw ) && ! empty( $worth_raw ) ) ? (array) reset( $worth_raw ) : array();
$worth_total = (int) $shows_count;

$worth_order = array(
	'yes' => array( __( 'Yes', 'lwtv' ), 'green' ),
	'meh' => array( __( 'Meh', 'lwtv' ), 'amber' ),
	'no'  => array( __( 'No', 'lwtv' ), 'red' ),
	'tbd' => array( __( 'TBD', 'lwtv' ), 'grey' ),
);

$worth_segments    = array();
$worth_yes         = 0;
$worth_yes_percent = 0;
$worth_no_percent  = 0;
foreach ( $worth_order as $worth_key => $worth_meta ) {
	$worth_count   = isset( $worth_data[ $worth_key ] ) ? (int) $worth_data[ $worth_key ]['count'] : 0;
	$worth_percent = ( $worth_total > 0 ) ? round( ( $worth_count / $worth_total ) * 100, 1 ) : 0;
	if ( 'yes' === $worth_key ) {
		$worth_yes         = $worth_count;
		$worth_yes_percent = $worth_percent;
	}
	if ( 'no' === $worth_key ) {
		$worth_no_percent = $worth_percent;
	}
	$worth_segments[] = array(
		'label' => $worth_meta[0],
		'count' => $worth_count,
		'pct'   => $worth_percent,
		'class' => $worth_meta[1],
	);
}

$donut = array(
	'segments'    => $worth_segments,
	'center'      => $worth_yes,
	'center_sub'  => __( 'rated “Yes”', 'lwtv' ),
	'eyebrow'     => __( 'Worth It Ratings', 'lwtv' ),
	// translators: %s is the percentage of shows we think are worth watching.
	'headline'    => sprintf( __( 'Over half (%s%%) are a clear yes', 'lwtv' ), $worth_yes_percent ),
	'description' => __( 'Our editors rate every show. Roughly one in ten is a hard "no." The rest sit somewhere in the middle or await review.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
