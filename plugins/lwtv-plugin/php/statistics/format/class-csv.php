<?php
/**
 * CSV formatter for statistics downloads.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSV {

	/**
	 * Build a UTF-8 CSV string (with BOM) from a header row + data rows.
	 *
	 * Every cell is hardened against CSV/formula injection. Uses fputcsv() so
	 * quoting and escaping are handled correctly.
	 *
	 * @param array $rows    List of rows; each row is a flat array of scalar cells.
	 * @param array $headers Column header cells.
	 * @return string The CSV payload, or '' if a stream could not be opened.
	 */
	public function build( array $rows, array $headers ): string {
		// php://temp is an in-memory stream, not a filesystem write; WP_Filesystem
		// does not apply here, so the native stream functions are used directly.
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, array_map( array( $this, 'harden' ), $headers ) );
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( array( $this, 'harden' ), (array) $row ) );
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Prepend a UTF-8 BOM so Excel opens accented names in the right encoding.
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Neutralise CSV/formula injection. A cell that opens with =, +, -, or @ can
	 * be executed as a formula by some spreadsheets; prefixing a single quote
	 * makes it inert. Cheap insurance for editor-controlled taxonomy names.
	 *
	 * @param mixed $cell The raw cell value.
	 * @return string The hardened cell value.
	 */
	private function harden( $cell ): string {
		$cell = (string) $cell;

		if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@' ), true ) ) {
			$cell = "'" . $cell;
		}

		return $cell;
	}
}
