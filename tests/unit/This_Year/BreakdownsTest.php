<?php
/**
 * Unit tests for the This Year "Where it came from" breakdown transforms:
 * origin (top-N + Other), format counts, and per-relationship role counts.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Breakdowns;

class BreakdownsTest extends TestCase {

	/**
	 * Fake a "grouped by X, then by show name" builder payload: X => number of
	 * shows under it. The transforms only count the shows in each group.
	 *
	 * @param array $groups group label => number of shows
	 * @return array
	 */
	private function grouped( array $groups ): array {
		$out = array();
		foreach ( $groups as $label => $number ) {
			$out[ $label ] = array();
			for ( $i = 0; $i < $number; $i++ ) {
				$out[ $label ][ "show-{$label}-{$i}" ] = array( 'name' => "Show {$i}" );
			}
		}
		return $out;
	}

	// ---- origin(): top-N countries + an aggregated remainder. ----

	public function test_origin_counts_and_sorts_countries_descending(): void {
		$data   = $this->grouped(
			array(
				'UK'  => 34,
				'USA' => 62,
			)
		);
		$result = Breakdowns::origin( $data );

		$this->assertSame(
			array(
				array(
					'name'  => 'USA',
					'count' => 62,
				),
				array(
					'name'  => 'UK',
					'count' => 34,
				),
			),
			$result['top']
		);
		$this->assertSame( 0, $result['other'] );
	}

	public function test_origin_aggregates_the_remainder_into_other(): void {
		$data = $this->grouped(
			array(
				'USA'       => 62,
				'UK'        => 34,
				'Thailand'  => 26,
				'Canada'    => 16,
				'Australia' => 11,
				'France'    => 7,
				'Japan'     => 5,
			)
		);

		$result = Breakdowns::origin( $data, 5 );

		$this->assertCount( 5, $result['top'] );
		$this->assertSame( 'USA', $result['top'][0]['name'] );
		$this->assertSame( 12, $result['other'] ); // 7 + 5
	}

	public function test_origin_empty_input(): void {
		$this->assertSame(
			array(
				'top'   => array(),
				'other' => 0,
			),
			Breakdowns::origin( array() )
		);
	}

	// ---- formats(): a count per format, descending, no aggregation. ----

	public function test_formats_counts_each_format_descending(): void {
		$data   = $this->grouped(
			array(
				'Comedy' => 41,
				'Drama'  => 68,
			)
		);
		$result = Breakdowns::formats( $data );

		$this->assertSame(
			array(
				array(
					'name'  => 'Drama',
					'count' => 68,
				),
				array(
					'name'  => 'Comedy',
					'count' => 41,
				),
			),
			$result
		);
	}

	// ---- roles(): per-relationship tally over regular/recurring/guest. ----

	public function test_roles_tally_every_relationship(): void {
		$characters = array(
			array(
				'shows' => array(
					array( 'type' => 'regular' ),
					array( 'type' => 'guest' ),   // same character, two roles → both count
				),
			),
			array(
				'shows' => array(
					array( 'type' => 'recurring' ),
				),
			),
			array(
				'shows' => array(
					array( 'type' => 'regular' ),
				),
			),
		);

		$this->assertSame(
			array(
				array(
					'key'   => 'regular',
					'count' => 2,
				),
				array(
					'key'   => 'recurring',
					'count' => 1,
				),
				array(
					'key'   => 'guest',
					'count' => 1,
				),
			),
			Breakdowns::roles( $characters )
		);
	}

	public function test_roles_ignore_unknown_or_missing_types(): void {
		$characters = array(
			array(
				'shows' => array(
					array( 'type' => 'cameo' ),  // not a tracked role
					array(),                      // no type key
					array( 'type' => 'guest' ),
				),
			),
		);

		$counts = array_column( Breakdowns::roles( $characters ), 'count', 'key' );

		$this->assertSame( 0, $counts['regular'] );
		$this->assertSame( 1, $counts['guest'] );
	}

	public function test_roles_empty_input_is_all_zero(): void {
		$counts = array_column( Breakdowns::roles( array() ), 'count', 'key' );

		$this->assertSame(
			array(
				'regular'   => 0,
				'recurring' => 0,
				'guest'     => 0,
			),
			$counts
		);
	}
}
