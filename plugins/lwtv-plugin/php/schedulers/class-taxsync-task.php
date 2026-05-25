<?php
/**
 * Taxonomy Sync Task Handler
 *
 * Handles deferred taxonomy synchronization to improve save/publish performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Shows;
use LWTV\CPTs\Characters;
use LWTV\Plugins\CMB2;

/**
 * Class Taxsync_Task
 */
class Taxsync_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register Action Scheduler hook
		add_action( 'lwtv_taxsync_task', array( $this, 'process_taxsync_task' ) );
	}

	/**
	 * Process the scheduled taxonomy sync task
	 *
	 * @param int $post_id The post ID to process
	 * @return void
	 */
	public function process_taxsync_task( int $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			lwtv_plugin()->debug_log( 'taxsync', "Invalid post ID: {$post_id}" );
			return;
		}

		// Check if post still exists and is published
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			lwtv_plugin()->debug_log( 'taxsync', "Skipping taxonomy sync for {$post_type} ID: {$post_id} - post not published or doesn't exist" );
			return;
		}

		lwtv_plugin()->debug_log( 'taxsync', "Processing taxonomy sync task for {$post_type} ID: {$post_id}" );

		$success     = false;
		$error_count = 0;

		try {
			// Process taxonomy sync based on post type
			switch ( $post_type ) {
				case 'post_type_actors':
					$success = $this->process_actor_taxonomy_sync( $post_id );
					break;
				case 'post_type_shows':
					$success = $this->process_show_taxonomy_sync( $post_id );
					break;
				case 'post_type_characters':
					$success = $this->process_character_taxonomy_sync( $post_id );
					break;
				default:
					lwtv_plugin()->debug_log( 'taxsync', "Unsupported post type: {$post_type} for ID: {$post_id}" );
					return;
			}

			if ( $success ) {
				lwtv_plugin()->debug_log( 'taxsync', "Successfully completed taxonomy sync for {$post_type} ID: {$post_id}" );
			} else {
				$this->handle_taxsync_failure( $post_id, $post_type, 'Unknown error during taxonomy sync' );
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxsync', 'Error processing taxonomy sync for {$post_type} ID: {$post_id}: ' . $e->getMessage() );
			$this->handle_taxsync_failure( $post_id, $post_type, $e->getMessage() );
		}
	}

	/**
	 * Process taxonomy sync for actors
	 *
	 * @param int $post_id The actor post ID
	 * @return bool Whether the sync was successful
	 */
	private function process_actor_taxonomy_sync( int $post_id ): bool {
		lwtv_plugin()->debug_log( 'taxsync', "Processing actor taxonomy sync for ID: {$post_id}" );

		// Get actor taxonomy mappings from the Actors class
		$taxonomy_mappings = Actors::SELECT2_TAXONOMIES;

		if ( empty( $taxonomy_mappings ) ) {
			lwtv_plugin()->debug_log( 'taxsync', "No taxonomy mappings found for actor ID: {$post_id}" );
			return true; // No mappings to sync, consider it successful
		}

		$success_count = 0;
		$total_count   = count( $taxonomy_mappings );

		// Process each taxonomy mapping
		foreach ( $taxonomy_mappings as $postmeta => $taxonomy ) {
			try {
				( new CMB2() )->select2_taxonomy_save( $post_id, $postmeta, $taxonomy );
				++$success_count;
				lwtv_plugin()->debug_log( 'taxsync', "Synced taxonomy {$taxonomy} for actor ID: {$post_id}" );
			} catch ( \Exception $e ) {
				lwtv_plugin()->error_log( 'taxsync', "Failed to sync taxonomy {$taxonomy} for actor ID: {$post_id}: " . $e->getMessage() );
			}
		}

		$success = ( $success_count === $total_count );
		lwtv_plugin()->debug_log( 'taxsync', "Completed actor taxonomy sync for ID: {$post_id} - {$success_count}/{$total_count} successful" );

		return $success;
	}

	/**
	 * Process taxonomy sync for shows
	 *
	 * @param int $post_id The show post ID
	 * @return bool Whether the sync was successful
	 */
	private function process_show_taxonomy_sync( int $post_id ): bool {
		lwtv_plugin()->debug_log( 'taxsync', "Processing show taxonomy sync for ID: {$post_id}" );

		// Get show taxonomy mappings from the Shows class
		$taxonomy_mappings = Shows::SELECT2_TAXONOMIES;

		if ( empty( $taxonomy_mappings ) ) {
			lwtv_plugin()->debug_log( 'taxsync', "No taxonomy mappings found for show ID: {$post_id}" );
			return true; // No mappings to sync, consider it successful
		}

		$success_count = 0;
		$total_count   = count( $taxonomy_mappings );

		// Process each taxonomy mapping
		foreach ( $taxonomy_mappings as $postmeta => $taxonomy ) {
			try {
				( new CMB2() )->select2_taxonomy_save( $post_id, $postmeta, $taxonomy );
				++$success_count;
				lwtv_plugin()->debug_log( 'taxsync', "Synced taxonomy {$taxonomy} for show ID: {$post_id}" );
			} catch ( \Exception $e ) {
				lwtv_plugin()->error_log( 'taxsync', "Failed to sync taxonomy {$taxonomy} for show ID: {$post_id}: " . $e->getMessage() );
			}
		}

		$success = ( $success_count === $total_count );
		lwtv_plugin()->debug_log( 'taxsync', "Completed show taxonomy sync for ID: {$post_id} - {$success_count}/{$total_count} successful" );

		return $success;
	}

	/**
	 * Process taxonomy sync for characters
	 *
	 * @param int $post_id The character post ID
	 * @return bool Whether the sync was successful
	 */
	private function process_character_taxonomy_sync( int $post_id ): bool {
		lwtv_plugin()->debug_log( 'taxsync', "Processing character taxonomy sync for ID: {$post_id}" );

		// Character taxonomy mappings
		$taxonomy_mappings = Characters::SELECT2_TAXONOMIES;

		if ( empty( $taxonomy_mappings ) ) {
			lwtv_plugin()->debug_log( 'taxsync', "No taxonomy mappings found for character ID: {$post_id}" );
			return true;
		}

		$success_count = 0;
		$total_count   = count( $taxonomy_mappings );

		// Process each taxonomy mapping
		foreach ( $taxonomy_mappings as $postmeta => $taxonomy ) {
			try {
				( new CMB2() )->select2_taxonomy_save( $post_id, $postmeta, $taxonomy );
				++$success_count;
				lwtv_plugin()->debug_log( 'taxsync', "Synced taxonomy {$taxonomy} for character ID: {$post_id}" );
			} catch ( \Exception $e ) {
				lwtv_plugin()->error_log( 'taxsync', "Failed to sync taxonomy {$taxonomy} for character ID: {$post_id}: " . $e->getMessage() );
			}
		}

		$success = ( $success_count === $total_count );
		lwtv_plugin()->debug_log( 'taxsync', "Completed character taxonomy sync for ID: {$post_id} - {$success_count}/{$total_count} successful" );

		return $success;
	}

	/**
	 * Handle taxonomy sync failure with retry logic
	 *
	 * @param int    $post_id   The post ID that failed
	 * @param string $post_type The post type
	 * @param string $error_msg The error message
	 * @return void
	 */
	private function handle_taxsync_failure( int $post_id, string $post_type, string $error_msg ): void {
		$retry_count = get_post_meta( $post_id, '_lwtv_taxsync_retry_count', true );
		$retry_count = (int) $retry_count;
		$max_retries = 3;

		lwtv_plugin()->debug_log( 'taxsync', "Taxonomy sync failed for {$post_type} ID: {$post_id} - {$error_msg} (attempt {$retry_count}/{$max_retries})" );

		if ( $retry_count < $max_retries ) {
			// Increment retry count
			update_post_meta( $post_id, '_lwtv_taxsync_retry_count', $retry_count + 1 );

			// Schedule retry with exponential backoff (30s, 60s, 120s)
			$delay = 30 * pow( 2, $retry_count );
			lwtv_plugin()->schedule_task( 'taxsync', $post_id, 0, $delay );

			lwtv_plugin()->debug_log( 'taxsync', "Scheduled retry {$retry_count} for {$post_type} ID: {$post_id} in {$delay} seconds" );
		} else {
			// Max retries reached, log final failure
			lwtv_plugin()->debug_log( 'taxsync', "Taxonomy sync permanently failed for {$post_type} ID: {$post_id} after {$max_retries} attempts" );

			// Store failure info for admin review
			update_post_meta(
				$post_id,
				'_lwtv_taxsync_failed',
				array(
					'error'       => $error_msg,
					'timestamp'   => current_time( 'mysql' ),
					'retry_count' => $retry_count,
				)
			);

			// Clean up retry count
			delete_post_meta( $post_id, '_lwtv_taxsync_retry_count' );
		}
	}
}
