<?php
/**
 * Unit tests for the character-identity-by-decade bucketer: decade rollup,
 * folding sparse leading decades together, the never-clears-threshold
 * fallback, and lead-term/percentage math. Same shape as
 * FormatDecadeBucketsTest — Character_Identity_Decade_Buckets mirrors
 * Format_Decade_Buckets exactly, just for a character-level single-value
 * taxonomy (lez_gender / lez_sexuality) instead of a show-level one.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Character_Identity_Decade_Buckets;

class CharacterIdentityDecadeBucketsTest extends TestCase {

	public function test_empty_input_returns_empty(): void {
		$this->assertSame( array(), Character_Identity_Decade_Buckets::build( array() ) );
	}

	public function test_single_well_populated_decade_stands_alone(): void {
		$out = Character_Identity_Decade_Buckets::build(
			array(
				2021 => array( 'Cisgender' => 30 ),
				2022 => array( 'Cisgender' => 25 ),
			),
			20
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'decade', $out[0]['type'] );
		$this->assertSame( 2020, $out[0]['from'] );
		$this->assertSame( 2030, $out[0]['to'] );
		$this->assertSame( 55, $out[0]['total'] );
		$this->assertSame( 'Cisgender', $out[0]['lead_term'] );
		$this->assertSame( 100.0, $out[0]['lead_pct'] );
	}

	public function test_sparse_leading_decades_fold_together(): void {
		// 1950s (1) + 1960s (10) + 1970s (31) = 42 characters, clearing a
		// 20-character floor. 1980s onward stand on their own.
		$out = Character_Identity_Decade_Buckets::build(
			array(
				1955 => array( 'Cisgender' => 1 ),
				1965 => array( 'Cisgender' => 10 ),
				1971 => array(
					'Cisgender'   => 25,
					'Non-Binary'  => 1,
					'Trans Woman' => 5,
				),
				1985 => array(
					'Cisgender'   => 47,
					'Trans Woman' => 7,
					'Non-Binary'  => 2,
				),
			),
			20
		);

		$this->assertCount( 2, $out );

		$before = $out[0];
		$this->assertSame( 'before', $before['type'] );
		$this->assertNull( $before['from'] );
		$this->assertSame( 1980, $before['to'] );
		$this->assertSame( 42, $before['total'] );
		$this->assertSame( array( 'Cisgender' => 36, 'Non-Binary' => 1, 'Trans Woman' => 5 ), $before['terms'] );
		$this->assertSame( 'Cisgender', $before['lead_term'] );
		$this->assertSame( 85.7, $before['lead_pct'] );

		$decade_80s = $out[1];
		$this->assertSame( 'decade', $decade_80s['type'] );
		$this->assertSame( 1980, $decade_80s['from'] );
		$this->assertSame( 1990, $decade_80s['to'] );
		$this->assertSame( 56, $decade_80s['total'] );
		$this->assertSame( 83.9, $decade_80s['pcts']['Cisgender'] );
	}

	public function test_never_clearing_threshold_still_emits_a_bucket(): void {
		$out = Character_Identity_Decade_Buckets::build(
			array(
				1962 => array( 'Cisgender' => 3 ),
				1974 => array( 'Cisgender' => 2 ),
			),
			20
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'before', $out[0]['type'] );
		$this->assertNull( $out[0]['to'] );
		$this->assertSame( 5, $out[0]['total'] );
	}

	public function test_tied_lead_term_keeps_first_seen(): void {
		$out = Character_Identity_Decade_Buckets::build(
			array(
				2021 => array(
					'Cisgender'  => 10,
					'Non-Binary' => 10,
				),
			),
			1
		);

		$this->assertSame( 'Cisgender', $out[0]['lead_term'] );
	}

	public function test_non_numeric_and_zero_years_are_ignored(): void {
		$out = Character_Identity_Decade_Buckets::build(
			array(
				0     => array( 'Cisgender' => 5 ),
				-1    => array( 'Cisgender' => 5 ),
				2021 => array( 'Cisgender' => 5 ),
			),
			1
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 5, $out[0]['total'] );
	}

	public function test_a_first_decade_that_already_clears_the_floor_stands_alone(): void {
		// Guards the same fix Format_Decade_Buckets/Genre_Decade_Buckets
		// both carry: a well-populated FIRST decade should not get wrapped
		// in a "before" label it doesn't need.
		$out = Character_Identity_Decade_Buckets::build(
			array(
				1998 => array( 'Cisgender' => 25 ),
			),
			20
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'decade', $out[0]['type'] );
		$this->assertSame( 1990, $out[0]['from'] );
	}

	public function test_zero_total_guards_against_division_by_zero(): void {
		$out = Character_Identity_Decade_Buckets::build(
			array(
				2021 => array( 'Cisgender' => 0 ),
			),
			1
		);

		$this->assertSame( 0, $out[0]['total'] );
		$this->assertSame( 0.0, $out[0]['pcts']['Cisgender'] );
	}
}
