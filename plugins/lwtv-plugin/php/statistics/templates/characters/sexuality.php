<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Sexuality: donut (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$sex_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $character_count;

uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$sex_ramp     = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );
$sex_segments = array();
$sex_named    = 0;
$sex_i        = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 5 || (int) $sex_row['count'] <= 0 ) {
		break;
	}
	$sex_count      = (int) $sex_row['count'];
	$sex_named     += $sex_count;
	$sex_segments[] = array(
		'label' => $sex_row['name'],
		'count' => $sex_count,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_count / $sex_total ) * 100, 1 ) : 0,
		'class' => $sex_ramp[ $sex_i ],
	);
	++$sex_i;
}
$sex_other = max( 0, $sex_total - $sex_named );
if ( $sex_other > 0 ) {
	$sex_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $sex_other,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_other / $sex_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	);
}

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'characters', 'lwtv' ),
	'eyebrow'     => __( 'Sexual Orientation', 'lwtv' ),
	'headline'    => __( 'Two in three are lesbian or bisexual', 'lwtv' ),
	'description' => __( 'Lesbian and bisexual characters make up the bulk of the characters.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
