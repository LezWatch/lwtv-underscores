<?php
/**
 * Description: REST-API: WikiData
 *
 * Does the magic connection checks to see if our wikidata is up to date
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Actors as Debug_Actors;
use LWTV\Queeries\Post_Meta as Queeries_Post_Meta;
use LWTV\CPTs\Actors as CPT_Actors;

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
		} elseif ( preg_match( '/^q\d+$/', $who_dat ) ) {
			$what_dat = 'wikidata';
		} elseif ( is_numeric( $who_dat ) ) {
			$what_dat = 'post-id';
		} else {
			$what_dat = 'actor-slug';
		}

		$response = match ( $what_dat ) {
			'actor-slug' => $this->get_by_actor_slug( $who_dat ),
			'imdb'       => $this->get_by_imdb( $who_dat ),
			'post-id'    => $this->get_by_post_id( $who_dat ),
			'wikidata'   => $this->get_by_wikidata( $who_dat ),
			default      => array(
				'error' => __( 'Invalid request', 'lwtv' ),
			),
		};

		// If we have no data, return an error.
		if ( empty( $response ) ) {
			$response = array(
				/* translators: %s: the requested actor slug, IMDB ID, WikiData Q-ID, or post ID. */
				'error' => sprintf( __( 'No data found for %s', 'lwtv' ), $who_dat ),
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
	private function get_by_post_id( $post_id ): array {
		if ( get_post_type( $post_id ) !== CPT_Actors::SLUG ) {
			return array(
				'error' => __( 'Invalid post ID', 'lwtv' ),
			);
		}

		// Users who can edit this actor (e.g. the editor's WikiData panel) may
		// view it regardless of status or privacy. Everyone else only sees a
		// published actor that has not requested full privacy.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			if ( 'publish' !== get_post_status( $post_id ) || lwtv_plugin()->hide_actor_data( $post_id, 'all' ) ) {
				return array(
					'error' => __( 'Invalid post ID', 'lwtv' ),
				);
			}
		}

		$wikidata = $this->get_actor_wikidata( $post_id );

		return array( $wikidata );
	}

	/**
	 * Get an actor's WikiData comparison for the REST response.
	 *
	 * Callers who cannot edit the actor get the stored comparison meta only —
	 * no live WikiData fetch and no post-meta write. Users who can edit the
	 * actor (e.g. the editor's WikiData panel) get a fresh comparison, which
	 * also refreshes the meta.
	 *
	 * Both branches return the same shape: an array keyed by actor ID, matching
	 * what check_actors_wikidata() returns (it stores the inner value under
	 * lezactors_saved_wikidata, so the stored read is re-wrapped by ID here).
	 *
	 * @param int $actor_id Actor post ID.
	 * @return array
	 */
	private function get_actor_wikidata( $actor_id ): array {
		if ( current_user_can( 'edit_post', $actor_id ) ) {
			return ( new Debug_Actors() )->check_actors_wikidata( $actor_id );
		}

		$stored = get_post_meta( $actor_id, 'lezactors_saved_wikidata', true );
		return is_array( $stored ) ? array( $actor_id => $stored ) : array();
	}

	/**
	 * Get Post ID by IMDB
	 *
	 * @param string $imdb
	 * @return array
	 */
	private function get_by_imdb( $imdb ): array {
		$actors    = array();
		$actor_ids = array();
		$queery    = ( new Queeries_Post_Meta() )->make( CPT_Actors::SLUG, 'lezactors_imdb', $imdb );

		// Add ONLY the IDs to the array.
		if ( is_object( $queery ) && $queery->have_posts() ) {
			$actor_ids = wp_list_pluck( $queery->posts, 'ID' );
		}

		// If we have more than one actor with the same IMDB ID, we need to return an array of post IDs.
		// This should be rare, but it's possible.
		foreach ( $actor_ids as $actor ) {
			if ( lwtv_plugin()->hide_actor_data( $actor, 'all' ) ) {
				continue;
			}
			$actors[] = $this->get_actor_wikidata( $actor );
		}

		return $actors;
	}

	/**
	 * Get Wikidata by Wikidata ID
	 *
	 * @param string $wikidata
	 * @return array
	 */
	private function get_by_wikidata( $wikidata ): array {
		$actors    = array();
		$actor_ids = array();
		$queery    = ( new Queeries_Post_Meta() )->make( CPT_Actors::SLUG, 'lezactors_wikidata_qid', $wikidata );

		// Add ONLY the IDs to the array.
		if ( is_object( $queery ) && $queery->have_posts() ) {
			$actor_ids = wp_list_pluck( $queery->posts, 'ID' );
		}

		// If we have more than one actor with the same IMDB ID, we need to return an array of post IDs.
		// This should be rare, but it's possible.
		foreach ( $actor_ids as $actor ) {
			if ( lwtv_plugin()->hide_actor_data( $actor, 'all' ) ) {
				continue;
			}
			$actors[] = $this->get_actor_wikidata( $actor );
		}

		return $actors;
	}

	/**
	 * Get Post ID by Actor Slug
	 *
	 * @param string $slug
	 * @return array
	 */
	private function get_by_actor_slug( $slug ): array {
		global $wpdb;

		$actors = array();

		// Reject empty/blank slugs so the LIKE never degrades to a match-all '%'.
		$slug = trim( (string) $slug );
		if ( '' === $slug ) {
			return array(
				'error' => __( 'No such actor found.', 'lwtv' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$possible_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post_type_actors' AND post_status = 'publish' AND post_name LIKE %s LIMIT 50",
				$wpdb->esc_like( $slug ) . '%'
			)
		);

		if ( ! $possible_ids ) {
			return array(
				'error' => __( 'No such actor found.', 'lwtv' ),
			);
		}

		foreach ( $possible_ids as $actor ) {
			if ( ! current_user_can( 'edit_post', $actor ) && lwtv_plugin()->hide_actor_data( $actor, 'all' ) ) {
				continue;
			}
			$actors[] = $this->get_actor_wikidata( $actor );
		}

		return $actors;
	}
}
