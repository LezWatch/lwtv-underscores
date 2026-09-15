<?php
/**
 * Who is this actor on WikiData?
 *
 * One place that owns the question, because three things now need the answer --
 * the wikidata diff view, the actor death audit, and the Q-ID backfill -- and
 * they need it to mean the same thing each time.
 *
 * There is ONE Q-ID field, lezactors_wikidata_qid, and it is the source of
 * truth. What varies is how the value got there, recorded alongside it in
 * lezactors_wikidata_qid_source:
 *
 *   1. 'manual'  -- an editor typed or pasted it. Authoritative.
 *   2. 'imdb'    -- an exact statement match on the IMDb ID (P345). One IMDb ID
 *                   means one person, so this is as good as a human checking.
 *   3. 'name'    -- a name search, taking the first hit. A guess.
 *   4. 'legacy'  -- predates source tracking, so unknowable. Untrusted.
 *
 * Only the first two produce a Q-ID an unattended process may act on. That
 * distinction living in the source, not in a second field, is the whole design:
 * a fuzzy name match is otherwise indistinguishable from a verified one the
 * moment it is stored, and the next process to read it treats a guess about a
 * stranger as an identity -- which for the death audit means telling readers a
 * living actor has died. Hence $allow_name, hence trusted_qid(), hence
 * Build\Qid_Trust.
 *
 * lezactors_wikidata_ignore is a WRITE-LOCK on that one field, nothing more.
 * Set it and store_qid() refuses, so the field becomes editable only by hand and
 * no backfill can overwrite what an editor put there. It says nothing about
 * whether the value is right; that is still the source's job. The one place it
 * carries a second meaning is the death audit, where ignore with an EMPTY Q-ID
 * is how an editor says "this person has no WikiData item" -- see
 * Debugger\Build\Actor_Death_Rules::editor_says_stop().
 *
 * The show-side equivalent is a cautionary tale rather than a model: the
 * calendar's get_tvmaze_info_show() writes a fuzzy /singlesearch hit straight
 * into lezshows_tvmaze_id and then trusts it forever, which cli-tvmaze.php
 * documents as a known hazard. This class follows cli-tvmaze.php's guarded
 * backfill instead.
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
	 * The resolved Q-ID. Machine-written.
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
	 * A Q-ID safe to draw conclusions from, or nothing.
	 *
	 * This is what an unattended process asks for. It performs no lookup and
	 * writes nothing: either we already hold a Q-ID we can vouch for, or the
	 * caller is told we cannot identify this person and must say so rather than
	 * guess.
	 *
	 * One rule, and deliberately only one: a Q-ID plus a source we trust. There
	 * is no special case for a hand-typed value because there does not need to
	 * be -- an editor typing in the field sets the source to 'manual', which is
	 * already in Qid_Trust::TRUSTED.
	 *
	 * The ignore toggle is NOT read here. It is a write-lock, not a statement
	 * about identity: it stops the machine overwriting the field, and says
	 * nothing about whether the value in it is right. An ignored actor holding a
	 * Q-ID from a trusted source is still identifiable, and refusing to hand it
	 * back would mean the death audit skipped exactly the actors an editor had
	 * taken the trouble to pin down. What ignore does mean for the audit lives in
	 * Debugger\Build\Actor_Death_Rules::editor_says_stop().
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

		// We may well hold a Q-ID here. We just cannot say whose it is.
		return array(
			'qid'    => '',
			'source' => '' === $qid ? '' : $source,
		);
	}

	/**
	 * Resolve an actor to a Q-ID, looking it up if we have to.
	 *
	 * Writes back what it finds, along with the source, so the next caller is a
	 * request lighter and a human can see and correct the match.
	 *
	 * @param  int  $actor_id   The ID of the actor.
	 * @param  bool $allow_name Whether to fall back to a name search. False for
	 *                          anything unattended -- see the class docblock.
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

		// An untrusted Q-ID we could not verify. Hand it back as-is with its
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
	 * The unit the backfill and the scheduler both run. Returns a verdict rather
	 * than a boolean because the outcomes need different handling, and the split
	 * that matters most is whether WikiData answered us:
	 *
	 *   - 'none' and 'ambiguous' are answers. Both earn a checked-marker, which
	 *     takes the actor out of routine runs until --retry-missed asks again.
	 *   - 'error' is the absence of an answer, and deliberately earns nothing at
	 *     all, so a WikiData outage cannot mark thousands of actors permanently
	 *     unresolvable.
	 *
	 * 'ambiguous' is emphatically not an error, however much it looks like one:
	 * two WikiData items carrying the same IMDb ID is a stable fact about their
	 * data, and re-asking gets the same answer forever. Conflating the two is how
	 * the scheduler's retry queue ends up looping on it. A human sees it via the
	 * death audit's AMBIGUOUS verdict, which reads resolve()'s 'imdb-ambiguous'
	 * source rather than anything this method writes.
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

		// An untrusted Q-ID we can now vouch for, or correct.
		if ( '' !== $collected['qid'] && $collected['qid'] !== $lookup['qid'] ) {
			if ( ! $dry_run ) {
				$this->store_qid( $actor_id, $lookup['qid'], Qid_Trust::SOURCE_IMDB );
			}

			return $this->outcome( 'conflict', $lookup['qid'], $collected['qid'], 'stored Q-ID disagreed with the IMDb match' );
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
	 * Uses CirrusSearch's haswbstatement, which matches a statement value rather
	 * than scoring text, so this is a lookup and not a search -- same host and
	 * same API as everything else here, no SPARQL endpoint needed.
	 *
	 * Asks for two results purely to detect ambiguity. Two or more means
	 * WikiData holds duplicate or disputed items for the ID, and "one of these
	 * two people died" is not an answer worth writing down.
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
	 * The first Q-ID WikiData returns for the actor's name.
	 *
	 * The weakest of the three lookups by a wide margin: wbsearchentities scores
	 * text, so the first hit for a common name is a coin toss between our actor,
	 * a politician, and a 19th century botanist. Fine for putting a diff in front
	 * of a human who will notice; never a basis for a conclusion.
	 *
	 * Searches on the raw post_title, not get_the_title(). The `the_title` filter
	 * runs wptexturize, which turns the apostrophe in "O'Brien" into a curly
	 * U+2019 and the hyphen in a double-barrelled name into an en dash -- none of
	 * which WikiData is indexing. html_entity_decode() on top of that covers the
	 * separate case of an entity an editor typed into the title itself.
	 *
	 * @param  int $actor_id The ID of the actor.
	 * @return string The Q-ID, or '' when nothing came back.
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
	 * @param  string $qid A Q-ID.
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
	 * A typed or pasted value, as a bare Q-ID.
	 *
	 * Accepts a pasted WikiData URL as well as a bare Q-ID. Someone copying
	 * wikidata.org/wiki/Q42 out of the address bar is the obvious way to fill the
	 * field in, and silently discarding it would be the worst outcome: the editor
	 * believes they have corrected a bad match while the audit keeps reporting it.
	 *
	 * Lives on the write path now. This used to read a separate
	 * lezactors_wikidata_qid_manual field; there is one Q-ID field, so the
	 * normalising happens once as an editor saves rather than on every read.
	 *
	 * @param  string $value Raw field value.
	 * @return string A bare Q-ID, or '' when the value holds nothing usable.
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
	 * Has an editor write-locked this actor's Q-ID?
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
	 * Store a Q-ID and how we got it.
	 *
	 * The source is written in the same breath as the Q-ID, never separately --
	 * a Q-ID with no recorded source reads as legacy, which is untrusted, so
	 * letting the two drift apart would silently downgrade a good match.
	 *
	 * Refuses outright when the ignore toggle is set. That is what makes ignore a
	 * write-lock rather than a convention: this is the only door every machine
	 * write goes through -- resolve(), resolve_and_record() and the scheduler all
	 * arrive here -- so enforcing it once means no future caller can forget, and
	 * an editor's Q-ID cannot be overwritten by a backfill they did not run.
	 *
	 * @param  int    $actor_id The ID of the actor.
	 * @param  string $qid      The Q-ID.
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
				'Refused to write ' . $qid . ' (' . $source . ') to actor ' . $actor_id . ': the Q-ID is write-locked.'
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
	 * @param  string $qid       The Q-ID, or ''.
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
	 * @param  string $qid    The Q-ID now stored, or ''.
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
