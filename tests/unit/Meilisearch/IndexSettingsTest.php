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

		// Nations last, so a name match always outranks "made in" (kdrama → south korea).
		$this->assertSame( array( 'name', 'qualifier', 'alt_names', 'characters', 'actors', 'years', 'nations' ), $settings['searchableAttributes'] );
		// No typos on nations: "canaan" must not find every Canadian show. The
		// full object, as the server returns it, so `status` doesn't see drift.
		$this->assertSame(
			array(
				'enabled'             => true,
				'minWordSizeForTypos' => array(
					'oneTypo'  => 5,
					'twoTypos' => 9,
				),
				'disableOnWords'      => array(),
				'disableOnAttributes' => array( 'nations' ),
				'disableOnNumbers'    => false,
			),
			$settings['typoTolerance']
		);
		// Same-name shows tie on name, then Worth It decides, then score.
		$this->assertSame( array( 'words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness', 'worth_it_rank:desc', 'score:desc' ), $settings['rankingRules'] );
		$this->assertSame( array( 'id', 'worth_it', 'stations', 'nations' ), $settings['filterableAttributes'] );
		$this->assertSame( array( 'from', 'on', 'with' ), $settings['stopWords'] );
	}

	public function test_actors_file_loads(): void {
		$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path( 'actors' ) ) );

		$this->assertSame( array( 'name', 'other_names', 'characters', 'shows', 'years', 'bio' ), $settings['searchableAttributes'] );
		// Other names are matched, never returned: the search key is public.
		$this->assertSame( array( 'id', 'name', 'characters', 'shows', 'years', 'bio', 'queer', 'character_count', 'url', 'thumb' ), $settings['displayedAttributes'] );
		$this->assertNotContains( 'other_names', $settings['displayedAttributes'] );
		// Same-name actors: queer actors first, then more characters (Mika, 2026-11-25).
		$this->assertSame( array( 'words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness', 'queer:desc', 'character_count:desc' ), $settings['rankingRules'] );
		$this->assertSame( array( 'id', 'queer' ), $settings['filterableAttributes'] );
		$this->assertSame( array( 'from', 'in', 'on' ), $settings['stopWords'] );
	}

	public function test_content_file_loads(): void {
		$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path( 'content' ) ) );

		// Title, then tags (mostly show names), excerpt, categories, body (Mika, 2026-11-25).
		$this->assertSame( array( 'title', 'tags', 'excerpt', 'categories', 'body' ), $settings['searchableAttributes'] );
		// The full body is searchable but never returned: results show the excerpt.
		$this->assertNotContains( 'body', $settings['displayedAttributes'] );
		$this->assertSame( array( 'id', 'type', 'title', 'excerpt', 'categories', 'tags', 'date', 'url', 'thumb' ), $settings['displayedAttributes'] );
		// Ties: pages first, then newest.
		$this->assertSame( array( 'words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness', 'type_rank:desc', 'date:desc' ), $settings['rankingRules'] );
		$this->assertSame( array( 'id', 'type', 'categories' ), $settings['filterableAttributes'] );
		$this->assertSame( array( 'date' ), $settings['sortableAttributes'] );
	}

	public function test_displayed_cuts_to_displayed_attributes(): void {
		$settings = array( 'displayedAttributes' => array( 'id', 'name' ) );
		$docs     = array(
			array(
				'id'          => 1,
				'name'        => 'A',
				'other_names' => array( 'B' ),
			),
		);

		$this->assertSame(
			array(
				array(
					'id'   => 1,
					'name' => 'A',
				),
			),
			Index_Settings::displayed( $settings, $docs )
		);
	}

	public function test_displayed_passes_through_without_a_list(): void {
		$docs = array(
			array(
				'id'   => 1,
				'name' => 'A',
			),
		);

		$this->assertSame( $docs, Index_Settings::displayed( array(), $docs ) );
		$this->assertSame( $docs, Index_Settings::displayed( array( 'displayedAttributes' => array( '*' ) ), $docs ) );
	}

	public function test_displayed_actors_file_hides_other_names(): void {
		$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path( 'actors' ) ) );
		$docs     = Index_Settings::displayed(
			$settings,
			array(
				array(
					'id'          => 1,
					'name'        => 'A',
					'other_names' => array( 'B' ),
					'bio'         => '',
				),
			)
		);

		$this->assertArrayNotHasKey( 'other_names', $docs[0] );
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
