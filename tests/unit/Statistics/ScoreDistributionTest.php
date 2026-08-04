<?php
/**
 * Unit tests for the score distribution transform: decile histogram
 * bucketing (0–100 scale, 90+ bucket inclusive of 100), median, tail
 * counts, per-on-air-year averages, and best-year selection.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Score_Distribution;

class ScoreDistributionTest extends TestCase {

	/*
	 * histogram()
	 */

	public function test_histogram_buckets_scores_into_deciles(): void {
		$out = Score_Distribution::histogram( array( 0, 5, 9, 10, 55, 89, 90, 100 ) );

		$this->assertCount( 10, $out['buckets'] );
		$this->assertSame( 8, $out['total'] );

		$this->assertSame( 3, $out['buckets'][0]['count'] ); // 0, 5, 9.
		$this->assertSame( 1, $out['buckets'][1]['count'] ); // 10.
		$this->assertSame( 1, $out['buckets'][5]['count'] ); // 55.
		$this->assertSame( 1, $out['buckets'][8]['count'] ); // 89.
		$this->assertSame( 2, $out['buckets'][9]['count'] ); // 90, 100.
	}

	public function test_histogram_bucket_bounds_are_labeled(): void {
		$out = Score_Distribution::histogram( array( 42 ) );

		$this->assertSame( 0, $out['buckets'][0]['floor'] );
		$this->assertSame( 9, $out['buckets'][0]['ceiling'] );
		$this->assertSame( 40, $out['buckets'][4]['floor'] );
		$this->assertSame( 49, $out['buckets'][4]['ceiling'] );

		// The last bucket absorbs a perfect 100 — there is no 11th bucket.
		$this->assertSame( 90, $out['buckets'][9]['floor'] );
		$this->assertSame( 100, $out['buckets'][9]['ceiling'] );
	}

	public function test_histogram_percentages_share_of_total(): void {
		$out = Score_Distribution::histogram( array( 5, 5, 5, 95 ) );

		$this->assertSame( 75.0, $out['buckets'][0]['pct'] );
		$this->assertSame( 25.0, $out['buckets'][9]['pct'] );
	}

	public function test_histogram_clamps_out_of_range_and_skips_non_numeric(): void {
		$out = Score_Distribution::histogram( array( -5, 105, 'nope', null, '50' ) );

		$this->assertSame( 3, $out['total'] ); // -5, 105, '50' — strings that are numeric count.
		$this->assertSame( 1, $out['buckets'][0]['count'] ); // -5 clamps to 0.
		$this->assertSame( 1, $out['buckets'][5]['count'] ); // '50' casts fine.
		$this->assertSame( 1, $out['buckets'][9]['count'] ); // 105 clamps to 100.
	}

	public function test_histogram_empty_input(): void {
		$out = Score_Distribution::histogram( array() );

		$this->assertSame( 0, $out['total'] );
		$this->assertCount( 10, $out['buckets'] );
		$this->assertSame( 0, array_sum( array_column( $out['buckets'], 'count' ) ) );
		$this->assertSame( 0.0, array_sum( array_column( $out['buckets'], 'pct' ) ) );
	}

	/*
	 * median()
	 */

	public function test_median_odd_count(): void {
		$this->assertSame( 63.0, Score_Distribution::median( array( 90, 63, 12 ) ) );
	}

	public function test_median_even_count_averages_middle_pair(): void {
		$this->assertSame( 55.0, Score_Distribution::median( array( 100, 60, 50, 10 ) ) );
	}

	public function test_median_empty_is_zero(): void {
		$this->assertSame( 0.0, Score_Distribution::median( array() ) );
	}

	/*
	 * tails()
	 */

	public function test_tails_boundaries_are_inclusive_high_exclusive_low(): void {
		$out = Score_Distribution::tails( array( 19, 20, 89, 90, 100 ) );

		$this->assertSame( 1, $out['low'] );  // 19 only; 20 is not "under 20".
		$this->assertSame( 2, $out['high'] ); // 90 and 100; 89 misses.
	}

	public function test_tails_custom_thresholds(): void {
		$out = Score_Distribution::tails( array( 10, 40, 60, 80 ), 50, 75 );

		$this->assertSame( 2, $out['low'] );
		$this->assertSame( 1, $out['high'] );
	}

	/*
	 * yearly_average()
	 */

	public function test_yearly_average_rounds_to_one_decimal_and_keeps_counts(): void {
		$out = Score_Distribution::yearly_average(
			array(
				2023 => array( 60, 61 ),
				2024 => array( 70, 71, 72 ),
			)
		);

		$this->assertSame( 60.5, $out[2023]['average'] );
		$this->assertSame( 2, $out[2023]['count'] );
		$this->assertSame( 71.0, $out[2024]['average'] );
		$this->assertSame( 3, $out[2024]['count'] );
	}

	public function test_yearly_average_skips_empty_years_and_sorts_ascending(): void {
		$out = Score_Distribution::yearly_average(
			array(
				2024 => array( 70 ),
				2020 => array(),
				2019 => array( 55 ),
			)
		);

		$this->assertSame( array( 2019, 2024 ), array_keys( $out ) );
		$this->assertArrayNotHasKey( 2020, $out );
	}

	public function test_yearly_average_sanitizes_scores(): void {
		$out = Score_Distribution::yearly_average(
			array( 2024 => array( '80', 'junk', null, 120 ) )
		);

		// '80' casts, junk/null skipped, 120 clamps to 100 → (80+100)/2.
		$this->assertSame( 90.0, $out[2024]['average'] );
		$this->assertSame( 2, $out[2024]['count'] );
	}

	/*
	 * trim_thin_years()
	 */

	public function test_trim_thin_years_drops_sparse_leading_years_only(): void {
		$out = Score_Distribution::trim_thin_years(
			array(
				1961 => array(
					'average' => 40.0,
					'count'   => 1,
				),
				1975 => array(
					'average' => 80.0,
					'count'   => 2,
				),
				1990 => array(
					'average' => 60.0,
					'count'   => 6,
				),
				1991 => array(
					'average' => 61.0,
					'count'   => 3,
				),
			),
			5
		);

		// 1961/1975 are a sparse head and go; 1991 dips below the
		// threshold mid-series but stays — no holes in the chart.
		$this->assertSame( array( 1990, 1991 ), array_keys( $out ) );
	}

	public function test_trim_thin_years_keeps_everything_when_dense(): void {
		$yearly = array(
			2020 => array(
				'average' => 60.0,
				'count'   => 30,
			),
			2021 => array(
				'average' => 62.0,
				'count'   => 31,
			),
		);

		$this->assertSame( $yearly, Score_Distribution::trim_thin_years( $yearly, 5 ) );
	}

	public function test_trim_thin_years_all_sparse_returns_empty(): void {
		$out = Score_Distribution::trim_thin_years(
			array(
				2001 => array(
					'average' => 50.0,
					'count'   => 2,
				),
			),
			5
		);

		$this->assertSame( array(), $out );
	}

	/*
	 * best_year()
	 */

	public function test_best_year_picks_highest_average(): void {
		$out = Score_Distribution::best_year(
			array(
				2022 => array(
					'average' => 61.2,
					'count'   => 40,
				),
				2023 => array(
					'average' => 68.4,
					'count'   => 44,
				),
				2024 => array(
					'average' => 65.0,
					'count'   => 39,
				),
			)
		);

		$this->assertSame( 2023, $out['year'] );
		$this->assertSame( 68.4, $out['average'] );
	}

	public function test_best_year_tie_prefers_earliest(): void {
		$out = Score_Distribution::best_year(
			array(
				2020 => array(
					'average' => 68.0,
					'count'   => 30,
				),
				2024 => array(
					'average' => 68.0,
					'count'   => 50,
				),
			)
		);

		// First year to hit the peak wins the tie.
		$this->assertSame( 2020, $out['year'] );
	}

	public function test_best_year_empty_input(): void {
		$this->assertSame( array(), Score_Distribution::best_year( array() ) );
	}
}
