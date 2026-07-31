<?php
/**
 * Series trend classifier.
 *
 * Pure array-in/array-out shape detection for a per-year count series,
 * so templates can pick adaptive verbiage ("more than ever" vs. "down
 * from the peak") instead of hardcoding claims the data can outgrow.
 * No WordPress calls — unit-testable without a WP runtime (see
 * tests/unit/Statistics/SeriesTrendTest.php). All i18n strings stay in
 * the templates; this class only reports the shape.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Series_Trend {

	/**
	 * Classify the shape of a per-year count series.
	 *
	 * The in-progress current year is excluded — its partial count would
	 * always read as a crash. States, judged over completed years:
	 *
	 * - 'at-peak':    the latest completed year matches the all-time high.
	 * - 'recovering': below the peak, but the last step moved up.
	 * - 'receding':   below the peak, and the last step moved down.
	 * - 'steady':     below the peak, unchanged from the year before.
	 *
	 * @param array $rows         [ ['year'=>int,'count'=>int], … ] in any order.
	 * @param int   $current_year The in-progress year to exclude.
	 * @return array {
	 *   @type string $state            One of the four states above.
	 *   @type int    $peak_year        Latest year matching the max count.
	 *   @type int    $peak_count
	 *   @type int    $latest_year      Latest completed year.
	 *   @type int    $latest_count
	 *   @type int    $years_since_peak 0 when at the peak.
	 *   @type int    $pct_of_peak      Latest count as a rounded % of the peak.
	 * } Empty array when no completed years exist.
	 */
	public static function classify( array $rows, int $current_year ): array {
		$by_year = array();

		foreach ( $rows as $row ) {
			$year = (int) ( $row['year'] ?? 0 );
			if ( $year <= 0 || $year >= $current_year ) {
				continue;
			}
			$by_year[ $year ] = (int) ( $row['count'] ?? 0 );
		}

		if ( empty( $by_year ) ) {
			return array();
		}

		ksort( $by_year );

		$years        = array_keys( $by_year );
		$latest_year  = (int) end( $years );
		$latest_count = $by_year[ $latest_year ];

		// Peak = the latest year matching the max, so a tie with an old
		// high reads as "back at the peak", not "eight years past it".
		$peak_count = max( $by_year );
		$peak_year  = 0;
		foreach ( $by_year as $year => $count ) {
			if ( $count === $peak_count ) {
				$peak_year = $year;
			}
		}

		// Direction of the most recent completed step.
		$previous_count = ( count( $years ) > 1 ) ? $by_year[ $years[ count( $years ) - 2 ] ] : $latest_count;

		if ( $latest_count >= $peak_count ) {
			$state = 'at-peak';
		} elseif ( $latest_count > $previous_count ) {
			$state = 'recovering';
		} elseif ( $latest_count < $previous_count ) {
			$state = 'receding';
		} else {
			$state = 'steady';
		}

		return array(
			'state'            => $state,
			'peak_year'        => $peak_year,
			'peak_count'       => $peak_count,
			'latest_year'      => $latest_year,
			'latest_count'     => $latest_count,
			'years_since_peak' => max( 0, $latest_year - $peak_year ),
			'pct_of_peak'      => ( $peak_count > 0 ) ? (int) round( ( $latest_count / $peak_count ) * 100 ) : 0,
		);
	}
}
