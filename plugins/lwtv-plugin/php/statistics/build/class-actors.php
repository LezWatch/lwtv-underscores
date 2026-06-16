<?php
/**
 * Build Actors Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Actors {

	/**
	 * Generate roles statistics for an actor
	 *
	 * @param int $actor_id Actor ID
	 * @return array Role statistics
	 */
	public function generate_roles( $actor_id ) {
		// Get the character list meta for this actor
		$char_list = get_post_meta( $actor_id, 'lezactors_char_list', true );

		// If no meta exists, return empty counts
		if ( empty( $char_list ) ) {
			return array(
				array(
					'name'  => __( 'Regular', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Recurring', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Guest', 'lwtv' ),
					'count' => 0,
				),
			);
		}

		// Initialize role counters
		$role_counts = array(
			'regular'   => 0,
			'recurring' => 0,
			'guest'     => 0,
		);

		// Parse through each character and count their roles
		foreach ( $char_list as $character ) {
			if ( isset( $character['shows'] ) && is_array( $character['shows'] ) ) {
				foreach ( $character['shows'] as $show ) {
					if ( isset( $show['type'] ) && in_array( $show['type'], array( 'regular', 'recurring', 'guest' ), true ) ) {
						++$role_counts[ $show['type'] ];
					}
				}
			}
		}

		return array(
			array(
				'name'  => __( 'Regular', 'lwtv' ),
				'count' => $role_counts['regular'],
			),
			array(
				'name'  => __( 'Recurring', 'lwtv' ),
				'count' => $role_counts['recurring'],
			),
			array(
				'name'  => __( 'Guest', 'lwtv' ),
				'count' => $role_counts['guest'],
			),
		);
	}

	/**
	 * Generate dead statistics for an actor
	 *
	 * @param int $actor_id Actor ID
	 * @return array Dead statistics
	 */
	public function generate_dead( $actor_id ) {
		lwtv_plugin()->debug_log( 'statistics', 'Generating death statistics for actor: ' . $actor_id );
		// Get the character list meta for this actor
		$char_list = get_post_meta( $actor_id, 'lezactors_char_list', true );

		// If no meta exists, return empty counts
		if ( empty( $char_list ) ) {
			return array(
				array(
					'name'  => __( 'Alive', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Dead', 'lwtv' ),
					'count' => 0,
				),
			);
		}

		// Initialize counters
		$alive_count = 0;
		$dead_count  = 0;

		// Extract character IDs and check their death status
		foreach ( $char_list as $character ) {
			if ( isset( $character['id'] ) ) {
				$character_id = (int) $character['id'];

				// Check if this character has the 'dead' term in lez_cliches taxonomy
				$has_dead_term = has_term( 'dead', 'lez_cliches', $character_id );

				if ( $has_dead_term ) {
					++$dead_count;
				} else {
					++$alive_count;
				}
			}
		}

		return array(
			array(
				'name'  => __( 'Alive', 'lwtv' ),
				'count' => $alive_count,
			),
			array(
				'name'  => __( 'Dead', 'lwtv' ),
				'count' => $dead_count,
			),
		);
	}
}
