<?php
/**
 * Name: Index State
 * Description: Whether an index is set up on this environment.
 *
 * Pure. Sync must never create an index (Meilisearch creates one on the
 * first document add, with default settings), so a queued task writes only
 * when the index is known to exist. Only index_not_found means "missing";
 * anything else (server down, bad key) is "unknown": not cached, no write.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Index_State {

	const EXISTS  = 'exists';
	const MISSING = 'missing';
	const UNKNOWN = 'unknown';

	/**
	 * Seconds to cache each state.
	 */
	const EXISTS_TTL  = 3600;
	const MISSING_TTL = 300;

	/**
	 * Seconds before an admin page checks an unknown index again.
	 */
	const UNKNOWN_RECHECK = 300;

	/**
	 * State from an index-stats call's error message.
	 *
	 * @param string|null $error_message Null when the call succeeded.
	 * @return string One of the state constants.
	 */
	public static function classify( ?string $error_message ): string {
		if ( null === $error_message ) {
			return self::EXISTS;
		}

		return ( false !== strpos( $error_message, 'index_not_found' ) ) ? self::MISSING : self::UNKNOWN;
	}

	/**
	 * Seconds to cache a state; 0 means don't cache.
	 *
	 * @param string $state State.
	 * @return int
	 */
	public static function ttl( string $state ): int {
		if ( self::EXISTS === $state ) {
			return self::EXISTS_TTL;
		}

		return ( self::MISSING === $state ) ? self::MISSING_TTL : 0;
	}

	/**
	 * Seconds an admin page waits before re-checking after this state; 0 means
	 * no wait. Unknown isn't cached for tasks (the server may be back on the next
	 * one), but the admin notice must not pay a timeout on every page load.
	 *
	 * @param string $state State.
	 * @return int
	 */
	public static function recheck_after( string $state ): int {
		return ( self::UNKNOWN === $state ) ? self::UNKNOWN_RECHECK : 0;
	}
}
