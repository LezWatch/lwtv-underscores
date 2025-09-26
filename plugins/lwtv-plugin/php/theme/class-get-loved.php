<?php
/**
 * Get Loved Shows
 *
 * Optimized database queries for getting random loved shows
 */

namespace LWTV\Theme;

class Get_Loved {

	/**
	 * Get random loved shows IDs using direct SQL
	 *
	 * This is more efficient than WP_Query because:
	 * 1. We only fetch the exact number needed (no over-fetching)
	 * 2. Randomization happens in SQL (no PHP shuffle)
	 * 3. We can cache the results
	 * 4. Direct SQL is faster than WP_Query for simple queries
	 *
	 * @param int $count Number of random loved shows to return
	 * @return array Array of post IDs
	 */
	public function get_random_ids( $count ) {
		// Validate input
		$count = max( 1, (int) $count );

		// Create cache key
		$cache_key  = 'loved_shows_random_' . $count;
		$cached_ids = lwtv_plugin()->get_transient( $cache_key );

		// Return cached results if available
		if ( false !== $cached_ids && is_array( $cached_ids ) ) {
			return $cached_ids;
		}

		global $wpdb;

		// Direct SQL query for random loved shows
		$query = $wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm.meta_key = %s
			AND pm.meta_value = %s
			ORDER BY RAND()
			LIMIT %d",
			'post_type_shows',
			'lezshows_worthit_show_we_love',
			'on',
			$count
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_col( $query );

		// Convert to integers and ensure we have an array
		$post_ids = array_map( 'intval', $results );

		// Cache for 1 hour (3600 seconds) - shorter cache for randomness
		lwtv_plugin()->set_transient( $cache_key, $post_ids, HOUR_IN_SECONDS );

		return $post_ids;
	}
}
