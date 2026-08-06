<?php
/**
 * Unit tests for the format-by-decade bucketer: decade rollup, folding
 * sparse leading decades together, the never-clears-threshold fallback,
 * and lead-format/percentage math.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Format_Decade_Buckets;

class FormatDecadeBucketsTest extends TestCase {

	public function test_empty_input_returns_empty(): void {
		$this->assertSame( array(), Format_Decade_Buckets::build( array() ) );
	}

	public function test_single_well_populated_decade_stands_alone(): void {
		$out = Format_Decade_Buckets::build(
			array(
				2021 => array( 'TV Show' => 30 ),
				2022 => array( 'TV Show' => 25 ),
			),
			20
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'decade', $out[0]['type'] );
		$this->assertSame( 2020, $out[0]['from'] );
		$this->assertSame( 2030, $out[0]['to'] );
		$this->assertSame( 55, $out[0]['total'] );
		$this->assertSame( 'TV Show', $out[0]['lead_format'] );
		$this->assertSame( 100.0, $out[0]['lead_pct'] );
	}

	public function test_sparse_leading_decades_fold_together(): void {
		// 1950s (1) + 1960s (10) + 1970s (31) = 42, clearing a 20-show floor.
		// 1980s onward stand on their own.
		$out = Format_Decade_Buckets::build(
			array(
				1955 => array( 'TV Show' => 1 ),
				1965 => array( 'TV Show' => 10 ),
				1971 => array(
					'TV Show'     => 25,
					'Mini-Series' => 1,
					'TV Movie'    => 5,
				),
				1985 => array(
					'TV Show'     => 47,
					'TV Movie'    => 7,
					'Mini-Series' => 2,
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
		$this->assertSame( array( 'TV Show' => 36, 'Mini-Series' => 1, 'TV Movie' => 5 ), $before['formats'] );
		$this->assertSame( 'TV Show', $before['lead_format'] );
		$this->assertSame( 85.7, $before['lead_pct'] );

		$decade_80s = $out[1];
		$this->assertSame( 'decade', $decade_80s['type'] );
		$this->assertSame( 1980, $decade_80s['from'] );
		$this->assertSame( 1990, $decade_80s['to'] );
		$this->assertSame( 56, $decade_80s['total'] );
		$this->assertSame( 83.9, $decade_80s['pcts']['TV Show'] );
	}

	public function test_reproduces_real_catalogue_shape(): void {
		// The actual per-decade tallies pulled from the live site, collapsed
		// to one row per decade (build() re-derives decades from years, so
		// a single representative year per decade is enough here).
		$out = Format_Decade_Buckets::build(
			array(
				1950 => array( 'TV Show' => 1 ),
				1960 => array( 'TV Show' => 10 ),
				1970 => array(
					'TV Show'     => 25,
					'Mini-Series' => 1,
					'TV Movie'    => 5,
				),
				1980 => array(
					'TV Movie'    => 7,
					'TV Show'     => 47,
					'Mini-Series' => 2,
				),
				1990 => array(
					'TV Show'     => 128,
					'Mini-Series' => 10,
					'TV Movie'    => 10,
				),
				2000 => array(
					'TV Show'     => 277,
					'Web Series'  => 15,
					'Mini-Series' => 15,
					'TV Movie'    => 19,
				),
				2010 => array(
					'TV Show'     => 825,
					'Web Series'  => 224,
					'Mini-Series' => 61,
					'TV Movie'    => 12,
				),
				2020 => array(
					'TV Show'     => 455,
					'Mini-Series' => 75,
					'TV Movie'    => 16,
					'Web Series'  => 15,
				),
			),
			20
		);

		$this->assertSame(
			array( 'before', 'decade', 'decade', 'decade', 'decade', 'decade' ),
			array_column( $out, 'type' )
		);
		$this->assertSame( array( null, 1980, 1990, 2000, 2010, 2020 ), array_column( $out, 'from' ) );
		$this->assertSame( array( 42, 56, 148, 326, 1122, 561 ), array_column( $out, 'total' ) );

		// The headline finding: Web Series peaks in the 2010s, then falls
		// back while Mini-Series takes over as the runner-up in the 2020s.
		$by_from = array();
		foreach ( $out as $row ) {
			$by_from[ $row['from'] ?? 'before' ] = $row;
		}
		$this->assertSame( 20.0, $by_from[ 2010 ]['pcts']['Web Series'] );
		$this->assertSame( 2.7, $by_from[ 2020 ]['pcts']['Web Series'] );
		$this->assertSame( 13.4, $by_from[ 2020 ]['pcts']['Mini-Series'] );
		$this->assertSame( 2255, array_sum( array_column( $out, 'total' ) ) );
	}

	public function test_never_clearing_threshold_still_emits_a_bucket(): void {
		$out = Format_Decade_Buckets::build(
			array(
				1962 => array( 'TV Show' => 3 ),
				1974 => array( 'TV Show' => 2 ),
			),
			20
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'before', $out[0]['type'] );
		$this->assertNull( $out[0]['to'] );
		$this->assertSame( 5, $out[0]['total'] );
	}

	public function test_tied_lead_format_keeps_first_seen(): void {
		$out = Format_Decade_Buckets::build(
			array(
				2021 => array(
					'TV Show'    => 10,
					'Web Series' => 10,
				),
			),
			1
		);

		$this->assertSame( 'TV Show', $out[0]['lead_format'] );
	}

	public function test_non_numeric_and_zero_years_are_ignored(): void {
		$out = Format_Decade_Buckets::build(
			array(
				0     => array( 'TV Show' => 5 ),
				-1    => array( 'TV Show' => 5 ),
				2021 => array( 'TV Show' => 5 ),
			),
			1
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 5, $out[0]['total'] );
	}
}
