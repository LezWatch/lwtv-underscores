<?php
/**
 * Comparable keys for a person's name. Pure: a name in, keys out.
 *
 * variants() is the strict tier (every token, sorted); ends() is the loose tier
 * (first and last token). Candidates for a human, never a verdict. See
 * docs/architecture/duplicate-detection.md#name-keys.
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
	 * variants() keeps them, so Sr and Jr stay distinct at the strict tier.
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

		$readings     = array( $base );
		$dehyphenated = self::dehyphenate( $base );
		if ( $dehyphenated !== $base ) {
			$readings[] = $dehyphenated;
		}

		// Joined first, then split. See
		// docs/architecture/duplicate-detection.md#hyphens.
		foreach ( $readings as $reading ) {
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
