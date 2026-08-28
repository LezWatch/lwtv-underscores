<?php
/**
 * Watch URL Scan Task
 *
 * Runs the watch-provider URL sweep in the background, so the admin can start it
 * without holding a page request open.
 *
 * Why this exists: the check makes one HTTP request per provider URL, a hundred
 * and more of them. Every other validator tab re-scans on a cold cache during the
 * page load, and this one cannot -- that is the whole reason it has a "no results
 * yet" state at all. So the Run Scan button queues the work here instead of doing
 * it inline.
 *
 * Action Scheduler rather than WP-Cron, matching the other batch tasks: it has its
 * own request loop, its own retry behaviour, and it does not depend on someone
 * visiting the site at the right moment.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watching\Watch_Hosts;
use LWTV\Debugger\Watch_URLs;

/**
 * Class Watch_URLs_Task
 */
class Watch_URLs_Task {

	/**
	 * Action Scheduler hook name.
	 */
	const AS_HOOK = 'lwtv_watch_urls_scan';

	/**
	 * Action Scheduler group name.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Wall-clock budget for one pass, in seconds.
	 *
	 * Generous, because nothing is waiting on it -- but still bounded, and that
	 * matters more than the number. `find_bad_watch_urls()` only writes its
	 * findings at the very end, so a pass killed by a time limit stores nothing
	 * and the whole run is wasted. Stopping ourselves first means every pass
	 * banks its work, with whatever it did not reach recorded as deferred.
	 *
	 * At four minutes a typical sweep finishes in one pass; a slow night takes
	 * two, and re-queues itself.
	 */
	const BUDGET = 240;

	/**
	 * How long to wait before the first pass, in seconds.
	 *
	 * Long enough that the redirect back to the tab lands before the scan starts
	 * competing for the same database.
	 */
	const DELAY = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::AS_HOOK, array( $this, 'run' ) );
	}

	/**
	 * Is Action Scheduler here?
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' );
	}

	/**
	 * Is a pass already queued or running?
	 *
	 * @return bool
	 */
	public static function is_queued(): bool {
		return self::available() && false !== as_next_scheduled_action( self::AS_HOOK, null, self::AS_GROUP );
	}

	/**
	 * Queue a sweep.
	 *
	 * Idempotent: pressing the button twice does not schedule two sweeps, because
	 * two concurrent passes would fetch every URL twice and race each other to
	 * write the transient.
	 *
	 * @return bool True when this call queued it, false when it was already
	 *              queued or Action Scheduler is unavailable.
	 */
	public static function queue(): bool {
		if ( ! self::available() || self::is_queued() ) {
			return false;
		}

		as_schedule_single_action( time() + self::DELAY, self::AS_HOOK, array(), self::AS_GROUP );
		lwtv_plugin()->debug_log( 'shows', 'Queued a watch provider URL sweep from the admin.' );

		return true;
	}

	/**
	 * Run one pass, and queue another if it did not get through the list.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			// Belt. The budget below is the braces, and the reason the pass is
			// bounded at all.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( self::BUDGET * 2 );
		}

		$items = ( new Watch_URLs() )->find_bad_watch_urls( array(), Watch_Hosts::TIMEOUT, self::BUDGET );

		$deferred = 0;
		foreach ( (array) $items as $item ) {
			$issues = (array) ( $item['issues'] ?? array() );

			if ( in_array( 'watch-url-deferred', $issues, true ) ) {
				++$deferred;
			}
		}

		lwtv_plugin()->debug_log(
			'shows',
			sprintf( 'Watch provider URL sweep: %d finding(s), %d not reached this pass.', count( (array) $items ), $deferred )
		);

		if ( ! $deferred ) {
			return;
		}

		// More to do. Re-queue rather than looping here, so each pass gets a
		// fresh time limit and Action Scheduler stays in control of pacing.
		if ( self::available() && ! self::is_queued() ) {
			as_schedule_single_action( time() + self::DELAY, self::AS_HOOK, array(), self::AS_GROUP );
		}
	}
}
