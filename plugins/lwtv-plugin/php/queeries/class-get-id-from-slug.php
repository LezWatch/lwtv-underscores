<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 6.0.1
 */

namespace LWTV\Queeries;

class Get_ID_From_Slug {

	/**
	 * Get Post ID from slug
	 *
	 * @access public
	 * @param  mixed $the_slug - Post Slug
	 * @return string
	 */
	public function make( $the_slug ): string {
		// Validate input
		if ( empty( $the_slug ) || ! is_string( $the_slug ) ) {
			return '';
		}

		// Create cache key
		$cache_key = 'post_id_from_slug_' . md5( $the_slug );
		$cached_id = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_id ) {
			return $cached_id;
		}

		global $wpdb;

		// Direct SQL query - much more efficient than get_posts()
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_name = %s
			AND post_type IN ('post_type_shows', 'post_type_actors')
			AND post_status = 'publish'
			LIMIT 1",
			$the_slug
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$post_id = $wpdb->get_var( $query );

		if ( empty( $post_id ) ) {
			return '';
		}

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $post_id, HOUR_IN_SECONDS );

		return $post_id;
	}
}
