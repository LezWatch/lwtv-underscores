<?php
/**
 * Name: Search Rules
 * Description: Normalizes, validates and converts the admin's search rules.
 *
 * Pure. The Search Rules page sends two kinds of synonym:
 *
 * - two_way: groups of words that all mean the same thing.
 * - one_way: typing `from` also finds each `to`, not the reverse.
 *
 * normalize() is the server's only defence against bad input (the browser
 * parses imports, but every save comes through here), so anything that isn't
 * the expected shape is dropped, never stored raw. Error and warning rows are
 * the row's index in the INPUT, so the app can show each message by its row.
 *
 * to_meilisearch() gives the body for the `synonyms` setting:
 * https://www.meilisearch.com/docs/reference/api/settings/update-synonyms
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Rules {

	/**
	 * Longest word, in characters.
	 */
	const MAX_WORD = 100;

	/**
	 * Most two-way groups.
	 */
	const MAX_GROUPS = 500;

	/**
	 * Most one-way entries.
	 */
	const MAX_ONE_WAY = 500;

	/**
	 * Most words in one two-way group. A group of n words becomes n keys of
	 * n - 1 values each, so this keeps the settings payload small.
	 */
	const MAX_GROUP_WORDS = 20;

	/**
	 * Most words a one-way entry also finds.
	 */
	const MAX_TO = 20;

	/**
	 * One cleaned word: trimmed, inner whitespace collapsed, lowercased.
	 *
	 * @param string $w Raw word.
	 * @return string '' when the word is empty or isn't valid UTF-8.
	 */
	public static function word( string $w ): string {
		$collapsed = preg_replace( '/\s+/u', ' ', $w );
		if ( null === $collapsed ) {
			return '';
		}

		return mb_strtolower( trim( $collapsed ), 'UTF-8' );
	}

	/**
	 * Clean and validate raw rules.
	 *
	 * @param mixed    $raw        Expected: { two_way: [[…]], one_way: [{ from, to: […] }] }.
	 * @param string[] $stop_words Stop words of every index.
	 * @return array{synonyms: array{two_way: array<int, string[]>, one_way: array<int, array{from: string, to: string[]}>}, errors: array<int, array{kind: string, row: int, message: string}>, warnings: array<int, array{kind: string, row: int, message: string}>}
	 */
	public static function normalize( $raw, array $stop_words ): array {
		$raw      = is_array( $raw ) ? $raw : array();
		$stops    = array_flip( array_map( fn( $s ) => self::word( (string) $s ), array_filter( $stop_words, 'is_scalar' ) ) );
		$errors   = array();
		$warnings = array();

		// Two-way groups.
		$groups   = array();
		$owner    = array(); // Word => input row of the first group that has it.
		$cleaned  = array(); // Input row => cleaned words, for naming groups.
		$rows_in  = is_array( $raw['two_way'] ?? null ) ? array_values( $raw['two_way'] ) : array();
		$flagged  = array(); // First-group rows already told about a conflict, per other row.
		$too_many = count( $rows_in ) > self::MAX_GROUPS;

		if ( $too_many ) {
			$errors[] = self::message( 'two_way', -1, sprintf( 'Too many groups: the most is %d.', self::MAX_GROUPS ) );
			$rows_in  = array_slice( $rows_in, 0, self::MAX_GROUPS );
		}

		foreach ( $rows_in as $row => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$words = self::words( $group );
			if ( empty( $words ) ) {
				continue;
			}

			$cleaned[ $row ] = $words;
			$row_errors      = self::long_words( 'two_way', $row, $words );

			if ( count( $words ) < 2 ) {
				$row_errors[] = self::message( 'two_way', $row, 'A group needs at least 2 words.' );
			}
			if ( count( $words ) > self::MAX_GROUP_WORDS ) {
				$row_errors[] = self::message( 'two_way', $row, sprintf( 'A group can have at most %d words.', self::MAX_GROUP_WORDS ) );
			}

			foreach ( $words as $word ) {
				if ( ! isset( $owner[ $word ] ) ) {
					$owner[ $word ] = $row;
					continue;
				}

				$first        = $owner[ $word ];
				$row_errors[] = self::message( 'two_way', $row, sprintf( '"%s" is already in the group: %s.', $word, implode( ', ', $cleaned[ $first ] ) ) );
				if ( ! isset( $flagged[ $first ][ $row ] ) ) {
					$flagged[ $first ][ $row ] = true;
					$errors[]                  = self::message( 'two_way', $first, sprintf( '"%s" is also in the group: %s.', $word, implode( ', ', $words ) ) );
				}
			}

			$warnings = array_merge( $warnings, self::stop_word_warnings( 'two_way', $row, $words, $stops ) );
			$errors   = array_merge( $errors, $row_errors );

			if ( empty( $row_errors ) ) {
				$groups[] = $words;
			}
		}

		// One-way entries.
		$entries = array();
		$froms   = array();
		$rows_in = is_array( $raw['one_way'] ?? null ) ? array_values( $raw['one_way'] ) : array();

		if ( count( $rows_in ) > self::MAX_ONE_WAY ) {
			$errors[] = self::message( 'one_way', -1, sprintf( 'Too many one-way entries: the most is %d.', self::MAX_ONE_WAY ) );
			$rows_in  = array_slice( $rows_in, 0, self::MAX_ONE_WAY );
		}

		foreach ( $rows_in as $row => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$from = self::words( array( $entry['from'] ?? null ) )[0] ?? '';
			$to   = is_array( $entry['to'] ?? null ) ? self::words( $entry['to'] ) : array();

			if ( '' === $from && empty( $to ) ) {
				continue;
			}

			$to         = array_values( array_filter( $to, fn( $w ) => $w !== $from ) );
			$row_errors = self::long_words( 'one_way', $row, array_merge( array( $from ), $to ) );

			if ( '' === $from ) {
				$row_errors[] = self::message( 'one_way', $row, 'Add the word people type.' );
			} elseif ( isset( $froms[ $from ] ) ) {
				$row_errors[] = self::message( 'one_way', $row, sprintf( '"%s" already has a one-way entry.', $from ) );
			} else {
				$froms[ $from ] = $row;
			}

			if ( empty( $to ) ) {
				$row_errors[] = self::message( 'one_way', $row, 'Add at least 1 word it should also find (other than itself).' );
			}
			if ( count( $to ) > self::MAX_TO ) {
				$row_errors[] = self::message( 'one_way', $row, sprintf( 'An entry can find at most %d words.', self::MAX_TO ) );
			}

			$warnings = array_merge( $warnings, self::stop_word_warnings( 'one_way', $row, array_merge( array( $from ), $to ), $stops ) );
			$errors   = array_merge( $errors, $row_errors );

			if ( empty( $row_errors ) ) {
				$entries[] = array(
					'from' => $from,
					'to'   => $to,
				);
			}
		}

		return array(
			'synonyms' => array(
				'two_way' => $groups,
				'one_way' => $entries,
			),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Meilisearch's synonyms map: every key and value list sorted.
	 *
	 * @param array $synonyms Normalized synonyms.
	 * @return array<string, string[]>
	 */
	public static function to_meilisearch( array $synonyms ): array {
		$map = array();

		foreach ( (array) ( $synonyms['two_way'] ?? array() ) as $group ) {
			foreach ( (array) $group as $word ) {
				foreach ( (array) $group as $other ) {
					if ( $other !== $word ) {
						$map[ (string) $word ][] = (string) $other;
					}
				}
			}
		}

		foreach ( (array) ( $synonyms['one_way'] ?? array() ) as $entry ) {
			$from = (string) ( $entry['from'] ?? '' );
			foreach ( (array) ( $entry['to'] ?? array() ) as $to ) {
				if ( '' !== $from && (string) $to !== $from ) {
					$map[ $from ][] = (string) $to;
				}
			}
		}

		return self::sorted( $map );
	}

	/**
	 * Whether two synonyms maps hold the same words, ignoring order.
	 *
	 * @param array $a Map.
	 * @param array $b Map.
	 * @return bool
	 */
	public static function same( array $a, array $b ): bool {
		return self::sorted( $a ) === self::sorted( $b );
	}

	/**
	 * Cleaned, unique words from a raw list. Ints count as words; anything
	 * else that isn't a string is dropped.
	 *
	 * @param array $raw Raw words.
	 * @return string[]
	 */
	private static function words( array $raw ): array {
		$words = array();
		foreach ( $raw as $w ) {
			if ( is_int( $w ) ) {
				$w = (string) $w;
			}
			if ( ! is_string( $w ) ) {
				continue;
			}
			$w = self::word( $w );
			if ( '' !== $w ) {
				$words[ $w ] = $w;
			}
		}

		return array_values( $words );
	}

	/**
	 * Errors for words over MAX_WORD characters.
	 *
	 * @param string   $kind  two_way or one_way.
	 * @param int      $row   Input row.
	 * @param string[] $words Cleaned words.
	 * @return array<int, array{kind: string, row: int, message: string}>
	 */
	private static function long_words( string $kind, int $row, array $words ): array {
		$errors = array();
		foreach ( $words as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) > self::MAX_WORD ) {
				$errors[] = self::message( $kind, $row, sprintf( 'A word is longer than %d characters.', self::MAX_WORD ) );
			}
		}

		return $errors;
	}

	/**
	 * Warnings for stop words, which Meilisearch ignores.
	 *
	 * @param string            $kind  two_way or one_way.
	 * @param int               $row   Input row.
	 * @param string[]          $words Cleaned words.
	 * @param array<string,int> $stops Stop words, flipped.
	 * @return array<int, array{kind: string, row: int, message: string}>
	 */
	private static function stop_word_warnings( string $kind, int $row, array $words, array $stops ): array {
		$warnings = array();
		foreach ( $words as $word ) {
			if ( isset( $stops[ $word ] ) ) {
				$warnings[] = self::message( $kind, $row, sprintf( '"%s" is a stop word, which search ignores, so it won\'t do anything here.', $word ) );
			}
		}

		return $warnings;
	}

	/**
	 * One error or warning.
	 *
	 * @param string $kind    two_way or one_way.
	 * @param int    $row     Input row; -1 for the whole list.
	 * @param string $message Message.
	 * @return array{kind: string, row: int, message: string}
	 */
	private static function message( string $kind, int $row, string $message ): array {
		return array(
			'kind'    => $kind,
			'row'     => $row,
			'message' => $message,
		);
	}

	/**
	 * A map with keys sorted and each value list de-duplicated and sorted.
	 *
	 * @param array $map Map.
	 * @return array<string, string[]>
	 */
	private static function sorted( array $map ): array {
		$out = array();
		foreach ( $map as $key => $values ) {
			$values = array_values( array_unique( array_map( 'strval', (array) $values ) ) );
			sort( $values, SORT_STRING );
			$out[ (string) $key ] = $values;
		}
		ksort( $out, SORT_STRING );

		return $out;
	}
}
