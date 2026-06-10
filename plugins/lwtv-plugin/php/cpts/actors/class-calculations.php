<?php
/**
 * Name: Actor Calculations
 * Description: Calculate various data points for actors
 */

namespace LWTV\CPTs\Actors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Queeries\Is_Actor_Queer;
use LWTV\CPTs\Actors as CPT_Actors;

class Calculations {

	/*
	 * Count all characters for an actor.
	 *
	 * @param int $post_id The post ID of the actor.
	 */
	public function count( $post_id, $type = 'count' ) {

		$type_array = array( 'count', 'none', 'dead' );

		// If this isn't an actor post or a valid request, return nothing
		if ( CPT_Actors::SLUG !== get_post_type( $post_id ) || ! in_array( $type, $type_array, true ) ) {
			return;
		}

		// Get all character counts in single pass to avoid redundant queries
		$all_counts = $this->count_all_character_types( $post_id );

		return $all_counts[ $type ] ?? 0;
	}

	/**
	 * Calculate all character counts in a single pass for performance
	 *
	 * @param int $post_id The post ID of the actor
	 * @return array Array of counts for all types
	 */
	private function count_all_character_types( $post_id ) {
		// Initialize counts
		$counts = array(
			'count' => 0,
			'dead'  => 0,
		);

		// Get array of characters (by ID)
		$characters = lwtv_plugin()->get_actor_characters( $post_id );

		if ( empty( $characters ) || ! is_array( $characters ) ) {
			return $counts;
		}

		// Get all character IDs for batch operations
		$character_ids = array_keys( $characters );

		// Batch get all character data
		$character_data = $this->get_batch_character_data( $character_ids );

		// Process all characters efficiently
		foreach ( $characters as $char_id => $char_details ) {
			$char_info = $character_data[ $char_id ];

			// If the character isn't published, skip it
			if ( 'publish' !== $char_info['status'] ) {
				continue;
			}

			// Check if this actor plays this character
			if ( $this->actor_plays_character( $post_id, $char_info['actors'] ) ) {
				++$counts['count'];
				if ( $char_info['is_dead'] ) {
					++$counts['dead'];
				}
			}
		}

		return $counts;
	}

	/**
	 * Get character data for multiple characters in batch queries
	 *
	 * @param array $character_ids Array of character IDs
	 * @return array Array of character data organized by character ID
	 */
	private function get_batch_character_data( $character_ids ) {
		$character_data = array();

		// Initialize array for each character
		foreach ( $character_ids as $char_id ) {
			$character_data[ $char_id ] = array(
				'status'  => get_post_status( $char_id ),
				'actors'  => get_post_meta( $char_id, 'lezchars_actor', true ),
				'is_dead' => false,
			);
		}

		// Batch get dead characters using taxonomy query
		$dead_terms = wp_get_object_terms(
			$character_ids,
			'lez_cliches',
			array(
				'fields' => 'all_with_object_id',
				'slug'   => 'dead',
			)
		);

		// Mark dead characters
		if ( ! is_wp_error( $dead_terms ) && ! empty( $dead_terms ) ) {
			foreach ( $dead_terms as $term ) {
				$character_data[ $term->object_id ]['is_dead'] = true;
			}
		}

		return $character_data;
	}

	/**
	 * Check if an actor plays a specific character
	 *
	 * @param int   $actor_id The actor ID
	 * @param mixed $actors_array The actors array from character meta
	 * @return bool True if actor plays the character
	 */
	private function actor_plays_character( $actor_id, $actors_array ) {
		if ( empty( $actors_array ) || ! is_array( $actors_array ) ) {
			return false;
		}

		foreach ( $actors_array as $char_actor ) {
			if ( (int) $char_actor === (int) $actor_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * do_the_math function.
	 *
	 * This will update the following meta keys on save:
	 *  - lezactors_char_count      Number of characters
	 *  - lezactors_dead_count      Number of dead characters
	 *  - lezactors_queer           Are they queer? True or false
	 *
	 * @access public
	 * @param  int  $post_id
	 * @param  bool $force    Force the calculation to run
	 * @return void
	 */
	public function do_the_math( $post_id, $force = false ): void {

		// If force is true, destroy any cached data before recalculation
		if ( $force ) {
			lwtv_plugin()->invalidate_statistics_cache( 'post_type_actors', $post_id );
		}

		// Get all character counts in single pass to avoid redundant queries
		$character_counts = $this->count_all_character_types( $post_id );
		$all_chars        = $character_counts['count'];
		$dead_chars       = $character_counts['dead'];
		$is_queer         = ( new Is_Actor_Queer() )->make( $post_id );

		// Update Meta:
		update_post_meta( $post_id, 'lezactors_char_count', $all_chars );
		update_post_meta( $post_id, 'lezactors_dead_count', $dead_chars );
		update_post_meta( $post_id, 'lezactors_queer', $is_queer );
	}
}
