<?php
/**
 * Name: Show Title
 * Description: Splits a show title into name and qualifier, and builds its years.
 *
 * Pure. Same-name shows are told apart by a trailing parenthetical
 * ("Charmed (2018)", "Skins (US)"). Meilisearch's exactness rule prefers a
 * whole-attribute match, so with full titles "charmed" would rank plain
 * Charmed first before any tie-breaker ran. Splitting lets same-name shows
 * tie on the name while "charmed 2018" still matches the qualifier.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Title {

	/**
	 * Split off one trailing parenthetical.
	 *
	 * Only a final "( … )" with no brackets inside, after a name whose own
	 * brackets balance, counts. Anything else keeps the whole title as the name.
	 *
	 * @param string $title Raw post title.
	 * @return array{name:string, qualifier:string}
	 */
	public static function split( string $title ): array {
		$title = trim( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( preg_match( '/^(.*\S)\s*\(([^()]*)\)$/u', $title, $parts ) && self::balanced( $parts[1] ) ) {
			return array(
				'name'      => $parts[1],
				'qualifier' => trim( $parts[2] ),
			);
		}

		return array(
			'name'      => $title,
			'qualifier' => '',
		);
	}

	/**
	 * Start and finish years as one space-separated string.
	 *
	 * The first four-digit year in each value counts. No start year gives '';
	 * a finish with no year ("current") or equal to the start is left off.
	 *
	 * @param string $start  Start value.
	 * @param string $finish Finish value.
	 * @return string
	 */
	public static function years( string $start, string $finish ): string {
		$from = self::year( $start );
		if ( '' === $from ) {
			return '';
		}

		$to = self::year( $finish );

		return ( '' === $to || $to === $from ) ? $from : $from . ' ' . $to;
	}

	/**
	 * First four-digit year in a value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function year( string $value ): string {
		return preg_match( '/\d{4}/', $value, $match ) ? $match[0] : '';
	}

	/**
	 * Whether every bracket in a string closes.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function balanced( string $text ): bool {
		$depth = 0;
		foreach ( str_split( $text ) as $char ) {
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && --$depth < 0 ) {
				return false;
			}
		}

		return 0 === $depth;
	}
}
