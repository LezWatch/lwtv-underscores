<?php
/**
 * Worth It grid transforms.
 *
 * Pure array-in/array-out math for the Worth It view's hundred-square
 * grid and average-score bars: square allocation with the sum-to-100
 * guard, per-verdict score means, and the check behind the "verdict
 * tracks the score" heading — a claim about the data that must only be
 * made while the data supports it. No WordPress calls — unit-testable
 * without a WP runtime (see tests/unit/Statistics/WorthItGridTest.php).
 * All i18n stays in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Worth_It_Grid {

	/**
	 * Ordinal verdict order, best first — grid fill and list order.
	 */
	public const ORDER = array( 'yes', 'meh', 'no', 'tbd' );

	/**
	 * "Tracks the score" needs at least this yes-to-no spread (points).
	 */
	public const TRACKS_MIN_SPREAD = 15;

	/**
	 * Allocate 100 grid squares by verdict share.
	 *
	 * Guards, per the handoff: a verdict with a non-zero count always
	 * renders at least one square (TBD must never round to nothing),
	 * and rounding drift is absorbed by the largest verdict so the
	 * grid always fills exactly 100.
	 *
	 * @param array $counts Verdict => count.
	 * @return array Verdict => squares (verdicts with zero count dropped);
	 *               empty array when nothing is counted.
	 */
	public static function squares( array $counts ): array {
		$clean = array();
		$total = 0;

		foreach ( self::ORDER as $verdict ) {
			$count = max( 0, (int) ( $counts[ $verdict ] ?? 0 ) );
			if ( $count > 0 ) {
				$clean[ $verdict ] = $count;
				$total            += $count;
			}
		}

		if ( 0 === $total ) {
			return array();
		}

		$squares = array();
		$largest = array_keys( $clean, max( $clean ), true )[0];

		foreach ( $clean as $verdict => $count ) {
			$squares[ $verdict ] = max( 1, (int) round( ( $count / $total ) * 100 ) );
		}

		// Sum-to-100 guard: the largest verdict absorbs the drift.
		$squares[ $largest ] += 100 - array_sum( $squares );

		return $squares;
	}

	/**
	 * Mean score per verdict, rounded to whole numbers (the display
	 * shows no decimals). Verdicts with no valid scores are dropped.
	 *
	 * @param array $scores_by_verdict Verdict => array of raw score values.
	 * @return array Verdict => [ 'average' => int, 'count' => int ].
	 */
	public static function averages( array $scores_by_verdict ): array {
		$out = array();

		foreach ( $scores_by_verdict as $verdict => $scores ) {
			$clean = array();
			foreach ( (array) $scores as $score ) {
				if ( is_numeric( $score ) ) {
					$clean[] = (float) min( 100, max( 0, (float) $score ) );
				}
			}

			if ( empty( $clean ) ) {
				continue;
			}

			$out[ $verdict ] = array(
				'average' => (int) round( array_sum( $clean ) / count( $clean ) ),
				'count'   => count( $clean ),
			);
		}

		return $out;
	}

	/**
	 * Does the verdict track the score? True only when the averages are
	 * strictly ordinal (yes > meh > no) AND the yes-to-no spread is wide
	 * enough to be worth claiming. A flat 66/64/58 is technically
	 * ordered but "tracks the score" would oversell it.
	 *
	 * @param array $averages Output of averages() (needs yes/meh/no).
	 * @return bool
	 */
	public static function tracks_score( array $averages ): bool {
		foreach ( array( 'yes', 'meh', 'no' ) as $verdict ) {
			if ( ! isset( $averages[ $verdict ]['average'] ) ) {
				return false;
			}
		}

		$yes = (int) $averages['yes']['average'];
		$meh = (int) $averages['meh']['average'];
		$no  = (int) $averages['no']['average'];

		return ( $yes > $meh && $meh > $no && ( $yes - $no ) >= self::TRACKS_MIN_SPREAD );
	}
}
