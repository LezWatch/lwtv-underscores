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
use LWTV\Relationships\Build\Link_Rows;
use LWTV\Relationships\Sync;

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

		// Rewrite this character's rows in the link table from ACF.
		Sync::character( (int) $character_id );

		// Recalculate every show and actor the character's ACF lists, whatever
		// their status, as the shadow-term sync used to.
		$rows = Link_Rows::from_fields(
			(int) $character_id,
			get_field( 'lezchars_show_group', $character_id ),
			get_field( 'lezchars_actor', $character_id )
		);

		foreach ( Link_Rows::ids_of( $rows, 'show' ) as $show_id ) {
			( new Shows_Calculations() )->do_the_math( $show_id, $force );
		}

		foreach ( Link_Rows::ids_of( $rows, 'actor' ) as $actor_id ) {
			( new Actors_Calculations() )->do_the_math( $actor_id, $force );
		}
	}
}
