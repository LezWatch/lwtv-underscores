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
}
