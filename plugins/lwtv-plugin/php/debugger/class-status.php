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
 * Centralising it removed the nine copies but not the race underneath: a
 * read-modify-write of one shared array means cron finishing a scan while an
 * admin clicks "Rerun" on a different tab loses one of the two counts, and the
 * losing check then reports a stale number until its next run. See
 * DEBUGGER-REVIEW.md 2.3.
 *
 * So each check now owns its own option, and a write touches nothing but that
 * check. `all()` reassembles the same array the shared option used to hold —
 * including the global `timestamp` member — so every consumer is unchanged.
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
	 *
	 * Read by `all()` and `last_run()`, and pruned by `forget()`, but never
	 * written any more. Entries here fill in for checks that have not run since
	 * the split, so nothing vanishes from the admin while the weekly rotation
	 * catches up. Once every check has run once, this option is inert and can be
	 * deleted.
	 */
	const OPTION = 'lwtv_debugger_status';

	/**
	 * Option name prefix for one check's status.
	 */
	const PREFIX = 'lwtv_debugger_status_';

	/**
	 * Option listing the check keys that have their own option.
	 *
	 * Deliberately does NOT start with PREFIX. Sharing the prefix would mean a
	 * check whose key happened to be the index's suffix wrote over the index
	 * itself, which is an unlikely bug and a horrible one to find.
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
	 * Returns the same shape the single option used to hold: check keys mapping
	 * to `array( name, count, last[, summary] )`, plus a `timestamp` member
	 * holding the most recent run. `timestamp` is derived rather than stored,
	 * because "when did anything last run" is exactly the newest `last`.
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

		// Union, not merge: `+` keeps the left-hand value, so a check that has
		// its own option always wins over its pre-split leftover.
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

		/*
		 * The whole point: one option per check, so two checks finishing in the
		 * same moment cannot overwrite each other's counts.
		 *
		 * Not autoloaded. Only wp-admin and WP-CLI ever read these, so there is
		 * no reason to carry them on every front-end request.
		 */
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
	 * Retiring a check leaves its last count behind, and
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
