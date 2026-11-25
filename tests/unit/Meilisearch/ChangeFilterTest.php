<?php
/**
 * Unit tests for deciding when a post change must update the index.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Change_Filter;

class ChangeFilterTest extends TestCase {

	public function test_rename_published_show(): void {
		$this->assertTrue( Change_Filter::needs_resync( 'A', 'B', 'publish', 'publish' ) );
	}

	public function test_routine_edit(): void {
		$this->assertFalse( Change_Filter::needs_resync( 'A', 'A', 'publish', 'publish' ) );
	}

	public function test_unpublish(): void {
		$this->assertTrue( Change_Filter::needs_resync( 'A', 'A', 'publish', 'draft' ) );
	}

	public function test_publish_from_draft(): void {
		$this->assertTrue( Change_Filter::needs_resync( 'A', 'A', 'draft', 'publish' ) );
	}

	public function test_rename_to_empty(): void {
		$this->assertFalse( Change_Filter::needs_resync( 'A', '', 'publish', 'publish' ) );
	}

	public function test_draft_rename(): void {
		$this->assertFalse( Change_Filter::needs_resync( 'A', 'B', 'draft', 'draft' ) );
	}

	public function test_auto_draft(): void {
		$this->assertFalse( Change_Filter::needs_resync( '', 'A', 'auto-draft', 'publish' ) );
	}

	public function test_crossing_publish(): void {
		$this->assertTrue( Change_Filter::crosses_publish( 'future', 'publish' ) );
		$this->assertTrue( Change_Filter::crosses_publish( 'publish', 'trash' ) );
		$this->assertTrue( Change_Filter::crosses_publish( 'publish', 'private' ) );
		$this->assertFalse( Change_Filter::crosses_publish( 'publish', 'publish' ) );
		$this->assertFalse( Change_Filter::crosses_publish( 'draft', 'pending' ) );
	}
}
