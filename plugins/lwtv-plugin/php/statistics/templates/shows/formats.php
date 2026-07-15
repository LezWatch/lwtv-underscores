<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Formats: donut of format breakdown (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$formats_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'formats' );
$formats_data  = ( is_array( $formats_raw ) && ! empty( $formats_raw ) ) ? (array) reset( $formats_raw ) : array();
$formats_total = (int) $shows_count;

// Raspberry ramp classes, darkest (largest) first.
$formats_ramp = array( 'dkpink', 'pink', 'mid', 'ltpink', 'ltpink' );

$formats_segments = array();
$formats_i        = 0;
foreach ( $formats_data as $formats_row ) {
	$formats_count      = (int) $formats_row['count'];
	$formats_segments[] = array(
		'label' => $formats_row['name'],
		'count' => $formats_count,
		'pct'   => ( $formats_total > 0 ) ? round( ( $formats_count / $formats_total ) * 100, 1 ) : 0,
		'class' => $formats_ramp[ min( $formats_i, count( $formats_ramp ) - 1 ) ],
	);
	++$formats_i;
}

// Headline from the leading slice.
$formats_lead = $formats_segments[0] ?? array( 'pct' => 0 );
$formats_in10 = ( $formats_lead['pct'] > 0 ) ? (int) round( $formats_lead['pct'] / 10 ) : 0;

$donut = array(
	'segments'    => $formats_segments,
	'center'      => $formats_total,
	'center_sub'  => __( 'shows', 'lwtv' ),
	'eyebrow'     => __( 'Format Breakdown', 'lwtv' ),
	/* translators: %d: "N in ten" figure for the leading format. */
	'headline'    => ( $formats_in10 > 0 ) ? sprintf( __( '%d in ten are full TV series', 'lwtv' ), $formats_in10 ) : __( 'Format breakdown', 'lwtv' ),
	'description' => __( 'Feature films and short-form web series make up most of the rest; true mini-series stay rare.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
