<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → On Air: area trendline of shows-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'on-air' );
$onair_data = ( is_array( $onair_raw ) && ! empty( $onair_raw ) ) ? (array) reset( $onair_raw ) : array();

$most_year = array(
	'name'  => 2016,
	'count' => 0,
);

$onair_points = array();
foreach ( $onair_data as $onair_row ) {
	$onair_points[] = array(
		'year'  => (int) ( $onair_row['name'] ?? 0 ),
		'count' => (int) ( $onair_row['count'] ?? 0 ),
	);

	if ( $onair_row['count'] > $most_year['count'] ) {
		$most_year['count'] = $onair_row['count'];
		$most_year['name']  = $onair_row['name'];
	}
}

$onair_last = end( $onair_points ) ?: array(
	'year'  => (int) gmdate( 'Y' ),
	'count' => 0,
);

// translators: %1$d is the year with most shows, %2$d is the number of shows it has.
$description = sprintf( __( 'The count climbed steadily for two decades and peaked in %1$d (with %2$d); the latest dip reflects the current contraction in scripted TV', 'lwtv' ), $most_year['name'], $most_year['count'] );

$trend = array(
	'points'       => $onair_points,
	'eyebrow'      => __( 'Shows On Air per Year', 'lwtv' ),
	'headline'     => __( 'More queer shows are on air than ever', 'lwtv' ),
	'description'  => $description,
	'current'      => (int) $onair_last['count'],
	'current_year' => (int) $onair_last['year'],
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/trendline.php';
