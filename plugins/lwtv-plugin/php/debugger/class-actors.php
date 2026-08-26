<?php
/*
 * Find all problems with Actor pages.
 *
 * find_actors_problems()   - find actors with weird/bad data
 * find_actors_incomplete() - find actors without bio or photo
 * find_actors_no_imdb()    - find actors without IMDb / bad IMDb data
 *
 * check_actors_wikidata()  - Validate our data vs WikiData.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\_Helpers\Imdb_Canonical;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;

class Actors {

	/**
	 * Transient holding the results of find_actors_problems().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_actor_problems';

	/**
	 * Transient holding the results of find_actors_incomplete().
	 */
	const TRANSIENT_EMPTY = 'lwtv_debug_actor_empty';

	/**
	 * Transient holding the results of find_actors_no_imdb().
	 */
	const TRANSIENT_IMDB = 'lwtv_debug_actor_imdb';

	/**
	 * Constructor — wire up action hooks.
	 */
	public function __construct() {
		add_action( 'lwtv_shadow_tax_sync_failed', array( $this, 'flag_shadow_sync_failure' ), 10, 2 );
	}

	/**
	 * Add a shadow taxonomy sync failure to the actor problems list.
	 *
	 * Called when sync_actors() has retried 3+ times without success.
	 * Appends the actor to the lwtv_debug_actor_problems transient so it
	 * surfaces in `wp lwtv debug actors` and the admin debugger view.
	 *
	 * @param int $actor_id  Post ID of the actor.
	 * @param int $term_id   Shadow taxonomy term ID that failed to attach.
	 * @return void
	 */
	public function flag_shadow_sync_failure( int $actor_id, int $term_id ): void {
		$items = lwtv_plugin()->get_transient( self::TRANSIENT_PROBLEMS );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		// Avoid duplicates — check if this actor is already flagged.
		foreach ( $items as $item ) {
			if ( isset( $item['id'] ) && (int) $item['id'] === $actor_id ) {
				return;
			}
		}

		// Built through the same pipeline as a scan finding so this row carries
		// `issues` too -- otherwise a hook-appended row would look, to the CLI
		// fixer, like a stale pre-reshape payload.
		$items = array_merge(
			$items,
			Rows::from_findings(
				array(
					Findings::make(
						$actor_id,
						CPT_Actors::SLUG,
						'actor-shadow-sync-failed',
						sprintf( 'Shadow taxonomy sync failed repeatedly (term %d). Run: wp lwtv shadow actors', $term_id ),
						array( 'term_id' => $term_id )
					),
				)
			)
		);

		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'actor_problems', 'Actors with Issues', count( $items ) );
	}

	/**
	 * Find Actors with problems.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_actors_problems( $items = array() ): array {

		// The array we will be checking.
		$actors = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the actors from items!
			foreach ( $items as $actor_item ) {
				if ( get_post_status( $actor_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$actors[] = $actor_item['id'];
				}
			}
		} else {
			// Get all the actors
			$the_loop = ( new Post_Type() )->make( 'post_type_actors' );

			// Add ONLY the IDs to the array.
			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$actors = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow actors is totally empty...
		if ( empty( $actors ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$actors = array_unique( $actors );

		// Findings are per issue; Rows::from_findings() collapses them back to one
		// row per actor at the end, so $items is rebuilt rather than appended.
		$findings = array();

		foreach ( $actors as $actor_id ) {
			/*
			 * Collected and never reported -- and that predates the typed
			 * findings, so the conversion did not drop it. The one warning below
			 * is a judgement call nobody has made yet (plenty of people have no
			 * recorded date of birth), so it is neither surfaced nor deleted.
			 * Give it an issue type when it is decided either way.
			 */
			$warnings = array();

			$meta  = get_post_meta( $actor_id );
			$check = array(
				'chars' => $meta['lezactors_char_count'][0] ?? '',
				'birth' => $meta['lezactors_birth'][0] ?? '',
				'death' => $meta['lezactors_death'][0] ?? '',
				'wiki'  => $meta['lezactors_wikipedia'][0] ?? '',
				'imdb'  => $meta['lezactors_imdb'][0] ?? '',
				'insta' => $meta['lezactors_instagram'][0] ?? '',
				'twits' => $meta['lezactors_twitter'][0] ?? '',
				'home'  => $meta['lezactors_homepage'][0] ?? '',
				'dupes' => get_post_field( 'post_name', $actor_id ),
			);

			// - Confirm there are characters listed.
			if ( ! $check['chars'] || empty( $check['chars'] ) ) {
				$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-no-characters' );
			}

			// - Warn if there is a death and no birth (nb: this may not be a good idea, some people have no DoB!)
			if ( ! empty( $check['death'] ) ) {
				if ( empty( $check['birth'] ) ) {
					$warnings[] = 'Death date set without date of birth.';
				}
			}

			// - Wikipedia links should point to Wikipedia: "https://[language].wikipedia.org/" (props Jamie)
			if ( ! empty( $check['wiki'] ) && strpos( $check['wiki'], 'wikipedia.org/' ) === false ) {
				$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-wikipedia-invalid' );
			}

			// - Instagram and Twitter usernames should follow whatever the
			//   actual restrictions on those are (props Jamie)
			// - If Instagram or Twitter usernames are the same format as IMDb IDs,
			//   that's suspicious (props Jamie)
			if ( ! empty( $check['insta'] ) ) {
				// Limit - 30 symbols. Username must contains only letters, numbers, periods and underscores.
				if ( Debug_Tool::sanitize_social( $check['insta'], 'instagram' ) !== $check['insta'] ) {
					$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-instagram-invalid', 'Instagram ID is invalid -- ' . esc_html( $check['insta'] ), array( 'value' => $check['insta'] ) );
				} elseif ( self::looks_like_actor_imdb( $check['insta'] ) ) {
					$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-instagram-is-imdb', 'Instagram ID is an IMDb ID: ' . esc_html( $check['insta'] ), array( 'value' => $check['insta'] ) );
				}
			}
			if ( ! empty( $check['twits'] ) ) {
				if ( Debug_Tool::sanitize_social( $check['twits'], 'twitter' ) !== $check['twits'] ) {
					$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-twitter-invalid', 'Twitter ID is invalid -- ' . esc_html( $check['twits'] ), array( 'value' => $check['twits'] ) );
				} elseif ( self::looks_like_actor_imdb( $check['twits'] ) ) {
					$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-twitter-is-imdb', 'Twitter ID is an IMDb ID: ' . esc_html( $check['twits'] ), array( 'value' => $check['twits'] ) );
				}
			}

			// - "Website" links should *not* point to Wikipedia, since that
			// would make them Wikipedia links (props Jamie)
			//
			// The first two branches used to be repaired silently, mid-scan, and
			// recorded no problem at all -- so these two findings are new to the
			// report even though the situation is not new. fix_actor_data()
			// performs them under --fix-it.
			if ( ! empty( $check['home'] ) ) {
				if ( strpos( $check['home'], 'wikipedia.org/' ) !== false ) {
					if ( empty( $check['wiki'] ) ) {
						$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-homepage-is-wikipedia' );
					} elseif ( $check['wiki'] === $check['home'] ) {
						$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-homepage-dupe-wiki' );
					} else {
						// Two different Wikipedia URLs. Needs a human to pick.
						$findings[] = Findings::make( $actor_id, CPT_Actors::SLUG, 'actor-homepage-wikipedia', 'Homepage points to Wikipedia - ' . sanitize_url( $check['home'] ), array( 'homepage' => $check['home'] ) );
					}
				}
			}
		}

		$items = Rows::from_findings( $findings );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'actor_problems', 'Actors with Issues', count( $items ) );

		return $items;
	}

	/**
	 * Does a social handle actually look like an actor's IMDb ID?
	 *
	 * Debug_Tool::validate_imdb() accepts 'nm' followed by any number of digits,
	 * which is right for validating the IMDb field itself but too loose here:
	 * 'nm2020' is a perfectly plausible Instagram handle, and under --fix-it a
	 * false positive costs someone their social link. Real IMDb person IDs run
	 * 7-8 digits, so require at least 6 before calling it misfiled.
	 *
	 * Kept local on purpose -- tightening the shared helper would change how the
	 * genuine IMDb fields on actors and shows are validated too.
	 *
	 * @param  string $value Social handle as stored.
	 * @return bool   True when the value is an IMDb ID in the wrong field.
	 */
	private static function looks_like_actor_imdb( $value ): bool {
		if ( ! Debug_Tool::validate_imdb( $value, 'actor' ) ) {
			return false;
		}

		return strlen( substr( (string) $value, 2 ) ) >= 6;
	}

	/**
	 * Repair one actor's fixable data problems.
	 *
	 * Registered as the fixer for the `actors` check, so it runs once per finding
	 * under `wp lwtv debug actors --fix-it`. An actor flagged only for something
	 * with no automated repair (no characters, an invalid handle, two competing
	 * Wikipedia URLs) returns false and is reported as unfixed.
	 *
	 * @param  int  $actor_id Actor post ID.
	 * @return bool True when at least one repair was applied.
	 */
	public function fix_actor_data( $actor_id ): bool {
		$actor_id = (int) $actor_id;

		// Deliberately not short-circuiting: an actor can need all three.
		$insta = $this->remove_imdb_from_social( $actor_id, 'instagram' );
		$twits = $this->remove_imdb_from_social( $actor_id, 'twitter' );
		$home  = $this->fix_homepage_wikipedia( $actor_id );

		return $insta || $twits || $home;
	}

	/**
	 * Registry entry point for the Instagram case.
	 *
	 * Every repair in Issue_Registry takes one post ID, so the two-argument
	 * worker gets a thin wrapper per field rather than the registry carrying
	 * bound arguments.
	 *
	 * @param  int  $actor_id Actor post ID.
	 * @return bool True when the meta was deleted.
	 */
	public function remove_imdb_from_instagram( int $actor_id ): bool {
		return $this->remove_imdb_from_social( $actor_id, 'instagram' );
	}

	/**
	 * Registry entry point for the Twitter case.
	 *
	 * @param  int  $actor_id Actor post ID.
	 * @return bool True when the meta was deleted.
	 */
	public function remove_imdb_from_twitter( int $actor_id ): bool {
		return $this->remove_imdb_from_social( $actor_id, 'twitter' );
	}

	/**
	 * Clear a social field that is holding an IMDb ID.
	 *
	 * Destructive by decision: the value is removed rather than moved into
	 * lezactors_imdb. An IMDb ID recovered from a social field is a guess about
	 * intent, and a wrong guess writes a wrong ID onto an actor -- worse than a
	 * missing one, since everything downstream trusts that field. The finding
	 * names the value before it goes, so it can be re-entered deliberately.
	 *
	 * @param  int    $actor_id Actor post ID.
	 * @param  string $social   'instagram' or 'twitter'.
	 * @return bool   True when the meta was deleted.
	 */
	public function remove_imdb_from_social( int $actor_id, string $social ): bool {
		if ( ! in_array( $social, array( 'instagram', 'twitter' ), true ) ) {
			return false;
		}

		$meta_key = 'lezactors_' . $social;
		$value    = get_post_meta( $actor_id, $meta_key, true );

		if ( empty( $value ) ) {
			return false;
		}

		// Only touch a value that is clean for the platform and IMDb-shaped. A
		// handle that fails sanitize_social() is a different finding with no
		// automated repair, and must not be deleted as collateral.
		if ( Debug_Tool::sanitize_social( $value, $social ) !== $value ) {
			return false;
		}

		if ( ! self::looks_like_actor_imdb( $value ) ) {
			return false;
		}

		return delete_post_meta( $actor_id, $meta_key );
	}

	/**
	 * Sort out a homepage that is really a Wikipedia URL.
	 *
	 * Moves it into lezactors_wikipedia when that field is empty, or drops it
	 * when it merely duplicates what is already there. Two *different* Wikipedia
	 * URLs are left alone -- picking between them is a human's call.
	 *
	 * @param  int  $actor_id Actor post ID.
	 * @return bool True when the homepage was moved or removed.
	 */
	public function fix_homepage_wikipedia( int $actor_id ): bool {
		$home = get_post_meta( $actor_id, 'lezactors_homepage', true );

		if ( empty( $home ) || strpos( $home, 'wikipedia.org/' ) === false ) {
			return false;
		}

		$wiki = get_post_meta( $actor_id, 'lezactors_wikipedia', true );

		if ( empty( $wiki ) ) {
			update_post_meta( $actor_id, 'lezactors_wikipedia', $home );
			return delete_post_meta( $actor_id, 'lezactors_homepage' );
		}

		if ( $wiki === $home ) {
			return delete_post_meta( $actor_id, 'lezactors_homepage' );
		}

		// Two competing Wikipedia URLs -- reported, not repaired.
		return false;
	}

	/**
	 * Find Actors with problems.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_actors_incomplete( $items = array() ): array {

		// The array we will be checking.
		$actors = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the actors from items!
			foreach ( $items as $actor_item ) {
				if ( get_post_status( $actor_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$actors[] = $actor_item['id'];
				}
			}
		} else {
			// Get all the actors
			$the_loop = ( new Post_Type() )->make( 'post_type_actors' );

			// Add ONLY the IDs to the array.
			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$actors = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow actors is totally empty...
		if ( empty( $actors ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$actors = array_unique( $actors );

		// reset items since we recheck off $actors.
		$items = array();

		foreach ( $actors as $actor_id ) {
			$problems = array();

			if ( ! has_post_thumbnail( $actor_id ) ) {
				$problems[] = 'No image found.';
			}

			if ( empty( get_the_content( '', false, $actor_id ) ) ) {
				$problems[] = 'No biography found.';
			}

			// If we added any problems, loop and add.
			if ( ! empty( $problems ) ) {
				$items[] = array(
					'url'     => get_permalink( $actor_id ),
					'id'      => $actor_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_EMPTY, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'actor_empty', 'Incomplete Actors', count( $items ) );

		return $items;
	}

	/**
	 * Find all actors without IMDb Settings.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_actors_no_imdb( $items = array() ): array {

		// The array we will be checking.
		$actors = array();

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the actors from items!
			foreach ( $items as $actor_item ) {
				if ( get_post_status( $actor_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$actors[] = $actor_item['id'];
				}
			}
		} else {
			// Get all the actors
			$the_loop = ( new Post_Type() )->make( 'post_type_actors' );

			// Add ONLY the IDs to the array.
			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$actors = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow actors is totally empty...
		if ( empty( $actors ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$actors = array_unique( $actors );

		// reset items since we recheck off $actors.
		$items = array();

		foreach ( $actors as $actor_id ) {

			$problems = array();

			$imdb = get_post_meta( $actor_id, 'lezactors_imdb', true );

			if ( empty( $imdb ) ) {
				// Check for IMDb existing at all...
				$problems[] = 'IMDb ID is not set.';
			} elseif ( Debug_Tool::validate_imdb( $imdb, 'actor' ) === false ) {
				// - IMDb IDs should be valid for the space they're in, e.g. "nm"
				// and digits for people (props Jamie).
				$problems[] = 'IMDb ID is invalid (ex: nm12345) -- ' . $imdb;
			} else {
				// Same staleness check as shows, oracled against TMDB rather than
				// TVMaze because that is the third party whose person IDs we
				// already store. See Debugger\Shows for why this cannot be a
				// format check and why an empty canonical stays silent.
				$canonical = get_post_meta( $actor_id, 'lezactors_imdb_canonical', true );

				if ( Imdb_Canonical::is_stale( $imdb, $canonical ) ) {
					$problems[] = 'IMDb ID disagrees with TMDB -- ours is ' . $imdb
						. ', TMDB has ' . $canonical
						. '. Ours has probably gone stale; check which is right before correcting it.';
				}
			}

			// If we added any problems, loop and add.
			if ( ! empty( $problems ) ) {
				$items[] = array(
					'url'     => get_permalink( $actor_id ),
					'id'      => $actor_id,
					'problem' => implode( '</br>', $problems ),
				);
			}
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_IMDB, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'actor_imdb', 'Actors without IMDb', count( $items ) );

		return $items;
	}

	/**
	 * Scan Actors' WikiData
	 *
	 * Validate the wikidata matches our data.
	 *
	 * @param int|array $actors Individual ID or Array of IDs, or empty.
	 *
	 * @return array    $items Result of checks.
	 */
	public function check_actors_wikidata( $actors = 0, $items = array() ): array {
		// If actors aren't a number or 0, AND they're not an array, we check everyone...
		if ( is_numeric( $actors ) && 0 !== $actors && ! is_array( $actors ) ) {
			$actors = array( $actors );
		} else {
			// The array we will be checking.
			$actors = array();

			// Are we a full scan or a recheck?
			if ( ! empty( $items ) ) {
				// Check only the actors from items!
				foreach ( $items as $actor_item ) {
					if ( get_post_status( $actor_item['id'] ) !== 'draft' ) {
						// If it's NOT a draft, we'll recheck.
						$actors[] = $actor_item['id'];
					}
				}
			} else {
				// Get all the actors
				$the_loop = ( new Post_Type() )->make( 'post_type_actors' );

				// Add ONLY the IDs to the array.
				if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
					$actors = wp_list_pluck( $the_loop->posts, 'ID' );
				}
			}
		}

		// If somehow actors is totally empty...
		if ( empty( $actors ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$actors = array_unique( $actors );

		// reset items since we recheck off $actors.
		$items = array();

		// Since this now an array no matter what, we search it all.
		foreach ( $actors as $actor_id ) {
			$items[ $actor_id ] = array(
				'id'   => $actor_id,
				'name' => get_the_title( $actor_id ),
			);

			$check_ours = $this->get_actors_wikidata_ours( $actor_id );

			// Search for the actor, using the Q-ID if it's set.
			$wikidata_id = get_post_meta( $actor_id, 'lezactors_wikidata_qid', true );
			$wiki_claims = ( ! empty( $wikidata_id ) ) ? $this->get_actors_wikidata_by_id( $wikidata_id ) : $this->get_actors_wikidata_by_search( $actor_id );

			// If we have no WikiData, we can't check anything.
			if ( empty( $wiki_claims['wikidata'] ) ) {
				$items[ $actor_id ]['wikidata'] = 'error';
				continue;
			}

			$items[ $actor_id ]['wikidata'] = ( ! empty( $wikidata_id ) ) ? $wikidata_id : $wiki_claims['wikidata'];
			unset( $wiki_claims['wikidata'] );

			$check_wiki = $this->process_actor_wikidata( $actor_id, $wiki_claims );

			foreach ( $check_ours as $item => $data ) {
				// Dates (birth/death) are stored raw as Ymd by the ACF date_picker
				// (e.g. 19760525) while WikiData returns Y-m-d (e.g. 1976-05-25).
				// Normalize both to digits-only so equivalent dates match.
				if ( in_array( $item, array( 'birth', 'death' ), true ) ) {
					$ours_lower = preg_replace( '/\D/', '', (string) $data );
					$wiki_lower = preg_replace( '/\D/', '', (string) $check_wiki[ $item ] );
				} else {
					$ours_lower = strtolower( $this->normalize_for_comparison( $data ) );
					$wiki_lower = strtolower( $this->normalize_for_comparison( $check_wiki[ $item ] ) );
				}

				if ( $ours_lower === $wiki_lower ) {
					$result = 'match';
				} elseif ( '' === $wiki_lower ) {
					$result = 'n/a';
				} else {
					$ours_value = $data;
					$wiki_value = $check_wiki[ $item ];

					// URL fields are compared scheme-less, but the copied value
					// needs its https:// back so it can be pasted in directly.
					if ( in_array( $item, array( 'wikipedia', 'facebook', 'website' ), true ) ) {
						$ours_value = $this->ensure_url_scheme( $ours_value );
						$wiki_value = $this->ensure_url_scheme( $wiki_value );
					}

					$result = array(
						'ours'     => $ours_value,
						'wikidata' => $wiki_value,
					);
				}

				$items[ $actor_id ][ $item ] = $result;
			}
		}

		if ( is_array( $items ) ) {
			// Save it all in the DB
			foreach ( $items as $one_item => $one_data ) {
				if ( 'post_type_actors' === get_post_type( $one_data['id'] ) ) {
					update_post_meta( $one_data['id'], 'lezactors_saved_wikidata', $one_data );
				} else {
					// Needed because of oopsie.
					delete_post_meta( $one_data['id'], 'lezactors_saved_wikidata' );
				}
			}
		}

		return $items;
	}

	/**
	 * Get our data for the actor.
	 *
	 * @param int $actor_id - The ID of the actor.
	 *
	 * @return array $check_ours - The data we have.
	 */
	public function get_actors_wikidata_ours( $actor_id ) {
		$meta = get_post_meta( $actor_id );
		return array(
			'birth'     => $this->format_our_date( $meta['lezactors_birth'][0] ?? '' ),
			'death'     => $this->format_our_date( $meta['lezactors_death'][0] ?? '' ),
			'imdb'      => $meta['lezactors_imdb'][0] ?? '',
			'wikipedia' => $meta['lezactors_wikipedia'][0] ?? '',
			'instagram' => $meta['lezactors_instagram'][0] ?? '',
			'twitter'   => $meta['lezactors_twitter'][0] ?? '',
			'facebook'  => $meta['lezactors_facebook'][0] ?? '',
			'website'   => $meta['lezactors_homepage'][0] ?? '',
		);
	}

	/**
	 * Use WikiData ID to search.
	 *
	 * @param string $wikidata_id - The Q-ID to search for.
	 *
	 * @return array $items - The results of the search.
	 */
	public function get_actors_wikidata_by_id( $wikidata_id ) {
		$search_data = wp_remote_get( 'https://www.wikidata.org/entity/' . $wikidata_id, array( 'timeout' => 15 ) );

		// Check for errors.
		if ( is_wp_error( $search_data ) ) {
			return array();
		}

		$search_body = json_decode( $search_data['body'], true );

		if ( empty( $search_body['entities'][ $wikidata_id ]['claims'] ) ) {
			return array();
		}

		$claims              = $search_body['entities'][ $wikidata_id ]['claims'];
		$claims['sitelinks'] = $search_body['entities'][ $wikidata_id ]['sitelinks'] ?? array();
		$claims['wikidata']  = $wikidata_id;

		return $claims;
	}

	/**
	 * Use WikiData Search to find the actor.
	 */
	public function get_actors_wikidata_by_search( $actor_id ) {
		$language    = 'en';
		$search_name = str_replace( ' ', '%20', get_the_title( $actor_id ) );
		$wikipedia   = get_post_meta( $actor_id, 'lezactors_wikipedia', true );

		// Pick language based on existing WikiPedia link.
		if ( ! empty( $wikipedia ) ) {
			$wikiurl  = wp_parse_url( $wikipedia );
			$wikihost = explode( '.', $wikiurl['host'] );
			$language = $wikihost[0];
		}

		$search_queery = 'https://www.wikidata.org/w/api.php?action=wbsearchentities&search=' . $search_name . '&language=' . $language . '&format=json';
		$search_data   = wp_remote_get( $search_queery, array( 'timeout' => 15 ) );

		// Check for errors.
		if ( ! is_wp_error( $search_data ) ) {
			$search_body = json_decode( $search_data['body'], true );
		}

		$wikidata_id = ( is_array( $search_body ) && ! empty( $search_body['search'] ) ) ? $search_body['search'][0]['id'] : '';
		$claims      = ( ! empty( $wikidata_id ) ) ? $this->get_actors_wikidata_by_id( $wikidata_id ) : array();

		$claims['wikidata'] = $wikidata_id;

		// Save the Q-ID if we found one.
		if ( ! empty( $wikidata_id ) ) {
			update_post_meta( $actor_id, 'lezactors_wikidata_qid', $wikidata_id );
		}

		return $claims;
	}

	/**
	 * Process WikiData for the actor.
	 *
	 * Takes the raw data and formats it for comparison.
	 *
	 * @param int   $actor_id - The ID of the actor.
	 * @param array $wiki_claims - The data from WikiData.
	 *
	 * @return array $check_wiki - The data we found.
	 */
	public function process_actor_wikidata( $actor_id, $wiki_claims ) {
		$wikipedia = get_post_meta( $actor_id, 'lezactors_wikipedia', true );

		if ( '' !== $wikipedia ) {
			$parsed_wiki  = wp_parse_url( $wikipedia );
			$explode_host = explode( '.', $parsed_wiki['host'] );
			$wiki_lang    = $explode_host[0];

			if ( isset( $wiki_claims['sitelinks'][ $wiki_lang . 'wiki' ] ) ) {
				$wiki_title = str_replace( ' ', '_', $wiki_claims['sitelinks'][ $wiki_lang . 'wiki' ]['title'] );
				$wiki_link  = 'https://' . $wiki_lang . '.wikipedia.org/wiki/' . $wiki_title;
			}
		} elseif ( isset( $wiki_claims['sitelinks']['enwiki'] ) ) {
			$wiki_title = str_replace( ' ', '_', $wiki_claims['sitelinks']['enwiki']['title'] );
			$wiki_link  = 'https://en.wikipedia.org/wiki/' . $wiki_title;
		}

		return array(
			'birth'     => ( isset( $wiki_claims['P569'] ) ) ? Debug_Tool::format_wikidate( $wiki_claims['P569'][0]['mainsnak']['datavalue']['value']['time'] ) : '',
			'death'     => ( isset( $wiki_claims['P570'] ) ) ? Debug_Tool::format_wikidate( $wiki_claims['P570'][0]['mainsnak']['datavalue']['value']['time'] ) : '',
			'wikipedia' => $wiki_link ?? '',
			'imdb'      => ( isset( $wiki_claims['P345'] ) ) ? $wiki_claims['P345'][0]['mainsnak']['datavalue']['value'] : '',
			'instagram' => ( isset( $wiki_claims['P2003'] ) ) ? $wiki_claims['P2003'][0]['mainsnak']['datavalue']['value'] : '',
			'twitter'   => ( isset( $wiki_claims['P2002'] ) ) ? $wiki_claims['P2002'][0]['mainsnak']['datavalue']['value'] : '',
			'facebook'  => ( isset( $wiki_claims['P2013'] ) ) ? 'https://facebook.com/' . $wiki_claims['P2013'][0]['mainsnak']['datavalue']['value'] : '',
			'website'   => ( isset( $wiki_claims['P856'] ) ) ? $wiki_claims['P856'][0]['mainsnak']['datavalue']['value'] : '',
		);
	}

	/**
	 * Format our raw date for display.
	 *
	 * ACF date_picker stores dates raw as Ymd (e.g. 19760525). Convert to
	 * Y-m-d (e.g. 1976-05-25) to match WikiData's format for display.
	 *
	 * @param string $value - The raw date value.
	 *
	 * @return string $value - The formatted date, or the original value if not a plain Ymd string.
	 */
	public function format_our_date( $value ) {
		if ( preg_match( '/^\d{8}$/', (string) $value ) ) {
			return substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
		}
		return $value;
	}

	/**
	 * Ensure a URL has a scheme.
	 *
	 * We compare URLs scheme-less (we always use https and WikiData is
	 * inconsistent), which leaves stored/copied values without a scheme.
	 * This puts https:// back so the value is pasteable as-is. Empty values
	 * and values that already have a scheme are returned untouched.
	 *
	 * @param string $value - The URL to check.
	 *
	 * @return string $value - The URL with an https:// scheme.
	 */
	public function ensure_url_scheme( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value || preg_match( '#^https?://#i', $value ) ) {
			return $value;
		}

		return 'https://' . ltrim( $value, '/' );
	}

	/**
	 * Normalize data for comparison.
	 *
	 * Removes www. and trailing slashes from URLs.
	 *
	 * @param string $value - The value to clean.
	 *
	 * @return string $value - The cleaned value.
	 */
	public function normalize_for_comparison( $value ) {
		// remove www
		$value = str_replace( 'www.', '', $value );
		// remove trailing slash
		$value = untrailingslashit( $value );
		// remove http://
		$value = str_replace( 'http://', '', $value );
		// remove https://
		$value = str_replace( 'https://', '', $value );

		return $value;
	}
}
