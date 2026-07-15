<?php
/**
 * Dead Statistics Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Dead {
	/**
	 * Get total dead characters
	 *
	 * @return int Total dead count
	 */
	public function total_dead_characters() {
		$characters = $this->get_dead_characters_data();
		return count( $characters );
	}

	/**
	 * Get dead characters data
	 *
	 * @return array Dead characters data
	 */
	public function get_dead_characters_data() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'dead_characters_data';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
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

			// $wpdb->get_results returns null on error; don't cache that (callers
			// run count()/foreach on this) — return empty and retry next time.
			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'death', 'Dead characters query failed: ' . $wpdb->last_error );
				return array();
			}

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting dead characters data: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get dead characters for a given year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of dead characters for the given year
	 */
	public function get_dead_characters_for_year( $year ) {
		$year = (string) $year;

		// Create cache key
		$cache_key   = 'dead_characters_for_year_' . $year;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Reuse the existing dead characters data
			$dead_characters = $this->get_dead_characters_data();

			// Build the set of character IDs that died in $year from a single
			// postmeta scan (shared request cache) rather than a get_field() call
			// per character, which was an N+1 over every dead character.
			$died_this_year = array();
			foreach ( $this->get_death_date_rows() as $row ) {
				// ACF date_picker raw postmeta is Ymd; legacy rows may still be Y-m-d.
				if ( preg_match( '/^(\d{4})-?\d{2}-?\d{2}$/', $row['meta_value'], $matches ) && $matches[1] === $year ) {
					$died_this_year[ (int) $row['post_id'] ] = true;
				}
			}

			// Preserve get_dead_characters_data()'s post_title ordering.
			$year_characters = array();
			foreach ( $dead_characters as $character ) {
				if ( isset( $died_this_year[ (int) $character['ID'] ] ) ) {
					$year_characters[] = $character;
				}
			}

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $year_characters, DAY_IN_SECONDS );

			return $year_characters;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting dead characters for year ' . $year . ': ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get all raw death-date rows from postmeta.
	 *
	 * ACF stores each death date as an individual repeater row key
	 * (lezchars_death_year_N_date). A single postmeta scan returns them all,
	 * request-cached so repeated callers within one request share the result.
	 *
	 * @return array List of [ 'post_id' => string, 'meta_value' => string ] rows.
	 */
	private function get_death_date_rows() {
		global $wpdb;

		$raw_cache_key = 'lwtv_dead_years_raw';
		$results       = wp_cache_get( $raw_cache_key );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				WHERE meta_key LIKE 'lezchars_death_year_%_date'
				AND meta_value != ''",
				ARRAY_A
			);

			if ( ! is_array( $results ) ) {
				$results = array();
			}

			wp_cache_set( $raw_cache_key, $results );
		}

		return $results;
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
			if ( is_array( $cached_data ) ) {
				return count( $cached_data );
			}
			return 0;
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
			lwtv_plugin()->error_log( 'death', 'Error getting total dead shows: ' . $e->getMessage() );
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
		lwtv_plugin()->debug_log( 'death', 'Generating characters statistics for view: ' . $view );
		switch ( $view ) {
			case 'years':
			case 'trendline':
				$return = $this->generate_years( $format );
				break;
			case 'all':
				$return = $this->generate_all( $format );
				break;
			case 'sexuality':
			case 'gender':
				$return = $this->generate_characters_taxonomy( $format, 'lez_' . $view );
				break;
			case 'role':
				$return = $this->generate_characters_by_roles( $format );
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
		lwtv_plugin()->debug_log( 'death', 'Generating shows statistics for view ' . $view . ' and format ' . $format );
		switch ( $view ) {
			case 'years':
				$return = $this->generate_years( $format );
				break;
			case 'per-show':
				$return = $this->generate_shows_by_characters( $format );
				break;
			case 'stations':
			case 'nations':
				$return = $this->generate_shows_by_taxonomy( $format, 'lez_' . $view );
				break;
			default:
				$return = array();
				break;
		}

		return $return;
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
			lwtv_plugin()->debug_log( 'death', 'Years are empty. Cannot generate years statistics for format: ' . $format );
			return array();
		}

		try {
			switch ( $format ) {
				case 'average':
					$return = number_format( array_sum( array_column( $years, 'death_count' ) ) / count( $total_years ), 2 );
					break;
				case 'trendline':
					$return = array(
						'years' => $this->format_years_trendline( $years, $total_years ),
					);
					break;
				case 'barchart':
					$return = $this->format_years_trendline( $years, $total_years );
					break;
				case 'percentage':
					$return = array( 'death' => $this->format_years_percentage( $years, $total_years ) );
					break;
				default:
					$return = $years;
					break;
			}
			return $return;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error generating years statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Generate years data
	 *
	 * A simple query to get the years and the count of characters that died in that year.
	 *
	 * @return array Years data
	 */
	public function generate_years_data() {
		// Create cache key with data version hash
		$cache_key   = 'dead_years_data_' . $this->get_data_version_hash();
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Query individual ACF repeater row keys — one row per death date after migration.
		$results = $this->get_death_date_rows();

		$year_counts = array();

		foreach ( $results as $row ) {
			// ACF's date_picker stores raw postmeta as Ymd; legacy pre-migration
			// rows that haven't been re-saved may still be in Y-m-d format.
			if ( preg_match( '/^(\d{4})-?\d{2}-?\d{2}$/', $row['meta_value'], $matches ) ) {
				$year = $matches[1];
				if ( ! isset( $year_counts[ $year ] ) ) {
					$year_counts[ $year ] = 0;
				}
				++$year_counts[ $year ];
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

		// Cache the results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

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
	public function format_years_trendline( $years, $total_years ) {
		// Map year => death count for lookup. Keys are cast to string so the
		// integer years from range() match the string years parsed from meta.
		$counts_by_year = array();
		foreach ( $years as $year ) {
			$counts_by_year[ (string) $year['death_year'] ] = $year['death_count'] ?? 0;
		}

		// One entry per year across the full range, zero-filled where nobody died.
		// $total_years is range( LWTV_FIRST_YEAR, current year ), so this yields a
		// single row per year — no duplicates.
		$trendline = array();
		foreach ( $total_years as $year ) {
			$trendline[] = array(
				'name'  => $year,
				'count' => $counts_by_year[ (string) $year ] ?? 0,
			);
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
			lwtv_plugin()->error_log( 'death', 'Error building dead list: ' . $e->getMessage() );
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
	 * Format years percentage
	 *
	 * @param array $years Years data
	 * @param array $total_years Total years data
	 *
	 * @return array Percentage data
	 */
	public function format_years_percentage( $years, $total_years ) {
		$percentage        = array();
		$count_total_years = count( $total_years );
		foreach ( $years as $year ) {
			$percentage[] = array(
				'name'       => $year['death_year'],
				'count'      => $year['death_count'] ?? 0,
				'percentage' => $count_total_years ? number_format( (float) $year['death_count'] / $count_total_years, 2, '.', '' ) : '0.00',
			);
		}
		return $percentage;
	}

	/**
	 * Build dead list statistics using optimized single query
	 *
	 * @param string $format Output format (array|time)
	 * @return array
	 */
	private function build_list( $format ) {
		global $wpdb;

		$cache_key   = 'dead_list_' . $format . '_' . $this->get_data_version_hash();
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $this->output_list( $cached_data, $format );
		}

		try {
			// Query individual ACF repeater row keys (lezchars_death_year_N_date).
			$queery = "SELECT
				p.ID,
				p.post_title,
				death_meta.meta_value as died_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} death_meta ON p.ID = death_meta.post_id
			WHERE p.post_type = 'post_type_characters'
			AND p.post_status = 'publish'
			AND death_meta.meta_key LIKE 'lezchars_death_year_%_date'
			AND death_meta.meta_value IS NOT NULL
			AND death_meta.meta_value != ''
			ORDER BY p.post_title";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$results = $wpdb->get_results( $queery, ARRAY_A );

			$array = array();

			foreach ( $results as $row ) {
				$char_id   = (int) $row['ID'];
				$died_date = $row['died_date'];

				if ( empty( $died_date ) ) {
					continue;
				}

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

			// sort by date (newest first)
			krsort( $array );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $array, DAY_IN_SECONDS );

			return $this->output_list( $array, $format );

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error building dead list statistics: ' . $e->getMessage() );
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
	 * Output list data
	 *
	 * @param array $output_array Output array
	 * @param string $format Format type
	 * @return array Output array
	 */
	private function output_list( $output_array, $format ) {
		if ( empty( $output_array ) ) {
			return array();
		}

		try {
			// calculate time since last death and most dead in a day.
			$keys      = array_keys( $output_array );
			$key_count = count( $keys ) - 1;
			for ( $i = 0; $i < $key_count; $i++ ) {
				// Check the diff
				$date1 = date_create( $keys[ $i ] );
				$date2 = date_create( $keys[ $i + 1 ] );
				$diff  = date_diff( $date1, $date2 );
				$days  = $diff->format( '%a' );

				// Add the time since last death
				$output_array[ $keys[ $i ] ]['since'] = $days;

				// Add the most dead in a day
				$output_array[ $keys[ $i ] ]['most'] = count( $output_array[ $keys[ $i ] ]['chars'] );
			}

			switch ( $format ) {
				case 'array':
					return $output_array;
				case 'time':
					// With a single (or zero) death date the loop above never runs, so
					// 'since'/'most' may be absent; guard max() against an empty column
					// (a PHP 8 ValueError) and default start/end.
					$since_col  = array_column( $output_array, 'since' );
					$most_col   = array_column( $output_array, 'most' );
					$diff_since = array(
						'time'      => $since_col ? max( $since_col ) : 0,
						'most'      => $most_col ? max( $most_col ) : 0,
						'most_date' => '0000-00-00',
						'start'     => '',
						'end'       => '',
					);
					for ( $i = 0; $i < $key_count; $i++ ) {
						if ( $diff_since['time'] === $output_array[ $keys[ $i ] ]['since'] ) {
							$diff_since['end']   = $keys[ $i ];
							$diff_since['start'] = $keys[ $i + 1 ];
						}

						if ( $diff_since['most'] === $output_array[ $keys[ $i ] ]['most'] ) {
							if ( $diff_since['most_date'] < $output_array[ $keys[ $i ] ]['date'] ) {
								$diff_since['most_date'] = $output_array[ $keys[ $i ] ]['date'];
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
				'all' => $output_array,
			);
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error outputting dead list: ' . $e->getMessage() );
			return array();
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

		// find the entry with the highest 'since' value (guard max() against an
		// empty column, e.g. when generate_list() returned the 'time' summary shape).
		$since_col     = array_column( $list_of_dead, 'since' );
		$highest_since = $since_col ? max( $since_col ) : 0;

		// find the entry with the highest 'most' value
		$most_col       = array_column( $list_of_dead, 'most' );
		$most_dead      = $most_col ? max( $most_col ) : 0;
		$most_dead_date = $most_col ? array_search( $most_dead, $most_col, true ) : false;

		$return = array(
			'highest_since'  => $highest_since,
			'most_dead'      => $most_dead,
			'most_dead_date' => $most_dead_date,
		);

		return array(
			'stats' => $return,
		);
	}

	/**
	 * Generate taxonomy data
	 *
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 * @param string $taxonomy Taxonomy to generate data for
	 *
	 * @return array Taxonomy data
	 */
	public function generate_characters_taxonomy( $format, $taxonomy ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'dead_characters_taxonomy_' . $taxonomy . '_' . $format;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Single optimized query to get all dead characters and their taxonomy values
			$query = $wpdb->prepare(
				"SELECT
					t.slug as term_slug,
					t.name as term_name,
					COUNT(DISTINCT dead_chars.ID) as count
				FROM {$wpdb->posts} dead_chars
				INNER JOIN {$wpdb->term_relationships} dead_tr ON dead_chars.ID = dead_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} dead_tt ON dead_tr.term_taxonomy_id = dead_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} dead_t ON dead_tt.term_id = dead_t.term_id
				LEFT JOIN {$wpdb->term_relationships} tax_tr ON dead_chars.ID = tax_tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tax_tt ON tax_tr.term_taxonomy_id = tax_tt.term_taxonomy_id
				LEFT JOIN {$wpdb->terms} t ON tax_tt.term_id = t.term_id
				WHERE dead_chars.post_type = %s
				AND dead_chars.post_status = 'publish'
				AND dead_tt.taxonomy = %s
				AND dead_t.slug = %s
				AND tax_tt.taxonomy = %s
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY count DESC, t.name ASC",
				'post_type_characters',
				'lez_cliches',
				'dead',
				$taxonomy
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Format the results based on the requested format
			$formatted_results = $this->format_taxonomy_results( $results, $format );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting dead characters taxonomy data for ' . $taxonomy . ': ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format taxonomy results based on requested format
	 *
	 * @param array $results Raw query results
	 * @param string $format Format type
	 * @return array Formatted results
	 */
	private function format_taxonomy_results( $results, $format ) {
		if ( empty( $results ) ) {
			return array();
		}

		$total_count = array_sum( array_column( $results, 'count' ) );

		switch ( $format ) {
			case 'count':
				// Return just the counts
				$formatted = array();
				foreach ( $results as $result ) {
					$formatted[ $result['term_slug'] ] = (int) $result['count'];
				}
				return $formatted;

			case 'percentage':
				// Return percentages
				$formatted = array();
				foreach ( $results as $result ) {
					$percentage                        = $total_count > 0 ? round( ( $result['count'] / $total_count ) * 100, 2 ) : 0;
					$formatted[ $result['term_slug'] ] = array(
						'count'      => (int) $result['count'],
						'percentage' => $percentage,
						'name'       => $result['term_name'],
					);
				}
				return array( 'death' => $formatted );

			case 'piechart':
				// Return data formatted for pie charts
				$formatted = array();
				foreach ( $results as $result ) {
					$percentage  = $total_count > 0 ? round( ( $result['count'] / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'name'       => $result['term_name'],
						'count'      => (int) $result['count'],
						'percentage' => $percentage,
					);
				}
				return array( 'death' => $formatted );

			case 'barchart':
				// Return data formatted for bar charts
				$formatted = array();
				foreach ( $results as $result ) {
					$formatted[] = array(
						'name'  => $result['term_name'],
						'count' => (int) $result['count'],
					);
				}
				return $formatted;

			case 'list':
				// Return detailed list format
				$formatted = array();
				foreach ( $results as $result ) {
					$percentage  = $total_count > 0 ? round( ( $result['count'] / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'slug'       => $result['term_slug'],
						'name'       => $result['term_name'],
						'count'      => (int) $result['count'],
						'percentage' => $percentage,
					);
				}
				return $formatted;

			case 'array':
			default:
				// Return raw array format
				$formatted = array();
				foreach ( $results as $result ) {
					$formatted[ $result['term_slug'] ] = array(
						'name'  => $result['term_name'],
						'count' => (int) $result['count'],
					);
				}
				return $formatted;
		}
	}

	/**
	 * Generate characters by roles
	 *
	 * @param string $format Format type
	 * @return array Characters by roles
	 */
	public function generate_characters_by_roles( $format ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'dead_characters_by_roles_' . $format;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// ACF repeater fields store sub-fields as separate meta keys (lezchars_show_group_N_type),
			// not as a serialized value under lezchars_show_group. Query sub-field keys directly.
			$query = "SELECT pm_type.meta_value as role_type
				FROM {$wpdb->postmeta} pm_type
				INNER JOIN {$wpdb->posts} p ON p.ID = pm_type.post_id
					AND p.post_type = 'post_type_characters'
					AND p.post_status = 'publish'
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = pm_type.post_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					AND tt.taxonomy = 'lez_cliches'
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					AND t.slug = 'dead'
				WHERE pm_type.meta_key REGEXP 'lezchars_show_group_[0-9]+_type'";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Initialize role counters
			$role_counts = array(
				'regular'   => 0,
				'recurring' => 0,
				'guest'     => 0,
			);

			foreach ( $results as $row ) {
				$role_type = $row['role_type'];
				if ( isset( $role_counts[ $role_type ] ) ) {
					++$role_counts[ $role_type ];
				}
			}

			// Format results based on requested format
			$formatted_results = $this->format_role_results( $role_counts, $format );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting dead characters by roles: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format role results based on requested format
	 *
	 * @param array $role_counts Role counts array
	 * @param string $format Format type
	 * @return array Formatted results
	 */
	private function format_role_results( $role_counts, $format ) {
		$total_count = array_sum( $role_counts );

		if ( 0 === $total_count ) {
			return array();
		}

		switch ( $format ) {
			case 'count':
				// Return just the counts
				return $role_counts;
			case 'percentage':
				// Return percentages
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$percentage         = $total_count > 0 ? round( ( $count / $total_count ) * 100, 2 ) : 0;
					$formatted[ $role ] = array(
						'count'      => $count,
						'percentage' => $percentage,
						'name'       => ucfirst( $role ),
					);
				}
				return array( 'death' => $formatted );
			case 'piechart':
				// Return data formatted for pie charts
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$percentage  = $total_count > 0 ? round( ( $count / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'name'       => ucfirst( $role ),
						'count'      => $count,
						'percentage' => $percentage,
					);
				}
				return array( 'death' => $formatted );
			case 'barchart':
				// Return data formatted for bar charts
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$formatted[] = array(
						'name'  => ucfirst( $role ),
						'count' => $count,
					);
				}
				return $formatted;
			case 'list':
				// Return detailed list format
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$percentage  = $total_count > 0 ? round( ( $count / $total_count ) * 100, 2 ) : 0;
					$formatted[] = array(
						'slug'       => $role,
						'name'       => ucfirst( $role ),
						'count'      => $count,
						'percentage' => $percentage,
					);
				}
				return $formatted;
			case 'array':
			default:
				// Return raw array format
				$formatted = array();
				foreach ( $role_counts as $role => $count ) {
					$formatted[ $role ] = array(
						'name'  => ucfirst( $role ),
						'count' => $count,
					);
				}
				return $formatted;
		}
	}

	/**
	 * Generate shows by characters
	 *
	 * @param string $format Format type
	 * @return array Shows by characters
	 */
	private function generate_shows_by_characters( $format ) {
		lwtv_plugin()->debug_log( 'death', 'Generating shows by characters statistics for format: ' . $format );

		global $wpdb;

		// Create cache key
		$cache_key   = 'dead_shows_by_characters_' . $format;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Get total shows count using the standard method
			$total_shows = lwtv_plugin()->generate_total_counts( 'shows' );

			// Get shows with 'dead-queers' term and their character counts
			$query = $wpdb->prepare(
				"SELECT
					p.ID,
					p.post_title,
					p.post_name,
					COALESCE(char_count.meta_value, 0) as char_count,
					COALESCE(dead_count.meta_value, 0) as dead_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
				LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
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
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Initialize counters
			$all_dead_count  = 0;
			$some_dead_count = 0;

			// Process each show with 'dead-queers' term
			foreach ( $results as $show ) {
				$char_count = (int) $show['char_count'];
				$dead_count = (int) $show['dead_count'];

				if ( 0 === $char_count ) {
					// Skip shows with no characters
					continue;
				}

				if ( $dead_count === $char_count ) {
					// All characters are dead
					++$all_dead_count;
				} else {
					// Some characters are dead (but not all)
					++$some_dead_count;
				}
			}

			// Calculate shows with no death: total shows minus shows with any death
			$shows_with_death = $all_dead_count + $some_dead_count;
			$no_dead_count    = $total_shows - $shows_with_death;

			// Format results based on requested format
			$formatted_results = $this->format_shows_by_characters_results(
				$all_dead_count,
				$some_dead_count,
				$no_dead_count,
				$total_shows,
				$format
			);

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting shows by characters data: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format shows by characters results based on requested format
	 *
	 * @param int $all_dead_count All dead count
	 * @param int $some_dead_count Some dead count
	 * @param int $no_dead_count No dead count
	 * @param int $total_shows Total shows
	 * @param string $format Format type
	 * @return array Formatted results
	 */
	private function format_shows_by_characters_results( $all_dead_count, $some_dead_count, $no_dead_count, $total_shows, $format ) {
		if ( 0 === $total_shows ) {
			return array();
		}

		switch ( $format ) {
			case 'count':
				// Return just the counts
				return array(
					'all_dead'  => $all_dead_count,
					'some_dead' => $some_dead_count,
					'no_dead'   => $no_dead_count,
				);

			case 'percentage':
				// Return percentages
				$all_percentage  = $total_shows > 0 ? round( ( $all_dead_count / $total_shows ) * 100, 1 ) : 0;
				$some_percentage = $total_shows > 0 ? round( ( $some_dead_count / $total_shows ) * 100, 1 ) : 0;
				$no_percentage   = $total_shows > 0 ? round( ( $no_dead_count / $total_shows ) * 100, 1 ) : 0;

				return array(
					'death' => array(
						'all_dead'  => array(
							'count'      => $all_dead_count,
							'percentage' => $all_percentage,
							'name'       => 'All characters are dead',
						),
						'some_dead' => array(
							'count'      => $some_dead_count,
							'percentage' => $some_percentage,
							'name'       => 'Some characters are dead',
						),
						'no_dead'   => array(
							'count'      => $no_dead_count,
							'percentage' => $no_percentage,
							'name'       => 'No characters are dead',
						),
					),
				);

			case 'piechart':
				// Return data formatted for pie charts
				$all_percentage  = $total_shows > 0 ? round( ( $all_dead_count / $total_shows ) * 100, 1 ) : 0;
				$some_percentage = $total_shows > 0 ? round( ( $some_dead_count / $total_shows ) * 100, 1 ) : 0;
				$no_percentage   = $total_shows > 0 ? round( ( $no_dead_count / $total_shows ) * 100, 1 ) : 0;

				return array(
					'death' => array(
						array(
							'name'       => 'All characters are dead',
							'count'      => $all_dead_count,
							'percentage' => $all_percentage,
						),
						array(
							'name'       => 'Some characters are dead',
							'count'      => $some_dead_count,
							'percentage' => $some_percentage,
						),
						array(
							'name'       => 'No characters are dead',
							'count'      => $no_dead_count,
							'percentage' => $no_percentage,
						),
					),
				);

			case 'barchart':
				// Return data formatted for bar charts
				return array(
					array(
						'name'  => 'All characters are dead',
						'count' => $all_dead_count,
					),
					array(
						'name'  => 'Some characters are dead',
						'count' => $some_dead_count,
					),
					array(
						'name'  => 'No characters are dead',
						'count' => $no_dead_count,
					),
				);

			case 'list':
				// Return detailed list format
				$all_percentage  = $total_shows > 0 ? round( ( $all_dead_count / $total_shows ) * 100, 1 ) : 0;
				$some_percentage = $total_shows > 0 ? round( ( $some_dead_count / $total_shows ) * 100, 1 ) : 0;
				$no_percentage   = $total_shows > 0 ? round( ( $no_dead_count / $total_shows ) * 100, 1 ) : 0;

				return array(
					array(
						'slug'       => 'all_dead',
						'name'       => 'All characters are dead',
						'count'      => $all_dead_count,
						'percentage' => $all_percentage,
					),
					array(
						'slug'       => 'some_dead',
						'name'       => 'Some characters are dead',
						'count'      => $some_dead_count,
						'percentage' => $some_percentage,
					),
					array(
						'slug'       => 'no_dead',
						'name'       => 'No characters are dead',
						'count'      => $no_dead_count,
						'percentage' => $no_percentage,
					),
				);

			case 'array':
			default:
				// Return raw array format
				return array(
					'all_dead'  => array(
						'name'  => 'All characters are dead',
						'count' => $all_dead_count,
					),
					'some_dead' => array(
						'name'  => 'Some characters are dead',
						'count' => $some_dead_count,
					),
					'no_dead'   => array(
						'name'  => 'No characters are dead',
						'count' => $no_dead_count,
					),
				);
		}
	}

	/**
	 * Generate shows by taxonomy
	 *
	 * @param string $format Format type
	 * @param string $taxonomy Taxonomy
	 * @return array Shows by taxonomy
	 */
	private function generate_shows_by_taxonomy( $format, $taxonomy ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'dead_shows_by_taxonomy_' . $taxonomy . '_' . $format;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'death', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		if ( 'lez_nations' === $taxonomy ) {
			$taxonomy = 'lez_country';
		}

		try {
			// Single optimized query to get all shows with 'dead-queers' term and their taxonomy values
			$query = $wpdb->prepare(
				"SELECT
					t.slug as term_slug,
					t.name as term_name,
					COUNT(DISTINCT dead_shows.ID) as count
				FROM {$wpdb->posts} dead_shows
				INNER JOIN {$wpdb->term_relationships} dead_tr ON dead_shows.ID = dead_tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} dead_tt ON dead_tr.term_taxonomy_id = dead_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} dead_t ON dead_tt.term_id = dead_t.term_id
				LEFT JOIN {$wpdb->term_relationships} tax_tr ON dead_shows.ID = tax_tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tax_tt ON tax_tr.term_taxonomy_id = tax_tt.term_taxonomy_id
				LEFT JOIN {$wpdb->terms} t ON tax_tt.term_id = t.term_id
				WHERE dead_shows.post_type = %s
				AND dead_shows.post_status = 'publish'
				AND dead_tt.taxonomy = %s
				AND dead_t.slug = %s
				AND tax_tt.taxonomy = %s
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY count DESC, t.name ASC",
				'post_type_shows',
				'lez_tropes',
				'dead-queers',
				$taxonomy
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Format the results for barchart
			$formatted_results = $this->format_shows_by_taxonomy_results( $results, $format );

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $formatted_results, DAY_IN_SECONDS );

			return $formatted_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'death', 'Error getting dead shows taxonomy data for ' . $taxonomy . ': ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format shows by taxonomy results
	 *
	 * @param array $results Raw query results
	 * @param string $format Format type
	 * @return array Formatted results
	 */
	private function format_shows_by_taxonomy_results( $results, $format ) {
		lwtv_plugin()->debug_log( 'death', 'Formatting shows by taxonomy results for format: ' . $format );
		$formatted_results      = array();
		$total_shows_with_death = (int) $this->total_dead_shows();
		switch ( $format ) {
			case 'count':
				$formatted_results = count( $results );
				break;
			case 'barchart':
				foreach ( $results as $result ) {
					$formatted_results[] = array(
						'name'  => $result['term_name'],
						'count' => (int) $result['count'],
					);
				}
				break;
			case 'percentage':
				$new_results = array();
				foreach ( $results as $result ) {
					$new_results[] = array(
						'slug'       => $result['term_slug'],
						'name'       => $result['term_name'],
						'count'      => (int) $result['count'],
						'percentage' => $total_shows_with_death > 0 ? round( ( $result['count'] / $total_shows_with_death ) * 100, 1 ) : 0,
					);
				}
				$formatted_results = array( 'death' => $new_results );
				break;
			default:
				$formatted_results = $results;
				break;
		}

		return $formatted_results;
	}

	public function get_data_version_hash() {
		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'post_type_characters'
			)
		);

		$hash = md5( $last_modified );

		return $hash;
	}
}
