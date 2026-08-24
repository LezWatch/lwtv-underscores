<?php
/**
 * Debugger run status: per-check counts and last-run timestamps.
 *
 * Every scanner used to inline this block, which meant ten copies of
 * `get_option()` → mutate → `update_option()`. That pattern also assigned into
 * the option's array offset without checking it was an array, which raises
 * "Automatic conversion of false to array is deprecated" on PHP 8.1+ whenever
 * the option is missing (fresh install, or after a reset).
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Status {

	/**
	 * Option holding every check's status.
	 */
	const OPTION = 'lwtv_debugger_status';

	/**
	 * Read the whole status option, always as an array.
	 *
	 * @return array
	 */
	public static function all(): array {
		$option = get_option( self::OPTION );
		return is_array( $option ) ? $option : array();
	}

	/**
	 * Record the result of a check run.
	 *
	 * @param string $key   Status key for this check (e.g. 'show_problems').
	 * @param string $name  Human label shown in the admin summary.
	 * @param int    $count How many findings this run produced.
	 *
	 * @return void
	 */
	public static function record( string $key, string $name, int $count ): void {
		$option = self::all();

		$option[ $key ]      = array(
			'name'  => $name,
			'count' => $count,
			'last'  => time(),
		);
		$option['timestamp'] = time();

		update_option( self::OPTION, $option );
	}

	/**
	 * Drop entries for checks that no longer exist.
	 *
	 * Retiring a check leaves its last count behind in this option, and
	 * Admin_Menu\Validation::current_status() prints every entry with a count
	 * above zero. Without a prune, a deleted check keeps reporting findings on
	 * the intro tab forever, with no tab to open and nothing that could ever
	 * recompute it to zero.
	 *
	 * @param array<string> $keys Status keys to remove.
	 *
	 * @return array<string> The keys that were actually present and removed.
	 */
	public static function forget( array $keys ): array {
		$option  = self::all();
		$removed = array();

		foreach ( $keys as $key ) {
			// 'timestamp' is the global last-run marker, not a check.
			if ( 'timestamp' === $key || ! array_key_exists( $key, $option ) ) {
				continue;
			}

			unset( $option[ $key ] );
			$removed[] = $key;
		}

		if ( ! empty( $removed ) ) {
			update_option( self::OPTION, $option );
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
		$option = self::all();
		return isset( $option[ $key ]['last'] ) ? (int) $option[ $key ]['last'] : 0;
	}
}
