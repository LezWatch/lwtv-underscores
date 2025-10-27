<?php
/**
 * Name: Character Calculations
 * Description: Calculate various data points for characters
 */

namespace LWTV\CPTs\Characters;

use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Actors\Calculations as Actors_Calculations;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\Queeries\Shadow_Taxonomy;

class Calculations {

	/**
	 * Calculate the most recent death
	 * This has to happen because Sara Lance.
	 *
	 * @param  int   $post_id
	 * @return N/A   No return, just update
	 */
	public function death( $post_id ) {
		// get the most recent death and save it as a new meta
		$character_death = get_post_meta( $post_id, 'lezchars_death_year', true );
		$last_char_death = get_post_meta( $post_id, 'lezchars_last_death', true );
		$newest_death    = '0000-00-00';
		if ( '' !== $character_death ) {
			foreach ( $character_death as $death ) {
				if ( $death > $newest_death ) {
					$newest_death = $death;
				}
			}
			// If there's a newest death AND it isn't equal last death, save it
			if ( '0000-00-00' !== $newest_death && $newest_death !== $last_char_death ) {
				update_post_meta( $post_id, 'lezchars_last_death', $newest_death );
			}
		}
	}

	/**
	 * Sync Shows
	 *
	 * Sync the shadow taxonomy for shows with the character.
	 *
	 * @param  int  $post_id
	 * @param  mixed $shadow_character
	 * @param  bool $force Force the calculation to run
	 * @return void
	 */
	public function sync_shows( $post_id, $shadow_character, $force = false ) {
		$show_group         = get_post_meta( $post_id, 'lezchars_show_group', true );
		$shows_array_simple = array();

		if ( ! $show_group ) {
			return;
		}

		foreach ( $show_group as $char_show ) {
			// Remove the Array if it's there.
			if ( is_array( $char_show['show'] ) ) {
				if ( ! isset( $char_show['show'][0] ) ) {
					continue;
				}

				$char_show['show'] = $char_show['show'][0];
			}
			$shows_array_simple[] = $char_show['show'];
		}

		// Get all shows with this character.
		$shadow_queery = ( new Shadow_Taxonomy() )->get_shows_for_character( $shadow_character->term_id );

		if ( is_object( $shadow_queery ) ) {
			if ( $shadow_queery->have_posts() ) {
				while ( $shadow_queery->have_posts() ) {
					$shadow_queery->the_post();
					$show_id = get_the_ID();

					// If the show has the taxonomy but the character doesn't have it in the array, remove the taxonomy.
					if ( ! in_array( (string) $show_id, $shows_array_simple, true ) ) {
						wp_remove_object_terms( (int) $show_id, (int) $shadow_character->term_id, CPT_Characters::SHADOW_TAXONOMY );
					}
				}
			}
		}

		foreach ( $show_group as $each_show ) {
			if ( ! isset( $each_show['show'] ) ) {
				continue;
			}

			// Remove the Array.
			if ( is_array( $each_show['show'] ) ) {
				$each_show['show'] = $each_show['show'][0];
			}

			// Add the tax for the character to the show.
			wp_add_object_terms( (int) $each_show['show'], (int) $shadow_character->term_id, CPT_Characters::SHADOW_TAXONOMY );

			( new Shows_Calculations() )->do_the_math( $each_show['show'], $force );
		}
	}

	/**
	 * Sync Actors
	 *
	 * Sync the shadow taxonomy for actors with the character.
	 *
	 * @param  int  $post_id
	 * @param  mixed $shadow_character
	 * @param  bool $force Force the calculation to run
	 * @return void
	 */
	public function sync_actors( $post_id, $shadow_character, $force = false ) {
		$actors = get_post_meta( $post_id, 'lezchars_actor', true );
		if ( ! $actors ) {
			return;
		}

		$actors = ( ! is_array( $actors ) ) ? array( $actors ) : $actors;

		// Get all actors with this character taxonomy.
		$shadow_actors = ( new Shadow_Taxonomy() )->get_actors_for_character( $shadow_character->term_id );

		if ( is_array( $shadow_actors ) && ! empty( $shadow_actors ) ) {
			foreach ( $shadow_actors as $actor_post ) {
				$actor_id = $actor_post->ID;

				// If the actor has the taxonomy but the character doesn't have it in the array, remove the taxonomy.
				if ( ! in_array( (string) $actor_id, $actors, true ) ) {
					wp_remove_object_terms( (int) $actor_id, (int) $shadow_character->term_id, CPT_Characters::SHADOW_TAXONOMY );
				}
			}
		}

		foreach ( $actors as $actor ) {
			// Add the tax for the character to the actor.
			wp_add_object_terms( (int) $actor, (int) $shadow_character->term_id, CPT_Characters::SHADOW_TAXONOMY );

			// Verify the relationship was established before running actor calculations
			$actor_terms = wp_get_post_terms( (int) $actor, CPT_Characters::SHADOW_TAXONOMY, array( 'fields' => 'ids' ) );

			// Run the calculations if the relationship was established.
			if ( in_array( (int) $shadow_character->term_id, $actor_terms, true ) ) {
				( new Actors_Calculations() )->do_the_math( $actor, $force );
			} else {
				// Log the failure and schedule a retry
				lwtv_plugin()->error_log( 'character-calc', "Failed to establish shadow taxonomy for actor {$actor}, scheduling retry" );
				lwtv_plugin()->schedule_task( 'calculation', $actor, 0, 10 ); // Retry in 10 seconds
			}
		}
	}

	/**
	 * Does the Math
	 *
	 * @param  int  $character_id Post ID of character
	 * @param  bool $force        Force the calculation to run
	 * @return void
	 */
	public function do_the_math( $character_id, $force = false ): void {

		// If force is true, destroy any cached data before recalculation
		if ( $force ) {
			lwtv_plugin()->invalidate_statistics_cache( 'post_type_characters', $character_id );
		}

		if ( ! isset( $character_id ) || CPT_Characters::SLUG !== get_post_type( $character_id ) ) {
			// delete the meta fields
			delete_post_meta( $character_id, 'lezchars_death_year' );
			delete_post_meta( $character_id, 'lezchars_last_death' );
			delete_post_meta( $character_id, 'lezchars_show_group' );
			delete_post_meta( $character_id, 'lezchars_actor' );
			delete_post_meta( $character_id, 'lezchars_dead_list' );
			delete_post_meta( $character_id, 'lezchars_queer_override' );
			return;
		}

		// Calculate Death
		self::death( $character_id );

		// Get the shadow tax ID
		$shadow_character = \Shadow_Taxonomy\Core\get_associated_term( $character_id, CPT_Characters::SHADOW_TAXONOMY );

		// Update Show data
		self::sync_shows( $character_id, $shadow_character, $force );

		// Update Actor data
		self::sync_actors( $character_id, $shadow_character, $force );
	}
}
