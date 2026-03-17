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
				'callback'            => array( $this, 'get_shows_data' ),
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

		register_rest_route(
			'lwtv/v1',
			'/sync-ai/shows',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_shows_data' ),
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

		register_rest_route(
			'lwtv/v1',
			'/sync-ai/shows/integrity',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_show_ids' ),
				'permission_callback' => array( $this, 'check_ai_key_permission' ),
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
	public function get_shows_data( \WP_REST_Request $request ): \WP_REST_Response {
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
			$tropes        = wp_get_post_terms( $id, 'lez_tropes', array( 'fields' => 'slugs' ) );
			$genres        = wp_get_post_terms( $id, 'lez_genres', array( 'fields' => 'slugs' ) );
			$country       = wp_get_post_terms( $id, 'lez_country', array( 'fields' => 'slugs' ) );
			$stations      = wp_get_post_terms( $id, 'lez_stations', array( 'fields' => 'slugs' ) );
			$formats       = wp_get_post_terms( $id, 'lez_formats', array( 'fields' => 'slugs' ) );
			$intersections = wp_get_post_terms( $id, 'lez_intersections', array( 'fields' => 'slugs' ) );
			$stars         = wp_get_post_terms( $id, 'lez_stars', array( 'fields' => 'slugs' ) );
			$triggers      = wp_get_post_terms( $id, 'lez_triggers', array( 'fields' => 'slugs' ) );

			$char_roles = get_post_meta( $id, 'lezshows_char_roles', true );

			$airdates   = get_post_meta( $id, 'lezshows_airdates', true );
			$start_year = ( is_array( $airdates ) && isset( $airdates['start'] ) ) ? $airdates['start'] : null;
			$end_year   = ( is_array( $airdates ) && isset( $airdates['finish'] ) ) ? $airdates['finish'] : null;

			$sync_data[] = array(
				'id'            => (int) $id,
				'title'         => get_the_title( $id ),
				'slug'          => get_post_field( 'post_name', $id ),
				'permalink'     => get_permalink( $id ),
				'score'         => (int) get_post_meta( $id, 'lezshows_the_score', true ),
				'worthit'       => strtolower( (string) get_post_meta( $id, 'lezshows_worthit_rating', true ) ),
				'tropes'        => is_wp_error( $tropes ) ? array() : (array) $tropes,
				'genres'        => is_wp_error( $genres ) ? array() : (array) $genres,
				'country'       => is_wp_error( $country ) ? array() : (array) $country,
				'stations'      => is_wp_error( $stations ) ? array() : (array) $stations,
				'formats'       => is_wp_error( $formats ) ? array() : (array) $formats,
				'intersections' => is_wp_error( $intersections ) ? array() : (array) $intersections,
				'curator_say'   => get_post_meta( $id, 'lezshows_worthit_details', true ),
				'excerpt'       => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 250 ),
				'characters'    => array(
					'total'     => (int) get_post_meta( $id, 'lezshows_char_count', true ),
					'dead'      => (int) get_post_meta( $id, 'lezshows_dead_count', true ),
					'queer_irl' => (int) get_post_meta( $id, 'lezshows_queer_irl_count', true ),
					'regulars'  => (int) $char_roles['regular'] ?? 0,
					'recurring' => (int) $char_roles['recurring'] ?? 0,
					'guests'    => (int) $char_roles['guest'] ?? 0,
				),
				'dates'         => array(
					'start'  => $start_year ? (int) $start_year : null,
					'finish' => ( $end_year && is_numeric( $end_year ) ) ? (int) $end_year : null,
					'on_air' => ( 'current' === $end_year || $end_year >= gmdate( 'Y' ) ) ? true : false,
				),
				'stars'         => ( ! is_wp_error( $stars ) && ! empty( $stars ) ) ? $stars[0] : null,
				'triggers'      => ( ! is_wp_error( $triggers ) && ! empty( $triggers ) ) ? $triggers[0] : null,
				'tmdb_id'       => (int) get_post_meta( $id, 'lezshows_tmdb_id', true ),
				'thumbnail_url' => ( get_the_post_thumbnail_url( $id, 'large' ) ) ? get_the_post_thumbnail_url( $id, 'large' ) : null,
				'similar_shows' => (array) get_post_meta( $id, 'lezshows_similar_shows', true ),
				'ratings'       => array(
					'loved'      => ( 'on' === get_post_meta( $id, 'lezshows_worthit_show_we_love', true ) ),
					'realness'   => (int) get_post_meta( $id, 'lezshows_realness_rating', true ),
					'quality'    => (int) get_post_meta( $id, 'lezshows_quality_rating', true ),
					'screentime' => (int) get_post_meta( $id, 'lezshows_screentime_rating', true ),
				),
				'demographics'  => array(
					'sexuality' => ( get_post_meta( $id, 'lezshows_char_sexuality', true ) ) ? get_post_meta( $id, 'lezshows_char_sexuality', true ) : array(),
					'gender'    => ( get_post_meta( $id, 'lezshows_char_gender', true ) ) ? get_post_meta( $id, 'lezshows_char_gender', true ) : array(),
					'romantic'  => ( get_post_meta( $id, 'lezshows_char_romantic', true ) ) ? get_post_meta( $id, 'lezshows_char_romantic', true ) : array(),
				),
			);
		}

		return rest_ensure_response( $sync_data );
	}

	/**
	 * Returns a simple list of every published show ID.
	 */
	public function get_all_show_ids(): \WP_REST_Response {
		$ids = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return rest_ensure_response( array_map( 'intval', $ids ) );
	}
}
