<?php

/**
 * ActionScheduler Plugin
 *
 * Custom code for ActionScheduler
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

	public function __construct() {
		add_filter( 'action_scheduler_cleanup_batch_size', $this->cleanup_batch_size );
		add_filter( 'action_scheduler_retention_period', $this->retention_period );

		// https://actionscheduler.org/perf/
		add_filter(
			'action_scheduler_default_cleaner_statuses',
			function ( $statuses ) {
				$statuses[] = 'failed';
				return $statuses;
			}
		);

		add_action( 'current_screen', $this->restore_screen_title(), 1 );
	}

	/**
	 * Restore the screen title
	 *
	 * @return void
	 */
	private function restore_screen_title() {
		$screen = get_current_screen();
		if ( $screen && 'tools_page_action-scheduler' === $screen->id && empty( $GLOBALS['title'] ) ) {
			//phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['title'] = __( 'Scheduled Actions', 'action-scheduler' );
		}
	}
}
