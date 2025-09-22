<?php
/**
 * REST-API: X Of The Day
 *
 * The code that runs the X Of the Day API service
 * Every 24 hours, a new character and show of the day are spawned
 *
 */

namespace LWTV\Rest_API;

use LWTV\_Components\Of_The_Day;

class OTD_JSON {

	/**
	 * Cache duration for OTD responses (1 hour)
	 */
	const OTD_CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/of-the-day/
	 */
	public function rest_api_init() {

		register_rest_route(
			'lwtv/v1',
			'/of-the-day/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'otd_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/of-the-day/(?P<type>[a-zA-Z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'otd_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/of-the-day/(?P<type>[a-zA-Z]+)/(?P<format>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'otd_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback for Of The Day
	 */
	public function otd_rest_api_callback( $data ) {
		$params = $data->get_params();
		$type   = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'unknown';
		$format = ( isset( $params['format'] ) && '' !== $params['format'] ) ? sanitize_title_for_query( $params['format'] ) : 'default';

		// Generate cache key
		$cache_key = 'lwtv_otd_' . $type . '_' . $format . '_' . $this->get_data_version_hash();

		// Try to get from cache first
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		$response = ( new Of_The_Day() )->of_the_day( $type, $format );

		// Cache the result
		lwtv_plugin()->set_transient( $cache_key, $response, self::OTD_CACHE_DURATION );

		return $response;
	}

	/**
	 * Get data version hash for cache invalidation
	 *
	 * @return string Hash based on last modification time
	 */
	private function get_data_version_hash() {
		$cache_key   = 'lwtv_otd_data_version_hash';
		$cached_hash = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_hash ) {
			return $cached_hash;
		}

		// Get the most recent modification time of any relevant post
		global $wpdb;
		$last_modified = $wpdb->get_var(
			"SELECT MAX(post_modified) FROM {$wpdb->posts}
			WHERE post_type IN ('post_type_characters', 'post_type_shows', 'post_type_actors')
			AND post_status = 'publish'"
		);

		$hash = md5( $last_modified );
		lwtv_plugin()->set_transient( $cache_key, $hash, HOUR_IN_SECONDS );

		return $hash;
	}
}
