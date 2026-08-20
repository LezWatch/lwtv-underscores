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
use LWTV\CPTs\Shows\Ways_To_Watch;
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
	 * Transient holding the results of find_shows_bad_url().
	 */
	const TRANSIENT_URL = 'lwtv_debug_show_url';

	const ITEMS_TO_CHECK = array(
		'score'      => array(
			'message'  => 'Score is 0 or not set - needs characters and/or ratings.',
			'meta'     => 'lezshows_the_score',
			'empty_ok' => true,
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
		$airdates = ( new Airdates() )->get( $show_id );
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
			} elseif ( ( new Debug_Tool() )->validate_imdb( $imdb, 'show' ) === false ) {
				// - IMDb IDs should be valid for the space they're in, e.g. "nm"
				// and digits for people (props Jamie).
				$problems[] = 'IMDb ID is invalid (ex: tt12345) -- ' . $imdb;
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

	/**
	 * Find all shows with bad URLs for Ways to Watch
	 *
	 * @param array $items - array of items to check. Can be empty.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_shows_bad_url( $items = array() ) {

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

		// Instantiated once, not per show: the constructor registers admin
		// filters, and doing that a few thousand times is pure waste. (This
		// migration call doesn't belong in a scanner at all -- see
		// DEBUGGER-REVIEW.md section 8.4.)
		$ways_to_watch_migrator = new Ways_To_Watch();

		foreach ( $shows as $show_id ) {

			$problems = array();

			// Check the Ways to Watch - this updates us to the new method.
			$ways_to_watch_migrator->migrate_ways_to_watch( $show_id );

			$wtw_rows      = get_field( 'lezshows_waystowatch', $show_id );
			$ways_to_watch = is_array( $wtw_rows ) ? array_filter( array_column( $wtw_rows, 'url' ) ) : array();

			if ( empty( $ways_to_watch ) ) {
				continue;
			}

			// Parse each URL.
			foreach ( $ways_to_watch as $url ) {
				$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$http_code = wp_remote_retrieve_response_code( $response );

					if ( '200' === $http_code ) {
						// If it's a 200, we're good, skip the rest.
						continue;
					} elseif ( empty( $http_code ) ) {
						// If it's empty, we got a bad URL.
						$problems[] = 'URL does not exist. Remove it from the page. -- ' . $url;
					} else {
						// Check the codes.
						switch ( $http_code ) {
							case '301':
							case '308':
								$problems[] = 'URL has been moved. Update the page so it doesn\'t have to redirect. -- ' . $url;
								break;
							case '400':
							case '403':
								$problems[] = 'URL cannot be accessed. We might be blocked from automated testing. Check to make sure it exists. -- ' . $url;
								break;
							case '404':
							case '410':
							case '418':
								$problems[] = 'URL does not exist. Remove it from the page. -- ' . $url;
								break;
							default:
								$problems[] = 'Something is up with this URL -- ' . $url;
								break;
						}
					}
				} else {
					$problems[] = 'URL is un-retrievable. Check if it really exists. -- ' . $url;
				}
			}

			// If we have no problems, we're good!
			if ( empty( $problems ) ) {
				continue;
			}

			$items[] = array(
				'url'     => get_permalink( $show_id ),
				'id'      => $show_id,
				'problem' => implode( '</br>', $problems ),
			);
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_URL, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'show_url', 'Shows with bad Ways to Watch', count( $items ) );

		return $items;
	}
}
