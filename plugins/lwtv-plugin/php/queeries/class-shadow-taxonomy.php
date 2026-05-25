<?php
/**
 * Shadow Taxonomy Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Actors as CPT_Actors;

class Shadow_Taxonomy {

	/**
	 * Get shows for a character using the shadow taxonomy (live data, no caching)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array The shows for the character
	 */
	public function get_shows_for_character( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$shows_slug      = CPT_Shows::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Use proper taxonomy query instead of meta query
		$query_args = array(
			'post_type'              => $shows_slug,
			'posts_per_page'         => -1, // Get all shows
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query = new \WP_Query( $query_args );
		$shows = $query->posts;
		wp_reset_postdata();

		return $shows;
	}

	/**
	 * Get shows for a character using the shadow taxonomy (cached for read-only operations)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array The shows for the character
	 */
	public function get_shows_for_character_cached( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$shows_slug      = CPT_Shows::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key
		$cache_key    = 'shadow_shows_' . $shadow_character_id;
		$cached_shows = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_shows ) {
			return $cached_shows;
		}

		// Use proper taxonomy query instead of meta query
		$query_args = array(
			'post_type'              => $shows_slug,
			'posts_per_page'         => -1, // Get all shows
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query = new \WP_Query( $query_args );
		$shows = $query->posts;
		wp_reset_postdata();

		// Cache for 1 hour since shadow taxonomy relationships are relatively stable
		lwtv_plugin()->set_transient( $cache_key, $shows, HOUR_IN_SECONDS );

		return $shows;
	}

	/**
	 * Get show IDs for a character using the shadow taxonomy (optimized for batch processing)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array Array of show post IDs
	 */
	public function get_show_ids_for_character( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$shows_slug      = CPT_Shows::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key
		$cache_key  = 'shadow_show_ids_' . $shadow_character_id;
		$cached_ids = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_ids ) {
			return $cached_ids;
		}

		// Use optimized query for just IDs
		$query_args = array(
			'post_type'              => $shows_slug,
			'posts_per_page'         => -1,
			'fields'                 => 'ids', // Only get IDs, not full post objects
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query    = new \WP_Query( $query_args );
		$show_ids = $query->posts;
		wp_reset_postdata();

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $show_ids, HOUR_IN_SECONDS );

		return $show_ids;
	}

	/**
	 * Get shows for multiple characters in a single query (batch processing)
	 *
	 * @param array $shadow_character_ids Array of term IDs
	 * @return array Associative array with character IDs as keys and show arrays as values
	 */
	public function get_shows_for_characters_batch( $shadow_character_ids ) {
		if ( empty( $shadow_character_ids ) || ! is_array( $shadow_character_ids ) ) {
			return array();
		}

		$shows_slug      = CPT_Shows::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key for batch
		$cache_key      = 'shadow_shows_batch_' . md5( implode( ',', $shadow_character_ids ) );
		$cached_results = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_results ) {
			return $cached_results;
		}

		$results = array();

		// Use single query to get all shows for all characters
		$query_args = array(
			'post_type'              => $shows_slug,
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $shadow_character_ids ),
				),
			),
		);

		$query = new \WP_Query( $query_args );
		$shows = $query->posts;
		wp_reset_postdata();

		// Group shows by character term ID
		foreach ( $shows as $show ) {
			$terms = wp_get_post_terms( $show->ID, $shadow_taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					if ( in_array( $term_id, $shadow_character_ids, true ) ) {
						if ( ! isset( $results[ $term_id ] ) ) {
							$results[ $term_id ] = array();
						}
						$results[ $term_id ][] = $show;
					}
				}
			}
		}

		// Ensure all requested character IDs have entries (even if empty)
		foreach ( $shadow_character_ids as $character_id ) {
			if ( ! isset( $results[ $character_id ] ) ) {
				$results[ $character_id ] = array();
			}
		}

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $results, HOUR_IN_SECONDS );

		return $results;
	}

	/**
	 * Get actors for a character using the shadow taxonomy (live data, no caching)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array The actors for the character
	 */
	public function get_actors_for_character( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$actors_slug     = CPT_Actors::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Use proper taxonomy query instead of meta query
		$query_args = array(
			'post_type'              => $actors_slug,
			'posts_per_page'         => -1, // Get all actors
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query  = new \WP_Query( $query_args );
		$actors = $query->posts;
		wp_reset_postdata();

		return $actors;
	}

	/**
	 * Get actors for a character using the shadow taxonomy (cached for read-only operations)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array The actors for the character
	 */
	public function get_actors_for_character_cached( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$actors_slug     = CPT_Actors::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key
		$cache_key     = 'shadow_actors_' . $shadow_character_id;
		$cached_actors = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_actors ) {
			return $cached_actors;
		}

		// Use proper taxonomy query instead of meta query
		$query_args = array(
			'post_type'              => $actors_slug,
			'posts_per_page'         => -1, // Get all actors
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query  = new \WP_Query( $query_args );
		$actors = $query->posts;
		wp_reset_postdata();

		// Cache for 1 hour since shadow taxonomy relationships are relatively stable
		lwtv_plugin()->set_transient( $cache_key, $actors, HOUR_IN_SECONDS );

		return $actors;
	}

	/**
	 * Get actor IDs for a character using the shadow taxonomy (optimized for batch processing)
	 *
	 * @param int $shadow_character_id The term ID of the shadow character
	 * @return array Array of actor post IDs
	 */
	public function get_actor_ids_for_character( $shadow_character_id ) {
		// Validate input
		if ( ! isset( $shadow_character_id ) || ! is_numeric( $shadow_character_id ) ) {
			return array();
		}

		$actors_slug     = CPT_Actors::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key
		$cache_key  = 'shadow_actor_ids_' . $shadow_character_id;
		$cached_ids = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_ids ) {
			return $cached_ids;
		}

		// Use optimized query for just IDs
		$query_args = array(
			'post_type'              => $actors_slug,
			'posts_per_page'         => -1,
			'fields'                 => 'ids', // Only get IDs, not full post objects
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $shadow_character_id,
				),
			),
		);

		$query     = new \WP_Query( $query_args );
		$actor_ids = $query->posts;
		wp_reset_postdata();

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $actor_ids, HOUR_IN_SECONDS );

		return $actor_ids;
	}

	/**
	 * Get actors for multiple characters in a single query (batch processing)
	 *
	 * @param array $shadow_character_ids Array of term IDs
	 * @return array Associative array with character IDs as keys and actor arrays as values
	 */
	public function get_actors_for_characters_batch( $shadow_character_ids ) {
		if ( empty( $shadow_character_ids ) || ! is_array( $shadow_character_ids ) ) {
			return array();
		}

		$actors_slug     = CPT_Actors::SLUG;
		$shadow_taxonomy = CPT_Characters::SHADOW_TAXONOMY;

		// Create cache key for batch
		$cache_key      = 'shadow_actors_batch_' . md5( implode( ',', $shadow_character_ids ) );
		$cached_results = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_results ) {
			return $cached_results;
		}

		$results = array();

		// Use single query to get all actors for all characters
		$query_args = array(
			'post_type'              => $actors_slug,
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'tax_query'              => array(
				array(
					'taxonomy' => $shadow_taxonomy,
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $shadow_character_ids ),
				),
			),
		);

		$query  = new \WP_Query( $query_args );
		$actors = $query->posts;
		wp_reset_postdata();

		// Group actors by character term ID
		foreach ( $actors as $actor ) {
			$terms = wp_get_post_terms( $actor->ID, $shadow_taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					if ( in_array( $term_id, $shadow_character_ids, true ) ) {
						if ( ! isset( $results[ $term_id ] ) ) {
							$results[ $term_id ] = array();
						}
						$results[ $term_id ][] = $actor;
					}
				}
			}
		}

		// Ensure all requested character IDs have entries (even if empty)
		foreach ( $shadow_character_ids as $character_id ) {
			if ( ! isset( $results[ $character_id ] ) ) {
				$results[ $character_id ] = array();
			}
		}

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $results, HOUR_IN_SECONDS );

		return $results;
	}
}
