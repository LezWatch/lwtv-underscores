<?php
/**
 * Unit tests for the Meilisearch connection settings.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Config;

class ConfigTest extends TestCase {

	public function test_configured_when_all_three_present(): void {
		$config = new Config( 'https://s.example', 'ik', 'sk' );

		$this->assertTrue( $config->is_configured() );
		$this->assertSame( array(), $config->missing() );
	}

	public function test_missing_names_constants_not_values(): void {
		$config = new Config( 'https://s.example', '', 'sk' );

		$this->assertFalse( $config->is_configured() );
		$this->assertSame( array( 'LWTV_MEILI_INDEX_KEY' ), $config->missing() );
		foreach ( $config->missing() as $name ) {
			$this->assertStringNotContainsString( 'sk', $name );
		}
	}

	public function test_url_trailing_slash_trimmed(): void {
		$config = new Config( 'https://s.example/', 'ik', 'sk' );

		$this->assertSame( 'https://s.example', $config->url() );
		$this->assertSame( 's.example', $config->host() );
	}

	public function test_no_prefix(): void {
		$config = new Config( 'https://s.example', 'ik', 'sk', '' );

		$this->assertSame( 'characters', $config->index( 'characters' ) );
	}

	public function test_prefix_joined_with_underscore(): void {
		$this->assertSame( 'local_characters', ( new Config( 'https://s.example', 'ik', 'sk', 'local' ) )->index( 'characters' ) );
		$this->assertSame( 'local_characters', ( new Config( 'https://s.example', 'ik', 'sk', 'local_' ) )->index( 'characters' ) );
	}
}
