<?php
/*
 * Find all problems with Actor pages.
 *
 * find_actors_problems()   - find actors with weird/bad data
 * find_actors_incomplete() - find actors without bio or photo
 * find_actors_no_imdb()    - find actors without IMDb / bad IMDb data
 *
 * check_actors_wikidata()  - Validate our data vs WikiData.
 * check_actor_death()      - Has an actor died without us noticing?
 *
 * Both ask LWTV\Wikidata\Identity who an actor is, but they ask different
 * questions of it: the diff above wants coverage and can live with a fuzzy name
 * match, because a human reads every row. The death check wants certainty and
 * takes only a trusted QID, because nothing reads its reasoning before it says
 * a person has died.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Debugger\Build\Actor_Completeness_Rules;
use LWTV\Debugger\Build\Actor_Death_Rules;
use LWTV\Debugger\Build\Actor_Rules;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Build\Imdb_Rules;
use LWTV\Debugger\Collect\Actor_Collector;
use LWTV\Debugger\Collect\Actor_Completeness_Collector;
use LWTV\Debugger\Collect\Imdb_Collector;
use LWTV\Debugger\Format\Rows;
use LWTV\Wikidata\Identity;

class Actors {

	/**
	 * Findings from find_actors_problems().
	 */
	const FINDINGS_PROBLEMS = 'lwtv_debug_actor_problems';

	/**
	 * Findings from find_actors_incomplete().
	 */
	const FINDINGS_EMPTY = 'lwtv_debug_actor_empty';

	/**
	 * Findings from find_actors_no_imdb().
	 */
	const FINDINGS_IMDB = 'lwtv_debug_actor_imdb';

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
	 * Appends the actor to the lwtv_debug_actor_problems findings so it
	 * surfaces in `wp lwtv debug actors` and the admin debugger view.
	 *
	 * @param int $actor_id  Post ID of the actor.
	 * @param int $term_id   Shadow taxonomy term ID that failed to attach.
	 * @return void
	 */
	public function flag_shadow_sync_failure( int $actor_id, int $term_id ): void {
		$items = Findings_Store::load( self::FINDINGS_PROBLEMS );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		// Avoid duplicates — check if this actor is already flagged.
		foreach ( $items as $item ) {
			if ( isset( $item['id'] ) && (int) $item['id'] === $actor_id ) {
				return;
			}
		}

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

		Scan::store( self::FINDINGS_PROBLEMS, $items );

		Status::record( 'actor_problems', 'Actors with Issues', count( $items ) );
	}

	/**
	 * Find Actors with problems.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_actors_problems( $items = array() ): array {

		// A recheck only revisits what was already flagged, so it may be tagged
		// against the baseline but never diffed into it. See Scan::finish().
		$is_recheck = ! empty( $items );

		$actors = Scan::post_ids( $items, CPT_Actors::SLUG );

		if ( empty( $actors ) ) {
			return array();
		}

		$collector = new Actor_Collector();
		$findings  = array();

		foreach ( array_chunk( $actors, Actor_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $actor ) {
				$findings = array_merge( $findings, Actor_Rules::evaluate( $actor ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'actor_problems',
				'findings' => self::FINDINGS_PROBLEMS,
				'label'    => 'Actors with Issues',
			),
			$findings,
			$is_recheck
		);
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
	 * Replace a pasted IMDb URL with the ID inside it.
	 *
	 * @param  int  $actor_id Actor post ID.
	 * @return bool True when the ID was written.
	 */
	public function extract_imdb_from_url( int $actor_id ): bool {
		$current   = (string) get_post_meta( $actor_id, Actor_Rules::META_IMDB, true );
		$extracted = Imdb_Rules::id_from_url( $current, Imdb_Rules::ACTOR );

		if ( '' === $extracted ) {
			return false;
		}

		return (bool) update_post_meta( $actor_id, Actor_Rules::META_IMDB, $extracted );
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

		if ( Debug_Tool::sanitize_social( $value, $social ) !== $value ) {
			return false;
		}

		if ( ! Actor_Rules::looks_like_actor_imdb( $value ) ) {
			return false;
		}

		return delete_post_meta( $actor_id, $meta_key );
	}

	/**
	 * Sort out a homepage that is really a Wikipedia URL.
	 *
	 * Moves it into lezactors_wikipedia when that field is empty, or drops it
	 * when it merely duplicates what is already there.
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
		$is_recheck = ! empty( $items );

		$actors = Scan::post_ids( $items, CPT_Actors::SLUG );

		if ( empty( $actors ) ) {
			return array();
		}

		$collector = new Actor_Completeness_Collector();
		$findings  = array();

		foreach ( array_chunk( $actors, Actor_Completeness_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $actor ) {
				$findings = array_merge( $findings, Actor_Completeness_Rules::evaluate( $actor ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'actor_empty',
				'findings' => self::FINDINGS_EMPTY,
				'label'    => 'Incomplete Actors',
			),
			$findings,
			$is_recheck
		);
	}

	/**
	 * Find all actors without IMDb Settings.
	 *
	 * @return array $problems - array of problems. Can be empty.
	 */
	public function find_actors_no_imdb( $items = array() ): array {
		$is_recheck = ! empty( $items );

		$actors = Scan::post_ids( $items, CPT_Actors::SLUG );

		if ( empty( $actors ) ) {
			return array();
		}

		$collector = new Imdb_Collector();
		$findings  = array();

		foreach ( array_chunk( $actors, Imdb_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( Imdb_Rules::ACTOR, $batch ) as $actor ) {
				$findings = array_merge( $findings, Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $actor ) );
			}
		}

		return Scan::finish(
			array(
				'scope'    => 'actor_imdb',
				'findings' => self::FINDINGS_IMDB,
				'label'    => 'Actors without IMDb',
			),
			$findings,
			$is_recheck
		);
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
			$actors = Scan::post_ids( $items, CPT_Actors::SLUG );
		}

		if ( empty( $actors ) ) {
			return array();
		}

		// reset items since we recheck off $actors.
		$items = array();

		// Since this now an array no matter what, we search it all.
		foreach ( $actors as $actor_id ) {
			$items[ $actor_id ] = array(
				'id'   => $actor_id,
				'name' => get_the_title( $actor_id ),
			);

			$check_ours = $this->get_actors_wikidata_ours( $actor_id );

			// Manual QID, then the IMDb ID, then the name. Name matches are
			// allowed here -- a human reads every row this produces -- and
			// Identity records which tier answered, so nothing downstream
			// mistakes a guess for a verified match.
			$wikidata    = new Identity();
			$identity    = $wikidata->resolve( (int) $actor_id );
			$wiki_claims = ( '' !== $identity['qid'] ) ? $wikidata->entity( $identity['qid'] ) : array();

			// If we have no WikiData, we can't check anything.
			if ( empty( $wiki_claims['wikidata'] ) ) {
				$items[ $actor_id ]['wikidata'] = 'error';
				continue;
			}

			$items[ $actor_id ]['wikidata'] = $identity['qid'];
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
	 * @param string $wikidata_id - The QID to search for.
	 *
	 * @return array $items - The results of the search.
	 */
	public function get_actors_wikidata_by_id( $wikidata_id ) {
		return ( new Identity() )->entity( (string) $wikidata_id );
	}

	/**
	 * Use WikiData Search to find the actor.
	 *
	 * Returns the shape its callers expect -- claims plus a 'wikidata' key --
	 * with the resolution done by Identity::resolve(), so this and the death
	 * audit agree about who an actor is.
	 *
	 * @param int $actor_id - The ID of the actor.
	 *
	 * @return array The claims, with a 'wikidata' key holding the QID.
	 */
	public function get_actors_wikidata_by_search( $actor_id ) {
		$identity = new Identity();
		$resolved = $identity->resolve( (int) $actor_id );
		$claims   = ( '' !== $resolved['qid'] ) ? $identity->entity( $resolved['qid'] ) : array();

		$claims['wikidata'] = $resolved['qid'];

		return $claims;
	}

	/**
	 * Has this actor died without us noticing?
	 *
	 * Collects the facts and hands the decision to Actor_Death_Rules, which is
	 * where the reasoning lives and where it is tested. This method's only job is
	 * the part that cannot be pure: reading meta and talking to WikiData.
	 *
	 * Identity comes from trusted_qid(), not resolve(). That is the whole
	 * safeguard: a QID that came from a name search -- or one stored before we
	 * tracked sources, which may well have -- is not an identity this check may
	 * act on, because nobody is reading its reasoning before it says a person
	 * has died. Such an actor is reported as unidentifiable, and
	 * `wp lwtv wikidata backfill --reverify` is what turns them into something
	 * checkable, on real evidence rather than on a guess being old enough to
	 * look settled.
	 *
	 * Reports; never writes a death date.
	 *
	 * @param int $actor_id The ID of the actor.
	 * @return array{verdict: string, action: string, qid: string, source: string, death: string, our_birth: string, wiki_birth: string}
	 */
	public function check_actor_death( int $actor_id ): array {
		$identity = new Identity();

		// Whether the write-lock means "stop" is a rule, not a meta read, so it
		// lives in Actor_Death_Rules where it is tested. See editor_says_stop().
		//
		// The raw stored QID, not trusted_qid(): the question is whether the
		// editor left the field empty, not whether we vouch for what is in it. A
		// locked QID we cannot vouch for should read as UNVERIFIED and be
		// reported, because the lock means the backfill can no longer resolve it
		// and only a human can.
		$item = array(
			'our_death'  => (string) get_post_meta( $actor_id, 'lezactors_death', true ),
			'our_birth'  => (string) get_post_meta( $actor_id, 'lezactors_birth', true ),
			'ignored'    => Actor_Death_Rules::editor_says_stop(
				$identity->is_ignored( $actor_id ),
				(string) get_post_meta( $actor_id, Identity::META_QID, true )
			),
			'qid'        => '',
			'source'     => '',
			'fetched'    => false,
			'wiki_death' => '',
			'wiki_birth' => '',
		);

		// Rule 1 of the audit: if we already have a date, we are done. Bail
		// before spending a request, so a full run costs nothing for the
		// thousands of actors whose data is already settled. Same for an actor
		// an editor has told us to stop asking about.
		if ( '' !== trim( $item['our_death'] ) || $item['ignored'] ) {
			return $this->death_result( $item, Actor_Death_Rules::evaluate( $item ) );
		}

		$trusted        = $identity->trusted_qid( $actor_id );
		$item['qid']    = $trusted['qid'];
		$item['source'] = $trusted['source'];

		if ( '' !== $item['qid'] ) {
			$claims = $identity->entity( $item['qid'] );

			if ( ! empty( $claims ) ) {
				// Read the two claims directly rather than going through
				// process_actor_wikidata(). That method also reconstructs a
				// Wikipedia link from the actor's stored URL, which is work we
				// don't need and a code path we'd be running across every actor
				// on the site instead of the one an editor asked about.
				$item['fetched']    = true;
				$item['wiki_death'] = $identity->date_claim( $claims, Identity::P_DEATH );
				$item['wiki_birth'] = $identity->date_claim( $claims, Identity::P_BIRTH );
			}
		}

		return $this->death_result( $item, Actor_Death_Rules::evaluate( $item ) );
	}

	/**
	 * Flatten a collected item and its verdict into one return shape.
	 *
	 * @param array $item    Collected actor data.
	 * @param array $verdict Result from Actor_Death_Rules::evaluate().
	 * @return array
	 */
	private function death_result( array $item, array $verdict ): array {
		return array(
			'verdict'    => $verdict['verdict'],
			'action'     => $verdict['action'],
			'death'      => $verdict['death'],
			'qid'        => $item['qid'],
			'source'     => $item['source'],
			'our_birth'  => $this->format_our_date( $item['our_birth'] ),
			'wiki_birth' => $item['wiki_birth'],
		);
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
