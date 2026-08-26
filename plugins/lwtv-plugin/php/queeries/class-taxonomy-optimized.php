<?php
/**
 * Optimized Taxonomy Query Class
 *
 * Replaces individual taxonomy queries with efficient batch operations
 * to eliminate N+1 query patterns.
 *
 * @package LezWatch.TV
 * @since 5.0
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Taxonomy_Optimized {

	/**
	 * Get taxonomy term counts for all terms in a single query
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param array $terms Optional array of specific terms to query
	 * @param string $sort_order Sort order: 'count_desc', 'count_asc', 'name_asc', 'name_desc', 'year_asc', 'year_desc'
	 * @return array Array of term slugs with their counts
	 */
	public function get_term_counts( $post_type, $taxonomy, $terms = array(), $sort_order = 'count_desc' ) {
		global $wpdb;

		// Determine sort order
		$order_clause = $this->get_order_clause( $sort_order );

		// Build the query to get term counts efficiently
		if ( ! empty( $terms ) ) {
			$term_slugs        = array_map( 'sanitize_text_field', $terms );
			$term_placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					t.term_id,
					COUNT(tr.object_id) as count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE tt.taxonomy = %s
				AND p.post_type = %s
				AND p.post_status = 'publish'
				AND t.slug IN ($term_placeholders)
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY $order_clause
				",
				array_merge( array( $taxonomy, $post_type ), $term_slugs )
			);
			// phpcs:enable
		} else {
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					t.slug,
					t.name,
					t.term_id,
					COUNT(tr.object_id) as count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE tt.taxonomy = %s
				AND p.post_type = %s
				AND p.post_status = 'publish'
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY $order_clause",
				$taxonomy,
				$post_type
			);
			// phpcs:enable
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $queery, ARRAY_A );

		// Format results for compatibility
		$formatted_results = array();
		foreach ( $results as $result ) {
			$formatted_results[ $result['slug'] ] = array(
				'count' => (int) $result['count'],
				'name'  => $result['name'],
				'url'   => get_term_link( (int) $result['term_id'], $taxonomy ),
			);
		}

		return $formatted_results;
	}

	/**
	 * Get ORDER BY clause based on sort order
	 *
	 * @param string $sort_order Sort order type
	 * @return string ORDER BY clause
	 */
	private function get_order_clause( $sort_order ) {
		switch ( $sort_order ) {
			case 'count_asc':
				return 'count ASC, t.name ASC';
			case 'name_asc':
				return 't.name ASC';
			case 'name_desc':
				return 't.name DESC';
			case 'year_asc':
				return 'CAST(t.name AS UNSIGNED) ASC, t.name ASC';
			case 'year_desc':
				return 'CAST(t.name AS UNSIGNED) DESC, t.name DESC';
			case 'count_desc':
			default:
				return 'count DESC, t.name ASC';
		}
	}

	/**
	 * Get posts for multiple terms in a single query
	 *
	 * Unlike Post_Type::make(), this does not cache the query object, so a
	 * caller asking for full posts is paying memory rather than writing a blob
	 * into the object cache. It is still worth asking for `ids` when that is all
	 * you want -- the BYQ debugger scan does, while the BYQ REST endpoint
	 * genuinely reads post_title and post_name off each result.
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param array $terms Array of term slugs
	 * @param string $operator Query operator (IN, NOT IN, AND)
	 * @param string $fields Fields to return: 'all' or 'ids'. Default 'all'.
	 * @return \WP_Query Query object
	 */
	public function get_posts_for_terms( $post_type, $taxonomy, $terms, $operator = 'IN', $fields = 'all' ) {
		if ( empty( $terms ) ) {
			return new \WP_Query( array( 'post__in' => array( 0 ) ) ); // Return empty query
		}

		/*
		 * -1 rather than wp_count_posts( $post_type )->publish. That was an
		 * upper bound derived from every published post of the type, for a query
		 * that returns a filtered subset of them -- so it did the same job as -1
		 * while adding a lookup and implying a limit that was never meaningful.
		 */
		$query_args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => -1,
			'fields'                 => $fields,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => array( 'publish' ),
			'tax_query'              => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => $operator,
				),
			),
		);

		$query = new \WP_Query( $query_args );
		wp_reset_query();

		return $query;
	}

	/**
	 * Get comprehensive taxonomy data with counts in a single operation
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param array $terms Optional array of specific terms
	 * @param string $sort_order Sort order: 'count_desc', 'count_asc', 'name_asc', 'name_desc', 'year_asc', 'year_desc'
	 * @return array Comprehensive taxonomy data
	 */
	public function get_comprehensive_data( $post_type, $taxonomy, $terms = array(), $sort_order = 'count_desc' ) {
		// Get all terms with counts in one query
		$term_counts = $this->get_term_counts( $post_type, $taxonomy, $terms, $sort_order );

		// If no specific terms requested, get all terms for the taxonomy
		if ( empty( $terms ) ) {
			$all_terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			// Merge with counts data
			foreach ( $all_terms as $term ) {
				if ( ! isset( $term_counts[ $term->slug ] ) ) {
					$term_counts[ $term->slug ] = array(
						'count' => 0,
						'name'  => $term->name,
						'url'   => get_term_link( $term->term_id, $taxonomy ),
					);
				}
			}
		}

		return $term_counts;
	}

	/**
	 * Batch process multiple taxonomies for the same post type
	 *
	 * @param string $post_type Post type to query
	 * @param array $taxonomies Array of taxonomy names
	 * @return array Multi-dimensional array of taxonomy data
	 */
	public function batch_process_taxonomies( $post_type, $taxonomies ) {
		$results = array();

		foreach ( $taxonomies as $taxonomy ) {
			$results[ $taxonomy ] = $this->get_comprehensive_data( $post_type, $taxonomy );
		}

		return $results;
	}

	/**
	 * Get term counts with caching
	 *
	 * @param string $post_type Post type to query
	 * @param string $taxonomy Taxonomy to query
	 * @param array $terms Optional array of specific terms
	 * @param string $sort_order Sort order: 'count_desc', 'count_asc', 'name_asc', 'name_desc', 'year_asc', 'year_desc'
	 * @param int $cache_duration Cache duration in seconds (default: 1 day)
	 * @return array Cached taxonomy data
	 */
	public function get_cached_term_counts( $post_type, $taxonomy, $terms = array(), $sort_order = 'count_desc', $cache_duration = DAY_IN_SECONDS ) {
		$cache_key = 'taxonomy_counts_' . $post_type . '_' . $taxonomy . '_' . $sort_order . '_' . md5( wp_json_encode( $terms ) );

		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $cached_data ) {
			$cached_data = $this->get_comprehensive_data( $post_type, $taxonomy, $terms, $sort_order );
			lwtv_plugin()->set_transient( $cache_key, $cached_data, $cache_duration );
		}

		return $cached_data;
	}
}
