<?php
/**
 * Name: Typo Tolerance
 * Description: Per-index typo tolerance: defaults, checks, the Meilisearch payload and the live comparison.
 *
 * Pure. A block is { enabled, oneTypo, twoTypos, disableOnWords,
 * disableOnAttributes, disableOnNumbers }. The defaults are what search does
 * today: 1 typo from 5 letters, 2 from 9, and shows never allow typos on
 * nations. Bad values are refused, never clamped; one bad field in a stored
 * block means the defaults for that whole block.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Typo_Tolerance {

	const ONE_MIN   = 1;
	const ONE_MAX   = 8;
	const TWO_MAX   = 20;
	const WORDS_MAX = 100;
	const WORD_MAX  = 100;

	const FIELDS = array( 'enabled', 'oneTypo', 'twoTypos', 'disableOnWords', 'disableOnAttributes', 'disableOnNumbers' );

	/**
	 * Shows never allow typos on these: "canaan" must not find every
	 * Canadian show, so a country name is matched exactly.
	 */
	const SHOWS_NO_TYPO = array( 'nations' );

	/**
	 * The block with nothing saved, in FIELDS order.
	 *
	 * @param string $type Index type.
	 * @return array{enabled: bool, oneTypo: int, twoTypos: int, disableOnWords: string[], disableOnAttributes: string[], disableOnNumbers: bool}
	 */
	public static function defaults( string $type ): array {
		return array(
			'enabled'             => true,
			'oneTypo'             => 5,
			'twoTypos'            => 9,
			'disableOnWords'      => array(),
			'disableOnAttributes' => 'shows' === $type ? self::SHOWS_NO_TYPO : array(),
			'disableOnNumbers'    => false,
		);
	}

	/**
	 * Check a block from the page. A missing field takes this type's default;
	 * unknown keys are ignored. Any error means no values.
	 *
	 * @param string   $type       Index type.
	 * @param mixed    $input      Raw input.
	 * @param string[] $searchable The index's searchable attributes.
	 * @return array{values: array, errors: array<string, string>}
	 */
	public static function validate( string $type, mixed $input, array $searchable ): array {
		if ( ! is_array( $input ) || ( ! empty( $input ) && array_is_list( $input ) ) ) {
			return array(
				'values' => array(),
				'errors' => array( 'settings' => 'Send the typo settings as an object.' ),
			);
		}

		$block  = array_merge( self::defaults( $type ), array_intersect_key( $input, array_flip( self::FIELDS ) ) );
		$errors = array();

		foreach ( array( 'enabled', 'disableOnNumbers' ) as $flag ) {
			if ( ! is_bool( $block[ $flag ] ) ) {
				$errors[ $flag ] = 'Choose on or off.';
			}
		}

		$one_ok = is_int( $block['oneTypo'] ) && $block['oneTypo'] >= self::ONE_MIN && $block['oneTypo'] <= self::ONE_MAX;
		if ( ! $one_ok ) {
			$errors['oneTypo'] = sprintf( 'One typo needs a whole number of letters from %d to %d.', self::ONE_MIN, self::ONE_MAX );
		}
		// Checked on its own too, so a bad two-typo length is reported even when the one-typo length is also bad.
		if ( ! is_int( $block['twoTypos'] ) || $block['twoTypos'] <= self::ONE_MIN || $block['twoTypos'] > self::TWO_MAX || ( $one_ok && $block['twoTypos'] <= $block['oneTypo'] ) ) {
			$errors['twoTypos'] = sprintf( 'Two typos needs a whole number of letters above the one-typo length, up to %d.', self::TWO_MAX );
		}

		$words = self::words( $block['disableOnWords'] );
		if ( null === $words ) {
			$errors['disableOnWords'] = sprintf( 'Use up to %d words, each up to %d characters.', self::WORDS_MAX, self::WORD_MAX );
		}

		$attributes = self::attributes( $block['disableOnAttributes'] );
		if ( null === $attributes || array_diff( $attributes, $searchable ) ) {
			$errors['disableOnAttributes'] = 'Choose only fields this index searches.';
		}

		if ( ! empty( $errors ) ) {
			return array(
				'values' => array(),
				'errors' => $errors,
			);
		}

		$block['disableOnWords']      = $words;
		$block['disableOnAttributes'] = $attributes;

		return array(
			'values' => $block,
			'errors' => array(),
		);
	}

	/**
	 * The block in effect for a stored value. Attributes the index no longer
	 * searches are split off into `removed` first; anything else that doesn't
	 * validate gives the defaults.
	 *
	 * @param string   $type       Index type.
	 * @param mixed    $stored     That type's stored block, or null.
	 * @param string[] $searchable The index's searchable attributes.
	 * @return array{values: array, removed: string[]}
	 */
	public static function effective( string $type, mixed $stored, array $searchable ): array {
		$removed = array();
		if ( is_array( $stored ) && is_array( $stored['disableOnAttributes'] ?? null ) ) {
			$kept    = array_values( array_filter( $stored['disableOnAttributes'], static fn( $a ) => in_array( $a, $searchable, true ) ) );
			$removed = array_values( array_filter( $stored['disableOnAttributes'], static fn( $a ) => is_string( $a ) && ! in_array( $a, $searchable, true ) ) );

			$stored['disableOnAttributes'] = $kept;
		}

		$checked = null === $stored ? null : self::validate( $type, $stored, $searchable );

		return array(
			'values'  => ( null === $checked || ! empty( $checked['errors'] ) ) ? self::defaults( $type ) : $checked['values'],
			'removed' => $removed,
		);
	}

	/**
	 * Whether a block equals this type's defaults, lists compared sorted.
	 *
	 * @param string $type   Index type.
	 * @param array  $values A validated block.
	 * @return bool
	 */
	public static function is_default( string $type, array $values ): bool {
		return self::payload( $values ) === self::payload( self::defaults( $type ) );
	}

	/**
	 * The Meilisearch typoTolerance object, lists sorted.
	 *
	 * @param array $values A validated block.
	 * @return array
	 */
	public static function payload( array $values ): array {
		return array(
			'enabled'             => (bool) ( $values['enabled'] ?? true ),
			'minWordSizeForTypos' => array(
				'oneTypo'  => (int) ( $values['oneTypo'] ?? 5 ),
				'twoTypos' => (int) ( $values['twoTypos'] ?? 9 ),
			),
			'disableOnWords'      => self::sorted( $values['disableOnWords'] ?? array() ),
			'disableOnAttributes' => self::sorted( $values['disableOnAttributes'] ?? array() ),
			'disableOnNumbers'    => (bool) ( $values['disableOnNumbers'] ?? false ),
		);
	}

	/**
	 * Whether a live typoTolerance object matches a payload, ignoring key and
	 * list order. A non-array or a missing key never matches.
	 *
	 * @param array $payload From payload().
	 * @param mixed $live    The server's typoTolerance.
	 * @return bool
	 */
	public static function same( array $payload, mixed $live ): bool {
		if ( ! is_array( $live ) || ! is_array( $live['minWordSizeForTypos'] ?? null ) ) {
			return false;
		}
		foreach ( array( 'enabled', 'disableOnWords', 'disableOnAttributes', 'disableOnNumbers' ) as $key ) {
			if ( ! array_key_exists( $key, $live ) ) {
				return false;
			}
		}
		foreach ( array( 'oneTypo', 'twoTypos' ) as $key ) {
			if ( ! is_numeric( $live['minWordSizeForTypos'][ $key ] ?? null ) ) {
				return false;
			}
		}
		if ( ! is_array( $live['disableOnWords'] ) || ! is_array( $live['disableOnAttributes'] ) ) {
			return false;
		}

		$normal = array(
			'enabled'             => (bool) $live['enabled'],
			'minWordSizeForTypos' => array(
				'oneTypo'  => (int) $live['minWordSizeForTypos']['oneTypo'],
				'twoTypos' => (int) $live['minWordSizeForTypos']['twoTypos'],
			),
			'disableOnWords'      => self::sorted( $live['disableOnWords'] ),
			'disableOnAttributes' => self::sorted( $live['disableOnAttributes'] ),
			'disableOnNumbers'    => (bool) $live['disableOnNumbers'],
		);

		return $normal === $payload;
	}

	/**
	 * Clean the words list: normalized, empties dropped, de-duplicated.
	 *
	 * @param mixed $raw Raw list.
	 * @return string[]|null Null when it isn't a list of strings within the limits.
	 */
	private static function words( mixed $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$words = array();
		foreach ( $raw as $w ) {
			if ( ! is_string( $w ) ) {
				return null;
			}
			$w = Search_Rules::word( $w );
			if ( '' === $w ) {
				continue;
			}
			if ( mb_strlen( $w, 'UTF-8' ) > self::WORD_MAX ) {
				return null;
			}
			$words[ $w ] = true;
		}

		return count( $words ) > self::WORDS_MAX ? null : array_map( 'strval', array_keys( $words ) );
	}

	/**
	 * Clean the attributes list: strings only, de-duplicated.
	 *
	 * @param mixed $raw Raw list.
	 * @return string[]|null Null when it isn't a list of strings.
	 */
	private static function attributes( mixed $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		foreach ( $raw as $a ) {
			if ( ! is_string( $a ) ) {
				return null;
			}
		}

		return array_values( array_unique( $raw ) );
	}

	/**
	 * A list of strings, sorted.
	 *
	 * @param mixed $items Raw list.
	 * @return string[]
	 */
	private static function sorted( mixed $items ): array {
		$out = is_array( $items ) ? array_values( array_map( 'strval', array_filter( $items, 'is_scalar' ) ) ) : array();
		sort( $out, SORT_STRING );

		return $out;
	}
}
