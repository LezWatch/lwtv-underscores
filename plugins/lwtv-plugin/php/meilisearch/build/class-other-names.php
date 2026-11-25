<?php
/**
 * Name: Other Names
 * Description: Reads an actor's other names out of their old excerpt.
 *
 * Pure. Before the Other Names field existed, editors listed an actor's
 * former, married and stage names in the excerpt ("Additional Notes") so
 * Jetpack Search could find them. Most excerpts are one name or a comma
 * list; some are prose. This tells the two apart for
 * `wp lwtv migrate othernames`, erring towards a human: anything that
 * doesn't look like a plain list of names comes back null.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Other_Names {

	/**
	 * Longest piece, in words, still read as a name.
	 */
	const MAX_WORDS = 5;

	/**
	 * Text that is never a list of names: a URL, a quote mark, a colon, or a
	 * sentence break (a word of two or more letters, a full stop, then a
	 * capital, so an initial like "E. Page" still passes).
	 */
	const NOT_A_LIST = array(
		'#https?://|www\.#i',
		'/["“”]/u',
		'/:/',
		'/\p{L}{2,}\.\s+\p{Lu}/u',
	);

	/**
	 * Names to move, array() for blank text, or null when a human should look.
	 *
	 * @param string $excerpt Raw excerpt.
	 * @return string[]|null
	 */
	public static function from_excerpt( string $excerpt ): ?array {
		$text = html_entity_decode( $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		foreach ( self::NOT_A_LIST as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return null;
			}
		}

		$names = array();
		foreach ( preg_split( '/[\r\n,]+/', $text ) ?: array() as $piece ) {
			$piece = trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $piece ) );
			if ( '' === $piece ) {
				continue;
			}

			$words = explode( ' ', $piece );
			// Over-long, or a lone "Jr." split off "Robert Downey, Jr.".
			if ( count( $words ) > self::MAX_WORDS || ( 1 === count( $words ) && str_ends_with( $piece, '.' ) ) ) {
				return null;
			}

			$names[ $piece ] = $piece;
		}

		return array_values( $names );
	}

	/**
	 * Whether stored Other Names rows hold exactly these names, so the
	 * migration only clears an excerpt once its names are safely saved.
	 *
	 * @param mixed    $rows  Raw repeater rows read back after the write.
	 * @param string[] $names Names that were written.
	 * @return bool
	 */
	public static function saved( $rows, array $names ): bool {
		return ! empty( $names ) && Actor_Document::other_names( $rows, '' ) === array_values( $names );
	}
}
