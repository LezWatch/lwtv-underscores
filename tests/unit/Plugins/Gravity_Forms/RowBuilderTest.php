<?php
/**
 * Unit tests for the To Add sheet row builder.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Row_Builder;

class RowBuilderTest extends TestCase {

	private function map(): array {
		return array(
			'Status'      => 0,
			'Queerness'   => 1,
			'Name'        => 2,
			'Actor'       => 3,
			'Show'        => 4,
			'Cliches'     => 5,
			'Sexuality'   => 6,
			'Gender'      => 7,
			'Description' => 8,
			'Image'       => 9,
			'Form Entry'  => 10,
		);
	}

	private function character( array $over = array() ): array {
		return array_merge( Row_Builder::fallback_character(), $over );
	}

	public function test_fallback_character_shape(): void {
		$this->assertSame(
			array( 'name', 'actor', 'queerness', 'cliches', 'sexuality', 'gender' ),
			array_keys( Row_Builder::fallback_character() )
		);
		$this->assertSame( '', implode( '', Row_Builder::fallback_character() ) );
	}

	public function test_new_row_layout(): void {
		$row = Row_Builder::new_row( $this->map(), $this->character( array( 'name' => 'Satya' ) ), 'Blue', 'Desc', 4521 );

		$this->assertCount( 11, $row );
		$this->assertSame( 'Needs Verification', $row[0] );
		$this->assertSame( 'Satya', $row[2] );
		$this->assertSame( 'Blue', $row[4] );
		$this->assertSame( '', $row[6] );
		$this->assertSame( '', $row[7] );
		$this->assertSame( 'Desc', $row[8] );
		$this->assertSame( '', $row[9] );
		$this->assertSame( '4521', $row[10] );
	}

	public function test_append_description_and_entry(): void {
		$changes = Row_Builder::append_changes(
			$this->map(),
			array(
				'Description' => 'Old text',
				'Form Entry'  => '',
			),
			$this->character(),
			'New text',
			4602,
			'2026-11-09'
		);

		$this->assertSame( "Old text\n\n— Added 2026-11-09 from form entry #4602 —\nNew text", $changes['Description'] );
		$this->assertSame( '4602', $changes['Form Entry'] );
		$this->assertSame( 'Needs Verification', $changes['Status'] );
	}

	public function test_append_blank_description_has_no_separator(): void {
		$changes = Row_Builder::append_changes( $this->map(), array( 'Description' => "\u{2060} " ), $this->character(), 'New text', 1, '2026-11-09' );
		$this->assertSame( 'New text', $changes['Description'] );
	}

	public function test_append_entry_list(): void {
		$changes = Row_Builder::append_changes( $this->map(), array( 'Form Entry' => '4521' ), $this->character(), 'x', 4602, '2026-11-09' );
		$this->assertSame( '4521, 4602', $changes['Form Entry'] );
	}

	public function test_append_fills_only_blank_cells(): void {
		$changes = Row_Builder::append_changes(
			$this->map(),
			array(
				'Actor'  => 'Jolene Purdy',
				'Gender' => '',
			),
			$this->character(
				array(
					'actor'  => 'Someone',
					'gender' => 'Cisgender',
				)
			),
			'x',
			1,
			'2026-11-09'
		);

		$this->assertSame( 'Cisgender', $changes['Gender'] );
		$this->assertArrayNotHasKey( 'Actor', $changes );
	}

	public function test_append_ignores_invisible_only_cell_as_blank(): void {
		$changes = Row_Builder::append_changes(
			$this->map(),
			array( 'Queerness' => "\u{2060} " ),
			$this->character( array( 'queerness' => 'Lesbian' ) ),
			'x',
			1,
			'2026-11-09'
		);

		$this->assertSame( 'Lesbian', $changes['Queerness'] );
	}

	public function test_append_skips_blank_extracted_value(): void {
		$changes = Row_Builder::append_changes( $this->map(), array(), $this->character(), 'x', 1, '2026-11-09' );
		$this->assertArrayNotHasKey( 'Gender', $changes );
	}

	public function test_append_never_touches_name_show_image(): void {
		$changes = Row_Builder::append_changes(
			$this->map(),
			array(),
			$this->character(
				array(
					'name'      => 'N',
					'actor'     => 'A',
					'queerness' => 'Q',
					'cliches'   => 'C',
					'sexuality' => 'S',
					'gender'    => 'G',
				)
			),
			'x',
			1,
			'2026-11-09'
		);

		$allowed = array( 'Status', 'Description', 'Form Entry', 'Actor', 'Queerness', 'Cliches', 'Sexuality', 'Gender' );
		$this->assertSame( array(), array_diff( array_keys( $changes ), $allowed ) );
		$this->assertCount( 8, $changes );
	}
}
