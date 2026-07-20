<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → On Air: area trendline of characters-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
$onair_data  = ( is_array( $onair_raw ) && ! empty( $onair_raw ) ) ? (array) reset( $onair_raw ) : array();
$onair_most  = array(
	'year'  => 0,
	'count' => 0,
);
$onair_least = array(
	'year'  => 0,
	'count' => 0,
);

$onair_points = array();
foreach ( $onair_data as $onair_row ) {
	$onair_points[] = array(
		'year'  => (int) ( $onair_row['name'] ?? 0 ),
		'count' => (int) ( $onair_row['count'] ?? 0 ),
	);

	if ( $onair_row['count'] > $onair_most['count'] ) {
		$onair_most['count'] = $onair_row['count'];
		$onair_most['year']  = $onair_row['name'];
	}

	if ( $onair_row['count'] < $onair_most['count'] ) {
		$onair_least['count'] = $onair_row['count'];
		$onair_least['year']  = $onair_row['name'];
	}
}
$onair_last = end( $onair_points ) ?: array(
	'year'  => (int) gmdate( 'Y' ),
	'count' => 0,
);

$lwtv_callouts = array(
	array(
		'label' => __( 'Best Year', 'lwtv' ),
		'svg'   => 'fireworks.svg',
		'icon'  => 'svg-fireworks',
		'text'  => sprintf(
			/* translators: 1: year, 2: number of characters on air. */
			_n( 'In %1$s, there was %2$s character on air.', 'In %1$s, there were %2$s characters on air.', $onair_most['count'], 'lwtv' ),
			(string) $onair_most['year'],
			number_format_i18n( $onair_most['count'] )
		),
	),
	array(
		'label' => __( 'Worst Year', 'lwtv' ),
		'svg'   => 'nessie.svg',
		'icon'  => 'svg-nessie',
		'text'  => sprintf(
			/* translators: 1: year, 2: number of characters on air. */
			_n( 'In %1$s, there was %2$s character on air.', 'In %1$s, there were %2$s characters on air.', $onair_least['count'], 'lwtv' ),
			(string) $onair_least['year'],
			number_format_i18n( $onair_least['count'] )
		),
	),
);

// translators: %1$d is the year with most shows, %2$d is the number of shows it has.
$description = sprintf( __( 'The number of queer and trans characters on air each year peaked in %1$d (with %2$d); the subsequent dip reflects the current contraction in scripted TV', 'lwtv' ), $onair_most['year'], $onair_most['count'] );

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
	'eyebrow'     => __( 'Characters On Air per Year', 'lwtv' ),
	'headline'    => __( 'More queer characters on screen than ever', 'lwtv' ),
	'description' => $description,
	'callouts'    => $lwtv_callouts,
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
