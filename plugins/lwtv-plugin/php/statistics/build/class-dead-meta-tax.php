<?php

namespace LWTV\Statistics\Build;

class Dead_Meta_Tax {

	/**
	 * Dead Statistics Meta and Taxonomy Array - Optimized with single query
	 *
	 * Generate array to parse taxonomy content as it relates to post metas
	 * using optimized single query instead of N+1 pattern
	 *
	 * @param string $post_type Post Type to be search
	 * @param array $meta_array Meta terms to loop through
	 * @param string $key Post Meta Key name (i.e. lezchars_gender)
	 * @param string $taxonomy Taxonomy to restrict to (default lez_cliches)
	 * @param string $field Taxonomy to restrict to (default dead)
	 *
	 * @return array
	 */
	public function make( $post_type, $meta_array, $key, $taxonomy = 'lez_cliches', $field = 'dead' ) {
		try {
			$transient = 'dead_meta_tax_' . $post_type . '_' . $taxonomy . '_' . $field;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_meta_tax_optimized( $post_type, $meta_array, $key, $taxonomy, $field );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-meta-tax-error', 'Error building dead meta taxonomy: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build dead meta taxonomy statistics using optimized single query
	 *
	 * @param string $post_type Post type to query
	 * @param array  $meta_array Meta values to count
	 * @param string $key Meta key to search
	 * @param string $taxonomy Taxonomy to restrict to
	 * @param string $field Taxonomy field to restrict to
	 * @return array
	 */
	private function build_dead_meta_tax_optimized( $post_type, $meta_array, $key, $taxonomy, $field ) {
		global $wpdb;

		try {
			$results_array = array();

			// Sanitize meta values
			$sanitized_values = array_map( 'sanitize_text_field', $meta_array );
			$placeholders     = implode( ',', array_fill( 0, count( $sanitized_values ), '%s' ) );

			// Single optimized query to get counts for all meta values with taxonomy restriction
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT pm.meta_value, COUNT(DISTINCT p.ID) as post_count
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				 WHERE p.post_type = %s
				 AND p.post_status = 'publish'
				 AND pm.meta_key = %s
				 AND pm.meta_value IN ({$placeholders})
				 AND tt.taxonomy = %s
				 AND t.slug = %s
				 GROUP BY pm.meta_value",
				array_merge( array( $post_type, $key ), $sanitized_values, array( $taxonomy, $field ) )
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Format results
			$counts = array();
			foreach ( $results as $row ) {
				$counts[ $row['meta_value'] ] = (int) $row['post_count'];
			}

			// Build final array with all requested values
			foreach ( $meta_array as $value ) {
				$results_array[ $value ] = array(
					'count' => $counts[ $value ] ?? 0,
					'name'  => ucfirst( $value ),
					'url'   => home_url( '/cliche/' . $value ),
				);
			}

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-meta-tax-error', 'Error building dead meta taxonomy statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
