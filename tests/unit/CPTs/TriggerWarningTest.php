<?php
/**
 * Tests for Trigger_Warning's legacy-alias normalization.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Scoring\Trigger_Warning;
use PHPUnit\Framework\TestCase;

final class TriggerWarningTest extends TestCase {

	public function test_canonical_slugs_pass_through(): void {
		$this->assertSame( 'high', Trigger_Warning::normalize( 'high' ) );
		$this->assertSame( 'med', Trigger_Warning::normalize( 'med' ) );
		$this->assertSame( 'low', Trigger_Warning::normalize( 'low' ) );
	}

	public function test_legacy_aliases_map_to_their_canonical_slug(): void {
		$this->assertSame( 'high', Trigger_Warning::normalize( 'on' ) );
		$this->assertSame( 'med', Trigger_Warning::normalize( 'medium' ) );
	}

	public function test_unrecognized_or_empty_slug_is_none(): void {
		$this->assertSame( 'none', Trigger_Warning::normalize( '' ) );
		$this->assertSame( 'none', Trigger_Warning::normalize( 'nope' ) );
	}

	public function test_matching_is_exact_case_and_whitespace_sensitive(): void {
		$this->assertSame( 'none', Trigger_Warning::normalize( 'HIGH' ) );
		$this->assertSame( 'none', Trigger_Warning::normalize( 'On' ) );
		$this->assertSame( 'none', Trigger_Warning::normalize( ' high ' ) );
	}
}
