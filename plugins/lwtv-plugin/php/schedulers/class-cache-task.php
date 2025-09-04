<?php
/**
 * Cache Task Handler
 *
 * Handles deferred cache invalidation to improve save performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\Plugins\Cache;

/**
 * Class Cache_Task
 */
class Cache_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'lwtv_cache_task', array( $this, 'process_cache_task' ) );
	}

	/**
	 * Process the scheduled cache task
	 *
	 * @param int $post_id The post ID to process
	 * @return void
	 */
	public function process_cache_task( int $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			lwtv_plugin()->error_log( 'cache-task', "Invalid post ID: {$post_id}" );
			return;
		}

		lwtv_plugin()->error_log( 'cache-task', "Processing cache task for {$post_type} ID: {$post_id}" );

		// Get a list of URLs to flush
		$clear_urls = ( new Cache() )->collect_cache_urls_for_actors_or_shows( $post_id );

		// If we've got a list of URLs, then flush.
		if ( isset( $clear_urls ) && ! empty( $clear_urls ) ) {
			( new Cache() )->clean_related_urls_for_cpts( $post_id, $clear_urls );
			$clear_url_count = count( $clear_urls );
			lwtv_plugin()->error_log( 'cache-task', "Successfully cleared cache for {$post_type} ID: {$post_id} - {$clear_url_count} URLs" );
		} else {
			lwtv_plugin()->error_log( 'cache-task', "No cache URLs to clear for {$post_type} ID: {$post_id}" );
		}
	}
}
