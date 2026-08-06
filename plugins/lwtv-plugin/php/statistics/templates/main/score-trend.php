<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Score trend band: the average score of the shows on air, year by year.
 *
 * Each show contributes its whole-run score to every year it aired, so a
 * year's figure reads as "how good was the lineup you could watch that
 * year." Bars sit on a fixed 0–100 scale (scale_max) so a few points of
 * drift never inflates into a dramatic skyline — the story lives in the
 * numbers and the best-year callout, not in bar theatrics.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Scores as Build_Scores;
use LWTV\Statistics\Build\Score_Distribution;

$trend_yearly = Score_Distribution::yearly_average( ( new Build_Scores() )->get_scores_by_on_air_year() );

// Years with a tiny lineup (fewer than five shows) are noise, not a trend.
$trend_yearly = Score_Distribution::trim_thin_years( $trend_yearly, 5 );

if ( empty( $trend_yearly ) ) {
	return;
}

// Best year among completed years only; the current year is still being
// recalculated as shows air and would flatter or slander itself.
$trend_now       = (int) gmdate( 'Y' );
$trend_completed = $trend_yearly;
unset( $trend_completed[ $trend_now ] );
$trend_best = Score_Distribution::best_year( ! empty( $trend_completed ) ? $trend_completed : $trend_yearly );

// Rows for the bars; the visual peak may be the in-progress year.
$trend_rows = array();
$trend_peak = array(
	'year'  => 0,
	'count' => 0,
);
foreach ( $trend_yearly as $trend_year => $trend_row ) {
	$trend_avg    = (int) round( (float) $trend_row['average'] );
	$trend_rows[] = array(
		'year'  => $trend_year,
		'count' => $trend_avg,
	);
	if ( $trend_avg > $trend_peak['count'] ) {
		$trend_peak = array(
			'year'  => $trend_year,
			'count' => $trend_avg,
		);
	}
}

$trend_last = end( $trend_rows );

$trend_description = __( 'The average score of every show on the air that year, on the full 0–100 scale. Years with fewer than five shows are left off.', 'lwtv' );
if ( isset( $trend_yearly[ $trend_now ] ) ) {
	$trend_description .= ' ' . __( 'The current year is still in progress.', 'lwtv' );
}

$yearbars = array(
	'rows'        => $trend_rows,
	'peak_year'   => $trend_peak['year'],
	'peak_count'  => $trend_peak['count'],
	'scale_max'   => Score_Distribution::SCORE_MAX,
	'stat_num'    => (int) $trend_last['count'],
	/* translators: %s: the latest year (4-digit, never thousands-formatted). */
	'stat_sub'    => sprintf( __( 'average score in %s', 'lwtv' ), (string) $trend_last['year'] ),
	'eyebrow'     => __( 'The Lineup, Graded', 'lwtv' ),
	'headline'    => __( 'How good was each year of queer TV?', 'lwtv' ),
	'description' => $trend_description,
	'callouts'    => array(
		array(
			'label' => __( 'Best Lineup', 'lwtv' ),
			'svg'   => 'trophy.svg',
			'icon'  => 'svg-trophy',
			'text'  => sprintf(
				/* translators: 1: year, 2: average show score that year (0–100). */
				__( 'The shows on air in %1$s averaged %2$s, making it the best-graded year of queer TV so far.', 'lwtv' ),
				(string) $trend_best['year'],
				number_format_i18n( $trend_best['average'], 1 )
			),
		),
	),
	/* translators: %s: year. */
	'hover_sub'   => __( 'average score in %s', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
