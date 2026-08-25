<?php
/*
 * Find all problems with Show pages.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\Debugger\Characters as Characters_Debugger;
use LWTV\CPTs\Shows\Airdates;
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

	const ITEMS_TO_CHECK = array(
		'score'      => array(
			'message'  => 'Score is 0 or not set - needs characters and/or ratings.',
			'meta'     => 'lezshows_the_score',
			'empty_ok' => true,
		),
		/*
		 * A show with no characters recorded, flagged deliberately and forever.
		 *
		 * These are real: some shows we simply do not have the character data for
		 * yet, and others only ever had background or unnamed queer characters. It
		 * is not a bug to be suppressed -- it is a documentation gap we want to
		 * keep seeing until somebody fills it in, which is precisely what this
		 * report is for. It also matters more than it used to: with the character
		 * score now weighted by screen time, a show with no characters has no
		 * character component at all, so it is scored on three of four parts.
		 *
		 * No `empty_ok`, on purpose. lezshows_char_count comes back as the string
		 * '0' for a genuinely characterless show, and empty( '0' ) is TRUE in PHP,
		 * so the standard check below flags it. That reads like an accident and
		 * is not -- it also catches a missing key, which means the show has never
		 * been calculated, and that is worth surfacing too.
		 */
		'characters' => array(
			'message' => 'No queer characters recorded. Either the data is missing, or the show only had background/unnamed characters - worth confirming which.',
			'meta'    => 'lezshows_char_count',
		),
		'details'    => array(
			'message'  => 'No worthit details.',
			'meta'     => 'lezshows_worthit_details',
			'empty_ok' => true,
		),
		'thumb'      => array(
			'message' => 'No Thumb score.',
			'meta'    => 'lezshows_worthit_rating',
			'skip'    => true,
		),
		'realness'   => array(
			'message'  => 'No realness rating.',
			'meta'     => 'lezshows_realness_rating',
			'empty_ok' => true,
		),
		'quality'    => array(
			'message'  => 'No quality rating.',
			'meta'     => 'lezshows_quality_rating',
			'empty_ok' => true,
		),
		'screentime' => array(
			'message'  => 'No screentime rating.',
			'meta'     => 'lezshows_screentime_rating',
			'empty_ok' => true,
		),
		'imdb'       => array(
			'message' => 'No IMDb ID.',
			'meta'    => 'lezshows_imdb',
			'skip'    => true,
		),
		'stations'   => array(
			'message' => 'No stations.',
			'term'    => 'lez_stations',
		),
		'nations'    => array(
			'message' => 'No country.',
			'term'    => 'lez_country',
		),
		'formats'    => array(
			'message' => 'No format.',
			'term'    => 'lez_formats',
		),
		'genres'     => array(
			'message' => 'No genres.',
			'term'    => 'lez_genres',
		),
		'tropes'     => array(
			'message' => 'No tropes.',
			'term'    => 'lez_tropes',
			'skip'    => true,
		),
	);

	/**
	 * Find Shows with Problems
	 */
	public function find_shows_problems( $items = array() ) {

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

			// What we can check for
			$check = array(
				'duplicate' => get_post_field( 'post_name', $show_id ),
			);

			// Build the check array and add to problems if needed.
			foreach ( self::ITEMS_TO_CHECK as $item => $check_array ) {
				$empty_okay = ( isset( $check_array['empty_ok'] ) ) ? $check_array['empty_ok'] : false;
				$skip_okay  = ( isset( $check_array['skip'] ) ) ? $check_array['skip'] : false;

				if ( isset( $check_array['meta'] ) ) {
					$check[ $item ] = get_post_meta( $show_id, $check_array['meta'], true );
					if ( ! $empty_okay && ! $skip_okay && empty( $check[ $item ] ) ) {
						$problems[] = $check_array['message'];
					}
				} elseif ( isset( $check_array['term'] ) ) {
					$check[ $item ] = get_the_terms( $show_id, $check_array['term'] );
					if ( ( ! $empty_okay && ! $skip_okay ) && ( ! $check[ $item ] || is_wp_error( $check[ $item ] ) ) ) {
						$problems[] = $check_array['message'];
					}
				}
			}

			// Force set a missing rating (aka Thumb Score) to TBD.
			if ( empty( $check['thumb'] ) ) {
				update_post_meta( $show_id, 'lezshows_worthit_rating', 'TBD' );
			}

			// If there are no tropes, add NONE.
			if ( ! $check['tropes'] || is_wp_error( $check['tropes'] ) ) {
				$term = get_term_by( 'name', 'none', 'lez_tropes' );
				if ( $term instanceof \WP_Term ) {
					wp_set_object_terms( $show_id, array( $term->term_id ), 'lez_tropes', true );
				} else {
					$problems[] = 'No tropes set, and the "none" trope is missing from lez_tropes so it could not be added.';
				}
			}

			$problems = array_merge( $problems, $this->check_airdates( $show_id ) );

			$duplicates   = self::check_duplicate_shows( $check, $show_id );
			$intersection = self::check_intersection_problems( $show_id );
			$problems     = array_merge( $problems, $intersection, $duplicates );

			// If we have problems, list them:
			if ( ! empty( $problems ) ) {
				$items[] = array(
					'url'     => get_permalink( $show_id ),
					'id'      => $show_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'show_problems', 'Shows with Issues', count( $items ) );

		return $items;
	}

	/**
	 * Check a show's airdates for sanity.
	 *
	 * Reads through LWTV\CPTs\Shows\Airdates so both the current ACF keys
	 * (lezshows_airdates_start / _finish) and the legacy serialized
	 * lezshows_airdates array are handled. Previously this only read the legacy
	 * key, which meant migrated shows were reported as having no airdates at all
	 * and the end-date checks below never ran.
	 *
	 * @param int $show_id The show ID to check.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function check_airdates( int $show_id ): array {
		$problems = array();
		$airdates = Airdates::get( $show_id );
		$start    = $airdates['start'];
		$finish   = $airdates['finish'];

		if ( '' === $start && '' === $finish ) {
			$problems[] = 'No airdates.';
			return $problems;
		}

		if ( '' === $start ) {
			$problems[] = 'No start date.';
		}

		if ( '' === $finish ) {
			$problems[] = 'No end-date. If the show is on-air, set to CURRENT. TV movies end in the same year.';
			return $problems;
		}

		// 'current' means still airing, so there's nothing to compare against.
		if ( Airdates::is_still_airing( $finish ) ) {
			return $problems;
		}

		// Only compare when both sides are actually years.
		if ( is_numeric( $start ) && is_numeric( $finish ) && (int) $start > (int) $finish ) {
			$problems[] = 'Start date is AFTER end date.';
		}

		return $problems;
	}

	/**
	 * Check if a show has duplicates.
	 */
	public function check_duplicate_shows( array $check, int $show_id ) {
		$problems = array();

		// - Duplicate Show check - shouldn't end in -[NUMBER].
		$permalink_array = explode( '-', $check['duplicate'] );
		$ends_with       = end( $permalink_array );

		// If it ends in a number, we have to check.
		if ( is_numeric( $ends_with ) ) {
			// See if an existing page without the -NUMBER exists (someone could rename themselves with numbers...).
			$possible = get_page_by_path( str_replace( '-' . $ends_with, '', $check['duplicate'] ), OBJECT, 'post_type_shows' );
			if ( is_object( $possible ) && false !== $possible ) {
				// The 90210 Loop
				// Make sure we didn't find ourselves (because some shows are number-named...)
				if ( (int) $possible->ID !== $show_id ) {
					$pos_imdb = get_post_meta( $possible->ID, 'lezshows_imdb', true );
					// Both being empty is not a match. The old isset() check was always
					// true, so every numerically-suffixed show with no IMDb ID matched
					// any same-named show that also had none.
					if ( ! empty( $pos_imdb ) && ! empty( $check['imdb'] ) && $pos_imdb === $check['imdb'] ) {
						$problems[] = 'Likely Dupe - Another Show has this name AND the same IMDb data.';
					}
				}
			}
		}

		return $problems;
	}

	/**
	 * Check shows with intersectionality
	 * Ensure they have matching characters.
	 *
	 * @param int    $show_id - the show ID to check.
	 * @return array $problems  - array of problems. Can be empty.
	 */
	public function check_intersection_problems( int $show_id ): array {

		$intersections = get_the_terms( $show_id, 'lez_intersections' );

		if ( ! $intersections || is_wp_error( $intersections ) ) {
			return array();
		}

		// Only shows tagged with the 'disabled' intersection need a disabled character.
		if ( ! in_array( 'disabled', wp_list_pluck( $intersections, 'slug' ), true ) ) {
			return array();
		}

		static $characters_debugger = null;
		if ( null === $characters_debugger ) {
			$characters_debugger = new Characters_Debugger();
		}

		$problems         = array();
		$disabled_problem = $characters_debugger->check_disabled_characters( $show_id );
		if ( ! empty( $disabled_problem ) ) {
			$problems[] = $disabled_problem;
		}

		return $problems;
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
