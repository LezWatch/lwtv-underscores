<?php
/**
 * Unit tests for the Dead Characters (By Date) view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Dead_Characters;

class DeadCharactersTest extends TestCase {

	// ---- normalize_date_key(): legacy Ymd → Y-m-d. ----

	public function test_normalize_date_key_converts_legacy_ymd(): void {
		$this->assertSame( '2025-04-06', Dead_Characters::normalize_date_key( '20250406' ) );
	}

	public function test_normalize_date_key_leaves_dashed_dates(): void {
		$this->assertSame( '2025-04-06', Dead_Characters::normalize_date_key( ' 2025-04-06 ' ) );
	}

	// ---- months(): 12-column model, counts sum to total. ----

	/** Build a $dead_by_date-shaped fixture: 'Y-m-d' => array of N character stubs. */
	private function deaths( array $by_date ): array {
		$out = array();
		foreach ( $by_date as $date => $n ) {
			$out[ $date ] = array_fill( 0, $n, array( 'name' => 'X', 'shows' => array( array( 'type' => 'regular' ) ) ) );
		}
		return $out;
	}

	public function test_months_tallies_twelve_columns_and_flags(): void {
		$result = Dead_Characters::months(
			$this->deaths(
				array(
					'2025-01-06' => 2,
					'2025-02-07' => 3,
					'2025-04-04' => 4,
					'2025-04-29' => 0, // no deaths recorded on a date is not a real case; treat as 0
				)
			)
		);

		$this->assertCount( 12, $result );
		$this->assertSame( 1, $result[0]['num'] );
		$this->assertSame( 12, $result[11]['num'] );

		$byNum = array_column( $result, null, 'num' );
		$this->assertSame( 4, $byNum[4]['count'] );  // April
		$this->assertTrue( $byNum[4]['peak'] );        // 4 is the max
		$this->assertFalse( $byNum[1]['peak'] );
		$this->assertTrue( $byNum[3]['empty'] );       // March: no deaths
		$this->assertSame( 0, $byNum[3]['count'] );

		$this->assertSame( 9, array_sum( array_column( $result, 'count' ) ) ); // 2+3+4
	}

	public function test_months_empty_input_has_no_peak(): void {
		$result = Dead_Characters::months( array() );
		$this->assertCount( 12, $result );
		$this->assertSame( 0, array_sum( array_column( $result, 'count' ) ) );
		$this->assertSame( array(), array_values( array_filter( $result, static fn( $m ) => $m['peak'] ) ) );
		$this->assertTrue( $result[5]['empty'] );
	}

	public function test_months_defaults_to_full_year(): void {
		$result = Dead_Characters::months( $this->deaths( array( '2025-03-10' => 1 ) ) );
		$this->assertCount( 12, $result );
		$this->assertSame( 12, $result[11]['num'] );
	}

	public function test_months_omits_future_months_when_capped(): void {
		// Year in progress: today is July, so only Jan→Jul should be built.
		$result = Dead_Characters::months(
			$this->deaths(
				array(
					'2026-03-10' => 1,
					'2026-06-05' => 2,
				)
			),
			7
		);

		$this->assertCount( 7, $result );
		$this->assertSame( 7, $result[ count( $result ) - 1 ]['num'] ); // last shown month is July

		$byNum = array_column( $result, null, 'num' );
		$this->assertArrayNotHasKey( 8, $byNum );       // August onward omitted
		$this->assertTrue( $byNum[6]['peak'] );          // June is the peak within range
		$this->assertTrue( $byNum[7]['empty'] );         // July (current month) has no deaths yet
		$this->assertSame( 3, array_sum( array_column( $result, 'count' ) ) );
	}

	public function test_months_cap_clamped_to_valid_range(): void {
		$this->assertCount( 12, Dead_Characters::months( array(), 99 ) );
		$this->assertCount( 12, Dead_Characters::months( array(), 12 ) );
	}

	// ---- longest_stretch(): largest gap between consecutive deaths. ----

	public function test_longest_stretch_picks_largest_gap(): void {
		$result = Dead_Characters::longest_stretch(
			$this->deaths(
				array(
					'2025-04-04' => 1,
					'2025-04-29' => 1,
					'2025-07-03' => 1, // 65-day gap from Apr 29
				)
			)
		);
		$this->assertSame( 65, $result['days'] );
		$this->assertSame( '2025-04-29', $result['from'] );
		$this->assertSame( '2025-07-03', $result['to'] );
	}

	public function test_longest_stretch_null_when_fewer_than_two_dates(): void {
		$this->assertNull( Dead_Characters::longest_stretch( $this->deaths( array( '2025-04-04' => 3 ) ) ) );
		$this->assertNull( Dead_Characters::longest_stretch( array() ) );
	}

	public function test_longest_stretch_ties_pick_earliest(): void {
		// Two equal 10-day gaps; the earlier one wins.
		$result = Dead_Characters::longest_stretch(
			$this->deaths(
				array(
					'2025-01-01' => 1,
					'2025-01-11' => 1, // gap 10
					'2025-01-16' => 1, // gap 5
					'2025-01-26' => 1, // gap 10
				)
			)
		);
		$this->assertSame( 10, $result['days'] );
		$this->assertSame( '2025-01-01', $result['from'] );
		$this->assertSame( '2025-01-11', $result['to'] );
	}

	// ---- timeline(): waypoints, deaths, gaps, tail in date order. ----

	private function char( string $name, string $slug, string $type, string $show = 'Show' ): array {
		return array(
			'slug'  => $slug,
			'name'  => $name,
			'shows' => array( array( 'name' => $show, 'url' => "/show/{$slug}/", 'type' => $type ) ),
		);
	}

	public function test_timeline_emits_waypoint_deaths_gap_and_tail(): void {
		$dead = array(
			'2025-04-04' => array( $this->char( 'Aa', 'aa', 'regular' ), $this->char( 'Ab', 'ab', 'guest' ) ),
			'2025-04-29' => array( $this->char( 'Ac', 'ac', 'recurring' ) ),
			'2025-07-03' => array( $this->char( 'Ba', 'ba', 'regular' ) ),
		);
		$items = Dead_Characters::timeline( $dead );
		$types = array_column( $items, 'type' );

		// April waypoint, 3 deaths, gap(May,Jun), July waypoint, 1 death, tail.
		$this->assertSame(
			array( 'waypoint', 'death', 'death', 'death', 'gap', 'waypoint', 'death', 'tail' ),
			$types
		);
		$this->assertSame( 4, $items[0]['month'] );
		$this->assertSame( 3, $items[0]['count'] );          // April total
		$this->assertSame( array( 5, 6 ), $items[4]['months'] ); // gap
		$this->assertSame( 'regular', $items[1]['role'] );    // role from shows[0]
		$this->assertSame( 4, $items[7]['total'] );
		$this->assertSame( 10, $items[7]['empty_month_count'] ); // 12 - 2 death-months
	}

	public function test_timeline_empty_month_count_respects_cap(): void {
		// Year in progress capped at July: only 7 months count toward "empty".
		$dead = array(
			'2026-03-04' => array( $this->char( 'Aa', 'aa', 'regular' ) ),
			'2026-06-29' => array( $this->char( 'Ab', 'ab', 'guest' ) ),
		);
		$items = Dead_Characters::timeline( $dead, 7 );
		$tail  = $items[ count( $items ) - 1 ];

		$this->assertSame( 'tail', $tail['type'] );
		// Jan→Jul = 7 months, 2 had deaths (Mar, Jun) → 5 empty (Jan, Feb, Apr, May, Jul).
		$this->assertSame( 5, $tail['empty_month_count'] );
	}

	public function test_timeline_same_day_deaths_repeat_the_date(): void {
		$dead = array(
			'2025-05-10' => array( $this->char( 'Aa', 'aa', 'regular' ), $this->char( 'Ab', 'ab', 'guest' ) ),
		);
		$items = Dead_Characters::timeline( $dead );
		$deaths = array_values( array_filter( $items, static fn( $i ) => 'death' === $i['type'] ) );
		$this->assertCount( 2, $deaths );
		$this->assertSame( '2025-05-10', $deaths[0]['date'] );
		$this->assertSame( '2025-05-10', $deaths[1]['date'] );
	}
}
