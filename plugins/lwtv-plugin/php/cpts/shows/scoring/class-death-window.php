<?php
/**
 * Whether a character counts as dead for one particular show.
 *
 * The `dead` cliché is a fact about the character, but a show is only scored on
 * what happened in it. A character who dies in a sequel (or in a later reboot)
 * is alive as far as the original show is concerned. Death dates are real-world
 * air dates, and a death before a show started is dated to its first episode,
 * so "dead for this show" is: the earliest death is on or before the last year
 * the character appears in that show.
 *
 * Pure: takes scalars and arrays, touches no WordPress state.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

// Bail if directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Death_Window {

	/**
	 * Is this character dead, for the purposes of one show's score?
	 *
	 * Fails toward "dead" whenever the data cannot prove otherwise (no usable death
	 * date, or no appearance years), so a gap in the data never inflates a score.
	 *
	 * @param bool  $has_dead_cliche Character carries the `dead` cliché.
	 * @param array $death_dates     Raw death dates from lezchars_death_year (any stored format).
	 * @param array $appears_years   Years the character appears in this show.
	 *
	 * @return bool
	 */
	public static function is_dead_for_show( bool $has_dead_cliche, array $death_dates, array $appears_years ): bool {
		if ( ! $has_dead_cliche ) {
			return false;
		}

		$death_years = array_filter( array_map( array( self::class, 'year_of' ), $death_dates ) );
		$last_appear = array_filter( array_map( array( self::class, 'year_of' ), $appears_years ) );

		if ( empty( $death_years ) || empty( $last_appear ) ) {
			return true;
		}

		return min( $death_years ) <= max( $last_appear );
	}

	/**
	 * Pull a four-digit year out of a stored date.
	 *
	 * ACF date pickers are stored as Ymd but read back as Y-m-d depending on the
	 * return format, and years arrive as ints or strings. 0 means "no usable year".
	 *
	 * @param mixed $date Date or year.
	 *
	 * @return int
	 */
	public static function year_of( $date ): int {
		if ( ! is_string( $date ) && ! is_int( $date ) ) {
			return 0;
		}

		if ( ! preg_match( '/^\s*(\d{4})(?!\d{5,})/', (string) $date, $matches ) ) {
			return 0;
		}

		return (int) $matches[1];
	}
}
