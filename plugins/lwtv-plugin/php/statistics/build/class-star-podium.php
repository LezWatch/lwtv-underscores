<?php
/**
 * Star podium transforms.
 *
 * Pure array-in/array-out math for the Stars view's medal podium and
 * callout rail: column ordering, scaled plate heights, leader facts,
 * and the silver/bronze relationship the footnote copy adapts to.
 * No WordPress calls — unit-testable without a WP runtime (see
 * tests/unit/Statistics/StarPodiumTest.php). All i18n stays in the
 * template; this class only reports shape and numbers.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Star_Podium {

	/**
	 * Physical podium display order: gold centre, anti (if ever earned)
	 * off the end. This is presentation order, not data order.
	 */
	public const ORDER = array( 'silver', 'gold', 'bronze', 'anti' );

	/**
	 * Tiers that can "lead" — anti is a demerit and never counts.
	 */
	public const MERIT_TIERS = array( 'gold', 'silver', 'bronze' );

	/**
	 * Podium columns: fixed display order, zero tiers dropped, plate
	 * heights scaled so the tallest tier takes $max_height.
	 *
	 * @param array $counts     Tier => count (gold/silver/bronze/anti; missing = 0).
	 * @param int   $max_height Plate height of the tallest tier (px). Default 200.
	 * @param int   $min_height Height floor so tiny tiers stay visible (px). Default 24.
	 * @return array [ ['tier','count','height'], … ] or empty when nothing is starred.
	 */
	public static function columns( array $counts, int $max_height = 200, int $min_height = 24 ): array {
		$out = array();
		$top = 0;

		foreach ( self::ORDER as $tier ) {
			$count = (int) ( $counts[ $tier ] ?? 0 );
			if ( $count > 0 ) {
				$out[] = array(
					'tier'  => $tier,
					'count' => $count,
				);
				$top   = max( $top, $count );
			}
		}

		if ( 0 === $top ) {
			return array();
		}

		foreach ( $out as $i => $col ) {
			$out[ $i ]['height'] = max( $min_height, (int) round( ( $col['count'] / $top ) * $max_height ) );
		}

		return $out;
	}

	/**
	 * The rail-card numbers: star total, the leading merit tier and its
	 * share of all stars, the star rate, and the unstarred remainder.
	 *
	 * Percent precision follows the handoff: whole numbers on the share
	 * (rail cards round), one decimal on rate and none-share.
	 *
	 * @param array $counts       Tier => count.
	 * @param int   $total_shows  All shows.
	 * @param int   $starred      Distinct shows with at least one star; defaults
	 *                            to the tier sum when omitted.
	 * @return array star_sum, leader, leader_count, leader_share_pct,
	 *               star_rate_pct, none_count, none_pct.
	 */
	public static function facts( array $counts, int $total_shows, int $starred = 0 ): array {
		$star_sum = 0;
		foreach ( $counts as $count ) {
			$star_sum += max( 0, (int) $count );
		}

		$leader       = '';
		$leader_count = 0;
		foreach ( self::MERIT_TIERS as $tier ) {
			$count = (int) ( $counts[ $tier ] ?? 0 );
			if ( $count > $leader_count ) {
				$leader_count = $count;
				$leader       = $tier;
			}
		}

		$starred    = ( $starred > 0 ) ? $starred : $star_sum;
		$none_count = max( 0, $total_shows - $starred );

		return array(
			'star_sum'         => $star_sum,
			'leader'           => $leader,
			'leader_count'     => $leader_count,
			'leader_share_pct' => ( $star_sum > 0 ) ? (int) round( ( $leader_count / $star_sum ) * 100 ) : 0,
			'star_rate_pct'    => ( $total_shows > 0 ) ? round( ( $starred / $total_shows ) * 100, 1 ) : 0.0,
			'none_count'       => $none_count,
			'none_pct'         => ( $total_shows > 0 ) ? round( ( $none_count / $total_shows ) * 100, 1 ) : 0.0,
		);
	}

	/**
	 * How two tier counts relate, for adaptive footnote copy: a "dead
	 * heat" only while the gap stays within $threshold of the larger.
	 *
	 * @param int   $first     First count (e.g. silver).
	 * @param int   $second    Second count (e.g. bronze).
	 * @param float $threshold Gap share of the larger count that still
	 *                         reads as a tie. Default 0.15.
	 * @return string 'dead-heat' | 'first-leads' | 'second-leads' | 'none'.
	 */
	public static function relationship( int $first, int $second, float $threshold = 0.15 ): string {
		if ( $first <= 0 || $second <= 0 ) {
			return 'none';
		}

		if ( abs( $first - $second ) <= $threshold * max( $first, $second ) ) {
			return 'dead-heat';
		}

		return ( $first > $second ) ? 'first-leads' : 'second-leads';
	}
}
