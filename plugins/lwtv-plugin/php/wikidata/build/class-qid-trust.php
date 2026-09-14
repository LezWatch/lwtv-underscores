<?php
/**
 * How much do we trust a stored WikiData Q-ID, and is it worth asking again?
 *
 * A Q-ID in postmeta is not self-describing. It might have been typed by an
 * editor who checked, derived from an exact IMDb statement match, or picked by a
 * fuzzy name search that returned a politician with the same name as an actress.
 * All three look identical once written, and that is the whole problem this file
 * exists to solve: `lezactors_wikidata_qid_source` records which, and nothing
 * unattended is allowed to act on the fuzzy kind.
 *
 * Pure. Both decisions here -- "can this be trusted" and "is this worth an API
 * call" -- are made from an array and nothing else, so the CLI backfill, the
 * scheduler task and the death audit all reach the same answer and the answer is
 * testable without a WordPress runtime.
 *
 * @package LWTV
 */

namespace LWTV\Wikidata\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qid_Trust {

	/**
	 * An editor typed this Q-ID into the manual field. Authoritative; nothing
	 * overwrites it.
	 */
	const SOURCE_MANUAL = 'manual';

	/**
	 * Resolved by an exact match on the IMDb ID (P345). One IMDb ID means one
	 * person, so this is as good as a human having checked.
	 */
	const SOURCE_IMDB = 'imdb';

	/**
	 * Resolved by a name search. A guess, and recorded as one.
	 */
	const SOURCE_NAME = 'name';

	/**
	 * A Q-ID that predates source tracking.
	 *
	 * Deliberately NOT trusted. The existing column is a mix of hand-entered
	 * IDs and old `wbsearchentities` first-hits with no way to tell them apart,
	 * and guessing generously here is exactly the mistake that would put a
	 * fuzzy match behind a death claim. The backfill's verify pass upgrades
	 * these to SOURCE_IMDB when an IMDb lookup agrees with the stored value,
	 * so the untrusted set shrinks as real evidence arrives rather than by
	 * assumption.
	 */
	const SOURCE_LEGACY = 'legacy';

	/**
	 * Sources an unattended process may act on.
	 *
	 * @var array<string>
	 */
	const TRUSTED = array( self::SOURCE_MANUAL, self::SOURCE_IMDB );

	/**
	 * Is a Q-ID from this source safe to draw conclusions from?
	 *
	 * @param  string $source A SOURCE_* value, or '' for an untracked Q-ID.
	 * @return bool
	 */
	public static function is_trusted( string $source ): bool {
		return in_array( self::normalise_source( $source ), self::TRUSTED, true );
	}

	/**
	 * A stored source value, with anything unrecognised read as legacy.
	 *
	 * An empty string means the Q-ID was written before we tracked sources. An
	 * unrecognised string means something wrote a value we do not know about,
	 * which gets the same cautious treatment rather than the benefit of the
	 * doubt.
	 *
	 * @param  string $source Raw meta value.
	 * @return string
	 */
	public static function normalise_source( string $source ): string {
		$source = strtolower( trim( $source ) );

		$known = array( self::SOURCE_MANUAL, self::SOURCE_IMDB, self::SOURCE_NAME, self::SOURCE_LEGACY );

		return in_array( $source, $known, true ) ? $source : self::SOURCE_LEGACY;
	}

	/**
	 * Is this actor worth spending a WikiData request on?
	 *
	 * The contract:
	 *
	 *     array(
	 *         'qid'          => string,  // lezactors_wikidata_qid
	 *         'source'       => string,  // lezactors_wikidata_qid_source
	 *         'manual_qid'   => string,  // lezactors_wikidata_qid_manual
	 *         'ignored'      => bool,    // lezactors_wikidata_ignore
	 *         'checked'      => int,     // lezactors_wikidata_checked, 0 = never
	 *         'imdb'         => string,  // a validated nm-prefixed ID, or ''
	 *         'retry_missed' => bool,    // --retry-missed
	 *         'reverify'     => bool,    // --reverify: re-check untrusted Q-IDs
	 *     )
	 *
	 * @param  array $item Per the contract above.
	 * @return array{check: bool, reason: string}
	 */
	public static function should_check( array $item ): array {
		$qid        = trim( (string) ( $item['qid'] ?? '' ) );
		$manual     = trim( (string) ( $item['manual_qid'] ?? '' ) );
		$source     = self::normalise_source( (string) ( $item['source'] ?? '' ) );
		$imdb       = trim( (string) ( $item['imdb'] ?? '' ) );
		$checked    = (int) ( $item['checked'] ?? 0 );
		$has_manual = ( '' !== $manual );

		// An editor has said stop asking. Checked before everything else,
		// because it is the one signal that means "I have already looked".
		if ( ! empty( $item['ignored'] ) ) {
			return self::no( $has_manual ? 'set by hand' : 'ignored by an editor' );
		}

		// A manual Q-ID outside the ignore toggle still wins: there is nothing
		// to resolve when someone has already told us the answer.
		if ( $has_manual ) {
			return self::no( 'set by hand' );
		}

		if ( '' !== $qid && self::is_trusted( $source ) ) {
			return self::no( 'already resolved (' . $source . ')' );
		}

		// Everything past here needs an IMDb ID, because the exact statement
		// match is the only lookup allowed to write a trusted Q-ID.
		if ( '' === $imdb ) {
			return self::no( '' === $qid ? 'no IMDb ID to ask with' : 'unverified, and no IMDb ID to verify it with' );
		}

		// A Q-ID we hold but cannot vouch for. Worth re-resolving, but only when
		// asked -- on a routine run these are not gaps, and re-checking every one
		// of them would make a backfill of the actual blanks impossible to size.
		if ( '' !== $qid ) {
			return empty( $item['reverify'] )
				? self::no( 'unverified (' . $source . ') -- --reverify to check' )
				: self::yes( 'verify stored Q-ID against IMDb' );
		}

		// We asked before and WikiData had nothing. Not a fault, and not worth
		// repeating on every run.
		if ( $checked > 0 && empty( $item['retry_missed'] ) ) {
			return self::no( 'checked before, no match' );
		}

		return self::yes( 'no Q-ID, IMDb ID available' );
	}

	/**
	 * @param  string $reason Why not.
	 * @return array{check: bool, reason: string}
	 */
	private static function no( string $reason ): array {
		return array(
			'check'  => false,
			'reason' => $reason,
		);
	}

	/**
	 * @param  string $reason Why.
	 * @return array{check: bool, reason: string}
	 */
	private static function yes( string $reason ): array {
		return array(
			'check'  => true,
			'reason' => $reason,
		);
	}
}
