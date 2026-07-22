<?php

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		// Only a true cache miss (false) triggers a rebuild; a cached empty array
		// is a valid result (a taxonomy with no non-empty terms) and is reused.
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

	/**
	 * Average and median number of a taxonomy's terms per object, measured across
	 * objects that carry at least one (non-excluded) term. Objects whose only
	 * term is an excluded placeholder (e.g. a "None" term) drop out entirely.
	 *
	 * @param string $post_type     Post type to measure (e.g. 'post_type_shows').
	 * @param string $taxonomy      Taxonomy to count (e.g. 'lez_tropes').
	 * @param array  $exclude_slugs Term slugs to exclude from the count (e.g. array( 'none' )).
	 * @return array { 'average' => float, 'median' => float, 'shows' => int, 'total' => int }.
	 */
	public function get_terms_per_object_stats( $post_type, $taxonomy, $exclude_slugs = array() ) {
		$exclude_slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $exclude_slugs ) ) );
		$cache_key     = 'terms_per_object_' . $post_type . '_' . $taxonomy . ( $exclude_slugs ? '_x' . md5( implode( ',', $exclude_slugs ) ) : '' );
		$cached_data   = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;

		// Optional slug exclusion (e.g. a "None" placeholder term).
		$exclude_where = '';
		$prepare_args  = array( $taxonomy, $post_type );
		if ( $exclude_slugs ) {
			$placeholders  = implode( ',', array_fill( 0, count( $exclude_slugs ), '%s' ) );
			$exclude_where = " WHERE t.slug NOT IN ($placeholders)";
			$prepare_args  = array_merge( $prepare_args, $exclude_slugs );
		}

		// One row per published object that carries >=1 counted term of the
		// taxonomy, each row = how many of that taxonomy's terms the object has.
		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT COUNT(*) AS n
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = %s AND p.post_status = 'publish'
			{$exclude_where}
			GROUP BY tr.object_id",
			$prepare_args
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$counts = array_map( 'intval', (array) $wpdb->get_col( $query ) );

		$stats = array(
			'average' => 0.0,
			'median'  => 0.0,
			'shows'   => 0,
			'total'   => 0,
		);

		$num = count( $counts );
		if ( $num > 0 ) {
			sort( $counts );
			$sum              = array_sum( $counts );
			$mid              = intdiv( $num, 2 );
			$stats['average'] = $sum / $num;
			$stats['median']  = ( 0 === $num % 2 ) ? ( $counts[ $mid - 1 ] + $counts[ $mid ] ) / 2 : (float) $counts[ $mid ];
			$stats['shows']   = $num;
			$stats['total']   = $sum;
		}

		lwtv_plugin()->set_transient( $cache_key, $stats, WEEK_IN_SECONDS );

		return $stats;
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

		// Single query to get both total and dead character counts.
		// ACF repeater stores show relationships as individual meta keys (lezchars_show_group_N_show),
		// not as a serialized value under lezchars_show_group. Join on the sub-field key directly.
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
			INNER JOIN {$wpdb->postmeta} char_shows ON char_shows.meta_key LIKE 'lezchars_show_group_%_show'
				AND char_shows.meta_value = shows.ID
			INNER JOIN {$wpdb->posts} chars ON chars.ID = char_shows.post_id AND chars.post_type = %s AND chars.post_status = 'publish'
			LEFT JOIN {$wpdb->postmeta} chars_death ON chars.ID = chars_death.post_id AND chars_death.meta_key = 'lezchars_last_death'
			WHERE tt.taxonomy = %s
			AND shows.post_type = 'post_type_shows'
			AND shows.post_status = 'publish'
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

		// Cache for 24 hours; query is cheaper after join fix, and counts change infrequently.
		lwtv_plugin()->set_transient( $cache_key, $formatted, DAY_IN_SECONDS );

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

		// Bail on query failure so we don't cache a bogus all-zero result set.
		if ( ! is_array( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Bulk show counts query failed: ' . $wpdb->last_error );
			return array();
		}

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

	/**
	 * Bulk earliest show start-year per term.
	 *
	 * Reads the earliest start year from the ACF key (lezshows_airdates_start) and
	 * folds in the legacy serialized lezshows_airdates['start'] value for any show
	 * that has not been migrated to the new key yet. Without the legacy fallback a
	 * pre-migration (or rolled-back) database returns 0 for every term, which is how
	 * the "New Since 2020" counters silently zeroed out. Every other reader of the
	 * air-date meta already falls back to the legacy array, so this brings the bulk
	 * query in line with them.
	 *
	 * @param string $taxonomy Taxonomy slug (e.g. 'lez_country').
	 * @param array  $slugs    Term slugs to include.
	 * @return array           [ slug => (int) earliest year | 0 ].
	 */
	public function get_bulk_first_years( $taxonomy, $slugs ) {
		global $wpdb;

		$first_years = array();
		foreach ( $slugs as $slug ) {
			$first_years[ ltrim( $slug, '_' ) ] = 0;
		}
		if ( empty( $slugs ) ) {
			return $first_years;
		}

		// Fold a candidate year into the running per-term minimum. Zero/unknown
		// years never win, so they cannot mask a genuine debut year.
		$fold = static function ( &$years, $slug, $year ) {
			$slug = ltrim( $slug, '_' );
			$year = (int) $year;
			if ( $year <= 0 ) {
				return;
			}
			$current = $years[ $slug ] ?? 0;
			if ( 0 === $current || $year < $current ) {
				$years[ $slug ] = $year;
			}
		};

		// Primary: earliest start year from the migrated ACF key.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug AS slug, MIN( CAST( pm.meta_value AS UNSIGNED ) ) AS first_year
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post_type_shows' AND p.post_status = 'publish'
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'lezshows_airdates_start'
				 WHERE pm.meta_value != '' AND CAST( pm.meta_value AS UNSIGNED ) > 0
				 GROUP BY t.slug",
				$taxonomy
			),
			ARRAY_A
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				$fold( $first_years, $row['slug'], $row['first_year'] );
			}
		}

		// Fallback: shows not yet migrated still carry the legacy serialized array.
		// The migrated key is unserializable in SQL, so read the raw legacy meta for
		// shows that lack a usable new key and fold the start year in via PHP.
		$legacy_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug AS slug, legacy.meta_value AS airdates
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post_type_shows' AND p.post_status = 'publish'
				 INNER JOIN {$wpdb->postmeta} legacy ON legacy.post_id = p.ID AND legacy.meta_key = 'lezshows_airdates'
				 LEFT JOIN {$wpdb->postmeta} migrated ON migrated.post_id = p.ID AND migrated.meta_key = 'lezshows_airdates_start' AND migrated.meta_value != '' AND CAST( migrated.meta_value AS UNSIGNED ) > 0
				 WHERE migrated.post_id IS NULL AND legacy.meta_value != ''",
				$taxonomy
			),
			ARRAY_A
		);

		if ( $legacy_rows ) {
			foreach ( $legacy_rows as $row ) {
				$airdates = maybe_unserialize( $row['airdates'] );
				if ( ! is_array( $airdates ) || empty( $airdates['start'] ) ) {
					continue;
				}
				$fold( $first_years, $row['slug'], $airdates['start'] );
			}
		}

		return $first_years;
	}
}
