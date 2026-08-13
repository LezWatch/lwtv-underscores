<?php
/**
 * Unit tests for the role podium transform: bucket ordering, leader facts,
 * per-type shares, and zero/empty edge cases.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Role_Podium;

class RolePodiumTest extends TestCase {

	private const CURRENT = array(
		'regular'   => 620,
		'recurring' => 310,
		'guest'     => 70,
	);

	public function test_facts_current_figures(): void {
		$out = Role_Podium::facts( self::CURRENT );

		$this->assertSame( 1000, $out['sum'] );
		$this->assertSame( 'regular', $out['leader'] );
		$this->assertSame( 620, $out['leader_count'] );
		$this->assertSame( 62, $out['leader_share_pct'] );
		$this->assertSame( 62.0, $out['levels']['regular']['share'] );
		$this->assertSame( 31.0, $out['levels']['recurring']['share'] );
		$this->assertSame( 7.0, $out['levels']['guest']['share'] );
	}

	public function test_facts_levels_present_even_when_zero(): void {
		$out = Role_Podium::facts( array( 'regular' => 5 ) );

		$this->assertSame( array( 'regular', 'recurring', 'guest' ), array_keys( $out['levels'] ) );
		$this->assertSame( 0, $out['levels']['recurring']['count'] );
		$this->assertSame( 0.0, $out['levels']['recurring']['share'] );
	}

	public function test_facts_leader_ties_go_to_the_first_in_order(): void {
		$out = Role_Podium::facts(
			array(
				'regular'   => 50,
				'recurring' => 50,
				'guest'     => 10,
			)
		);

		// Regular is checked first in ORDER, so an exact tie favors it.
		$this->assertSame( 'regular', $out['leader'] );
		$this->assertSame( 50, $out['leader_count'] );
	}

	public function test_facts_empty_counts(): void {
		$out = Role_Podium::facts( array() );

		$this->assertSame( 0, $out['sum'] );
		$this->assertSame( '', $out['leader'] );
		$this->assertSame( 0, $out['leader_count'] );
		$this->assertSame( 0, $out['leader_share_pct'] );
		$this->assertSame( 0.0, $out['levels']['guest']['share'] );
	}

	public function test_facts_ignores_negative_counts(): void {
		$out = Role_Podium::facts(
			array(
				'regular'   => 10,
				'recurring' => -5,
				'guest'     => 0,
			)
		);

		$this->assertSame( 10, $out['sum'] );
		$this->assertSame( 0, $out['levels']['recurring']['count'] );
	}
}
