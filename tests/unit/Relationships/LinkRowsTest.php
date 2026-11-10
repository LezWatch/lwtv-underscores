<?php
/**
 * Unit tests for turning ACF field values into character link rows.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Relationships;

use PHPUnit\Framework\TestCase;
use LWTV\Relationships\Build\Link_Rows;

class LinkRowsTest extends TestCase {

	/**
	 * Build the expected row shape.
	 *
	 * @param int    $character Character ID.
	 * @param int    $object_id Object ID.
	 * @param string $type      Object type.
	 *
	 * @return array
	 */
	private function row( int $character, int $object_id, string $type ): array {
		return array(
			'character_id' => $character,
			'object_id'    => $object_id,
			'object_type'  => $type,
		);
	}

	public function test_show_and_actor_rows(): void {
		$this->assertSame(
			array(
				$this->row( 10, 5, 'show' ),
				$this->row( 10, 6, 'show' ),
				$this->row( 10, 7, 'actor' ),
				$this->row( 10, 8, 'actor' ),
			),
			Link_Rows::from_fields( 10, array( array( 'show' => 5 ), array( 'show' => '6' ) ), array( '7', '8' ) )
		);
	}

	public function test_legacy_repeater_shapes(): void {
		$this->assertSame(
			array( $this->row( 10, 5, 'show' ) ),
			Link_Rows::from_fields(
				10,
				array( array( 'show' => array( 5 ) ), array( 'show' => array() ), array( 'nope' => 1 ), array( 'show' => '' ) ),
				false
			)
		);
	}

	public function test_duplicates_collapse(): void {
		$this->assertSame(
			array( $this->row( 10, 5, 'show' ), $this->row( 10, 7, 'actor' ) ),
			Link_Rows::from_fields( 10, array( array( 'show' => 5 ), array( 'show' => 5 ) ), array( '7', 7 ) )
		);
	}

	public function test_non_arrays_give_nothing(): void {
		$this->assertSame( array(), Link_Rows::from_fields( 10, false, '' ) );
		$this->assertSame( array(), Link_Rows::from_fields( 10, null, null ) );
	}

	public function test_legacy_characters_both_directions(): void {
		$this->assertSame(
			array( $this->row( 10, 11, 'character' ), $this->row( 12, 10, 'character' ) ),
			Link_Rows::legacy_characters( 10, array( '11', '10', 0, '' ), array( 12, 10 ) )
		);
	}

	public function test_legacy_scalar_chart(): void {
		$this->assertSame(
			array( $this->row( 10, 11, 'character' ) ),
			Link_Rows::legacy_characters( 10, '11', array() )
		);
	}

	public function test_ids_of(): void {
		$rows = array( $this->row( 10, 8, 'actor' ), $this->row( 10, 5, 'show' ), $this->row( 10, 7, 'actor' ), $this->row( 11, 7, 'actor' ) );
		$this->assertSame( array( 7, 8 ), Link_Rows::ids_of( $rows, 'actor' ) );
		$this->assertSame( array(), Link_Rows::ids_of( $rows, 'character' ) );
	}

	public function test_compare(): void {
		$this->assertSame(
			array(
				'missing' => array( 1, 3 ),
				'extra'   => array( 4 ),
			),
			Link_Rows::compare( array( 3, 1, 2 ), array( 2, 4 ) )
		);
	}

	public function test_junk_ids_are_dropped(): void {
		$this->assertSame(
			array( $this->row( 10, 9, 'actor' ) ),
			Link_Rows::from_fields( 10, false, array( 'abc', true, '5abc', -3, '9' ) )
		);
		$this->assertSame( array(), Link_Rows::from_fields( 10, array( array( 'show' => 'abc' ) ), false ) );
	}

	public function test_legacy_keeps_both_directions_for_same_id(): void {
		$this->assertSame(
			array( $this->row( 10, 11, 'character' ), $this->row( 11, 10, 'character' ) ),
			Link_Rows::legacy_characters( 10, array( 11 ), array( 11 ) )
		);
	}

	public function test_compare_normalises_ids(): void {
		$this->assertSame(
			array(
				'missing' => array( 1, 2 ),
				'extra'   => array(),
			),
			Link_Rows::compare( array( '2', '2', 1 ), array() )
		);
	}

	public function test_other_ids_picks_the_far_side_of_each_row(): void {
		// X = 10. Chart lists 30 and 20; holders 20 and 40. 20 appears in both
		// directions and comes out once; the result is sorted.
		$rows = Link_Rows::legacy_characters( 10, array( 30, '20' ), array( 20, 40 ) );

		$this->assertSame( array( 20, 30, 40 ), Link_Rows::other_ids( $rows, 10 ) );
	}

	public function test_other_ids_ignores_other_types_and_rows_without_the_character(): void {
		$rows = array(
			$this->row( 10, 5, 'show' ),
			$this->row( 7, 8, 'character' ),
			$this->row( 10, 10, 'character' ),
			$this->row( 3, 10, 'character' ),
		);

		$this->assertSame( array( 3 ), Link_Rows::other_ids( $rows, 10 ) );
		$this->assertSame( array(), Link_Rows::other_ids( array(), 10 ) );
	}

	public function test_only_drops_rows_for_another_character(): void {
		$rows = array(
			$this->row( 10, 5, 'show' ),
			$this->row( 11, 6, 'show' ),
		);

		$this->assertSame( array( $this->row( 10, 5, 'show' ) ), Link_Rows::only( $rows, 10, array( 'show' ) ) );
	}

	public function test_only_drops_types_outside_the_requested_list(): void {
		$rows = array(
			$this->row( 10, 5, 'show' ),
			$this->row( 10, 7, 'actor' ),
		);

		$this->assertSame( array( $this->row( 10, 7, 'actor' ) ), Link_Rows::only( $rows, 10, array( 'actor' ) ) );
	}

	public function test_only_drops_unknown_types_even_when_requested(): void {
		$rows = array(
			$this->row( 10, 5, 'movie' ),
			$this->row( 10, 7, 'actor' ),
		);

		$this->assertSame( array( $this->row( 10, 7, 'actor' ) ), Link_Rows::only( $rows, 10, array( 'movie', 'actor' ) ) );
	}

	public function test_only_keeps_valid_rows_in_order(): void {
		$rows = array(
			$this->row( 10, 7, 'actor' ),
			$this->row( 10, 5, 'show' ),
			$this->row( 10, 9, 'actor' ),
		);

		$this->assertSame( $rows, Link_Rows::only( $rows, 10, array( 'show', 'actor' ) ) );
	}

	public function test_only_skips_malformed_rows(): void {
		$rows = array( 'junk', array( 'object_type' => 'show' ), $this->row( '10', 5, 'show' ) );

		$this->assertCount( 1, Link_Rows::only( $rows, 10, array( 'show' ) ) );
	}
}
