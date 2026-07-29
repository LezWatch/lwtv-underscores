<?php

/**
 * Shared Build Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Shared_Builder {
	/**
	 * Get character marker for alphabetical grouping
	 *
	 * @param string $name The name to get the marker for
	 * @return string The marker (# for numbers, uppercase letter for alphanumeric, - for special chars)
	 */
	public function get_character_marker( string $name ): string {
		$first_char = substr( $name, 0, 1 );

		// Check if it's a number (0-9)
		if ( is_numeric( $first_char ) ) {
			return '#';
		}

		// Check if it's a basic ASCII letter (a-z, A-Z)
		if ( preg_match( '/^[a-zA-Z]$/', $first_char ) ) {
			return strtoupper( $first_char );
		}

		// Everything else (accented characters, symbols, etc.) goes in the - group
		return '-';
	}

	/**
	 * Normalize a title into an alphabetization key by dropping a single leading
	 * English article ("A", "An", "The") so titles file the way a catalog would
	 * — "The Bear" under B, "A Good Girl's Guide to Murder" under G.
	 *
	 * Only the three English articles are handled: the vast majority of titles
	 * are English, and non-English articles are impractical to strip reliably.
	 * Feed this into get_character_marker() for the bucket and into a
	 * case-insensitive comparison for within-bucket order.
	 *
	 * @param string $name The title.
	 * @return string The comparison key (leading article removed).
	 */
	public function sort_name( string $name ): string {
		return preg_replace( '/^(?:a|an|the)\s+/i', '', trim( $name ) );
	}
}
