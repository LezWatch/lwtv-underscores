<?php
/**
 * Nations Statistics Builder - Optimized Version
 *
 * Handles nation-specific statistics generation with optimized queries
 * and proper caching strategy.
 *
 * @package LWTV_Plugin
 * @subpackage Statistics
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Statistics\Build\On_Air_Optimized as Build_On_Air;

/**
 * Nations class
 */
class Nations {

	/**
	 * Get nation summary data for main nations page
	 *
	 * Returns basic data for all nations: name, show count, character count
	 * This is lightweight data for the main nations listing page.
	 *
	 * @return array Array of nation data
	 */
	public function get_nation_summaries() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'nation_summaries';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			// Single optimized query to get basic nation data
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count,
					SUM(COALESCE(char_count.meta_value, 0)) as character_count,
					SUM(COALESCE(dead_count.meta_value, 0)) as dead_count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
				LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
				WHERE tt.taxonomy = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY t.name",
				'lez_country'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Query failed: ' . $wpdb->last_error );
				return array();
			}

			// Cache the results
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Query failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get detailed data for a specific nation
	 *
	 * Returns comprehensive data for a single nation including
	 * all breakdowns (gender, sexuality, tropes, etc.)
	 *
	 * @param string $nation_slug Station slug (e.g., 'cbs', 'abc')
	 * @return array Station detailed data
	 */
	public function get_nation_details( $nation_slug, $format, $view ) {
		// Sanitize input
		$nation_slug = sanitize_text_field( $nation_slug );

		// Create cache key
		$cache_key   = 'nation_details_' . $nation_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// The cache stores the format-independent raw breakdowns; always run them
		// through prepare_nation_data() so the return shape matches on hit and miss.
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $nation_slug . ': ' . $cache_key );
			return $this->prepare_nation_data( $cached_data, $nation_slug, $format, $view );
		}

		try {
			// Get basic nation data
			$basic_data = $this->get_nation_basic_data( $nation_slug );

			if ( empty( $basic_data ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Basic data query failed for nation: ' . $nation_slug );
				return array();
			}

			lwtv_plugin()->debug_log( 'statistics', 'Basic data query results for ' . $nation_slug . ': ' . wp_json_encode( $basic_data ) );

			// Get detailed breakdowns
			$detailed_data = array(
				'basic'     => $basic_data,
				'gender'    => $this->get_gender_breakdown( $nation_slug ),
				'sexuality' => $this->get_sexuality_breakdown( $nation_slug ),
				'tropes'    => $this->get_tropes_breakdown( $nation_slug ),
				'formats'   => $this->get_formats_breakdown( $nation_slug ),
				'on_air'    => $this->get_onair_breakdown( $nation_slug ),
			);

			// Cache the results
			lwtv_plugin()->set_transient( $cache_key, $detailed_data, DAY_IN_SECONDS );

			// Format the results
			$return_data = $this->prepare_nation_data( $detailed_data, $nation_slug, $format, $view );

			return $return_data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Query failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Prepare nation data for return
	 *
	 * @param array $detailed_data Detailed data
	 * @param string $nation_slug Station slug
	 * @return array Prepared nation data
	 */
	private function prepare_nation_data( $detailed_data, $nation_slug, $format, $view ) {
		$clean_nation = ltrim( $nation_slug, '_' );
		$nation_data  = $detailed_data;

		if ( empty( $nation_data ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'No data found for nation: ' . $clean_nation );
			return 'count' === $format ? 0 : array();
		}

		// Get specific view data
		$data = array();
		switch ( $view ) {
			case 'gender':
				$data = $nation_data['gender'] ?? array();
				break;
			case 'sexuality':
				$data = $nation_data['sexuality'] ?? array();
				break;
			case 'tropes':
				$data = $nation_data['tropes'] ?? array();
				break;
			case 'formats':
				$data = $nation_data['formats'] ?? array();
				break;
			case 'on-air':
				$data = $nation_data['on_air'] ?? array();
				break;
			case 'all':
			default:
				$data = $nation_data;
				break;
		}

		// If format is 'array', return raw data
		if ( 'array' === $format ) {
			return $data;
		}

		$return_data = array(
			'formatted'    => $data,
			'clean_nation' => $clean_nation,
		);
		return $return_data;
	}

	/**
	 * Get basic data for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Basic nation data
	 */
	private function get_nation_basic_data( $nation_slug ) {
		global $wpdb;

		// remove underscore prefix
		$nation_slug = ltrim( $nation_slug, '_' );

		// Create cache key
		$cache_key   = 'nation_basic_data_' . $nation_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count,
					SUM(COALESCE(char_count.meta_value, 0)) as character_count,
					SUM(COALESCE(dead_count.meta_value, 0)) as dead_count,
					(SELECT COUNT(DISTINCT dead_p.ID)
					FROM {$wpdb->posts} dead_p
					INNER JOIN {$wpdb->term_relationships} dead_tr ON dead_p.ID = dead_tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} dead_tt ON dead_tr.term_taxonomy_id = dead_tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} dead_t ON dead_tt.term_id = dead_t.term_id
					INNER JOIN {$wpdb->term_relationships} nation_dead_tr ON dead_p.ID = nation_dead_tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} nation_dead_tt ON nation_dead_tr.term_taxonomy_id = nation_dead_tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} nation_dead_t ON nation_dead_tt.term_id = nation_dead_t.term_id
					WHERE dead_tt.taxonomy = 'lez_tropes'
					AND dead_t.slug = 'dead-queers'
					AND nation_dead_tt.taxonomy = 'lez_country'
					AND nation_dead_t.slug = %s
					AND dead_p.post_type = 'post_type_shows'
					AND dead_p.post_status = 'publish'
					) as dead_show_count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
				LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
				WHERE tt.taxonomy = %s
				AND t.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name",
				$nation_slug,
				'lez_country',
				$nation_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results || empty( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Basic data query failed for nation: ' . $nation_slug . ' - Last error: ' . $wpdb->last_error );
				return array();
			}

			lwtv_plugin()->set_transient( $cache_key, $results[0], DAY_IN_SECONDS );

			return $results[0];
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Query failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get gender breakdown for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Gender breakdown data
	 */
	private function get_gender_breakdown( $nation_slug ) {
		// This will be implemented to parse the lezshows_char_gender meta field
		// and aggregate the data for the nation
		return $this->parse_meta_breakdown( $nation_slug, 'lezshows_char_gender' );
	}

	/**
	 * Get sexuality breakdown for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Sexuality breakdown data
	 */
	private function get_sexuality_breakdown( $nation_slug ) {
		// This will be implemented to parse the lezshows_char_sexuality meta field
		// and aggregate the data for the nation
		return $this->parse_meta_breakdown( $nation_slug, 'lezshows_char_sexuality' );
	}

	/**
	 * Get tropes breakdown for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Tropes breakdown data
	 */
	private function get_tropes_breakdown( $nation_slug ) {
		global $wpdb;

		$nation_slug = ltrim( $nation_slug, '_' );

		// Create cache key
		$cache_key   = 'nation_tropes_' . $nation_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_relationships} nation_tr ON p.ID = nation_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} nation_tt ON nation_tr.term_taxonomy_id = nation_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} nation_term ON nation_tt.term_id = nation_term.term_id
				WHERE tt.taxonomy = %s
				AND nation_tt.taxonomy = %s
				AND nation_term.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY show_count DESC",
				'lez_tropes',
				'lez_country',
				$nation_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Tropes breakdown query failed for nation: ' . $nation_slug );
				return array();
			}

			// Format results for consistency with other breakdowns
			$formatted_results = array();
			foreach ( $results as $row ) {
				$formatted_results[] = array(
					'name'  => $row['name'],
					'count' => (int) $row['show_count'],
					'url'   => home_url( '/trope/' . $row['slug'] . '/' ),
				);
			}

			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Tropes breakdown query failed for nation: ' . $nation_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get formats breakdown for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Formats breakdown data
	 */
	private function get_formats_breakdown( $nation_slug ) {
		global $wpdb;

		$nation_slug = ltrim( $nation_slug, '_' );

		// Create cache key
		$cache_key   = 'nation_formats_' . $nation_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_relationships} nation_tr ON p.ID = nation_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} nation_tt ON nation_tr.term_taxonomy_id = nation_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} nation_term ON nation_tt.term_id = nation_term.term_id
				WHERE tt.taxonomy = %s
				AND nation_tt.taxonomy = %s
				AND nation_term.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY show_count DESC",
				'lez_formats',
				'lez_country',
				$nation_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Formats breakdown query failed for nation: ' . $nation_slug );
				return array();
			}

			// Format results for consistency with other breakdowns
			$formatted_results = array();
			foreach ( $results as $row ) {
				$formatted_results[] = array(
					'name'  => $row['name'],
					'count' => (int) $row['show_count'],
					'url'   => home_url( '/format/' . $row['slug'] . '/' ),
				);
			}

			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Formats breakdown query failed for nation: ' . $nation_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get on-air data for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array On-air data
	 */
	private function get_onair_breakdown( $nation_slug ) {
		$nation_slug = ltrim( $nation_slug, '_' );

		// Use existing On_Air class to get year-by-year data for this nation
		$on_air_builder = new Build_On_Air();

		// Get shows for this nation
		$nation_shows = $this->get_nation_shows( $nation_slug );

		if ( empty( $nation_shows ) ) {
			return array();
		}

		// Build on-air data using the existing optimized method
		$on_air_data = $on_air_builder->make( 'post_type_shows', $nation_shows, $nation_slug );

		return $on_air_data;
	}

	/**
	 * Get shows for a specific nation
	 *
	 * @param string $nation_slug Station slug
	 * @return array Array of show IDs
	 */
	private function get_nation_shows( $nation_slug ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'nation_shows_' . $nation_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {

			$query = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s
				AND t.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'",
				'lez_country',
				$nation_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				return array();
			}

			// Cache the flat ID list (not raw rows) so the cache-hit and cache-miss
			// return shapes match; callers expect a flat array of show IDs.
			$show_ids = array_column( $results, 'ID' );
			lwtv_plugin()->set_transient( $cache_key, $show_ids, DAY_IN_SECONDS );

			return $show_ids;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Shows query failed for nation: ' . $nation_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Parse meta field breakdown data for a nation
	 *
	 * @param string $nation_slug Station slug
	 * @param string $meta_key Meta key to parse
	 * @return array Parsed breakdown data
	 */
	private function parse_meta_breakdown( $nation_slug, $meta_key ) {
		global $wpdb;

		$nation_slug = ltrim( $nation_slug, '_' );

		// Create cache key - unique per nation and meta_key type
		$cache_key   = 'nation_meta_' . $nation_slug . '_' . str_replace( 'lezshows_char_', '', $meta_key );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {

			$query = $wpdb->prepare(
				"SELECT meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE tt.taxonomy = %s
				AND t.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value IS NOT NULL
				AND pm.meta_value != ''",
				'lez_country',
				$nation_slug,
				$meta_key
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results || empty( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Meta breakdown query failed for nation: ' . $nation_slug );
				return array();
			}

			// Parse and aggregate the serialized data
			$aggregated_data = array();
			foreach ( $results as $row ) {
				$meta_data = maybe_unserialize( $row['meta_value'] );
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $key => $value ) {
						if ( ! isset( $aggregated_data[ $key ] ) ) {
							$aggregated_data[ $key ] = 0;
						}
						$aggregated_data[ $key ] += (int) $value;
					}
				}
			}

			// Determine URL prefix based on meta_key
			$url_prefix = 'gender';
			if ( 'lezshows_char_sexuality' === $meta_key ) {
				$url_prefix = 'sexuality';
			}

			// Format results for consistency with other breakdowns
			$formatted_results = array();
			foreach ( $aggregated_data as $key => $count ) {
				$formatted_results[] = array(
					'name'  => ucwords( str_replace( '-', ' ', $key ) ),
					'count' => (int) $count,
					'url'   => home_url( '/' . $url_prefix . '/' . $key . '/' ),
					'slug'  => $key,
				);
			}

			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Meta breakdown query failed for nation: ' . $nation_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get top X nations
	 *
	 * @param int $number Number of nations to get
	 * @return array Array of top X nations
	 */
	public function get_top_nations( $number ): array {
		global $wpdb;

		// Create cache key
		$cache_key   = 'top_nations_' . $number;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return (array) $cached_data;
		}

		try {

			$queery = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					t.term_id,
					COUNT(DISTINCT p.ID) as count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID AND p.post_status = 'publish'
				WHERE tt.taxonomy = %s
				AND p.post_type = 'post_type_shows'
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY count DESC, t.name ASC
				LIMIT %d",
				'lez_country',
				$number
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Don't cache a failed query; return empty so the next call retries.
			if ( ! is_array( $results ) ) {
				lwtv_plugin()->error_log( 'statistics', 'Top nations query failed for number: ' . $number . ' - Last error: ' . $wpdb->last_error );
				return array();
			}

			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Top nations query failed for number: ' . $number . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}
}
