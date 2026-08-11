<?php
/**
 * Unit tests for the genre-by-decade bucketer: decade rollup, folding
 * sparse leading decades together (by distinct show count, not genre-tag
 * count), the never-clears-threshold fallback, and top-N ranking math.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Genre_Decade_Buckets;

class GenreDecadeBucketsTest extends TestCase {

	public function test_empty_input_returns_empty(): void {
		$this->assertSame( array(), Genre_Decade_Buckets::build( array() ) );
	}

	public function test_single_well_populated_decade_stands_alone(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				2021 => array(
					'shows'  => 30,
					'genres' => array(
						'drama'  => array( 'name' => 'Drama', 'count' => 20 ),
						'comedy' => array( 'name' => 'Comedy', 'count' => 15 ),
					),
				),
				2022 => array(
					'shows'  => 25,
					'genres' => array(
						'drama'   => array( 'name' => 'Drama', 'count' => 18 ),
						'mystery' => array( 'name' => 'Mystery', 'count' => 10 ),
					),
				),
			),
			20,
			3
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'decade', $out[0]['type'] );
		$this->assertSame( 2020, $out[0]['from'] );
		$this->assertSame( 2030, $out[0]['to'] );
		$this->assertSame( 55, $out[0]['shows'] );
		$this->assertSame(
			array(
				'drama'   => array( 'name' => 'Drama', 'count' => 38 ),
				'comedy'  => array( 'name' => 'Comedy', 'count' => 15 ),
				'mystery' => array( 'name' => 'Mystery', 'count' => 10 ),
			),
			$out[0]['genres']
		);

		$this->assertSame(
			array(
				array( 'slug' => 'drama', 'name' => 'Drama', 'count' => 38, 'pct' => 69.1 ),
				array( 'slug' => 'comedy', 'name' => 'Comedy', 'count' => 15, 'pct' => 27.3 ),
				array( 'slug' => 'mystery', 'name' => 'Mystery', 'count' => 10, 'pct' => 18.2 ),
			),
			$out[0]['top']
		);
	}

	public function test_sparse_leading_decades_fold_by_distinct_shows(): void {
		// 1950s (1) + 1960s (10) + 1970s (31) = 42 shows, clearing a 20-show
		// floor even though genre tag counts (which overlap per show) differ.
		$out = Genre_Decade_Buckets::build(
			array(
				1955 => array(
					'shows'  => 1,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 1 ) ),
				),
				1965 => array(
					'shows'  => 10,
					'genres' => array(
						'drama'  => array( 'name' => 'Drama', 'count' => 8 ),
						'comedy' => array( 'name' => 'Comedy', 'count' => 3 ),
					),
				),
				1971 => array(
					'shows'  => 31,
					'genres' => array(
						'drama'   => array( 'name' => 'Drama', 'count' => 25 ),
						'mystery' => array( 'name' => 'Mystery', 'count' => 1 ),
						'comedy'  => array( 'name' => 'Comedy', 'count' => 5 ),
					),
				),
				1985 => array(
					'shows'  => 56,
					'genres' => array(
						'drama'   => array( 'name' => 'Drama', 'count' => 40 ),
						'mystery' => array( 'name' => 'Mystery', 'count' => 16 ),
					),
				),
			),
			20,
			3
		);

		$this->assertCount( 2, $out );

		$before = $out[0];
		$this->assertSame( 'before', $before['type'] );
		$this->assertNull( $before['from'] );
		$this->assertSame( 1980, $before['to'] );
		$this->assertSame( 42, $before['shows'] );
		$this->assertSame(
			array(
				'drama'   => array( 'name' => 'Drama', 'count' => 34 ),
				'comedy'  => array( 'name' => 'Comedy', 'count' => 8 ),
				'mystery' => array( 'name' => 'Mystery', 'count' => 1 ),
			),
			$before['genres']
		);
		$this->assertSame(
			array(
				array( 'slug' => 'drama', 'name' => 'Drama', 'count' => 34, 'pct' => 81.0 ),
				array( 'slug' => 'comedy', 'name' => 'Comedy', 'count' => 8, 'pct' => 19.0 ),
				array( 'slug' => 'mystery', 'name' => 'Mystery', 'count' => 1, 'pct' => 2.4 ),
			),
			$before['top']
		);

		$decade_80s = $out[1];
		$this->assertSame( 'decade', $decade_80s['type'] );
		$this->assertSame( 1980, $decade_80s['from'] );
		$this->assertSame( 1990, $decade_80s['to'] );
		$this->assertSame( 56, $decade_80s['shows'] );
		$this->assertSame(
			array(
				array( 'slug' => 'drama', 'name' => 'Drama', 'count' => 40, 'pct' => 71.4 ),
				array( 'slug' => 'mystery', 'name' => 'Mystery', 'count' => 16, 'pct' => 28.6 ),
			),
			$decade_80s['top']
		);
	}

	public function test_top_n_override_trims_the_list(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				2021 => array(
					'shows'  => 50,
					'genres' => array(
						'drama'   => array( 'name' => 'Drama', 'count' => 30 ),
						'comedy'  => array( 'name' => 'Comedy', 'count' => 20 ),
						'mystery' => array( 'name' => 'Mystery', 'count' => 10 ),
					),
				),
			),
			20,
			2
		);

		$this->assertCount( 2, $out[0]['top'] );
		$this->assertSame(
			array(
				array( 'slug' => 'drama', 'name' => 'Drama', 'count' => 30, 'pct' => 60.0 ),
				array( 'slug' => 'comedy', 'name' => 'Comedy', 'count' => 20, 'pct' => 40.0 ),
			),
			$out[0]['top']
		);
	}

	public function test_percentages_do_not_need_to_sum_to_100(): void {
		// The whole point of tracking 'shows' separately from 'genres': a
		// multi-genre catalogue can have every top-N pct add up to well
		// over 100 without that being a bug.
		$out    = Genre_Decade_Buckets::build(
			array(
				2021 => array(
					'shows'  => 50,
					'genres' => array(
						'drama'   => array( 'name' => 'Drama', 'count' => 30 ),
						'comedy'  => array( 'name' => 'Comedy', 'count' => 20 ),
						'mystery' => array( 'name' => 'Mystery', 'count' => 10 ),
					),
				),
			),
			20,
			3
		);
		$pctSum = array_sum( array_column( $out[0]['top'], 'pct' ) );

		$this->assertGreaterThan( 100.0, $pctSum );
	}

	public function test_never_clearing_threshold_still_emits_a_bucket(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				1962 => array(
					'shows'  => 3,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 3 ) ),
				),
				1974 => array(
					'shows'  => 2,
					'genres' => array(
						'drama'  => array( 'name' => 'Drama', 'count' => 1 ),
						'comedy' => array( 'name' => 'Comedy', 'count' => 1 ),
					),
				),
			),
			20,
			3
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'before', $out[0]['type'] );
		$this->assertNull( $out[0]['to'] );
		$this->assertSame( 5, $out[0]['shows'] );
		$this->assertSame(
			array(
				array( 'slug' => 'drama', 'name' => 'Drama', 'count' => 4, 'pct' => 80.0 ),
				array( 'slug' => 'comedy', 'name' => 'Comedy', 'count' => 1, 'pct' => 20.0 ),
			),
			$out[0]['top']
		);
	}

	public function test_tied_genres_keep_first_seen_order(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				2021 => array(
					'shows'  => 20,
					'genres' => array(
						'drama'  => array( 'name' => 'Drama', 'count' => 10 ),
						'comedy' => array( 'name' => 'Comedy', 'count' => 10 ),
					),
				),
			),
			1,
			3
		);

		$this->assertSame( 'drama', $out[0]['top'][0]['slug'] );
		$this->assertSame( 'comedy', $out[0]['top'][1]['slug'] );
	}

	public function test_zero_shows_guards_against_division_by_zero(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				2021 => array(
					'shows'  => 0,
					'genres' => array(),
				),
			),
			1,
			3
		);

		$this->assertSame( 0, $out[0]['shows'] );
		$this->assertSame( array(), $out[0]['top'] );
	}

	public function test_non_numeric_and_zero_years_are_ignored(): void {
		$out = Genre_Decade_Buckets::build(
			array(
				0     => array(
					'shows'  => 5,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 5 ) ),
				),
				-1    => array(
					'shows'  => 5,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 5 ) ),
				),
				2021 => array(
					'shows'  => 5,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 5 ) ),
				),
			),
			1,
			3
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 5, $out[0]['shows'] );
	}

	public function test_repeated_slug_across_years_keeps_first_seen_name(): void {
		// A genre's display name is invariant per slug within one build()
		// call — later sightings should only add to the count, never
		// clobber the name with something unexpected.
		$out = Genre_Decade_Buckets::build(
			array(
				2020 => array(
					'shows'  => 10,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 10 ) ),
				),
				2021 => array(
					'shows'  => 10,
					'genres' => array( 'drama' => array( 'name' => 'Drama', 'count' => 10 ) ),
				),
			),
			1,
			3
		);

		$this->assertSame( 'Drama', $out[0]['top'][0]['name'] );
		$this->assertSame( 20, $out[0]['top'][0]['count'] );
	}
}
