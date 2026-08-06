<?php
/**
 * Trigger-levels transforms.
 *
 * Pure array-in/array-out math for the Triggers view's callout rail and
 * true-scale/magnified bars: flagged vs none splits, per-level shares
 * of both denominators, the derived rail figures, and the low/high
 * balance data the footnote copy adapts to ("nearly 2 to 1" only while
 * that is true). No WordPress calls — unit-testable without a WP
 * runtime (see tests/unit/Statistics/TriggerLevelsTest.php). All i18n
 * stays in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trigger_Levels {

	/**
	 * Severity order, mildest first — the bar and legend order.
	 */
	public const ORDER = array( 'low', 'medium', 'high' );

	/**
	 * The rail and bar numbers.
	 *
	 * Percent precision follows the handoff: one decimal everywhere,
	 * whole numbers on the "1 in N" ratios.
	 *
	 * @param array $counts Level => count (low/medium/high; missing = 0).
	 * @param int   $total  All shows.
	 * @return array {
	 *   @type int   $flagged        Shows with any warning.
	 *   @type int   $none           Shows without one.
	 *   @type float $none_pct       Share of all shows (1dp).
	 *   @type float $flagged_pct    Share of all shows (1dp).
	 *   @type int   $scarcity_ratio "1 in N" for any warning (0 when none).
	 *   @type int   $heavy          Medium + high count.
	 *   @type float $heavy_pct      Heavy share of flagged (1dp).
	 *   @type int   $floor_ratio    "1 in N" for high (0 when none).
	 *   @type array $levels         Level => [ count, share_total, share_flagged ].
	 * }
	 */
	public static function facts( array $counts, int $total ): array {
		$levels  = array();
		$flagged = 0;

		foreach ( self::ORDER as $level ) {
			$count            = max( 0, (int) ( $counts[ $level ] ?? 0 ) );
			$levels[ $level ] = array( 'count' => $count );
			$flagged         += $count;
		}

		foreach ( $levels as $level => $row ) {
			$levels[ $level ]['share_total']   = ( $total > 0 ) ? round( ( $row['count'] / $total ) * 100, 1 ) : 0.0;
			$levels[ $level ]['share_flagged'] = ( $flagged > 0 ) ? round( ( $row['count'] / $flagged ) * 100, 1 ) : 0.0;
		}

		$high  = $levels['high']['count'];
		$heavy = $levels['medium']['count'] + $high;
		$none  = max( 0, $total - $flagged );

		return array(
			'flagged'        => $flagged,
			'none'           => $none,
			'none_pct'       => ( $total > 0 ) ? round( ( $none / $total ) * 100, 1 ) : 0.0,
			'flagged_pct'    => ( $total > 0 ) ? round( ( $flagged / $total ) * 100, 1 ) : 0.0,
			'scarcity_ratio' => ( $flagged > 0 && $total > 0 ) ? (int) round( $total / $flagged ) : 0,
			'heavy'          => $heavy,
			'heavy_pct'      => ( $flagged > 0 ) ? round( ( $heavy / $flagged ) * 100, 1 ) : 0.0,
			'floor_ratio'    => ( $high > 0 && $total > 0 ) ? (int) round( $total / $high ) : 0,
			'levels'         => $levels,
		);
	}

	/**
	 * How low and high warning counts relate, for the balance footnote.
	 *
	 * Counts within 15% of each other read as "even" — an X-to-1 ratio
	 * would overstate that gap. Otherwise the larger side leads, with a
	 * rounded ratio and a qualifier telling the template whether the
	 * rounding went up ("nearly 2 to 1"), down ("more than 2 to 1"), or
	 * nowhere ("exactly").
	 *
	 * @param int $low  Low-warning count.
	 * @param int $high High-warning count.
	 * @return array [ 'mode' => 'low-leads'|'high-leads'|'even'|'none',
	 *                 'ratio' => int, 'qualifier' => 'nearly'|'more-than'|'exactly' ]
	 */
	public static function balance( int $low, int $high ): array {
		$out = array(
			'mode'      => 'none',
			'ratio'     => 0,
			'qualifier' => 'exactly',
		);

		if ( $low <= 0 || $high <= 0 ) {
			return $out;
		}

		$larger  = max( $low, $high );
		$smaller = min( $low, $high );

		if ( ( $larger - $smaller ) <= 0.15 * $larger ) {
			$out['mode'] = 'even';
			return $out;
		}

		$ratio   = $larger / $smaller;
		$rounded = max( 1, (int) round( $ratio ) );

		if ( abs( $rounded - $ratio ) < 0.005 ) {
			$qualifier = 'exactly';
		} else {
			$qualifier = ( $rounded > $ratio ) ? 'nearly' : 'more-than';
		}

		return array(
			'mode'      => ( $low > $high ) ? 'low-leads' : 'high-leads',
			'ratio'     => $rounded,
			'qualifier' => $qualifier,
		);
	}
}
