<?php
/**
 * Main Scheduler Component
 *
 * Handles all deferred operations to improve save performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Components;

use LWTV\Schedulers\TMDB_Task;
use LWTV\Schedulers\Cache_Task;
use LWTV\Schedulers\Cache_Queue;
use LWTV\Schedulers\Calculation_Task;

/**
 * Class Scheduler
 */
class Scheduler implements Component, Templater {

	/**
	 * Constructor
	 */
	public function init() {
		// Initialize task handlers
		new TMDB_Task();
		new Cache_Task();
		new Cache_Queue();
		new Calculation_Task();

		// Register the main cron hook
		add_action( 'lwtv_process_deferred_tasks', array( $this, 'process_deferred_tasks' ) );
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'schedule_task' => array( $this, 'schedule_task' ),
			'cache_queue'   => array( $this, 'cache_queue' ),
		);
	}

	/**
	 * Schedule a deferred task
	 *
	 * @param string $task_type The type of task to schedule
	 * @param int    $post_id  The post ID to process
	 * @param int    $delay    Delay in seconds (default: 30)
	 * @return bool  Whether the task was scheduled successfully
	 */
	public function schedule_task( string $task_type, int $post_id, int $delay = 30 ): bool {
		$hook_name = 'lwtv_' . $task_type . '_task_' . $post_id;

		// Schedule the task
		$scheduled = wp_schedule_single_event( time() + $delay, $hook_name, array( $post_id ) );

		if ( $scheduled ) {
			lwtv_plugin()->error_log( 'scheduler', "Scheduled {$task_type} task for post ID: {$post_id} with {$delay}s delay" );
		} else {
			lwtv_plugin()->error_log( 'scheduler', "Failed to schedule {$task_type} task for post ID: {$post_id}" );
		}

				return $scheduled;
	}

	/**
	 * Queue a post for immediate cache invalidation on shutdown
	 *
	 * @param int $post_id The post ID to queue
	 * @return void
	 */
	public function cache_queue( int $post_id ): void {
		Cache_Queue::queue( $post_id );
	}

	/**
	 * Process deferred tasks (main cron handler)
	 *
	 * @return void
	 */
	public function process_deferred_tasks(): void {
		lwtv_plugin()->error_log( 'scheduler', 'Processing deferred tasks' );

		// This method can be expanded to handle task queuing and prioritization
		// For now, individual task classes handle their own processing
	}
}
