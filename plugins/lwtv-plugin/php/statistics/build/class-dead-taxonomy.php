<?php

namespace LWTV\Statistics\Build;

class Dead_Taxonomy {

	/**
	 * Statistics Taxonomy Array for DEAD - Optimized with single query
	 *
	 * Generate array to parse taxonomy content for death
	 * using optimized single query instead of N+1 pattern
	 *
	 * @param string $post_type Post Type to be searched
	 * @param string $taxonomy Taxonomy to be searched
	 *
	 * @return array
	 */
	public function make( $post_type, $taxonomy ) {
		try {
			$transient = 'dead_taxonomy_' . $post_type . '_' . $taxonomy;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_taxonomy_optimized( $post_type, $taxonomy );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-taxonomy-error', 'Error building dead taxonomy: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build dead taxonomy statistics using optimized single query
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @return array
	 */
	private function build_dead_taxonomy_optimized( $post_type, $taxonomy ) {
		global $wpdb;

		try {
			// Get all terms for the taxonomy
			$taxonomies = get_terms( $taxonomy );

			if ( is_wp_error( $taxonomies ) || empty( $taxonomies ) ) {
				lwtv_plugin()->error_log( 'dead-taxonomy-error', "Failed to get terms for taxonomy: {$taxonomy}" );
				return array();
			}

			$results_array = array();

			// Build base array with all terms
			foreach ( $taxonomies as $term ) {
				$results_array[ $term->slug ] = array(
					'name'  => $term->name,
					'url'   => get_term_link( $term ),
					'count' => 0,
				);
			}

			// Single optimized query to get all term counts with death restriction
			$term_slugs   = array_column( $taxonomies, 'slug' );
			$placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT t.slug, COUNT(DISTINCT p.ID) as term_count
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				 INNER JOIN {$wpdb->term_relationships} dead_rel ON p.ID = dead_rel.object_id
				 INNER JOIN {$wpdb->term_taxonomy} dead_tax ON dead_rel.term_taxonomy_id = dead_tax.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id
				 WHERE tt.taxonomy = %s
				 AND p.post_type = %s
				 AND p.post_status = 'publish'
				 AND t.slug IN ({$placeholders})
				 AND dead_tax.taxonomy = 'lez_cliches'
				 AND dead_term.slug = 'dead'
				 GROUP BY t.slug",
				array_merge( array( $taxonomy, $post_type ), $term_slugs )
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

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-taxonomy-error', 'Error building dead taxonomy statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
