<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Years: one bar per year.
 *
 * @package LezWatch.TV
 *
 * @var string $dead_years_average
 * @var array  $dead_years_series
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$dy = lwtv_stats_year_series( $dead_years_series );

// The year-bars card renders its own "Deaths By Year" eyebrow, so no standalone one here.
$first_year = ! empty( $dy['rows'] ) ? (int) $dy['rows'][0]['year'] : 0;
$last_year  = ! empty( $dy['rows'] ) ? (int) $dy['rows'][ count( $dy['rows'] ) - 1 ]['year'] : 0;
$yearbars   = array(
	'rows'        => $dy['rows'],
	'peak_year'   => $dy['peak_year'],
	'peak_count'  => $dy['peak_count'],
	'average'     => $dead_years_average,
	'eyebrow'     => __( 'Deaths By Year', 'lwtv' ),
	/* translators: 1: first year, 2: last year. */
	'headline'    => sprintf( __( 'Every year, %1$s–%2$s', 'lwtv' ), (string) $first_year, (string) $last_year ),
	/* translators: %s: the deadliest year. */
	'description' => sprintf( __( 'One bar per year. %s towers over the rest.', 'lwtv' ), (string) $dy['peak_year'] ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
