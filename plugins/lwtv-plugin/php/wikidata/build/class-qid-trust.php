<?php
/**
 * How much do we trust a stored WikiData QID, and is it worth asking again?
 *
 * Pure, so the CLI backfill, the scheduler and the death audit reach the same
 * answer. See docs/architecture/actor-identity.md#sources-and-trust.
 *
 * @package LWTV
 */

namespace LWTV\Wikidata\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qid_Trust {

	/**
	 * An editor typed or pasted this QID into the field. Authoritative.
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
	 * A QID that predates source tracking. Deliberately NOT trusted; --reverify
	 * upgrades it to SOURCE_IMDB when the IMDb lookup agrees.
	 */
	const SOURCE_LEGACY = 'legacy';

	/**
	 * Sources an unattended process may act on.
	 *
	 * @var array<string>
	 */
	const TRUSTED = array( self::SOURCE_MANUAL, self::SOURCE_IMDB );

	/**
	 * Is a QID from this source safe to draw conclusions from?
	 *
	 * @param  string $source A SOURCE_* value, or '' for an untracked QID.
	 * @return bool
	 */
	public static function is_trusted( string $source ): bool {
		return in_array( self::normalise_source( $source ), self::TRUSTED, true );
	}

	/**
	 * A stored source value, with anything unrecognised read as legacy.
	 *
	 * An empty string means the QID was written before we tracked sources. An
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
	 *         'ignored'      => bool,    // lezactors_wikidata_ignore (write-lock)
	 *         'checked'      => int,     // lezactors_wikidata_checked, 0 = never
	 *         'imdb'         => string,  // a validated nm-prefixed ID, or ''
	 *         'retry_missed' => bool,    // --retry-missed
	 *         'reverify'     => bool,    // --reverify: re-check untrusted QIDs
	 *     )
	 *
	 * @param  array $item Per the contract above.
	 * @return array{check: bool, reason: string}
	 */
	public static function should_check( array $item ): array {
		$qid     = trim( (string) ( $item['qid'] ?? '' ) );
		$source  = self::normalise_source( (string) ( $item['source'] ?? '' ) );
		$imdb    = trim( (string) ( $item['imdb'] ?? '' ) );
		$checked = (int) ( $item['checked'] ?? 0 );

		// Write-locked. Checked before everything else: there is no point
		// spending a request on an answer store_qid() would refuse to write.
		if ( ! empty( $item['ignored'] ) ) {
			return self::no( 'write-locked by an editor' );
		}

		// A hand-typed QID needs no special case here. Editing the field sets
		// the source to 'manual', which is trusted, so the next branch already
		// leaves it alone.
		if ( '' !== $qid && self::is_trusted( $source ) ) {
			return self::no( 'already resolved (' . $source . ')' );
		}

		// Everything past here needs an IMDb ID, because the exact statement
		// match is the only lookup allowed to write a trusted QID.
		if ( '' === $imdb ) {
			return self::no( '' === $qid ? 'no IMDb ID to ask with' : 'unverified, and no IMDb ID to verify it with' );
		}

		// A QID we hold but cannot vouch for. Worth re-resolving, but only when
		// asked -- on a routine run these are not gaps, and re-checking every one
		// of them would make a backfill of the actual blanks impossible to size.
		if ( '' !== $qid ) {
			return empty( $item['reverify'] )
				? self::no( 'unverified (' . $source . ') -- --reverify to check' )
				: self::yes( 'verify stored QID against IMDb' );
		}

		// We asked before and WikiData had nothing. Not a fault, and not worth
		// repeating on every run.
		if ( $checked > 0 && empty( $item['retry_missed'] ) ) {
			return self::no( 'checked before, no match' );
		}

		return self::yes( 'no QID, IMDb ID available' );
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
