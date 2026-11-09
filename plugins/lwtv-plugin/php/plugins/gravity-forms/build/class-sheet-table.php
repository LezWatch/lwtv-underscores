<?php
/**
 * Name: Sheet Table
 * Description: Pure parsing of Google Sheets value arrays into header-keyed rows.
 *
 * The Sheets API drops trailing empty cells, so short rows are padded. No
 * WordPress calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sheet_Table {

	/**
	 * Map required header labels to 0-based column indexes.
	 *
	 * @param array    $header_row First row of the sheet.
	 * @param string[] $required   Header labels that must exist.
	 * @return array<string,int>
	 * @throws \UnexpectedValueException When any required header is missing.
	 */
	public static function header_map( array $header_row, array $required ): array {
		$found = array();
		foreach ( $header_row as $index => $label ) {
			$key = Name_Match::normalize( (string) $label );
			if ( ! isset( $found[ $key ] ) ) {
				$found[ $key ] = (int) $index;
			}
		}

		$map     = array();
		$missing = array();
		foreach ( $required as $label ) {
			$key = Name_Match::normalize( (string) $label );
			if ( isset( $found[ $key ] ) ) {
				$map[ $label ] = $found[ $key ];
			} else {
				$missing[] = $label;
			}
		}

		if ( ! empty( $missing ) ) {
			// Internal diagnostic for logs, never rendered; this class stays WordPress-free.
			throw new \UnexpectedValueException( 'Sheet is missing required column(s): ' . implode( ', ', $missing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $map;
	}

	/**
	 * Data rows keyed by header label, with their 1-based sheet row numbers.
	 *
	 * @param array              $values Sheet values; the first row is the header.
	 * @param array<string,int>  $map    Header label => column index.
	 * @return array<int, array{row:int, cells:array<string,string>}>
	 */
	public static function rows( array $values, array $map ): array {
		$out = array();

		foreach ( array_values( $values ) as $i => $raw ) {
			if ( 0 === $i ) {
				continue;
			}

			$raw   = is_array( $raw ) ? array_values( $raw ) : array();
			$cells = array();
			$blank = true;

			foreach ( $map as $label => $col ) {
				$cell = isset( $raw[ $col ] ) ? (string) $raw[ $col ] : '';
				if ( '' !== Name_Match::normalize( $cell ) ) {
					$blank = false;
				}
				$cells[ $label ] = $cell;
			}

			if ( ! $blank ) {
				$out[] = array(
					'row'   => $i + 1,
					'cells' => $cells,
				);
			}
		}

		return $out;
	}

	/**
	 * Distinct non-blank values of one column, first-seen order.
	 *
	 * @param array  $rows Output of rows().
	 * @param string $key  Header label.
	 * @return string[]
	 */
	public static function column_values( array $rows, string $key ): array {
		$seen = array();
		$out  = array();

		foreach ( $rows as $row ) {
			$value      = (string) ( $row['cells'][ $key ] ?? '' );
			$normalized = Name_Match::normalize( $value );
			if ( '' === $normalized || isset( $seen[ $normalized ] ) ) {
				continue;
			}
			$seen[ $normalized ] = true;
			$out[]               = $value;
		}

		return $out;
	}

	/**
	 * First row number of an A1 range, e.g. 1084 for "'To Add'!A1084:K1084".
	 *
	 * @param string $range A1 range, optionally with a (quoted) tab name.
	 * @return int 0 when the range names no row ("A:K", or garbage).
	 */
	public static function range_row( string $range ): int {
		$bang  = strrpos( $range, '!' );
		$cells = false === $bang ? $range : substr( $range, $bang + 1 );

		return 1 === preg_match( '/^\$?[A-Za-z]*\$?([1-9]\d*)/', $cells, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Spreadsheet column letter for a 0-based index (0 = A, 26 = AA).
	 *
	 * @param int $index Column index.
	 * @return string
	 */
	public static function column_letter( int $index ): string {
		$letter = '';
		$n      = $index + 1;

		while ( $n > 0 ) {
			$mod    = ( $n - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$n      = intdiv( $n - 1, 26 );
		}

		return $letter;
	}
}
