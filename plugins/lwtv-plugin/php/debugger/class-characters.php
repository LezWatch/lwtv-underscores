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
use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy;
use LWTV\CPTs\Characters as CPT_Characters;

class Characters {

	/**
	 * Findings from find_byq_problems().
	 */
	const FINDINGS_BYQ = 'lwtv_debug_byq_problems';

	/**
	 * Findings from find_characters_problems().
	 */
	const FINDINGS_PROBLEMS = 'lwtv_debug_character_problems';

	/**
	 * Find Characters with Problems regarding BYQ
	 *
	 * @param  array $items Array of characters to check (can be empty)
	 * @return array Characters with issues
	 */
	public function find_byq_problems( $items = array() ): array {
		$is_recheck = ! empty( $items );

		/*
		 * Only dead characters, so the full-scan source is a taxonomy query
		 * rather than a whole post type. 'ids' because this only ever wanted them.
		 */
		$characters = Scan::targets(
			$items,
			static function () {
				$loop = ( new Queery_Taxonomy() )->get_posts_for_terms( CPT_Characters::SLUG, 'lez_cliches', 'dead', 'IN', 'ids' );

				return ( is_object( $loop ) && ! empty( $loop->posts ) ) ? $loop->posts : array();
			}
		);

		if ( empty( $characters ) ) {
			return array();
		}

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

		return Scan::finish(
			array(
				'scope'    => 'byq_problems',
				'findings' => self::FINDINGS_BYQ,
				'label'    => 'Bury Your Queers Problems',
			),
			$findings,
			$is_recheck
		);
	}

	/**
	 * Find Characters with Problems
	 *
	 * @param  array $items Array of characters to check (can be empty)
	 * @return array Characters with issues
	 */
	public function find_characters_problems( $items = array() ): array {
		$is_recheck = ! empty( $items );

		$characters = Scan::post_ids( $items, CPT_Characters::SLUG );

		if ( empty( $characters ) ) {
			return array();
		}

		$collector = new Character_Collector();
		$findings  = array();

		foreach ( array_chunk( $characters, Character_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $character ) {
				$findings = array_merge( $findings, Character_Rules::evaluate( $character ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'character_problems',
				'findings' => self::FINDINGS_PROBLEMS,
				'label'    => 'Characters with Issues',
			),
			$findings,
			$is_recheck
		);
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
