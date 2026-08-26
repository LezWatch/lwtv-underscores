<?php
/*
 * Find all problems with Show pages.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\Debugger\Build\Show_Rules;
use LWTV\Debugger\Collect\Show_Collector;
use LWTV\Debugger\Format\Rows;
use LWTV\_Helpers\Imdb_Canonical;
use LWTV\Queeries\Post_Type;

class Shows {

	/**
	 * Transient holding the results of find_shows_problems().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_show_problems';

	/**
	 * Transient holding the results of find_shows_no_imdb().
	 */
	const TRANSIENT_IMDB = 'lwtv_debug_show_imdb';

	/**
	 * Find Shows with Problems
	 */
	public function find_shows_problems( $items = array() ) {

		// The array we will be checking.
		$shows = array();

		/*
		 * A recheck only revisits the posts already flagged, so it cannot be
		 * diffed against the baseline -- everything it did not look at would
		 * read as resolved. Remembered here because $items is reused below.
		 */
		$is_recheck = ! empty( $items );

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the shows from items!
			foreach ( $items as $show_item ) {
				if ( get_post_status( $show_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$shows[] = $show_item['id'];
				}
			}
		} else {
			// Get all the shows
			$the_loop = ( new Post_Type() )->make( 'post_type_shows' );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$shows = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow shows is totally empty...
		if ( empty( $shows ) ) {
			return false;
		}

		// Make sure we don't have dupes.
		$shows = array_unique( $shows );

		/*
		 * Collect, then evaluate. The rules are pure and live in
		 * Build\Show_Rules; everything that touches the database is in
		 * Collect\Show_Collector. Batched because the collector fetches terms for
		 * a whole batch in one query rather than five per show.
		 */
		$collector = new Show_Collector();
		$findings  = array();

		foreach ( array_chunk( $shows, Show_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $show ) {
				$findings = array_merge( $findings, Show_Rules::evaluate( $show ) );
			}
		}

		// Diff against the last run before rendering, so each row knows whether
		// its problems are new or long-standing. A recheck is tagged but not
		// diffed, and must not overwrite the baseline -- see tag_only().
		$diff  = $is_recheck
			? Baseline_Store::tag_only( 'show_problems', $findings )
			: Baseline_Store::apply( 'show_problems', $findings );
		$items = Rows::from_findings( $diff['findings'] );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'show_problems', 'Shows with Issues', count( $items ), $diff['summary'] );

		return $items;
	}

	/**
	 * Repair one show's fixable data problems.
	 *
	 * Registered as the fixer for the `shows` check, so it runs once per finding
	 * under `wp lwtv debug shows --fix-it`. A show flagged only for something
	 * with no automated repair (no characters, a bad airdate, a duplicate slug)
	 * returns false and is reported as unfixed.
	 *
	 * @param  int  $show_id Show post ID.
	 * @return bool True when at least one repair was applied.
	 */
	public function fix_show_data( $show_id ): bool {
		$show_id = (int) $show_id;

		/*
		 * Deliberately not short-circuiting: a show can need both. And
		 * deliberately excluding flag_no_characters(), which is a judgement call
		 * registered as `manual` -- this dispatcher is the bulk path.
		 */
		$trope = $this->add_none_trope( $show_id );
		$thumb = $this->set_thumb_tbd( $show_id );

		return $trope || $thumb;
	}

	/**
	 * Add the 'none' trope to a show carrying no trope terms.
	 *
	 * Looked up by slug on purpose: the term's display name is 'None!', so a
	 * name lookup silently returns false -- which is how this repair spent a
	 * long time doing nothing while reporting that the term was missing.
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
	 * Not a repair in the usual sense: it does not fill the gap, it states that
	 * there is nothing to fill it with. That is why the issue is registered as
	 * `manual` -- a bulk run must not decide this for every characterless show.
	 *
	 * Written through update_field() rather than update_post_meta() because
	 * lezshows_no_chars is an ACF true_false field, and ACF also stores the
	 * companion `_lezshows_no_chars` field-key row that the editor UI reads.
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
	 * Find all shows without IMDb Settings.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_shows_no_imdb( $items = array() ) {

		// The array we will be checking.
		$shows = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the shows from items!
			foreach ( $items as $show_item ) {
				if ( get_post_status( $show_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$shows[] = $show_item['id'];
				}
			}
		} else {
			// Get all the shows
			$the_loop = ( new Post_Type() )->make( 'post_type_shows' );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$shows = wp_list_pluck( $the_loop->posts, 'ID' );
				wp_reset_query();
			}
		}

		// If somehow shows is totally empty...
		if ( empty( $shows ) ) {
			return false;
		}

		// Make sure we don't have dupes.
		$shows = array_unique( $shows );

		// reset items since we recheck off $shows.
		$items = array();

		foreach ( $shows as $show_id ) {

			$problems = array();

			$imdb = get_post_meta( $show_id, 'lezshows_imdb', true );

			if ( empty( $imdb ) ) {
				// Check for IMDb existing at all, unless it's a web-series.
				if ( ! has_term( 'web-series', 'lez_formats', $show_id ) ) {
					$problems[] = 'IMDb ID is not set.';
				}
			} elseif ( Debug_Tool::validate_imdb( $imdb, 'show' ) === false ) {
				// - IMDb IDs should be valid for the space they're in, e.g. "nm"
				// and digits for people (props Jamie).
				$problems[] = 'IMDb ID is invalid (ex: tt12345) -- ' . $imdb;
			} elseif ( ! get_post_meta( $show_id, 'lezshows_tvmaze_ignore', true ) ) {
				// IMDb reassigns title IDs and leaves the old one redirecting, so
				// a stale ID still opens the right page in a browser while
				// breaking every exact-match API lookup keyed on it. Nothing about
				// the value looks wrong, which is why format validation above
				// cannot catch it.
				//
				// Compared fresh each run rather than trusting a stored verdict:
				// lezshows_imdb_canonical holds what TVMaze last told us, and if
				// an editor has since corrected the ID to match, the problem
				// clears itself without waiting for a re-check.
				//
				// An empty canonical means "no disagreement recorded", which
				// covers both verified-clean and never-checked. Deliberately
				// silent either way -- a debugger row implying "verified" for a
				// show nobody has looked at would be worse than no row at all.
				$canonical = get_post_meta( $show_id, 'lezshows_imdb_canonical', true );

				if ( Imdb_Canonical::is_stale( $imdb, $canonical ) ) {
					$problems[] = 'IMDb ID disagrees with TVMaze -- ours is ' . $imdb
						. ', TVMaze has ' . $canonical
						. '. Ours has probably gone stale; check which is right, then correct it or '
						. 'tick "Ignore TVMaze Match" on the show.';
				}
			}

			// If we added any problems, loop and add.
			if ( ! empty( $problems ) ) {
				$items[] = array(
					'url'     => get_permalink( $show_id ),
					'id'      => $show_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_IMDB, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'show_imdb', 'Shows without IMDb', count( $items ) );

		return $items;
	}
}
