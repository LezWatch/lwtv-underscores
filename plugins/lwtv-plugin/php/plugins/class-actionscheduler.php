<?php

/**
 * ActionScheduler Plugin
 *
 * Custom code for ActionScheduler
 *
 * References:
 * - https://actionscheduler.org/perf/
 *
 * @package LezWatch.TV Plugin
 *
 */

namespace LWTV\Plugins;

class ActionScheduler {

	/**
	 * Cleanup batch size
	 *
	 * @var int
	 */
	private $cleanup_batch_size = 50;

	/**
	 * Retention period
	 *
	 * @var int
	 */
	private $retention_period = 3 * DAY_IN_SECONDS;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Adjust batch size
		add_filter(
			'action_scheduler_cleanup_batch_size',
			function () {
				return $this->cleanup_batch_size;
			}
		);

		// Adjust retention period
		add_filter(
			'action_scheduler_retention_period',
			function () {
				return $this->retention_period;
			}
		);

		// Remove logging from ignored actions
		add_action( 'init', array( $this, 'remove_ignored_action_logging' ) );

		// Tell AS to clean up failed actions
		add_filter(
			'action_scheduler_default_cleaner_statuses',
			function ( $statuses ) {
				$statuses[] = 'failed';
				return $statuses;
			}
		);

		// Set the next scan time to 60 minutes from now for aioseo_seo_analyzer_posts_next_scan
		add_filter(
			'aioseo_seo_analyzer_posts_next_scan',
			function ( $next_scan ) {
				$next_scan = strtotime( '+60 minutes' );
				return $next_scan;
			}
		);

		// Set the next scan time to 60 minutes from now for aioseo_seo_analyzer_terms_next_scan
		add_filter(
			'aioseo_seo_analyzer_terms_next_scan',
			function ( $next_scan ) {
				$next_scan = strtotime( '+60 minutes' );
				return $next_scan;
			}
		);
	}

	/**
	 * Remove logging from ignored actions
	 *
	 * @return void
	 */
	public function remove_ignored_action_logging() {
		$logger = \ActionScheduler_Logger::instance();
		remove_action( 'action_scheduler_execution_ignored', array( $logger, 'log_ignored_action' ), 10, 2 );
	}
}
