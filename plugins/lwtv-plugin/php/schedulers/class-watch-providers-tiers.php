<?php
/**
 * TMDB Watch Providers refresh tiers
 *
 * How long a show's stored watch providers are good for, by on-air status. A show
 * that is airing can change services mid-season; one that ended years ago rarely
 * does. Pure: no WordPress calls, so the rule is unit-tested and the SQL in
 * Watch_Providers_Task::stale_ids() mirrors it. See GitHub issue #728.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Airdates;

/**
 * Class Watch_Providers_Tiers
 */
class Watch_Providers_Tiers {

	/**
	 * Finish is 'current', or a year at or after the current one. A show that
	 * ends this year stays here until 1 January, which covers "recently ended".
	 */
	const ON_AIR = 'on_air';

	/**
	 * Finish is a year before the current one.
	 */
	const ENDED = 'ended';

	/**
	 * No usable finish date. Never refreshed automatically, only by
	 * `wp lwtv watchproviders refresh`. A never-checked show is still fetched once.
	 */
	const NONE = 'none';

	/**
	 * Seconds an answer is good for, per tier. 0 means never due.
	 */
	const TTLS = array(
		self::ON_AIR => 7 * DAY_IN_SECONDS,
		self::ENDED  => 30 * DAY_IN_SECONDS,
		self::NONE   => 0,
	);

	/**
	 * Which tier a show is in.
	 *
	 * Only 'current' and a bare four-digit year count, the same values the SQL in
	 * stale_ids() accepts. Anything else is NONE rather than a guess.
	 *
	 * @param string $finish Value of lezshows_airdates_finish.
	 * @param int    $year   The current year, from the caller.
	 * @return string One of the tier constants.
	 */
	public static function tier( string $finish, int $year ): string {
		$finish = trim( $finish );

		if ( Airdates::is_still_airing( $finish ) ) {
			return self::ON_AIR;
		}

		if ( 1 !== preg_match( '/^\d{4}$/', $finish ) ) {
			return self::NONE;
		}

		return (int) $finish >= $year ? self::ON_AIR : self::ENDED;
	}

	/**
	 * How long a show's answer is good for.
	 *
	 * @param string $finish Value of lezshows_airdates_finish.
	 * @param int    $year   The current year, from the caller.
	 * @return int Seconds; 0 means never refreshed automatically.
	 */
	public static function ttl_for( string $finish, int $year ): int {
		return self::TTLS[ self::tier( $finish, $year ) ];
	}
}
