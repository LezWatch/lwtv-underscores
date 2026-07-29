<?php
/**
 * Unit tests for the This Year deaths-strip month-bucketing transform.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Deaths_Strip;

class DeathsStripTest extends TestCase {

	/**
	 * Fake a $dead_by_date_ov payload: keys are Y-m-d, each value is an array
	 * of that day's dead characters (the transform only counts them).
	 *
	 * @param array $deaths_per_date date string => number of deaths that day
	 * @return array
	 */
	private function payload( array $deaths_per_date ): array {
		$out = array();
		foreach ( $deaths_per_date as $date => $number ) {
			$out[ $date ] = array_fill( 0, $number, array( 'name' => 'Someone' ) );
		}
		return $out;
	}

	public function test_always_returns_twelve_months_keyed_one_to_twelve(): void {
		$result = Deaths_Strip::build( array() );

		$this->assertSame( range( 1, 12 ), array_keys( $result['months'] ) );
	}

	public function test_buckets_deaths_by_month_summing_days_and_same_day_counts(): void {
		// Feb 1, Apr 1, Jun 2 (one day with two deaths), Jul 1.
		$data = $this->payload(
			array(
				'2026-02-14' => 1,
				'2026-04-03' => 1,
				'2026-06-09' => 2,
				'2026-07-25' => 1,
			)
		);

		$result = Deaths_Strip::build( $data );
		$counts = array_map(
			static fn( $m ) => $m['count'],
			$result['months']
		);

		$this->assertSame(
			array( 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0, 6 => 2, 7 => 1, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0 ),
			$counts
		);
	}

	public function test_total_is_the_sum_of_all_deaths(): void {
		$data = $this->payload(
			array(
				'2026-02-14' => 1,
				'2026-06-09' => 2,
				'2026-07-25' => 1,
			)
		);

		$this->assertSame( 4, Deaths_Strip::build( $data )['total'] );
	}

	public function test_empty_month_is_a_five_pixel_tick(): void {
		$month = Deaths_Strip::build( array() )['months'][1];

		$this->assertTrue( $month['is_empty'] );
		$this->assertFalse( $month['is_single'] );
		$this->assertFalse( $month['show_count'] );
		$this->assertSame( 5, $month['size'] );
	}

	public function test_single_death_is_a_fifteen_pixel_dot(): void {
		$data  = $this->payload( array( '2026-03-02' => 1 ) );
		$month = Deaths_Strip::build( $data )['months'][3];

		$this->assertTrue( $month['is_single'] );
		$this->assertFalse( $month['is_empty'] );
		$this->assertFalse( $month['show_count'] );
		$this->assertSame( 15, $month['size'] );
	}

	public function test_multiple_deaths_scale_the_marker(): void {
		$data  = $this->payload( array( '2026-06-09' => 2, '2026-06-20' => 1 ) ); // 3 in June
		$month = Deaths_Strip::build( $data )['months'][6];

		$this->assertTrue( $month['show_count'] );
		$this->assertFalse( $month['is_single'] );
		$this->assertSame( 3, $month['count'] );
		$this->assertSame( 30, $month['size'] ); // 18 + 3 * 4
	}

	public function test_current_year_flags_months_after_today_as_future(): void {
		// Current month = July (7): Aug–Dec are future, Jan–Jul are not.
		$result = Deaths_Strip::build( array(), true, 7 );

		$this->assertFalse( $result['months'][7]['is_future'] );
		$this->assertTrue( $result['months'][8]['is_future'] );
		$this->assertTrue( $result['is_current_year'] );
		$this->assertSame( 58.33, $result['elapsed_pct'] ); // round( 7 / 12 * 100, 2 )
	}

	public function test_past_year_has_no_future_months(): void {
		$result = Deaths_Strip::build( array(), false, 7 );

		$this->assertFalse( $result['is_current_year'] );
		foreach ( $result['months'] as $month ) {
			$this->assertFalse( $month['is_future'] );
		}
	}
}
