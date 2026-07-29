<?php
/**
 * This Year Longest-Running Character We Lost
 *
 * Pure selection logic for the Overview standout that names the character we
 * lost this year with the longest on-air tenure — measured as the number of
 * DISTINCT years the character was actually on air (the union of their shows'
 * `appears` years), not the debut-to-death span, which overstates characters
 * whose careers were a handful of mini-series spread across many years.
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Longest_Running
 */
class Longest_Running {

	/**
	 * A character's on-air tenure across their whole show group.
	 *
	 * @param array $show_group ACF lezchars_show_group value: relationships
	 *                          each with 'show' and 'appears' (array of years).
	 * @return array {
	 *     @type int      $years      Count of distinct years on air.
	 *     @type int|null $first_year Earliest year on air (debut).
	 *     @type int|null $show_id    Show of the earliest appearance.
	 * }
	 */
	public static function tenure( array $show_group ): array {
		$years   = array();
		$first   = null;
		$show_id = null;

		foreach ( $show_group as $relationship ) {
			if ( ! is_array( $relationship ) || empty( $relationship['appears'] ) || ! is_array( $relationship['appears'] ) ) {
				continue;
			}

			$show = $relationship['show'] ?? null;
			if ( is_array( $show ) ) {
				$show = $show[0] ?? null;
			}

			foreach ( $relationship['appears'] as $appears ) {
				$year = (int) $appears;
				if ( $year <= 0 ) {
					continue;
				}
				$years[ $year ] = true;
				if ( null === $first || $year < $first ) {
					$first   = $year;
					$show_id = ( null === $show ) ? null : (int) $show;
				}
			}
		}

		return array(
			'years'      => count( $years ),
			'first_year' => $first,
			'show_id'    => $show_id,
		);
	}

	/**
	 * The candidate with the most years on air; ties break on the earliest debut.
	 *
	 * @param array $candidates Each with int 'years' and int|null 'first_year'.
	 * @return array|null The winning candidate, or null if none qualify.
	 */
	public static function pick( array $candidates ): ?array {
		$winner = null;

		foreach ( $candidates as $candidate ) {
			$years = (int) ( $candidate['years'] ?? 0 );
			if ( $years <= 0 ) {
				continue;
			}

			if ( null === $winner ) {
				$winner = $candidate;
				continue;
			}

			$winner_years = (int) $winner['years'];
			if ( $years > $winner_years ) {
				$winner = $candidate;
			} elseif ( $years === $winner_years ) {
				$candidate_debut = (int) ( $candidate['first_year'] ?? PHP_INT_MAX );
				$winner_debut    = (int) ( $winner['first_year'] ?? PHP_INT_MAX );
				if ( $candidate_debut < $winner_debut ) {
					$winner = $candidate;
				}
			}
		}

		return $winner;
	}
}
