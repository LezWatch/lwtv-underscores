<?php
/**
 * Unit tests for the statistics CSV formatter: BOM, formula-injection
 * hardening, and RFC 4180 quoting (no proprietary backslash escaping, whose
 * implicit default PHP 8.4+ deprecates).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Format\CSV;

class CsvFormatTest extends TestCase {

	private const BOM = "\xEF\xBB\xBF";

	public function test_builds_header_and_rows_with_bom(): void {
		$out = ( new CSV() )->build( array( array( 'Tara', 3 ) ), array( 'Name', 'Count' ) );

		$this->assertSame( self::BOM . "Name,Count\nTara,3\n", $out );
	}

	public function test_hardens_formula_cells(): void {
		$out = ( new CSV() )->build( array( array( '=SUM(A1)', '@here', '-1', '+1' ) ), array( 'a', 'b', 'c', 'd' ) );

		$this->assertStringContainsString( "'=SUM(A1),'@here,'-1,'+1", $out );
	}

	/**
	 * A backslash before a quote is literal text, so the quote is doubled per
	 * RFC 4180. With PHP's legacy "\\" escape it was left undoubled, which
	 * spreadsheets misread as the end of the cell.
	 */
	public function test_backslash_quote_is_rfc4180_quoted(): void {
		$out = ( new CSV() )->build( array( array( 'a\\"b' ) ), array( 'h' ) );

		$this->assertSame( self::BOM . "h\n\"a\\\"\"b\"\n", $out );
	}
}
