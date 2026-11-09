<?php
/**
 * Unit tests for Google Sheets value-array parsing.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Sheet_Table;

class SheetTableTest extends TestCase {

	private const HEADER = array( 'Status', 'Queerness', 'Name', 'Actor', 'Show', 'Cliches', 'Sexuality', 'Gender', 'Description', 'Image', 'Form Entry' );

	public function test_header_map_finds_columns_by_text(): void {
		$map = Sheet_Table::header_map( self::HEADER, array( 'Name', 'Show', 'Form Entry' ) );
		$this->assertSame(
			array(
				'Name'       => 2,
				'Show'       => 4,
				'Form Entry' => 10,
			),
			$map
		);
	}

	public function test_header_map_throws_listing_missing(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Form Entry' );
		Sheet_Table::header_map( array_slice( self::HEADER, 0, 10 ), array( 'Name', 'Form Entry' ) );
	}

	public function test_rows_pads_short_rows(): void {
		$map  = Sheet_Table::header_map( self::HEADER, array( 'Status', 'Name', 'Show', 'Form Entry' ) );
		$rows = Sheet_Table::rows( array( self::HEADER, array( 'Unreviewed', '', 'Mandella', '', '10 Things' ) ), $map );
		$this->assertCount( 1, $rows );
		$this->assertSame( 2, $rows[0]['row'] );
		$this->assertSame( '', $rows[0]['cells']['Form Entry'] );
		$this->assertSame( '10 Things', $rows[0]['cells']['Show'] );
	}

	public function test_rows_skips_blank_rows_but_keeps_numbering(): void {
		$map  = Sheet_Table::header_map( self::HEADER, array( 'Name', 'Show' ) );
		$rows = Sheet_Table::rows(
			array( self::HEADER, array( '', '', 'A', '', 'S1' ), array( '', '', "\u{2060} ", '', ' ' ), array( '', '', 'B', '', 'S2' ) ),
			$map
		);
		$this->assertCount( 2, $rows );
		$this->assertSame( 2, $rows[0]['row'] );
		$this->assertSame( 4, $rows[1]['row'] );
	}

	public function test_column_values_dedupes_normalized(): void {
		$rows = array();
		foreach ( array( "Canonical\u{2060} ", 'Canonical', '', 'Debunked' ) as $v ) {
			$rows[] = array(
				'row'   => 2,
				'cells' => array( 'Cliches' => $v ),
			);
		}
		$this->assertSame( array( "Canonical\u{2060} ", 'Debunked' ), Sheet_Table::column_values( $rows, 'Cliches' ) );
	}

	public function test_column_letter(): void {
		$this->assertSame( 'A', Sheet_Table::column_letter( 0 ) );
		$this->assertSame( 'K', Sheet_Table::column_letter( 10 ) );
		$this->assertSame( 'Z', Sheet_Table::column_letter( 25 ) );
		$this->assertSame( 'AA', Sheet_Table::column_letter( 26 ) );
	}

	public function test_range_row_reads_the_first_row_of_an_append_result(): void {
		$this->assertSame( 1084, Sheet_Table::range_row( "'To Add'!A1084:K1084" ) );
		$this->assertSame( 7, Sheet_Table::range_row( "'Bang!Tab'''!A7" ) );
		$this->assertSame( 12, Sheet_Table::range_row( 'Lists!$A$12:$B$13' ) );
	}

	public function test_range_row_is_zero_without_a_row(): void {
		$this->assertSame( 0, Sheet_Table::range_row( "'To Add'!A:K" ) );
		$this->assertSame( 0, Sheet_Table::range_row( '' ) );
		$this->assertSame( 0, Sheet_Table::range_row( 'nonsense' ) );
	}
}
