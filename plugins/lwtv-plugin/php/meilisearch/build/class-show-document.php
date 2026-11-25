<?php
/**
 * Name: Show Document
 * Description: Builds a show's Meilisearch document.
 *
 * Pure: the caller gathers the raw values, published character titles and
 * actor visibility; this decides what goes in. Only visible actors are named,
 * because the document is readable through the public search key.
 *
 * Same-name shows tie on `name` (see Show_Title), so the index's ranking
 * rules fall through to worth_it_rank, then score: Mika's call, 2026-11-25.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Document {

	/**
	 * Worth It tie-breaker: higher ranks first. TBD ("not rated yet") sits
	 * above an editorial No.
	 */
	const WORTH_IT_RANKS = array(
		'Yes' => 3,
		'Meh' => 2,
		'TBD' => 1,
		'No'  => 0,
	);

	/**
	 * Alternate-name repeater sub-field, by field key and by name.
	 */
	const ALT_NAME_KEYS = array( 'field_lwtv_lezshows_alt_show_name', 'lezshows_alt_show_name' );

	/**
	 * Build the document.
	 *
	 * @param int      $id         Show post ID.
	 * @param string   $title      Raw show title.
	 * @param mixed    $alt_rows   lezshows_show_names repeater rows; anything else for none.
	 * @param array    $airdates   array( 'start' => string, 'finish' => string ), from Shows\Airdates::get().
	 * @param mixed    $worth_it   lezshows_worthit_rating value.
	 * @param mixed    $score      lezshows_the_score value.
	 * @param string[] $characters Titles of published characters on the show.
	 * @param array    $actors     id => array( 'title' => string, 'visible' => bool ) for their actors.
	 * @param string[] $stations   Station term names.
	 * @param string[] $nations    Nation term names.
	 * @param string   $url        Permalink.
	 * @param string   $thumb      Thumbnail URL, or ''.
	 * @return array<string, mixed>
	 */
	public static function build( int $id, string $title, $alt_rows, array $airdates, $worth_it, $score, array $characters, array $actors, array $stations, array $nations, string $url, string $thumb ): array {
		$title    = Show_Title::split( $title );
		$worth_it = ( is_string( $worth_it ) && isset( self::WORTH_IT_RANKS[ $worth_it ] ) ) ? $worth_it : '';

		$visible = array();
		foreach ( $actors as $actor ) {
			if ( is_array( $actor ) && isset( $actor['title'] ) && ! empty( $actor['visible'] ) ) {
				$visible[] = $actor['title'];
			}
		}

		return array(
			'id'            => $id,
			'name'          => $title['name'],
			'qualifier'     => $title['qualifier'],
			'alt_names'     => self::alt_names( $alt_rows ),
			'characters'    => self::names( $characters ),
			'actors'        => self::names( $visible ),
			'years'         => Show_Title::years( (string) ( $airdates['start'] ?? '' ), (string) ( $airdates['finish'] ?? '' ) ),
			'worth_it'      => $worth_it,
			'worth_it_rank' => '' === $worth_it ? 0 : self::WORTH_IT_RANKS[ $worth_it ],
			'score'         => is_numeric( $score ) ? max( 0.0, min( 100.0, (float) $score ) ) : 0.0,
			'stations'      => self::names( $stations ),
			'nations'       => self::names( $nations ),
			'url'           => $url,
			'thumb'         => $thumb,
		);
	}

	/**
	 * Alternate names from the repeater, raw (field keys) or formatted (names).
	 *
	 * @param mixed $rows Repeater rows; anything else for none.
	 * @return string[]
	 */
	public static function alt_names( $rows ): array {
		$names = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( self::ALT_NAME_KEYS as $key ) {
				if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) {
					$names[] = (string) $row[ $key ];
					break;
				}
			}
		}

		return self::names( $names );
	}

	/**
	 * Decoded, trimmed, non-empty, de-duplicated strings, in order.
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

			$text = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $text ) {
				$out[ $text ] = $text;
			}
		}

		return array_values( $out );
	}
}
