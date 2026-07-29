<?php
/**
 * Unit tests for the shared This Year builder helpers.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Shared_Builder;

class SharedBuilderTest extends TestCase {

	private Shared_Builder $builder;

	protected function setUp(): void {
		$this->builder = new Shared_Builder();
	}

	// ---- sort_name(): drop a single leading English article. ----

	public function test_sort_name_strips_the(): void {
		$this->assertSame( 'Bear', $this->builder->sort_name( 'The Bear' ) );
	}

	public function test_sort_name_strips_a(): void {
		$this->assertSame( "Good Girl's Guide to Murder", $this->builder->sort_name( "A Good Girl's Guide to Murder" ) );
	}

	public function test_sort_name_strips_an(): void {
		$this->assertSame( 'American Crime Story', $this->builder->sort_name( 'An American Crime Story' ) );
	}

	public function test_sort_name_matches_article_case_insensitively(): void {
		// A lowercase "the" is still stripped; the remainder keeps its own case
		// (the case-insensitive comparison happens later, at strnatcasecmp time).
		$this->assertSame( 'simpsons', $this->builder->sort_name( 'the simpsons' ) );
	}

	public function test_sort_name_leaves_embedded_article_alone(): void {
		// Only a *leading* article is stripped.
		$this->assertSame( 'Theodore Rex', $this->builder->sort_name( 'Theodore Rex' ) );
		$this->assertSame( 'Anne with an E', $this->builder->sort_name( 'Anne with an E' ) );
	}

	public function test_sort_name_requires_whitespace_after_article(): void {
		// "Atlanta" must not lose its "A" — the article needs a following space.
		$this->assertSame( 'Atlanta', $this->builder->sort_name( 'Atlanta' ) );
	}

	public function test_sort_name_trims_first(): void {
		$this->assertSame( 'Wire', $this->builder->sort_name( '  The Wire' ) );
	}

	// ---- sort_name() feeds get_character_marker() to yield the display bucket. ----

	public function test_article_stripped_marker_files_under_real_initial(): void {
		$this->assertSame( 'B', $this->builder->get_character_marker( $this->builder->sort_name( 'The Bear' ) ) );
		$this->assertSame( 'G', $this->builder->get_character_marker( $this->builder->sort_name( "A Good Girl's Guide to Murder" ) ) );
		$this->assertSame( 'A', $this->builder->get_character_marker( $this->builder->sort_name( 'An American Crime Story' ) ) );
	}
}
