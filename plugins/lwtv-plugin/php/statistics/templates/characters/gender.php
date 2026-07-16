<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Gender: donut (grey cisgender + raspberry-ramp minorities).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$gen_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $character_count;

$gen_cis      = isset( $gen_data['cisgender'] ) ? (int) $gen_data['cisgender']['count'] : 0;
$gen_cis_name = isset( $gen_data['cisgender'] ) ? $gen_data['cisgender']['name'] : __( 'Cisgender', 'lwtv' );
unset( $gen_data['cisgender'] );

$gen_segments = array(
	array(
		'label' => $gen_cis_name,
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);

uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$gen_ramp  = array( 'dkpink', 'pink', 'mid', 'ltpink' );
$gen_named = $gen_cis;
$gen_i     = 0;
foreach ( $gen_data as $gen_row ) {
	if ( $gen_i >= 4 || (int) $gen_row['count'] <= 0 ) {
		break;
	}
	$gen_count      = (int) $gen_row['count'];
	$gen_named     += $gen_count;
	$gen_segments[] = array(
		'label' => $gen_row['name'],
		'count' => $gen_count,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_count / $gen_total ) * 100, 1 ) : 0,
		'class' => $gen_ramp[ $gen_i ],
	);
	++$gen_i;
}
$gen_other = max( 0, $gen_total - $gen_named );
if ( $gen_other > 0 ) {
	$gen_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $gen_other,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_other / $gen_total ) * 100, 1 ) : 0,
		'class' => 'mid2',
	);
}

$donut = array(
	'segments'    => $gen_segments,
	'center'      => $gen_cis,
	'center_sub'  => __( 'cisgender', 'lwtv' ),
	'eyebrow'     => __( 'Gender Identity', 'lwtv' ),
	'headline'    => __( 'Most characters are cisgender', 'lwtv' ),
	'description' => __( 'Cisgender characters dominate, but the database tracks a growing range of trans, non-binary and genderqueer identities.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
