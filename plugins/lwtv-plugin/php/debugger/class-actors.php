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

		$items[] = array(
			'url'     => get_permalink( $actor_id ),
			'id'      => $actor_id,
			'problem' => sprintf( 'Shadow taxonomy sync failed repeatedly (term %d). Run: wp lwtv shadow actors', $term_id ),
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

		// reset items since we recheck off $actors.
		$items = array();

		foreach ( $actors as $actor_id ) {
			$problems = array();
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
				$problems[] = 'No characters listed.';
			}

			// - Warn if there is a death and no birth (nb: this may not be a good idea, some people have no DoB!)
			if ( ! empty( $check['death'] ) ) {
				if ( empty( $check['birth'] ) ) {
					$warnings[] = 'Death date set without date of birth.';
				}
			}

			// - Wikipedia links should point to Wikipedia: "https://[language].wikipedia.org/" (props Jamie)
			if ( ! empty( $check['wiki'] ) && strpos( $check['wiki'], 'wikipedia.org/' ) === false ) {
				$problems[] = 'Wikipedia URL does not point to Wikipedia.';
			}

			// - Instagram and Twitter usernames should follow whatever the
			//   actual restrictions on those are (props Jamie)
			// - If Instagram or Twitter usernames are the same format as IMDb IDs,
			//   that's suspicious (props Jamie)
			if ( ! empty( $check['insta'] ) ) {
				// Limit - 30 symbols. Username must contains only letters, numbers, periods and underscores.
				if ( ( new Debug_Tool() )->sanitize_social( $check['insta'], 'instagram' ) !== $check['insta'] ) {
					$problems[] = 'Instagram ID is invalid -- ' . esc_html( $check['insta'] );
				} elseif ( ( new Debug_Tool() )->validate_imdb( $check['insta'], 'actor' ) ) {
					// If instagram is IMDb, then it's wrong.
					delete_post_meta( $actor_id, 'lezactors_instagram' );
					$problems[] = 'Instagram ID was set as IMDb and has been removed - ' . esc_html( $check['insta'] );
				}
			}
			if ( ! empty( $check['twits'] ) ) {
				if ( ( new Debug_Tool() )->sanitize_social( $check['twits'], 'twitter' ) !== $check['twits'] ) {
					$problems[] = 'Twitter ID is invalid -- ' . esc_html( $check['twits'] );
				} elseif ( ( new Debug_Tool() )->validate_imdb( $check['twits'], 'actor' ) ) {
					// If Twitter is IMDb, then it's wrong.
					delete_post_meta( $actor_id, 'lezactors_twitter' );
					$problems[] = 'Twitter ID was set as IMDb and has been removed - ' . esc_html( $check['twits'] );
				}
			}

			// - "Website" links should *not* point to Wikipedia, since that
			// would make them Wikipedia links (props Jamie)
			if ( ! empty( $check['home'] ) ) {
				if ( strpos( $check['home'], 'wikipedia.org/' ) !== false ) {
					if ( empty( $check['wiki'] ) ) {
						// If there is no wiki set, move homepage to wiki and clear home page.
						update_post_meta( $actor_id, 'lezactors_wikipedia', $check['home'] );
						delete_post_meta( $actor_id, 'lezactors_homepage' );
					} elseif ( $check['wiki'] === $check['home'] ) {
						// If wiki === home page, delete home page.
						delete_post_meta( $actor_id, 'lezactors_homepage' );
					} else {
						// record problem
						$problems[] = 'Homepage points to Wikipedia - ' . sanitize_url( $check['home'] );
					}
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
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'actor_problems', 'Actors with Issues', count( $items ) );

		return $items;
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
			} elseif ( ( new Debug_Tool() )->validate_imdb( $imdb, 'actor' ) === false ) {
				// - IMDb IDs should be valid for the space they're in, e.g. "nm"
				// and digits for people (props Jamie).
				$problems[] = 'IMDb ID is invalid (ex: nm12345) -- ' . $imdb;
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
					} else {
						// Delete post meta for drafts.
						delete_post_meta( $actor_item['id'], 'debug_check' );
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
			'birth'     => ( isset( $wiki_claims['P569'] ) ) ? ( new Debug_Tool() )->format_wikidate( $wiki_claims['P569'][0]['mainsnak']['datavalue']['value']['time'] ) : '',
			'death'     => ( isset( $wiki_claims['P570'] ) ) ? ( new Debug_Tool() )->format_wikidate( $wiki_claims['P570'][0]['mainsnak']['datavalue']['value']['time'] ) : '',
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
