<?php
/**
 * Unit tests for the This Year Standouts selectors: "pick the biggest" (shared
 * by biggest ensemble + busiest actor) and "the longest-running show that
 * ended this year".
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Standouts;

class StandoutsTest extends TestCase {

	// ---- busiest(): the key with the largest count. ----

	public function test_busiest_picks_the_largest_count(): void {
		$this->assertSame(
			array(
				'key'   => 'Nightfall Bay',
				'count' => 21,
			),
			Standouts::busiest(
				array(
					'Crown & Country' => 9,
					'Nightfall Bay'   => 21,
					'The Air'         => 18,
				)
			)
		);
	}

	public function test_busiest_is_stable_on_ties(): void {
		$result = Standouts::busiest(
			array(
				'First'  => 7,
				'Second' => 7,
			)
		);

		$this->assertSame( 'First', $result['key'] );
	}

	public function test_busiest_returns_null_when_empty_or_all_zero(): void {
		$this->assertNull( Standouts::busiest( array() ) );
		$this->assertNull( Standouts::busiest( array( 'A' => 0 ) ) );
	}

	// ---- longest_run_ended(): the longest-running show that ended this year. ----

	public function test_longest_run_ended_picks_earliest_start_among_this_years_endings(): void {
		$shows = array(
			array(
				'name'   => 'Newer',
				'start'  => '2010',
				'finish' => '2019',
			),
			array(
				'name'   => 'Veteran',
				'start'  => '2005',
				'finish' => '2019',
			),
			array(
				'name'   => 'Old but ended earlier',
				'start'  => '2000',
				'finish' => '2018', // different finish year — excluded
			),
		);

		$result = Standouts::longest_run_ended( $shows, 2019 );

		$this->assertSame( 'Veteran', $result['name'] );
		$this->assertSame( 2005, $result['start_year'] );
		$this->assertSame( 14, $result['years'] ); // 2019 - 2005
	}

	public function test_longest_run_ended_ignores_still_running_shows(): void {
		$shows = array(
			array(
				'name'   => 'Still going',
				'start'  => '2001',
				'finish' => '', // no finish → still on air
			),
			array(
				'name'   => 'Ended',
				'start'  => '2015',
				'finish' => '20190607',
			),
		);

		$this->assertSame( 'Ended', Standouts::longest_run_ended( $shows, 2019 )['name'] );
	}

	public function test_longest_run_ended_returns_null_when_nothing_ended_this_year(): void {
		$shows = array(
			array(
				'name'   => 'A',
				'start'  => '2010',
				'finish' => '2018',
			),
		);

		$this->assertNull( Standouts::longest_run_ended( $shows, 2019 ) );
		$this->assertNull( Standouts::longest_run_ended( array(), 2019 ) );
	}

	public function test_longest_run_ended_skips_endings_with_no_usable_start(): void {
		$shows = array(
			array(
				'name'   => 'No start',
				'start'  => '',
				'finish' => '2019',
			),
		);

		$this->assertNull( Standouts::longest_run_ended( $shows, 2019 ) );
	}

	// ---- runs_ended(): every show that ended this year, longest run first. ----

	public function test_runs_ended_lists_this_years_endings_longest_first(): void {
		$shows = array(
			array(
				'name'   => 'Short',
				'start'  => '2016',
				'finish' => '2019',
			),
			array(
				'name'   => 'Long',
				'start'  => '2005',
				'finish' => '2019',
			),
			array(
				'name'   => 'Other year',
				'start'  => '2000',
				'finish' => '2018', // excluded
			),
		);

		$result = Standouts::runs_ended( $shows, 2019 );

		$this->assertSame( array( 'Long', 'Short' ), array_column( $result, 'name' ) );
		$this->assertSame( 14, $result[0]['years'] );
		$this->assertSame( 3, $result[1]['years'] );
	}

	public function test_runs_ended_is_empty_when_nothing_ended_this_year(): void {
		$shows = array(
			array(
				'name'   => 'A',
				'start'  => '2010',
				'finish' => '2018',
			),
		);

		$this->assertSame( array(), Standouts::runs_ended( $shows, 2019 ) );
	}
}
