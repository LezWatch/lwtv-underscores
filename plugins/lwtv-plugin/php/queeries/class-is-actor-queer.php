<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

class Is_Actor_Queer {

	/**
	 * Determine if an actor is queer
	 *
	 * There are multiple ways someone can be queer:
	 * sexuality, gender, pronouns, romantic orientation
	 *
	 * There's also an override.
	 *
	 * @access public
	 * @param  mixed $the_id
	 * @return bool
	 */
	public function make( $the_id ): bool {
		// Validate input
		if ( ! isset( $the_id ) || ! is_numeric( $the_id ) ) {
			return false;
		}

		// If we're not an actor, return false
		if ( 'post_type_actors' !== get_post_type( $the_id ) ) {
			return false;
		}

		// Create cache key
		$cache_key     = 'actor_queer_status_' . $the_id;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (bool) $cached_result;
		}

		// Check the override first
		$override = get_post_meta( $the_id, 'lezactors_queer_override', true );
		if ( 'is_queer' === $override ) {
			lwtv_plugin()->set_transient( $cache_key, true, HOUR_IN_SECONDS );
			return true;
		}

		// If we're private, we aren't queer no matter what to protect identities
		if ( 'private' === get_post_status( $the_id ) ) {
			lwtv_plugin()->set_transient( $cache_key, false, HOUR_IN_SECONDS );
			return false;
		}

		// Get all actor taxonomies in a single query
		$taxonomies = $this->get_actor_taxonomies( $the_id );

		// Check if ANY category indicates queerness
		$is_queer = $this->check_queerness( $taxonomies );

		// Cache the result
		lwtv_plugin()->set_transient( $cache_key, $is_queer, HOUR_IN_SECONDS );

		return $is_queer;
	}

	/**
	 * Get all actor taxonomies in a single SQL query
	 *
	 * @param int $actor_id Actor post ID
	 * @return array Taxonomy data organized by taxonomy name
	 */
	private function get_actor_taxonomies( $actor_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT tt.taxonomy, t.slug, t.name
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id = %d
			AND tt.taxonomy IN ('lez_actor_gender', 'lez_actor_sexuality', 'lez_actor_pronouns', 'lez_actor_romantic')",
			$actor_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Organize by taxonomy
		$taxonomies = array();
		foreach ( $results as $result ) {
			$taxonomies[ $result['taxonomy'] ][] = $result['slug'];
		}

		return $taxonomies;
	}

	/**
	 * Check if actor is queer based on taxonomy data
	 *
	 * @param array $taxonomies Taxonomy data
	 * @return bool True if actor is queer
	 */
	private function check_queerness( $taxonomies ): bool {
		// Define straight/not queer terms
		$straight_genders   = array( 'cis-man', 'cis-woman', 'cisgender', 'undefined', 'unknown' );
		$straight_sexuality = array( 'heterosexual', 'unknown' );
		$straight_romantics = array( 'heteroromantic' );

		$all_straight_pronouns = array(
			'cis-man'   => array( 'he', 'him', 'his' ),
			'cis-woman' => array( 'she', 'her', 'hers' ),
			'cisgender' => array( 'he', 'him', 'his', 'she', 'her', 'hers' ),
			'undefined' => array( 'he', 'him', 'his', 'she', 'her', 'hers' ),
			'unknown'   => array( 'he', 'him', 'his', 'she', 'her', 'hers' ),
		);

		// Check each category - if ANY is queer, actor is queer

		// Gender check
		$gender_terms = $taxonomies['lez_actor_gender'] ?? array();
		if ( ! empty( $gender_terms ) && ! array_intersect( $gender_terms, $straight_genders ) ) {
			lwtv_plugin()->error_log( 'actor_queer_debug', 'Actor is queer based on gender terms: ' . wp_json_encode( $gender_terms ) );
			return true; // Has queer gender terms
		}

		// Sexuality check
		$sexuality_terms = $taxonomies['lez_actor_sexuality'] ?? array();
		if ( ! empty( $sexuality_terms ) && ! array_intersect( $sexuality_terms, $straight_sexuality ) ) {
			lwtv_plugin()->error_log( 'actor_queer_debug', 'Actor is queer based on sexuality terms: ' . wp_json_encode( $sexuality_terms ) );
			return true; // Has queer sexuality terms
		}

		// Pronouns check
		$pronoun_terms = $taxonomies['lez_actor_pronouns'] ?? array();
		if ( ! empty( $pronoun_terms ) ) {
			// Check if pronouns are queer based on gender
			$has_queer_pronouns = true;
			if ( ! empty( $gender_terms ) ) {
				$primary_gender    = $gender_terms[0];
				$straight_pronouns = $all_straight_pronouns[ $primary_gender ] ?? array();
				if ( ! empty( $straight_pronouns ) && ! array_diff( $pronoun_terms, $straight_pronouns ) ) {
					$has_queer_pronouns = false; // Only has straight pronouns
				}
			}
			if ( $has_queer_pronouns ) {
				lwtv_plugin()->error_log( 'actor_queer_debug', 'Actor is queer based on pronouns terms: ' . wp_json_encode( $pronoun_terms ) );
				return true; // Has queer pronouns
			}
		}

		// Romantic orientation check
		$romantic_terms = $taxonomies['lez_actor_romantic'] ?? array();
		if ( ! empty( $romantic_terms ) && ! array_intersect( $romantic_terms, $straight_romantics ) ) {
			lwtv_plugin()->error_log( 'actor_queer_debug', 'Actor is queer based on romantic terms: ' . wp_json_encode( $romantic_terms ) );
			return true; // Has queer romantic terms
		}

		return false; // No queer indicators found
	}
}
