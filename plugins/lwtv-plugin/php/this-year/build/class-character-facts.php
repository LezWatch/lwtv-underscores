<?php
/**
 * This Year Character Facts
 *
 * Pure per-character derivations for the Characters On Air panel extras:
 * how many distinct shows a character appeared in during a given year, and
 * whether that year was their on-screen debut.
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Character_Facts
 */
class Character_Facts {

	/**
	 * Derive a character's year facts from their show group.
	 *
	 * @param array $show_group ACF lezchars_show_group value: relationships each
	 *                          with 'show' and 'appears' (array of years).
	 * @param int   $year       The year in question.
	 * @return array {
	 *     @type int  $shows_this_year Distinct shows they appeared in that year.
	 *     @type bool $debuted         Whether that year was their earliest appearance.
	 * }
	 */
	public static function for_year( array $show_group, int $year ): array {
		$shows_this_year = array();
		$first_year      = null;

		foreach ( $show_group as $relationship ) {
			if ( ! is_array( $relationship ) || empty( $relationship['appears'] ) || ! is_array( $relationship['appears'] ) ) {
				continue;
			}

			$show = $relationship['show'] ?? null;
			if ( is_array( $show ) ) {
				$show = $show[0] ?? null;
			}

			$appeared_this_year = false;
			foreach ( $relationship['appears'] as $appears ) {
				$appears_year = (int) $appears;
				if ( $appears_year <= 0 ) {
					continue;
				}
				if ( null === $first_year || $appears_year < $first_year ) {
					$first_year = $appears_year;
				}
				if ( $appears_year === $year ) {
					$appeared_this_year = true;
				}
			}

			if ( $appeared_this_year && null !== $show ) {
				$shows_this_year[ (int) $show ] = true;
			}
		}

		return array(
			'shows_this_year' => count( $shows_this_year ),
			'debuted'         => ( null !== $first_year && $first_year === $year ),
		);
	}
}
