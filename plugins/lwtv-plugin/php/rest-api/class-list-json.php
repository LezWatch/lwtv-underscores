<?php
/**
 * Lists API
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class List_JSON {

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
	 *   - /lwtv/v1/list/
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/list/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/list/(?P<type>[a-zA-Z.\-]+)',
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
		$params = $data->get_params();
		$type   = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'none';

		if ( ! in_array( $type, array( 'shows', 'characters', 'actors' ), true ) ) {
			$return = new \WP_Error( 'invalid', 'An unexpected error has occurred.' );
		}

		$return = $this->list( $type );
		if ( false === $return ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		return $return;
	}

	public function list( $type ) {
		// Generate cache key
		$cache_key = 'lwtv_list_' . $type . '_' . $this->get_data_version_hash( $type );

		// Try to get from cache first
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Use optimized query with fields parameter
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_' . $type,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$post_options = array();

		if ( ! empty( $posts ) ) {
			// Bulk fetch all meta data
			$meta_data = $this->get_bulk_meta_data( $posts, $type );

			// Bulk fetch taxonomy data for characters
			$taxonomy_data = array();
			if ( 'characters' === $type ) {
				$taxonomy_data = $this->get_bulk_taxonomy_data( $posts );
			}

			foreach ( $posts as $post_id ) {
				// Base Array
				$post_options[ $post_id ] = array(
					'title' => get_the_title( $post_id ),
					'url'   => get_permalink( $post_id ),
				);

				// Add type-specific data
				switch ( $type ) {
					case 'shows':
						$post_options[ $post_id ]['onair'] = $meta_data[ $post_id ]['lezshows_on_air'] ?? '';
						break;
					case 'actors':
						$queer_value                       = $meta_data[ $post_id ]['lezactors_queer'] ?? '';
						$post_options[ $post_id ]['queer'] = $queer_value ? 'yes' : 'no';
						break;
					case 'characters':
						$post_options[ $post_id ]['status'] = isset( $taxonomy_data[ $post_id ] ) ? 'dead' : 'alive';
						break;
				}
			}
		}

		// Cache the result for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $post_options, HOUR_IN_SECONDS );

		return $post_options;
	}

	/**
	 * Get bulk meta data for multiple posts
	 *
	 * @param array  $post_ids Array of post IDs
	 * @param string $type Post type
	 * @return array Meta data organized by post ID
	 */
	private function get_bulk_meta_data( $post_ids, $type ) {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		// Sanitize IDs
		$post_ids = array_map( 'intval', $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Determine meta keys based on type
		$meta_keys = array();
		switch ( $type ) {
			case 'shows':
				$meta_keys[] = 'lezshows_on_air';
				break;
			case 'actors':
				$meta_keys[] = 'lezactors_queer';
				break;
		}

		if ( empty( $meta_keys ) ) {
			return array();
		}

		$ids_string  = implode( ',', $post_ids );
		$keys_string = "'" . implode( "','", array_map( 'esc_sql', $meta_keys ) ) . "'";

		$query = "SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN ($ids_string)
			AND meta_key IN ($keys_string)";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs and keys are sanitized
		$results = $wpdb->get_results( $query );

		$meta_data = array();
		foreach ( $results as $row ) {
			$meta_data[ $row->post_id ][ $row->meta_key ] = maybe_unserialize( $row->meta_value );
		}

		return $meta_data;
	}

	/**
	 * Get bulk taxonomy data for characters
	 *
	 * @param array $post_ids Array of post IDs
	 * @return array Post IDs that have 'dead' term
	 */
	private function get_bulk_taxonomy_data( $post_ids ) {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$ids_string = implode( ',', $post_ids );

		$query = "SELECT DISTINCT tr.object_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id IN ($ids_string)
			AND tt.taxonomy = 'lez_cliches'
			AND t.slug = 'dead'";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs are sanitized
		$results = $wpdb->get_results( $query );

		$dead_posts = array();
		foreach ( $results as $row ) {
			$dead_posts[ $row->object_id ] = true;
		}

		return $dead_posts;
	}

	/**
	 * Get data version hash for cache invalidation
	 *
	 * @param string $type Post type
	 * @return string Hash based on last modification time
	 */
	private function get_data_version_hash( $type ) {
		$cache_key   = 'lwtv_list_data_version_' . $type;
		$cached_hash = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_hash ) {
			return $cached_hash;
		}

		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'post_type_' . $type
			)
		);

		$hash = md5( $last_modified );
		lwtv_plugin()->set_transient( $cache_key, $hash, HOUR_IN_SECONDS );

		return $hash;
	}
}
