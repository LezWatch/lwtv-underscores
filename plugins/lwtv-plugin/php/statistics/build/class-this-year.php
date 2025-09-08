<?php

namespace LWTV\Statistics\Build;

class This_Year {

	/**
	 * Stats for This Year - Optimized with batch queries
	 *
	 * @param string $data
	 * @param array  $year_array
	 *
	 * @return array
	 */
	public function make( $data, $year_array = array() ) {

		// loop through array and rebuild into format for charts.
		$transient = 'this_year_' . $data;
		$array     = lwtv_plugin()->get_transient( $transient );
		$taxonomy  = substr( $data, 0, -10 );      // Remove _year_XXXX from the end.

		// If the array is empty, we want to rebuild it.
		if ( false === $array || empty( $array ) ) {
			$array = $this->build_this_year_optimized( $taxonomy, $year_array );

			// save array as transient
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
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

		$results_array = array();

		try {
			// Get all terms for the taxonomy
			$taxonomies = get_terms( 'lez_' . $taxonomy );

			if ( is_wp_error( $taxonomies ) || empty( $taxonomies ) ) {
				lwtv_plugin()->error_log( 'this-year-taxonomy', "Failed to get terms for taxonomy: lez_{$taxonomy}" );
				return array();
			}

			// Build base array with all terms
			foreach ( $taxonomies as $term ) {
				$results_array[ $term->slug ] = array(
					'name'  => $term->name,
					'url'   => $term->link,
					'count' => 0,
				);
			}

			// If no character data provided, return empty counts
			if ( empty( $year_array ) ) {
				return $results_array;
			}

			// Extract character IDs
			$character_ids = array_column( $year_array, 'id' );
			$character_ids = array_map( 'intval', $character_ids );
			$character_ids = array_filter( $character_ids ); // Remove invalid IDs

			if ( empty( $character_ids ) ) {
				return $results_array;
			}

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
				 GROUP BY t.slug",
				array_merge( array( 'lez_' . $taxonomy ), $character_ids )
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Update counts with actual data
			foreach ( $results as $row ) {
				if ( isset( $results_array[ $row['slug'] ] ) ) {
					$results_array[ $row['slug'] ]['count'] = (int) $row['term_count'];
				}
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'this-year-error', 'Error building this year statistics: ' . $e->getMessage() );
			return array();
		}

		return $results_array;
	}
}
