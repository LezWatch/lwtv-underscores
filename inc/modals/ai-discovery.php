<?php

/**
 * Create the AI Discovery modal.
 */

class LWTV_AI_Discovery_Modal {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Placeholder for future actions.
	}

	/**
	 * Output the floating AI Discovery widget in the footer.
	 * Only on pages where we don't have an inline panel (404, no-results).
	 */
	public function output_modal() {
		global $wp_query;

		if ( is_404() ) {
			return;
		}
		if ( is_search() && isset( $wp_query->found_posts ) && 0 === (int) $wp_query->found_posts ) {
			return;
		}

		$mood_chips = $this->get_mood_chips();
		get_template_part( 'template-parts/partials/ai/discovery-widget', null, array( 'mood_chips' => $mood_chips ) );
	}

	/**
	 * Get the context-aware mood chips for the AI Discovery panel.
	 *
	 * @return array Array of prompt strings for quick-start chips.
	 */
	public function get_mood_chips() {
		$chips = array();

		if ( is_404() ) {
			$chips = array(
				__( "I'm lost—just show me the highest-rated shows right now", 'lwtv-underscores' ),
				__( 'Find me web series from the US', 'lwtv-underscores' ),
				__( 'Find me shows worth watching from Britain', 'lwtv-underscores' ),
			);
		} elseif ( is_singular( 'post_type_shows' ) ) {
			$post_id = get_the_ID();
			$genres  = get_the_terms( $post_id, 'lez_genres' );
			$country = get_the_terms( $post_id, 'lez_country' );
			if ( $genres && ! is_wp_error( $genres ) ) {
				$genre_name = $genres[0]->name;
				$chips[]    = sprintf(
					/* translators: %s: genre name */
					__( 'Find more %s without the \'Bury Your Gays\' trope', 'lwtv-underscores' ),
					$genre_name
				);
			}
			if ( $country && ! is_wp_error( $country ) ) {
				$country_name = $country[0]->name;
				$chips[]      = sprintf(
					/* translators: %s: country name */
					__( 'Show me the best shows from %s that are still on air', 'lwtv-underscores' ),
					$country_name
				);
			}
			if ( empty( $chips ) ) {
				$chips = array(
					__( 'More like this with happy endings', 'lwtv-underscores' ),
					__( 'More like this but in web series', 'lwtv-underscores' ),
				);
			}
		} elseif ( is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				if ( 'lez_country' === $term->taxonomy ) {
					$chips[] = sprintf(
						/* translators: %s: country name */
						__( 'Find me shows from %s I\'ve never heard of', 'lwtv-underscores' ),
						$term->name
					);
				} elseif ( 'lez_genres' === $term->taxonomy ) {
					$chips[] = sprintf(
						/* translators: %s: genre name */
						__( 'More %s with happy endings', 'lwtv-underscores' ),
						$term->name
					);
				}
			}
		}

		if ( empty( $chips ) ) {
			$chips = array(
				__( 'Find me web series from the US', 'lwtv-underscores' ),
				__( 'Find me shows worth watching from the UK', 'lwtv-underscores' ),
				__( 'Find me shows that are still on air from Canada', 'lwtv-underscores' ),
			);
		}

		return $chips;
	}
}

new LWTV_AI_Discovery_Modal();
