<?php
/**
 * Unit tests for the airdates resolver: the current ACF keys, the legacy
 * serialized fallback, part-migrated shows that only have one of the two, and
 * the "current" still-airing sentinel.
 *
 * Regression cover for the Shows debugger reading only the legacy
 * lezshows_airdates key, which made every migrated show report "No airdates."
 * while silently skipping the end-date checks entirely.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Scoring\Airdates;

class AirdatesTest extends TestCase {

	/*
	 * resolve() - current ACF keys
	 */

	public function test_resolve_prefers_current_acf_keys(): void {
		$out = Airdates::resolve( '2019', '2021', array( 'start' => '1999', 'finish' => '2001' ) );

		$this->assertSame( '2019', $out['start'] );
		$this->assertSame( '2021', $out['finish'] );
	}

	public function test_resolve_ignores_legacy_when_both_current_keys_present(): void {
		$out = Airdates::resolve( '2019', 'current', array( 'start' => '1999', 'finish' => '2001' ) );

		$this->assertSame( '2019', $out['start'] );
		$this->assertSame( 'current', $out['finish'] );
	}

	/*
	 * resolve() - legacy fallback
	 */

	public function test_resolve_falls_back_to_legacy_array(): void {
		$out = Airdates::resolve( '', '', array( 'start' => '1999', 'finish' => '2001' ) );

		$this->assertSame( '1999', $out['start'] );
		$this->assertSame( '2001', $out['finish'] );
	}

	public function test_resolve_fills_only_the_missing_half(): void {
		// Part-migrated: start moved to the new key, finish never did.
		$out = Airdates::resolve( '2019', '', array( 'start' => '1999', 'finish' => '2001' ) );

		$this->assertSame( '2019', $out['start'], 'The migrated start must win.' );
		$this->assertSame( '2001', $out['finish'], 'The missing finish comes from legacy.' );
	}

	public function test_resolve_handles_legacy_with_partial_members(): void {
		$out = Airdates::resolve( '', '', array( 'start' => '1999' ) );

		$this->assertSame( '1999', $out['start'] );
		$this->assertSame( '', $out['finish'] );
	}

	/*
	 * resolve() - absent / junk input
	 */

	public function test_resolve_returns_empty_strings_when_nothing_is_set(): void {
		$out = Airdates::resolve( '', '', '' );

		$this->assertSame( '', $out['start'] );
		$this->assertSame( '', $out['finish'] );
	}

	public function test_resolve_ignores_non_array_legacy(): void {
		// An unmigrated show can carry a stray scalar here.
		$out = Airdates::resolve( '', '', 'nonsense' );

		$this->assertSame( '', $out['start'] );
		$this->assertSame( '', $out['finish'] );
	}

	public function test_resolve_ignores_array_values_in_the_current_keys(): void {
		$out = Airdates::resolve( array( 'oops' ), null, null );

		$this->assertSame( '', $out['start'] );
		$this->assertSame( '', $out['finish'] );
	}

	public function test_resolve_trims_whitespace(): void {
		$out = Airdates::resolve( ' 2019 ', "2021\n", null );

		$this->assertSame( '2019', $out['start'] );
		$this->assertSame( '2021', $out['finish'] );
	}

	public function test_resolve_casts_integer_meta_to_string(): void {
		$out = Airdates::resolve( 2019, 2021, null );

		$this->assertSame( '2019', $out['start'] );
		$this->assertSame( '2021', $out['finish'] );
	}

	/*
	 * is_still_airing()
	 */

	public function test_is_still_airing_matches_the_sentinel_case_insensitively(): void {
		$this->assertTrue( Airdates::is_still_airing( 'current' ) );
		$this->assertTrue( Airdates::is_still_airing( 'CURRENT' ) );
		$this->assertTrue( Airdates::is_still_airing( ' Current ' ) );
	}

	public function test_is_still_airing_rejects_years_and_empties(): void {
		$this->assertFalse( Airdates::is_still_airing( '2021' ) );
		$this->assertFalse( Airdates::is_still_airing( '' ) );
		$this->assertFalse( Airdates::is_still_airing( 'ongoing' ) );
	}
}
