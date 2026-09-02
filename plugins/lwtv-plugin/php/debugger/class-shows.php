<?php
/*
 * Find all problems with Show pages.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Debugger\Build\Imdb_Rules;
use LWTV\Debugger\Build\Show_Rules;
use LWTV\Debugger\Collect\Imdb_Collector;
use LWTV\Debugger\Collect\Show_Collector;

class Shows {

	/**
	 * Findings from find_shows_problems().
	 */
	const FINDINGS_PROBLEMS = 'lwtv_debug_show_problems';

	/**
	 * Findings from find_shows_no_imdb().
	 */
	const FINDINGS_IMDB = 'lwtv_debug_show_imdb';

	/**
	 * Find Shows with Problems
	 */
	public function find_shows_problems( $items = array() ) {
		$is_recheck = ! empty( $items );

		$shows = Scan::post_ids( $items, CPT_Shows::SLUG );

		if ( empty( $shows ) ) {
			return array();
		}

		$collector = new Show_Collector();
		$findings  = array();

		foreach ( array_chunk( $shows, Show_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $show ) {
				$findings = array_merge( $findings, Show_Rules::evaluate( $show ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'show_problems',
				'findings' => self::FINDINGS_PROBLEMS,
				'label'    => 'Shows with Issues',
			),
			$findings,
			$is_recheck
		);
	}

	/**
	 * Repair one show's fixable data problems.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when at least one repair was applied.
	 */
	public function fix_show_data( $show_id ): bool {
		$show_id = (int) $show_id;
		$trope   = $this->add_none_trope( $show_id );
		$thumb   = $this->set_thumb_tbd( $show_id );

		return $trope || $thumb;
	}

	/**
	 * Add the 'none' trope to a show carrying no trope terms.
	 *
	 * Looked up by slug on purpose: the term's display name is 'None!', so a
	 * name lookup silently returns false.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when the term was added.
	 */
	public function add_none_trope( int $show_id ): bool {
		$tropes = get_the_terms( $show_id, 'lez_tropes' );

		// Already has tropes -- nothing to repair.
		if ( $tropes && ! is_wp_error( $tropes ) ) {
			return false;
		}

		$term = get_term_by( 'slug', 'none', 'lez_tropes' );
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}

		return ! is_wp_error( wp_set_object_terms( $show_id, array( $term->term_id ), 'lez_tropes', true ) );
	}

	/**
	 * Record that a show genuinely has no findable queer characters.
	 *
	 * This exists to handle shows we know have queers, but they're only background,
	 * OR we just can't get the info because the show is lost to time.
	 *
	 * The show page reads the same flag: it swaps the "Under Construction"
	 * placeholder for a "No Known Characters" panel asking readers who do know
	 * something to get in touch.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when the flag was set now.
	 */
	public function flag_no_characters( int $show_id ): bool {
		if ( ! empty( get_post_meta( $show_id, Show_Rules::META_NO_CHARS, true ) ) ) {
			return false;
		}

		if ( function_exists( 'update_field' ) ) {
			return (bool) update_field( Show_Rules::META_NO_CHARS, 1, $show_id );
		}

		return (bool) update_post_meta( $show_id, Show_Rules::META_NO_CHARS, 1 );
	}

	/**
	 * Write 'TBD' for a show with no Thumb (Worth It) rating.
	 *
	 * Guarded on empty so this no longer rewrites the same value on every scan.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when the rating was written.
	 */
	public function set_thumb_tbd( int $show_id ): bool {
		if ( ! empty( get_post_meta( $show_id, 'lezshows_worthit_rating', true ) ) ) {
			return false;
		}

		return (bool) update_post_meta( $show_id, 'lezshows_worthit_rating', 'TBD' );
	}

	/**
	 * Replace a pasted IMDb URL with the ID inside it.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when the ID was written.
	 */
	public function extract_imdb_from_url( int $show_id ): bool {
		$current   = (string) get_post_meta( $show_id, 'lezshows_imdb', true );
		$extracted = Imdb_Rules::id_from_url( $current, Imdb_Rules::SHOW );

		if ( '' === $extracted ) {
			return false;
		}

		return (bool) update_post_meta( $show_id, 'lezshows_imdb', $extracted );
	}

	/**
	 * Find all shows without IMDb Settings.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_shows_no_imdb( $items = array() ) {
		$is_recheck = ! empty( $items );

		$shows = Scan::post_ids( $items, CPT_Shows::SLUG );

		if ( empty( $shows ) ) {
			return array();
		}

		$collector = new Imdb_Collector();
		$findings  = array();

		foreach ( array_chunk( $shows, Imdb_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( Imdb_Rules::SHOW, $batch ) as $show ) {
				$findings = array_merge( $findings, Imdb_Rules::evaluate( Imdb_Rules::SHOW, $show ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'show_imdb',
				'findings' => self::FINDINGS_IMDB,
				'label'    => 'Shows without IMDb',
			),
			$findings,
			$is_recheck
		);
	}
}
