<?php
/**
 * Description: REST-API: Broom
 */

namespace LWTV\Rest_API;

use LWTV\Rest_API\BYQ;

class Broom {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates the /lwtv/v1/broom route.
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/broom',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'sweep_cache' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Sweep cache
	 *
	 * Sweeps the cache for a given check.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The response object.
	 */
	public function sweep_cache( \WP_REST_Request $request ) {
		$results = array();
		$check   = $request->get_param( 'check' );
		$sweeper = $this->broom_check( $check );

		if ( false === $sweeper ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'success',
					'message' => 'No action needed: Data is already in sync for ' . $check . '.',
					'time'    => current_time( 'mysql' ),
				),
				200
			);
		}

		switch ( $check ) {
			case 'last_death':
				if ( class_exists( 'LWTV\Rest_API\BYQ' ) ) {
					$byq                     = new BYQ();
					$results['byq_internal'] = 'Transients invalidated';
					$byq->invalidate_death_list_cache();
				}
				break;
			default:
				break;
		}

		// Global Flushes
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results['redis'] = 'Object cache flushed';
		}

		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
			$results['opcache'] = 'OPcache reset';
		}

		return new \WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => 'Cache swept and Object Cache flushed for ' . $check . '.',
				'details' => $results,
				'time'    => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * Broom check
	 *
	 * Checks if the broom should be used for a given check.
	 *
	 * @param string $check The check to perform.
	 * @return bool True if the broom should be used, false otherwise.
	 */
	public function broom_check( $check ): bool {
		$return = true;

		// We only need to flush the cache if the last death API is behind the cache.
		if ( 'last_death' === $check && class_exists( 'LWTV\Rest_API\BYQ' ) ) {
			$byq               = new BYQ();
			$last_death_cached = $byq->last_death();
			$cached_id         = (int) ( $last_death_cached['id'] ?? 0 );

			// Fetch fresh data bypassing usual cache
			$fresh_list     = $byq->generate_death_list_array( null, 'temp_uncached_check_' . time() );
			$last_timestamp = array_key_last( $fresh_list );
			$fresh_id       = (int) ( $fresh_list[ $last_timestamp ]['id'] ?? 0 );

			// If the DB is BEHIND of the cache, we need to sweep the leg.
			if ( $fresh_id <= $cached_id ) {
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Check permission
	 *
	 * Checks if the user has permission to use the broom.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the user has permission, false otherwise.
	 */
	public function check_permission( \WP_REST_Request $request ): bool {
		if ( ! defined( 'BROOM_SECRET_KEY' ) ) {
			return false;
		}

		// Matches the ?secret_key= parameter in your URL
		return $request->get_param( 'secret_key' ) === BROOM_SECRET_KEY;
	}
}
