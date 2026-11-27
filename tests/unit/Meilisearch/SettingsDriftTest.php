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

	public function test_typo_drift_is_last_and_order_insensitive(): void {
		$values                        = \LWTV\Meilisearch\Build\Typo_Tolerance::defaults( 'shows' );
		$values['disableOnWords']      = array( 'alpha', 'beta' );
		$values['disableOnAttributes'] = array( 'name', 'nations' );
		$typo                          = \LWTV\Meilisearch\Build\Typo_Tolerance::payload( $values );

		$repo = array( 'stopWords' => array( 'on' ) );
		$live = array(
			'stopWords'     => array( 'on' ),
			'typoTolerance' => array(
				'disableOnNumbers'    => false,
				'disableOnAttributes' => array( 'nations', 'name' ),
				'disableOnWords'      => array( 'beta', 'alpha' ),
				'minWordSizeForTypos' => array(
					'twoTypos' => 9,
					'oneTypo'  => 5,
				),
				'enabled'             => true,
			),
		);
		$this->assertSame( array(), Settings_Drift::differs( $repo, $live, $typo ) );

		$live['typoTolerance']['minWordSizeForTypos']['twoTypos'] = 8;
		$this->assertSame( array( 'typoTolerance' ), Settings_Drift::differs( $repo, $live, $typo ) );

		$live['stopWords'] = array( 'with' );
		$this->assertSame( array( 'stopWords', 'typoTolerance' ), Settings_Drift::differs( $repo, $live, $typo ) );
	}

	public function test_repo_typo_key_is_ignored(): void {
		$repo = array( 'typoTolerance' => array( 'enabled' => true ) );
		$live = array( 'typoTolerance' => array( 'enabled' => false ) );

		$this->assertSame( array(), Settings_Drift::differs( $repo, $live ) );
	}
}
