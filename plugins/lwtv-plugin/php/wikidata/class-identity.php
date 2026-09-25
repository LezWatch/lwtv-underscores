<?php
/**
 * Who is this actor on WikiData?
 *
 * The single owner of actor-to-QID resolution. One QID field, with its source
 * recorded beside it; only 'manual' and 'imdb' sources may drive unattended
 * decisions. See docs/architecture/actor-identity.md.
 *
 * @package LWTV
 */

namespace LWTV\Wikidata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Debugger\Build\Imdb_Rules;
use LWTV\Wikidata\Build\Qid_Trust;

class Identity {

	/**
	 * The resolved QID. Machine-written.
	 */
	const META_QID = 'lezactors_wikidata_qid';

	/**
	 * How META_QID was resolved -- a Qid_Trust::SOURCE_* value.
	 */
	const META_SOURCE = 'lezactors_wikidata_qid_source';

	/**
	 * Timestamp of the last *attempted* lookup, so "WikiData has no item for
	 * this person" and "we never asked" stop being indistinguishable.
	 */
	const META_CHECKED = 'lezactors_wikidata_checked';

	/**
	 * Write-lock on META_QID. Set it and no machine write can land, so the field
	 * is editable by hand only. See store_qid().
	 */
	const META_IGNORE = 'lezactors_wikidata_ignore';

	/**
	 * The actor's IMDb ID, and the canonical one TMDB holds when ours is stale.
	 */
	const META_IMDB           = 'lezactors_imdb';
	const META_IMDB_CANONICAL = 'lezactors_imdb_canonical';

	/**
	 * WikiData API and entity endpoints.
	 */
	const API_URL    = 'https://www.wikidata.org/w/api.php';
	const ENTITY_URL = 'https://www.wikidata.org/entity/';

	/**
	 * Identify ourselves. The Wikimedia Foundation's user-agent policy asks for
	 * a contactable client rather than a generic library string, and reserves
	 * the right to block requests without one.
	 */
	const USER_AGENT = 'LezWatch.TV WikiData identity resolution (+https://lezwatchtv.com)';

	/**
	 * Default pause between requests, in milliseconds.
	 */
	const DEFAULT_SLEEP_MS = 400;

	/**
	 * How long to back off on an HTTP 429, in milliseconds.
	 */
	const BACKOFF_MS = 5000;

	/**
	 * Date properties.
	 */
	const P_BIRTH = 'P569';
	const P_DEATH = 'P570';

	/**
	 * IMDb ID property.
	 */
	const P_IMDB = 'P345';

	/**
	 * A QID safe to draw conclusions from, or nothing.
	 *
	 * What an unattended process asks for. No lookup, no writes: a QID with a
	 * trusted source, or nothing. The write-lock is deliberately not read here.
	 * See docs/architecture/actor-identity.md#the-write-lock.
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return array{qid: string, source: string}
	 */
	public function trusted_qid( int $actor_id ): array {
		$qid    = trim( (string) get_post_meta( $actor_id, self::META_QID, true ) );
		$source = Qid_Trust::normalise_source( (string) get_post_meta( $actor_id, self::META_SOURCE, true ) );

		if ( '' !== $qid && Qid_Trust::is_trusted( $source ) ) {
			return array(
				'qid'    => $qid,
				'source' => $source,
			);
		}

		// We may well hold a QID here. We just cannot say whose it is.
		return array(
			'qid'    => '',
			'source' => '' === $qid ? '' : $source,
		);
	}

	/**
	 * Resolve an actor to a QID, looking it up if we have to.
	 *
	 * Writes back what it finds, along with the source, so the next caller is a
	 * request lighter and a human can see and correct the match.
	 *
	 * @param  int  $actor_id   The ID of the actor.
	 * @param  bool $allow_name Whether to fall back to a name search. False for
	 *                          anything unattended -- see
	 *                          docs/architecture/actor-identity.md.
	 * @return array{qid: string, source: string} Source is a SOURCE_* value, or
	 *               'imdb-ambiguous' when the IMDb ID matched several items, or
	 *               '' when nothing resolved.
	 */
	public function resolve( int $actor_id, bool $allow_name = true ): array {
		$stored = trim( (string) get_post_meta( $actor_id, self::META_QID, true ) );
		$source = Qid_Trust::normalise_source( (string) get_post_meta( $actor_id, self::META_SOURCE, true ) );

		if ( '' !== $stored && Qid_Trust::is_trusted( $source ) ) {
			return array(
				'qid'    => $stored,
				'source' => $source,
			);
		}

		$by_imdb = $this->qid_from_imdb( $this->imdb_id( $actor_id ) );

		if ( '' !== $by_imdb['qid'] ) {
			$this->store_qid( $actor_id, $by_imdb['qid'], Qid_Trust::SOURCE_IMDB );

			return array(
				'qid'    => $by_imdb['qid'],
				'source' => Qid_Trust::SOURCE_IMDB,
			);
		}

		// Several items carry this IMDb ID. Report it rather than picking one,
		// and do not fall through to the weaker lookup: an ambiguous exact
		// match is a data problem worth naming, not a reason to start guessing.
		if ( $by_imdb['ambiguous'] ) {
			return array(
				'qid'    => '',
				'source' => 'imdb-ambiguous',
			);
		}

		// An untrusted QID we could not verify. Hand it back as-is with its
		// real source, so a caller that only needs *something* to diff against
		// still has it, and one that needs certainty can see it lacks it.
		if ( '' !== $stored ) {
			return array(
				'qid'    => $stored,
				'source' => $source,
			);
		}

		if ( ! $allow_name ) {
			return array(
				'qid'    => '',
				'source' => '',
			);
		}

		$by_name = $this->qid_from_name( $actor_id );

		if ( '' !== $by_name ) {
			$this->store_qid( $actor_id, $by_name, Qid_Trust::SOURCE_NAME );
		}

		return array(
			'qid'    => $by_name,
			'source' => ( '' === $by_name ) ? '' : Qid_Trust::SOURCE_NAME,
		);
	}

	/**
	 * Everything should_check() needs to judge one actor.
	 *
	 * @param  int   $actor_id The ID of the actor.
	 * @param  array $flags    Optional 'retry_missed' and 'reverify' booleans.
	 * @return array
	 */
	public function collect( int $actor_id, array $flags = array() ): array {
		return array(
			'qid'          => trim( (string) get_post_meta( $actor_id, self::META_QID, true ) ),
			'source'       => (string) get_post_meta( $actor_id, self::META_SOURCE, true ),
			'ignored'      => $this->is_ignored( $actor_id ),
			'checked'      => (int) get_post_meta( $actor_id, self::META_CHECKED, true ),
			'imdb'         => $this->imdb_id( $actor_id ),
			'retry_missed' => ! empty( $flags['retry_missed'] ),
			'reverify'     => ! empty( $flags['reverify'] ),
		);
	}

	/**
	 * Look one actor up and record the outcome.
	 *
	 * The unit the backfill and the scheduler both run. 'none' and 'ambiguous'
	 * are answers and earn a checked-marker; 'error' is not and earns nothing.
	 * See docs/architecture/actor-identity.md#lookup-outcomes-and-the-checked-marker.
	 *
	 * @param  int  $actor_id The ID of the actor.
	 * @param  bool $dry_run  Compute the verdict without writing meta.
	 * @return array{status: string, qid: string, was: string, reason: string}
	 *               Status is found|confirmed|conflict|none|ambiguous|error|skipped.
	 */
	public function resolve_and_record( int $actor_id, bool $dry_run = false ): array {
		$collected = $this->collect( $actor_id );
		$imdb      = $collected['imdb'];

		if ( '' === $imdb ) {
			return $this->outcome( 'skipped', '', '', 'no IMDb ID to ask with' );
		}

		$lookup = $this->qid_from_imdb( $imdb );

		if ( 'error' === $lookup['status'] ) {
			// No checked-marker. An outage is not an answer.
			return $this->outcome( 'error', '', $collected['qid'], $lookup['reason'] );
		}

		// An answer, just not a usable one. Marked checked so it stops being
		// re-asked: nothing about the next request would come back different.
		if ( $lookup['ambiguous'] ) {
			if ( ! $dry_run ) {
				update_post_meta( $actor_id, self::META_CHECKED, time() );
			}

			return $this->outcome( 'ambiguous', '', $collected['qid'], 'IMDb ID matches several WikiData items' );
		}

		if ( '' === $lookup['qid'] ) {
			if ( ! $dry_run ) {
				update_post_meta( $actor_id, self::META_CHECKED, time() );
			}

			return $this->outcome( 'none', '', $collected['qid'], 'no WikiData item holds this IMDb ID' );
		}

		// An untrusted QID we can now vouch for, or correct.
		if ( '' !== $collected['qid'] && $collected['qid'] !== $lookup['qid'] ) {
			if ( ! $dry_run ) {
				$this->store_qid( $actor_id, $lookup['qid'], Qid_Trust::SOURCE_IMDB );
			}

			return $this->outcome( 'conflict', $lookup['qid'], $collected['qid'], 'stored QID disagreed with the IMDb match' );
		}

		$status = ( '' !== $collected['qid'] ) ? 'confirmed' : 'found';

		if ( ! $dry_run ) {
			$this->store_qid( $actor_id, $lookup['qid'], Qid_Trust::SOURCE_IMDB );
		}

		return $this->outcome( $status, $lookup['qid'], $collected['qid'], '' );
	}

	/**
	 * The WikiData item whose IMDb ID (P345) is exactly this one.
	 *
	 * A haswbstatement lookup, not a text search. Asks for two results only to
	 * detect ambiguity, which is reported rather than resolved. See
	 * docs/architecture/actor-identity.md#why-only-p345-matches-may-write.
	 *
	 * @param  string $imdb_id A validated nm-prefixed IMDb ID.
	 * @return array{qid: string, ambiguous: bool, status: string, reason: string}
	 */
	public function qid_from_imdb( string $imdb_id ): array {
		if ( '' === $imdb_id || ! Debug_Tool::validate_imdb( $imdb_id, 'actor' ) ) {
			return $this->lookup( '', false, 'none', '' );
		}

		$response = $this->request(
			add_query_arg(
				array(
					'action'   => 'query',
					'list'     => 'search',
					'srsearch' => 'haswbstatement:' . self::P_IMDB . '=' . $imdb_id,
					'srlimit'  => 2,
					'format'   => 'json',
				),
				self::API_URL
			)
		);

		if ( 'ok' !== $response['status'] ) {
			return $this->lookup( '', false, 'error', $response['reason'] );
		}

		$results = $response['body']['query']['search'] ?? array();

		if ( ! is_array( $results ) || empty( $results ) ) {
			return $this->lookup( '', false, 'none', '' );
		}

		if ( count( $results ) > 1 ) {
			return $this->lookup( '', true, 'ok', '' );
		}

		$qid = (string) ( $results[0]['title'] ?? '' );

		return $this->lookup( preg_match( '/^Q[0-9]+$/', $qid ) ? $qid : '', false, 'ok', '' );
	}

	/**
	 * The first QID WikiData returns for the actor's name.
	 *
	 * The weakest of the three lookups by a wide margin: wbsearchentities scores
	 * text, so the first hit for a common name is a coin toss between our actor,
	 * a politician, and a 19th century botanist. Fine for putting a diff in front
	 * of a human who will notice; never a basis for a conclusion.
	 *
	 * Searches the raw post_title, because wptexturize output is not what
	 * WikiData indexes.
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return string The QID, or '' when nothing came back.
	 */
	public function qid_from_name( int $actor_id ): string {
		$language  = 'en';
		$wikipedia = (string) get_post_meta( $actor_id, 'lezactors_wikipedia', true );

		// Pick the language from an existing Wikipedia link, when there is one.
		if ( '' !== $wikipedia ) {
			$parsed = wp_parse_url( $wikipedia );
			$host   = explode( '.', $parsed['host'] ?? '' );

			if ( '' !== $host[0] ) {
				$language = $host[0];
			}
		}

		$response = $this->request(
			add_query_arg(
				array(
					'action'   => 'wbsearchentities',
					'search'   => $this->search_title( $actor_id ),
					'language' => $language,
					'format'   => 'json',
				),
				self::API_URL
			)
		);

		if ( 'ok' !== $response['status'] ) {
			return '';
		}

		return (string) ( $response['body']['search'][0]['id'] ?? '' );
	}

	/**
	 * An actor's name in a form worth sending to a search API.
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return string
	 */
	private function search_title( int $actor_id ): string {
		$title = (string) get_post_field( 'post_title', $actor_id, 'raw' );

		return trim( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * A WikiData entity's claims, keyed by property.
	 *
	 * @param  string $qid A QID.
	 * @return array Empty when the entity could not be read or holds no claims.
	 */
	public function entity( string $qid ): array {
		if ( ! preg_match( '/^Q[0-9]+$/', $qid ) ) {
			return array();
		}

		$response = $this->request( self::ENTITY_URL . $qid );

		if ( 'ok' !== $response['status'] || empty( $response['body']['entities'][ $qid ]['claims'] ) ) {
			return array();
		}

		$claims              = $response['body']['entities'][ $qid ]['claims'];
		$claims['sitelinks'] = $response['body']['entities'][ $qid ]['sitelinks'] ?? array();
		$claims['wikidata']  = $qid;

		return $claims;
	}

	/**
	 * One date claim out of an entity.
	 *
	 * @param  array  $claims   Claims from entity().
	 * @param  string $property P569 (birth) or P570 (death).
	 * @return string The date, or '' when absent or malformed.
	 */
	public function date_claim( array $claims, string $property ): string {
		$time = $claims[ $property ][0]['mainsnak']['datavalue']['value']['time'] ?? '';

		return ( '' === $time ) ? '' : Debug_Tool::format_wikidate( $time );
	}

	/**
	 * The actor's IMDb person ID, in a form WikiData can be queried with.
	 *
	 * Tolerates the two ways this field goes wrong without being useless: a
	 * pasted IMDb URL, where the ID is sitting right there in a known position,
	 * and a stale ID, where TMDB's canonical value is the better bet. Both are
	 * common enough that giving up on them would cost real coverage.
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return string A validated nm-prefixed ID, or '' when there isn't one.
	 */
	public function imdb_id( int $actor_id ): string {
		$candidates = array(
			(string) get_post_meta( $actor_id, self::META_IMDB, true ),
			(string) get_post_meta( $actor_id, self::META_IMDB_CANONICAL, true ),
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );

			if ( '' === $candidate ) {
				continue;
			}

			if ( Debug_Tool::validate_imdb( $candidate, 'actor' ) ) {
				return $candidate;
			}

			$extracted = Imdb_Rules::id_from_url( $candidate, Imdb_Rules::ACTOR );

			if ( '' !== $extracted ) {
				return $extracted;
			}
		}

		return '';
	}

	/**
	 * A typed or pasted value, as a bare QID.
	 *
	 * Accepts a pasted wikidata.org URL as well as a bare QID, so a pasted
	 * correction is never silently discarded. Runs on the write path, once.
	 *
	 * @param  string $value Raw field value.
	 * @return string A bare QID, or '' when the value holds nothing usable.
	 */
	public static function normalise_qid( string $value ): string {
		$value = trim( $value );

		if ( preg_match( '/^Q[0-9]+$/', $value ) ) {
			return $value;
		}

		// Only from a real wikidata.org URL. Something Q-shaped inside another
		// site's URL is not evidence of anything.
		if ( preg_match( '#^https?://([a-z0-9-]+\.)*wikidata\.org/.*?/(Q[0-9]+)(?:[/?\#]|$)#i', $value, $matches ) ) {
			return $matches[2];
		}

		return '';
	}

	/**
	 * Has an editor write-locked this actor's QID?
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return bool
	 */
	public function is_ignored( int $actor_id ): bool {
		$ignore = get_post_meta( $actor_id, self::META_IGNORE, true );

		// ACF true_false stores "1"/"0" as strings, so a plain truthiness check
		// would read "0" as set.
		return ! in_array( (string) $ignore, array( '', '0' ), true );
	}

	/**
	 * Is this post an actor? Guards the write paths.
	 *
	 * @param  int $actor_id The ID to check.
	 * @return bool
	 */
	public function is_actor( int $actor_id ): bool {
		return CPT_Actors::SLUG === get_post_type( $actor_id );
	}

	/**
	 * Store a QID and how we got it.
	 *
	 * The only path for machine writes. Writes QID, source and checked-marker
	 * together, and refuses when the write-lock is set. See
	 * docs/architecture/actor-identity.md#store_qid-the-only-machine-write-path.
	 *
	 * @param  int    $actor_id The ID of the actor.
	 * @param  string $qid      The QID.
	 * @param  string $source   A Qid_Trust::SOURCE_* value.
	 * @return void
	 */
	public function store_qid( int $actor_id, string $qid, string $source ): void {
		if ( ! preg_match( '/^Q[0-9]+$/', $qid ) || ! $this->is_actor( $actor_id ) ) {
			return;
		}

		if ( $this->is_ignored( $actor_id ) ) {
			lwtv_plugin()->debug_log(
				'wikidata',
				'Refused to write ' . $qid . ' (' . $source . ') to actor ' . $actor_id . ': the QID is write-locked.'
			);

			return;
		}

		update_post_meta( $actor_id, self::META_QID, $qid );
		update_post_meta( $actor_id, self::META_SOURCE, Qid_Trust::normalise_source( $source ) );
		update_post_meta( $actor_id, self::META_CHECKED, time() );
	}

	/**
	 * Pause between requests.
	 *
	 * @param  int $sleep_ms Milliseconds.
	 * @return void
	 */
	public function throttle( int $sleep_ms = self::DEFAULT_SLEEP_MS ): void {
		usleep( max( 0, $sleep_ms ) * 1000 );
	}

	/**
	 * One GET against WikiData, with the shared status handling.
	 *
	 * @param  string $url Fully-formed URL.
	 * @return array{status: string, reason: string, body: mixed}
	 */
	private function request( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'user-agent' => self::USER_AGENT,
				'timeout'    => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'error',
				'reason' => 'request failed: ' . $response->get_error_message(),
				'body'   => null,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// Rate limited. Pause and report it as a fault, never as a no-match, so
		// nothing records a checked-marker off the back of it.
		if ( 429 === $code ) {
			usleep( self::BACKOFF_MS * 1000 );

			return array(
				'status' => 'error',
				'reason' => 'HTTP 429 rate limited -- backed off ' . ( self::BACKOFF_MS / 1000 ) . 's. Re-run with a larger --sleep.',
				'body'   => null,
			);
		}

		if ( 200 !== $code ) {
			return array(
				'status' => 'error',
				'reason' => 'HTTP ' . $code,
				'body'   => null,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return array(
				'status' => 'error',
				'reason' => 'unreadable response body',
				'body'   => null,
			);
		}

		return array(
			'status' => 'ok',
			'reason' => '',
			'body'   => $body,
		);
	}

	/**
	 * Shape a qid_from_imdb() return.
	 *
	 * @param  string $qid       The QID, or ''.
	 * @param  bool   $ambiguous Whether several items matched.
	 * @param  string $status    ok|none|error.
	 * @param  string $reason    Why, when it went wrong.
	 * @return array
	 */
	private function lookup( string $qid, bool $ambiguous, string $status, string $reason ): array {
		return array(
			'qid'       => $qid,
			'ambiguous' => $ambiguous,
			'status'    => $status,
			'reason'    => $reason,
		);
	}

	/**
	 * Shape a resolve_and_record() return.
	 *
	 * @param  string $status One of found|confirmed|conflict|none|error|skipped.
	 * @param  string $qid    The QID now stored, or ''.
	 * @param  string $was    What was stored before, or ''.
	 * @param  string $reason Explanation, when there is one.
	 * @return array
	 */
	private function outcome( string $status, string $qid, string $was, string $reason ): array {
		return array(
			'status' => $status,
			'qid'    => $qid,
			'was'    => $was,
			'reason' => $reason,
		);
	}
}
