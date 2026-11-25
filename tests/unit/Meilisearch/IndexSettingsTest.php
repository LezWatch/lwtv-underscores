<?php
/**
 * Unit tests for the versioned Meilisearch index settings.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Index_Settings;

class IndexSettingsTest extends TestCase {

	public function test_repo_file_loads(): void {
		$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path() ) );

		$this->assertSame( array( 'name', 'shows', 'actors' ), $settings['searchableAttributes'] );
		$this->assertSame( 'role_rank:desc', end( $settings['rankingRules'] ) );
		$this->assertSame( array( 'from', 'on' ), $settings['stopWords'] );
	}

	public function test_bad_json_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		Index_Settings::load( '{' );
	}

	public function test_missing_searchable_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		Index_Settings::load( '{"rankingRules":[]}' );
	}
}
