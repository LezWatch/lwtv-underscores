<?php
/**
 * Unit tests for the Shows block view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Shows_Block;

class ShowsBlockTest extends TestCase {

	// ---- initial_of(): Latin initial or null. ----

	public function test_initial_of_latin_uppercased(): void {
		$this->assertSame( 'A', Shows_Block::initial_of( 'australia' ) );
		$this->assertSame( 'Z', Shows_Block::initial_of( 'Zambia' ) );
	}

	public function test_initial_of_non_letter_is_null(): void {
		$this->assertNull( Shows_Block::initial_of( '#' ) );
		$this->assertNull( Shows_Block::initial_of( '-' ) );
		$this->assertNull( Shows_Block::initial_of( '9-1-1' ) );
		$this->assertNull( Shows_Block::initial_of( 'รักสุดท้าย' ) );
	}

	public function test_initial_of_trims_whitespace(): void {
		$this->assertSame( 'B', Shows_Block::initial_of( '  belgium' ) );
	}

	// ---- jump_bar('name'): markers first, then A–Z with absent struck. ----

	public function test_jump_bar_name_markers_first_then_az(): void {
		$bar = Shows_Block::jump_bar( array( '#', '-', 'A', 'C' ), 'name' );

		// Two marker chips first, in key order, never struck.
		$this->assertSame( '#', $bar[0]['label'] );
		$this->assertSame( 0, $bar[0]['target'] );
		$this->assertFalse( $bar[0]['struck'] );
		$this->assertSame( '-', $bar[1]['label'] );
		$this->assertSame( 1, $bar[1]['target'] );

		// A–Z follows: A→2, B struck, C→3, D–Z struck.
		$az = array_slice( $bar, 2 );
		$this->assertCount( 26, $az );
		$this->assertSame( array( 'label' => 'A', 'target' => 2, 'struck' => false, 'count' => null ), $az[0] );
		$this->assertSame( array( 'label' => 'B', 'target' => null, 'struck' => true, 'count' => null ), $az[1] );
		$this->assertSame( array( 'label' => 'C', 'target' => 3, 'struck' => false, 'count' => null ), $az[2] );
		$this->assertTrue( $az[3]['struck'] ); // D
	}

	public function test_jump_bar_name_no_markers_when_none_present(): void {
		$bar = Shows_Block::jump_bar( array( 'A', 'B' ), 'name' );
		$this->assertCount( 26, $bar ); // A–Z only, no marker chips.
		$this->assertSame( 'A', $bar[0]['label'] );
	}

	// ---- jump_bar('country'): A–Z → first group of each initial, no markers. ----

	public function test_jump_bar_country_first_initial_wins(): void {
		$bar = Shows_Block::jump_bar( array( 'Australia', 'Austria', 'Belgium' ), 'country' );
		$this->assertCount( 26, $bar ); // A–Z only.
		$this->assertSame( 0, $bar[0]['target'] ); // A → first Australia, not Austria.
		$this->assertTrue( $bar[2]['struck'] );     // C absent.
		$this->assertSame( 2, $bar[1]['target'] );   // B → Belgium.
	}

	// ---- jump_bar('format'): one chip per group in order, with count. ----

	public function test_jump_bar_format_carries_counts_in_order(): void {
		$bar = Shows_Block::jump_bar(
			array( 'TV Show', 'Mini-Series', 'Web Series' ),
			'format',
			array( 'TV Show' => 50, 'Mini-Series' => 7, 'Web Series' => 3 )
		);
		$this->assertSame(
			array(
				array( 'label' => 'TV Show', 'target' => 0, 'struck' => false, 'count' => 50 ),
				array( 'label' => 'Mini-Series', 'target' => 1, 'struck' => false, 'count' => 7 ),
				array( 'label' => 'Web Series', 'target' => 2, 'struck' => false, 'count' => 3 ),
			),
			$bar
		);
	}

	// ---- Edge cases. ----

	public function test_jump_bar_empty_keys(): void {
		$name = Shows_Block::jump_bar( array(), 'name' );
		$this->assertCount( 26, $name );
		$this->assertTrue( $name[0]['struck'] );

		$this->assertSame( array(), Shows_Block::jump_bar( array(), 'format' ) );
	}
}
