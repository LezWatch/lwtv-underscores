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

// Callouts: the deadliest year, and the years nobody died. The current year is
// still in progress, so exclude it from the zero-death "best years" list.
$dy_now        = (int) gmdate( 'Y' );
$dy_zero_years = array();
foreach ( $dy['rows'] as $dy_row ) {
	if ( 0 === (int) $dy_row['count'] && (int) $dy_row['year'] !== $dy_now ) {
		$dy_zero_years[] = (string) (int) $dy_row['year'];
	}
}

$dy_callouts = array();
if ( (int) $dy['peak_count'] > 0 ) {
	$dy_callouts[] = array(
		'label' => __( 'Worst Year', 'lwtv' ),
		'svg'   => 'hand-holding-skull.svg',
		'icon'  => 'svg-hand-holding-skull',
		// Raw values — the partial escapes the assembled text with esc_html().
		'text'  => sprintf(
			/* translators: 1: year, 2: number of deaths. */
			_n( 'In %1$s there was %2$s death.', 'In %1$s there were %2$s deaths.', (int) $dy['peak_count'], 'lwtv' ),
			(string) $dy['peak_year'],
			number_format_i18n( (int) $dy['peak_count'] )
		),
	);
}
if ( ! empty( $dy_zero_years ) ) {
	$dy_callouts[] = array(
		'label' => __( 'Best Years', 'lwtv' ),
		'svg'   => 'fireworks.svg',
		'icon'  => 'svg-fireworks',
		'text'  => sprintf(
			/* translators: %s: a list of years, e.g. "2001, 2002, and 2003". */
			__( 'There were 0 deaths in %s.', 'lwtv' ),
			wp_sprintf_l( '%l', $dy_zero_years )
		),
	);
}

$yearbars = array(
	'rows'        => $dy['rows'],
	'peak_year'   => $dy['peak_year'],
	'peak_count'  => $dy['peak_count'],
	'average'     => $dead_years_average,
	'callouts'    => $dy_callouts,
	'eyebrow'     => __( 'Deaths By Year', 'lwtv' ),
	/* translators: 1: first year, 2: last year. */
	'headline'    => sprintf( __( 'Every year, %1$s–%2$s', 'lwtv' ), (string) $first_year, (string) $last_year ),
	/* translators: %s: the deadliest year. */
	'description' => sprintf( __( 'One bar per year. %s towers over the rest.', 'lwtv' ), (string) $dy['peak_year'] ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
