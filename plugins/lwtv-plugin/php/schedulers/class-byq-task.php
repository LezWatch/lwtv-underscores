<?php
/**
 * BYQ Task
 *
 * Handles scheduled cache regeneration for Bury Your Queers death data
 * Uses Action Scheduler for reliable background processing
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

/**
 * Class BYQ_Task
 */
class BYQ_Task {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_byq_check_cache';

	/**
	 * Action Scheduler group name
	 */
	const AS_GROUP = 'lwtv_byq';

	/**
	 * Initialize the BYQ task
	 */
	public function __construct() {
		// Only initialize if Action Scheduler is available
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->error_log( 'scheduler', 'Action Scheduler not available, skipping BYQ task handler initialization' );
			return;
		}

		// Register Action Scheduler hook
		add_action( self::AS_HOOK, array( $this, 'process_cache_check' ), 10, 1 );
	}

	/**
	 * Schedule a cache check
	 *
	 * @param string $cache_key The cache key to check.
	 * @return bool True if scheduled successfully, false otherwise.
	 */
	public function schedule_cache_check( string $cache_key ): bool {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->error_log( 'scheduler', 'Action Scheduler not available, cannot schedule cache check' );
			return false;
		}

		as_schedule_single_action( time() + 60, self::AS_HOOK, array( $cache_key ), self::AS_GROUP );
		lwtv_plugin()->error_log( 'scheduler', 'Scheduled BYQ cache check for key: ' . $cache_key );

		return true;
	}

	/**
	 * Process the cache check
	 *
	 * @param string $cache_key The cache key to check.
	 * @return void
	 */
	public function process_cache_check( string $cache_key ): void {
		lwtv_plugin()->error_log( 'scheduler', 'Processing BYQ cache check for key: ' . $cache_key );

		// Check if cache exists
		$cached_list = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $cached_list ) {
			// Cache still doesn't exist, regenerate it
			lwtv_plugin()->error_log( 'scheduler', 'Cache does not exist, regenerating it' );
			$byq = new \LWTV\Rest_API\BYQ();
			$byq->generate_death_list_array( null, $cache_key );
		} else {
			lwtv_plugin()->error_log( 'scheduler', 'Cache exists, no regeneration needed' );
		}
	}
}
