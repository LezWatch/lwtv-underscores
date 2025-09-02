<?php
/**
 * TMDB Task Handler
 *
 * Handles deferred TMDB API calls to improve save performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\_Components\CPTs;

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
			lwtv_plugin()->error_log( 'tmdb-task', "Invalid post ID: {$post_id}" );
			return;
		}

		lwtv_plugin()->error_log( 'tmdb-task', "Processing TMDB task for {$post_type} ID: {$post_id}" );

		// Check if TMDB ID is already set
		$tmdb_id = $this->get_existing_tmdb_id( $post_id, $post_type );
		if ( $tmdb_id ) {
			lwtv_plugin()->error_log( 'tmdb-task', "TMDB ID already exists for {$post_type} ID: {$post_id}" );
			return;
		}

		// Get TMDB data
		$tmdb_data = ( new CPTs() )->get_tmdb_info( $post_id );

		if ( ! $tmdb_data ) {
			lwtv_plugin()->error_log( 'tmdb-task', "No TMDB data found for {$post_type} ID: {$post_id}" );
			return;
		}

		// Extract TMDB ID based on post type
		$tmdb_id = $this->extract_tmdb_id( $tmdb_data, $post_type );

		// Save TMDB ID if found
		if ( $tmdb_id ) {
			$this->save_tmdb_id( $post_id, $post_type, $tmdb_id );
			lwtv_plugin()->error_log( 'tmdb-task', "Successfully saved TMDB ID: {$tmdb_id} for {$post_type} ID: {$post_id}" );
		} else {
			lwtv_plugin()->error_log( 'tmdb-task', "No TMDB ID found in data for {$post_type} ID: {$post_id}" );
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
	 * Extract TMDB ID from API response based on post type
	 *
	 * @param array  $tmdb_data The TMDB API response
	 * @param string $post_type The post type
	 * @return string|false The TMDB ID or false
	 */
	private function extract_tmdb_id( array $tmdb_data, string $post_type ) {
		return match ( $post_type ) {
			'post_type_actors' => $this->extract_actor_tmdb_id( $tmdb_data ),
			'post_type_shows'  => $this->extract_show_tmdb_id( $tmdb_data ),
			default            => false,
		};
	}

	/**
	 * Extract TMDB ID for actors
	 *
	 * @param array $tmdb_data The TMDB API response
	 * @return string|false The TMDB ID or false
	 */
	private function extract_actor_tmdb_id( array $tmdb_data ) {
		if ( isset( $tmdb_data['id'] ) ) {
			return $tmdb_data['id'];
		}

		if ( isset( $tmdb_data['person_results'][0]['id'] ) ) {
			return $tmdb_data['person_results'][0]['id'];
		}

		return false;
	}

	/**
	 * Extract TMDB ID for shows
	 *
	 * @param array $tmdb_data The TMDB API response
	 * @return string|false The TMDB ID or false
	 */
	private function extract_show_tmdb_id( array $tmdb_data ) {
		if ( isset( $tmdb_data['id'] ) ) {
			return $tmdb_data['id'];
		}

		if ( isset( $tmdb_data['tv_results'][0]['id'] ) ) {
			return $tmdb_data['tv_results'][0]['id'];
		}

		return false;
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
