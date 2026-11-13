<?php
/**
 * Name: Death Years
 * Description: The documented /stats/years/ shape, built from death-year rows.
 *
 * Pure: rows in, keyed array out. No WordPress calls, so it is unit-tested.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Death_Years {

	/**
	 * Every year from $first to $last, newest first, with its death count.
	 *
	 * Output: array<int, array{name:int, count:int, url:string}>, keyed by
	 * year. Years with no deaths are filled in as 0, and rows outside the
	 * range or missing a count are ignored.
	 *
	 * @param array  $rows     Rows of array{death_year:int, death_count:int}.
	 * @param int    $first    First year to include.
	 * @param int    $last     Last year to include.
	 * @param string $base_url The This Year URL that the year is appended to.
	 *
	 * @return array
	 */
	public static function keyed( array $rows, int $first, int $last, string $base_url ): array {
		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['death_year'], $row['death_count'] ) ) {
				continue;
			}
			$year            = (int) $row['death_year'];
			$counts[ $year ] = ( $counts[ $year ] ?? 0 ) + (int) $row['death_count'];
		}

		$base_url = rtrim( $base_url, '/' ) . '/';
		$years    = array();
		for ( $year = $last; $year >= $first; $year-- ) {
			$years[ $year ] = array(
				'name'  => $year,
				'count' => $counts[ $year ] ?? 0,
				'url'   => $base_url . $year . '/',
			);
		}

		return $years;
	}
}
