<?php
/**
 * Stations Statistics Builder
 *
 * Handles station-specific statistics generation with optimized queries
 * and proper caching strategy.
 *
 * @package LWTV_Plugin
 * @subpackage Statistics
 */

namespace LWTV\Statistics\Build;

use LWTV\Statistics\Build\On_Air as Build_On_Air;

/**
 * Stations class
 */
class Stations {

	/**
	 * Get station summary data for main stations page
	 *
	 * Returns basic data for all stations: name, show count, character count
	 * This is lightweight data for the main stations listing page.
	 *
	 * @return array Array of station data
	 */
	public function get_station_summaries() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'station_summaries';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			// Single optimized query to get basic station data
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count,
					SUM(DISTINCT COALESCE(char_count.meta_value, 0)) as character_count,
					SUM(DISTINCT COALESCE(dead_count.meta_value, 0)) as dead_count
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
				'lez_stations'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results ) {
				lwtv_plugin()->error_log( 'stations-error', 'Query failed: ' . $wpdb->last_error );
				return array();
			}

			// Cache the results
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Query failed: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get detailed data for a specific station
	 *
	 * Returns comprehensive data for a single station including
	 * all breakdowns (gender, sexuality, tropes, etc.)
	 *
	 * @param string $station_slug Station slug (e.g., 'cbs', 'abc')
	 * @return array Station detailed data
	 */
	public function get_station_details( $station_slug, $format, $view ) {
		lwtv_plugin()->error_log( 'stations-debug', 'Getting station details for ' . $station_slug . ' with format: ' . $format . ' and view: ' . $view );

		// Sanitize input
		$station_slug = sanitize_text_field( $station_slug );

		// Create cache key
		$cache_key   = 'station_details_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'stations-debug', 'Cached data found for ' . $station_slug . ': ' . $cache_key );
			return $cached_data;
		}

		try {
			// Get basic station data
			$basic_data = $this->get_station_basic_data( $station_slug );

			if ( empty( $basic_data ) ) {
				lwtv_plugin()->error_log( 'stations-error', 'Basic data query failed for station: ' . $station_slug );
				return array();
			}

			// Get detailed breakdowns
			$detailed_data = array(
				'basic'     => $basic_data,
				'gender'    => $this->get_gender_breakdown( $station_slug ),
				'sexuality' => $this->get_sexuality_breakdown( $station_slug ),
				'tropes'    => $this->get_tropes_breakdown( $station_slug ),
				'formats'   => $this->get_formats_breakdown( $station_slug ),
				'on_air'    => $this->get_onair_breakdown( $station_slug ),
			);

			// Cache the results
			lwtv_plugin()->set_transient( $cache_key, $detailed_data, DAY_IN_SECONDS );

			// Format the results
			$return_data = $this->prepare_station_data( $detailed_data, $station_slug, $format, $view );

			return $return_data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Query failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Prepare station data for return
	 *
	 * @param array $detailed_data Detailed data
	 * @param string $station_slug Station slug
	 * @return array Prepared station data
	 */
	private function prepare_station_data( $detailed_data, $station_slug, $format, $view ) {
		$clean_station = ltrim( $station_slug, '_' );
		$station_data  = $detailed_data;

		if ( empty( $station_data ) ) {
			lwtv_plugin()->error_log( 'stations-error', 'No data found for station: ' . $clean_station );
			return 'count' === $format ? 0 : array();
		}

		// Get specific view data
		$data = array();
		switch ( $view ) {
			case 'gender':
				$data = $station_data['gender'] ?? array();
				break;
			case 'sexuality':
				$data = $station_data['sexuality'] ?? array();
				break;
			case 'tropes':
				$data = $station_data['tropes'] ?? array();
				break;
			case 'formats':
				$data = $station_data['formats'] ?? array();
				break;
			case 'on-air':
				$data = $station_data['on_air'] ?? array();
				break;
			case 'all':
			default:
				$data = $station_data;
				break;
		}

		lwtv_plugin()->error_log( 'stations-debug', 'Prepared station data: ' . $format . ' ' . $view );

		// If format is 'array', return raw data
		if ( 'array' === $format ) {
			return $data;
		}

		$return_data = array(
			'formatted'     => $data,
			'clean_station' => $clean_station,
		);
		return $return_data;
	}

	/**
	 * Get basic data for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Basic station data
	 */
	private function get_station_basic_data( $station_slug ) {
		global $wpdb;

		// remove underscore prefix
		$station_slug = ltrim( $station_slug, '_' );

		// Create cache key
		$cache_key   = 'station_basic_data_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as show_count,
					SUM(DISTINCT COALESCE(char_count.meta_value, 0)) as character_count,
					SUM(DISTINCT COALESCE(dead_count.meta_value, 0)) as dead_count,
					(SELECT COUNT(DISTINCT dead_p.ID)
					FROM {$wpdb->posts} dead_p
					INNER JOIN {$wpdb->term_relationships} dead_tr ON dead_p.ID = dead_tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} dead_tt ON dead_tr.term_taxonomy_id = dead_tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} dead_t ON dead_tt.term_id = dead_t.term_id
					INNER JOIN {$wpdb->term_relationships} station_dead_tr ON dead_p.ID = station_dead_tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} station_dead_tt ON station_dead_tr.term_taxonomy_id = station_dead_tt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} station_dead_t ON station_dead_tt.term_id = station_dead_t.term_id
					WHERE dead_tt.taxonomy = 'lez_tropes'
					AND dead_t.slug = 'dead-queers'
					AND station_dead_tt.taxonomy = 'lez_stations'
					AND station_dead_t.slug = %s
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
				$station_slug,
				'lez_stations',
				$station_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results || empty( $results ) ) {
				lwtv_plugin()->error_log( 'stations-error', 'Basic data query failed for station: ' . $station_slug . ' - Last error: ' . $wpdb->last_error );
				return array();
			}

			lwtv_plugin()->set_transient( $cache_key, $results[0], DAY_IN_SECONDS );

			return $results[0];
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Basic data query failed for station: ' . $station_slug . ' - Last error: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get gender breakdown for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Gender breakdown data
	 */
	private function get_gender_breakdown( $station_slug ) {
		// This will be implemented to parse the lezshows_char_gender meta field
		// and aggregate the data for the station
		return $this->parse_meta_breakdown( $station_slug, 'lezshows_char_gender' );
	}

	/**
	 * Get sexuality breakdown for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Sexuality breakdown data
	 */
	private function get_sexuality_breakdown( $station_slug ) {
		// This will be implemented to parse the lezshows_char_sexuality meta field
		// and aggregate the data for the station
		return $this->parse_meta_breakdown( $station_slug, 'lezshows_char_sexuality' );
	}

	/**
	 * Get tropes breakdown for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Tropes breakdown data
	 */
	private function get_tropes_breakdown( $station_slug ) {
		global $wpdb;

		$station_slug = ltrim( $station_slug, '_' );

		// Create cache key
		$cache_key   = 'station_tropes_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
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
				INNER JOIN {$wpdb->term_relationships} station_tr ON p.ID = station_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} station_tt ON station_tr.term_taxonomy_id = station_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} station_term ON station_tt.term_id = station_term.term_id
				WHERE tt.taxonomy = %s
				AND station_tt.taxonomy = %s
				AND station_term.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY show_count DESC",
				'lez_tropes',
				'lez_stations',
				$station_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results ) {
				lwtv_plugin()->error_log( 'stations-error', 'Tropes breakdown query failed for station: ' . $station_slug );
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
			lwtv_plugin()->error_log( 'stations-error', 'Tropes breakdown query failed for station: ' . $station_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get formats breakdown for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Formats breakdown data
	 */
	private function get_formats_breakdown( $station_slug ) {
		global $wpdb;

		$station_slug = ltrim( $station_slug, '_' );

		// Create cache key
		$cache_key   = 'station_formats_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
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
				INNER JOIN {$wpdb->term_relationships} station_tr ON p.ID = station_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} station_tt ON station_tr.term_taxonomy_id = station_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} station_term ON station_tt.term_id = station_term.term_id
				WHERE tt.taxonomy = %s
				AND station_tt.taxonomy = %s
				AND station_term.slug = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY show_count DESC",
				'lez_formats',
				'lez_stations',
				$station_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results ) {
				lwtv_plugin()->error_log( 'stations-error', 'Formats breakdown query failed for station: ' . $station_slug );
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
			lwtv_plugin()->error_log( 'stations-error', 'Formats breakdown query failed for station: ' . $station_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get on-air data for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array On-air data
	 */
	private function get_onair_breakdown( $station_slug ) {
		$station_slug = ltrim( $station_slug, '_' );

		// Use existing On_Air class to get year-by-year data for this station
		$on_air_builder = new Build_On_Air();

		// Get shows for this station
		$station_shows = $this->get_station_shows( $station_slug );

		if ( empty( $station_shows ) ) {
			return array();
		}

		// Build on-air data using the existing optimized method
		$on_air_data = $on_air_builder->make( 'post_type_shows', $station_shows, $station_slug );

		return $on_air_data;
	}

	/**
	 * Get shows for a specific station
	 *
	 * @param string $station_slug Station slug
	 * @return array Array of show IDs
	 */
	private function get_station_shows( $station_slug ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'station_shows_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
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
				'lez_stations',
				$station_slug
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			$clean_results = array_column( $results, 'ID' );

			lwtv_plugin()->set_transient( $cache_key, $clean_results, DAY_IN_SECONDS );

			return $clean_results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Shows query failed for station: ' . $station_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Parse meta field breakdown data for a station
	 *
	 * @param string $station_slug Station slug
	 * @param string $meta_key Meta key to parse
	 * @return array Parsed breakdown data
	 */
	private function parse_meta_breakdown( $station_slug, $meta_key ) {
		global $wpdb;

		$station_slug = ltrim( $station_slug, '_' );

		// Create cache key
		$cache_key   = 'station_shows_' . $station_slug;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
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
				'lez_stations',
				$station_slug,
				$meta_key
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( false === $results || empty( $results ) ) {
				lwtv_plugin()->error_log( 'stations-error', 'Meta breakdown query failed for station: ' . $station_slug );
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

			return $aggregated_data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Meta breakdown query failed for station: ' . $station_slug . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}

	/**
	 * Get top X stations
	 *
	 * @param int $number Number of stations to get
	 * @return array Array of top X stations
	 */
	public function get_top_stations( $number ): array {
		global $wpdb;

		// Create cache key
		$cache_key   = 'top_stations_' . $number;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'formats-debug', 'Cached data found for ' . $cache_key );
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
				'lez_stations',
				$number
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'stations-error', 'Top stations query failed for number: ' . $number . ' - Last error: ' . $wpdb->last_error );
			return array();
		}
	}
}
