<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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

		// Only compute/store queer status for published actors. Any other
		// status (private/draft/pending/future/trash) is treated as not-queer
		// to protect unpublished and privacy-hidden identities, and to avoid
		// writing post meta for unpublished records.
		if ( 'publish' !== get_post_status( $the_id ) ) {
			return false;
		}

		// Check the override first
		$override = get_post_meta( $the_id, 'lezactors_queer_override', true );
		if ( 'is_queer' === $override ) {
			return true;
		}

		// Get all actor taxonomies in a single query
		$taxonomies = $this->get_actor_taxonomies( $the_id );

		// Check if ANY category indicates queerness
		$is_queer = $this->check_queerness( $taxonomies );

		// Update the post meta with the result
		update_post_meta( $the_id, 'lezactors_queer_status', (bool) $is_queer );

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
		// Define straight/not queer terms - any term NOT in these lists is queer
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

		// Get all taxonomy terms
		$gender_terms    = $taxonomies['lez_actor_gender'] ?? array();
		$pronoun_terms   = $taxonomies['lez_actor_pronouns'] ?? array();
		$sexuality_terms = $taxonomies['lez_actor_sexuality'] ?? array();
		$romantic_terms  = $taxonomies['lez_actor_romantic'] ?? array();

		// Check 1: If NOT cis gender (i.e., has non-cis gender terms), return true
		if ( ! empty( $gender_terms ) ) {
			// Check if gender terms are not in the straight list
			$non_straight_genders = array_diff( $gender_terms, $straight_genders );
			if ( ! empty( $non_straight_genders ) ) {
				lwtv_plugin()->debug_log( 'is-queer', 'Actor is queer based on gender terms: ' . wp_json_encode( $gender_terms ) );
				return true;
			}
		}

		// Check 2: If cis gender BUT pronouns are NOT straight, return true
		if ( ! empty( $pronoun_terms ) && ! empty( $gender_terms ) ) {
			$primary_gender    = $gender_terms[0];
			$straight_pronouns = $all_straight_pronouns[ $primary_gender ] ?? array();

			// If we have straight pronouns defined for this gender, check if actor uses them
			if ( ! empty( $straight_pronouns ) ) {
				// Check if actor uses ONLY straight pronouns for their gender
				$has_non_straight_pronouns = ! empty( array_diff( $pronoun_terms, $straight_pronouns ) );
				if ( $has_non_straight_pronouns ) {
					lwtv_plugin()->debug_log( 'is-queer', 'Actor is queer based on pronouns: ' . wp_json_encode( $pronoun_terms ) . ' for gender: ' . wp_json_encode( $gender_terms ) );
					return true;
				}
			} else {
				// No straight pronouns defined for this gender (e.g., non-binary, etc.)
				// If they have pronouns set and we don't know what's "straight" for them, consider queer
				lwtv_plugin()->debug_log( 'is-queer', 'Actor is queer based on pronouns (no straight definition): ' . wp_json_encode( $pronoun_terms ) . ' for gender: ' . wp_json_encode( $gender_terms ) );
				return true;
			}
		}

		// Check 3: If cis gender AND straight pronouns BUT sexuality is NOT straight, return true
		if ( ! empty( $sexuality_terms ) ) {
			// Check if sexuality is NOT in straight list
			$non_straight_sexuality = array_diff( $sexuality_terms, $straight_sexuality );
			if ( ! empty( $non_straight_sexuality ) ) {
				lwtv_plugin()->debug_log( 'is-queer', 'Actor is queer based on sexuality terms: ' . wp_json_encode( $sexuality_terms ) );
				return true;
			}
		}

		// Check 4: Romantic orientation is NOT straight, return true
		if ( ! empty( $romantic_terms ) ) {
			$non_straight_romantic = array_diff( $romantic_terms, $straight_romantics );
			if ( ! empty( $non_straight_romantic ) ) {
				lwtv_plugin()->debug_log( 'is-queer', 'Actor is queer based on romantic terms: ' . wp_json_encode( $romantic_terms ) );
				return true;
			}
		}

		// All checks passed - NOT queer based on available data
		return false;
	}
}
