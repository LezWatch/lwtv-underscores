<?php
/**
 * Unit tests for the This Year trends count-map transform.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Trends;

class TrendsTest extends TestCase {

	/**
	 * Build a fake ten_years() payload: each metric is an array of dummy
	 * "posts" of the requested length. ten_years() returns arrays of posts,
	 * and the transform only cares about how many there are.
	 *
	 * @param array $counts metric => number of posts
	 * @return array
	 */
	private function year_payload( array $counts ): array {
		$payload = array();
		foreach ( $counts as $metric => $number ) {
			$payload[ $metric ] = array_fill( 0, $number, array( 'id' => 1 ) );
		}
		return $payload;
	}

	public function test_counts_each_metric_per_year(): void {
		$data = array(
			2025 => $this->year_payload(
				array(
					'characters' => 197,
					'dead'       => 5,
					'shows'      => 180,
					'started'    => 14,
					'canceled'   => 26,
				)
			),
		);

		$expected = array(
			2025 => array(
				'characters' => 197,
				'dead'       => 5,
				'shows'      => 180,
				'started'    => 14,
				'canceled'   => 26,
			),
		);

		$this->assertSame( $expected, Trends::to_count_map( $data ) );
	}

	public function test_preserves_every_year_in_order(): void {
		$data = array(
			2023 => $this->year_payload( array( 'characters' => 1 ) ),
			2024 => $this->year_payload( array( 'characters' => 2 ) ),
			2025 => $this->year_payload( array( 'characters' => 3 ) ),
		);

		$result = Trends::to_count_map( $data );

		$this->assertSame( array( 2023, 2024, 2025 ), array_keys( $result ) );
	}

	public function test_missing_metric_key_counts_as_zero(): void {
		// A year with no canceled shows may omit the key entirely.
		$data = array(
			2025 => $this->year_payload(
				array(
					'characters' => 10,
					'dead'       => 0,
					'shows'      => 8,
					'started'    => 2,
				)
			),
		);

		$result = Trends::to_count_map( $data );

		$this->assertSame( 0, $result[2025]['canceled'] );
	}

	public function test_empty_input_returns_empty_array(): void {
		$this->assertSame( array(), Trends::to_count_map( array() ) );
	}

	public function test_cache_key_is_year_scoped(): void {
		$this->assertSame( 'lwtv_this_year_trends_2026', Trends::cache_key( 2026 ) );
	}

	public function test_cache_key_normalizes_string_year(): void {
		$this->assertSame( 'lwtv_this_year_trends_2026', Trends::cache_key( '2026' ) );
	}
}
