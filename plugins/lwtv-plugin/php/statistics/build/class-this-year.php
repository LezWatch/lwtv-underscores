<?php
/**
 * This Year Statistics Build Class - OLD Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class This_Year {

	/**
	 * Stats for This Year - Optimized with batch queries
	 *
	 * @param string $data Data identifier (e.g., 'gender_year_2024')
	 * @param array  $year_array Array of character data for the year
	 *
	 * @return array
	 */
	public function make( $data, $year_array = array() ) {
		try {
			// Validate input parameters
			if ( empty( $data ) || ! is_string( $data ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'Invalid data parameter provided' );
				return array();
			}

			// Extract taxonomy from data string by splitting on the '_year_' marker
			// (e.g. 'gender_year_2024' => 'gender'), instead of assuming a fixed
			// 10-character '_year_XXXX' suffix that only holds for 4-digit years.
			$year_pos = strrpos( $data, '_year_' );
			$taxonomy = ( false !== $year_pos ) ? substr( $data, 0, $year_pos ) : '';
			if ( empty( $taxonomy ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'Invalid data format: ' . $data );
				return array();
			}

			// Debug logging
			lwtv_plugin()->debug_log( 'this-year', 'This_Year make called with data: ' . $data . ', taxonomy: ' . $taxonomy . ', year_array count: ' . count( $year_array ) );

			// Check transient cache
			$transient = 'this_year_' . $data;
			$array     = lwtv_plugin()->get_transient( $transient );

			// If the array is empty, we want to rebuild it
			if ( false === $array || empty( $array ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'Cache miss, rebuilding data for: ' . $transient );
				$array = $this->build_this_year_optimized( $taxonomy, $year_array );

				// Save array as transient if we have data
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
					lwtv_plugin()->debug_log( 'this-year', 'Cached ' . count( $array ) . ' terms for: ' . $transient );
				} else {
					lwtv_plugin()->debug_log( 'this-year', 'No data to cache for: ' . $transient );
				}
			} else {
				lwtv_plugin()->debug_log( 'this-year', 'Using cached data for: ' . $transient );
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'this-year', 'Error in This_Year make: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build this year statistics using optimized batch query
	 *
	 * @param string $taxonomy Taxonomy name (without lez_ prefix)
	 * @param array  $year_array Array of character data
	 * @return array
	 */
	private function build_this_year_optimized( $taxonomy, $year_array ) {
		global $wpdb;

		try {
			$results_array = array();

			// Validate taxonomy parameter
			if ( empty( $taxonomy ) || ! is_string( $taxonomy ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'Invalid taxonomy parameter: ' . $taxonomy );
				return array();
			}

			// Get all terms for the taxonomy with error handling
			$taxonomy_name = 'lez_' . $taxonomy;
			$taxonomies    = get_terms(
				array(
					'taxonomy'   => $taxonomy_name,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $taxonomies ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'Failed to get terms for taxonomy: ' . $taxonomy_name . ' - ' . $taxonomies->get_error_message() );
				return array();
			}

			if ( empty( $taxonomies ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'No terms found for taxonomy: ' . $taxonomy_name );
				return array();
			}

			// Build base array with all terms
			foreach ( $taxonomies as $term ) {
				$results_array[ $term->slug ] = array(
					'name'  => $term->name,
					'url'   => get_term_link( $term ),
					'count' => 0,
				);
			}

			lwtv_plugin()->debug_log( 'this-year', 'Built base array with ' . count( $results_array ) . ' terms for taxonomy: ' . $taxonomy_name );

			// If no character data provided, return empty counts
			if ( empty( $year_array ) || ! is_array( $year_array ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'No character data provided, returning empty counts' );
				return $results_array;
			}

			// Extract and validate character IDs
			$character_ids = array_column( $year_array, 'id' );
			$character_ids = array_map( 'intval', $character_ids );
			$character_ids = array_filter(
				$character_ids,
				function ( $id ) {
					return $id > 0;
				}
			);

			if ( empty( $character_ids ) ) {
				lwtv_plugin()->debug_log( 'this-year', 'No valid character IDs found in year_array' );
				return $results_array;
			}

			lwtv_plugin()->debug_log( 'this-year', 'Processing ' . count( $character_ids ) . ' character IDs for taxonomy: ' . $taxonomy_name );

			// Single optimized query to get all taxonomy counts
			$placeholders = implode( ',', array_fill( 0, count( $character_ids ), '%d' ) );

			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT t.slug, COUNT(DISTINCT tr.object_id) as term_count
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 WHERE tt.taxonomy = %s
				 AND tr.object_id IN ({$placeholders})
				 GROUP BY t.slug
				 ORDER BY term_count DESC",
				array_merge( array( $taxonomy_name ), $character_ids )
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			lwtv_plugin()->debug_log( 'this-year', 'Query returned ' . count( $results ) . ' results for taxonomy: ' . $taxonomy_name );

			// Update counts with actual data
			$total_count = 0;
			foreach ( $results as $row ) {
				if ( isset( $results_array[ $row['slug'] ] ) ) {
					$count = (int) $row['term_count'];

					$results_array[ $row['slug'] ]['count'] = $count;

					$total_count += $count;
				}
			}

			lwtv_plugin()->debug_log( 'this-year', 'Total character assignments: ' . $total_count . ' for taxonomy: ' . $taxonomy_name );

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'this-year', 'Error building this year statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
