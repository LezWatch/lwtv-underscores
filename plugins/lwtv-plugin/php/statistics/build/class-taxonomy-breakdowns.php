<?php

namespace LWTV\Statistics\Build;

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;


/**
 * Taxonomy Breakdowns
 *
 * Used by Nations and Stations and Formats.
 *
 * Pages to test:
 * /statistics/stations/?station=nbc&view=tropes
 * /statistics/nations/?country=usa&view=gender
 * /statistics/formats/?showform=movie&view=tropes
 */
class Taxonomy_Breakdowns {

	/**
	 * Calculate statistics for complicated taxonomies - Optimized with single query
	 *
	 * @param mixed $count   - integer; number of posts.
	 * @param mixed $format  - string; format of stats (i.e. lists, piecharts, etc).
	 * @param mixed $data    - string; [main taxonomy]_[term of main]_[metadata to parse].
	 * @param mixed $subject - string; post type (shows, characters).
	 * @return array|int
	 */
	public function make( $count, $format, $data, $subject ) {
		try {
			// Validate input parameters
			if ( empty( $data ) || empty( $format ) ) {
				lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Invalid parameters provided' );
				return 'count' === $format ? 0 : array();
			}

			$transient = 'taxonomy_breakdowns_' . md5( $data . '_' . $format . '_' . $subject );
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_taxonomy_breakdowns_optimized( $count, $format, $data, $subject );

				// save array as transient.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error building taxonomy breakdowns: ' . $e->getMessage() );
			return 'count' === $format ? 0 : array();
		}
	}

	/**
	 * Build taxonomy breakdowns using optimized single query
	 *
	 * @param mixed $count   - integer; number of posts.
	 * @param mixed $format  - string; format of stats.
	 * @param mixed $data    - string; [main taxonomy]_[term of main]_[metadata to parse].
	 * @param mixed $subject - string; post type (shows, characters).
	 * @return array|int
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	private function build_taxonomy_breakdowns_optimized( $count, $format, $data, $subject ) {
		global $wpdb;

		try {
			// Arrays of the secondary taxonomies we care about.
			$main_subtaxes  = array( 'gender', 'sexuality', 'romantic' );
			$extra_subtaxes = array( 'cliches', 'tropes', 'intersections', 'formats', 'stations', 'country' );
			$special_cases  = array( 'on-air' );
			$valid_subtaxes = array_merge( $main_subtaxes, $extra_subtaxes, $special_cases );

			// Parse the data string: [main taxonomy]_[term of main]_[metadata to parse]
			// Examples: stations_cbs_gender, country_usa_sexuality, formats_movie_tropes
			// Also handles: stations_cbs_on-air (special case)
			$data_main = '';
			$data_term = 'all';
			$data_meta = 'all';

			// Handle single underscore patterns (this is the standard format)
			$pieces    = explode( '_', $data );
			$data_main = $pieces[0];
			$data_term = ( isset( $pieces[1] ) ) ? $pieces[1] : 'all';
			$data_meta = ( isset( $pieces[2] ) && in_array( $pieces[2], $valid_subtaxes, true ) ) ? $pieces[2] : 'all';

			// Handle count format early
			if ( 'count' === $format ) {
				return $this->get_count_for_subject( $data_main, $data_term );
			}

			// Handle 'on-air' case - delegate to On_Air class
			if ( 'on-air' === $data_meta ) {
				// This should not happen as on-air is handled by the statistics class
				// But if it does, return empty array
				lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'On-air case reached in Taxonomy_Breakdowns - this should not happen' );
				return array();
			}

			// Handle 'all' taxonomy case - delegate to optimized taxonomy builder
			if ( 'all' === $data_term && 'all' === $data_meta ) {
				$optimized_taxonomy = new Build_Taxonomy_Optimized();
				return $optimized_taxonomy->make( 'post_type_shows', 'lez_' . $data_main );
			}

			// Build comprehensive single query for specific taxonomy breakdowns
			$results = $this->build_comprehensive_query( $data_main, $data_term, $data_meta );

			if ( empty( $results ) ) {
				lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'No results found for data: ' . $data );
				return array();
			}

			// Process results based on format
			$processed_results = $this->process_results_by_format( $results, $format, $data_main, $data_term, $data_meta );

			if ( empty( $processed_results ) ) {
				lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'No processed results found for data: ' . $data );
				return array();
			}

			return $processed_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error in build_taxonomy_breakdowns_optimized: ' . $e->getMessage() );
			return 'count' === $format ? 0 : array();
		}
	}

	/**
	 * Get count for specific subject
	 *
	 * @param string $data_main Main taxonomy
	 * @param string $data_term Specific term or 'all'
	 * @return int
	 */
	private function get_count_for_subject( $data_main, $data_term ) {
		global $wpdb;

		try {
			if ( 'all' === $data_term ) {
				// Count all terms in taxonomy
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
						'lez_' . $data_main
					)
				);
				return (int) $count;
			} else {
				// Count shows for specific term
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE p.post_type = 'post_type_shows'
						AND p.post_status = 'publish'
						AND tt.taxonomy = %s
						AND t.slug = %s",
						'lez_' . $data_main,
						$data_term
					)
				);
				return (int) $count;
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error getting count: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Build comprehensive single query for taxonomy breakdowns
	 *
	 * @param string $data_main Main taxonomy
	 * @param string $data_term Specific term or 'all'
	 * @param string $data_meta Meta data type
	 * @return array
	 */
	private function build_comprehensive_query( $data_main, $data_term, $data_meta ) {
		global $wpdb;

		try {
			// Arrays of the secondary taxonomies we care about.
			$main_subtaxes  = array( 'gender', 'sexuality', 'romantic' );
			$extra_subtaxes = array( 'cliches', 'tropes', 'intersections', 'formats', 'stations', 'country' );

			// Debug which query path we're taking
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'Query path - data_meta: ' . $data_meta . ', in extra_subtaxes: ' . ( in_array( $data_meta, $extra_subtaxes, true ) ? 'yes' : 'no' ) . ', in main_subtaxes: ' . ( in_array( $data_meta, $main_subtaxes, true ) ? 'yes' : 'no' ) );

			// Handle specific meta data breakdowns
			if ( 'all' !== $data_meta && in_array( $data_meta, $extra_subtaxes, true ) ) {
				return $this->build_extra_taxonomy_breakdown( $data_main, $data_term, $data_meta );
			} elseif ( 'all' !== $data_meta && in_array( $data_meta, $main_subtaxes, true ) ) {
				return $this->build_main_taxonomy_breakdown( $data_main, $data_term, $data_meta );
			}

			// Single optimized query to get basic data
			// phpcs:disable
			if ( 'all' !== $data_term ) {
				$queery = $wpdb->prepare(
					"SELECT
						main_term.slug as main_slug,
						main_term.name as main_name,
						COUNT(DISTINCT p.ID) as show_count,
						SUM(DISTINCT COALESCE(char_count.meta_value, 0)) as character_count,
						SUM(DISTINCT COALESCE(dead_count.meta_value, 0)) as dead_count,
						COUNT(DISTINCT CASE WHEN dead_trope.term_taxonomy_id IS NOT NULL THEN p.ID END) as dead_show_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} main_rel ON p.ID = main_rel.object_id
					INNER JOIN {$wpdb->term_taxonomy} main_tax ON main_rel.term_taxonomy_id = main_tax.term_taxonomy_id
					INNER JOIN {$wpdb->terms} main_term ON main_tax.term_id = main_term.term_id
					LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
					LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
					LEFT JOIN {$wpdb->term_relationships} dead_trope ON p.ID = dead_trope.object_id
					LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_trope.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_tropes'
					LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead-queers'
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'
					AND main_tax.taxonomy = %s
					AND main_term.slug = %s
					GROUP BY main_term.slug, main_term.name
					ORDER BY main_term.name",
					'lez_' . $data_main,
					$data_term
				);
			} else {
				$queery = $wpdb->prepare(
					"SELECT
						main_term.slug as main_slug,
						main_term.name as main_name,
						COUNT(DISTINCT p.ID) as show_count,
						SUM(DISTINCT COALESCE(char_count.meta_value, 0)) as character_count,
						SUM(DISTINCT COALESCE(dead_count.meta_value, 0)) as dead_count,
						COUNT(DISTINCT CASE WHEN dead_trope.term_taxonomy_id IS NOT NULL THEN p.ID END) as dead_show_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} main_rel ON p.ID = main_rel.object_id
					INNER JOIN {$wpdb->term_taxonomy} main_tax ON main_rel.term_taxonomy_id = main_tax.term_taxonomy_id
					INNER JOIN {$wpdb->terms} main_term ON main_tax.term_id = main_term.term_id
					LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
					LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
					LEFT JOIN {$wpdb->term_relationships} dead_trope ON p.ID = dead_trope.object_id
					LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_trope.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_tropes'
					LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead-queers'
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'
					AND main_tax.taxonomy = %s
					GROUP BY main_term.slug, main_term.name
					ORDER BY main_term.name",
					'lez_' . $data_main
				);
			}
			// phpcs:enable

			// Debug the query
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'Comprehensive query: ' . $queery );
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'Query params: ' . wp_json_encode( array( 'lez_' . $data_main, 'all' !== $data_term ? $data_term : null ) ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'Query results: ' . wp_json_encode( $results ) );

			return $results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error building comprehensive query: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build breakdown for extra taxonomies (cliches, tropes, intersections, formats, stations, country)
	 *
	 * @param string $data_main Main taxonomy
	 * @param string $data_term Specific term or 'all'
	 * @param string $data_meta Meta data type
	 * @return array
	 */
	private function build_extra_taxonomy_breakdown( $data_main, $data_term, $data_meta ) {
		global $wpdb;

		try {
			// Single optimized query to get taxonomy breakdowns
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					extra_term.slug as extra_slug,
					extra_term.name as extra_name,
					COUNT(DISTINCT p.ID) as show_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} main_rel ON p.ID = main_rel.object_id
				INNER JOIN {$wpdb->term_taxonomy} main_tax ON main_rel.term_taxonomy_id = main_tax.term_taxonomy_id
				INNER JOIN {$wpdb->terms} main_term ON main_tax.term_id = main_term.term_id
				INNER JOIN {$wpdb->term_relationships} extra_rel ON p.ID = extra_rel.object_id
				INNER JOIN {$wpdb->term_taxonomy} extra_tax ON extra_rel.term_taxonomy_id = extra_tax.term_taxonomy_id
				INNER JOIN {$wpdb->terms} extra_term ON extra_tax.term_id = extra_term.term_id
				WHERE p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				AND main_tax.taxonomy = %s
				AND extra_tax.taxonomy = %s" .
				( 'all' !== $data_term ? " AND main_term.slug = %s" : '' ) . "
				GROUP BY extra_term.slug, extra_term.name
				ORDER BY extra_term.name",
				'lez_' . $data_main,
				'lez_' . $data_meta,
				'all' !== $data_term ? $data_term : null
			);
			// phpcs:enable

			// Debug the query
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-debug', 'Extra taxonomy query: ' . $queery );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			return $wpdb->get_results( $queery, ARRAY_A );

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error building extra taxonomy breakdown: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build breakdown for main taxonomies (gender, sexuality, romantic) using serialized meta data
	 *
	 * @param string $data_main Main taxonomy
	 * @param string $data_term Specific term or 'all'
	 * @param string $data_meta Meta data type
	 * @return array
	 */
	private function build_main_taxonomy_breakdown( $data_main, $data_term, $data_meta ) {
		global $wpdb;

		try {
			// Single optimized query to get serialized meta data
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					meta.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} main_rel ON p.ID = main_rel.object_id
				INNER JOIN {$wpdb->term_taxonomy} main_tax ON main_rel.term_taxonomy_id = main_tax.term_taxonomy_id
				INNER JOIN {$wpdb->terms} main_term ON main_tax.term_id = main_term.term_id
				INNER JOIN {$wpdb->postmeta} meta ON p.ID = meta.post_id
				WHERE p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				AND main_tax.taxonomy = %s
				AND meta.meta_key = %s
				AND meta.meta_value IS NOT NULL
				AND meta.meta_value != ''" .
				( 'all' !== $data_term ? " AND main_term.slug = %s" : '' ),
				'lez_' . $data_main,
				'lezshows_char_' . $data_meta,
				'all' !== $data_term ? $data_term : null
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Process serialized data
			$aggregated_counts = array();
			foreach ( $results as $row ) {
				$meta_data = maybe_unserialize( $row['meta_value'] );
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $key => $count ) {
						if ( ! isset( $aggregated_counts[ $key ] ) ) {
							$aggregated_counts[ $key ] = 0;
						}
						$aggregated_counts[ $key ] += (int) $count;
					}
				}
			}

			// Convert to expected format
			$formatted_results = array();
			foreach ( $aggregated_counts as $key => $count ) {
				if ( $count > 0 ) { // Only include non-zero counts
					$formatted_results[] = array(
						'char_slug'  => $key,
						'char_name'  => ucfirst( str_replace( array( '-', '_' ), ' ', $key ) ),
						'char_count' => $count,
					);
				}
			}

			// Sort by count descending
			usort(
				$formatted_results,
				function ( $a, $b ) {
					return $b['char_count'] - $a['char_count'];
				}
			);

			return $formatted_results;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error building main taxonomy breakdown: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Process results based on format requirements
	 *
	 * @param array  $results Query results
	 * @param string $format Output format
	 * @param string $data_main Main taxonomy
	 * @param string $data_term Specific term or 'all'
	 * @param string $data_meta Meta data type
	 * @return array
	 */
	private function process_results_by_format( $results, $format, $data_main, $data_term, $data_meta ) {
		$array = array();

		try {
			// Arrays of the secondary taxonomies we care about.
			$main_subtaxes  = array( 'gender', 'sexuality', 'romantic' );
			$extra_subtaxes = array( 'cliches', 'tropes', 'intersections', 'formats', 'stations', 'country' );

			switch ( $format ) {
				case 'barchart':
					if ( 'all' !== $data_term && 'all' === $data_meta ) {
						// Overview for specific term
						foreach ( $results as $row ) {
							$array['shows'] = array(
								'name'  => 'Shows',
								'count' => (int) $row['show_count'],
							);
							$array['chars'] = array(
								'name'  => 'Characters',
								'count' => (int) $row['character_count'],
							);
							$array['death'] = array(
								'name'  => 'Dead Characters',
								'count' => (int) $row['dead_count'],
							);
						}
					} elseif ( 'all' !== $data_meta && in_array( $data_meta, $extra_subtaxes, true ) ) {
						// Extra taxonomy breakdown (tropes, cliches, etc.)
						foreach ( $results as $row ) {
							$array[] = array(
								'name'  => $row['extra_name'],
								'count' => (int) $row['show_count'],
							);
						}
					} elseif ( 'all' !== $data_meta && in_array( $data_meta, $main_subtaxes, true ) ) {
						// Main taxonomy breakdown (gender, sexuality, romantic)
						foreach ( $results as $row ) {
							$array[] = array(
								'name'  => $row['char_name'],
								'count' => (int) $row['char_count'],
							);
						}
					} else {
						// Specific data breakdown
						foreach ( $results as $row ) {
							$array[] = array(
								'name'  => $row['main_name'],
								'count' => (int) $row['show_count'],
							);
						}
					}
					break;

				case 'percentage':
				case 'piechart':
				case 'list':
					if ( 'all' !== $data_term ) {
						if ( 'all' === $data_meta ) {
							// Overview for specific term
							foreach ( $results as $row ) {
								$array['shows'] = array(
									'count' => (int) $row['show_count'],
									'name'  => 'Shows',
									'url'   => '#',
								);
								$array['chars'] = array(
									'count' => (int) $row['character_count'],
									'name'  => 'Characters',
									'url'   => '#',
								);
							}
						} elseif ( in_array( $data_meta, $extra_subtaxes, true ) ) {
							// Extra taxonomy breakdown (tropes, cliches, etc.)
							foreach ( $results as $row ) {
								$array[] = array(
									'name'  => $row['extra_name'],
									'count' => (int) $row['show_count'],
									'url'   => home_url( '/' . rtrim( $data_meta, 's' ) . '/' . $row['extra_slug'] . '/' ),
								);
							}
						} elseif ( in_array( $data_meta, $main_subtaxes, true ) ) {
							// Main taxonomy breakdown (gender, sexuality, romantic)
							foreach ( $results as $row ) {
								$array[] = array(
									'name'  => $row['char_name'],
									'count' => (int) $row['char_count'],
									'url'   => home_url( '/' . rtrim( $data_meta, 's' ) . '/' . $row['char_slug'] . '/' ),
								);
							}
						}
					} else {
						// All terms overview
						foreach ( $results as $row ) {
							$array[] = array(
								'name'  => $row['main_name'],
								'count' => (int) $row['show_count'],
								'url'   => home_url( '/' . rtrim( $data_main, 's' ) . '/' . $row['main_slug'] . '/' ),
							);
						}
					}
					break;

				case 'stackedbar':
					foreach ( $results as $row ) {
						$array[ $row['main_slug'] ] = array(
							'name'       => $row['main_name'],
							'count'      => (int) $row['show_count'],
							'characters' => (int) $row['character_count'],
							'dataset'    => array(), // Would need additional query for detailed breakdown
						);
					}
					break;
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'taxonomy-breakdowns-error', 'Error processing results: ' . $e->getMessage() );
			return array();
		}
	}
}
