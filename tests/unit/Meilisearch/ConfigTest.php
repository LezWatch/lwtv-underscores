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

	public function test_missing_url_only_when_empty(): void {
		$config = new Config( '', 'ik', 'sk' );

		$this->assertSame( array( 'LWTV_MEILI_URL' ), $config->missing() );
	}

	public function test_required_constants_are_the_keys(): void {
		$this->assertSame( array( 'LWTV_MEILI_INDEX_KEY', 'LWTV_MEILI_SEARCH_KEY' ), Config::REQUIRED );
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

	public function test_search_key_is_public_only_when_it_differs_from_the_index_key(): void {
		$this->assertTrue( ( new Config( 'https://s.example', 'ik', 'sk' ) )->search_key_is_public() );
		// The indexing key pasted in as the search key must never reach the browser.
		$this->assertFalse( ( new Config( 'https://s.example', 'same', 'same' ) )->search_key_is_public() );
		$this->assertFalse( ( new Config( 'https://s.example', ' same ', 'same' ) )->search_key_is_public() );
		$this->assertFalse( ( new Config( 'https://s.example', 'ik', '' ) )->search_key_is_public() );
	}
}
