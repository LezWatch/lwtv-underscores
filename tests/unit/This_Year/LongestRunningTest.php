<?php
/**
 * Unit tests for the "longest-running character we lost" selection logic.
 *
 * Tenure is measured as the number of DISTINCT years a character was actually
 * on air (the union of their shows' `appears` years) — not the calendar span
 * from debut to death, which overstates gappy careers (e.g. a character in four
 * one-season mini-series across 26 years was only ever on screen for 4 years).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Longest_Running;

class LongestRunningTest extends TestCase {

	// ---- tenure(): distinct years on air, plus debut year + debut show. ----

	public function test_tenure_counts_distinct_years_across_shows(): void {
		// Four one-season mini-series across 26 calendar years = 4 years on air.
		$show_group = array(
			array(
				'show'    => 12,
				'appears' => array( '1993' ),
			),
			array(
				'show'    => 34,
				'appears' => array( '1998' ),
			),
			array(
				'show'    => 56,
				'appears' => array( '2001' ),
			),
			array(
				'show'    => 78,
				'appears' => array( '2019' ),
			),
		);

		$result = Longest_Running::tenure( $show_group );

		$this->assertSame( 4, $result['years'] );
		$this->assertSame( 1993, $result['first_year'] );
		$this->assertSame( 12, $result['show_id'] ); // the show of the earliest appearance
	}

	public function test_tenure_dedupes_years_shared_across_shows(): void {
		$show_group = array(
			array(
				'show'    => 1,
				'appears' => array( '2010', '2011' ),
			),
			array(
				'show'    => 2,
				'appears' => array( '2011', '2012' ), // 2011 overlaps
			),
		);

		// Distinct years: 2010, 2011, 2012 = 3.
		$this->assertSame( 3, Longest_Running::tenure( $show_group )['years'] );
	}

	public function test_tenure_ignores_invalid_years(): void {
		$show_group = array(
			array(
				'show'    => 5,
				'appears' => array( '', '0', 'n/a', '2019' ),
			),
		);

		$this->assertSame( 1, Longest_Running::tenure( $show_group )['years'] );
	}

	public function test_tenure_is_zero_for_no_usable_data(): void {
		$this->assertSame(
			array(
				'years'      => 0,
				'first_year' => null,
				'show_id'    => null,
			),
			Longest_Running::tenure( array() )
		);
	}

	// ---- pick(): the candidate with the most years on air. ----

	public function test_pick_returns_the_most_years_on_air(): void {
		$candidates = array(
			array(
				'name'  => 'Mini-series veteran', // early debut, few years
				'years' => 4,
			),
			array(
				'name'  => 'Soap regular', // later debut, many years
				'years' => 15,
			),
		);

		$this->assertSame( 'Soap regular', Longest_Running::pick( $candidates )['name'] );
	}

	public function test_pick_breaks_ties_on_earliest_debut(): void {
		$candidates = array(
			array(
				'name'       => 'Later',
				'years'      => 6,
				'first_year' => 2014,
			),
			array(
				'name'       => 'Earlier',
				'years'      => 6,
				'first_year' => 2009,
			),
		);

		$this->assertSame( 'Earlier', Longest_Running::pick( $candidates )['name'] );
	}

	public function test_pick_skips_candidates_with_no_years(): void {
		$candidates = array(
			array(
				'name'  => 'Unknown',
				'years' => 0,
			),
			array(
				'name'  => 'Known',
				'years' => 2,
			),
		);

		$this->assertSame( 'Known', Longest_Running::pick( $candidates )['name'] );
	}

	public function test_pick_returns_null_when_no_candidate_qualifies(): void {
		$this->assertNull( Longest_Running::pick( array() ) );
		$this->assertNull( Longest_Running::pick( array( array( 'years' => 0 ) ) ) );
	}
}
