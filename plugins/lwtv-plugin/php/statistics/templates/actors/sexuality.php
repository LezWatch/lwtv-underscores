<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Sexuality: donut (grey straight + queer ramp + unknown).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

$sex_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $actor_count;

$sex_straight = isset( $sex_data['heterosexual'] ) ? (int) $sex_data['heterosexual']['count'] : 0;
$sex_unknown  = isset( $sex_data['unknown'] ) ? (int) $sex_data['unknown']['count'] : 0;
unset( $sex_data['heterosexual'], $sex_data['unknown'] );

// Remaining = queer orientations; rank and ramp the top 4, fold the rest into "Other".
uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$sex_ramp     = array( 'dkpink', 'pink', 'mid', 'mid2' );
$sex_segments = array(
	array(
		'label' => __( 'Straight', 'lwtv' ),
		'count' => $sex_straight,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_straight / $sex_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$sex_named    = $sex_straight + $sex_unknown;
$sex_i        = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 4 || (int) $sex_row['count'] <= 0 ) {
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
		'class' => 'ltpink',
	);
}
$sex_segments[] = array(
	'label' => __( 'Unknown', 'lwtv' ),
	'count' => $sex_unknown,
	'pct'   => ( $sex_total > 0 ) ? round( ( $sex_unknown / $sex_total ) * 100, 1 ) : 0,
	'class' => 'bordergrey',
);

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'actors', 'lwtv' ),
	'eyebrow'     => __( 'Actor Sexual Orientation', 'lwtv' ),
	'headline'    => __( 'More than half the actors are straight', 'lwtv' ),
	'description' => __( 'Queer roles are still mostly played by straight actors, with openly-LGBTQ+ performers making up the rest — and a large share whose orientation is unrecorded.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
