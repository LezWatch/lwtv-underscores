<?php
/**
 * Where a check's findings live.
 *
 * A non-autoloaded option per check plus a small index, which is the same shape
 * Status and Baseline_Store already use. Findings used to be transients, and the
 * move off them is not a tidying exercise -- it fixes a class of bug that cost a
 * morning to find.
 *
 * A transient is a *cache*: something derived, with a cheaper source to fall
 * back to. Findings are not that. `Watch_URLs` costs a hundred-odd HTTP requests
 * over several minutes, so there is no cheaper source, and a read that misses
 * does not mean "recompute" -- it means the report is gone.
 *
 * What that cost in practice: on production WP-CLI does not load the object-cache
 * drop-in that web requests use. `set_transient()` from a cron scan therefore
 * wrote to `wp_options`, while wp-admin's `get_transient()` asked the object
 * cache and got nothing -- no DB fallback, by design. The scan found 58 broken
 * provider URLs, stored them, recorded the count in its status *option*, and the
 * tab said "This check has not run". The status option was visible because
 * options do not care which cache tier a process has; the findings were not.
 *
 * An option cannot go missing that way, and a cache flush cannot throw away a
 * few hundred HTTP requests' worth of work.
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
	 *
	 * Deliberately not a prefix of the keys themselves. Findings keys are used
	 * verbatim as option names (see option_name()), so an index named after a
	 * shared prefix could be written over by a check whose key happened to match
	 * it -- the trap Status::INDEX documents.
	 */
	const INDEX = 'lwtv_debug_findings_keys';

	/**
	 * How long findings stay readable.
	 *
	 * Ten days, not seven, and the difference is the whole point: the debug
	 * rotation runs each check once a week, so a seven-day life expires the
	 * findings exactly as the next run comes due. One missed run -- a deploy, a
	 * failed cron, a slow night -- and the report is empty until the following
	 * week for no reason at all.
	 *
	 * Not longer, either. The expiry is what makes a check nobody has looked at
	 * rebuild itself rather than showing figures from spring. Ten days survives
	 * one missed weekly run and not much more, which is exactly the slack wanted.
	 */
	const TTL = 10 * DAY_IN_SECONDS;

	/**
	 * Option name for a findings key.
	 *
	 * The key is used verbatim. It is safe: WordPress stores a transient under
	 * `_transient_{key}`, so an option named `{key}` cannot collide with the
	 * transient of the same name -- which is also what lets the migration read
	 * the old row and write the new one without renaming anything.
	 *
	 * Pure, and its own method rather than inline concatenation, so the one place
	 * that decides where findings live can be tested and can grow a prefix later
	 * without hunting call sites.
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
	 * Pure, and takes `$now` rather than calling time(), so the boundary is
	 * testable. An `$expires` of 0 means no expiry -- nothing writes that today,
	 * but a stored row from a future caller that omits the stamp should read as
	 * live rather than silently vanishing.
	 *
	 * The comparison is `<=`, matching get_transient(): a row whose expiry is
	 * this exact second is gone, not live for one more.
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
	 * Pure. The migration uses it to carry the remaining TTL across rather than
	 * resetting the clock, so moving storage does not silently extend how long a
	 * stale report is presented as current.
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
	 * Returns `false` for absent or expired, an array otherwise -- the contract
	 * get_transient() had, because nine call sites are written against it and two
	 * of them treat `false` as "go and scan".
	 *
	 * Expired findings are deleted on read. Same self-cleaning behaviour a
	 * transient had, and without it the rows would accumulate: no web request
	 * takes the DB path on a site with an object cache, so WordPress's own
	 * expired-transient sweep never ran on these.
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
	 * Not autoloaded. Only wp-admin and WP-CLI ever read findings, so there is no
	 * reason to carry a few dozen KB of them on every front-end request.
	 *
	 * `$expires` is for the migration, which has an existing expiry to preserve.
	 * Everything else omits it and gets TTL from now, because the lifetime of a
	 * report is a fact about findings rather than a number each caller picks --
	 * the reasoning Scan::store() documents about coming through one door.
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
