<?php
/**
 * Name: Character Calculations
 * Description: Calculate various data points for characters
 */

namespace LWTV\CPTs\Characters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		$death_rows      = get_field( 'lezchars_death_year', $post_id );
		$character_death = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();
		$last_char_death = get_post_meta( $post_id, 'lezchars_last_death', true );
		$newest_death    = '0000-00-00';

		foreach ( $character_death as $death ) {
			if ( $death > $newest_death ) {
				$newest_death = $death;
			}
		}

		if ( '0000-00-00' === $newest_death ) {
			// No death dates — remove the meta entirely so FacetWP doesn't index this character
			// in death-sorted results. Clears stale or corrupted values (e.g. 'L') left over
			// from incomplete saves or data migrations.
			if ( '' !== $last_char_death ) {
				delete_post_meta( $post_id, 'lezchars_last_death' );
			}
		} elseif ( $newest_death !== $last_char_death ) {
			update_post_meta( $post_id, 'lezchars_last_death', $newest_death );
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
		$show_group         = get_field( 'lezchars_show_group', $post_id );
		$shows_array_simple = array();

		if ( ! is_array( $show_group ) ) {
			return;
		}

		foreach ( $show_group as $char_show ) {
			// Remove the Array if it's there (pre-migration CMB2 data).
			if ( is_array( $char_show['show'] ) ) {
				if ( ! isset( $char_show['show'][0] ) ) {
					continue;
				}

				$char_show['show'] = $char_show['show'][0];
			}
			$shows_array_simple[] = (int) $char_show['show'];
		}

		// Get all shows with this character.
		$shadow_queery = ( new Shadow_Taxonomy() )->get_shows_for_character( $shadow_character->term_id );

		if ( is_object( $shadow_queery ) ) {
			if ( $shadow_queery->have_posts() ) {
				while ( $shadow_queery->have_posts() ) {
					$shadow_queery->the_post();
					$show_id = get_the_ID();

					// If the show has the taxonomy but the character doesn't have it in the array, remove the taxonomy.
					if ( ! in_array( $show_id, $shows_array_simple, true ) ) {
						wp_remove_object_terms( (int) $show_id, (int) $shadow_character->term_id, CPT_Characters::SHADOW_TAXONOMY );
					}
				}
			}
		}

		foreach ( $show_group as $each_show ) {
			if ( ! isset( $each_show['show'] ) ) {
				continue;
			}

			// Remove the Array (pre-migration CMB2 data).
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
		$actors = get_field( 'lezchars_actor', $post_id );
		if ( ! is_array( $actors ) || empty( $actors ) ) {
			return;
		}

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

			// Verify the relationship was established before running actor calculations.
			$actor_terms = wp_get_post_terms( (int) $actor, CPT_Characters::SHADOW_TAXONOMY, array( 'fields' => 'ids' ) );

			if ( in_array( (int) $shadow_character->term_id, $actor_terms, true ) ) {
				// Relationship confirmed — reset failure counter and run calculations.
				delete_transient( 'shadow_tax_failure_' . $actor );
				( new Actors_Calculations() )->do_the_math( $actor, $force );
			} else {
				// Check whether the shadow term itself exists. If not, a retry is warranted.
				// If it does exist, this is a deeper DB inconsistency — don't keep retrying silently.
				$shadow_term_exists = \Shadow_Taxonomy\Core\get_associated_term( $actor, CPT_Characters::SHADOW_TAXONOMY );

				$failure_key   = 'shadow_tax_failure_' . $actor;
				$failure_count = (int) get_transient( $failure_key );
				++$failure_count;
				set_transient( $failure_key, $failure_count, HOUR_IN_SECONDS );

				if ( $failure_count >= 3 ) {
					lwtv_plugin()->debug_log( 'shadow-taxonomy', "Repeated failure ({$failure_count}x) establishing shadow taxonomy for actor {$actor} — firing lwtv_shadow_tax_sync_failed" );
					do_action( 'lwtv_shadow_tax_sync_failed', (int) $actor, (int) $shadow_character->term_id );
				} elseif ( ! $shadow_term_exists ) {
					lwtv_plugin()->debug_log( 'shadow-taxonomy', "Shadow term missing for actor {$actor}, scheduling retry (attempt {$failure_count})" );
					lwtv_plugin()->schedule_task( 'calculation', $actor, 0, 10 );
				} else {
					lwtv_plugin()->debug_log( 'shadow-taxonomy', "Shadow term exists but wp_get_post_terms returned empty for actor {$actor} — possible DB inconsistency (attempt {$failure_count})" );
				}
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
			return;
		}

		// Calculate Death
		$this->death( $character_id );

		// Get the shadow tax ID
		$shadow_character = \Shadow_Taxonomy\Core\get_associated_term( $character_id, CPT_Characters::SHADOW_TAXONOMY );

		// Update Show data
		$this->sync_shows( $character_id, $shadow_character, $force );

		// Update Actor data
		$this->sync_actors( $character_id, $shadow_character, $force );
	}
}
