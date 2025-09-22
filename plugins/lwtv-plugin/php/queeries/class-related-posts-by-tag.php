<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

class Related_Posts_By_Tag {

	/**
	 * Related Posts by Tags - Optimized Version
	 *
	 * @param string $post_type Post type to query
	 * @param string $slug The slug of the post we're trying to relate to
	 * @param int    $posts_per_page Number of posts per page. Default -1 (all)
	 * @param int    $paged Page number for pagination. Default 1
	 * @param string $fields Fields to return. Default 'all'
	 * @param string $orderby Order by field. Default 'date'
	 * @param string $order Order direction. Default 'DESC'
	 *
	 * @return WP_Query The WP_Query object
	 */
	public function make( $post_type, $slug, $posts_per_page = -1, $paged = 1, $fields = 'all', $orderby = 'date', $order = 'DESC' ) {
		// Capture original parameters for cache key before any modifications
		$original_args = func_get_args();

		// Validate input parameters
		if ( empty( $post_type ) || empty( $slug ) ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) );
		}

		// Check if tag exists
		$term = term_exists( $slug, 'post_tag' );
		if ( 0 === $term || null === $term ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) );
		}

		// Create cache key based on all parameters
		$cache_key     = 'related_posts_tag_' . md5( wp_json_encode( $original_args ) );
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Build query arguments
		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'fields'         => $fields,
			'post_status'    => array( 'publish' ),
			'tag'            => $slug,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Only add found_rows if we need pagination info
		if ( $posts_per_page > 0 ) {
			$query_args['no_found_rows'] = false;
		} else {
			$query_args['no_found_rows'] = true;
		}

		$query = new \WP_Query( $query_args );

		// Cache the result for 30 minutes
		lwtv_plugin()->set_transient( $cache_key, $query, DAY_IN_SECONDS );

		return $query;
	}

	/**
	 * Get count of related posts by tag
	 *
	 * @param string $post_type Post type to query
	 * @param string $slug The slug of the post we're trying to relate to
	 *
	 * @return int Number of related posts
	 */
	public function get_count( $post_type, $slug ) {
		$query = $this->make( $post_type, $slug, 1, 1, 'ids' );
		return $query->found_posts;
	}
}
