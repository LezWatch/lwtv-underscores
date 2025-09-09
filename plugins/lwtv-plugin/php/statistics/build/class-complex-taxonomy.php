<?php

namespace LWTV\Statistics\Build;

class Complex_Taxonomy {

	/**
	 * Complex Taxonomy Breakdown - Optimized with single queries
	 *
	 * @param  boolean $count Total count for calculations
	 * @param  string  $data  Taxonomy data type
	 * @param  string  $type  Post type (characters, actors, etc.)
	 * @return array          Taxonomy breakdown with counts
	 */
	public function make( $count, $data, $type ) {

		$post_type = 'post_type_' . $type;
		$do_count  = ( isset( $count ) && 0 !== $count ) ? 'yes' : 'no';
		$transient = 'complex_taxonomy_' . $data . '_' . $type . '_' . $do_count;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_complex_taxonomy_optimized( $count, $data, $type, $post_type, $do_count );

			// save array as transient
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build complex taxonomy statistics using optimized single queries
	 *
	 * @param int    $count     Total count for calculations
	 * @param string $data      Taxonomy data type
	 * @param string $type      Post type
	 * @param string $post_type Full post type name
	 * @param string $do_count  Whether to include 'none' count
	 * @return array
	 */
	private function build_complex_taxonomy_optimized( $count, $data, $type, $post_type, $do_count ) {
		global $wpdb;

		try {
			// Handle special case for queer-irl
			if ( 'queer-irl' === $data ) {
				return $this->build_queer_irl_optimized( $count, $type );
			}

			// Get all terms for the taxonomy
			$taxonomies = get_terms( 'lez_' . $data );

			if ( is_wp_error( $taxonomies ) || empty( $taxonomies ) ) {
				lwtv_plugin()->error_log( 'complex-taxonomy-error', "Failed to get terms for taxonomy: lez_{$data}" );
				return array();
			}

			// Single optimized query to get all term counts
			$term_slugs   = array_column( $taxonomies, 'slug' );
			$placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT t.slug, COUNT(DISTINCT tr.object_id) as term_count
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				 WHERE tt.taxonomy = %s
				 AND p.post_type = %s
				 AND p.post_status = 'publish'
				 AND t.slug IN ({$placeholders})
				 GROUP BY t.slug",
				array_merge( array( 'lez_' . $data, $post_type ), $term_slugs )
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Build results array
			$results_array = array();
			$total_counted = 0;

			foreach ( $taxonomies as $term ) {
				$term_slug  = $term->slug;
				$term_count = 0;

				// Find count for this term
				foreach ( $results as $row ) {
					if ( $row['slug'] === $term_slug ) {
						$term_count = (int) $row['term_count'];
						break;
					}
				}

				$results_array[ $term_slug ] = array(
					'count' => $term_count,
					'name'  => $term->name,
					'url'   => get_term_link( $term ),
				);

				$total_counted += $term_count;
			}

			// Add 'none' count if requested
			if ( 'yes' === $do_count ) {
				$none_count            = max( 0, $count - $total_counted );
				$results_array['none'] = array(
					'count' => $none_count,
					'name'  => 'None',
					'url'   => '',
				);
			}

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'complex-taxonomy-error', 'Error building complex taxonomy: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build queer-irl statistics using optimized approach
	 *
	 * @param int    $count Total count
	 * @param string $type  Post type
	 * @return array
	 */
	private function build_queer_irl_optimized( $count, $type ) {
		global $wpdb;

		try {
			$results_array = array(
				'queer'     => array(
					'name'  => 'Queer',
					'count' => 0,
					'url'   => home_url(),
				),
				'not_queer' => array(
					'name'  => 'Not Queer',
					'count' => 0,
					'url'   => home_url(),
				),
			);

			if ( 'characters' === $type ) {
				// Single query to get queer-irl character count
				$queery = "SELECT COUNT(DISTINCT tr.object_id) as queer_count
					 FROM {$wpdb->terms} t
					 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
					 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
					 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					 WHERE tt.taxonomy = 'lez_cliches'
					 AND t.slug = 'queer-irl'
					 AND p.post_type = 'post_type_characters'
					 AND p.post_status = 'publish'";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
				$queer_count = (int) $wpdb->get_var( $queery );

				$results_array['queer']['count']     = $queer_count;
				$results_array['queer']['url']       = home_url( '/cliche/queer-irl/' );
				$results_array['not_queer']['count'] = max( 0, $count - $queer_count );

			} elseif ( 'actors' === $type ) {
				// Single query to get queer actor count using meta data
				$queery = "SELECT COUNT(DISTINCT p.ID) as queer_count
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					 WHERE p.post_type = 'post_type_actors'
					 AND p.post_status = 'publish'
					 AND pm.meta_key = 'lezactors_queer'
					 AND pm.meta_value = 'yes'";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
				$queer_count = (int) $wpdb->get_var( $queery );

				$results_array['queer']['count']     = $queer_count;
				$results_array['not_queer']['count'] = max( 0, $count - $queer_count );
			}

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'queer-irl-error', 'Error building queer-irl statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
