<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shared helpers: turn a percentage into natural-language phrasing for stat
 * headlines and descriptions ("Over half", "about one in 11"). Data-driven and
 * filterable so any stats view can phrase its figures dynamically.
 *
 * @package LezWatch.TV
 */

if ( ! function_exists( 'lwtv_stats_fraction_phrase' ) ) {
	/**
	 * Natural "fraction of the whole" lead phrase for a percentage — e.g. 80 ->
	 * "Over three quarters", 55 -> "Over half". Slots in front of a headline like
	 * "%s (55%%) are a clear yes".
	 *
	 * The ladder is ordered high -> low; the first threshold the percent meets
	 * wins. Filter `lwtv_stats_fraction_ladder` to tune or extend it.
	 *
	 * @param float $pct Percentage, 0-100.
	 * @return string Translated phrase (no surrounding punctuation).
	 */
	function lwtv_stats_fraction_phrase( $pct ) {
		$pct = (float) $pct;

		// Each rung: [ minimum percent (inclusive), phrase ]. High -> low.
		$ladder = array(
			array( 90, __( 'Nearly all', 'lwtv' ) ),
			array( 75, __( 'Over three quarters', 'lwtv' ) ),
			array( 66, __( 'Over two thirds', 'lwtv' ) ),
			array( 50, __( 'Over half', 'lwtv' ) ),
			array( 40, __( 'Nearly half', 'lwtv' ) ),
			array( 33, __( 'Over a third', 'lwtv' ) ),
			array( 25, __( 'Over a quarter', 'lwtv' ) ),
			array( 10, __( 'About a fifth', 'lwtv' ) ),
			array( 1, __( 'A small share', 'lwtv' ) ),
			array( 0, __( 'None', 'lwtv' ) ),
		);

		/**
		 * Filter the fraction-phrase ladder.
		 *
		 * @param array $ladder Rungs of [ float $min_pct, string $phrase ], ordered high -> low.
		 * @param float $pct    The percentage being described.
		 */
		$ladder = apply_filters( 'lwtv_stats_fraction_ladder', $ladder, $pct );

		foreach ( $ladder as $rung ) {
			if ( $pct >= (float) $rung[0] ) {
				return $rung[1];
			}
		}

		return __( 'None', 'lwtv' );
	}
}

if ( ! function_exists( 'lwtv_stats_shortfall_phrase' ) ) {
	/**
	 * Natural "fewer than a fraction" lead phrase for a percentage — the
	 * negative-framed counterpart to lwtv_stats_fraction_phrase(). e.g. 23 ->
	 * "Fewer than a quarter", 30 -> "Fewer than a third". For emphasising how
	 * SMALL a share is ("%s are played by queer actors").
	 *
	 * The ladder is ordered low -> high; the first ceiling the percent is under
	 * wins. Filter `lwtv_stats_shortfall_ladder` to tune or extend it.
	 *
	 * @param float $pct Percentage, 0-100.
	 * @return string Translated phrase (no surrounding punctuation).
	 */
	function lwtv_stats_shortfall_phrase( $pct ) {
		$pct = (float) $pct;

		// Each rung: [ exclusive ceiling percent, phrase ]. Low -> high.
		$ladder = array(
			array( 10, __( 'Very few', 'lwtv' ) ),
			array( 25, __( 'Fewer than a quarter', 'lwtv' ) ),
			array( 33, __( 'Fewer than a third', 'lwtv' ) ),
			array( 50, __( 'Fewer than half', 'lwtv' ) ),
			array( 66, __( 'Fewer than two thirds', 'lwtv' ) ),
			array( 75, __( 'Fewer than three quarters', 'lwtv' ) ),
			array( 90, __( 'Most', 'lwtv' ) ),
		);

		/**
		 * Filter the shortfall-phrase ladder.
		 *
		 * @param array $ladder Rungs of [ float $ceiling_pct, string $phrase ], ordered low -> high.
		 * @param float $pct    The percentage being described.
		 */
		$ladder = apply_filters( 'lwtv_stats_shortfall_ladder', $ladder, $pct );

		foreach ( $ladder as $rung ) {
			if ( $pct < (float) $rung[0] ) {
				return $rung[1];
			}
		}

		return __( 'Nearly all', 'lwtv' );
	}
}

if ( ! function_exists( 'lwtv_stats_year_series' ) ) {
	/**
	 * Dense-fill a sparse per-year series and find the peak. Defaults to the
	 * Death shape ('death_year'/'death_count'); pass key names to reuse it for
	 * other series (e.g. the on-air 'year'/'count' shape).
	 *
	 * @param array  $sparse    Ascending sparse per-year rows.
	 * @param string $year_key  Row key holding the year. Default 'death_year'.
	 * @param string $count_key Row key holding the count. Default 'death_count'.
	 * @return array [ 'rows' => [ ['year'=>int,'count'=>int], … ] dense, 'peak_year'=>int, 'peak_count'=>int ]
	 */
	function lwtv_stats_year_series( $sparse, $year_key = 'death_year', $count_key = 'death_count' ) {
		$map = array();
		$min = 0;
		$max = 0;
		foreach ( (array) $sparse as $row ) {
			$y = (int) ( $row[ $year_key ] ?? 0 );
			$c = (int) ( $row[ $count_key ] ?? 0 );
			if ( $y <= 0 ) {
				continue;
			}
			$map[ $y ] = $c;
			$min       = ( 0 === $min ) ? $y : min( $min, $y );
			$max       = max( $max, $y );
		}
		if ( 0 === $min ) {
			return array(
				'rows'       => array(),
				'peak_year'  => 0,
				'peak_count' => 0,
			);
		}
		$now        = (int) gmdate( 'Y' );
		$max        = max( $max, $now );
		$rows       = array();
		$peak_year  = $min;
		$peak_count = 0;
		for ( $y = $min; $y <= $max; $y++ ) {
			$c      = $map[ $y ] ?? 0;
			$rows[] = array(
				'year'  => $y,
				'count' => $c,
			);
			if ( $c > $peak_count ) {
				$peak_count = $c;
				$peak_year  = $y;
			}
		}
		return array(
			'rows'       => $rows,
			'peak_year'  => $peak_year,
			'peak_count' => $peak_count,
		);
	}
}

if ( ! function_exists( 'lwtv_stats_ratio_phrase' ) ) {
	/**
	 * "one in N" phrasing for a percentage — e.g. 9 -> "one in 11", 50 -> "one in 2".
	 * Returns '' for zero/negative so callers can choose a fallback clause.
	 *
	 * @param float $pct Percentage, 0-100.
	 * @return string Translated "one in N" phrase, or '' when there is nothing to describe.
	 */
	function lwtv_stats_ratio_phrase( $pct ) {
		$pct = (float) $pct;
		if ( $pct <= 0 ) {
			return '';
		}
		$denominator = max( 2, (int) round( 100 / $pct ) );
		/* translators: %s: a whole number N, as in "one in 11". */
		return sprintf( __( 'one in %s', 'lwtv' ), number_format_i18n( $denominator ) );
	}
}
