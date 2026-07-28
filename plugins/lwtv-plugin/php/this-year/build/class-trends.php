<?php
/**
 * This Year Trends
 *
 * Pure transforms for the This Year eleven-year trend series.
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trends
 */
class Trends {

	/**
	 * Transient key prefix for the compact per-year trend count map.
	 *
	 * @var string
	 */
	public const CACHE_PREFIX = 'lwtv_this_year_trends_';

	/**
	 * Build the year-scoped transient key for the trend count map.
	 *
	 * @param int|string $year The end year of the eleven-year window.
	 * @return string
	 */
	public static function cache_key( $year ): string {
		return self::CACHE_PREFIX . (int) $year;
	}

	/**
	 * Reduce a ten_years() payload to a compact per-year count map.
	 *
	 * @param array $ten_years_data Output of This_Year_JSON::ten_years():
	 *                              [ year => [ metric => array of posts ] ].
	 * @return array [ year => [ metric => int count ] ].
	 */
	public static function to_count_map( array $ten_years_data ): array {
		$metrics   = array( 'characters', 'dead', 'shows', 'started', 'canceled' );
		$count_map = array();

		foreach ( $ten_years_data as $year => $year_data ) {
			$counts = array();
			foreach ( $metrics as $metric ) {
				$counts[ $metric ] = isset( $year_data[ $metric ] ) ? count( $year_data[ $metric ] ) : 0;
			}
			$count_map[ $year ] = $counts;
		}

		return $count_map;
	}
}
