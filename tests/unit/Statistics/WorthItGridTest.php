<?php
/**
 * Unit tests for the Worth It grid transform: hundred-square allocation
 * with the sum-to-100 guard (largest verdict absorbs rounding drift,
 * non-zero verdicts always get a square), per-verdict score averages,
 * and the "verdict tracks the score" claim check.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Worth_It_Grid;

class WorthItGridTest extends TestCase {

	private const CURRENT = array(
		'yes' => 1407,
		'meh' => 565,
		'no'  => 266,
		'tbd' => 17,
	);

	/*
	 * squares()
	 */

	public function test_squares_current_data_sums_to_exactly_100(): void {
		$out = Worth_It_Grid::squares( self::CURRENT );

		// 62.4 / 25.1 / 11.8 / 0.8 → 62 / 25 / 12 / 1.
		$this->assertSame( 62, $out['yes'] );
		$this->assertSame( 25, $out['meh'] );
		$this->assertSame( 12, $out['no'] );
		$this->assertSame( 1, $out['tbd'] );
		$this->assertSame( 100, array_sum( $out ) );
	}

	public function test_squares_drift_is_absorbed_by_the_largest_verdict(): void {
		// Three equal thirds round to 33+33+33 = 99; the first-largest
		// verdict gets the spare square so the grid always fills.
		$out = Worth_It_Grid::squares(
			array(
				'yes' => 1,
				'meh' => 1,
				'no'  => 1,
			)
		);

		$this->assertSame( 100, array_sum( $out ) );
		$this->assertSame( 34, $out['yes'] );
		$this->assertSame( 33, $out['meh'] );
		$this->assertSame( 33, $out['no'] );
	}

	public function test_squares_tiny_nonzero_verdict_never_disappears(): void {
		// 1 of 1000 = 0.1% → rounds to 0, but a real verdict must show a square.
		$out = Worth_It_Grid::squares(
			array(
				'yes' => 999,
				'tbd' => 1,
			)
		);

		$this->assertSame( 1, $out['tbd'] );
		$this->assertSame( 99, $out['yes'] ); // Largest absorbs the overflow.
		$this->assertSame( 100, array_sum( $out ) );
	}

	public function test_squares_zero_verdicts_get_no_squares(): void {
		$out = Worth_It_Grid::squares(
			array(
				'yes' => 10,
				'no'  => 0,
			)
		);

		$this->assertArrayNotHasKey( 'no', $out );
		$this->assertSame( 100, array_sum( $out ) );
	}

	public function test_squares_empty_input(): void {
		$this->assertSame( array(), Worth_It_Grid::squares( array() ) );
		$this->assertSame( array(), Worth_It_Grid::squares( array( 'yes' => 0 ) ) );
	}

	/*
	 * averages()
	 */

	public function test_averages_rounds_to_whole_numbers_with_counts(): void {
		$out = Worth_It_Grid::averages(
			array(
				'yes' => array( 80, 85 ),
				'no'  => array( 40, 45, 47 ),
			)
		);

		$this->assertSame( 83, $out['yes']['average'] ); // 82.5 rounds up.
		$this->assertSame( 2, $out['yes']['count'] );
		$this->assertSame( 44, $out['no']['average'] );
		$this->assertSame( 3, $out['no']['count'] );
	}

	public function test_averages_sanitizes_and_drops_empty_verdicts(): void {
		$out = Worth_It_Grid::averages(
			array(
				'yes' => array( '90', 'junk', null ),
				'tbd' => array(),
			)
		);

		$this->assertSame( 90, $out['yes']['average'] );
		$this->assertSame( 1, $out['yes']['count'] );
		$this->assertArrayNotHasKey( 'tbd', $out );
	}

	/*
	 * tracks_score()
	 */

	public function test_tracks_score_true_when_ordinal_and_spread_is_real(): void {
		$this->assertTrue(
			Worth_It_Grid::tracks_score(
				array(
					'yes' => array( 'average' => 82 ),
					'meh' => array( 'average' => 61 ),
					'no'  => array( 'average' => 43 ),
				)
			)
		);
	}

	public function test_tracks_score_false_when_order_breaks(): void {
		// Meh outscoring Yes breaks the ordinal claim.
		$this->assertFalse(
			Worth_It_Grid::tracks_score(
				array(
					'yes' => array( 'average' => 70 ),
					'meh' => array( 'average' => 75 ),
					'no'  => array( 'average' => 43 ),
				)
			)
		);
	}

	public function test_tracks_score_false_when_spread_is_narrow(): void {
		// Ordinal but flat (71/64/58 style): the claim would oversell it.
		$this->assertFalse(
			Worth_It_Grid::tracks_score(
				array(
					'yes' => array( 'average' => 66 ),
					'meh' => array( 'average' => 64 ),
					'no'  => array( 'average' => 58 ),
				)
			)
		);
	}

	public function test_tracks_score_false_when_a_verdict_is_missing(): void {
		$this->assertFalse(
			Worth_It_Grid::tracks_score(
				array(
					'yes' => array( 'average' => 82 ),
					'no'  => array( 'average' => 43 ),
				)
			)
		);
	}
}
