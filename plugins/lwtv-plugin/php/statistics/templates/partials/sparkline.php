<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shared helper: cumulative series -> SVG polyline points.
 *
 * @package LezWatch.TV
 */

if ( ! function_exists( 'lwtv_stats_sparkline_points' ) ) {
	/**
	 * Convert a cumulative series into SVG polyline points within a viewBox.
	 *
	 * @param array $series Cumulative series [ ['year'=>int,'count'=>int], … ].
	 * @param int   $w      viewBox width.
	 * @param int   $h      viewBox height.
	 * @return string Space-separated "x,y" pairs, or '' if fewer than 2 points.
	 */
	function lwtv_stats_sparkline_points( array $series, int $w = 120, int $h = 26 ): string {
		$counts = array_column( $series, 'count' );
		$n      = count( $counts );
		if ( $n < 2 ) {
			return '';
		}
		$max   = max( $counts );
		$min   = min( $counts );
		$range = ( $max - $min ) ?: 1;
		$pts   = array();
		foreach ( array_values( $counts ) as $i => $c ) {
			$x     = round( ( $i / ( $n - 1 ) ) * $w, 2 );
			$y     = round( $h - ( ( $c - $min ) / $range ) * $h, 2 );
			$pts[] = $x . ',' . $y;
		}
		return implode( ' ', $pts );
	}
}
