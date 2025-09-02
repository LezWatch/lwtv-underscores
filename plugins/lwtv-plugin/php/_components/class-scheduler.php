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
use LWTV\Schedulers\TMDB_Batch_Task;
use LWTV\Schedulers\Cache_Task;
use LWTV\Schedulers\Cache_Queue;
use LWTV\Schedulers\Calculation_Task;
use LWTV\Schedulers\Cache_Batch_Task;

/**
 * Class Scheduler
 */
class Scheduler implements Component, Templater {

	/**
	 * Constructor
	 */
	public function init() {
		// Initialize task handlers with lazy loading
		$this->initialize_task_handlers();

		// Register the main cron hook
		add_action( 'lwtv_process_deferred_tasks', array( $this, 'process_deferred_tasks' ) );
	}

	/**
	 * Check if we're in a critical WordPress operation that should avoid initialization
	 *
	 * @return bool True if we should skip initialization
	 */
	private function is_critical_operation(): bool {
		// Skip during plugin activation/deactivation
		if ( defined( 'WP_PLUGIN_DIR' ) && (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'activate', 'deactivate' ), true ) ) ||
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'activate-plugin', 'deactivate-plugin' ), true ) )
		) ) {
			lwtv_plugin()->error_log( 'scheduler', 'Skipping scheduler initialization during plugin activation/deactivation' );
			return true;
		}

		// Skip during any admin action that might be memory-intensive
		if ( is_admin() && (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'activate', 'deactivate', 'install', 'update' ), true ) ) ||
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'activate-plugin', 'deactivate-plugin', 'install-plugin', 'update-plugin' ), true ) )
		) ) {
			// phpcs:ignore
			lwtv_plugin()->error_log( 'scheduler', 'Skipping scheduler initialization during admin action: ' . ( $_GET['action'] ?? $_POST['action'] ?? 'unknown' ) );
			return true;
		}

		// Skip on any admin page that might be memory-intensive (plugins, updates, etc.)
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( is_admin() && ! isset( $_GET['action'] ) && ! isset( $_POST['action'] ) ) {
			$current_screen = get_current_screen();
			if ( $current_screen && in_array( $current_screen->id, array( 'plugins', 'update-core', 'themes', 'upload' ), true ) ) {
				lwtv_plugin()->error_log( 'scheduler', 'Skipping scheduler initialization on memory-intensive admin page: ' . $current_screen->id );
				return true;
			}
		}

		// Skip during WordPress installation/upgrade
		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return true;
		}

		// Skip during database upgrades
		if ( defined( 'WP_DB_UPGRADE' ) && WP_DB_UPGRADE ) {
			return true;
		}

		// Skip if memory limit is critically low
		$memory_limit     = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$current_memory   = memory_get_usage( true );
		$memory_threshold = $memory_limit * 0.3; // 30% of memory limit

		if ( $current_memory > $memory_threshold ) {
			lwtv_plugin()->error_log( 'scheduler', 'Skipping scheduler initialization due to high memory usage: ' . size_format( $current_memory ) . ' / ' . size_format( $memory_limit ) );
			return true;
		}

		// Skip if we're already at 90%+ memory usage (emergency stop)
		if ( $current_memory > ( $memory_limit * 0.9 ) ) {
			lwtv_plugin()->error_log( 'scheduler', 'EMERGENCY: Skipping scheduler initialization due to critical memory usage: ' . size_format( $current_memory ) . ' / ' . size_format( $memory_limit ) );
			return true;
		}
		return false;
	}

	/**
	 * Initialize task handlers with lazy loading
	 *
	 * @return void
	 */
	private function initialize_task_handlers(): void {
		try {
			// Initialize task handlers with error handling
			new TMDB_Task();
			new TMDB_Batch_Task();
			new Cache_Task();
			new Cache_Queue();
			new Calculation_Task();
			new Cache_Batch_Task();

			lwtv_plugin()->error_log( 'scheduler', 'Task handlers initialized successfully' );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'scheduler', 'Error initializing task handlers: ' . $e->getMessage() );
		}
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
			'schedule_task'                 => array( $this, 'schedule_task' ),
			'cache_queue'                   => array( $this, 'cache_queue' ),
			'is_action_scheduler_available' => array( $this, 'is_action_scheduler_available' ),
			'get_scheduler_status'          => array( $this, 'get_scheduler_status' ),
			'queue_tmdb_batch'              => array( $this, 'queue_tmdb_batch' ),
			'get_tmdb_batch_status'         => array( $this, 'get_tmdb_batch_status' ),
			'queue_cache_batch'             => array( $this, 'queue_cache_batch' ),
			'get_cache_batch_status'        => array( $this, 'get_cache_batch_status' ),
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
		$task_name = 'lwtv_' . $task_type . '_task';
		$hook_name = $task_name . '_' . $post_id;

		// If Action Scheduler is active, use it with generic hook name
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$scheduled = as_schedule_single_action( time() + $delay, $task_name, array( $post_id ) );
			lwtv_plugin()->error_log( 'scheduler', "Scheduled {$task_type} task via Action Scheduler for post ID: {$post_id} with {$delay}s delay" );
		} else {
			// Fallback to WordPress cron with unique hook name
			$scheduled = wp_schedule_single_event( time() + $delay, $hook_name, array( $post_id ) );
			lwtv_plugin()->error_log( 'scheduler', "Scheduled {$task_type} task via WordPress cron for post ID: {$post_id} with {$delay}s delay" );
		}

		if ( ! $scheduled ) {
			lwtv_plugin()->error_log( 'scheduler', "Failed to schedule {$task_type} task for post ID: {$post_id}" );
		}

		return $scheduled;
	}

	/**
	 * Queue a post for cache invalidation
	 *
	 * @param int $post_id The post ID to queue
	 * @return void
	 */
	public function cache_queue( int $post_id ): void {
		// Use Action Scheduler-based cache batch processing if available
		if ( $this->is_action_scheduler_available() ) {
			$success = $this->queue_cache_batch( $post_id );
			if ( $success ) {
				return; // Successfully queued with Action Scheduler
			}
		}

		// Fallback to shutdown-based processing
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

	/**
	 * Check if Action Scheduler is available and active
	 *
	 * @return bool Whether Action Scheduler is available
	 */
	public function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Get scheduler status information
	 *
	 * @return array Status information about the scheduler
	 */
	public function get_scheduler_status(): array {
		return array(
			'action_scheduler_available' => $this->is_action_scheduler_available(),
			'wordpress_cron_enabled'     => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON,
			'current_scheduler'          => $this->is_action_scheduler_available() ? 'Action Scheduler' : 'WordPress Cron',
		);
	}

	/**
	 * Queue a post for TMDB batch processing
	 *
	 * @param int $post_id The post ID to queue
	 * @return bool Whether the post was queued successfully
	 */
	public function queue_tmdb_batch( int $post_id ): bool {
		$batch_task = new TMDB_Batch_Task();
		return $batch_task->queue_post( $post_id );
	}

	/**
	 * Get TMDB batch processing status
	 *
	 * @return array Status information about TMDB batch processing
	 */
	public function get_tmdb_batch_status(): array {
		$batch_task = new TMDB_Batch_Task();
		return $batch_task->get_batch_status();
	}

	/**
	 * Queue a post for cache batch processing
	 *
	 * @param int $post_id The post ID to queue
	 * @return bool True if successfully queued, false otherwise
	 */
	public function queue_cache_batch( int $post_id ): bool {
		$cache_batch_task = new Cache_Batch_Task();
		return $cache_batch_task->queue_post( $post_id );
	}

	/**
	 * Get cache batch processing status
	 *
	 * @return array Status information
	 */
	public function get_cache_batch_status(): array {
		$cache_batch_task = new Cache_Batch_Task();
		return $cache_batch_task->get_batch_status();
	}
}
