<?php
/**
 * Should this actor have a death date, and can we trust the answer?
 *
 * The data contract, as assembled by Debugger\Actors::check_actor_death():
 *
 *     array(
 *         'our_death'  => string,  // lezactors_death, raw
 *         'our_birth'  => string,  // lezactors_birth, raw
 *         'ignored'    => bool,    // editor_says_stop(): locked AND no QID
 *         'qid'        => string,  // a TRUSTED WikiData QID, '' when we have none
 *         'source'     => string,  // Wikidata\Build\Qid_Trust::SOURCE_*, or '' when
 *                                  // we hold no QID at all. A non-empty source
 *                                  // alongside an empty qid means we hold one we
 *                                  // cannot vouch for -- see UNVERIFIED below.
 *         'fetched'    => bool,    // the entity fetch returned claims
 *         'wiki_death' => string,  // P570, formatted
 *         'wiki_birth' => string,  // P569, formatted
 *     )
 *
 * Pure: every decision is made from that array and nothing else.
 *
 * Refuses rather than guesses: anything uncertain gets its own verdict, never a
 * death claim. See docs/architecture/actor-identity.md#death-audit.
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
	 * Locked with an empty QID: an editor has said there is no WikiData item.
	 * Not reportable. See editor_says_stop().
	 */
	const IGNORED = 'ignored';

	/**
	 * No QID and no IMDb ID to find one with, so there is nothing to check
	 * against. Not evidence the actor is alive.
	 */
	const NO_IDENTITY = 'no-identity';

	/**
	 * We hold a QID for this actor, but not one we can vouch for -- it came
	 * from a name search, or predates source tracking. Reported separately from
	 * NO_IDENTITY because the fix is different: there is nothing to add, only
	 * something to verify.
	 */
	const UNVERIFIED = 'unverified-identity';

	/**
	 * The IMDb ID matched more than one WikiData entity. Picking one would be a
	 * guess, so we name the problem instead.
	 *
	 * The death audit never resolves, so it does not produce this itself; the
	 * branch stays for any caller that resolves before asking.
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
	 * HAS_DATE, ALIVE and IGNORED are absent deliberately: nothing to do.
	 *
	 * A method rather than a const so the advice can go through __(). The WP-CLI
	 * command in the UNVERIFIED line stays outside the translatable string.
	 *
	 * @return array<string, string>
	 */
	public static function reportable(): array {
		return array(
			self::FOUND       => __( 'Verify, then add the death date', 'lwtv' ),
			self::SUSPECT     => __( 'Birth dates disagree -- wrong person? Check the QID', 'lwtv' ),
			self::UNVERIFIED  => sprintf(
				/* translators: %s: a WP-CLI command to run, not translatable. */
				__( 'QID held but unverified -- run: %s', 'lwtv' ),
				'wp lwtv wikidata backfill --reverify'
			),
			self::AMBIGUOUS   => __( 'IMDb ID matches several WikiData items -- set the QID by hand', 'lwtv' ),
			self::NO_IDENTITY => __( 'No QID and no usable IMDb ID -- add one to make this checkable', 'lwtv' ),
			self::NO_DATA     => __( 'WikiData had nothing to read -- retry, or check the QID', 'lwtv' ),
		);
	}

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
	 * Does the write-lock mean stop checking this actor?
	 *
	 * Only when the QID is empty: that is how an editor says "no WikiData item".
	 * Locked with a QID audits normally on it. See
	 * docs/architecture/actor-identity.md#editor_says_stop.
	 *
	 * @param  bool   $ignored The lezactors_wikidata_ignore write-lock.
	 * @param  string $qid     The stored QID, or '' when the field is empty.
	 * @return bool   True when the audit should treat the actor as settled.
	 */
	public static function editor_says_stop( bool $ignored, string $qid ): bool {
		return $ignored && '' === trim( $qid );
	}

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
			'action'  => self::reportable()[ $verdict ] ?? '',
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
		return isset( self::reportable()[ $verdict ] );
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

			// A source with no trusted QID beside it means we do hold one, we
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
	 * The guard against a wrong QID. Only two *known* parts that differ count;
	 * a year-only or month-only date is never a conflict. See
	 * docs/architecture/actor-identity.md#birth-date-guard.
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
	 * Reads `Ymd` (ACF postmeta), `Y-m-d` (WikiData) and `m/d/Y` (unmigrated
	 * rows). A `00` month or day is unknown and comes back empty. See
	 * docs/architecture/actor-identity.md#birth-date-guard.
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
