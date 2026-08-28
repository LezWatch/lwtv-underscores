<?php
/**
 * Name: Airdates
 * Description: Single source of truth for reading a show's start/finish airdates.
 *
 * ACF stores airdates as two separate keys (lezshows_airdates_start and
 * lezshows_airdates_finish). Shows that predate that migration still carry a
 * serialized lezshows_airdates array with 'start'/'finish' members. Anything
 * reading airdates has to handle both, so it lives here once rather than being
 * reimplemented per caller.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Airdates {

	/**
	 * Current ACF meta key for the start year.
	 */
	const META_START = 'lezshows_airdates_start';

	/**
	 * Current ACF meta key for the finish year.
	 */
	const META_FINISH = 'lezshows_airdates_finish';

	/**
	 * Legacy serialized meta key, holding array( 'start' => .., 'finish' => .. ).
	 */
	const META_LEGACY = 'lezshows_airdates';

	/**
	 * Sentinel finish value meaning "still airing".
	 */
	const STILL_AIRING = 'current';

	/**
	 * Resolve start/finish from raw meta values.
	 *
	 * Pure: no WordPress calls, so this is unit-testable. Prefers the current
	 * ACF keys and fills in only the individually-empty ones from the legacy
	 * array, which matters for part-migrated shows that have a start but no
	 * finish (or vice versa).
	 *
	 * @param mixed $start  Raw value of META_START.
	 * @param mixed $finish Raw value of META_FINISH.
	 * @param mixed $legacy Raw value of META_LEGACY. Only used when it's an array.
	 *
	 * @return array{start: string, finish: string} Trimmed strings; '' when unknown.
	 */
	public static function resolve( $start, $finish, $legacy = null ): array {
		$start  = is_scalar( $start ) ? trim( (string) $start ) : '';
		$finish = is_scalar( $finish ) ? trim( (string) $finish ) : '';

		if ( ( '' === $start || '' === $finish ) && is_array( $legacy ) ) {
			if ( '' === $start && isset( $legacy['start'] ) && is_scalar( $legacy['start'] ) ) {
				$start = trim( (string) $legacy['start'] );
			}
			if ( '' === $finish && isset( $legacy['finish'] ) && is_scalar( $legacy['finish'] ) ) {
				$finish = trim( (string) $legacy['finish'] );
			}
		}

		return array(
			'start'  => $start,
			'finish' => $finish,
		);
	}

	/**
	 * Is this finish value the "still airing" sentinel?
	 *
	 * @param string $finish Finish value.
	 *
	 * @return bool
	 */
	public static function is_still_airing( $finish ): bool {
		return self::STILL_AIRING === strtolower( trim( (string) $finish ) );
	}

	/**
	 * Read a show's airdates, handling the legacy fallback.
	 *
	 * Static, like everything else here: there is no instance state, and the
	 * callers are all per-show inside loops.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return array{start: string, finish: string}
	 */
	public static function get( int $show_id ): array {
		$start  = get_post_meta( $show_id, self::META_START, true );
		$finish = get_post_meta( $show_id, self::META_FINISH, true );
		$legacy = null;

		// Only pay for the legacy read when one of the current keys is missing.
		if ( empty( $start ) || empty( $finish ) ) {
			$legacy = get_post_meta( $show_id, self::META_LEGACY, true );
		}

		return self::resolve( $start, $finish, $legacy );
	}
}
