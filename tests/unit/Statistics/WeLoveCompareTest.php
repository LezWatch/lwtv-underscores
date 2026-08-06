<?php
/**
 * Unit tests for the Shows We Love transforms: cohort facts from the
 * roster rows, loved-side totals, the multiple/direction/largest-gap
 * helpers behind the adaptive takeaways, and the versus() composition
 * that drives the comparison section (including the leads-all heading
 * gate).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\We_Love_Compare;

class WeLoveCompareTest extends TestCase {

	private function rows(): array {
		return array(
			array(
				'start'     => 2004,
				'finish'    => 2009,
				'airing'    => false,
				'chars'     => 12,
				'actors'    => 4,
				'dead'      => 2,
				'gold'      => true,
				'happy'     => true,
				'countries' => array( 'usa' ),
			),
			array(
				'start'     => 2019,
				'finish'    => 0,
				'airing'    => true,
				'chars'     => 6,
				'actors'    => 2,
				'dead'      => 0,
				'gold'      => false,
				'happy'     => false,
				'countries' => array( 'usa', 'canada' ),
			),
			array(
				'start'     => 0, // Unknown premiere.
				'finish'    => 0,
				'airing'    => false,
				'chars'     => 3,
				'actors'    => 0,
				'dead'      => 0,
				'gold'      => true,
				'happy'     => true,
				'countries' => array( 'uk' ),
			),
		);
	}

	/*
	 * cohort()
	 */

	public function test_cohort_facts(): void {
		$out = We_Love_Compare::cohort( $this->rows() );

		$this->assertSame( 2, $out['gold'] );
		$this->assertSame( 1, $out['airing'] );
		$this->assertSame( 2004, $out['span_min'] ); // Unknown (0) years ignored.
		$this->assertSame( 2019, $out['span_max'] );
		$this->assertSame( 3, $out['countries'] );   // usa, canada, uk — deduped.
	}

	public function test_cohort_empty(): void {
		$out = We_Love_Compare::cohort( array() );

		$this->assertSame( 0, $out['gold'] );
		$this->assertSame( 0, $out['span_min'] );
	}

	/*
	 * loved_totals()
	 */

	public function test_loved_totals(): void {
		$out = We_Love_Compare::loved_totals( $this->rows() );

		$this->assertSame( 3, $out['n'] );
		$this->assertSame( 21, $out['chars_sum'] );
		$this->assertSame( 6, $out['actors_sum'] );
		$this->assertSame( 2, $out['happy'] );
		$this->assertSame( 1, $out['deadly'] ); // dead > 0 counts once per show.
	}

	/*
	 * multiple()
	 */

	public function test_multiple_two_x_and_up(): void {
		$out = We_Love_Compare::multiple( 9.0, 4.0 ); // 2.25×.

		$this->assertSame( 'multiple', $out['mode'] );
		$this->assertSame( 2, $out['times'] );
	}

	public function test_multiple_three_x(): void {
		$this->assertSame( 3, We_Love_Compare::multiple( 3.0, 1.0 )['times'] );
	}

	public function test_multiple_under_two_x_is_more(): void {
		$this->assertSame( 'more', We_Love_Compare::multiple( 6.0, 4.0 )['mode'] ); // 1.5×.
	}

	public function test_multiple_within_ten_percent_is_about_same(): void {
		$this->assertSame( 'about-same', We_Love_Compare::multiple( 4.2, 4.0 )['mode'] );
		$this->assertSame( 'about-same', We_Love_Compare::multiple( 4.0, 4.2 )['mode'] );
	}

	public function test_multiple_loved_behind_is_fewer(): void {
		$this->assertSame( 'fewer', We_Love_Compare::multiple( 2.0, 4.0 )['mode'] );
	}

	public function test_multiple_zero_rest_guard(): void {
		// "N times zero" is nonsense; degrade to the plain "more" phrasing.
		$this->assertSame( 'more', We_Love_Compare::multiple( 5.0, 0.0 )['mode'] );
		$this->assertSame( 'about-same', We_Love_Compare::multiple( 0.0, 0.0 )['mode'] );
	}

	/*
	 * direction()
	 */

	public function test_direction_with_tolerance(): void {
		$this->assertSame( 'higher', We_Love_Compare::direction( 25.0, 23.0 ) );
		$this->assertSame( 'same', We_Love_Compare::direction( 23.5, 23.0 ) );
		$this->assertSame( 'lower', We_Love_Compare::direction( 20.0, 23.0 ) );
	}

	/*
	 * largest_gap()
	 */

	public function test_largest_gap_by_relative_ratio(): void {
		$out = We_Love_Compare::largest_gap(
			array(
				'chars' => array( 9.0, 4.0 ),   // 2.25×.
				'happy' => array( 40.6, 17.0 ), // 2.39× — the clearest gap.
				'deaths' => array( 25.0, 23.0 ),
			)
		);

		$this->assertSame( 'happy', $out );
	}

	public function test_largest_gap_zero_side_wins(): void {
		$out = We_Love_Compare::largest_gap(
			array(
				'chars' => array( 9.0, 4.0 ),
				'happy' => array( 40.0, 0.0 ), // Unbounded gap.
			)
		);

		$this->assertSame( 'happy', $out );
	}

	/*
	 * versus()
	 */

	public function test_versus_composition(): void {
		$loved = array(
			'n'          => 32,
			'chars_sum'  => 288, // avg 9.0.
			'actors_sum' => 96,  // avg 3.0.
			'happy'      => 13,
			'deadly'     => 8,
		);
		// Archive totals INCLUDE the loved shows; versus() subtracts them.
		$archive = array(
			'n'          => 2255,
			'chars_sum'  => 9180,  // rest: 8892/2223 = 4.0.
			'actors_sum' => 2319,  // rest: 2223/2223 = 1.0.
			'happy'      => 395,   // rest: 382/2223 = 17.2%.
			'deadly'     => 519,   // rest: 511/2223 = 23.0%.
		);

		$out = We_Love_Compare::versus( $loved, $archive );

		$this->assertSame( 9.0, $out['chars']['loved'] );
		$this->assertSame( 4.0, $out['chars']['rest'] );
		$this->assertSame( 'multiple', $out['chars']['mode'] );
		$this->assertSame( 3, $out['actors']['times'] );

		$this->assertSame( 13, $out['happy']['loved_count'] );
		$this->assertSame( 40.6, $out['happy']['loved_pct'] );
		$this->assertSame( 17.2, $out['happy']['rest_pct'] );

		$this->assertSame( 8, $out['deaths']['loved_count'] );
		$this->assertSame( 25.0, $out['deaths']['loved_pct'] );
		$this->assertSame( 'higher', $out['deaths']['direction'] );

		// Loved leads chars, actors, and happy endings → the claim heading holds.
		$this->assertTrue( $out['leads_all'] );

		// With these figures actors (3.0×) outranks happy endings (2.4×), so
		// the "clearest gap on the page" clause must NOT attach to happy —
		// exactly the ranking check the handoff asks for.
		$this->assertSame( 'actors', $out['largest_gap'] );
	}

	public function test_versus_leads_all_fails_when_one_metric_slips(): void {
		$loved   = array(
			'n'          => 32,
			'chars_sum'  => 96, // avg 3.0 — below the rest.
			'actors_sum' => 96,
			'happy'      => 13,
			'deadly'     => 8,
		);
		$archive = array(
			'n'          => 2255,
			'chars_sum'  => 8988, // rest avg 4.0.
			'actors_sum' => 2319,
			'happy'      => 395,
			'deadly'     => 519,
		);

		$this->assertFalse( We_Love_Compare::versus( $loved, $archive )['leads_all'] );
	}

	public function test_versus_empty_loved(): void {
		$this->assertSame( array(), We_Love_Compare::versus( array( 'n' => 0 ), array( 'n' => 100 ) ) );
	}
}
