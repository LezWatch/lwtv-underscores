<?php
/**
 * Unit tests for the search results page's query-string parsing.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Search_Params;

class SearchParamsTest extends TestCase {

	const TYPES = array( 'shows', 'characters', 'actors', 'content' );

	public function test_type_is_trimmed_and_lowercased(): void {
		$this->assertSame( 'characters', Search_Params::type( 'Characters', self::TYPES ) );
		$this->assertSame( 'shows', Search_Params::type( ' shows ', self::TYPES ) );
	}

	public function test_unknown_type_is_null(): void {
		$this->assertNull( Search_Params::type( 'x<script>', self::TYPES ) );
		$this->assertNull( Search_Params::type( 'show', self::TYPES ) );
	}

	public function test_array_type_is_null(): void {
		$this->assertNull( Search_Params::type( array( 'shows' ), self::TYPES ) );
	}

	public function test_missing_type_is_null(): void {
		$this->assertNull( Search_Params::type( null, self::TYPES ) );
		$this->assertNull( Search_Params::type( '', self::TYPES ) );
	}

	public function test_paged_reads_a_number(): void {
		$this->assertSame( 3, Search_Params::paged( '3' ) );
		$this->assertSame( 2, Search_Params::paged( 2 ) );
	}

	public function test_negative_paged_is_one(): void {
		$this->assertSame( 1, Search_Params::paged( '-5' ) );
		$this->assertSame( 1, Search_Params::paged( '0' ) );
	}

	public function test_non_numeric_paged_is_one(): void {
		$this->assertSame( 1, Search_Params::paged( 'abc' ) );
	}

	public function test_null_paged_is_one(): void {
		$this->assertSame( 1, Search_Params::paged( null ) );
	}

	public function test_huge_paged_is_capped(): void {
		$this->assertSame( Search_Params::MAX_PAGE, Search_Params::paged( '99999999999999999999' ) );
		$this->assertSame( Search_Params::MAX_PAGE, Search_Params::paged( '1e300' ) );
		$this->assertSame( Search_Params::MAX_PAGE, Search_Params::paged( PHP_INT_MAX ) );
	}

	public function test_array_paged_is_one(): void {
		$this->assertSame( 1, Search_Params::paged( array( '3' ) ) );
	}
}
