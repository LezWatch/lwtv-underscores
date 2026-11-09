<?php
/**
 * Name: Name Match
 * Description: Pure name and title matching rules for submission triage.
 *
 * Compares what a submitter typed against sheet rows and live LWTV data. Both
 * sides are hand-typed, so matching ignores case, accents, invisible format
 * characters and non-breaking spaces. No WordPress calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Name_Match {

	/**
	 * Normalized values that mean "no real name".
	 */
	const PLACEHOLDERS = array( '', 'multiple', 'n/a', 'na', 'unknown', 'none', 'various' );

	/**
	 * Lowercase, accent-folded, invisible-character-free, single-spaced form.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	public static function normalize( string $value ): string {
		// Format characters (zero-width space, word joiner and friends).
		$value = (string) preg_replace( '/\p{Cf}/u', '', $value );
		$value = str_replace( "\u{00A0}", ' ', $value );

		if ( class_exists( '\Normalizer' ) ) {
			$decomposed = \Normalizer::normalize( $value, \Normalizer::FORM_D );
			if ( is_string( $decomposed ) ) {
				$value = (string) preg_replace( '/\p{Mn}/u', '', $decomposed );
			}
		}

		$value = mb_strtolower( $value );
		$value = (string) preg_replace( '/\s+/u', ' ', $value );

		return trim( $value );
	}

	/**
	 * Is this a "multiple" / "n/a" style non-answer?
	 *
	 * @param string $name Raw name.
	 * @return bool
	 */
	public static function is_placeholder( string $name ): bool {
		return in_array( self::normalize( $name ), self::PLACEHOLDERS, true );
	}

	/**
	 * Do two person or character names refer to the same one?
	 *
	 * Equal after normalizing, or the shorter is exactly the first word of the
	 * longer ("Satya" matches "Satya Patel", "Sam" does not match "Samantha").
	 *
	 * @param string $a First name.
	 * @param string $b Second name.
	 * @return bool
	 */
	public static function names_match( string $a, string $b ): bool {
		if ( self::is_placeholder( $a ) || self::is_placeholder( $b ) ) {
			return false;
		}

		$a = self::normalize( $a );
		$b = self::normalize( $b );

		if ( $a === $b ) {
			return true;
		}

		$a_first = explode( ' ', $a )[0];
		$b_first = explode( ' ', $b )[0];

		return $a === $b_first || $b === $a_first;
	}

	/**
	 * Do two show titles match, ignoring a leading "the" and punctuation (but not &)?
	 *
	 * @param string $a First title.
	 * @param string $b Second title.
	 * @return bool
	 */
	public static function titles_match( string $a, string $b ): bool {
		$key_a = self::title_key( $a );
		$key_b = self::title_key( $b );

		return '' !== $key_a && '' !== $key_b && $key_a === $key_b;
	}

	/**
	 * Does the free text mention the name as a whole word or phrase?
	 *
	 * @param string $text Free text.
	 * @param string $name Name to look for.
	 * @return bool
	 */
	public static function text_mentions( string $text, string $name ): bool {
		if ( self::is_placeholder( $name ) ) {
			return false;
		}

		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( self::normalize( $name ), '/' ) . '(?![\p{L}\p{N}])/u';

		return 1 === preg_match( $pattern, self::normalize( $text ) );
	}

	/**
	 * The allowed value equal to the given one once normalized.
	 *
	 * Returns the stored string, exactly as the allowed list has it, so a
	 * caller writes back the value the sheet's own validation expects.
	 *
	 * @param string   $value   Raw value.
	 * @param string[] $allowed Allowed values.
	 * @return string The matching allowed value, or ''.
	 */
	public static function exact_value( string $value, array $allowed ): string {
		$wanted = self::normalize( $value );

		foreach ( $allowed as $candidate ) {
			if ( self::normalize( (string) $candidate ) === $wanted ) {
				return (string) $candidate;
			}
		}

		return '';
	}

	/**
	 * Comparison key for a title.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	private static function title_key( string $title ): string {
		$key = self::normalize( $title );
		// Apostrophes vanish ("Don't" -> "dont"); other punctuation separates words.
		$key = str_replace( array( "'", "\u{2019}" ), '', $key );
		$key = (string) preg_replace( '/[^\p{L}\p{N}\s&]/u', ' ', $key );
		$key = (string) preg_replace( '/\s+/u', ' ', $key );
		$key = trim( $key );

		return (string) preg_replace( '/^the /u', '', $key );
	}
}
