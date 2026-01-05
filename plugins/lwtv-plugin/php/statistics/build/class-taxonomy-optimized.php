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
		$cache_key = 'batch_taxonomy_' . $post_type . '_' . md5( wp_json_encode( $taxonomies ) );
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

		$cache_key = 'taxonomy_terms_' . $post_type . '_' . $taxonomy . '_' . md5( wp_json_encode( $terms ) );
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

		if ( false === $array || empty( $array ) ) {
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

	/**
	 * Get bulk character counts for multiple taxonomy terms in a single query
	 *
	 * Eliminates N+1 query patterns by getting character counts for all terms at once.
	 * Returns both total character counts and dead character counts.
	 *
	 * @param string $taxonomy Taxonomy to query (lez_formats, lez_country, lez_stations)
	 * @param array $terms Array of term slugs to get counts for
	 * @param string $post_type Post type to query (default: post_type_characters)
	 * @return array Array of term_slug => ['total' => int, 'dead' => int]
	 */
	public function get_bulk_character_counts( $taxonomy, $terms, $post_type = 'post_type_characters' ) {
		if ( empty( $terms ) ) {
			return array();
		}

		// Create cache key
		$cache_key   = 'bulk_char_counts_' . $taxonomy . '_' . md5( wp_json_encode( $terms ) );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;

		// Sanitize term slugs
		$term_slugs        = array_map( 'sanitize_text_field', $terms );
		$term_placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

		// Single query to get both total and dead character counts
		// Characters are linked to shows through lezchars_show_group meta field (serialized array)
		// The format is: a:1:{i:0;a:3:{s:4:"show";a:1:{i:0;s:3:"655";}s:4:"type";s:9:"recurring";s:7:"appears";a:1:{i:0;s:4:"2017";}}}
		// We need to match characters that are linked to shows belonging to the specific station
		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT
				t.slug,
				COUNT(DISTINCT CASE WHEN chars.post_status = 'publish' THEN chars.ID END) as total_chars,
				COUNT(DISTINCT CASE WHEN chars.post_status = 'publish' AND chars_death.meta_value IS NOT NULL AND chars_death.meta_value != '' THEN chars.ID END) as dead_chars
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			LEFT JOIN {$wpdb->posts} shows ON tr.object_id = shows.ID
			LEFT JOIN {$wpdb->posts} chars ON chars.post_type = %s AND chars.post_status = 'publish'
			LEFT JOIN {$wpdb->postmeta} char_shows ON chars.ID = char_shows.post_id AND char_shows.meta_key = 'lezchars_show_group'
			LEFT JOIN {$wpdb->postmeta} chars_death ON chars.ID = chars_death.post_id AND chars_death.meta_key = 'lezchars_last_death'
			WHERE tt.taxonomy = %s
			AND shows.post_type = 'post_type_shows'
			AND shows.post_status = 'publish'
			AND char_shows.meta_value IS NOT NULL
			AND char_shows.meta_value != ''
			AND char_shows.meta_value COLLATE utf8mb4_unicode_ci LIKE CONCAT('%%s:', LENGTH(CAST(shows.ID AS CHAR)), ':\"', CAST(shows.ID AS CHAR), '\";%%') COLLATE utf8mb4_unicode_ci
			AND t.slug IN ($term_placeholders)
			GROUP BY t.slug",
			array_merge( array( $post_type, $taxonomy ), $term_slugs )
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Handle query failure
		if ( false === $results || empty( $results ) || is_null( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Query failed: ' . $wpdb->last_error );
			return array();
		}

		// Format results
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[ $row['slug'] ] = array(
				'total' => (int) $row['total_chars'],
				'dead'  => (int) $row['dead_chars'],
			);
		}

		// Add zero counts for terms that weren't found
		foreach ( $term_slugs as $slug ) {
			if ( ! isset( $formatted[ $slug ] ) ) {
				$formatted[ $slug ] = array(
					'total' => 0,
					'dead'  => 0,
				);
			}
		}

		// Cache for 1 hour since character counts change less frequently
		lwtv_plugin()->set_transient( $cache_key, $formatted, HOUR_IN_SECONDS );

		return $formatted;
	}

	/**
	 * Get bulk show counts for multiple taxonomy terms in a single query
	 *
	 * Eliminates multiple show count queries by getting all metrics at once.
	 * Returns onair, total, score, and onairscore for each term.
	 *
	 * @param string $taxonomy Taxonomy to query (lez_formats, lez_country, lez_stations)
	 * @param array $terms Array of term slugs to get counts for
	 * @return array Array of term_slug => ['onair' => int, 'total' => int, 'score' => float, 'onairscore' => float]
	 */
	public function get_bulk_show_counts( $taxonomy, $terms ) {
		if ( empty( $terms ) ) {
			return array();
		}

		// Create cache key
		$cache_key   = 'bulk_show_counts_' . $taxonomy . '_' . md5( wp_json_encode( $terms ) );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;

		// Sanitize term slugs
		$term_slugs        = array_map( 'sanitize_text_field', $terms );
		$term_placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

		// Prepare parameters: taxonomy, then all term slugs
		$parameters = array_merge( array( $taxonomy ), $term_slugs );

		// Get current year for on-air calculations
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
		$dt->setTimestamp( $timestamp );
		$current_year = $dt->format( 'Y' );

		// Single query to get all show metrics for all terms
		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT
				t.slug,
				COUNT(DISTINCT shows.ID) as total_shows,
				COUNT(DISTINCT CASE WHEN onair.meta_value = 'yes' THEN shows.ID END) as onair_shows,
				AVG(CASE WHEN scores.meta_value IS NOT NULL THEN CAST(scores.meta_value AS DECIMAL(5,2)) END) as avg_score,
				AVG(CASE WHEN scores.meta_value IS NOT NULL AND onair.meta_value = 'yes' THEN CAST(scores.meta_value AS DECIMAL(5,2)) END) as avg_onair_score
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			LEFT JOIN {$wpdb->posts} shows ON tr.object_id = shows.ID
			LEFT JOIN {$wpdb->postmeta} onair ON shows.ID = onair.post_id AND onair.meta_key = 'lezshows_on_air'
			LEFT JOIN {$wpdb->postmeta} scores ON shows.ID = scores.post_id AND scores.meta_key = 'lezshows_the_score'
			WHERE tt.taxonomy = %s
			AND shows.post_type = 'post_type_shows'
			AND shows.post_status = 'publish'
			AND t.slug IN ($term_placeholders)
			GROUP BY t.slug",
			$parameters
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Format results
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[ $row['slug'] ] = array(
				'onair'      => (int) $row['onair_shows'],
				'total'      => (int) $row['total_shows'],
				'score'      => round( (float) $row['avg_score'], 2 ),
				'onairscore' => round( (float) $row['avg_onair_score'], 2 ),
			);
		}

		// Add zero counts for terms that weren't found
		foreach ( $term_slugs as $slug ) {
			if ( ! isset( $formatted[ $slug ] ) ) {
				$formatted[ $slug ] = array(
					'onair'      => 0,
					'total'      => 0,
					'score'      => 0.0,
					'onairscore' => 0.0,
				);
			}
		}

		// Cache for 1 hour since show counts change less frequently
		lwtv_plugin()->set_transient( $cache_key, $formatted, HOUR_IN_SECONDS );

		return $formatted;
	}
}
