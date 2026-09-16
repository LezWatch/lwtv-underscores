<?php
/**
 * TMDB Task Handler
 *
 * Handles deferred TMDB API calls to improve save performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\CPTs;
use LWTV\_Helpers\Tmdb_Response;

/**
 * Class TMDB_Task
 */
class TMDB_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'lwtv_tmdb_task', array( $this, 'process_tmdb_task' ) );
	}

	/**
	 * Process the scheduled TMDB task
	 *
	 * @param int $post_id The post ID to process
	 * @return void
	 */
	public function process_tmdb_task( int $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			lwtv_plugin()->debug_log( 'tmdb', "Invalid post ID: {$post_id}" );
			return;
		}

		lwtv_plugin()->debug_log( 'tmdb', "Processing TMDB task for {$post_type} ID: {$post_id}" );

		// Check if TMDB ID is already set
		$tmdb_id = $this->get_existing_tmdb_id( $post_id, $post_type );
		if ( $tmdb_id ) {
			lwtv_plugin()->debug_log( 'tmdb', "TMDB ID already exists for {$post_type} ID: {$post_id}" );
			return;
		}

		// Get TMDB data
		$tmdb_data = ( new CPTs() )->get_tmdb_info( $post_id );

		if ( ! $tmdb_data ) {
			lwtv_plugin()->debug_log( 'tmdb', "No TMDB data found for {$post_type} ID: {$post_id}" );
			return;
		}

		// get_tmdb_info() returns a detail object or a find envelope depending on
		// our own meta; Tmdb_Response knows both.
		$tmdb_id = Tmdb_Response::id( $tmdb_data, $post_type );

		// Save TMDB ID if found
		if ( '' !== $tmdb_id ) {
			$this->save_tmdb_id( $post_id, $post_type, $tmdb_id );
			lwtv_plugin()->debug_log( 'tmdb', "Successfully saved TMDB ID: {$tmdb_id} for {$post_type} ID: {$post_id}" );
		} else {
			lwtv_plugin()->debug_log( 'tmdb', "No TMDB ID found in data for {$post_type} ID: {$post_id}" );
		}
	}

	/**
	 * Get existing TMDB ID if already set
	 *
	 * @param int    $post_id   The post ID
	 * @param string $post_type The post type
	 * @return string|false The existing TMDB ID or false
	 */
	private function get_existing_tmdb_id( int $post_id, string $post_type ) {
		$meta_key = match ( $post_type ) {
			'post_type_actors' => 'lezactors_tmdb_id',
			'post_type_shows'  => 'lezshows_tmdb_id',
			default            => false,
		};

		if ( ! $meta_key ) {
			return false;
		}

		$tmdb_id = get_post_meta( $post_id, $meta_key, true );
		return ( isset( $tmdb_id ) && ! empty( $tmdb_id ) ) ? $tmdb_id : false;
	}

	/**
	 * Save TMDB ID to post meta
	 *
	 * @param int    $post_id   The post ID
	 * @param string $post_type The post type
	 * @param string $tmdb_id   The TMDB ID to save
	 * @return void
	 */
	private function save_tmdb_id( int $post_id, string $post_type, string $tmdb_id ): void {
		$meta_key = match ( $post_type ) {
			'post_type_actors' => 'lezactors_tmdb_id',
			'post_type_shows'  => 'lezshows_tmdb_id',
			default            => false,
		};

		if ( $meta_key ) {
			update_post_meta( $post_id, $meta_key, $tmdb_id );
		}
	}
}
