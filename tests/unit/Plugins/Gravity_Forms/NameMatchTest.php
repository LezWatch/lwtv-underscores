<?php
/**
 * Unit tests for the Gravity Forms submission name-matching rules.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Name_Match;

class NameMatchTest extends TestCase {

	public function test_normalize_strips_invisible_and_case(): void {
		$this->assertSame( 'canonical', Name_Match::normalize( "Canonical\u{2060} " ) );
		$this->assertSame( 'te brant', Name_Match::normalize( '  Tê  Brant ' ) );
		$this->assertSame( 'unreviewed', Name_Match::normalize( 'Unreviewed ' ) );
		$this->assertSame( 'a b', Name_Match::normalize( "A\u{00A0}B" ) );
	}

	public function test_placeholders(): void {
		$this->assertTrue( Name_Match::is_placeholder( 'Multiple' ) );
		$this->assertTrue( Name_Match::is_placeholder( 'N/A' ) );
		$this->assertTrue( Name_Match::is_placeholder( ' unknown ' ) );
		$this->assertTrue( Name_Match::is_placeholder( '' ) );
		$this->assertFalse( Name_Match::is_placeholder( 'Satya' ) );
	}

	public function test_names_match_both_directions(): void {
		$this->assertTrue( Name_Match::names_match( 'Satya', 'Satya Patel' ) );
		$this->assertTrue( Name_Match::names_match( 'Satya Patel', 'Satya' ) );
		$this->assertTrue( Name_Match::names_match( 'satya', 'SATYA' ) );
		$this->assertFalse( Name_Match::names_match( 'Sam', 'Samantha' ) );
		$this->assertFalse( Name_Match::names_match( 'Satya Patel', 'Satya Kumar' ) );
		$this->assertFalse( Name_Match::names_match( 'Multiple', 'Multiple' ) );
		$this->assertTrue( Name_Match::names_match( 'Tê Brant', 'Te Brant' ) );
	}

	public function test_titles_match(): void {
		$this->assertTrue( Name_Match::titles_match( 'The Wilds', 'Wilds' ) );
		$this->assertTrue( Name_Match::titles_match( 'Kiya & the Kimoja Heroes', 'kiya & the kimoja heroes' ) );
		$this->assertTrue( Name_Match::titles_match( "A '90s Christmas", 'A 90s Christmas' ) );
		$this->assertFalse( Name_Match::titles_match( 'Blue', 'Blue Eye Samurai' ) );
		$this->assertTrue( Name_Match::titles_match( 'Spider-Man', 'Spider Man' ) );
		$this->assertTrue( Name_Match::titles_match( "Don't Look Deeper", 'Dont Look Deeper' ) );
		$this->assertTrue( Name_Match::titles_match( "Don\u{2019}t Look Deeper", 'Dont Look Deeper' ) );
	}

	public function test_titles_match_rejects_empty_keys(): void {
		$this->assertFalse( Name_Match::titles_match( '', '' ) );
		$this->assertFalse( Name_Match::titles_match( '!!!', '???' ) );
	}

	public function test_text_mentions(): void {
		$this->assertTrue( Name_Match::text_mentions( "Julie - was mentioned\u{2026} asks Didi out later", 'Didi' ) );
		$this->assertFalse( Name_Match::text_mentions( 'Diditamba is here', 'Didi' ) );
		$this->assertFalse( Name_Match::text_mentions( 'anything', 'Multiple' ) );
	}

	public function test_exact_value_returns_stored_string(): void {
		$this->assertSame( "Canonical\u{2060} ", Name_Match::exact_value( 'canonical', array( "Canonical\u{2060} ", 'Debunked' ) ) );
		$this->assertSame( '', Name_Match::exact_value( 'Cisgender Female', array( 'Cisgender', 'Trans Woman' ) ) );
	}
}
