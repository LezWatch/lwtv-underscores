<?php
/**
 * Score distribution transforms.
 *
 * Pure array-in/array-out math for the show score (0–100) infographics:
 * the decile histogram, median, tail counts, and the average score of
 * the shows on air in each year. No WordPress calls — unit-testable
 * without a WP runtime (see tests/unit/Statistics/ScoreDistributionTest.php).
 *
 * Data acquisition (meta reads, queries) stays in Build\Scores and the
 * on-air builders; this class only crunches what they hand it.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Score_Distribution {

	/**
	 * Scores run 0–100; the top decile bucket is 90–100 inclusive.
	 */
	public const SCORE_MAX = 100;

	/**
	 * Number of histogram buckets (deciles).
	 */
	public const BUCKETS = 10;

	/**
	 * Bucket scores into deciles.
	 *
	 * The last bucket is 90–100 inclusive so a perfect score does not
	 * spill into a phantom 11th bucket.
	 *
	 * @param array $scores Raw score values (numeric strings fine; junk skipped).
	 * @return array {
	 *   @type array $buckets Ten rows of [ floor, ceiling, count, pct ].
	 *   @type int   $total   Number of valid scores counted.
	 * }
	 */
	public static function histogram( array $scores ): array {
		$clean   = self::sanitize( $scores );
		$total   = count( $clean );
		$buckets = array();

		for ( $i = 0; $i < self::BUCKETS; $i++ ) {
			$buckets[ $i ] = array(
				'floor'   => $i * 10,
				'ceiling' => ( self::BUCKETS - 1 === $i ) ? self::SCORE_MAX : ( $i * 10 ) + 9,
				'count'   => 0,
				'pct'     => 0.0,
			);
		}

		foreach ( $clean as $score ) {
			$bucket = min( self::BUCKETS - 1, (int) floor( $score / 10 ) );
			++$buckets[ $bucket ]['count'];
		}

		if ( $total > 0 ) {
			foreach ( $buckets as $i => $bucket ) {
				$buckets[ $i ]['pct'] = round( ( $bucket['count'] / $total ) * 100, 1 );
			}
		}

		return array(
			'buckets' => $buckets,
			'total'   => $total,
		);
	}

	/**
	 * Median score.
	 *
	 * @param array $scores Raw score values.
	 * @return float Median (0.0 for empty input).
	 */
	public static function median( array $scores ): float {
		$clean = self::sanitize( $scores );

		if ( empty( $clean ) ) {
			return 0.0;
		}

		sort( $clean );
		$count  = count( $clean );
		$middle = (int) floor( $count / 2 );

		if ( 0 === $count % 2 ) {
			return (float) ( ( $clean[ $middle - 1 ] + $clean[ $middle ] ) / 2 );
		}

		return (float) $clean[ $middle ];
	}

	/**
	 * Count the tails: scores strictly under $low and at-or-above $high.
	 *
	 * @param array $scores Raw score values.
	 * @param int   $low    "Under N" threshold (exclusive). Default 20.
	 * @param int   $high   "N or better" threshold (inclusive). Default 90.
	 * @return array [ 'low' => int, 'high' => int ]
	 */
	public static function tails( array $scores, int $low = 20, int $high = 90 ): array {
		$clean = self::sanitize( $scores );
		$out   = array(
			'low'  => 0,
			'high' => 0,
		);

		foreach ( $clean as $score ) {
			if ( $score < $low ) {
				++$out['low'];
			}
			if ( $score >= $high ) {
				++$out['high'];
			}
		}

		return $out;
	}

	/**
	 * Average score of the shows on air in each year.
	 *
	 * Each show contributes its (whole-run, cumulative) score to every
	 * year it aired, so a year's figure reads as "how good was the
	 * lineup you could watch that year."
	 *
	 * @param array $scores_by_year Year => array of raw score values.
	 * @return array Year (ascending) => [ 'average' => float, 'count' => int ].
	 *               Years with no valid scores are dropped.
	 */
	public static function yearly_average( array $scores_by_year ): array {
		$out = array();

		foreach ( $scores_by_year as $year => $scores ) {
			$clean = self::sanitize( is_array( $scores ) ? $scores : array() );

			if ( empty( $clean ) ) {
				continue;
			}

			$out[ (int) $year ] = array(
				'average' => round( array_sum( $clean ) / count( $clean ), 1 ),
				'count'   => count( $clean ),
			);
		}

		ksort( $out );

		return $out;
	}

	/**
	 * Drop the sparse leading years from a yearly series.
	 *
	 * A year with one or two shows is not a "lineup" and its average is
	 * noise, so the head of the series is trimmed until the first year
	 * that meets $min_count. Later dips below the threshold are kept —
	 * a dense chart with a soft year beats a chart with holes.
	 *
	 * @param array $yearly    Output of yearly_average() (year ascending).
	 * @param int   $min_count Minimum shows for the series to start.
	 * @return array Trimmed series.
	 */
	public static function trim_thin_years( array $yearly, int $min_count = 5 ): array {
		ksort( $yearly );

		$out     = array();
		$started = false;

		foreach ( $yearly as $year => $row ) {
			if ( ! $started && (int) ( $row['count'] ?? 0 ) < $min_count ) {
				continue;
			}
			$started      = true;
			$out[ $year ] = $row;
		}

		return $out;
	}

	/**
	 * The best-graded year: highest average, earliest year on a tie.
	 *
	 * @param array $yearly Output of yearly_average().
	 * @return array [ 'year' => int, 'average' => float, 'count' => int ] or empty array.
	 */
	public static function best_year( array $yearly ): array {
		$best = array();

		ksort( $yearly );

		foreach ( $yearly as $year => $row ) {
			$average = (float) ( $row['average'] ?? 0 );

			if ( empty( $best ) || $average > $best['average'] ) {
				$best = array(
					'year'    => (int) $year,
					'average' => $average,
					'count'   => (int) ( $row['count'] ?? 0 ),
				);
			}
		}

		return $best;
	}

	/**
	 * Keep numeric values only, clamped to the 0–100 scale.
	 *
	 * @param array $scores Raw values.
	 * @return array Floats.
	 */
	private static function sanitize( array $scores ): array {
		$clean = array();

		foreach ( $scores as $score ) {
			if ( ! is_numeric( $score ) ) {
				continue;
			}
			$clean[] = (float) min( self::SCORE_MAX, max( 0, (float) $score ) );
		}

		return $clean;
	}
}
