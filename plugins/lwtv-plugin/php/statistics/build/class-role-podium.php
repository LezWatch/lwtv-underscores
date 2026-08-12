<?php
/**
 * Role podium transforms.
 *
 * Pure array-in/array-out math for the Actors → Roles view: the
 * Regular/Recurring/Guest breakdown, the leading role type, and its share
 * of every tagged appearance. No WordPress calls — unit-testable without a
 * WP runtime (see tests/unit/Statistics/RolePodiumTest.php). All i18n
 * stays in the template; this class only reports shape and numbers.
 *
 * Unlike Star_Podium/Trigger_Levels, there is no separate "total shows/
 * characters" denominator here: every tagged show-group row carries
 * exactly one role type, so the sum of the three buckets is the total by
 * definition.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role_Podium {

	/**
	 * Display order: main-cast first, guest last.
	 */
	public const ORDER = array( 'regular', 'recurring', 'guest' );

	/**
	 * The rail/donut numbers: total tagged appearances, the leading role
	 * type, its count and share, and each type's own share of the total.
	 *
	 * @param array $counts Role type => count (regular/recurring/guest; missing = 0).
	 * @return array {
	 *   @type int    $sum              Total tagged appearances.
	 *   @type string $leader           Leading role type, '' when nothing is tagged.
	 *   @type int    $leader_count     The leader's count.
	 *   @type int    $leader_share_pct The leader's share of $sum, whole percent.
	 *   @type array  $levels           Role type => [ 'count' => int, 'share' => float (1dp) ].
	 * }
	 */
	public static function facts( array $counts ): array {
		$levels = array();
		$sum    = 0;

		foreach ( self::ORDER as $type ) {
			$count           = max( 0, (int) ( $counts[ $type ] ?? 0 ) );
			$levels[ $type ] = array( 'count' => $count );
			$sum            += $count;
		}

		$leader       = '';
		$leader_count = 0;
		foreach ( self::ORDER as $type ) {
			$count = $levels[ $type ]['count'];
			if ( $count > $leader_count ) {
				$leader_count = $count;
				$leader       = $type;
			}
		}

		foreach ( $levels as $type => $row ) {
			$levels[ $type ]['share'] = ( $sum > 0 ) ? round( ( $row['count'] / $sum ) * 100, 1 ) : 0.0;
		}

		return array(
			'sum'              => $sum,
			'leader'           => $leader,
			'leader_count'     => $leader_count,
			'leader_share_pct' => ( $sum > 0 ) ? (int) round( ( $leader_count / $sum ) * 100 ) : 0,
			'levels'           => $levels,
		);
	}
}
