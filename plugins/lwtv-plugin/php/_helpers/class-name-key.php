<?php
/**
 * Comparable keys for a person's name.
 *
 * Editors add actors by typing a name, and the same person arrives spelled more
 * than one way: "Bae Doona" and "Doona Bae", "Moennig, Katherine", "Zoë" and
 * "Zoe", "Doo-na" and "Doona". A duplicate check keyed on the title as written
 * catches none of those, which is how one actor ends up in the database twice
 * under two slugs.
 *
 * This is the pure half of that check -- a name in, comparable keys out. It runs
 * no queries and reads no meta; see Queeries\Get_Actors_By_Name for the lookup
 * and Debugger\Build\Duplicate_Rules for the after-the-fact audit.
 *
 * Two families of key, because they answer different questions:
 *
 *   variants()  Every token, sorted. "Bae Doona" and "Doona Bae" both key to
 *               "bae doona", so surname-first entry stops mattering. Strict:
 *               every token must be present on both sides.
 *
 *   ends()      First and last token only, sorted, generational suffixes
 *               dropped. Catches the dropped middle name -- "Sarah Michelle
 *               Gellar" against "Sarah Gellar" -- which variants() cannot.
 *               Much looser, so a match here is a weaker signal and belongs in
 *               a lower confidence tier, never in a hard block.
 *
 * Hyphens are why variants() returns a list rather than one string. The
 * convention is genuinely ambiguous: a Korean given name joins ("Doo-na" is
 * "Doona") while a Western double barrel splits ("Mary-Louise" is "Mary
 * Louise"). Guessing one way misses real duplicates the other way, so both
 * readings are emitted and the caller matches on any of them.
 *
 * Accent folding is injectable for one reason: WordPress's remove_accents()
 * branches on get_locale() for German and Danish, which puts it outside what
 * tests/bootstrap.php is willing to shim. Callers inside WordPress get it by
 * default; the unit tests pass their own deterministic fold.
 *
 * Known limits, all deliberate. Romanization systems are not reconciled
 * ("Zhang Ziyi" will not meet "Chang Tzu-i"), native script does not meet its
 * romanization, and a changed name is a different name. Two different people
 * genuinely do share a name, and a token-sorted key cannot tell them apart from
 * a duplicate. Nothing here returns a verdict; it produces candidates for a
 * human to look at.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Name_Key {

	/**
	 * Generational suffixes, dropped by ends() only.
	 *
	 * variants() keeps them, so "Robert Downey Jr" and "Robert Downey Sr" stay
	 * distinct at the strict tier. ends() drops them so a genuinely dropped
	 * suffix still surfaces, at the cost of pairing a father and child who are
	 * both actors -- exactly the sort of known pair the dupe override exists to
	 * silence once.
	 *
	 * @var array<int, string>
	 */
	const SUFFIXES = array( 'jr', 'sr', 'ii', 'iii', 'iv' );

	/**
	 * Every strict key for a name.
	 *
	 * @param string        $name The name as written.
	 * @param callable|null $fold Accent folder. Defaults to remove_accents().
	 *
	 * @return array<int, string> One or two sorted-token keys, or empty for a
	 *                            name with nothing comparable in it.
	 */
	public static function variants( string $name, ?callable $fold = null ): array {
		$base = self::base( $name, $fold );

		if ( '' === $base ) {
			return array();
		}

		$keys = array();

		// Joined first, then split. See the class docblock on hyphens.
		foreach ( array( self::dehyphenate( $base ), $base ) as $reading ) {
			$tokens = self::tokens( $reading );

			if ( empty( $tokens ) ) {
				continue;
			}

			sort( $tokens );
			$keys[] = implode( ' ', $tokens );
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * The loose key: first and last token, sorted, suffixes dropped.
	 *
	 * Returned as an array so callers can treat it exactly like variants(),
	 * and so a name with nothing usable in it returns empty rather than ''.
	 *
	 * @param string        $name The name as written.
	 * @param callable|null $fold Accent folder. Defaults to remove_accents().
	 *
	 * @return array<int, string> At most one key.
	 */
	public static function ends( string $name, ?callable $fold = null ): array {
		$base = self::base( $name, $fold );

		if ( '' === $base ) {
			return array();
		}

		// A hyphen here is only ever a word boundary: we want the outermost
		// name parts, and "Ji-won Kim" should end up with "ji" not "jiwon".
		$tokens = self::tokens( str_replace( '-', ' ', $base ) );
		$tokens = self::strip_suffixes( $tokens );

		if ( empty( $tokens ) ) {
			return array();
		}

		$ends = array_unique( array( reset( $tokens ), end( $tokens ) ) );
		sort( $ends );

		return array( implode( ' ', $ends ) );
	}

	/**
	 * Does one name plausibly refer to the same person as another?
	 *
	 * The convenience wrapper for a single comparison. A lookup across many
	 * actors should key each one once and compare keys instead of calling this
	 * in a loop.
	 *
	 * @param string        $one  A name.
	 * @param string        $two  Another name.
	 * @param callable|null $fold Accent folder. Defaults to remove_accents().
	 *
	 * @return string 'full' for a strict match, 'ends' for a loose one, '' for
	 *                no match at all.
	 */
	public static function compare( string $one, string $two, ?callable $fold = null ): string {
		if ( array_intersect( self::variants( $one, $fold ), self::variants( $two, $fold ) ) ) {
			return 'full';
		}

		if ( array_intersect( self::ends( $one, $fold ), self::ends( $two, $fold ) ) ) {
			return 'ends';
		}

		return '';
	}

	/**
	 * Everything both key families do before they diverge.
	 *
	 * Drops a trailing parenthetical, folds accents, and removes apostrophes so
	 * O'Donnell keys as one token. Apostrophes join rather than split because
	 * nobody writes "O Donnell" on purpose; hyphens are left in place for the
	 * callers to read their own way.
	 *
	 * @param string        $name The name as written.
	 * @param callable|null $fold Accent folder.
	 *
	 * @return string
	 */
	private static function base( string $name, ?callable $fold ): string {
		$name = trim( $name );

		if ( '' === $name ) {
			return '';
		}

		// Drop a trailing disambiguation bracket: an actress tag, or a second
		// spelling that an editor put after the name to tell two people apart.
		$name = (string) preg_replace( '/\s*\([^)]*\)\s*$/u', '', $name );

		$name = self::fold( $name, $fold );

		// Curly and modifier-letter apostrophes first, then drop the lot.
		$name = str_replace( array( '’', '‘', 'ʼ', '`', "'" ), '', $name );

		return trim( $name );
	}

	/**
	 * Join across every dash shape a name arrives with.
	 *
	 * @param string $text Folded name.
	 *
	 * @return string
	 */
	private static function dehyphenate( string $text ): string {
		return str_replace( array( '-', '‐', '‑', '–', '—' ), '', $text );
	}

	/**
	 * Split into lowercase word tokens, dropping all remaining punctuation.
	 *
	 * \p{L} and \p{N} rather than \w so that a name still in its own script
	 * survives tokenisation instead of vanishing.
	 *
	 * @param string $text Folded name.
	 *
	 * @return array<int, string>
	 */
	private static function tokens( string $text ): array {
		$text = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text );

		if ( '' === $text ) {
			return array();
		}

		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

		return explode( ' ', $text );
	}

	/**
	 * Drop generational suffixes from the end of a token list.
	 *
	 * Only from the end, and never the last remaining token: an actor credited
	 * as a bare "Iv" would otherwise key to nothing.
	 *
	 * @param array<int, string> $tokens Lowercase tokens.
	 *
	 * @return array<int, string>
	 */
	private static function strip_suffixes( array $tokens ): array {
		$remaining = count( $tokens );

		while ( $remaining > 1 && in_array( end( $tokens ), self::SUFFIXES, true ) ) {
			array_pop( $tokens );
			--$remaining;
		}

		return array_values( $tokens );
	}

	/**
	 * Fold accents, by whatever means the caller has.
	 *
	 * @param string        $text Name.
	 * @param callable|null $fold Injected folder, or null for the default.
	 *
	 * @return string
	 */
	private static function fold( string $text, ?callable $fold ): string {
		if ( null !== $fold ) {
			return (string) call_user_func( $fold, $text );
		}

		// Always present in a WordPress runtime. The identity fallback is only
		// reachable from a test that declined to inject a fold of its own.
		if ( function_exists( 'remove_accents' ) ) {
			return (string) remove_accents( $text );
		}

		return $text;
	}
}
