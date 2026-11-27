<?php
/**
 * Unit tests for the filter that prunes documents no longer published.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Prune_Filter;

class PruneFilterTest extends TestCase {

	public function test_not_in_filter(): void {
		$this->assertSame( 'id NOT IN [3, 10, 241]', Prune_Filter::not_in( array( 241, '3', 10 ) ) );
	}

	public function test_duplicates_and_junk_dropped(): void {
		$this->assertSame( 'id NOT IN [5]', Prune_Filter::not_in( array( 5, '5', 0, -2, 'abc', null ) ) );
	}

	public function test_empty_set_never_prunes(): void {
		// An empty published set would mean "delete everything": refuse.
		$this->assertNull( Prune_Filter::not_in( array() ) );
		$this->assertNull( Prune_Filter::not_in( array( 0, 'x' ) ) );
	}
}
