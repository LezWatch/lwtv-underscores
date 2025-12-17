<?php
/**
 * Cache Batch Task
 *
 * Handles batch processing of cache invalidation operations using Action Scheduler
 * Replaces the shutdown-based cache queue with reliable background processing
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\Plugins\Cache;

/**
 * Class Cache_Batch_Task
 */
class Cache_Batch_Task {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_cache_batch_task';

	/**
	 * Action Scheduler group name
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Batch size for processing URLs (optimized for 4-core, 4GB server)
	 */
	const BATCH_SIZE = 75;

	/**
	 * Delay between batches in seconds
	 */
	const DELAY_BETWEEN_BATCHES = 2;

	/**
	 * Transient key for cache queue
	 */
	const QUEUE_TRANSIENT = 'lwtv_cache_batch_queue';

	/**
	 * Transient key for processing status
	 */
	const STATUS_TRANSIENT = 'lwtv_cache_batch_status';

	/**
	 * Priority levels based on data relationships
	 */
	const PRIORITY_LEVELS = array(
		'CRITICAL' => 1, // Homepage, stats pages, archive pages
		'HIGH'     => 2, // Shows (affects character counts, actor stats)
		'MEDIUM'   => 3, // Characters (affects show counts, actor stats)
		'LOW'      => 4, // Actors (affects character counts, show stats)
		'CASCADE'  => 5, // Shadow taxonomy updates, stat recalculations
	);

	/**
	 * Post type priority mapping
	 */
	const POST_TYPE_PRIORITIES = array(
		'post_type_shows'      => 'HIGH',
		'post_type_characters' => 'MEDIUM',
		'post_type_actors'     => 'LOW',
	);

	/**
	 * Initialize the cache batch task
	 */
	public function __construct() {
		// Only initialize if Action Scheduler is available
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->debug_log( 'scheduler', 'Action Scheduler not available, skipping batch task handler initialization' );
			return;
		}

		// Register Action Scheduler hook
		add_action( self::AS_HOOK, array( $this, 'process_cache_batch' ) );
	}

	/**
	 * Queue a post for cache invalidation
	 *
	 * @param int $post_id The post ID to queue
	 * @return bool True if successfully queued, false otherwise
	 */
	public function queue_post( int $post_id ): bool {
		// Get existing queue
		$queue = $this->get_queue();

		// Add post to queue with priority if not already present
		if ( ! $this->is_post_in_queue( $post_id, $queue ) ) {
			$priority   = $this->get_post_priority( $post_id );
			$queue_item = array(
				'post_id'   => $post_id,
				'priority'  => $priority,
				'timestamp' => time(),
			);

			$queue[] = $queue_item;
			$this->set_queue( $queue );

			lwtv_plugin()->debug_log( 'caching', "Queued cache invalidation for post ID: {$post_id} with priority: {$priority}" );
		}

		// Schedule processing if not already scheduled
		if ( ! $this->is_processing_scheduled() ) {
			as_schedule_single_action( time(), self::AS_HOOK, array(), self::AS_GROUP );

			$this->update_status(
				array(
					'next_processing' => time(),
					'queued_count'    => count( $queue ),
				)
			);

			lwtv_plugin()->debug_log( 'caching', 'Scheduled cache batch processing' );
		}

		return true;
	}

	/**
	 * Process cache batch using Action Scheduler
	 *
	 * @return void
	 */
	public function process_cache_batch(): void {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->debug_log( 'caching', 'Action Scheduler not available for cache batch processing' );
			return;
		}

		$queue = $this->get_queue();

		if ( empty( $queue ) ) {
			lwtv_plugin()->debug_log( 'caching', 'Cache batch queue is empty' );
			$this->clear_status();
			return;
		}

		lwtv_plugin()->debug_log( 'caching', 'Processing cache batch - ' . count( $queue ) . ' posts' );

		// Sort queue by priority (CRITICAL first, then HIGH, MEDIUM, LOW, CASCADE)
		$sorted_queue = $this->sort_queue_by_priority( $queue );

		// Collect all URLs for queued posts in priority order
		$all_urls        = array();
		$processed_posts = array();

		foreach ( $sorted_queue as $item ) {
			$post_id  = $item['post_id'];
			$priority = $item['priority'];

			lwtv_plugin()->debug_log( 'caching', "Processing post ID: {$post_id} with priority: {$priority}" );

			$urls = ( new Cache() )->collect_cache_urls_for_actors_or_shows( $post_id );
			if ( ! empty( $urls ) ) {
				$all_urls = array_merge( $all_urls, $urls );
			}
			$processed_posts[] = $post_id;
		}

		// Remove duplicates
		$unique_urls = array_unique( $all_urls );

		if ( ! empty( $unique_urls ) ) {
			// Process URLs in batches
			$url_batches = array_chunk( $unique_urls, self::BATCH_SIZE );

			foreach ( $url_batches as $batch_index => $url_batch ) {
				$result = $this->process_url_batch( $url_batch );

				lwtv_plugin()->debug_log( 'caching', 'Processed URL batch ' . ( $batch_index + 1 ) . ' of ' . count( $url_batches ) . ' - ' . count( $url_batch ) . ' URLs' );

				// Add delay between batches to prevent overwhelming cache systems
				if ( $batch_index < count( $url_batches ) - 1 ) {
					sleep( self::DELAY_BETWEEN_BATCHES );
				}
			}
		}

		// Clear processed posts from queue
		$remaining_queue = array_filter(
			$queue,
			function ( $item ) use ( $processed_posts ) {
				return ! in_array( $item['post_id'], $processed_posts, true );
			}
		);
		$this->set_queue( $remaining_queue );

		// Update status
		$this->update_status(
			array(
				'last_processed'  => time(),
				'processed_count' => count( $processed_posts ),
				'urls_cleared'    => count( $unique_urls ),
				'queued_count'    => count( $remaining_queue ),
			)
		);

		// Schedule next processing if queue is not empty
		if ( ! empty( $remaining_queue ) ) {
			as_schedule_single_action( time() + 30, self::AS_HOOK, array(), self::AS_GROUP ); // 30 second delay
			$this->update_status(
				array(
					'next_processing' => time() + 30,
				)
			);

			lwtv_plugin()->debug_log( 'caching', 'Scheduled next cache batch processing - ' . count( $remaining_queue ) . ' posts remaining' );
		} else {
			$this->clear_status();
			lwtv_plugin()->debug_log( 'caching', 'Cache batch processing completed' );
		}
	}

	/**
	 * Process a batch of URLs
	 *
	 * @param array $urls Array of URLs to clear
	 * @return bool True if successful, false otherwise
	 */
	private function process_url_batch( array $urls ): bool {
		try {
			( new Cache() )->clean_any_urls( $urls );
			return true;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'caching', 'Error processing URL batch: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the current cache queue
	 *
	 * @return array Array of post IDs
	 */
	private function get_queue(): array {
		$queue = lwtv_plugin()->get_transient( self::QUEUE_TRANSIENT );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Set the cache queue
	 *
	 * @param array $queue Array of post IDs
	 * @return void
	 */
	private function set_queue( array $queue ): void {
		lwtv_plugin()->set_transient( self::QUEUE_TRANSIENT, $queue, HOUR_IN_SECONDS );
	}

	/**
	 * Check if processing is already scheduled
	 *
	 * @return bool True if scheduled, false otherwise
	 */
	private function is_processing_scheduled(): bool {
		$next_scheduled = as_next_scheduled_action( self::AS_HOOK );
		return false !== $next_scheduled;
	}

	/**
	 * Check if a post is already in the queue
	 *
	 * @param int $post_id The post ID to check
	 * @param array $queue The current queue array
	 * @return bool True if the post is in the queue, false otherwise
	 */
	private function is_post_in_queue( int $post_id, array $queue ): bool {
		foreach ( $queue as $item ) {
			if ( $item['post_id'] === $post_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the priority of a post for queuing
	 *
	 * @param int $post_id The post ID to get priority for
	 * @return string The priority level (CRITICAL, HIGH, MEDIUM, LOW, CASCADE)
	 */
	private function get_post_priority( int $post_id ): string {
		$post_type = get_post_type( $post_id );

		// Use post type priority mapping
		if ( isset( self::POST_TYPE_PRIORITIES[ $post_type ] ) ) {
			return self::POST_TYPE_PRIORITIES[ $post_type ];
		}

		// Default to MEDIUM priority for unknown post types
		return 'MEDIUM';
	}

	/**
	 * Sort queue by priority
	 *
	 * @param array $queue The queue to sort
	 * @return array The sorted queue
	 */
	private function sort_queue_by_priority( array $queue ): array {
		usort(
			$queue,
			function ( $a, $b ) {
				$priority_a = self::PRIORITY_LEVELS[ $a['priority'] ] ?? 999;
				$priority_b = self::PRIORITY_LEVELS[ $b['priority'] ] ?? 999;

				// Sort by priority (lower number = higher priority)
				if ( $priority_a !== $priority_b ) {
					return $priority_a - $priority_b;
				}

				// If same priority, sort by timestamp (older first)
				return $a['timestamp'] - $b['timestamp'];
			}
		);

		return $queue;
	}

	/**
	 * Get cache batch status
	 *
	 * @return array Status information
	 */
	public function get_batch_status(): array {
		$queue          = $this->get_queue();
		$status         = lwtv_plugin()->get_transient( self::STATUS_TRANSIENT );
		$next_scheduled = as_next_scheduled_action( self::AS_HOOK );

		// Extract post IDs from queue items for backward compatibility
		$queued_post_ids = array_map(
			function ( $item ) {
				return $item['post_id'];
			},
			$queue
		);

		// Group by priority for status display
		$priority_groups = array();
		foreach ( $queue as $item ) {
			$priority = $item['priority'];
			if ( ! isset( $priority_groups[ $priority ] ) ) {
				$priority_groups[ $priority ] = array();
			}
			$priority_groups[ $priority ][] = $item['post_id'];
		}

		return array(
			'queued_posts'               => $queued_post_ids,
			'queued_count'               => count( $queue ),
			'priority_groups'            => $priority_groups,
			'next_scheduled'             => $next_scheduled,
			'action_scheduler_available' => lwtv_plugin()->is_action_scheduler_available(),
			'last_processed'             => isset( $status['last_processed'] ) ? $status['last_processed'] : null,
			'processed_count'            => isset( $status['processed_count'] ) ? $status['processed_count'] : 0,
			'urls_cleared'               => isset( $status['urls_cleared'] ) ? $status['urls_cleared'] : 0,
		);
	}

	/**
	 * Update status information
	 *
	 * @param array $status Status data to update
	 * @return void
	 */
	private function update_status( array $status ): void {
		$current_status = lwtv_plugin()->get_transient( self::STATUS_TRANSIENT );
		$current_status = is_array( $current_status ) ? $current_status : array();

		$updated_status = array_merge( $current_status, $status );
		lwtv_plugin()->set_transient( self::STATUS_TRANSIENT, $updated_status, HOUR_IN_SECONDS );
	}

	/**
	 * Clear status information
	 *
	 * @return void
	 */
	private function clear_status(): void {
		lwtv_plugin()->delete_transient( self::STATUS_TRANSIENT );
	}

	/**
	 * Clear the entire cache queue
	 *
	 * @return bool True if successful, false otherwise
	 */
	public function clear_queue(): bool {
		$this->set_queue( array() );
		$this->clear_status();

		// Cancel any scheduled actions
		if ( lwtv_plugin()->is_action_scheduler_available() ) {
			as_unschedule_all_actions( self::AS_HOOK );
		}

		lwtv_plugin()->debug_log( 'caching', 'Cache batch queue cleared' );
		return true;
	}

	/**
	 * Trigger immediate processing
	 *
	 * @return bool True if triggered, false otherwise
	 */
	public function trigger_processing(): bool {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			return false;
		}

		$queue = $this->get_queue();
		if ( empty( $queue ) ) {
			return false;
		}

		// Schedule immediate processing
		as_schedule_single_action( time(), self::AS_HOOK, array(), self::AS_GROUP );

		$this->update_status(
			array(
				'next_processing' => time(),
			)
		);

		lwtv_plugin()->debug_log( 'caching', 'Triggered immediate cache batch processing' );
		return true;
	}
}
