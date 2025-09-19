<?php
/**
 * namespace LWTV\Queeries;
 *
 * @since 5.0
 */

namespace LWTV\Queeries;

class Is_Actor_Trans {

	/**
	 * Determine if an actor is transgender IRL
	 *
	 * @access public
	 * @param  int $the_id - Post ID
	 * @return bool
	 */
	public function make( $the_id ): bool {
		// Validate input
		if ( ! isset( $the_id ) || ! is_numeric( $the_id ) ) {
			return false;
		}

		// Only run for actors
		if ( 'post_type_actors' !== get_post_type( $the_id ) ) {
			return false;
		}

		// Create cache key
		$cache_key     = 'actor_trans_status_' . $the_id;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (bool) $cached_result;
		}

		// If the post is private, auto-false
		if ( 'private' === get_post_status( $the_id ) ) {
			lwtv_plugin()->set_transient( $cache_key, false, HOUR_IN_SECONDS );
			return false;
		}

		// The gender terms this actor uses:
		$gender_terms = get_the_terms( $the_id, 'lez_actor_gender', true );

		// If there are terms, check for trans terms directly
		if ( ! empty( $gender_terms ) && ! is_wp_error( $gender_terms ) ) {
			$term_slugs = wp_list_pluck( $gender_terms, 'slug' );

			// Check if any term contains 'trans'
			foreach ( $term_slugs as $slug ) {
				if ( false !== strpos( $slug, 'trans' ) ) {
					lwtv_plugin()->set_transient( $cache_key, true, HOUR_IN_SECONDS );
					return true;
				}
			}
		}

		// Cache false result
		lwtv_plugin()->set_transient( $cache_key, false, HOUR_IN_SECONDS );
		return false;
	}
}
