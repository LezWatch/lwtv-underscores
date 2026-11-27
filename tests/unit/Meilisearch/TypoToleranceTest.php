<?php
/**
 * Unit tests for the per-index typo tolerance pure logic.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Typo_Tolerance;

class TypoToleranceTest extends TestCase {

	private const SEARCHABLE = array( 'name', 'nations' );

	private const BASE = array(
		'enabled'             => true,
		'oneTypo'             => 5,
		'twoTypos'            => 9,
		'disableOnWords'      => array(),
		'disableOnAttributes' => array(),
		'disableOnNumbers'    => false,
	);

	public function test_defaults_match_today(): void {
		$this->assertSame( self::BASE, Typo_Tolerance::defaults( 'characters' ) );
		$this->assertSame( array( 'nations' ), Typo_Tolerance::defaults( 'shows' )['disableOnAttributes'] );
		$this->assertSame( self::BASE, Typo_Tolerance::defaults( 'actors' ) );
		$this->assertSame( self::BASE, Typo_Tolerance::defaults( 'content' ) );
	}

	public function test_validate_accepts_a_full_block(): void {
		$out = Typo_Tolerance::validate(
			'shows',
			array(
				'enabled'             => true,
				'oneTypo'             => 4,
				'twoTypos'            => 7,
				'disableOnWords'      => array( '  Buffy  The ', 'buffy the' ),
				'disableOnAttributes' => array( 'nations' ),
				'disableOnNumbers'    => false,
			),
			self::SEARCHABLE
		);

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( 4, $out['values']['oneTypo'] );
		$this->assertSame( 7, $out['values']['twoTypos'] );
		$this->assertSame( array( 'buffy the' ), $out['values']['disableOnWords'] );
	}

	public function test_validate_fills_missing_fields_from_defaults(): void {
		$out = Typo_Tolerance::validate( 'shows', array( 'oneTypo' => 4 ), self::SEARCHABLE );

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( 9, $out['values']['twoTypos'] );
		$this->assertSame( array( 'nations' ), $out['values']['disableOnAttributes'] );
	}

	public function test_validate_refuses_bad_numbers(): void {
		$cases = array(
			array( array( 'oneTypo' => 0 ), 'oneTypo' ),
			array( array( 'oneTypo' => 9 ), 'oneTypo' ),
			array( array( 'oneTypo' => '5' ), 'oneTypo' ),
			array( array( 'oneTypo' => 5.0 ), 'oneTypo' ),
			array( array( 'oneTypo' => null ), 'oneTypo' ),
			array(
				array(
					'oneTypo'  => 6,
					'twoTypos' => 6,
				),
				'twoTypos',
			),
			array( array( 'twoTypos' => 21 ), 'twoTypos' ),
		);
		foreach ( $cases as list( $input, $key ) ) {
			$out = Typo_Tolerance::validate( 'characters', $input, self::SEARCHABLE );
			$this->assertSame( array( $key ), array_keys( $out['errors'] ), var_export( $input, true ) );
			$this->assertSame( array(), $out['values'], var_export( $input, true ) );
		}
	}

	public function test_validate_refuses_non_booleans(): void {
		$cases = array(
			array( array( 'enabled' => 1 ), 'enabled' ),
			array( array( 'enabled' => 'true' ), 'enabled' ),
			array( array( 'disableOnNumbers' => 0 ), 'disableOnNumbers' ),
		);
		foreach ( $cases as list( $input, $key ) ) {
			$out = Typo_Tolerance::validate( 'characters', $input, self::SEARCHABLE );
			$this->assertSame( array( $key ), array_keys( $out['errors'] ), var_export( $input, true ) );
		}
	}

	public function test_validate_refuses_unsearchable_attribute(): void {
		$out = Typo_Tolerance::validate( 'shows', array( 'disableOnAttributes' => array( 'secret' ) ), self::SEARCHABLE );

		$this->assertSame( array( 'disableOnAttributes' ), array_keys( $out['errors'] ) );
	}

	public function test_validate_refuses_word_limits(): void {
		$many = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$many[] = 'word' . $i;
		}
		$cases = array( $many, array( str_repeat( 'a', 101 ) ), array( 'fine', array() ), 'not a list' );
		foreach ( $cases as $words ) {
			$out = Typo_Tolerance::validate( 'characters', array( 'disableOnWords' => $words ), self::SEARCHABLE );
			$this->assertSame( array( 'disableOnWords' ), array_keys( $out['errors'] ), var_export( $words, true ) );
		}
	}

	public function test_validate_accepts_exactly_the_word_limits(): void {
		$words = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$words[] = 'w' . $i;
		}
		$words[0] = str_repeat( 'a', 100 );

		$out = Typo_Tolerance::validate( 'characters', array( 'disableOnWords' => $words ), self::SEARCHABLE );
		$this->assertSame( array(), $out['errors'] );
		$this->assertCount( 100, $out['values']['disableOnWords'] );
	}

	public function test_validate_refuses_hostile_shapes(): void {
		foreach ( array( 'x', null, array( array() ) ) as $input ) {
			$out = Typo_Tolerance::validate( 'characters', $input, self::SEARCHABLE );
			$this->assertSame( array( 'settings' ), array_keys( $out['errors'] ), var_export( $input, true ) );
			$this->assertSame( array(), $out['values'] );
		}
	}

	public function test_validate_ignores_unknown_keys(): void {
		$out = Typo_Tolerance::validate( 'characters', array( 'foo' => 'bar' ), self::SEARCHABLE );

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( self::BASE, $out['values'] );
	}

	public function test_effective_ignores_corrupt_blocks(): void {
		foreach ( array( 'x', array( 'oneTypo' => 99 ), array( 'enabled' => 'yes' ) ) as $stored ) {
			$out = Typo_Tolerance::effective( 'shows', $stored, self::SEARCHABLE );
			$this->assertSame( Typo_Tolerance::defaults( 'shows' ), $out['values'], var_export( $stored, true ) );
		}
		$this->assertSame( Typo_Tolerance::defaults( 'characters' ), Typo_Tolerance::effective( 'characters', null, self::SEARCHABLE )['values'] );
	}

	public function test_effective_drops_unsearchable_attributes(): void {
		$out = Typo_Tolerance::effective( 'shows', array( 'disableOnAttributes' => array( 'nations', 'gone' ) ), array( 'nations' ) );

		$this->assertSame( array( 'nations' ), $out['values']['disableOnAttributes'] );
		$this->assertSame( array( 'gone' ), $out['removed'] );
	}

	public function test_is_default_compares_sorted_lists(): void {
		$this->assertTrue( Typo_Tolerance::is_default( 'shows', Typo_Tolerance::defaults( 'shows' ) ) );

		$a                   = self::BASE;
		$a['disableOnWords'] = array( 'b', 'a' );
		$b                   = self::BASE;
		$b['disableOnWords'] = array( 'a', 'b' );
		$this->assertFalse( Typo_Tolerance::is_default( 'characters', $a ) );
		$this->assertSame(
			Typo_Tolerance::payload( $a ),
			Typo_Tolerance::payload( $b )
		);

		$changed            = Typo_Tolerance::defaults( 'shows' );
		$changed['oneTypo'] = 4;
		$this->assertFalse( Typo_Tolerance::is_default( 'shows', $changed ) );
	}

	public function test_is_default_ignores_list_order(): void {
		$shows                        = Typo_Tolerance::defaults( 'shows' );
		$shows['disableOnAttributes'] = array( 'nations' );
		$this->assertTrue( Typo_Tolerance::is_default( 'shows', $shows ) );
	}

	public function test_payload_shape(): void {
		$this->assertSame(
			array(
				'enabled'             => true,
				'minWordSizeForTypos' => array(
					'oneTypo'  => 5,
					'twoTypos' => 9,
				),
				'disableOnWords'      => array(),
				'disableOnAttributes' => array( 'nations' ),
				'disableOnNumbers'    => false,
			),
			Typo_Tolerance::payload( Typo_Tolerance::defaults( 'shows' ) )
		);
	}

	public function test_same_ignores_key_and_list_order(): void {
		$values                        = Typo_Tolerance::defaults( 'shows' );
		$values['disableOnWords']      = array( 'alpha', 'beta' );
		$values['disableOnAttributes'] = array( 'name', 'nations' );
		$payload                       = Typo_Tolerance::payload( $values );

		$live = array(
			'disableOnNumbers'    => false,
			'disableOnAttributes' => array( 'nations', 'name' ),
			'disableOnWords'      => array( 'beta', 'alpha' ),
			'minWordSizeForTypos' => array(
				'twoTypos' => 9,
				'oneTypo'  => 5,
			),
			'enabled'             => true,
		);
		$this->assertTrue( Typo_Tolerance::same( $payload, $live ) );
	}

	public function test_same_reports_missing_or_changed_keys(): void {
		$payload = Typo_Tolerance::payload( Typo_Tolerance::defaults( 'characters' ) );

		$missing = $payload;
		unset( $missing['disableOnNumbers'] );
		$this->assertFalse( Typo_Tolerance::same( $payload, $missing ) );

		$changed                                    = $payload;
		$changed['minWordSizeForTypos']['twoTypos'] = 8;
		$this->assertFalse( Typo_Tolerance::same( $payload, $changed ) );

		$this->assertFalse( Typo_Tolerance::same( $payload, null ) );
	}

	public function test_validate_reports_both_lengths_when_both_are_bad(): void {
		$out = Typo_Tolerance::validate(
			'characters',
			array(
				'oneTypo'  => 0,
				'twoTypos' => 0,
			),
			self::SEARCHABLE
		);

		$this->assertSame( array( 'oneTypo', 'twoTypos' ), array_keys( $out['errors'] ) );
	}
}
