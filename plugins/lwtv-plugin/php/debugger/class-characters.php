<?php
/*
 * Find all problems with Character pages.
 *
 * find_characters_problems()  - find characters with bad or missing data
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Byq_Rules;
use LWTV\Debugger\Build\Character_Rules;
use LWTV\Debugger\Collect\Byq_Collector;
use LWTV\Debugger\Collect\Character_Collector;
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

		// A recheck only revisits the posts already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
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
			// 'ids' — this scan only ever plucked the IDs out again.
			$the_loop = ( new Queery_Taxonomy() )->get_posts_for_terms( CPT_Characters::SLUG, 'lez_cliches', 'dead', 'IN', 'ids' );

			if ( is_object( $the_loop ) && ! empty( $the_loop->posts ) ) {
				$characters = array_map( 'intval', $the_loop->posts );
			}
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		/*
		 * Collect, then evaluate. Build\Byq_Rules holds the two rules and the gate
		 * that decides when a missing trope is worth reporting -- pure, and tested,
		 * which it needed to be: that gate had a bug in it (1.9c) for as long as it
		 * was interleaved with the ACF reads.
		 */
		$collector = new Byq_Collector();
		$findings  = array();

		foreach ( array_chunk( $characters, Byq_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $character ) {
				$findings = array_merge( $findings, Byq_Rules::evaluate( $character ) );
			}
		}

		$diff  = $is_recheck
			? Baseline_Store::tag_only( 'byq_problems', $findings )
			: Baseline_Store::apply( 'byq_problems', $findings );
		$items = Rows::from_findings( $diff['findings'] );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_BYQ, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'byq_problems', 'Bury Your Queers Problems', count( $items ), $diff['summary'] );

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
			$characters = ( new Post_Type() )->get_ids( CPT_Characters::SLUG );
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		/*
		 * Collect, then evaluate. The rules are pure and live in
		 * Build\Character_Rules; everything that reads meta, terms or ACF is in
		 * Collect\Character_Collector, which batches so a full run is a handful
		 * of term queries rather than one per character.
		 */
		$collector = new Character_Collector();
		$findings  = array();

		foreach ( array_chunk( $characters, Character_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $character ) {
				$findings = array_merge( $findings, Character_Rules::evaluate( $character ) );
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
}
