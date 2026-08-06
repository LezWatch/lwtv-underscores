<?php
/**
 * Shows We Love transforms.
 *
 * Pure array-in/array-out math for the We Love It view: cohort facts
 * from the roster rows, loved-side totals, and the loved-vs-rest
 * comparison data behind every adaptive takeaway — computed multiples,
 * the deaths direction, the leads-all heading gate, and the ranking
 * check for "the clearest gap on the page". No WordPress calls —
 * unit-testable without a WP runtime (see
 * tests/unit/Statistics/WeLoveCompareTest.php). All i18n stays in the
 * template; data acquisition stays in Build\We_Love.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class We_Love_Compare {

	/**
	 * "About the same" band: sides within this relative gap.
	 */
	public const SAME_BAND = 0.1;

	/**
	 * Deaths-direction tolerance, in percentage points.
	 */
	public const DIRECTION_TOLERANCE = 1.0;

	/**
	 * Cohort facts from the roster rows.
	 *
	 * @param array $rows Roster rows: start, airing, gold, countries[].
	 * @return array gold, airing, span_min, span_max, countries (distinct count).
	 */
	public static function cohort( array $rows ): array {
		$out = array(
			'gold'      => 0,
			'airing'    => 0,
			'span_min'  => 0,
			'span_max'  => 0,
			'countries' => 0,
		);

		$all_countries = array();

		foreach ( $rows as $row ) {
			if ( ! empty( $row['gold'] ) ) {
				++$out['gold'];
			}
			if ( ! empty( $row['airing'] ) ) {
				++$out['airing'];
			}

			$start = (int) ( $row['start'] ?? 0 );
			if ( $start > 0 ) {
				$out['span_min'] = ( 0 === $out['span_min'] ) ? $start : min( $out['span_min'], $start );
				$out['span_max'] = max( $out['span_max'], $start );
			}

			foreach ( (array) ( $row['countries'] ?? array() ) as $country ) {
				$all_countries[ (string) $country ] = true;
			}
		}

		$out['countries'] = count( $all_countries );

		return $out;
	}

	/**
	 * Loved-side totals for the comparison math.
	 *
	 * @param array $rows Roster rows: chars, actors, happy, dead.
	 * @return array n, chars_sum, actors_sum, happy, deadly.
	 */
	public static function loved_totals( array $rows ): array {
		$out = array(
			'n'          => count( $rows ),
			'chars_sum'  => 0,
			'actors_sum' => 0,
			'happy'      => 0,
			'deadly'     => 0,
		);

		foreach ( $rows as $row ) {
			$out['chars_sum']  += max( 0, (int) ( $row['chars'] ?? 0 ) );
			$out['actors_sum'] += max( 0, (int) ( $row['actors'] ?? 0 ) );
			if ( ! empty( $row['happy'] ) ) {
				++$out['happy'];
			}
			if ( (int) ( $row['dead'] ?? 0 ) > 0 ) {
				++$out['deadly'];
			}
		}

		return $out;
	}

	/**
	 * How much bigger the loved side is, in takeaway-ready shape.
	 *
	 * Modes: 'about-same' (within the band), 'more' (ahead but under 2×,
	 * or the rest side is zero where "N times zero" is nonsense),
	 * 'multiple' (2× and up, with the whole multiple in 'times'),
	 * 'fewer' (behind — comparisons can flip, and the copy must follow).
	 *
	 * @param float $loved Loved-side value.
	 * @param float $rest  Everything-else value.
	 * @return array [ 'mode' => string, 'times' => int ]
	 */
	public static function multiple( float $loved, float $rest ): array {
		$high = max( $loved, $rest );
		$low  = min( $loved, $rest );

		if ( $high <= 0 || ( $high - $low ) <= self::SAME_BAND * $high ) {
			return array(
				'mode'  => 'about-same',
				'times' => 1,
			);
		}

		if ( $loved < $rest ) {
			return array(
				'mode'  => 'fewer',
				'times' => 1,
			);
		}

		if ( $rest <= 0 || ( $loved / $rest ) < 2 ) {
			return array(
				'mode'  => 'more',
				'times' => 1,
			);
		}

		return array(
			'mode'  => 'multiple',
			'times' => (int) floor( $loved / $rest ),
		);
	}

	/**
	 * Which way a rate comparison points, with a tolerance band so a
	 * fraction of a point never reads as a claim.
	 *
	 * @param float $loved     Loved-side rate (percentage points).
	 * @param float $rest      Everything-else rate.
	 * @param float $tolerance Band width. Default DIRECTION_TOLERANCE.
	 * @return string 'higher' | 'same' | 'lower'.
	 */
	public static function direction( float $loved, float $rest, float $tolerance = self::DIRECTION_TOLERANCE ): string {
		if ( abs( $loved - $rest ) <= $tolerance ) {
			return 'same';
		}

		return ( $loved > $rest ) ? 'higher' : 'lower';
	}

	/**
	 * Which metric has the largest relative gap between its two sides.
	 * A zero side is an unbounded gap and wins outright.
	 *
	 * @param array $pairs Metric key => [ side_a, side_b ].
	 * @return string The winning key ('' for empty input).
	 */
	public static function largest_gap( array $pairs ): string {
		$best_key   = '';
		$best_ratio = 0.0;

		foreach ( $pairs as $key => $pair ) {
			$high = max( (float) $pair[0], (float) $pair[1] );
			$low  = min( (float) $pair[0], (float) $pair[1] );

			if ( $high <= 0 ) {
				continue;
			}

			$ratio = ( $low > 0 ) ? ( $high / $low ) : PHP_FLOAT_MAX;

			if ( $ratio > $best_ratio ) {
				$best_ratio = $ratio;
				$best_key   = (string) $key;
			}
		}

		return $best_key;
	}

	/**
	 * The full comparison block: loved vs. everything else per metric.
	 *
	 * Archive totals INCLUDE the loved shows; the everything-else side
	 * is derived by subtraction so the two groups never overlap.
	 *
	 * @param array $loved   Output of loved_totals().
	 * @param array $archive Same shape, for the whole archive.
	 * @return array chars/actors (loved, rest, mode, times),
	 *               happy/deaths (loved_count, loved_pct, rest_pct, mode
	 *               or direction), leads_all, largest_gap. Empty array
	 *               when either group is empty.
	 */
	public static function versus( array $loved, array $archive ): array {
		$loved_n = (int) ( $loved['n'] ?? 0 );
		$rest_n  = (int) ( $archive['n'] ?? 0 ) - $loved_n;

		if ( $loved_n <= 0 || $rest_n <= 0 ) {
			return array();
		}

		$avg = static fn( $sum, $n ) => round( $sum / $n, 1 );
		$pct = static fn( $count, $n ) => round( ( $count / $n ) * 100, 1 );

		$chars_loved  = $avg( (int) ( $loved['chars_sum'] ?? 0 ), $loved_n );
		$chars_rest   = $avg( (int) ( $archive['chars_sum'] ?? 0 ) - (int) ( $loved['chars_sum'] ?? 0 ), $rest_n );
		$actors_loved = $avg( (int) ( $loved['actors_sum'] ?? 0 ), $loved_n );
		$actors_rest  = $avg( (int) ( $archive['actors_sum'] ?? 0 ) - (int) ( $loved['actors_sum'] ?? 0 ), $rest_n );

		$happy_loved_pct  = $pct( (int) ( $loved['happy'] ?? 0 ), $loved_n );
		$happy_rest_pct   = $pct( (int) ( $archive['happy'] ?? 0 ) - (int) ( $loved['happy'] ?? 0 ), $rest_n );
		$deaths_loved_pct = $pct( (int) ( $loved['deadly'] ?? 0 ), $loved_n );
		$deaths_rest_pct  = $pct( (int) ( $archive['deadly'] ?? 0 ) - (int) ( $loved['deadly'] ?? 0 ), $rest_n );

		return array(
			'chars'       => array_merge(
				array(
					'loved' => $chars_loved,
					'rest'  => $chars_rest,
				),
				self::multiple( $chars_loved, $chars_rest )
			),
			'actors'      => array_merge(
				array(
					'loved' => $actors_loved,
					'rest'  => $actors_rest,
				),
				self::multiple( $actors_loved, $actors_rest )
			),
			'happy'       => array_merge(
				array(
					'loved_count' => (int) ( $loved['happy'] ?? 0 ),
					'loved_pct'   => $happy_loved_pct,
					'rest_pct'    => $happy_rest_pct,
				),
				self::multiple( $happy_loved_pct, $happy_rest_pct )
			),
			'deaths'      => array(
				'loved_count' => (int) ( $loved['deadly'] ?? 0 ),
				'loved_pct'   => $deaths_loved_pct,
				'rest_pct'    => $deaths_rest_pct,
				'direction'   => self::direction( $deaths_loved_pct, $deaths_rest_pct ),
			),
			'leads_all'   => ( $chars_loved > $chars_rest && $actors_loved > $actors_rest && $happy_loved_pct > $happy_rest_pct ),
			'largest_gap' => self::largest_gap(
				array(
					'chars'  => array( $chars_loved, $chars_rest ),
					'actors' => array( $actors_loved, $actors_rest ),
					'happy'  => array( $happy_loved_pct, $happy_rest_pct ),
					'deaths' => array( $deaths_loved_pct, $deaths_rest_pct ),
				)
			),
		);
	}
}
