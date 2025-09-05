<?php

namespace LWTV\Statistics\Build;

use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy_Optimized;

class Taxonomy_Optimized {

	/**
	 * Statistics Taxonomy Array - Optimized Version
	 *
	 * Generate array to parse taxonomy content using efficient batch queries
	 * instead of individual WP_Query calls for each term.
	 *
	 * @param string $post_type Post Type to be searched
	 * @param string $taxonomy Taxonomy to be searched
	 * @param string $terms The terms to be matched (default empty)
	 * @param string $operator Search operator (default IN)
	 * @param string $sort_order Sort order: 'count_desc', 'count_asc', 'name_asc', 'name_desc', 'year_asc', 'year_desc'
	 *
	 * @return array
	 */
	public function make( $post_type, $taxonomy, $terms = '', $operator = 'IN', $sort_order = 'count_desc' ) {
		// Create cache key including sort order
		$transient = 'taxonomy_opt_' . $post_type . '_' . $taxonomy . '_' . $sort_order . '_' . md5( $terms . $operator );
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = array();

			// Use optimized query class
			$optimized_queery = new Queery_Taxonomy_Optimized();

			if ( '' === $terms ) {
				// Get all terms for the taxonomy with counts in one query
				$array = $optimized_queery->get_cached_term_counts( $post_type, $taxonomy, array(), $sort_order );
			} else {
				// Get specific term(s) with counts
				$term_array = is_array( $terms ) ? $terms : array( $terms );
				$array      = $optimized_queery->get_cached_term_counts( $post_type, $taxonomy, $term_array, $sort_order );
			}

			// Cache for 7 days since taxonomy data is relatively stable
			lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}

	/**
	 * Batch process multiple taxonomies for the same post type
	 *
	 * @param string $post_type Post type to query
	 * @param array $taxonomies Array of taxonomy names
	 * @return array Multi-dimensional array of taxonomy data
	 */
	public function batch_make( $post_type, $taxonomies ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key = 'batch_taxonomy_' . $post_type . '_' . md5( serialize( $taxonomies ) );
		$array     = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $array ) {
			$optimized_queery = new Queery_Taxonomy_Optimized();
			$array            = $optimized_queery->batch_process_taxonomies( $post_type, $taxonomies );

			// Cache for 7 days
			lwtv_plugin()->set_transient( $cache_key, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}

	/**
	 * Get taxonomy data with post counts for specific terms
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param array $terms Array of term slugs
	 * @return array Taxonomy data with counts
	 */
	public function make_for_terms( $post_type, $taxonomy, $terms ) {
		if ( empty( $terms ) ) {
			return array();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key = 'taxonomy_terms_' . $post_type . '_' . $taxonomy . '_' . md5( serialize( $terms ) );
		$array     = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $array ) {
			$optimized_queery = new Queery_Taxonomy_Optimized();
			$array            = $optimized_queery->get_cached_term_counts( $post_type, $taxonomy, $terms );

			// Cache for 7 days
			lwtv_plugin()->set_transient( $cache_key, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}

	/**
	 * Get comprehensive taxonomy breakdown for statistics pages
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param bool $include_empty Whether to include terms with zero counts
	 * @param string $sort_order Sort order: 'count_desc', 'count_asc', 'name_asc', 'name_desc', 'year_asc', 'year_desc'
	 * @return array Comprehensive taxonomy data
	 */
	public function make_comprehensive( $post_type, $taxonomy, $include_empty = false, $sort_order = 'count_desc' ) {
		$cache_key = 'taxonomy_comp_' . $post_type . '_' . $taxonomy . '_' . $sort_order . '_' . ( $include_empty ? 'all' : 'nonempty' );
		$array     = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $array ) {
			$optimized_queery = new Queery_Taxonomy_Optimized();
			$array            = $optimized_queery->get_cached_term_counts( $post_type, $taxonomy, array(), $sort_order );

			// Filter out empty terms if requested
			if ( ! $include_empty ) {
				$array = array_filter(
					$array,
					function ( $term_data ) {
						return $term_data['count'] > 0;
					}
				);
			}

			// Sort by name ascending
			uasort(
				$array,
				function ( $a, $b ) {
					return strcmp( $a['name'], $b['name'] );
				}
			);

			// Cache for 7 days
			lwtv_plugin()->set_transient( $cache_key, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}
}
