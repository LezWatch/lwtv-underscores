<?php
/**
 * IMDb Canonical ID comparison.
 *
 * IMDb reassigns title and name IDs, leaving the previous one working as a
 * redirect. That makes a stale ID invisible to a human -- the link still opens
 * the right page -- while silently breaking every exact-match API lookup keyed
 * on it. TVMaze's /lookup/shows?imdb= is one of those: it matches against the
 * single canonical ID TVMaze stores, so a stale alias returns 404.
 *
 * Worked example: Only Murders in the Building. TVMaze holds tt11691774, we
 * held tt12851524, and both resolve to the same show on imdb.com. Nothing about
 * our value looks wrong -- it is well-formed and it works in a browser.
 *
 * Detection therefore cannot come from IMDb. It comes from the third parties
 * that already store a canonical IMDb ID and whose IDs we already hold: TVMaze
 * for shows, TMDB for actors. This class is the pure comparison; the HTTP lives
 * in Schedulers\Imdb_Verify_Task.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Imdb_Canonical {

	/**
	 * Our ID and the oracle's agree. Nothing to do.
	 */
	const MATCH = 'match';

	/**
	 * They disagree, and both are well-formed: ours has probably gone stale.
	 */
	const STALE = 'stale';

	/**
	 * The oracle has no IMDb ID recorded, so it cannot judge ours either way.
	 */
	const NO_ORACLE = 'no-oracle';

	/**
	 * We have no usable ID. Already reported by Debug_Tool::validate_imdb().
	 */
	const NOT_SET = 'not-set';

	/**
	 * Reduce an IMDb reference to a bare, comparable ID.
	 *
	 * Accepts a full imdb.com URL as well as a bare ID, because editors paste
	 * URLs into ID fields and comparing a URL against a bare ID would report a
	 * false mismatch on every one of them.
	 *
	 * @param mixed $value An IMDb ID, an imdb.com URL, or anything else.
	 *
	 * @return string Lowercased tt/nm ID, or '' when there is nothing usable.
	 */
	public static function normalise( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		// Pull the ID out of anything that contains one, which covers both a bare
		// ID and a /title/ or /name/ URL.
		if ( 1 !== preg_match( '/\b((?:tt|nm)\d{6,10})\b/', $value, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Compare our stored ID against a canonical one from a third party.
	 *
	 * @param mixed $ours   Our stored IMDb value.
	 * @param mixed $theirs The oracle's IMDb value.
	 *
	 * @return string One of the class constants.
	 */
	public static function verdict( $ours, $theirs ): string {
		$ours   = self::normalise( $ours );
		$theirs = self::normalise( $theirs );

		// A missing or malformed value of ours is not a staleness question.
		// Debug_Tool::validate_imdb() already reports it, and flagging it here
		// too would put two problems on one row for a single fault.
		if ( '' === $ours ) {
			return self::NOT_SET;
		}

		// The oracle has the record but never linked IMDb. That says nothing
		// about our ID, so it must not read as stale.
		if ( '' === $theirs ) {
			return self::NO_ORACLE;
		}

		return ( $ours === $theirs ) ? self::MATCH : self::STALE;
	}

	/**
	 * The one-line question the debugger and the scheduler both ask.
	 *
	 * @param mixed $ours   Our stored IMDb value.
	 * @param mixed $theirs The oracle's IMDb value.
	 *
	 * @return bool True only for a genuine disagreement between two usable IDs.
	 */
	public static function is_stale( $ours, $theirs ): bool {
		return self::STALE === self::verdict( $ours, $theirs );
	}
}
