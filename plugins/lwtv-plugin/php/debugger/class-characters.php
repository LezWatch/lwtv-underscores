<?php
/*
 * Find all problems with Character pages.
 *
 * find_characters_problems()  - find characters with bad or missing data
 *
 * check_disabled_characters() - check that disabled characters' shows are flagged correctly
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;
use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy;
use LWTV\CPTs\Characters as CPT_Characters;

class Characters {

	/**
	 * Transient holding the results of find_byq_problems().
	 */
	const TRANSIENT_BYQ = 'lwtv_debug_byq_problems';

	/**
	 * Transient holding the results of find_characters_problems().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_character_problems';

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
			$the_loop = ( new Queery_Taxonomy() )->get_posts_for_terms( CPT_Characters::SLUG, 'lez_cliches', 'dead' );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$characters = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		// reset items since we recheck off $characters.
		$items = array();

		foreach ( $characters as $char_id ) {
			$problems = array();

			// Check for missing death year meta data
			$death_rows = get_field( 'lezchars_death_year', $char_id );
			$death_year = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();
			if ( empty( $death_year ) ) {
				$problems[] = 'Character marked as dead but missing lezchars_death_year meta data.';
			}

			$shows = get_field( 'lezchars_show_group', $char_id );

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
		lwtv_plugin()->set_transient( self::TRANSIENT_BYQ, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'byq_problems', 'Bury Your Queers Problems', count( $items ) );

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

		/*
		 * A recheck only revisits the posts already flagged, so it cannot be
		 * diffed against the baseline -- everything it did not look at would
		 * read as resolved. Remembered here because $items is reused below.
		 */
		$is_recheck = ! empty( $items );

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
			$the_loop = ( new Post_Type() )->make( CPT_Characters::SLUG );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$characters = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		// Findings are per issue; Rows::from_findings() collapses them back to one
		// row per character at the end, so $items is rebuilt rather than appended.
		$findings = array();

		foreach ( $characters as $char_id ) {

			// What we can check for
			$check = array(
				'cliche' => get_the_terms( $char_id, 'lez_cliches' ),
				'death'  => get_post_meta( $char_id, 'lezchars_last_death', true ),
				'shows'  => get_field( 'lezchars_show_group', $char_id ),
				'actors' => get_field( 'lezchars_actor', $char_id ) ?: array(),
			);

			// No cliché terms at all. Detect only — add_none_cliche() repairs it.
			if ( ! $check['cliche'] || is_wp_error( $check['cliche'] ) ) {
				$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-missing-cliche' );
			}

			if ( has_term( 'dead', 'lez_cliches', $char_id ) && empty( $check['death'] ) ) {
				$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-dead-no-date' );
			}

			if ( ! $check['shows'] || ! is_array( $check['shows'] ) ) {
				$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-no-shows' );
			} else {
				foreach ( $check['shows'] as $each_show ) {
					// Remove the Array.
					if ( is_array( $each_show['show'] ) ) {
						$each_show['show'] = $each_show['show'][0];
					}
					if ( ! isset( $each_show['appears'] ) || ! is_array( $each_show['appears'] ) ) {
						$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-no-years', 'No years on air set for ' . get_the_title( $each_show['show'] ) . '.' );
					}
					if ( ! isset( $each_show['type'] ) || '' === $each_show['type'] ) {
						$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-no-role', 'No role set for ' . get_the_title( $each_show['show'] ) . '.' );
					}
					if ( ! isset( $each_show['show'] ) || '' === $each_show['show'] ) {
						$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-no-show-name' );
					}
				}
			}

			// Okay fine, now we use the NONE actor.
			if ( ! $check['actors'] ) {
				$findings[] = Findings::make( $char_id, CPT_Characters::SLUG, 'char-no-actors' );
			}
		}

		// Diff against the last run before rendering, so each row knows whether
		// its problems are new or long-standing. A recheck is tagged but not
		// diffed, and must not overwrite the baseline -- see tag_only().
		$diff  = $is_recheck
			? Baseline_Store::tag_only( 'character_problems', $findings )
			: Baseline_Store::apply( 'character_problems', $findings );
		$items = Rows::from_findings( $diff['findings'] );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'character_problems', 'Characters with Issues', count( $items ), $diff['summary'] );

		return $items;
	}

	/**
	 * Repair one character's fixable data problems.
	 *
	 * Registered as the fixer for the `chars` check, so it runs once per finding
	 * under `wp lwtv debug chars --fix-it`. Characters flagged for something with
	 * no automated repair (missing death date, no shows, no actors) return false
	 * and are reported as unfixed.
	 *
	 * @param  int  $char_id Character post ID.
	 * @return bool True when a repair was applied.
	 */
	public function fix_character_data( $char_id ): bool {
		return $this->add_none_cliche( (int) $char_id );
	}

	/**
	 * Add the 'none' cliché to a character carrying no cliché terms.
	 *
	 * Looked up by slug on purpose: the term's display name is not 'none', so a
	 * name lookup silently returns false.
	 *
	 * @param  int  $char_id Character post ID.
	 * @return bool True when the term was added.
	 */
	public function add_none_cliche( int $char_id ): bool {
		$cliches = get_the_terms( $char_id, 'lez_cliches' );

		// Already has clichés — nothing to repair.
		if ( $cliches && ! is_wp_error( $cliches ) ) {
			return false;
		}

		$term = get_term_by( 'slug', 'none', 'lez_cliches' );
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}

		return ! is_wp_error( wp_set_object_terms( $char_id, array( $term->term_id ), 'lez_cliches', true ) );
	}

	/**
	 * Check all characters who are disabled.
	 *
	 * Note: There is no 'rechecking' for this, since it's per show.
	 * The recheck check happens earlier.
	 *
	 * @param  int   $show_id post ID of show
	 * @return array|string What's wrong
	 */
	public function check_disabled_characters( $show_id ): array|string {

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
