<?php
/**
 * Unit tests for the intersection pair (co-occurrence) transform: pair
 * counting across shows, canonical ordering, dedupe, tie-breaking, and
 * the top-N / minimum-count ranking filter.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Intersection_Pairs;

class IntersectionPairsTest extends TestCase {

	public function test_count_pairs_counts_cooccurrence_across_objects(): void {
		$out = Intersection_Pairs::count_pairs(
			array(
				101 => array( 'poc-centric', 'diverse-cast' ),
				102 => array( 'poc-centric', 'diverse-cast', 'immigrants' ),
				103 => array( 'poc-centric', 'immigrants' ),
			)
		);

		// 102 contributes three pairs; 101 and 103 one each.
		$this->assertCount( 3, $out );
		$this->assertSame( array( 'diverse-cast', 'poc-centric' ), $out[0]['slugs'] );
		$this->assertSame( 2, $out[0]['count'] );
		$this->assertSame( array( 'immigrants', 'poc-centric' ), $out[1]['slugs'] );
		$this->assertSame( 2, $out[1]['count'] );
		$this->assertSame( array( 'diverse-cast', 'immigrants' ), $out[2]['slugs'] );
		$this->assertSame( 1, $out[2]['count'] );
	}

	public function test_count_pairs_slugs_are_canonically_sorted(): void {
		// Same pair in both orders must fold into one canonical entry.
		$out = Intersection_Pairs::count_pairs(
			array(
				1 => array( 'zeta', 'alpha' ),
				2 => array( 'alpha', 'zeta' ),
			)
		);

		$this->assertCount( 1, $out );
		$this->assertSame( array( 'alpha', 'zeta' ), $out[0]['slugs'] );
		$this->assertSame( 2, $out[0]['count'] );
	}

	public function test_count_pairs_ignores_singletons_and_dedupes(): void {
		$out = Intersection_Pairs::count_pairs(
			array(
				1 => array( 'solo' ),                              // one term: no pair.
				2 => array(),                                      // no terms: nothing.
				3 => array( 'dupe', 'dupe', 'other' ),             // dupe counted once.
			)
		);

		$this->assertCount( 1, $out );
		$this->assertSame( array( 'dupe', 'other' ), $out[0]['slugs'] );
		$this->assertSame( 1, $out[0]['count'] );
	}

	public function test_count_pairs_ties_break_alphabetically(): void {
		$out = Intersection_Pairs::count_pairs(
			array(
				1 => array( 'bravo', 'charlie' ),
				2 => array( 'alpha', 'delta' ),
			)
		);

		// Equal counts: alpha+delta sorts before bravo+charlie.
		$this->assertSame( array( 'alpha', 'delta' ), $out[0]['slugs'] );
		$this->assertSame( array( 'bravo', 'charlie' ), $out[1]['slugs'] );
	}

	public function test_count_pairs_empty_input(): void {
		$this->assertSame( array(), Intersection_Pairs::count_pairs( array() ) );
	}

	public function test_top_pairs_takes_n_and_applies_minimum(): void {
		$pairs = array(
			array(
				'slugs' => array( 'a', 'b' ),
				'count' => 5,
			),
			array(
				'slugs' => array( 'a', 'c' ),
				'count' => 3,
			),
			array(
				'slugs' => array( 'b', 'c' ),
				'count' => 1,
			),
		);

		// min 2 drops the count-1 pair even though take allows it.
		$out = Intersection_Pairs::top_pairs( $pairs, 8, 2 );
		$this->assertCount( 2, $out );

		// take 1 keeps only the strongest pair.
		$out = Intersection_Pairs::top_pairs( $pairs, 1, 1 );
		$this->assertCount( 1, $out );
		$this->assertSame( 5, $out[0]['count'] );
	}

	public function test_top_pairs_empty_input(): void {
		$this->assertSame( array(), Intersection_Pairs::top_pairs( array(), 8, 1 ) );
	}
}
