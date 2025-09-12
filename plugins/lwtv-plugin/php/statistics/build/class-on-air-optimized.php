<?php
/**
 * The build class for on-air statistics - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

class On_Air_Optimized {

	/**
	 * Generate on-air statistics
	 *
	 * @param string $post_type Post type to generate statistics for
	 * @return array
	 */
	public function generate( $post_type ) {
		$all_data = array();

		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
		$dt->setTimestamp( $timestamp );
		$this_year = $dt->format( 'Y' );

		$year_first = LWTV_FIRST_YEAR;
		$year_range = range( $year_first, $this_year );

		lwtv_plugin()->error_log( 'on-air-debug', 'Building on air statistics for post type: ' . $post_type );

		switch ( $post_type ) {
			case 'shows':
				$all_data = $this->build_shows( $year_range );
				break;
			case 'characters':
				$all_data = $this->build_characters( $year_range );
				break;
			default:
				lwtv_plugin()->error_log( 'on-air-debug', 'Invalid post type: ' . $post_type );
				break;
		}

		return $all_data;
	}

	/**
	 * Build characters on air statistics using optimized single query
	 *
	 * @param array $year_range Array of years
	 * @param array $filtered_data Filtered data (WP_Query object or array)
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	private function build_characters( $year_range, $filtered_data = false ) {
		global $wpdb;

		try {
			$array = array();

			// Single optimized query to get character on air counts by year
			// phpcs:disable
			$queery = "SELECT
				SUBSTRING_INDEX(SUBSTRING_INDEX(show_meta.meta_value, '\"', 4), '\"', -1) as air_year,
				COUNT(DISTINCT chars.ID) as char_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} show_meta ON chars.ID = show_meta.post_id AND show_meta.meta_key = 'lezchars_show_group'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			AND show_meta.meta_value IS NOT NULL
			AND show_meta.meta_value != ''
			AND SUBSTRING_INDEX(SUBSTRING_INDEX(show_meta.meta_value, '\"', 4), '\"', -1) REGEXP '^[0-9]{4}$'
			GROUP BY air_year
			ORDER BY air_year DESC";
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Build base array with all years
			foreach ( $year_range as $year ) {
				$array[ $year ] = array(
					'name'  => $year,
					'count' => 0,
					'url'   => home_url( '/this-year/' . $year . '/' ),
				);
			}

			// Update counts with actual data
			foreach ( $results as $row ) {
				$air_year = (int) $row['air_year'];
				if ( isset( $array[ $air_year ] ) ) {
					$array[ $air_year ]['count'] = (int) $row['char_count'];
				}
			}

			// Sort array by year keys in ascending order (oldest first)
			ksort( $array );

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'on-air-error', 'Error building characters on air statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build shows on air statistics using optimized single query
	 *
	 * @param array $year_range Array of years
	 * @param array $filtered_data Filtered data (WP_Query object or array)
	 * @return array
	 */
	private function build_shows( $year_range, $filtered_data = false ) {
		global $wpdb;

		try {
			$array = array();

			// Debug the filtered data
			lwtv_plugin()->error_log( 'on-air-debug', 'build_shows received filtered_data type: ' . gettype( $filtered_data ) );
			if ( $filtered_data ) {
				if ( is_object( $filtered_data ) ) {
					lwtv_plugin()->error_log( 'on-air-debug', 'filtered_data is object, class: ' . get_class( $filtered_data ) );
					if ( isset( $filtered_data->posts ) ) {
						lwtv_plugin()->error_log( 'on-air-debug', 'filtered_data has posts property with ' . count( $filtered_data->posts ) . ' items' );
					}
				} elseif ( is_array( $filtered_data ) ) {
					lwtv_plugin()->error_log( 'on-air-debug', 'filtered_data is array with ' . count( $filtered_data ) . ' items' );
				}
			}

			// Extract show IDs from filtered data if provided
			$show_ids = array();
			if ( $filtered_data && is_object( $filtered_data ) && isset( $filtered_data->posts ) ) {
				// It's a WP_Query object
				foreach ( $filtered_data->posts as $post ) {
					$show_ids[] = $post->ID;
				}
				lwtv_plugin()->error_log( 'on-air-debug', 'Extracted ' . count( $show_ids ) . ' show IDs from WP_Query object' );
			} elseif ( $filtered_data && is_array( $filtered_data ) ) {
				// It's an array of post IDs
				$show_ids = $filtered_data;
				lwtv_plugin()->error_log( 'on-air-debug', 'Using ' . count( $show_ids ) . ' show IDs from array' );
			} else {
				lwtv_plugin()->error_log( 'on-air-debug', 'No filtered data provided, querying all shows' );
			}

			// Build WHERE clause for show IDs if we have filtered data
			$where_clause = '';
			if ( ! empty( $show_ids ) ) {
				$show_ids_placeholders = implode( ',', array_fill( 0, count( $show_ids ), '%d' ) );
				$where_clause          = "AND shows.ID IN ({$show_ids_placeholders})";
			}

			// Query to get all shows with their airdates meta data
			$queery = "SELECT
				shows.ID,
				air_meta.meta_value as airdates_serialized
			FROM {$wpdb->posts} shows
			INNER JOIN {$wpdb->postmeta} air_meta ON shows.ID = air_meta.post_id AND air_meta.meta_key = 'lezshows_airdates'
			WHERE shows.post_type = 'post_type_shows'
			AND shows.post_status = 'publish'
			AND air_meta.meta_value IS NOT NULL
			AND air_meta.meta_value != ''
			{$where_clause}";

			// Execute query with or without prepared statement
			if ( ! empty( $show_ids ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
				$results = $wpdb->get_results( $wpdb->prepare( $queery, ...$show_ids ), ARRAY_A );
				lwtv_plugin()->error_log( 'on-air-debug', 'Query executed with ' . count( $show_ids ) . ' show IDs, returned ' . count( $results ) . ' results' );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
				$results = $wpdb->get_results( $queery, ARRAY_A );
				lwtv_plugin()->error_log( 'on-air-debug', 'Query executed without filtering, returned ' . count( $results ) . ' results' );
			}

			// If we have filtered data, determine the actual year range from the shows' airdates
			$actual_year_range = $year_range;
			if ( ! empty( $results ) ) {
				$earliest_start = null;
				$latest_finish  = null;

				// First pass: find the actual year range from the shows' airdates
				foreach ( $results as $row ) {
					$airdates_serialized = $row['airdates_serialized'];
					$airdates            = maybe_unserialize( $airdates_serialized );

					if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
						continue;
					}

					$start_year  = (int) $airdates['start'];
					$finish_year = ( 'current' === $airdates['finish'] ) ? (int) gmdate( 'Y' ) : (int) $airdates['finish'];

					if ( null === $earliest_start || $start_year < $earliest_start ) {
						$earliest_start = $start_year;
					}
					if ( null === $latest_finish || $finish_year > $latest_finish ) {
						$latest_finish = $finish_year;
					}
				}

				// If we found valid years, use the actual range
				if ( null !== $earliest_start && null !== $latest_finish ) {
					$actual_year_range = range( $earliest_start, $latest_finish );
					lwtv_plugin()->error_log( 'on-air-debug', 'Using actual year range from shows: ' . $earliest_start . ' to ' . $latest_finish );
				}
			}

			// Build base array with actual year range
			foreach ( $actual_year_range as $year ) {
				$array[ $year ] = array(
					'name'  => $year,
					'count' => 0,
					'url'   => home_url( '/this-year/' . $year . '/' ),
				);
			}

			// Process each show's airdates to determine which years it was on air
			$year_counts = array();
			foreach ( $results as $row ) {
				$airdates_serialized = $row['airdates_serialized'];
				$airdates            = maybe_unserialize( $airdates_serialized );

				if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
					continue;
				}

				$start_year  = (int) $airdates['start'];
				$finish_year = ( 'current' === $airdates['finish'] ) ? (int) gmdate( 'Y' ) : (int) $airdates['finish'];

				// A show is "on air" for any year between start and finish (inclusive)
				for ( $year = $start_year; $year <= $finish_year; $year++ ) {
					if ( isset( $array[ $year ] ) ) {
						$year_counts[ $year ] = ( $year_counts[ $year ] ?? 0 ) + 1;
					}
				}
			}

			// Update counts with actual data
			foreach ( $year_counts as $year => $count ) {
				$array[ $year ]['count'] = $count;
			}

			// Sort array by year keys in ascending order (oldest first)
			ksort( $array );

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'on-air-error', 'Error building shows on air statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Statistics On Air - Optimized with single query
	 *
	 * Note: This is being phased out.
	 *
	 * Trying to do math of who's on what year.
	 * Now optimized with single query instead of N+1 pattern.
	 *
	 * @param string $post_type  Post Type of data (show or character)
	 * @param array  $data       Array of data to loop at.
	 * @param string $minor      String for if this is a subset (like station or nation)
	 *
	 * @return array
	 */
	public function make( $post_type, $data = false, $minor = false ) {

		// Debug logging
		lwtv_plugin()->error_log( 'on-air-debug', 'On_Air make called with post_type: ' . $post_type . ', minor: ' . $minor );

		try {
			$transient = 'on_air_stats_' . $post_type;
			$transient = ( false !== $minor ) ? $transient . '_' . $minor : $transient;
			$array     = lwtv_plugin()->get_transient( $transient );

			lwtv_plugin()->error_log( 'on-air-debug', 'Transient key: ' . $transient . ', cached: ' . ( false !== $array ? 'yes' : 'no' ) );

			if ( false === $array ) {
				$array = $this->build_on_air_optimized( $post_type, $data );

				if ( empty( $array ) ) {
					// If we're empty, delete the transients.
					lwtv_plugin()->delete_transient( $transient );
				} else {
					// Otherwise save array as transient for 14 hours
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			// Fallback if empty so we don't throw errors.
			if ( empty( $array ) ) {
				$timestamp = time();
				$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
				$dt->setTimestamp( $timestamp );
				$this_year = $dt->format( 'Y' );

				$array[ $this_year ] = array(
					'name'  => $this_year,
					'count' => 0,
					'url'   => home_url( '/this-year/' ),
				);
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'on-air-error', 'Error building on air statistics: ' . $e->getMessage() );

			$timestamp = time();
			$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
			$dt->setTimestamp( $timestamp );
			$this_year = $dt->format( 'Y' );

			return array(
				$this_year => array(
					'name'  => $this_year,
					'count' => 0,
					'url'   => home_url( '/this-year/' ),
				),
			);
		}
	}

	/**
	 * Build on air statistics using optimized single query
	 *
	 * @param string $post_type Post type to query
	 * @param mixed  $data Filtered data (WP_Query object or array)
	 *
	 * @return array
	 */
	private function build_on_air_optimized( $post_type, $data = false ) {
		try {
			$array = array();

			// Create the date with regards to timezones
			$timestamp = time();
			$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
			$dt->setTimestamp( $timestamp );
			$this_year = $dt->format( 'Y' );

			// Array of Years
			$year_first = LWTV_FIRST_YEAR;
			$year_range = range( $year_first, $this_year );

			if ( 'post_type_characters' === $post_type ) {
				$array = $this->build_characters( $year_range, $data );
			} elseif ( 'post_type_shows' === $post_type ) {
				$array = $this->build_shows( $year_range, $data );
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'on-air-error', 'Error building on air statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
