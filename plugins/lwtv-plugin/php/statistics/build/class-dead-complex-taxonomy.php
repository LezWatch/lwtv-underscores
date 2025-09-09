<?php

namespace LWTV\Statistics\Build;

class Dead_Complex_Taxonomy {

	/**
	 * Complex death taxonomies for stations and nations - Optimized with single query
	 *
	 * @access public
	 * @static
	 * @param mixed $type - string (stations/country)
	 * @return array
	 */
	public function make( $type ) {
		try {
			// Validate input
			$valid_types = array( 'stations', 'country' );
			if ( ! in_array( $type, $valid_types, true ) ) {
				lwtv_plugin()->error_log( 'dead-complex-taxonomy-error', "Invalid type: {$type}" );
				return array();
			}

			$transient = 'dead_complex_taxonomy_lez_' . $type;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_complex_taxonomy_optimized( $type );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-complex-taxonomy-error', 'Error building dead complex taxonomy: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build dead complex taxonomy statistics using optimized single query
	 *
	 * @param string $type Taxonomy type (stations/country)
	 * @return array
	 */
	private function build_dead_complex_taxonomy_optimized( $type ) {
		global $wpdb;

		try {
			// Single optimized query to get all station/nation death statistics
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					COUNT(DISTINCT p.ID) as shows,
					SUM(COALESCE(char_count.meta_value, 0)) as characters,
					SUM(COALESCE(dead_count.meta_value, 0)) as dead_chars,
					COUNT(DISTINCT CASE WHEN dead_trope.term_taxonomy_id IS NOT NULL THEN p.ID END) as dead_shows
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
				LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
				LEFT JOIN {$wpdb->term_relationships} dead_trope ON p.ID = dead_trope.object_id
				LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_trope.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_tropes'
				LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead-queers'
				WHERE tt.taxonomy = %s
				AND p.post_type = 'post_type_shows'
				AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				ORDER BY t.name",
				'lez_' . $type
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			$array = array();
			foreach ( $results as $row ) {
				// Get term link
				$term_link = get_term_link( (int) $row['slug'], 'lez_' . $type );
				if ( is_wp_error( $term_link ) ) {
					$term_link = '';
				}

				$array[] = array(
					'count'      => (int) $row['dead_chars'],
					'name'       => $row['name'],
					'url'        => $term_link,
					'characters' => (int) $row['characters'],
					'shows'      => (int) $row['shows'],
				);
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-complex-taxonomy-error', 'Error building dead complex taxonomy statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
