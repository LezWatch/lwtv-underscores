<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Gender: donut (grey cisgender + trans/non-binary ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

$gen_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $actor_count;

$gen_cis_slugs = array( 'cis-woman', 'cis-man', 'cisgender' );
$gen_cis       = 0;
foreach ( $gen_cis_slugs as $gen_cis_slug ) {
	if ( isset( $gen_data[ $gen_cis_slug ] ) ) {
		$gen_cis += (int) $gen_data[ $gen_cis_slug ]['count'];
		unset( $gen_data[ $gen_cis_slug ] );
	}
}

// Remaining = trans / non-binary / unknown; ramp the top 4, fold the rest into "Other".
uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$gen_ramp     = array( 'dkpink', 'pink', 'mid', 'ltpink' );
$gen_segments = array(
	array(
		'label' => __( 'Cisgender', 'lwtv' ),
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$gen_named    = $gen_cis;
$gen_i        = 0;
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
	'eyebrow'     => __( 'Actor Gender Identity', 'lwtv' ),
	'headline'    => __( 'Nine in ten actors are cisgender', 'lwtv' ),
	'description' => __( 'Trans and non-binary actors remain a small share of the total — a figure worth watching as casting for trans and non-binary roles evolves.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
