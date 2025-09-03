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
		add_filter(
			'action_scheduler_cleanup_batch_size',
			function () {
				return $this->cleanup_batch_size;
			}
		);
		add_filter(
			'action_scheduler_retention_period',
			function () {
				return $this->retention_period;
			}
		);

		// Tell AS to clean up failed actions
		add_filter(
			'action_scheduler_default_cleaner_statuses',
			function ( $statuses ) {
				$statuses[] = 'failed';
				return $statuses;
			}
		);
	}
}
