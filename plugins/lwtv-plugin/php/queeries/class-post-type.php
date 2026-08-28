<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Post_Type {

	/**
	 * Post Type Query - Optimized Version
	 *
	 * Generate posts from a specific post type with proper pagination
	 *
	 * @param string $post_type Post type to query
	 * @param int    $posts_per_page Number of posts per page. Default -1 (all)
	 * @param int    $paged Page number for pagination. Default 1
	 * @param string $fields Fields to return. Default 'all'
	 * @param string $orderby Order by field. Default 'title'
	 * @param string $order Order direction. Default 'ASC'
	 *
	 * @return WP_Query The WP_Query object
	 */
	public function make( $post_type, $posts_per_page = -1, $paged = 1, $fields = 'all', $orderby = 'title', $order = 'ASC' ) {
		// Capture original parameters for cache key before any modifications
		$original_args = func_get_args();

		// If the post type does not exist, return empty query
		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) );
		}

		// Create cache key based on all parameters
		$cache_key     = 'post_type_' . md5( wp_json_encode( $original_args ) );
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Build query arguments
		$query_args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => $posts_per_page,
			'paged'                  => $paged,
			'orderby'                => $orderby,
			'order'                  => $order,
			'fields'                 => $fields,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status'            => array( 'publish' ),
		);

		// Only add found_rows if we need pagination info
		if ( $posts_per_page > 0 ) {
			$query_args['no_found_rows'] = false;
		} else {
			$query_args['no_found_rows'] = true;
		}

		$query = new \WP_Query( $query_args );

		// Cache the result for 30 minutes
		lwtv_plugin()->set_transient( $cache_key, $query, 30 * MINUTE_IN_SECONDS );

		return $query;
	}

	/**
	 * Every published post ID in a post type.
	 *
	 * `make()` defaults to `fields => 'all'` and caches the whole WP_Query, so a
	 * caller that only wanted IDs was writing a multi-megabyte blob of full post
	 * objects -- post_content and all -- into the object cache to extract an
	 * array of integers. Every debugger scan did exactly that, then immediately
	 * called `wp_list_pluck( $query->posts, 'ID' )`.
	 *
	 * This queries `fields => 'ids'` and caches only the ID list.
	 *
	 * @param string $post_type Post type to query.
	 * @param string $orderby   Order by field. Default 'title'.
	 * @param string $order     Order direction. Default 'ASC'.
	 *
	 * @return array<int> Post IDs, empty when the post type has none.
	 */
	public function get_ids( $post_type, $orderby = 'title', $order = 'ASC' ): array {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$cache_key = 'post_type_ids_' . md5( wp_json_encode( array( $post_type, $orderby, $order ) ) );
		$cached    = lwtv_plugin()->get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => $orderby,
				'order'                  => $order,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'post_status'            => array( 'publish' ),
			)
		);

		$ids = array_map( 'intval', (array) $query->posts );

		lwtv_plugin()->set_transient( $cache_key, $ids, 30 * MINUTE_IN_SECONDS );

		return $ids;
	}

	/**
	 * Get count of posts in post type
	 *
	 * @param string $post_type Post type to query
	 *
	 * @return int Number of posts
	 */
	public function get_count( $post_type ) {
		$query = $this->make( $post_type, 1, 1, 'ids' );
		return $query->found_posts;
	}

	/**
	 * Legacy method for backward compatibility
	 *
	 * @param string $post_type Post type to query
	 * @param int    $page Page number (legacy format)
	 *
	 * @return WP_Query The WP_Query object
	 */
	public function make_legacy( $post_type, $page = 0 ) {
		if ( 0 === $page ) {
			return $this->make( $post_type, -1, 1 );
		} else {
			return $this->make( $post_type, 100, $page );
		}
	}
}
