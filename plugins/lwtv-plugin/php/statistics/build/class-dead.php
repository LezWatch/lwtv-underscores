<?php
/**
 * Dead Statistics Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

class Dead {
	/**
	 * Get total dead characters
	 *
	 * @return array Total dead
	 */
	public function total_dead_characters() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'total_dead_characters';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'dead-debug', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			$queery = $wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_name
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND tt.taxonomy = %s
				AND t.slug = %s
				ORDER BY p.post_title ASC",
				'post_type_characters',
				'lez_cliches',
				'dead'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			// return the count of the results
			return count( $results );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-debug', 'Error getting total dead characters: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Get total dead shows
	 *
	 * This is all shows with the term 'dead-queers' in the taxonomy lez_tropes
	 *
	 * @return array Total dead
	 */
	public function total_dead_shows() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'total_dead_shows';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			$queery = $wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_name
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND tt.taxonomy = %s
				AND t.slug = %s
				ORDER BY p.post_title ASC",
				'post_type_shows',
				'lez_tropes',
				'dead-queers'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			// return the count of the results
			return count( $results );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-debug', 'Error getting total dead shows: ' . $e->getMessage() );
			return 0;
		}
	}
}
