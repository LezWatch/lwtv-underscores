<?php
/**
 * Name: Actor Document
 * Description: Builds an actor's Meilisearch document.
 *
 * Pure: the caller gathers published character and show titles and the raw
 * meta; this decides what goes in. The document is readable through the
 * public search key, so:
 *
 * - other names (former, married, stage) go in to be matched, but the
 *   index's displayedAttributes leave them out of every result;
 * - dates are years only, and a hide_dob actor has no birth year;
 * - socials, IDs and privacy notes are never passed in at all.
 *
 * Same-name actors tie on `name`, so the ranking rules fall through to
 * queer, then character_count: Mika's call, 2026-11-25.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actor_Document {

	/**
	 * Other-names repeater sub-field, by field key and by name.
	 */
	const OTHER_NAME_KEYS = array( 'field_lwtv_lezactors_other_name', 'lezactors_other_name' );

	/**
	 * Build the document.
	 *
	 * @param int      $id         Actor post ID.
	 * @param string   $title      Raw actor title.
	 * @param mixed    $other_rows lezactors_other_names repeater rows; anything else for none.
	 * @param array    $characters Titles of the actor's published characters.
	 * @param array    $shows      Titles of those characters' published shows.
	 * @param mixed    $birth      lezactors_birth value.
	 * @param mixed    $death      lezactors_death value.
	 * @param mixed    $privacy    lezactors_make_option_private value.
	 * @param string   $bio        Bio as plain text (blocks, shortcodes and tags already stripped).
	 * @param mixed    $queer      lezactors_queer value.
	 * @param mixed    $char_count lezactors_char_count value.
	 * @param string   $url        Permalink.
	 * @param string   $thumb      Thumbnail URL, or ''.
	 * @return array<string, mixed>
	 */
	public static function build( int $id, string $title, $other_rows, array $characters, array $shows, $birth, $death, $privacy, string $bio, $queer, $char_count, string $url, string $thumb ): array {
		$name     = self::text( $title );
		$hide_dob = is_array( $privacy ) && in_array( 'hide_dob', $privacy, true );
		$years    = array_unique( array_filter( array( $hide_dob ? '' : self::year( $birth ), self::year( $death ) ) ) );

		return array(
			'id'              => $id,
			'name'            => $name,
			'other_names'     => self::other_names( $other_rows, $name ),
			'characters'      => self::names( $characters ),
			'shows'           => self::names( $shows ),
			'years'           => implode( ' ', $years ),
			'bio'             => self::text( $bio ),
			'queer'           => empty( $queer ) ? 0 : 1,
			'character_count' => is_numeric( $char_count ) ? max( 0, (int) $char_count ) : 0,
			'url'             => $url,
			'thumb'           => $thumb,
		);
	}

	/**
	 * The four-digit year a stored date starts with, or ''.
	 *
	 * Covers Y-m-d, Y-n-j (1975-1-25) and ACF's raw Ymd.
	 *
	 * @param mixed $value Stored date.
	 * @return string
	 */
	public static function year( $value ): string {
		if ( ! is_scalar( $value ) || ! preg_match( '/^(\d{4})(?:\d{4}$|-)/', trim( (string) $value ), $match ) || '0000' === $match[1] ) {
			return '';
		}

		return $match[1];
	}

	/**
	 * Other names from the repeater, raw (field keys) or formatted (names),
	 * leaving out the actor's current name.
	 *
	 * @param mixed  $rows Repeater rows; anything else for none.
	 * @param string $name Current name, already decoded.
	 * @return string[]
	 */
	public static function other_names( $rows, string $name ): array {
		$values = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( self::OTHER_NAME_KEYS as $key ) {
				if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
					$values[] = (string) $row[ $key ];
					break;
				}
			}
		}

		$current = mb_strtolower( $name );

		return array_values( array_filter( self::names( $values ), fn( $other ) => mb_strtolower( $other ) !== $current ) );
	}

	/**
	 * Decoded, collapsed, non-empty, de-duplicated strings, in order.
	 *
	 * @param array $values Raw values.
	 * @return string[]
	 */
	private static function names( array $values ): array {
		$out = array();
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$text = self::text( (string) $value );
			if ( '' !== $text ) {
				$out[ $text ] = $text;
			}
		}

		return array_values( $out );
	}

	/**
	 * Plain text: entities decoded, non-breaking spaces and runs of
	 * whitespace collapsed to one space, trimmed.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}
}
