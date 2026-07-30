<?php
/**
 * Unit tests for the pure debounce-timing helper behind the statistics
 * cache-warming schedule. See
 * docs/superpowers/specs/2026-07-30-stats-cache-warming-design.md.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Components;

use PHPUnit\Framework\TestCase;
use LWTV\_Components\Transients;

class WarmScheduleTest extends TestCase {

	// No burst open (deadline 0): open one and fire $delay from now.
	public function test_no_active_burst_opens_deadline_and_targets_now_plus_delay(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] ); // 1000 + max(600)
		$this->assertSame( 1120, $result['target'] );   // min(1000+120, 1600)
	}

	// Burst open with room: push target forward, keep the same deadline.
	public function test_active_burst_with_room_pushes_target_keeps_deadline(): void {
		$result = Transients::next_stats_warm_time( 1200, 1600, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1320, $result['target'] );   // min(1200+120, 1600)
	}

	// Burst open near the cap: clamp the target to the deadline.
	public function test_active_burst_near_cap_clamps_target_to_deadline(): void {
		$result = Transients::next_stats_warm_time( 1550, 1600, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1600, $result['target'] );   // min(1550+120=1670, 1600)
	}

	// delay == max, fresh burst: target lands exactly on the deadline.
	public function test_delay_equal_to_max_targets_deadline(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 600, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1600, $result['target'] );
	}

	// Zero delay: warm as soon as possible within the burst window.
	public function test_zero_delay_targets_now(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 0, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1000, $result['target'] );
	}
}
