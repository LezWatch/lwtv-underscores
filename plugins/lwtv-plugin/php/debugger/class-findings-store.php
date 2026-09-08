<?php
/**
 * Where a check's findings live.
 *
 * A non-autoloaded option per check plus a small index, which is the same shape
 * Status and Baseline_Store already use.
 *
 * Expiry is kept, and kept explicit. It is what makes a check nobody has looked
 * at rebuild itself rather than showing figures from spring, and `Report::items()`
 * leans on it: a `false` read is what triggers an automatic re-scan. So `load()`
 * returns `false` for "absent or expired" exactly as `get_transient()` did, and
 * the three states callers distinguish stay distinguishable:
 *
 *   false      -- never run, or the findings aged out.
 *   array()    -- ran, found nothing. "Excellent!", not "no report".
 *   array(...) -- ran, found these.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Findings_Store {

	/**
	 * Option holding the list of keys that have findings stored.
	 */
	const INDEX = 'lwtv_debug_findings_keys';

	/**
	 * How long findings stay readable.
	 */
	const TTL = 10 * DAY_IN_SECONDS;

	/**
	 * Option name for a findings key.
	 *
	 * @param  string $key Findings key.
	 * @return string      Option name.
	 */
	public static function option_name( string $key ): string {
		return $key;
	}

	/**
	 * Has this run of findings aged out?
	 *
	 * @param  int $expires Expiry timestamp, or 0 for never.
	 * @param  int $now     Current timestamp.
	 * @return bool
	 */
	public static function expired( int $expires, int $now ): bool {
		return $expires > 0 && $expires <= $now;
	}

	/**
	 * Seconds of life left in a stored run.
	 *
	 * @param  int $expires Expiry timestamp, or 0 for never.
	 * @param  int $now     Current timestamp.
	 * @return int          Seconds remaining; 0 when expired or unset.
	 */
	public static function remaining( int $expires, int $now ): int {
		if ( $expires <= 0 ) {
			return 0;
		}

		return max( 0, $expires - $now );
	}

	/**
	 * Read one check's findings.
	 *
	 * Returns `false` for absent or expired, an array otherwise.
	 *
	 * @param  string $key Findings key.
	 * @return array|false Findings, or false when there are none to read.
	 */
	public static function load( string $key ) {
		$stored = get_option( self::option_name( $key ) );

		// Not ours, or never written. An envelope always has `items`.
		if ( ! is_array( $stored ) || ! array_key_exists( 'items', $stored ) ) {
			return false;
		}

		if ( self::expired( (int) ( $stored['expires'] ?? 0 ), time() ) ) {
			self::forget( $key );
			return false;
		}

		// A malformed payload reads as absent rather than as clean. Saying
		// "Excellent!" over a corrupted row would be the wrong kind of wrong.
		return is_array( $stored['items'] ) ? $stored['items'] : false;
	}

	/**
	 * Write one check's findings.
	 *
	 * @param  string   $key     Findings key.
	 * @param  array    $items   Rows to store.
	 * @param  int|null $expires Expiry timestamp, or null for TTL from now.
	 * @return array             The rows, so callers can `return Findings_Store::save( … )`.
	 */
	public static function save( string $key, array $items, ?int $expires = null ): array {
		$envelope = array(
			'items'   => $items,
			'expires' => (int) ( $expires ?? ( time() + self::TTL ) ),
			'saved'   => time(),
		);

		update_option( self::option_name( $key ), $envelope, false );

		$keys = self::keys();

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::INDEX, $keys, false );
		}

		return $items;
	}

	/**
	 * Drop one check's findings.
	 *
	 * Prunes the index too, so `keys()` stays an honest list of what exists and a
	 * `--fix-it` run does not leave a name pointing at a deleted option.
	 *
	 * @param  string $key Findings key.
	 * @return void
	 */
	public static function forget( string $key ): void {
		delete_option( self::option_name( $key ) );

		$keys = self::keys();
		$kept = array_values( array_diff( $keys, array( $key ) ) );

		// Only write when something actually changed; forget() is called on every
		// expired read.
		if ( count( $kept ) !== count( $keys ) ) {
			update_option( self::INDEX, $kept, false );
		}
	}

	/**
	 * Every key with findings stored.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		$keys = get_option( self::INDEX );

		if ( ! is_array( $keys ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
	}

	/**
	 * When were these findings written?
	 *
	 * @param  string $key Findings key.
	 * @return int         Unix timestamp, or 0.
	 */
	public static function saved_at( string $key ): int {
		$stored = get_option( self::option_name( $key ) );

		return is_array( $stored ) ? (int) ( $stored['saved'] ?? 0 ) : 0;
	}
}
