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


class Post_Meta {

	/**
	 * Post Meta Query - Optimized Version
	 *
	 * For when you need posts based on meta criteria
	 *
	 * @param string $post_type Post type to query
	 * @param string $key The post meta key being searched for
	 * @param string $value The post meta VALUE being searched for
	 * @param string $compare Search operator. Default =
	 * @param int    $posts_per_page Number of posts per page. Default -1 (all)
	 * @param int    $paged Page number for pagination. Default 1
	 * @param string $fields Fields to return. Default 'all'
	 * @param string $orderby Order by field. Default 'title'
	 * @param string $order Order direction. Default 'ASC'
	 *
	 * @return WP_Query The WP_Query object
	 */
	public function make( $post_type, $key, $value, $compare = '=', $posts_per_page = -1, $paged = 1, $fields = 'all', $orderby = 'title', $order = 'ASC' ) {
		// Capture original parameters for cache key before any modifications
		$original_args = func_get_args();

		// Validate input parameters
		if ( empty( $post_type ) || empty( $key ) ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) ); // Return empty query
		}

		// Create cache key based on all parameters
		$cache_key     = 'post_meta_' . md5( wp_json_encode( $original_args ) );
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Build base query arguments
		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => array( 'publish' ),
			'orderby'                => $orderby,
			'order'                  => $order,
			'posts_per_page'         => $posts_per_page,
			'paged'                  => $paged,
			'fields'                 => $fields,
			'update_post_term_cache' => false,
		);

		// Only add found_rows if we need pagination info
		if ( $posts_per_page > 0 ) {
			$query_args['no_found_rows'] = false;
		} else {
			$query_args['no_found_rows'] = true;
		}

		// Build meta query based on whether value is provided
		if ( '' !== $value ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => $key,
					'value'   => $value,
					'compare' => $compare,
				),
			);
		} else {
			$query_args['meta_query'] = array(
				array(
					'key'     => $key,
					'compare' => $compare,
				),
			);
		}

		$query = new \WP_Query( $query_args );

		// Cache the result for 30 minutes
		lwtv_plugin()->set_transient( $cache_key, $query, 30 * MINUTE_IN_SECONDS );

		return $query;
	}

	/**
	 * Get count of posts matching meta criteria
	 *
	 * @param string $post_type Post type to query
	 * @param string $key The post meta key being searched for
	 * @param string $value The post meta VALUE being searched for
	 * @param string $compare Search operator. Default =
	 *
	 * @return int Number of matching posts
	 */
	public function get_count( $post_type, $key, $value, $compare = '=' ) {
		$query = $this->make( $post_type, $key, $value, $compare, 1, 1, 'ids' );
		return $query->found_posts;
	}
}
