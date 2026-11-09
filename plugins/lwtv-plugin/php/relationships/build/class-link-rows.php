<?php
/**
 * Name: Link Rows
 * Description: Character link rows from ACF field values.
 *
 * Pure: field values in, rows out. No WordPress calls, so it is unit-tested.
 * A row is array{character_id:int, object_id:int, object_type:string}.
 *
 * @package LWTV
 */

namespace LWTV\Relationships\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Link_Rows {

	/**
	 * Allowed object types. Store::TYPES reuses this.
	 */
	const TYPES = array( 'show', 'actor', 'character' );

	/**
	 * Show and actor rows for a character.
	 *
	 * Show rows come first, then actor rows, each in input order. The first
	 * occurrence of a (type, id) pair wins.
	 *
	 * @param int   $character_id Character post ID.
	 * @param mixed $show_group   ACF repeater value: a list of arrays, normally array( 'show' => id ).
	 * @param mixed $actors       ACF relationship value: a list of IDs, often numeric strings.
	 *
	 * @return array<int, array{character_id:int, object_id:int, object_type:string}>
	 */
	public static function from_fields( int $character_id, $show_group, $actors ): array {
		$rows = array();

		if ( is_array( $show_group ) ) {
			foreach ( $show_group as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['show'] ) ) {
					continue;
				}
				foreach ( self::ids( $entry['show'] ) as $id ) {
					self::add( $rows, $character_id, $id, 'show' );
				}
			}
		}

		foreach ( self::ids( $actors ) as $id ) {
			self::add( $rows, $character_id, $id, 'actor' );
		}

		return array_values( $rows );
	}

	/**
	 * Character-to-character rows from the legacy relationship chart.
	 *
	 * A chart entry Y on character X gives (X, Y). A holder Y, a character
	 * whose chart lists X, gives (Y, X).
	 *
	 * @param int   $character_id       Character post ID (X).
	 * @param mixed $relationship_chart Character post IDs X's chart points at (already
	 *                                  mapped from shadow term IDs by the caller).
	 * @param array $holders_of_my_term IDs of characters whose chart lists X.
	 *
	 * @return array<int, array{character_id:int, object_id:int, object_type:string}>
	 */
	public static function legacy_characters( int $character_id, $relationship_chart, array $holders_of_my_term ): array {
		$rows = array();

		foreach ( self::ids( $relationship_chart ) as $id ) {
			if ( $id !== $character_id ) {
				self::add( $rows, $character_id, $id, 'character' );
			}
		}

		foreach ( self::ids( $holders_of_my_term ) as $id ) {
			if ( $id !== $character_id ) {
				self::add( $rows, $id, $character_id, 'character' );
			}
		}

		return array_values( $rows );
	}

	/**
	 * Rows for one character, limited to the given object types.
	 *
	 * A row is kept when its character_id is $character_id and its object_type
	 * is both in $types and in self::TYPES. Order is preserved.
	 *
	 * @param array    $rows         Link rows.
	 * @param int      $character_id Character post ID.
	 * @param string[] $types        Object types to keep.
	 *
	 * @return array<int, array{character_id:int, object_id:int, object_type:string}>
	 */
	public static function only( array $rows, int $character_id, array $types ): array {
		$types = array_values( array_intersect( array_map( 'strval', $types ), self::TYPES ) );

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $character_id, $types ) {
					return is_array( $row )
						&& isset( $row['character_id'], $row['object_type'] )
						&& self::to_id( $row['character_id'] ) === $character_id
						&& in_array( $row['object_type'], $types, true );
				}
			)
		);
	}

	/**
	 * Sorted unique object IDs of one type.
	 *
	 * @param array  $rows        Link rows.
	 * @param string $object_type Type to keep.
	 *
	 * @return int[]
	 */
	public static function ids_of( array $rows, string $object_type ): array {
		$ids = array();

		foreach ( $rows as $row ) {
			if ( isset( $row['object_type'], $row['object_id'] ) && $object_type === $row['object_type'] ) {
				$id = self::to_id( $row['object_id'] );
				if ( $id >= 1 ) {
					$ids[] = $id;
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );

		return $ids;
	}

	/**
	 * The far side of each character row that involves a character.
	 *
	 * For a row (X, Y) or (Y, X), Y. Rows of other types, rows that don't
	 * involve the character, and self-links are skipped.
	 *
	 * @param array $rows         Link rows.
	 * @param int   $character_id The character (X).
	 *
	 * @return int[] Sorted, unique.
	 */
	public static function other_ids( array $rows, int $character_id ): array {
		$ids = array();

		foreach ( $rows as $row ) {
			if ( ! isset( $row['object_type'], $row['character_id'], $row['object_id'] ) || 'character' !== $row['object_type'] ) {
				continue;
			}

			$from = self::to_id( $row['character_id'] );
			$to   = self::to_id( $row['object_id'] );

			if ( $from === $character_id && $to >= 1 && $to !== $character_id ) {
				$ids[] = $to;
			} elseif ( $to === $character_id && $from >= 1 && $from !== $character_id ) {
				$ids[] = $from;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );

		return $ids;
	}

	/**
	 * Difference between two ID sets.
	 *
	 * @param array $expected IDs that should be present.
	 * @param array $actual   IDs that are present.
	 *
	 * @return array{missing:int[], extra:int[]} Both sorted.
	 */
	public static function compare( array $expected, array $actual ): array {
		$expected = array_values( array_unique( self::ids( $expected ) ) );
		$actual   = array_values( array_unique( self::ids( $actual ) ) );

		$missing = array_values( array_diff( $expected, $actual ) );
		$extra   = array_values( array_diff( $actual, $expected ) );
		sort( $missing );
		sort( $extra );

		return array(
			'missing' => $missing,
			'extra'   => $extra,
		);
	}

	/**
	 * Positive integer IDs from a scalar or a list; everything else is dropped.
	 *
	 * @param mixed $value An ID or a list of IDs.
	 *
	 * @return int[]
	 */
	private static function ids( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		$ids = array();
		foreach ( $value as $item ) {
			$id = self::to_id( $item );
			if ( $id >= 1 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * An int or numeric string as an int; anything else (bools, junk, '5abc') as 0.
	 *
	 * @param mixed $item Candidate ID.
	 *
	 * @return int 0 when it is not an ID.
	 */
	private static function to_id( $item ): int {
		if ( is_int( $item ) || ( is_string( $item ) && is_numeric( $item ) ) ) {
			return (int) $item;
		}

		return 0;
	}

	/**
	 * Add a row unless its "character:type:id" key is already there.
	 *
	 * The character is part of the key so legacy rows from both directions stay
	 * distinct when they share an object.
	 *
	 * @param array  $rows         Rows so far, by reference.
	 * @param int    $character_id Character ID.
	 * @param int    $object_id    Object ID.
	 * @param string $object_type  Object type.
	 */
	private static function add( array &$rows, int $character_id, int $object_id, string $object_type ): void {
		$key = $character_id . ':' . $object_type . ':' . $object_id;

		if ( isset( $rows[ $key ] ) ) {
			return;
		}

		$rows[ $key ] = array(
			'character_id' => $character_id,
			'object_id'    => $object_id,
			'object_type'  => $object_type,
		);
	}
}
