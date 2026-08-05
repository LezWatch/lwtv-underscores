<?php
/**
 * Unit tests for the star podium transform: column ordering (physical
 * podium order, gold centre), zero-tier dropping, scaled plate heights
 * with a floor, leader facts, and the silver/bronze relationship.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Star_Podium;

class StarPodiumTest extends TestCase {

	private const CURRENT = array(
		'gold'   => 141,
		'silver' => 41,
		'bronze' => 39,
		'anti'   => 0,
	);

	/*
	 * columns()
	 */

	public function test_columns_podium_order_and_zero_tiers_dropped(): void {
		$out = Star_Podium::columns( self::CURRENT );

		// Physical podium order: silver · gold · bronze. Anti (0) gets no column.
		$this->assertSame( array( 'silver', 'gold', 'bronze' ), array_column( $out, 'tier' ) );
	}

	public function test_columns_heights_scale_to_the_tallest_tier(): void {
		$out = Star_Podium::columns( self::CURRENT );

		$this->assertSame( 58, $out[0]['height'] );  // 41/141 × 200.
		$this->assertSame( 200, $out[1]['height'] ); // Gold is the max.
		$this->assertSame( 55, $out[2]['height'] );  // 39/141 × 200.
	}

	public function test_columns_tallest_tier_takes_max_even_when_not_gold(): void {
		$out = Star_Podium::columns(
			array(
				'gold'   => 100,
				'silver' => 300,
				'bronze' => 50,
			)
		);

		// Order stays silver · gold · bronze; only the heights move.
		$this->assertSame( array( 'silver', 'gold', 'bronze' ), array_column( $out, 'tier' ) );
		$this->assertSame( 200, $out[0]['height'] );
		$this->assertSame( 67, $out[1]['height'] ); // 100/300 × 200.
	}

	public function test_columns_height_floor(): void {
		$out = Star_Podium::columns(
			array(
				'gold'   => 500,
				'bronze' => 2,
			)
		);

		$this->assertSame( array( 'gold', 'bronze' ), array_column( $out, 'tier' ) );
		$this->assertSame( 24, $out[1]['height'] ); // 2/500 × 200 = 0.8 → floor.
	}

	public function test_columns_anti_appends_after_bronze_when_nonzero(): void {
		$out = Star_Podium::columns(
			array(
				'gold'   => 10,
				'silver' => 5,
				'bronze' => 5,
				'anti'   => 1,
			)
		);

		$this->assertSame( array( 'silver', 'gold', 'bronze', 'anti' ), array_column( $out, 'tier' ) );
	}

	public function test_columns_empty_when_no_stars(): void {
		$this->assertSame( array(), Star_Podium::columns( array( 'anti' => 0 ) ) );
		$this->assertSame( array(), Star_Podium::columns( array() ) );
	}

	/*
	 * facts()
	 */

	public function test_facts_current_figures(): void {
		$out = Star_Podium::facts( self::CURRENT, 2255, 221 );

		$this->assertSame( 221, $out['star_sum'] );
		$this->assertSame( 'gold', $out['leader'] );
		$this->assertSame( 141, $out['leader_count'] );
		$this->assertSame( 64, $out['leader_share_pct'] );  // 141/221, rail cards round whole.
		$this->assertSame( 9.8, $out['star_rate_pct'] );    // 221/2255, one decimal.
		$this->assertSame( 2034, $out['none_count'] );
		$this->assertSame( 90.2, $out['none_pct'] );
	}

	public function test_facts_defaults_starred_shows_to_tier_sum(): void {
		$out = Star_Podium::facts( self::CURRENT, 2255 );

		$this->assertSame( 9.8, $out['star_rate_pct'] );
		$this->assertSame( 2034, $out['none_count'] );
	}

	public function test_facts_leader_never_anti(): void {
		$out = Star_Podium::facts(
			array(
				'gold'   => 2,
				'silver' => 1,
				'anti'   => 50,
			),
			100
		);

		// Anti is a demerit: it can dominate the counts, never "lead".
		$this->assertSame( 'gold', $out['leader'] );
		$this->assertSame( 53, $out['star_sum'] );
	}

	public function test_facts_no_stars(): void {
		$out = Star_Podium::facts( array(), 100 );

		$this->assertSame( 0, $out['star_sum'] );
		$this->assertSame( '', $out['leader'] );
		$this->assertSame( 0.0, $out['star_rate_pct'] );
		$this->assertSame( 100, $out['none_count'] );
	}

	public function test_facts_zero_total_shows(): void {
		$out = Star_Podium::facts( self::CURRENT, 0, 221 );

		$this->assertSame( 0.0, $out['star_rate_pct'] );
		$this->assertSame( 0.0, $out['none_pct'] );
		$this->assertSame( 0, $out['none_count'] );
	}

	/*
	 * relationship()
	 */

	public function test_relationship_dead_heat_within_threshold(): void {
		// 41 vs 39: gap 2 ≤ 15% of 41.
		$this->assertSame( 'dead-heat', Star_Podium::relationship( 41, 39 ) );
	}

	public function test_relationship_clear_leads(): void {
		$this->assertSame( 'first-leads', Star_Podium::relationship( 100, 50 ) );
		$this->assertSame( 'second-leads', Star_Podium::relationship( 50, 100 ) );
	}

	public function test_relationship_none_when_either_is_zero(): void {
		$this->assertSame( 'none', Star_Podium::relationship( 41, 0 ) );
		$this->assertSame( 'none', Star_Podium::relationship( 0, 0 ) );
	}

	public function test_relationship_custom_threshold(): void {
		// Gap 20 vs max 100: dead heat only if the threshold allows 20%.
		$this->assertSame( 'first-leads', Star_Podium::relationship( 100, 80 ) );
		$this->assertSame( 'dead-heat', Star_Podium::relationship( 100, 80, 0.2 ) );
	}
}
