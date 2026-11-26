<?php
/**
 * Unit tests for the repo-settings drift comparison.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Settings_Drift;

class SettingsDriftTest extends TestCase {

	private const REPO = array(
		'searchableAttributes' => array( 'name', 'shows' ),
		'rankingRules'         => array( 'words', 'typo' ),
		'stopWords'            => array( 'from' ),
	);

	public function test_equal_settings_have_no_drift(): void {
		$live               = self::REPO;
		$live['synonyms']   = array( 'a' => array( 'b' ) );
		$live['pagination'] = array( 'maxTotalHits' => 1000 );

		$this->assertSame( array(), Settings_Drift::differs( self::REPO, $live ) );
	}

	public function test_a_changed_key_is_reported(): void {
		$live                 = self::REPO;
		$live['rankingRules'] = array( 'typo', 'words' );

		$this->assertSame( array( 'rankingRules' ), Settings_Drift::differs( self::REPO, $live ) );
	}

	public function test_a_missing_key_is_reported_in_repo_order(): void {
		$live = self::REPO;
		unset( $live['stopWords'], $live['searchableAttributes'] );

		$this->assertSame( array( 'searchableAttributes', 'stopWords' ), Settings_Drift::differs( self::REPO, $live ) );
	}

	public function test_synonyms_are_skipped(): void {
		$repo             = self::REPO;
		$repo['synonyms'] = array( 'x' => array( 'y' ) );

		$this->assertSame( array(), Settings_Drift::differs( $repo, self::REPO ) );
	}
}
