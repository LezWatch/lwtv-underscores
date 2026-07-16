<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → On Air: area trendline of characters-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
$onair_data = ( is_array( $onair_raw ) && ! empty( $onair_raw ) ) ? (array) reset( $onair_raw ) : array();

$onair_points = array();
foreach ( $onair_data as $onair_row ) {
	$onair_points[] = array(
		'year'  => (int) ( $onair_row['name'] ?? 0 ),
		'count' => (int) ( $onair_row['count'] ?? 0 ),
	);
}
$onair_last = end( $onair_points ) ?: array(
	'year'  => (int) gmdate( 'Y' ),
	'count' => 0,
);

$trend = array(
	'points'       => $onair_points,
	'eyebrow'      => __( 'Characters On Air per Year', 'lwtv' ),
	'headline'     => __( 'More queer characters on screen than ever', 'lwtv' ),
	'description'  => __( 'The number of queer and trans characters on air each year climbed steadily for two decades before the recent contraction in scripted TV.', 'lwtv' ),
	'current'      => (int) $onair_last['count'],
	'current_year' => (int) $onair_last['year'],
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/trendline.php';
