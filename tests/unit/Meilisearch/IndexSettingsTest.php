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
		// reindex prunes with an `id NOT IN [...]` filter.
		$this->assertContains( 'id', $settings['filterableAttributes'] );
	}

	public function test_shows_file_loads(): void {
		$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path( 'shows' ) ) );

		$this->assertSame( array( 'name', 'qualifier', 'alt_names', 'characters', 'actors', 'years' ), $settings['searchableAttributes'] );
		// Same-name shows tie on name, then Worth It decides, then score.
		$this->assertSame( array( 'words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness', 'worth_it_rank:desc', 'score:desc' ), $settings['rankingRules'] );
		$this->assertSame( array( 'id', 'worth_it', 'stations', 'nations' ), $settings['filterableAttributes'] );
		$this->assertSame( array( 'from', 'on', 'with' ), $settings['stopWords'] );
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
