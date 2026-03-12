<?php
/**
 * Description: REST-API: Sync AI
 *
 * Private endpoint for AI/Ollama sync. Returns a flat list of shows with AI-relevant
 * metadata (score, on_air, worthit, tropes, genres, country). Excludes heavy content.
 * Protected by X-LezWatch-AI-Key. Supports modified_after for incremental daily syncs.
 */

namespace LWTV\Rest_API;

class Sync_AI {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates the /lwtv/v1/sync-ai route.
	 */
	public function rest_api_init(): void {
		register_rest_route(
			'lwtv/v1',
			'/sync-ai',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ai_sync_data' ),
				'permission_callback' => array( $this, 'check_ai_key_permission' ),
				'args'                => array(
					'modified_after' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'ISO 8601 or strtotime-compatible date (e.g. 2025-03-11). Only shows modified after this date.',
					),
				),
			)
		);
	}

	/**
	 * Check permission via X-LezWatch-AI-Key header
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if key is valid, false otherwise.
	 */
	public function check_ai_key_permission( \WP_REST_Request $request ): bool {
		if ( ! defined( 'LWTV_AI_KEY' ) ) {
			return false;
		}

		$header_key = $request->get_header( 'X-LezWatch-AI-Key' );

		return ! empty( $header_key ) && hash_equals( (string) LWTV_AI_KEY, (string) $header_key );
	}

	/**
	 * Get flat list of shows with AI-relevant metadata.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_ai_sync_data( \WP_REST_Request $request ): \WP_REST_Response {
		$modified_after = $request->get_param( 'modified_after' );

		$args = array(
			'post_type'      => 'post_type_shows',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( ! empty( $modified_after ) && strtotime( $modified_after ) !== false ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => $modified_after,
				),
			);
		} elseif ( ! empty( $modified_after ) ) {
			lwtv_plugin()->debug_log( 'sync-ai', 'Invalid modified_after date ignored: ' . $modified_after );
		}

		$show_ids  = get_posts( $args );
		$sync_data = array();

		foreach ( $show_ids as $id ) {
			$tropes  = wp_get_post_terms( $id, 'lez_tropes', array( 'fields' => 'slugs' ) );
			$genres  = wp_get_post_terms( $id, 'lez_genres', array( 'fields' => 'slugs' ) );
			$country = wp_get_post_terms( $id, 'lez_country', array( 'fields' => 'slugs' ) );

			$char_count = (int) get_post_meta( $id, 'lezshows_char_count', true );
			$dead_count = (int) get_post_meta( $id, 'lezshows_dead_count', true );

			$sync_data[] = array(
				'id'         => (int) $id,
				'title'      => get_the_title( $id ),
				'slug'       => get_post_field( 'post_name', $id ),
				'permalink'  => get_permalink( $id ),
				'score'      => (int) get_post_meta( $id, 'lezshows_the_score', true ),
				'on_air'     => get_post_meta( $id, 'lezshows_on_air', true ),
				'worthit'    => strtolower( (string) get_post_meta( $id, 'lezshows_worthit_rating', true ) ),
				'tropes'     => is_wp_error( $tropes ) ? array() : (array) $tropes,
				'genres'     => is_wp_error( $genres ) ? array() : (array) $genres,
				'country'    => is_wp_error( $country ) ? array() : (array) $country,
				'excerpt'    => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 30 ),
				'characters' => $char_count,
				'dead'       => $dead_count,
			);
		}

		return rest_ensure_response( $sync_data );
	}
}
