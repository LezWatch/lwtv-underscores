<?php
/**
 * This Year Standouts
 *
 * Pure selectors for the Overview Chapter 03 standout rows: "pick the biggest"
 * (biggest ensemble, busiest actor) and "pick the earliest-starting show"
 * (longest running).
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Standouts
 */
class Standouts {

	/**
	 * The key with the largest count.
	 *
	 * @param array $counts [ key => int count ].
	 * @return array|null { @type mixed $key, @type int $count }, or null when
	 *                    empty or every count is zero.
	 */
	public static function busiest( array $counts ): ?array {
		$best_key   = null;
		$best_count = 0;

		foreach ( $counts as $key => $count ) {
			if ( (int) $count > $best_count ) {
				$best_count = (int) $count;
				$best_key   = $key;
			}
		}

		if ( null === $best_key ) {
			return null;
		}

		return array(
			'key'   => $best_key,
			'count' => $best_count,
		);
	}

	/**
	 * The longest-running show that ended this year: of the shows whose run
	 * finished in $year, the one with the earliest start.
	 *
	 * @param array $shows List of shows, each with raw 'start' and 'finish' dates
	 *                     (Ymd, Y-m-d, or a bare year — all begin with the year).
	 * @param int   $year  The year a show's run must have ended in.
	 * @return array|null The winning show with added int 'start_year', 'end_year',
	 *                    and 'years' (the run span), or null when none qualify.
	 */
	public static function longest_run_ended( array $shows, int $year ): ?array {
		$ended = self::runs_ended( $shows, $year );
		return $ended[0] ?? null;
	}

	/**
	 * Every show whose run ended this year, longest run first.
	 *
	 * @param array $shows List of shows, each with raw 'start' and 'finish' dates.
	 * @param int   $year  The year a show's run must have ended in.
	 * @return array Each qualifying show with added int 'start_year', 'end_year',
	 *               and 'years', sorted by 'years' descending.
	 */
	public static function runs_ended( array $shows, int $year ): array {
		$ended = array();

		foreach ( $shows as $show ) {
			$finish_year = (int) substr( (string) ( $show['finish'] ?? '' ), 0, 4 );
			if ( $finish_year !== $year ) {
				continue;
			}

			$start_year = (int) substr( (string) ( $show['start'] ?? '' ), 0, 4 );
			if ( $start_year <= 0 ) {
				continue;
			}

			$show['start_year'] = $start_year;
			$show['end_year']   = $year;
			$show['years']      = max( 1, $year - $start_year );
			$ended[]            = $show;
		}

		// Longest run first; usort is stable in PHP 8, so equal runs keep the
		// builder's alphabetical order.
		usort(
			$ended,
			static function ( $lwtv_a, $lwtv_b ) {
				return $lwtv_b['years'] <=> $lwtv_a['years'];
			}
		);

		return $ended;
	}
}
