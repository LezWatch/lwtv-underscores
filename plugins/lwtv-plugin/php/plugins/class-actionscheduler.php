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
	 * Concurrent queues
	 *
	 * @var int
	 */
	private $concurrent_queues = 2;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Remove logging from ignored actions
		add_action( 'init', array( $this, 'remove_ignored_action_logging' ) );

		$this->run_filters();
	}

	/**
	 * Run filters
	 */
	private function run_filters() {
		$this->cleanup_batch_size();
		$this->retention_period();
		$this->default_cleaner_statuses();
		$this->aioseo_seo_analyzer_next_scan();
		$this->concurrent_queues();
	}

	/**
	 * Adjust batch size
	 */
	private function cleanup_batch_size() {
		add_filter(
			'action_scheduler_cleanup_batch_size',
			function () {
				return $this->cleanup_batch_size;
			}
		);
	}

	/**
	 * Adjust retention period
	 */
	private function retention_period() {
		add_filter(
			'action_scheduler_retention_period',
			function () {
				return $this->retention_period;
			}
		);
	}

	/**
	 * Tell AS to clean up failed actions
	 */
	private function default_cleaner_statuses() {
		add_filter(
			'action_scheduler_default_cleaner_statuses',
			function ( $statuses ) {
				$statuses[] = 'failed';
				return $statuses;
			}
		);
	}

	/**
	 * Set the next scan time to 60 minutes from now
	 *
	 * This is a hack to get around the way that the AIOSEO will schedule things.
	 *
	 * Source: Company Slack
	 */
	private function aioseo_seo_analyzer_next_scan() {
		add_filter(
			'aioseo_seo_analyzer_posts_next_scan',
			function ( $next_scan ) {
				$next_scan = strtotime( '+60 minutes' );
				return $next_scan;
			}
		);

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

	/**
	 * Adjust concurrent queues
	 */
	private function concurrent_queues() {
		add_filter(
			'action_scheduler_queue_runner_concurrent_batches',
			function () {
				return $this->concurrent_queues;
			}
		);
	}
}
