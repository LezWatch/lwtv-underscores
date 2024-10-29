<?php
/*
 * Find all problems with Show pages.
 */

namespace LWTV\Debugger;

use LWTV\CPTs\Shows\Ways_To_Watch;

class Shows {

	const ITEMS_TO_CHECK = array(
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
		'airdates'   => array(
			'message' => 'No airdates.',
			'meta'    => 'lezshows_airdates',
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
			$the_loop = lwtv_plugin()->queery_post_type( 'post_type_shows' );

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

			// Force set a missing thumb to TBD.
			if ( empty( $check['thumb'] ) ) {
				update_post_meta( $show_id, 'lezshows_worthit_rating', 'TBD' );
			}

			// If there are no tropes, add NONE.
			if ( ! $check['tropes'] || is_wp_error( $check['tropes'] ) ) {
				$term = get_term_by( 'name', 'none', 'lez_tropes' );
				wp_set_object_terms( $show_id, $term->ID, 'lez_tropes', true );
			}

			// Double check there is an END airdate.
			if ( array( $check['airdates'] ) ) {
				$start  = $check['airdates']['start'];
				$finish = $check['airdates']['finish'];

				if ( empty( $finish ) ) {
					$problems[] = 'No end-date. If the show is on-air, set to CURRENT. TV movies end in the same year.';
				} elseif ( $start > $finish ) {
					$problems[] = 'Start date is AFTER end date.';
				}
			}

			$duplicates   = self::check_duplicate_shows( $check, $show_id );
			$intersection = self::check_intersection_problems( $show_id, $check['tropes'] );
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
		set_transient( 'lwtv_debug_show_problems', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                  = get_option( 'lwtv_debugger_status' );
		$option['show_problems'] = array(
			'name'  => 'Shows with Issues',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp']     = time();
		update_option( 'lwtv_debugger_status', $option );

		return $items;
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
				if ( $possible->ID !== $show_id ) {
					$pos_imdb = get_post_meta( $possible->ID, 'lezshows_imdb', true );
					if ( isset( $pos_imdb ) && $pos_imdb === $check['imdb'] ) {
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
	 * @param int   $show_id - the show ID to check.
	 * @return array $items  - array of problems. Can be empty.
	 */
	public function check_intersection_problems( int $show_id ) {

		$intersections = get_post_meta( $show_id, 'lez_intersections', true );

		if ( ! $intersections || is_wp_error( $intersections ) ) {
			return array();
		}

		$items    = array();
		$problems = lwtv_plugin()->check_disabled_characters( $show_id );

		// if there are problems, we put them in items.
		if ( ! empty( $problems ) ) {
			$items[] = array(
				'url'     => get_permalink( $show_id ),
				'id'      => $show_id,
				'problem' => implode( '</br>', $problems ),
			);
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
			$the_loop = lwtv_plugin()->queery_post_type( 'post_type_shows' );

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
				// Check for IMDb existing at all, unless it's a webseries
				if ( ! has_term( 'web-series', 'lez_formats', $show_id ) ) {
					$problems[] = 'IMDb ID is not set.';
				}
			} elseif ( lwtv_plugin()->validate_imdb( $imdb, 'show' ) === false ) {
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
		set_transient( 'lwtv_debug_show_imdb', $items, WEEK_IN_SECONDS );

		// Update Options
		$option              = get_option( 'lwtv_debugger_status' );
		$option['show_imdb'] = array(
			'name'  => 'Shows without IMDb',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp'] = time();
		update_option( 'lwtv_debugger_status', $option );

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
			$the_loop = lwtv_plugin()->queery_post_type( 'post_type_shows' );

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

			// Check the Ways to Watch - this updates us to the new method.
			( new Ways_To_Watch() )->migrate_ways_to_watch( $show_id );

			$ways_to_watch = get_post_meta( $show_id, 'lezshows_waystowatch', true );

			if ( empty( $ways_to_watch ) ) {
				return;
			}

			// Parse each URL.
			foreach ( $ways_to_watch as $url ) {
				$response = wp_remote_get( $url );

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
				return;
			}

			$items[] = array(
				'url'     => get_permalink( $show_id ),
				'id'      => $show_id,
				'problem' => implode( '</br>', $problems ),
			);
		}

		// Save Transient
		set_transient( 'lwtv_debug_show_url', $items, WEEK_IN_SECONDS );

		// Update Options
		$option              = get_option( 'lwtv_debugger_status' );
		$option['show_imdb'] = array(
			'name'  => 'Shows with bad Ways to Watch',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp'] = time();
		update_option( 'lwtv_debugger_status', $option );

		return $items;
	}
}
