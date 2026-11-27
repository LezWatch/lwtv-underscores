<?php
/**
 * Unit tests for the search modal switch.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Modal_Switch;

class ModalSwitchTest extends TestCase {

	public function test_mode_normalizes(): void {
		$this->assertSame( 'off', Modal_Switch::mode( null ) );
		$this->assertSame( 'off', Modal_Switch::mode( false ) );
		$this->assertSame( 'off', Modal_Switch::mode( '' ) );
		$this->assertSame( 'off', Modal_Switch::mode( 'Editor' ) );   // A typo is off, not on.
		$this->assertSame( 'off', Modal_Switch::mode( true ) );       // Ambiguous is off.
		$this->assertSame( 'editors', Modal_Switch::mode( 'editors' ) );
		$this->assertSame( 'editors', Modal_Switch::mode( ' EDITORS ' ) );
		$this->assertSame( 'everyone', Modal_Switch::mode( 'everyone' ) );
	}

	public function test_unconfigured_is_always_off(): void {
		$this->assertFalse( Modal_Switch::enabled( 'everyone', false, true ) );
	}

	public function test_editors_needs_edit(): void {
		$this->assertTrue( Modal_Switch::enabled( 'editors', true, true ) );
		$this->assertFalse( Modal_Switch::enabled( 'editors', true, false ) );
	}

	public function test_everyone_ignores_edit(): void {
		$this->assertTrue( Modal_Switch::enabled( 'everyone', true, false ) );
	}

	public function test_off_is_off(): void {
		$this->assertFalse( Modal_Switch::enabled( null, true, true ) );
	}
}
