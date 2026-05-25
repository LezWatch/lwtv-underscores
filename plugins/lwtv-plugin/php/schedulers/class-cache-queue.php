<?php
/**
 * Cache Queue System
 *
 * Queues cache invalidation operations and processes them on shutdown
 * to provide instant save operations with background cache clearing
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Cache;

/**
 * Class Cache_Queue
 */
class Cache_Queue {

	/**
	 * URLs to clear on shutdown
	 *
	 * @var array
	 */
	private static $urls_to_clear = array();

	/**
	 * Post IDs to process on shutdown
	 *
	 * @var array
	 */
	private static $post_ids_to_process = array();

	/**
	 * Initialize the cache queue
	 */
	public function __construct() {
		// Register shutdown hook to process queued cache operations
		add_action( 'shutdown', array( $this, 'process_on_shutdown' ) );
	}

	/**
	 * Queue a post for cache invalidation
	 *
	 * @param int $post_id The post ID to queue
	 * @return void
	 */
	public static function queue( int $post_id ): void {
		// Add to post IDs to process
		self::$post_ids_to_process[] = $post_id;

		// Collect URLs for this post
		$urls = ( new Cache() )->collect_cache_urls_for_actors_or_shows( $post_id );

		if ( ! empty( $urls ) ) {
			self::$urls_to_clear = array_merge( self::$urls_to_clear, $urls );
		}

		// Invalidate object cache (Redis) for this post and related posts
		( new Cache() )->invalidate_object_cache_for_related_posts( $post_id );

		lwtv_plugin()->error_log( 'cache-queue', "Queued cache invalidation for post ID: {$post_id}" );
	}

	/**
	 * Process queued cache operations on shutdown
	 *
	 * @return void
	 */
	public function process_on_shutdown(): void {
		if ( empty( self::$post_ids_to_process ) ) {
			return;
		}

		lwtv_plugin()->debug_log( 'caching', 'Processing cache queue on shutdown - ' . count( self::$post_ids_to_process ) . ' posts, ' . count( self::$urls_to_clear ) . ' URLs' );

		// Remove duplicates
		$unique_urls = array_unique( self::$urls_to_clear );

		// Process cache invalidation
		if ( ! empty( $unique_urls ) ) {
			( new Cache() )->clean_any_urls( $unique_urls );
			lwtv_plugin()->debug_log( 'caching', 'Successfully cleared cache for ' . count( $unique_urls ) . ' URLs' );
		}

		// Clear the queues
		self::$urls_to_clear       = array();
		self::$post_ids_to_process = array();
	}

	/**
	 * Get current queue status (for debugging)
	 *
	 * @return array
	 */
	public static function get_queue_status(): array {
		return array(
			'post_ids' => self::$post_ids_to_process,
			'urls'     => self::$urls_to_clear,
			'count'    => count( self::$post_ids_to_process ),
		);
	}
}
