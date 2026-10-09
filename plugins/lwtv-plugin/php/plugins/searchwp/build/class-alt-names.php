<?php
/**
 * Name: Alt Names
 * Description: Pure matcher for the live search "also known as" line.
 *
 * Given a show's alternate-name repeater rows and the search term, returns
 * the alternate names that explain why the show matched. No WordPress calls,
 * so it is unit-testable; the live search template gathers the inputs.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\SearchWP\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alt_Names {

	/**
	 * Repeater sub-field holding the name.
	 */
	const NAME_KEY = 'lezshows_alt_show_name';

	/**
	 * Repeater sub-field holding the language code.
	 */
	const TYPE_KEY = 'type';

	/**
	 * Alternate names that contain every word of the search term.
	 *
	 * Matching is case-insensitive and word-order-insensitive. Pass
	 * remove_accents (or any string fold) as $fold to also ignore accents.
	 *
	 * @param mixed         $rows      Repeater rows from get_field(); false/null when empty.
	 * @param string        $term      The search term.
	 * @param array         $languages Code => label map, from Languages::all_languages().
	 * @param callable|null $fold      Optional accent fold applied before comparing.
	 * @return array<int, array{name: string, language: string}>
	 */
	public static function matching( $rows, string $term, array $languages = array(), ?callable $fold = null ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$words = self::words( $term, $fold );
		if ( empty( $words ) ) {
			return array();
		}

		$found = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[ self::NAME_KEY ] ) ) {
				continue;
			}

			$name = trim( (string) $row[ self::NAME_KEY ] );
			if ( '' === $name ) {
				continue;
			}

			$haystack = self::normalize( $name, $fold );
			if ( isset( $found[ $haystack ] ) ) {
				continue;
			}

			foreach ( $words as $word ) {
				if ( false === mb_strpos( $haystack, $word ) ) {
					continue 2;
				}
			}

			$found[ $haystack ] = array(
				'name'     => $name,
				'language' => self::language_label( $row[ self::TYPE_KEY ] ?? null, $languages ),
			);
		}

		return array_values( $found );
	}

	/**
	 * Short English label for a language code.
	 *
	 * "Spanish (Mexico) - español (México)" becomes "Spanish, Mexico", so the
	 * label can sit inside parentheses without nesting them.
	 *
	 * @param mixed $code      Language code.
	 * @param array $languages Code => label map.
	 * @return string Empty when the code is missing or unknown.
	 */
	public static function language_label( $code, array $languages ): string {
		if ( ! is_string( $code ) || '' === $code || ! isset( $languages[ $code ] ) ) {
			return '';
		}

		$label = explode( ' - ', (string) $languages[ $code ], 2 )[0];

		if ( preg_match( '/^(.+?)\s*\((.+)\)$/u', $label, $parts ) ) {
			$label = $parts[1] . ', ' . $parts[2];
		}

		return trim( $label );
	}

	/**
	 * Split a search term into normalized words.
	 *
	 * @param string        $term Search term.
	 * @param callable|null $fold Optional accent fold.
	 * @return string[]
	 */
	private static function words( string $term, ?callable $fold ): array {
		$words = preg_split( '/\s+/u', self::normalize( $term, $fold ), -1, PREG_SPLIT_NO_EMPTY );

		return $words ? array_values( array_unique( $words ) ) : array();
	}

	/**
	 * Lowercase and optionally accent-fold a string for comparison.
	 *
	 * @param string        $text Text.
	 * @param callable|null $fold Optional accent fold.
	 * @return string
	 */
	private static function normalize( string $text, ?callable $fold ): string {
		if ( null !== $fold ) {
			$text = (string) $fold( $text );
		}

		return mb_strtolower( trim( $text ), 'UTF-8' );
	}
}
