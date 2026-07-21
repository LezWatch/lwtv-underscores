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

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';
$onair_series = lwtv_stats_year_series( $onair_points, 'year', 'count' );

$yearbars = array(
	'rows'        => $onair_series['rows'],
	'peak_year'   => $onair_series['peak_year'],
	'peak_count'  => $onair_series['peak_count'],
	'stat_num'    => (int) $onair_last['count'],
	/* translators: %s: the latest year (4-digit, never thousands-formatted). */
	'stat_sub'    => sprintf( __( 'on air in %s', 'lwtv' ), (string) $onair_last['year'] ),
	'eyebrow'     => __( 'Shows On Air per Year', 'lwtv' ),
	'headline'    => __( 'More queer shows are on air than ever', 'lwtv' ),
	'description' => $description,
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';

$download_csv = array(
	'page'  => __( 'year', 'lwtv' ),
	'title' => __( 'Shows on air, by year', 'lwtv' ),
	'count' => count( $onair_series['rows'] ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';
