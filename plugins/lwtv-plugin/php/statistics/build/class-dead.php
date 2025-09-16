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

	/**
	 * Generate dead characters statistics
	 *
	 * @param string $subject Subject type (years/roles/sexuality/gender/stations/nations)
	 * @param string $view View type (characters/shows)
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array Dead characters statistics data
	 */
	public function generate_characters( $view, $format ) {
		lwtv_plugin()->error_log( 'dead-debug', 'Generating characters statistics for view: ' . $view );
		switch ( $view ) {
			case 'years':
			case 'trendline':
				$return = $this->generate_years( $format );
				break;
			case 'all':
				$return = $this->generate_all( $format );
				break;
			default:
				$return = array();
				break;
		}
		return $return;
	}

	/**
	 * Generate all data
	 *
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array All data
	 */
	public function generate_all( $format ) {
		switch ( $format ) {
			case 'list':
			case 'time':
				return $this->generate_list( $format );
			default:
				return array();
		}
	}

	/**
	 * Generate dead shows statistics
	 *
	 * @param string $subject Subject type (years/roles/sexuality/gender/stations/nations)
	 * @param string $view View type (characters/shows)
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array Dead shows statistics data
	 */
	public function generate_shows( $view, $format ) {
		switch ( $view ) {
			case 'years':
				return $this->generate_years( $format );
		}
		return array();
	}

	/**
	 * Generate dead years statistics
	 *
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array Dead years statistics data
	 */
	public function generate_years( $format ) {
		// 1. Get how many characters died in each year
		$years       = $this->generate_years_data();
		$total_years = range( LWTV_FIRST_YEAR, gmdate( 'Y' ) );

		if ( empty( $years ) ) {
			lwtv_plugin()->error_log( 'dead-debug', 'Years are empty. Cannot generate years statistics for format: ' . $format );
			return array();
		}

		switch ( $format ) {
			case 'average':
				$return = number_format( array_sum( array_column( $years, 'death_count' ) ) / count( $total_years ), 2 );
				break;
			case 'trendline':
				$return = array(
					'years' => $this->generate_trendline( $years, $total_years ),
				);
				break;
			default:
				$return = $years;
				break;
		}
		return $return;
	}

	/**
	 * Generate years data
	 *
	 * A simple query to get the years and the count of characters that died in that year.
	 *
	 * @return array Years data
	 */
	public function generate_years_data() {
		global $wpdb;

		// Get all death year meta data (serialized arrays)
		$query = $wpdb->prepare(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND meta_value IS NOT NULL
			AND meta_value != ''",
			'lezchars_death_year'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $query, ARRAY_A );

		$year_counts = array();

		foreach ( $results as $row ) {
			// Unserialize the meta value
			$death_dates = maybe_unserialize( $row['meta_value'] );

			// Ensure it's an array
			if ( ! is_array( $death_dates ) ) {
				continue;
			}

			// Extract years from each death date
			foreach ( $death_dates as $death_date ) {
				// Extract year from Y-m-d format
				if ( preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $death_date, $matches ) ) {
					$year = $matches[1];

					// Count this year
					if ( ! isset( $year_counts[ $year ] ) ) {
						$year_counts[ $year ] = 0;
					}
					++$year_counts[ $year ];
				}
			}
		}

		// Convert to the expected format
		$formatted_results = array();
		foreach ( $year_counts as $year => $count ) {
			$formatted_results[] = array(
				'death_year'  => $year,
				'death_count' => $count,
			);
		}

		// Sort by year
		usort(
			$formatted_results,
			function ( $a, $b ) {
				return $a['death_year'] <=> $b['death_year'];
			}
		);

		return $formatted_results;
	}

	/**
	 * Generate trendline data
	 *
	 * We need to make sure we have all years in the trendline, so we need to add 0 for
	 * years that are not in the years data.
	 *
	 * @param array $years Years data
	 * @param array $total_years Total years data
	 *
	 * @return array Trendline data
	 */
	public function generate_trendline( $years, $total_years ) {
		$trendline = array();

		// Add the years data to the trendline
		foreach ( $years as $year ) {
			$trendline[] = array(
				'name'  => $year['death_year'],
				'count' => $year['death_count'] ?? 0,
			);
		}

		// Add 0 for years that are not in the years data
		foreach ( $total_years as $year ) {
			if ( ! isset( $trendline[ $year ] ) ) {
				$trendline[] = array(
					'name'  => $year,
					'count' => 0,
				);
			}
		}

		return $trendline;
	}

	/**
	 * Generate list data
	 *
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array List data
	 */
	public function generate_list( $format ) {
		try {
			$transient = 'dead_list_' . $format;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_list( $format );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-list-error', 'Error building dead list: ' . $e->getMessage() );
			return 'time' === $format ? array(
				'most'  => array(
					'count' => 0,
					'date'  => '0000-00-00',
				),
				'time'  => 0,
				'start' => '',
				'end'   => '',
			) : array();
		}
	}

	/**
	 * Build dead list statistics using optimized single query
	 *
	 * @param string $format Output format (array|time)
	 * @return array
	 */
	private function build_list( $format ) {
		global $wpdb;

		try {
			// Single optimized query to get all dead character data
			$queery = "SELECT
				p.ID,
				p.post_title,
				p.post_name,
				p.post_status,
				death_meta.meta_value as death_years
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} death_meta ON p.ID = death_meta.post_id AND death_meta.meta_key = 'lezchars_death_year'
			WHERE p.post_type = 'post_type_characters'
			AND p.post_status = 'publish'
			AND death_meta.meta_value IS NOT NULL
			AND death_meta.meta_value != ''
			ORDER BY p.post_title";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$results = $wpdb->get_results( $queery, ARRAY_A );

			$array = array();

			foreach ( $results as $row ) {
				$char_id     = (int) $row['ID'];
				$death_years = maybe_unserialize( $row['death_years'] );

				if ( ! is_array( $death_years ) ) {
					continue;
				}

				foreach ( $death_years as $died_date ) {
					// If there's no entry, add it.
					if ( ! isset( $array[ $died_date ] ) ) {
						$array[ $died_date ] = array(
							'date' => $died_date,
						);
					}

					$array[ $died_date ]['chars'][ $char_id ] = array(
						'name' => $row['post_title'],
						'url'  => get_permalink( $char_id ),
					);
				}
			}

			// sort by date (newest first)
			krsort( $array );

			// calculate time since last death and most dead in a day.
			$keys      = array_keys( $array );
			$key_count = count( $keys ) - 1;
			for ( $i = 0; $i < $key_count; $i++ ) {
				// Check the diff
				$date1 = date_create( $keys[ $i ] );
				$date2 = date_create( $keys[ $i + 1 ] );
				$diff  = date_diff( $date1, $date2 );
				$days  = $diff->format( '%a' );

				// Add the time since last death
				$array[ $keys[ $i ] ]['since'] = $days;

				// Add the most dead in a day
				$array[ $keys[ $i ] ]['most'] = count( $array[ $keys[ $i ] ]['chars'] );
			}

			// Change what we output...
			switch ( $format ) {
				case 'array':
					return $array;
				case 'time':
					$diff_since = array(
						'time'      => max( array_column( $array, 'since' ) ),
						'most'      => max( array_column( $array, 'most' ) ),
						'most_date' => '0000-00-00',
					);
					for ( $i = 0; $i < $key_count; $i++ ) {
						if ( $diff_since['time'] === $array[ $keys[ $i ] ]['since'] ) {
							$diff_since['end']   = $keys[ $i ];
							$diff_since['start'] = $keys[ $i + 1 ];
						}

						if ( $diff_since['most'] === $array[ $keys[ $i ] ]['most'] ) {
							if ( $diff_since['most_date'] < $array[ $keys[ $i ] ]['date'] ) {
								$diff_since['most_date'] = $array[ $keys[ $i ] ]['date'];
							}
						}
					}
					return array(
						'most'  => array(
							'count' => $diff_since['most'],
							'date'  => $diff_since['most_date'],
						),
						'time'  => $diff_since['time'],
						'start' => $diff_since['start'],
						'end'   => $diff_since['end'],
					);
			}

			return array(
				'all' => $array,
			);

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-list-error', 'Error building dead list statistics: ' . $e->getMessage() );
			return 'time' === $format ? array(
				'most'  => array(
					'count' => 0,
					'date'  => '0000-00-00',
				),
				'time'  => 0,
				'start' => '',
				'end'   => '',
			) : array();
		}
	}

	/**
	 * Generate stats data
	 *
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array Stats data
	 */
	public function generate_stats( $format ) {
		$list_of_dead = $this->generate_list( $format );

		// find the entry with the highest 'since' value
		$highest_since = max( array_column( $list_of_dead, 'since' ) );

		// find the entry with the highest 'most' value
		$most_dead      = max( array_column( $list_of_dead, 'most' ) );
		$most_dead_date = array_search( $most_dead, array_column( $list_of_dead, 'most' ), true );

		$return = array(
			'highest_since'  => $highest_since,
			'most_dead'      => $most_dead,
			'most_dead_date' => $most_dead_date,
		);

		return array(
			'stats' => $return,
		);
	}
}
