<?php
/**
 * Unit tests for the watch-providers refresh tiers: which shows refresh weekly
 * (on air, or ended this year), monthly (ended in an earlier year) or never
 * automatically (no finish date).
 *
 * The year is passed in, so the 31 December → 1 January rollover is testable.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Schedulers;

use PHPUnit\Framework\TestCase;
use LWTV\Schedulers\Watch_Providers_Tiers;

class WatchProvidersTiersTest extends TestCase {

	/*
	 * Which tier a finish value lands in
	 */

	public function test_current_is_on_air(): void {
		$this->assertSame( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::tier( 'current', 2026 ) );
	}

	public function test_current_is_case_and_space_insensitive(): void {
		$this->assertSame( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::tier( ' Current ', 2026 ) );
	}

	public function test_ending_this_year_stays_on_air(): void {
		$this->assertSame( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::tier( '2026', 2026 ) );
	}

	public function test_future_finish_year_is_on_air(): void {
		$this->assertSame( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::tier( '2027', 2026 ) );
	}

	public function test_earlier_finish_year_is_ended(): void {
		$this->assertSame( Watch_Providers_Tiers::ENDED, Watch_Providers_Tiers::tier( '2025', 2026 ) );
		$this->assertSame( Watch_Providers_Tiers::ENDED, Watch_Providers_Tiers::tier( '1961', 2026 ) );
	}

	public function test_year_rollover_drops_to_ended(): void {
		$this->assertSame( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::tier( '2026', 2026 ) );
		$this->assertSame( Watch_Providers_Tiers::ENDED, Watch_Providers_Tiers::tier( '2026', 2027 ) );
	}

	public function test_empty_finish_is_none(): void {
		$this->assertSame( Watch_Providers_Tiers::NONE, Watch_Providers_Tiers::tier( '', 2026 ) );
		$this->assertSame( Watch_Providers_Tiers::NONE, Watch_Providers_Tiers::tier( '   ', 2026 ) );
	}

	public function test_unrecognised_finish_is_none(): void {
		// Must match the SQL in stale_ids(), which only accepts 'current' or four digits.
		$this->assertSame( Watch_Providers_Tiers::NONE, Watch_Providers_Tiers::tier( 'soon', 2026 ) );
		$this->assertSame( Watch_Providers_Tiers::NONE, Watch_Providers_Tiers::tier( '20255', 2026 ) );
		$this->assertSame( Watch_Providers_Tiers::NONE, Watch_Providers_Tiers::tier( '2020-05-01', 2026 ) );
	}

	/*
	 * How long each tier waits
	 */

	public function test_on_air_refreshes_weekly(): void {
		$this->assertSame( 7 * DAY_IN_SECONDS, Watch_Providers_Tiers::ttl_for( 'current', 2026 ) );
	}

	public function test_ended_refreshes_monthly(): void {
		$this->assertSame( 30 * DAY_IN_SECONDS, Watch_Providers_Tiers::ttl_for( '2019', 2026 ) );
	}

	public function test_none_never_refreshes(): void {
		$this->assertSame( 0, Watch_Providers_Tiers::ttl_for( '', 2026 ) );
	}

	public function test_every_tier_has_a_ttl(): void {
		foreach ( array( Watch_Providers_Tiers::ON_AIR, Watch_Providers_Tiers::ENDED, Watch_Providers_Tiers::NONE ) as $tier ) {
			$this->assertArrayHasKey( $tier, Watch_Providers_Tiers::TTLS );
		}
	}
}
