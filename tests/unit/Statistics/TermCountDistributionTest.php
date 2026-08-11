<?php
/**
 * Unit tests for term count distribution: bucketing objects by how many
 * distinct terms they carry, including the "0" bucket for objects that
 * never appear in the term-relationship map at all, an excluded placeholder
 * slug (e.g. "none") collapsing an object into the 0 bucket, and the
 * overflow bucket collapsing high counts.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Term_Count_Distribution;

class TermCountDistributionTest extends TestCase {

	public function test_basic_bucketing(): void {
		$out = Term_Count_Distribution::build(
			array(
				1 => array( 'a' ),
				2 => array( 'a', 'b' ),
				3 => array( 'a', 'b' ),
			),
			3
		);

		$this->assertSame(
			array(
				array( 'label' => '0', 'count' => 0, 'pct' => 0.0 ),
				array( 'label' => '1', 'count' => 1, 'pct' => round( 1 / 3 * 100, 1 ) ),
				array( 'label' => '2', 'count' => 2, 'pct' => round( 2 / 3 * 100, 1 ) ),
				array( 'label' => '3', 'count' => 0, 'pct' => 0.0 ),
				array( 'label' => '4+', 'count' => 0, 'pct' => 0.0 ),
			),
			$out
		);
	}

	public function test_objects_missing_from_map_land_in_zero_bucket(): void {
		// Map only knows about 2 objects, but 5 objects exist in total —
		// the other 3 never had a term relationship row at all.
		$out = Term_Count_Distribution::build(
			array(
				1 => array( 'a' ),
				2 => array( 'a' ),
			),
			5
		);

		$zero_bucket = $out[0];
		$this->assertSame( '0', $zero_bucket['label'] );
		$this->assertSame( 3, $zero_bucket['count'] );

		$one_bucket = $out[1];
		$this->assertSame( '1', $one_bucket['label'] );
		$this->assertSame( 2, $one_bucket['count'] );
	}

	public function test_excluded_slug_collapses_to_zero_bucket(): void {
		$out = Term_Count_Distribution::build(
			array(
				1 => array( 'none' ),
				2 => array( 'none', 'a' ),
			),
			2,
			array( 'none' )
		);

		$this->assertSame( 1, $out[0]['count'] ); // Object 1: only "none" -> 0 real tropes.
		$this->assertSame( 1, $out[1]['count'] ); // Object 2: "none" dropped, "a" remains -> 1.
	}

	public function test_duplicate_slugs_on_one_object_count_once(): void {
		$out = Term_Count_Distribution::build(
			array(
				1 => array( 'a', 'a', 'b' ),
			),
			1
		);

		$this->assertSame( 0, $out[0]['count'] );
		$this->assertSame( 0, $out[1]['count'] );
		$this->assertSame( 1, $out[2]['count'] ); // 2 distinct slugs.
	}

	public function test_overflow_bucket_collapses_high_counts(): void {
		$out = Term_Count_Distribution::build(
			array(
				1 => array( 'a', 'b', 'c', 'd', 'e', 'f' ), // 6 distinct slugs.
			),
			1,
			array(),
			4
		);

		$overflow = end( $out );
		$this->assertSame( '4+', $overflow['label'] );
		$this->assertSame( 1, $overflow['count'] );
	}

	public function test_empty_input_with_zero_total(): void {
		$out = Term_Count_Distribution::build( array(), 0 );

		foreach ( $out as $bucket ) {
			$this->assertSame( 0, $bucket['count'] );
			$this->assertSame( 0.0, $bucket['pct'] );
		}
	}

	public function test_to_cells_sums_to_exactly_the_requested_total(): void {
		// Counts that don't divide evenly into 100 (620/2130 etc. all carry
		// rounding error) — the whole point of to_cells() is that the sum
		// still lands on exactly 100 despite that.
		$buckets = array(
			array( 'count' => 300 ),
			array( 'count' => 620 ),
			array( 'count' => 700 ),
			array( 'count' => 360 ),
			array( 'count' => 150 ),
		);
		$total = 300 + 620 + 700 + 360 + 150;

		$cells = Term_Count_Distribution::to_cells( $buckets, $total, 100 );

		$this->assertSame( array( 14, 29, 33, 17, 7 ), $cells );
		$this->assertSame( 100, array_sum( $cells ) );
	}

	public function test_to_cells_even_three_way_split_still_sums_correctly(): void {
		// 1/3 each of 100 cells: 33.33… repeating three times — floors give
		// 99, and the single leftover cell should go to the first bucket
		// (all three remainders tie, so insertion order wins).
		$buckets = array(
			array( 'count' => 1 ),
			array( 'count' => 1 ),
			array( 'count' => 1 ),
		);

		$cells = Term_Count_Distribution::to_cells( $buckets, 3, 100 );

		$this->assertSame( 100, array_sum( $cells ) );
		$this->assertSame( array( 34, 33, 33 ), $cells );
	}

	public function test_to_cells_zero_total_returns_all_zero(): void {
		$cells = Term_Count_Distribution::to_cells(
			array( array( 'count' => 0 ), array( 'count' => 0 ) ),
			0,
			100
		);

		$this->assertSame( array( 0, 0 ), $cells );
	}

	public function test_to_cells_single_bucket_gets_every_cell(): void {
		$cells = Term_Count_Distribution::to_cells(
			array( array( 'count' => 5 ), array( 'count' => 0 ) ),
			5,
			100
		);

		$this->assertSame( array( 100, 0 ), $cells );
	}

	public function test_top_object_picks_the_highest_count(): void {
		$out = Term_Count_Distribution::top_object(
			array(
				5 => array( 'a', 'b' ),
				2 => array( 'a' ),
				9 => array( 'a', 'b', 'c' ),
			)
		);

		$this->assertSame( array( 'id' => 9, 'count' => 3, 'tied' => 1 ), $out );
	}

	public function test_top_object_reports_ties_and_picks_lowest_id(): void {
		$out = Term_Count_Distribution::top_object(
			array(
				5 => array( 'a', 'b' ),
				2 => array( 'a', 'b' ),
				9 => array( 'a' ),
			)
		);

		$this->assertSame( array( 'id' => 2, 'count' => 2, 'tied' => 2 ), $out );
	}

	public function test_top_object_respects_excluded_slugs(): void {
		$out = Term_Count_Distribution::top_object(
			array(
				5 => array( 'none' ),
				2 => array( 'none', 'a' ),
			),
			array( 'none' )
		);

		$this->assertSame( array( 'id' => 2, 'count' => 1, 'tied' => 1 ), $out );
	}

	public function test_top_object_duplicate_slugs_count_once(): void {
		$out = Term_Count_Distribution::top_object(
			array(
				5 => array( 'a', 'a', 'b' ),
			)
		);

		$this->assertSame( array( 'id' => 5, 'count' => 2, 'tied' => 1 ), $out );
	}

	public function test_top_object_empty_input(): void {
		$out = Term_Count_Distribution::top_object( array() );

		$this->assertSame( array( 'id' => 0, 'count' => 0, 'tied' => 0 ), $out );
	}
}
