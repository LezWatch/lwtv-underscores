<?php
/**
 * Debugger run status: per-check counts and last-run timestamps.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Status {

	/**
	 * The former single option holding every check's status.
	 */
	const OPTION = 'lwtv_debugger_status';

	/**
	 * Option name prefix for one check's status.
	 */
	const PREFIX = 'lwtv_debugger_status_';

	/**
	 * Option listing the check keys that have their own option.
	 */
	const INDEX = 'lwtv_debugger_check_keys';

	/**
	 * Check keys that currently have their own option.
	 *
	 * @return array<string>
	 */
	public static function keys(): array {
		$keys = get_option( self::INDEX );

		if ( ! is_array( $keys ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
	}

	/**
	 * Read every check's status, always as an array.
	 *
	 * @return array
	 */
	public static function all(): array {
		$status = array();

		foreach ( self::keys() as $key ) {
			$entry = get_option( self::PREFIX . $key );

			if ( is_array( $entry ) ) {
				$status[ $key ] = $entry;
			}
		}

		$status = $status + self::legacy();

		$times = array();

		foreach ( $status as $entry ) {
			if ( is_array( $entry ) && isset( $entry['last'] ) ) {
				$times[] = (int) $entry['last'];
			}
		}

		if ( ! empty( $times ) ) {
			$status['timestamp'] = max( $times );
		}

		return $status;
	}

	/**
	 * Record the result of a check run.
	 *
	 * @param string $key     Status key for this check (e.g. 'show_problems').
	 * @param string $name    Human label shown in the admin summary.
	 * @param int    $count   How many findings this run produced.
	 * @param array  $summary Optional new/open/resolved breakdown from
	 *                        Build\Baseline::diff(). Omitting it clears any
	 *                        stored breakdown, which is what a caller that has
	 *                        changed the count without re-scanning (a single
	 *                        admin repair) wants: a stale "3 new" is worse than
	 *                        no breakdown at all.
	 *
	 * @return void
	 */
	public static function record( string $key, string $name, int $count, array $summary = array() ): void {
		$entry = array(
			'name'  => $name,
			'count' => $count,
			'last'  => time(),
		);

		if ( ! empty( $summary ) ) {
			$entry['summary'] = $summary;
		}

		update_option( self::PREFIX . $key, $entry, false );

		$keys = self::keys();

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::INDEX, $keys, false );
		}
	}

	/**
	 * Drop entries for checks that no longer exist.
	 *
	 * @param array<string> $keys Status keys to remove.
	 *
	 * @return array<string> The keys that were actually present and removed.
	 */
	public static function forget( array $keys ): array {
		$known         = self::keys();
		$legacy        = self::legacy();
		$legacy_change = false;
		$known_change  = false;
		$removed       = array();

		foreach ( $keys as $key ) {
			// 'timestamp' is the derived last-run marker, not a check.
			if ( 'timestamp' === $key ) {
				continue;
			}

			$has_own    = in_array( $key, $known, true );
			$has_legacy = array_key_exists( $key, $legacy );

			if ( ! $has_own && ! $has_legacy ) {
				continue;
			}

			if ( $has_own ) {
				delete_option( self::PREFIX . $key );
				$known        = array_values( array_diff( $known, array( $key ) ) );
				$known_change = true;
			}

			if ( $has_legacy ) {
				unset( $legacy[ $key ] );
				$legacy_change = true;
			}

			$removed[] = $key;
		}

		if ( $known_change ) {
			update_option( self::INDEX, $known, false );
		}

		if ( $legacy_change ) {
			update_option( self::OPTION, $legacy, false );
		}

		return $removed;
	}

	/**
	 * When was a given check last run?
	 *
	 * @param string $key Status key for the check.
	 *
	 * @return int Unix timestamp, or 0 when unknown.
	 */
	public static function last_run( string $key ): int {
		$entry = get_option( self::PREFIX . $key );

		if ( is_array( $entry ) && isset( $entry['last'] ) ) {
			return (int) $entry['last'];
		}

		$legacy = self::legacy();

		return isset( $legacy[ $key ]['last'] ) ? (int) $legacy[ $key ]['last'] : 0;
	}

	/**
	 * The pre-split option's check entries, without its stored timestamp.
	 *
	 * @return array
	 */
	private static function legacy(): array {
		$legacy = get_option( self::OPTION );

		if ( ! is_array( $legacy ) ) {
			return array();
		}

		// The old global marker was stored; the new one is derived in all().
		unset( $legacy['timestamp'] );

		return $legacy;
	}
}
