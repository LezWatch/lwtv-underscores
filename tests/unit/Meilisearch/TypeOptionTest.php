<?php
/**
 * Unit tests for the CLI --type option.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Type_Option;

class TypeOptionTest extends TestCase {

	const KNOWN = array( 'characters', 'shows', 'actors' );

	public function test_default_is_characters(): void {
		$this->assertSame( array( 'characters' ), Type_Option::parse( null, self::KNOWN ) );
		$this->assertSame( array( 'characters' ), Type_Option::parse( '', self::KNOWN ) );
	}

	public function test_one_type(): void {
		$this->assertSame( array( 'shows' ), Type_Option::parse( 'shows', self::KNOWN ) );
	}

	public function test_all(): void {
		$this->assertSame( self::KNOWN, Type_Option::parse( 'all', self::KNOWN ) );
	}

	public function test_typo_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'characters, shows, actors, all' );
		Type_Option::parse( 'show', self::KNOWN );
	}
}
