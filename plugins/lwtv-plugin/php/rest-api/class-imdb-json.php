<?php
/**
 * Description: REST-API: IMDb
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class IMDb_JSON {

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
	 *   - /lwtv/v1/imdb/
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/imdb/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'imdb_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/imdb/(?P<id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'imdb_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback
	 */
	public function imdb_rest_api_callback( $data ) {
		$params = $data->get_params();
		$id     = ( isset( $params['id'] ) && '' !== $params['id'] ) ? sanitize_title_for_query( $params['id'] ) : false;

		if ( in_array( substr( $id, 0, 2 ), array( 'tt', 'nm' ), true ) ) {
			$return = $this->imdb( $id );
		} else {
			$return = new \WP_Error( 'invalid', 'An unexpected error has occurred.' );
		}

		return $return;
	}

	/*
	 * Of the Day function
	 */
	public function imdb( $id ) {

		$type = substr( $id, 0, 2 );

		// Set params based on items...
		switch ( $type ) {
			case 'tt':
				$post_type = 'post_type_shows';
				$meta_key  = 'lezshows_imdb';
				break;
			case 'nm':
				$post_type = 'post_type_actors';
				$meta_key  = 'lezactors_imdb';
				break;
		}

		// Direct SQL query for better performance
		global $wpdb;
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value = %s
				LIMIT 1",
				$post_type,
				$meta_key,
				$id
			)
		);

		// Base Array.
		$array = array(
			'id'   => $post_id ? $post_id : 0,
			'name' => $post_id ? get_the_title( $post_id ) : 'Not Found',
			'url'  => $post_id ? get_the_permalink( $post_id ) : '',
		);

		// Extra bitsys.
		if ( $post_id ) {
			switch ( $type ) {
				case 'tt':
					$array['score'] = get_post_meta( $post_id, 'lezshows_the_score', true );
					break;
				case 'nm':
					$array['queer'] = ( get_post_meta( $post_id, 'lezactors_queer', true ) ) ? true : false;
					break;
			}
		}

		return $array;
	}
}
