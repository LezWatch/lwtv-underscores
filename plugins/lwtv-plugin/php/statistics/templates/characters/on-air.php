<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → On Air: year-over-year column chart of characters-on-air per year.
 *
 * @package LezWatch.TV
 */

$onair_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
$onair_data = ( is_array( $onair_raw ) && ! empty( $onair_raw ) ) ? (array) reset( $onair_raw ) : array();
$onair_most = array(
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
}
$onair_last = end( $onair_points ) ?: array(
	'year'  => (int) gmdate( 'Y' ),
	'count' => 0,
);

// Biggest year-over-year decline. A raw "fewest characters" year is meaningless
// here — the sparse early decades are a wall of 0s and 1s — so the counterpoint
// to the best year is the sharpest drop instead. The current (final) year is
// still in progress, so its partial count is excluded to stop it masquerading as
// the steepest fall.
$onair_drop     = array(
	'year' => 0,
	'size' => 0,
);
$onair_complete = $onair_points;
array_pop( $onair_complete );
$onair_prev = null;
foreach ( $onair_complete as $onair_point ) {
	if ( null !== $onair_prev ) {
		$onair_delta = $onair_prev['count'] - $onair_point['count'];
		if ( $onair_delta > $onair_drop['size'] ) {
			$onair_drop['size'] = $onair_delta;
			$onair_drop['year'] = $onair_point['year'];
		}
	}
	$onair_prev = $onair_point;
}

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
);

// Only pair the best year with a decline callout when there actually was a drop
// between completed years; otherwise the best year stands on its own.
if ( $onair_drop['size'] > 0 ) {
	$lwtv_callouts[] = array(
		'label' => __( 'Biggest Drop', 'lwtv' ),
		'svg'   => 'nessie.svg',
		'icon'  => 'svg-nessie',
		'text'  => sprintf(
			/* translators: 1: year, 2: number of fewer characters on air than the year before. */
			_n( 'In %1$s, %2$s fewer character was on air than the year before.', 'In %1$s, %2$s fewer characters were on air than the year before.', $onair_drop['size'], 'lwtv' ),
			(string) $onair_drop['year'],
			number_format_i18n( $onair_drop['size'] )
		),
	);
}

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
	/* translators: %s: year. */
	'hover_sub'   => __( 'on air in %s', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';

$download_csv = array(
	'page'  => __( 'year', 'lwtv' ),
	'title' => __( 'Characters on air, by year', 'lwtv' ),
	'count' => count( $onair_series['rows'] ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';
