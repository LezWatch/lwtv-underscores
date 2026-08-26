<?php
/*
 * Find all problems with Show pages.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Characters as Characters_Debugger;
use LWTV\Debugger\Format\Rows;
use LWTV\CPTs\Shows as CPT_Shows;
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
			'issue'    => 'show-no-score',
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
			'issue' => 'show-no-characters',
			'meta'  => 'lezshows_char_count',
		),
		'details'    => array(
			'issue'    => 'show-no-worthit-details',
			'meta'     => 'lezshows_worthit_details',
			'empty_ok' => true,
		),
		/*
		 * Reported, not silently backfilled. This used to be written to 'TBD'
		 * mid-scan, which is why it carried `skip` -- the scan repaired it and
		 * so never had anything to report. The repair now lives in
		 * set_thumb_tbd() behind --fix-it, so the finding has to be visible or
		 * there is nothing for the fixer to act on.
		 *
		 * Worth knowing before deciding this is cosmetic: class-scores.php and
		 * class-of-the-day.php INNER JOIN on lezshows_worthit_rating, so a show
		 * with no row at all drops out of those queries entirely.
		 */
		'thumb'      => array(
			'issue' => 'show-missing-thumb',
			'meta'  => 'lezshows_worthit_rating',
		),
		'realness'   => array(
			'issue'    => 'show-no-realness',
			'meta'     => 'lezshows_realness_rating',
			'empty_ok' => true,
		),
		'quality'    => array(
			'issue'    => 'show-no-quality',
			'meta'     => 'lezshows_quality_rating',
			'empty_ok' => true,
		),
		'screentime' => array(
			'issue'    => 'show-no-screentime',
			'meta'     => 'lezshows_screentime_rating',
			'empty_ok' => true,
		),
		'imdb'       => array(
			'issue' => 'show-no-imdb',
			'meta'  => 'lezshows_imdb',
			'skip'  => true,
		),
		'stations'   => array(
			'issue' => 'show-no-stations',
			'term'  => 'lez_stations',
		),
		'nations'    => array(
			'issue' => 'show-no-country',
			'term'  => 'lez_country',
		),
		'formats'    => array(
			'issue' => 'show-no-format',
			'term'  => 'lez_formats',
		),
		'genres'     => array(
			'issue' => 'show-no-genres',
			'term'  => 'lez_genres',
		),
		// Same story as 'thumb' above: repaired by add_none_trope() under
		// --fix-it, so the finding is now reported rather than skipped.
		'tropes'     => array(
			'issue' => 'show-missing-trope',
			'term'  => 'lez_tropes',
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

		// Findings are per issue; Rows::from_findings() collapses them back to
		// one row per show at the end, so $items is rebuilt rather than appended.
		$findings = array();

		foreach ( $shows as $show_id ) {

			// What we can check for
			$check = array(
				'duplicate' => get_post_field( 'post_name', $show_id ),
			);

			// Build the check array and add findings if needed.
			foreach ( self::ITEMS_TO_CHECK as $item => $check_array ) {
				$empty_okay = ( isset( $check_array['empty_ok'] ) ) ? $check_array['empty_ok'] : false;
				$skip_okay  = ( isset( $check_array['skip'] ) ) ? $check_array['skip'] : false;

				if ( isset( $check_array['meta'] ) ) {
					$check[ $item ] = get_post_meta( $show_id, $check_array['meta'], true );
					if ( ! $empty_okay && ! $skip_okay && empty( $check[ $item ] ) ) {
						$findings[] = Findings::make( $show_id, CPT_Shows::SLUG, $check_array['issue'] );
					}
				} elseif ( isset( $check_array['term'] ) ) {
					$check[ $item ] = get_the_terms( $show_id, $check_array['term'] );
					if ( ( ! $empty_okay && ! $skip_okay ) && ( ! $check[ $item ] || is_wp_error( $check[ $item ] ) ) ) {
						$findings[] = Findings::make( $show_id, CPT_Shows::SLUG, $check_array['issue'] );
					}
				}
			}

			/*
			 * These three return message strings rather than issue types, so the
			 * type is supplied here and the message rides along as a per-post
			 * override. Splitting them into their own types is a follow-up; what
			 * matters now is that each problem is one addressable finding.
			 */
			$findings = array_merge(
				$findings,
				Findings::from_messages( $show_id, CPT_Shows::SLUG, 'show-airdate', $this->check_airdates( $show_id ) ),
				Findings::from_messages( $show_id, CPT_Shows::SLUG, 'show-intersection', self::check_intersection_problems( $show_id ) ),
				Findings::from_messages( $show_id, CPT_Shows::SLUG, 'show-duplicate', self::check_duplicate_shows( $check, $show_id ) )
			);
		}

		$items = Rows::from_findings( $findings );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'show_problems', 'Shows with Issues', count( $items ) );

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

		// Deliberately not short-circuiting: a show can need both.
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
