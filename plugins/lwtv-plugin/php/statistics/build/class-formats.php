<?php
/**
 * Formats Statistics Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Formats {

	/**
	 * Generate formats statistics
	 *
	 * @param string $format Output format
	 * @return array Formats statistics data
	 */
	public function generate( $format = 'array' ) {
		$all_formats_data = $this->generate_all_formats();
		switch ( $format ) {
			case 'count':
				return count( $all_formats_data );
			case 'piechart':
				return $this->format_piechart( $all_formats_data );
			case 'percentage':
				return $this->format_percentage( $all_formats_data );
			default:
				return $all_formats_data;
		}
	}

	/**
	 * Get total formats
	 *
	 * @return array Total formats
	 */
	public function generate_all_formats() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'total_formats';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Direct SQL Query that will return all formats with their counts
			$queery = "SELECT t.slug, t.name, COUNT(tr.object_id) as count
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			WHERE tt.taxonomy = 'lez_formats'
			AND p.post_type = 'post_type_shows'
			AND p.post_status = 'publish'
			GROUP BY t.term_id, t.slug, t.name
			ORDER BY count DESC
			";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			lwtv_plugin()->set_transient( $cache_key, $results, HOUR_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating formats statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format piechart
	 *
	 * @param array $all_formats_data All formats data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_formats_data ) {
		$data = array();
		foreach ( $all_formats_data as $format ) {
			$data[] = array(
				'name'  => $format['name'],
				'count' => $format['count'],
			);
		}
		return $data;
	}

	/**
	 * Format percentage
	 *
	 * @param array $all_formats_data All formats data
	 * @return array Percentage data
	 */
	public function format_percentage( $all_formats_data ) {
		$data = array();
		foreach ( $all_formats_data as $format ) {
			$data[ $format['name'] ] = array(
				'name'  => $format['name'],
				'count' => $format['count'],
				'url'   => site_url( '/formats/' . $format['slug'] ),
			);
		}

		return $data;
	}
}
