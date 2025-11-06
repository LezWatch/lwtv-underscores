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

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\Queeries\Post_Type;

class Actors {

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

			// What we can check for
			$check = array(
				'chars' => get_post_meta( $actor_id, 'lezactors_char_count', true ),
				'birth' => get_post_meta( $actor_id, 'lezactors_birth', true ),
				'death' => get_post_meta( $actor_id, 'lezactors_death', true ),
				'wiki'  => get_post_meta( $actor_id, 'lezactors_wikipedia', true ),
				'imdb'  => get_post_meta( $actor_id, 'lezactors_imdb', true ),
				'insta' => get_post_meta( $actor_id, 'lezactors_instagram', true ),
				'twits' => get_post_meta( $actor_id, 'lezactors_twitter', true ),
				'home'  => get_post_meta( $actor_id, 'lezactors_homepage', true ),
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
					$problems[] = 'Instagram ID is invalid -- ' . $check['insta'];
				} elseif ( ( new Debug_Tool() )->validate_imdb( $check['insta'], 'actor' ) ) {
					// If instagram is IMDb, then it's wrong.
					delete_post_meta( $actor_id, 'lezactors_instagram' );
					$problems[] = 'Instagram ID was set as IMDb and has been removed - ' . $check['insta'];
				}
			}
			if ( ! empty( $check['twits'] ) ) {
				if ( ( new Debug_Tool() )->sanitize_social( $check['twits'], 'twitter' ) !== $check['twits'] ) {
					$problems[] = 'Twitter ID is invalid -- ' . $check['insta'];
				} elseif ( ( new Debug_Tool() )->validate_imdb( $check['twits'], 'actor' ) ) {
					// If Twitter is IMDb, then it's wrong.
					delete_post_meta( $actor_id, 'lezactors_twitter' );
					$problems[] = 'Twitter ID was set as IMDb and has been removed - ' . $check['twits'];
				}
			}

			// - "Website" links should *not* point to Wikipedia, since that
			// would make them Wikipedia links (props Jamie)
			if ( ! empty( $check['home'] ) ) {
				if ( strpos( $check['home'], 'wikipedia.org/' ) !== false ) {
					if ( empty( $check['wiki'] ) ) {
						// If there is no wiki set, move homepage to wiki and clear home page.
						update_post_meta( $actor_id, 'lezactors_wikipedia', $check['wiki'] );
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
		lwtv_plugin()->set_transient( 'lwtv_debug_actor_problems', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                   = get_option( 'lwtv_debugger_status' );
		$option['actor_problems'] = array(
			'name'  => 'Actors with Issues',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp']      = time();
		update_option( 'lwtv_debugger_status', $option );

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
		lwtv_plugin()->set_transient( 'lwtv_debug_actor_empty', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                = get_option( 'lwtv_debugger_status' );
		$option['actor_empty'] = array(
			'name'  => 'Incomplete Actors',
			'count' => count( $items ),
			'last'  => time(),
		);
		$option['timestamp']   = time();
		update_option( 'lwtv_debugger_status', $option );

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
		lwtv_plugin()->set_transient( 'lwtv_debug_actor_imdb', $items, WEEK_IN_SECONDS );

		// Update Options
		$option               = get_option( 'lwtv_debugger_status' );
		$option['actor_imdb'] = array(
			'name'  => 'Actors without IMDb',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		$option['timestamp']  = time();
		update_option( 'lwtv_debugger_status', $option );

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
				$data                = strtolower( $this->remove_www( $data ) );
				$check_wiki[ $item ] = strtolower( $this->remove_www( $check_wiki[ $item ] ) );

				if ( $data === $check_wiki[ $item ] ) {
					$result = 'match';
				} elseif ( '' === $check_wiki[ $item ] ) {
					$result = 'n/a';
				} else {
					$result = array(
						'ours'     => $data,
						'wikidata' => $check_wiki[ $item ],
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
		return array(
			'birth'     => get_post_meta( $actor_id, 'lezactors_birth', true ),
			'death'     => get_post_meta( $actor_id, 'lezactors_death', true ),
			'imdb'      => get_post_meta( $actor_id, 'lezactors_imdb', true ),
			'wikipedia' => get_post_meta( $actor_id, 'lezactors_wikipedia', true ),
			'instagram' => get_post_meta( $actor_id, 'lezactors_instagram', true ),
			'twitter'   => get_post_meta( $actor_id, 'lezactors_twitter', true ),
			'facebook'  => get_post_meta( $actor_id, 'lezactors_facebook', true ),
			'website'   => get_post_meta( $actor_id, 'lezactors_homepage', true ),
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
		$search_data = wp_remote_get( 'http://www.wikidata.org/entity/' . $wikidata_id );

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
		$search_data   = wp_remote_get( $search_queery );

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
	 * Remove www from URLs.
	 *
	 * @param string $url - The URL to clean.
	 *
	 * @return string $url - The cleaned URL.
	 */
	public function remove_www( $url ) {
		$url = str_replace( 'www.', '', $url );

		return $url;
	}
}
