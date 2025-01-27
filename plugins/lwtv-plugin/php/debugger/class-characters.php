<?php
/*
 * Find all problems with Character pages.
 *
 * find_characters_problems()  - find characters with bad or missing data
 *
 * check_disabled_characters() - check that disabled characters' shows are flagged correctly
 */

namespace LWTV\Debugger;

use LWTV\Queeries\Post_Type;
use LWTV\Queeries\Taxonomy as Queery_Taxonomy;

class Characters {

	/**
	 * Find Characters with Problems regarding BYQ
	 *
	 * @param  array $items Array of characters to check (can be empty)
	 * @return array Characters with issues
	 */
	public function find_byq_problems( $items = array() ): array {
		// The array we will be checking.
		$characters = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the characters from items!
			foreach ( $items as $character_item ) {
				if ( get_post_status( $character_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$characters[] = $character_item['id'];
				}
			}
		} else {
			$the_loop = ( new Queery_Taxonomy() )->make( 'post_type_characters', 'lez_cliches', 'slug', 'dead' );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$characters = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return false;
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		// reset items since we recheck off $characters.
		$items = array();

		foreach ( $characters as $char_id ) {
			$problems = array();

			$shows = get_post_meta( $char_id, 'lezchars_show_group', true );

			// If there are no shows, skip.
			if ( empty( $shows ) ) {
				continue;
			}

			// Get all the shows the character is on. If ANY of them are missing the dead-queers trope we have a problem
			foreach ( $shows as $each_show ) {
				// Remove the Array.
				if ( is_array( $each_show['show'] ) ) {
					$each_show['show'] = $each_show['show'][0];
				}

				$show_id = $each_show['show'];

				if ( ! has_term( 'dead-queers', 'lez_tropes', $show_id ) ) {
					$problems[] = 'There is no BYQ trope on the show <a href="/wp-admin/post.php?post=' . $each_show['show'] . '&action=edit">' . get_the_title( $each_show['show'] ) . '</a> (edit).';
				}
			}

			// Check if we have any problems, and the character isn't marked dead on at least ONE show.
			if ( ! empty( $problems ) && ( count( $shows ) - count( $problems ) ) !== 1 ) {
				$items[] = array(
					'url'     => get_permalink( $char_id ),
					'id'      => $char_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		set_transient( 'lwtv_debug_byq_problems', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                 = get_option( 'lwtv_debugger_status' );
		$option['byq_problems'] = array(
			'name'  => 'Bury Your Queers Problems',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp']    = time();
		update_option( 'lwtv_debugger_status', $option );

		return $items;
	}

	/**
	 * Find Characters with Problems
	 *
	 * @param  array $items Array of characters to check (can be empty)
	 * @return array Characters with issues
	 */
	public function find_characters_problems( $items = array() ): array {

		// The array we will be checking.
		$characters = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the characters from items!
			foreach ( $items as $character_item ) {
				if ( get_post_status( $character_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$characters[] = $character_item['id'];
				}
			}
		} else {
			// Get all the characters
			$the_loop = ( new Post_Type() )->make( 'post_type_characters' );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$characters = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return false;
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		// reset items since we recheck off $characters.
		$items = array();

		foreach ( $characters as $char_id ) {
			$problems = array();

			// What we can check for
			$check = array(
				'cliche' => get_the_terms( $char_id, 'lez_cliches' ),
				'death'  => get_post_meta( $char_id, 'lezchars_last_death', true ),
				'shows'  => get_post_meta( $char_id, 'lezchars_show_group', true ),
				'actors' => get_post_meta( $char_id, 'lezchars_actor', true ),
			);

			// If there's no Cliche, we add 'None'
			if ( ! $check['cliche'] || is_wp_error( $check['cliche'] ) ) {
				$term = get_term_by( 'name', 'none', 'lez_cliches' );
				wp_set_object_terms( $char_id, $term->ID, 'lez_cliches', true );
			}

			if ( has_term( 'dead', 'lez_cliches' ) && empty( $check['death'] ) ) {
				$problems[] = 'Dead but missing date.';
			}

			if ( ! $check['shows'] || ! is_array( $check['shows'] ) ) {
				$problems[] = 'No shows listed.';
			} else {
				foreach ( $check['shows'] as $each_show ) {
					// Remove the Array.
					if ( is_array( $each_show['show'] ) ) {
						$each_show['show'] = $each_show['show'][0];
					}
					if ( ! isset( $each_show['appears'] ) || ! is_array( $each_show['appears'] ) ) {
						$problems[] = 'No years on air set for ' . get_the_title( $each_show['show'] ) . '.';
					}
					if ( ! isset( $each_show['type'] ) || '' === $each_show['type'] ) {
						$problems[] = 'No role set for' . get_the_title( $each_show['show'] ) . '.';
					}
					if ( ! isset( $each_show['show'] ) || '' === $each_show['show'] ) {
						$problems[] = 'No show name set.';
					}
				}
			}

			// Okay fine, now we use the NONE actor.
			if ( ! $check['actors'] ) {
				$problems[] = 'No actors listed.';
			}

			// If we have problems, list them:
			if ( ! empty( $problems ) ) {
				$items[] = array(
					'url'     => get_permalink( $char_id ),
					'id'      => $char_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		set_transient( 'lwtv_debug_character_problems', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                       = get_option( 'lwtv_debugger_status' );
		$option['character_problems'] = array(
			'name'  => 'Characters with Issues',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp']          = time();
		update_option( 'lwtv_debugger_status', $option );

		return $items;
	}

	/**
	 * Check all characters who are disabled.
	 *
	 * Note: There is no 'rechecking' for this, since it's per show.
	 * The recheck check happens earlier.
	 *
	 * @param  int   $show_id post ID of show
	 * @return string What's wrong
	 */
	public function check_disabled_characters( $show_id ): array {

		// The array we will be checking.
		$characters = lwtv_plugin()->get_characters_list( $show_id, 'query' );

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		// Default has disabled
		$has_disabled = false;

		foreach ( $characters as $character ) {
			// If someone has disabled, we're good.
			if ( has_term( 'disabled', 'lez_cliches', $character ) ) {
				$has_disabled = true;
				break;
			}
		}

		$problems = ( ! $has_disabled ) ? 'No character on this show is tagged as disabled. Please review.' : '';

		return $problems;
	}
}
