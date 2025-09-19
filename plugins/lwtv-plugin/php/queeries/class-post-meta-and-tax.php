<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

class Post_Meta_And_Tax {

	/**
	 * Post Meta AND Taxonomy Query - Optimized Version
	 *
	 * Function to generate an array of posts that have a specific post meta AND
	 * a specific taxonomy value. Useful for getting a list of all dead queers
	 * who are main characters (for example).
	 *
	 * @param string $post_type Post type to query
	 * @param string $key The post meta key being searched for
	 * @param string $value The post meta VALUE being searched for
	 * @param string $taxonomy The taxonomy being searched
	 * @param string $field The field to search in taxonomy terms
	 * @param array  $terms The terms being searched for
	 * @param string $compare Search operator for meta_query. Default =
	 * @param string $operator Search operator for tax_query. Default IN
	 * @param int    $posts_per_page Number of posts per page. Default -1 (all)
	 * @param int    $paged Page number for pagination. Default 1
	 * @param string $fields Fields to return. Default 'all'
	 *
	 * @return WP_Query The WP_Query object
	 */
	public function make( $post_type, $key, $value, $taxonomy, $field, $terms, $compare = '=', $operator = 'IN', $posts_per_page = -1, $paged = 1, $fields = 'all' ) {
		// Capture original parameters for cache key before any modifications
		$original_args = func_get_args();

		// Validate input parameters
		if ( empty( $post_type ) || empty( $key ) || empty( $taxonomy ) || empty( $terms ) ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) ); // Return empty query
		}

		// Create cache key based on all parameters
		$cache_key     = 'post_meta_tax_' . md5( wp_json_encode( $original_args ) );
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Build query arguments
		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'post_status'    => array( 'publish' ),
			'fields'         => $fields,
			'meta_query'     => array(
				array(
					'key'     => $key,
					'value'   => $value,
					'compare' => $compare,
				),
			),
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => $field,
					'terms'    => $terms,
					'operator' => $operator,
				),
			),
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
	 * Get count of posts matching meta and taxonomy criteria
	 *
	 * @param string $post_type Post type to query
	 * @param string $key The post meta key being searched for
	 * @param string $value The post meta VALUE being searched for
	 * @param string $taxonomy The taxonomy being searched
	 * @param string $field The field to search in taxonomy terms
	 * @param array  $terms The terms being searched for
	 * @param string $compare Search operator for meta_query. Default =
	 * @param string $operator Search operator for tax_query. Default IN
	 *
	 * @return int Number of matching posts
	 */
	public function get_count( $post_type, $key, $value, $taxonomy, $field, $terms, $compare = '=', $operator = 'IN' ) {
		$query = $this->make( $post_type, $key, $value, $taxonomy, $field, $terms, $compare, $operator, 1, 1, 'ids' );
		return $query->found_posts;
	}
}
