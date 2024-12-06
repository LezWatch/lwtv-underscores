<?php
/**
 * Description: REST-API: WikiData
 *
 * Does the magic connection checks to see if our wikidata is up to date
 */

namespace LWTV\Rest_API;

use LWTV\Debugger\Actors as Debug_Actors;
use LWTV\Queeries\Post_Meta as Queeries_Post_Meta;

class Wikidata {
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
	 *   - /lwtv/v1/wikidata/[actor-slug|imdb|post-id]/
	 *   - YYYY-MM-DD
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/wikidata/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/wikidata/(?P<who_dat>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback
	 */
	public function rest_api_callback( $data ) {
		$params   = $data->get_params();
		$who_dat  = ( isset( $params['who_dat'] ) && '' !== $params['who_dat'] ) ? sanitize_title_for_query( $params['who_dat'] ) : '';
		$what_dat = '';

		// If it starts with nm(number) then it's an IMDB ID
		// if it's JUST a number then it's a post ID
		// otherwise it's a slug
		if ( preg_match( '/^nm\d+$/', $who_dat ) ) {
			$what_dat = 'imdb';
		} elseif ( is_numeric( $who_dat ) ) {
			$what_dat = 'post-id';
		} else {
			$what_dat = 'actor-slug';
		}

		// Figure out what we're running...
		switch ( $what_dat ) {
			case 'post-id':
				$response = $this->get_wikidata_by_post_id( $who_dat );
				break;
			case 'imdb':
				$response = $this->get_post_id_by_imdb( $who_dat );
				break;
			case 'actor-slug':
				$response = $this->get_post_id_by_actor_slug( $who_dat );
				break;
			default:
				$response = array(
					'error' => 'Invalid request',
				);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get Wikidata by Post ID
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_wikidata_by_post_id( $post_id ): array {
		if ( get_post_type( $post_id ) !== 'post_type_actors' ) {
			return array(
				'error' => 'Invalid post ID',
			);
		}

		$wikidata = ( new Debug_Actors() )->check_actors_wikidata( $post_id );

		return array( $wikidata );
	}

	/**
	 * Get Post ID by IMDB
	 *
	 * @param string $imdb
	 * @return array
	 */
	private function get_post_id_by_imdb( $imdb ): array {
		$actors      = array();
		$imdb_queery = ( new Queeries_Post_Meta() )->make( 'post_type_actors', 'lezactors_imdb', $imdb );

		// Add ONLY the IDs to the array.
		if ( is_object( $imdb_queery ) && $imdb_queery->have_posts() ) {
			$actor_ids = wp_list_pluck( $imdb_queery->posts, 'ID' );
		}

		// If we have more than one actor with the same IMDB ID, we need to return an array of post IDs.
		// This should be rare, but it's possible.
		foreach ( $actor_ids as $actor ) {
			$actors[] = ( new Debug_Actors() )->check_actors_wikidata( $actor );
		}

		return $actors;
	}

	/**
	 * Get Post ID by Actor Slug
	 *
	 * @param string $slug
	 * @return array
	 */
	private function get_post_id_by_actor_slug( $slug ): array {
		global $wpdb;

		$actors = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$possible_ids = $wpdb->get_col( "select ID from $wpdb->posts where post_type = 'post_type_actors' AND post_name LIKE '%" . $slug . "%' " );

		if ( ! $possible_ids ) {
			return array(
				'error' => 'No such actor found.',
			);
		}

		foreach ( $possible_ids as $actor ) {
			$actors[] = ( new Debug_Actors() )->check_actors_wikidata( $actor );
		}

		return $actors;
	}
}
