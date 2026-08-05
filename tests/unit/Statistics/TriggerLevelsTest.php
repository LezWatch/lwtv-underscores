<?php
/**
 * Unit tests for the trigger-levels transform: flagged/none splits, the
 * true-scale and magnified shares, the derived rail figures (scarcity,
 * weight, floor), and the adaptive low/high balance phrasing data.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Trigger_Levels;

class TriggerLevelsTest extends TestCase {

	private const CURRENT = array(
		'low'    => 88,
		'medium' => 52,
		'high'   => 49,
	);

	/*
	 * facts()
	 */

	public function test_facts_current_figures(): void {
		$out = Trigger_Levels::facts( self::CURRENT, 2255 );

		$this->assertSame( 189, $out['flagged'] );
		$this->assertSame( 2066, $out['none'] );
		$this->assertSame( 91.6, $out['none_pct'] );
		$this->assertSame( 8.4, $out['flagged_pct'] );
		$this->assertSame( 12, $out['scarcity_ratio'] );  // round(2255/189).
		$this->assertSame( 101, $out['heavy'] );          // medium + high.
		$this->assertSame( 53.4, $out['heavy_pct'] );     // share of flagged.
		$this->assertSame( 46, $out['floor_ratio'] );     // round(2255/49).
	}

	public function test_facts_per_level_shares(): void {
		$out = Trigger_Levels::facts( self::CURRENT, 2255 );

		$this->assertSame( 3.9, $out['levels']['low']['share_total'] );
		$this->assertSame( 2.3, $out['levels']['medium']['share_total'] );
		$this->assertSame( 2.2, $out['levels']['high']['share_total'] );

		$this->assertSame( 46.6, $out['levels']['low']['share_flagged'] );
		$this->assertSame( 27.5, $out['levels']['medium']['share_flagged'] );
		$this->assertSame( 25.9, $out['levels']['high']['share_flagged'] );
	}

	public function test_facts_zero_high_drops_floor_ratio(): void {
		$out = Trigger_Levels::facts(
			array(
				'low'    => 10,
				'medium' => 5,
				'high'   => 0,
			),
			100
		);

		$this->assertSame( 0, $out['floor_ratio'] );
		$this->assertSame( 5, $out['heavy'] );
	}

	public function test_facts_nothing_flagged(): void {
		$out = Trigger_Levels::facts( array(), 100 );

		$this->assertSame( 0, $out['flagged'] );
		$this->assertSame( 100, $out['none'] );
		$this->assertSame( 0, $out['scarcity_ratio'] );
		$this->assertSame( 0.0, $out['heavy_pct'] );
	}

	public function test_facts_zero_total(): void {
		$out = Trigger_Levels::facts( self::CURRENT, 0 );

		$this->assertSame( 0.0, $out['none_pct'] );
		$this->assertSame( 0, $out['none'] );
		$this->assertSame( 0, $out['scarcity_ratio'] );
	}

	/*
	 * balance()
	 */

	public function test_balance_low_leads_rounded_up_reads_nearly(): void {
		$out = Trigger_Levels::balance( 88, 49 );

		// 88/49 = 1.8 → "nearly 2 to 1".
		$this->assertSame( 'low-leads', $out['mode'] );
		$this->assertSame( 2, $out['ratio'] );
		$this->assertSame( 'nearly', $out['qualifier'] );
	}

	public function test_balance_rounded_down_reads_more_than(): void {
		$out = Trigger_Levels::balance( 90, 40 );

		// 90/40 = 2.25 → "more than 2 to 1".
		$this->assertSame( 'low-leads', $out['mode'] );
		$this->assertSame( 2, $out['ratio'] );
		$this->assertSame( 'more-than', $out['qualifier'] );
	}

	public function test_balance_exact_ratio(): void {
		$out = Trigger_Levels::balance( 80, 40 );

		$this->assertSame( 2, $out['ratio'] );
		$this->assertSame( 'exactly', $out['qualifier'] );
	}

	public function test_balance_high_leads_flips_mode(): void {
		$out = Trigger_Levels::balance( 40, 90 );

		$this->assertSame( 'high-leads', $out['mode'] );
		$this->assertSame( 2, $out['ratio'] );
	}

	public function test_balance_near_even_counts(): void {
		// Within 15% of each other: a ratio would overstate the gap.
		$this->assertSame( 'even', Trigger_Levels::balance( 50, 48 )['mode'] );
		$this->assertSame( 'even', Trigger_Levels::balance( 50, 50 )['mode'] );
	}

	public function test_balance_zero_either_side_is_none(): void {
		$this->assertSame( 'none', Trigger_Levels::balance( 10, 0 )['mode'] );
		$this->assertSame( 'none', Trigger_Levels::balance( 0, 0 )['mode'] );
	}
}
