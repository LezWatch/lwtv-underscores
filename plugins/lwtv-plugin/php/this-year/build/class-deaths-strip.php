<?php
/**
 * This Year Deaths Strip
 *
 * Pure transform for the This Year Overview deaths strip: buckets a year's
 * deaths into a twelve-month timeline with per-month marker metadata.
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deaths_Strip
 */
class Deaths_Strip {

	/**
	 * Build the deaths-strip data from a by-date death map.
	 *
	 * @param array $dead_by_date_ov  [ 'Y-m-d' => array of dead characters ].
	 * @param bool  $is_current_year  Whether to apply the in-progress treatment.
	 * @param int   $current_month    Current month (1-12); only used for the
	 *                                 current year's "today" / future split.
	 * @return array {
	 *     @type int   $total           Total deaths across the year.
	 *     @type bool  $is_current_year Whether the year is in progress.
	 *     @type int   $elapsed_months  Months elapsed (current month, or 12).
	 *     @type float $elapsed_pct     Elapsed share of the year, 0-100.
	 *     @type array $months          [ 1-12 => marker metadata ].
	 * }
	 */
	public static function build( array $dead_by_date_ov, bool $is_current_year = false, int $current_month = 12 ): array {
		// Bucket every death into its calendar month. Keys are Y-m-d (the
		// formatter normalizes both Ymd and Y-m-d to this), so the month is
		// the two digits after the first dash.
		$counts = array_fill( 1, 12, 0 );
		foreach ( $dead_by_date_ov as $date => $characters ) {
			$month = (int) substr( (string) $date, 5, 2 );
			if ( $month < 1 || $month > 12 ) {
				continue;
			}
			$counts[ $month ] += count( $characters );
		}

		$months = array();
		foreach ( $counts as $month => $count ) {
			$is_single  = ( 1 === $count );
			$show_count = ( $count > 1 );

			if ( $show_count ) {
				$size = 18 + ( $count * 4 );
			} elseif ( $is_single ) {
				$size = 15;
			} else {
				$size = 5;
			}

			$months[ $month ] = array(
				'month'      => $month,
				'count'      => $count,
				'is_empty'   => ( 0 === $count ),
				'is_single'  => $is_single,
				'show_count' => $show_count,
				'size'       => $size,
				'is_future'  => ( $is_current_year && $month > $current_month ),
			);
		}

		$elapsed_months = $is_current_year ? $current_month : 12;

		return array(
			'total'           => array_sum( $counts ),
			'is_current_year' => $is_current_year,
			'elapsed_months'  => $elapsed_months,
			'elapsed_pct'     => round( $elapsed_months / 12 * 100, 2 ),
			'months'          => $months,
		);
	}
}
