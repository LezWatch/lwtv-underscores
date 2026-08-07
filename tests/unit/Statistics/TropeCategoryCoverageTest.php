<?php
/**
 * Unit tests for trope category coverage: counting shows-with-at-least-one
 * trope per good/maybe/bad/ploy bucket, including shows that land in more
 * than one bucket and slugs outside all four lists.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Trope_Category_Coverage;

class TropeCategoryCoverageTest extends TestCase {

	public function test_counts_one_show_per_category(): void {
		$out = Trope_Category_Coverage::count(
			array(
				1 => array( 'happy-ending' ),          // good
				2 => array( 'coming-out' ),             // maybe
				3 => array( 'queerbashing' ),           // bad
				4 => array( 'queer-for-ratings' ),      // ploy
			)
		);

		$this->assertSame(
			array(
				'good'  => 1,
				'maybe' => 1,
				'bad'   => 1,
				'ploy'  => 1,
			),
			$out
		);
	}

	public function test_show_in_multiple_categories_counts_toward_each(): void {
		$out = Trope_Category_Coverage::count(
			array(
				1 => array( 'happy-ending', 'queerbashing' ), // good AND bad on the same show.
			)
		);

		$this->assertSame( 1, $out['good'] );
		$this->assertSame( 1, $out['bad'] );
		$this->assertSame( 0, $out['maybe'] );
		$this->assertSame( 0, $out['ploy'] );
	}

	public function test_duplicate_slugs_on_one_show_count_once(): void {
		$out = Trope_Category_Coverage::count(
			array(
				1 => array( 'happy-ending', 'happy-ending', 'everyones-queer' ),
			)
		);

		$this->assertSame( 1, $out['good'] );
	}

	public function test_uncategorized_slugs_and_none_are_ignored(): void {
		$out = Trope_Category_Coverage::count(
			array(
				1 => array( 'literary-inspired' ), // not in any of the four lists.
				2 => array( 'none' ),
				3 => array(),
			)
		);

		$this->assertSame(
			array(
				'good'  => 0,
				'maybe' => 0,
				'bad'   => 0,
				'ploy'  => 0,
			),
			$out
		);
	}

	public function test_empty_input(): void {
		$this->assertSame(
			array(
				'good'  => 0,
				'maybe' => 0,
				'bad'   => 0,
				'ploy'  => 0,
			),
			Trope_Category_Coverage::count( array() )
		);
	}

	public function test_category_sets_groups_categories_per_show(): void {
		$out = Trope_Category_Coverage::category_sets(
			array(
				1 => array( 'happy-ending', 'queerbashing' ), // good + bad, same show.
				2 => array( 'happy-ending' ),                  // good only.
				3 => array( 'literary-inspired' ),             // uncategorized, dropped entirely.
			)
		);

		$this->assertSame(
			array(
				1 => array( 'good', 'bad' ),
				2 => array( 'good' ),
			),
			$out
		);
	}

	public function test_category_sets_three_way_mix(): void {
		$out = Trope_Category_Coverage::category_sets(
			array(
				1 => array( 'happy-ending', 'coming-out', 'queerbashing' ), // good + maybe + bad.
			)
		);

		$this->assertSame( array( 1 => array( 'good', 'maybe', 'bad' ) ), $out );
	}

	public function test_category_sets_empty_input(): void {
		$this->assertSame( array(), Trope_Category_Coverage::category_sets( array() ) );
	}

	public function test_alignment_split_counts_pure_vs_mixed(): void {
		$sets = Trope_Category_Coverage::category_sets(
			array(
				1 => array( 'happy-ending', 'queerbashing' ), // mixed.
				2 => array( 'happy-ending' ),                  // pure.
			)
		);

		$this->assertSame(
			array(
				'pure'      => 1,
				'mixed'     => 1,
				'mixed_pct' => 50.0,
			),
			Trope_Category_Coverage::alignment_split( $sets )
		);
	}

	public function test_alignment_split_all_pure(): void {
		$sets = Trope_Category_Coverage::category_sets(
			array(
				1 => array( 'happy-ending' ),
				2 => array( 'coming-out' ),
				3 => array( 'queerbashing' ),
			)
		);

		$this->assertSame(
			array(
				'pure'      => 3,
				'mixed'     => 0,
				'mixed_pct' => 0.0,
			),
			Trope_Category_Coverage::alignment_split( $sets )
		);
	}

	public function test_alignment_split_empty_input(): void {
		$this->assertSame(
			array(
				'pure'      => 0,
				'mixed'     => 0,
				'mixed_pct' => 0.0,
			),
			Trope_Category_Coverage::alignment_split( array() )
		);
	}
}
