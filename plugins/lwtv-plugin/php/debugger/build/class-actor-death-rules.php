<?php
/**
 * Should this actor have a death date, and can we trust the answer?
 *
 * The data contract, as assembled by Debugger\Actors::check_actor_death():
 *
 *     array(
 *         'our_death'  => string,  // lezactors_death, raw
 *         'our_birth'  => string,  // lezactors_birth, raw
 *         'ignored'    => bool,    // lezactors_wikidata_ignore
 *         'qid'        => string,  // a TRUSTED WikiData Q-ID, '' when we have none
 *         'source'     => string,  // Wikidata\Build\Qid_Trust::SOURCE_*, or '' when
 *                                  // we hold no Q-ID at all. A non-empty source
 *                                  // alongside an empty qid means we hold one we
 *                                  // cannot vouch for -- see UNVERIFIED below.
 *         'fetched'    => bool,    // the entity fetch returned claims
 *         'wiki_death' => string,  // P570, formatted
 *         'wiki_birth' => string,  // P569, formatted
 *     )
 *
 * Pure: every decision is made from that array and nothing else, which is what
 * makes the reasoning below testable rather than only observable in production.
 *
 * The important thing this file does is refuse to answer. A death date is a fact
 * about a real person, and the cost of the two errors is wildly asymmetric:
 * missing one is a stale page, while asserting one that is wrong is telling our
 * readers an actor is dead. So an unresolved identity, a failed fetch, and a
 * Q-ID whose birth date contradicts ours all produce their own verdict instead
 * of collapsing into "no death found" or, worse, into a death claim.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actor_Death_Rules {

	/**
	 * We already hold a death date. Nothing to look up.
	 */
	const HAS_DATE = 'has-date';

	/**
	 * An editor has ticked "Ignore WikiData Match" -- they have looked, and
	 * there is nothing to find. Not reportable, because a toggle that silences
	 * nothing is a toggle nobody will trust twice.
	 */
	const IGNORED = 'ignored';

	/**
	 * No Q-ID and no IMDb ID to find one with, so there is nothing to check
	 * against. Not evidence the actor is alive.
	 */
	const NO_IDENTITY = 'no-identity';

	/**
	 * We hold a Q-ID for this actor, but not one we can vouch for -- it came
	 * from a name search, or predates source tracking. Reported separately from
	 * NO_IDENTITY because the fix is different: there is nothing to add, only
	 * something to verify.
	 */
	const UNVERIFIED = 'unverified-identity';

	/**
	 * The IMDb ID matched more than one WikiData entity. Picking one would be a
	 * guess, so we name the problem instead.
	 *
	 * The death audit reads stored state and never resolves, so it does not
	 * produce this itself -- `wp lwtv wikidata` does. The branch stays for any
	 * caller that resolves before asking.
	 */
	const AMBIGUOUS = 'ambiguous-identity';

	/**
	 * We know who they are on WikiData but could not read the entity -- network
	 * trouble, a deleted item, or an item with no claims at all.
	 */
	const NO_DATA = 'no-wikidata';

	/**
	 * WikiData holds no death claim. Our empty field agrees with it.
	 */
	const ALIVE = 'alive';

	/**
	 * WikiData claims a death, but its birth date contradicts the one we hold.
	 * Almost certainly a different person, so the death claim is not ours to
	 * repeat.
	 */
	const SUSPECT = 'suspect-match';

	/**
	 * WikiData holds a death date we do not, and nothing contradicts it.
	 */
	const FOUND = 'death-found';

	/**
	 * Verdicts worth a human's time, and what that human should do.
	 *
	 * HAS_DATE, ALIVE and IGNORED are absent deliberately: each means there is
	 * nothing to do, and a report that lists every settled row is a report
	 * nobody reads.
	 *
	 * @var array<string, string>
	 */
	const REPORTABLE = array(
		self::FOUND       => 'Verify, then add the death date',
		self::SUSPECT     => 'Birth dates disagree -- wrong person? Check the Q-ID',
		self::UNVERIFIED  => 'Q-ID held but unverified -- run: wp lwtv wikidata backfill --reverify',
		self::AMBIGUOUS   => 'IMDb ID matches several WikiData items -- set the Q-ID by hand',
		self::NO_IDENTITY => 'No Q-ID and no usable IMDb ID -- add one to make this checkable',
		self::NO_DATA     => 'WikiData had nothing to read -- retry, or check the Q-ID',
	);

	/**
	 * Verdicts that are about our own missing metadata rather than about a
	 * possible death. They are real gaps, but there are thousands of them and
	 * they drown out the handful of rows that need acting on today, so the CLI
	 * keeps them behind a flag.
	 *
	 * @var array<string>
	 */
	const UNRESOLVED = array( self::NO_IDENTITY, self::UNVERIFIED, self::AMBIGUOUS, self::NO_DATA );

	/**
	 * The verdict for one actor.
	 *
	 * @param  array $item Collected actor data, per the contract above.
	 * @return array{verdict: string, action: string, death: string}
	 */
	public static function evaluate( array $item ): array {
		$verdict = self::verdict( $item );

		return array(
			'verdict' => $verdict,
			'action'  => self::REPORTABLE[ $verdict ] ?? '',
			'death'   => ( self::FOUND === $verdict || self::SUSPECT === $verdict )
				? (string) ( $item['wiki_death'] ?? '' )
				: '',
		);
	}

	/**
	 * Is this verdict worth showing a human?
	 *
	 * @param  string $verdict A verdict constant.
	 * @return bool
	 */
	public static function is_reportable( string $verdict ): bool {
		return isset( self::REPORTABLE[ $verdict ] );
	}

	/**
	 * Is this verdict about our missing metadata rather than a possible death?
	 *
	 * @param  string $verdict A verdict constant.
	 * @return bool
	 */
	public static function is_unresolved( string $verdict ): bool {
		return in_array( $verdict, self::UNRESOLVED, true );
	}

	/**
	 * The verdict, in the only order these checks can safely run.
	 *
	 * @param  array $item Collected actor data.
	 * @return string
	 */
	private static function verdict( array $item ): string {
		// Rule 1: we already know. Checked first so a stored date is never
		// weighed against WikiData's -- that comparison is check_actors_wikidata's
		// job, and doing it here would turn "skip" into "argue".
		if ( '' !== trim( (string) ( $item['our_death'] ?? '' ) ) ) {
			return self::HAS_DATE;
		}

		// An editor has already looked and told us there is nothing to find.
		// Checked before the identity rules so it silences the unidentifiable
		// verdicts too -- which is the entire reason the toggle exists.
		if ( ! empty( $item['ignored'] ) ) {
			return self::IGNORED;
		}

		if ( '' === trim( (string) ( $item['qid'] ?? '' ) ) ) {
			$source = trim( (string) ( $item['source'] ?? '' ) );

			if ( 'imdb-ambiguous' === $source ) {
				return self::AMBIGUOUS;
			}

			// A source with no trusted Q-ID beside it means we do hold one, we
			// just cannot say whose it is. Worth distinguishing: "verify this"
			// and "there is nothing here to verify" are different jobs.
			return ( '' === $source ) ? self::NO_IDENTITY : self::UNVERIFIED;
		}

		if ( empty( $item['fetched'] ) ) {
			return self::NO_DATA;
		}

		if ( '' === trim( (string) ( $item['wiki_death'] ?? '' ) ) ) {
			return self::ALIVE;
		}

		if ( self::birth_dates_conflict( (string) ( $item['our_birth'] ?? '' ), (string) ( $item['wiki_birth'] ?? '' ) ) ) {
			return self::SUSPECT;
		}

		return self::FOUND;
	}

	/**
	 * Do two birth dates describe different people?
	 *
	 * This is the guard on the whole command. A stored Q-ID can be wrong, and an
	 * IMDb ID can have been reassigned, and in both cases what we get back is a
	 * confident death date for a stranger. Birth date is the cheapest way to
	 * notice, since we already hold one for most actors.
	 *
	 * Unknowns are never a conflict. WikiData records plenty of birth dates to
	 * the year or the month only, and treating "1976" against "1976-05-25" as a
	 * contradiction would suppress exactly the correct matches we are looking
	 * for. Only two *known* parts that differ count.
	 *
	 * @param  string $ours   Our birth date, any of the formats we store.
	 * @param  string $theirs WikiData's birth date.
	 * @return bool
	 */
	public static function birth_dates_conflict( string $ours, string $theirs ): bool {
		$ours   = self::date_parts( $ours );
		$theirs = self::date_parts( $theirs );

		if ( '' === $ours['year'] || '' === $theirs['year'] ) {
			return false;
		}

		if ( $ours['year'] !== $theirs['year'] ) {
			return true;
		}

		foreach ( array( 'month', 'day' ) as $part ) {
			if ( '' !== $ours[ $part ] && '' !== $theirs[ $part ] && $ours[ $part ] !== $theirs[ $part ] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split a date into year / month / day, whatever shape it arrived in.
	 *
	 * Three formats are in play at once and all of them are real:
	 *
	 * - `Ymd` -- what the ACF date_picker actually writes to postmeta, despite
	 *   its return_format saying Y-m-d.
	 * - `Y-m-d` -- what WikiData returns, and what format_our_date() produces.
	 * - `m/d/Y` -- rows the ACF migration did not convert.
	 *
	 * A `00` month or day comes back empty rather than as "00", because that is
	 * WikiData saying it does not know, and an unknown must not read as a
	 * mismatch downstream.
	 *
	 * @param  string $date A date in one of the formats above.
	 * @return array{year: string, month: string, day: string} Empty strings when unparseable.
	 */
	public static function date_parts( string $date ): array {
		$date = trim( $date );

		if ( preg_match( '#^(\d{4})[-/]?(\d{2})[-/]?(\d{2})$#', $date, $match ) ) {
			// Y-m-d and Ymd.
			$year  = $match[1];
			$month = $match[2];
			$day   = $match[3];
		} elseif ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $match ) ) {
			// m/d/Y.
			$year  = $match[3];
			$month = str_pad( $match[1], 2, '0', STR_PAD_LEFT );
			$day   = str_pad( $match[2], 2, '0', STR_PAD_LEFT );
		} elseif ( preg_match( '#^(\d{4})$#', $date, $match ) ) {
			// Year only.
			$year  = $match[1];
			$month = '00';
			$day   = '00';
		} else {
			return array(
				'year'  => '',
				'month' => '',
				'day'   => '',
			);
		}

		return array(
			'year'  => $year,
			'month' => ( '00' === $month ) ? '' : $month,
			'day'   => ( '00' === $day ) ? '' : $day,
		);
	}
}
