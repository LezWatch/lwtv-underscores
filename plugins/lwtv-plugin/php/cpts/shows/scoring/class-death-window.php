<?php
/**
 * Whether a character counts as dead for one particular show.
 *
 * The `dead` cliché is a fact about the character, but a show is only scored on
 * what happened in it. A character who dies in a sequel (or in a later reboot)
 * is alive as far as the original show is concerned. Death dates are real-world
 * air dates, and a death before a show started is dated to its first episode,
 * so "dead for this show" is either:
 *
 *  1. a death on or before the last year the character appears in that show, or
 *  2. an off-screen death during that show's run (on or before its final year),
 *     in a year the character is not appearing in some other show. The
 *     exception keeps a sequel or spin-off death with the show it aired on.
 *
 * Pure: takes scalars and arrays, touches no WordPress state.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

use LWTV\CPTs\Shows\Airdates;

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
	 * Without a final year only rule 1 applies, which is the pre-#747 behaviour.
	 *
	 * @param bool     $has_dead_cliche  Character carries the `dead` cliché.
	 * @param array    $death_dates      Raw death dates from lezchars_death_year (any stored format).
	 * @param array    $appears_years    Years the character appears in this show.
	 * @param int|null $show_final_year  The show's last year (current year if still airing), see final_year().
	 * @param array    $other_show_years Years the character appears in any other show.
	 *
	 * @return bool
	 */
	public static function is_dead_for_show( bool $has_dead_cliche, array $death_dates, array $appears_years, ?int $show_final_year = null, array $other_show_years = array() ): bool {
		if ( ! $has_dead_cliche ) {
			return false;
		}

		$death_years = array_filter( array_map( array( self::class, 'year_of' ), $death_dates ) );
		$last_appear = array_filter( array_map( array( self::class, 'year_of' ), $appears_years ) );

		if ( empty( $death_years ) || empty( $last_appear ) ) {
			return true;
		}

		// Rule 1: died while (or before) appearing here.
		if ( min( $death_years ) <= max( $last_appear ) ) {
			return true;
		}

		if ( null === $show_final_year ) {
			return false;
		}

		// Rule 2: killed off-screen during this show's run, unless that year they
		// were on screen elsewhere (the death belongs to that show).
		$elsewhere = array_flip( array_filter( array_map( array( self::class, 'year_of' ), $other_show_years ) ) );

		foreach ( $death_years as $year ) {
			if ( $year <= $show_final_year && ! isset( $elsewhere[ $year ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The last year of a show's run, from its stored finish value.
	 *
	 * @param string $finish lezshows_airdates_finish: a year, or 'current' while still airing.
	 * @param int    $now    The current year.
	 *
	 * @return int|null Null when the finish is unknown, which turns rule 2 off.
	 */
	public static function final_year( string $finish, int $now ): ?int {
		if ( Airdates::is_still_airing( $finish ) ) {
			return $now;
		}

		$year = self::year_of( $finish );

		return ( 0 === $year ) ? null : $year;
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
