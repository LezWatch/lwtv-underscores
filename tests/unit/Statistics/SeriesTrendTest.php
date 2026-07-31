<?php
/**
 * Unit tests for the series trend classifier: at-peak / recovering /
 * receding / steady states over completed years, exclusion of the
 * in-progress current year, ties, and the peak-share figure.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Series_Trend;

class SeriesTrendTest extends TestCase {

	private function rows( array $pairs ): array {
		$rows = array();
		foreach ( $pairs as $year => $count ) {
			$rows[] = array(
				'year'  => $year,
				'count' => $count,
			);
		}
		return $rows;
	}

	public function test_latest_completed_year_at_peak(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2022 => 300,
					2023 => 320,
					2024 => 360,
				)
			),
			2025
		);

		$this->assertSame( 'at-peak', $out['state'] );
		$this->assertSame( 2024, $out['peak_year'] );
		$this->assertSame( 360, $out['peak_count'] );
		$this->assertSame( 2024, $out['latest_year'] );
		$this->assertSame( 0, $out['years_since_peak'] );
		$this->assertSame( 100, $out['pct_of_peak'] );
	}

	public function test_current_year_partial_count_is_ignored(): void {
		// 2025 is in progress; its low partial count must not read as a crash.
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2023 => 320,
					2024 => 360,
					2025 => 180,
				)
			),
			2025
		);

		$this->assertSame( 'at-peak', $out['state'] );
		$this->assertSame( 2024, $out['latest_year'] );
	}

	public function test_tie_with_earlier_peak_counts_as_at_peak(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2016 => 360,
					2023 => 320,
					2024 => 360,
				)
			),
			2025
		);

		$this->assertSame( 'at-peak', $out['state'] );
		$this->assertSame( 2024, $out['peak_year'] ); // The latest year matching the max.
	}

	public function test_below_peak_and_rising_is_recovering(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2016 => 360,
					2022 => 280,
					2023 => 290,
					2024 => 310,
				)
			),
			2025
		);

		$this->assertSame( 'recovering', $out['state'] );
		$this->assertSame( 2016, $out['peak_year'] );
		$this->assertSame( 8, $out['years_since_peak'] );
		$this->assertSame( 86, $out['pct_of_peak'] ); // 310/360.
	}

	public function test_below_peak_and_falling_is_receding(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2016 => 360,
					2023 => 320,
					2024 => 300,
				)
			),
			2025
		);

		$this->assertSame( 'receding', $out['state'] );
		$this->assertSame( 2016, $out['peak_year'] );
		$this->assertSame( 300, $out['latest_count'] );
	}

	public function test_below_peak_and_flat_is_steady(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2016 => 360,
					2023 => 300,
					2024 => 300,
				)
			),
			2025
		);

		$this->assertSame( 'steady', $out['state'] );
	}

	public function test_single_completed_year_is_at_peak(): void {
		$out = Series_Trend::classify( $this->rows( array( 2024 => 42 ) ), 2025 );

		$this->assertSame( 'at-peak', $out['state'] );
		$this->assertSame( 2024, $out['peak_year'] );
	}

	public function test_unsorted_rows_are_handled(): void {
		$out = Series_Trend::classify(
			$this->rows(
				array(
					2024 => 300,
					2016 => 360,
					2023 => 320,
				)
			),
			2025
		);

		$this->assertSame( 'receding', $out['state'] );
		$this->assertSame( 2024, $out['latest_year'] );
	}

	public function test_empty_and_all_current_year_input(): void {
		$this->assertSame( array(), Series_Trend::classify( array(), 2025 ) );
		$this->assertSame( array(), Series_Trend::classify( $this->rows( array( 2025 => 10 ) ), 2025 ) );
	}
}
