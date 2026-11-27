<?php
/**
 * Unit tests for the app catalogue body.
 *
 * @package lwtv-underscores
 */

// Core json_encode: wp_json_encode is WordPress, and these tests run without it.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\App_Catalogue;

class AppCatalogueTest extends TestCase {

	/**
	 * A raw show with the given id, terms and similar ids.
	 *
	 * @param int   $id      Show id.
	 * @param array $terms   Terms by field.
	 * @param array $similar Similar ids.
	 *
	 * @return array
	 */
	private function raw( int $id, array $terms = array(), array $similar = array() ): array {
		return array(
			'id'      => $id,
			'slug'    => 'show-' . $id,
			'title'   => 'Show ' . $id,
			'url'     => 'https://example.test/show/show-' . $id . '/',
			'start'   => '2020',
			'finish'  => '2021',
			'on_air'  => 'no',
			'terms'   => $terms,
			'similar' => $similar,
		);
	}

	/**
	 * One term row.
	 *
	 * @param string $slug Slug.
	 * @param string $name Name.
	 *
	 * @return array
	 */
	private function term( string $slug, string $name ): array {
		return array(
			'slug' => $slug,
			'name' => $name,
		);
	}

	public function test_shows_sorted_by_id(): void {
		$out = App_Catalogue::build( array( $this->raw( 30 ), $this->raw( 10 ), $this->raw( 20 ) ) );

		$this->assertSame( array( 10, 20, 30 ), array_column( $out['shows'], 'id' ) );
	}

	public function test_dictionaries_hold_only_used_terms(): void {
		$out = App_Catalogue::build(
			array(
				$this->raw(
					10,
					array(
						'tropes' => array(
							101 => $this->term( 'cops', 'Cops &amp; Robbers' ),
							102 => $this->term( 'period', 'Period' ),
						),
					)
				),
				$this->raw( 20, array( 'tropes' => array( 103 => $this->term( 'comedy', 'Comedy' ) ) ) ),
			)
		);

		$tropes = json_decode( json_encode( $out['terms']['tropes'] ), true );

		$this->assertSame( array( 101, 102, 103 ), array_keys( $tropes ) );
		$this->assertArrayNotHasKey( 104, $tropes );
		$this->assertSame(
			array(
				'slug' => 'cops',
				'name' => 'Cops & Robbers',
			),
			$tropes[101]
		);
	}

	public function test_terms_keys_follow_contract_order(): void {
		$out = App_Catalogue::build( array() );

		$this->assertSame( array( 'tropes', 'genres', 'formats', 'countries', 'stations' ), array_keys( $out['terms'] ) );
		$this->assertSame( array(), $out['shows'] );
	}

	public function test_empty_taxonomy_dictionary_encodes_as_object(): void {
		$out = App_Catalogue::build( array( $this->raw( 10 ) ) );

		$this->assertSame( '{}', json_encode( $out['terms']['genres'] ) );
	}

	public function test_similar_drops_ids_not_in_catalogue(): void {
		$out = App_Catalogue::build( array( $this->raw( 10, array(), array( 20, 99 ) ), $this->raw( 20 ) ) );

		$this->assertSame( array( 20 ), $out['shows'][0]['similar'] );
	}

	/**
	 * Raws built from rows of the pwa sample catalogue.
	 *
	 * Fixture: https://github.com/LezWatch/pwa/blob/production/tests/Fixtures/app/catalogue.json
	 * Only the shows needed to touch every term are copied.
	 */
	public function test_build_of_pwa_sample_raws_matches_sample_terms(): void {
		$formats   = array( 401 => $this->term( 'tv-show', 'Series' ) );
		$countries = array( 501 => $this->term( 'usa', 'USA' ) );
		$genres    = array( 301 => $this->term( 'drama', 'Drama' ) );
		$law       = $this->term( 'law-enforcement', 'Law enforcement' );
		$period    = $this->term( 'period-drama', 'Period drama' );

		$base = array(
			'formats'   => $formats,
			'countries' => $countries,
			'genres'    => $genres,
		);

		$out = App_Catalogue::build(
			array(
				$this->raw(
					90006,
					$base + array(
						'stations' => array( 601 => $this->term( 'max', 'Max' ) ),
						'tropes'   => array(
							102 => $period,
							103 => $this->term( 'sex-workers', 'Sex workers' ),
						),
					)
				),
				$this->raw(
					90002,
					$base + array(
						'stations' => array( 602 => $this->term( 'prime-video', 'Prime Video' ) ),
						'tropes'   => array( 102 => $period ),
					),
					array( 90006 )
				),
				$this->raw(
					90005,
					$base + array(
						'stations' => array( 603 => $this->term( 'netflix', 'Netflix' ) ),
						'tropes'   => array(
							101 => $law,
							107 => $this->term( 'dead-queers', 'Queers died' ),
						),
					)
				),
				$this->raw(
					90008,
					$base + array(
						'stations' => array( 604 => $this->term( 'hulu', 'Hulu' ) ),
						'tropes'   => array(
							101 => $law,
							105 => $this->term( 'coming-out', 'Coming out stories' ),
						),
					)
				),
			)
		);

		$sample = array(
			'tropes'    => array(
				101 => array(
					'slug' => 'law-enforcement',
					'name' => 'Law enforcement',
				),
				102 => array(
					'slug' => 'period-drama',
					'name' => 'Period drama',
				),
				103 => array(
					'slug' => 'sex-workers',
					'name' => 'Sex workers',
				),
				105 => array(
					'slug' => 'coming-out',
					'name' => 'Coming out stories',
				),
				107 => array(
					'slug' => 'dead-queers',
					'name' => 'Queers died',
				),
			),
			'genres'    => array(
				301 => array(
					'slug' => 'drama',
					'name' => 'Drama',
				),
			),
			'formats'   => array(
				401 => array(
					'slug' => 'tv-show',
					'name' => 'Series',
				),
			),
			'countries' => array(
				501 => array(
					'slug' => 'usa',
					'name' => 'USA',
				),
			),
			'stations'  => array(
				601 => array(
					'slug' => 'max',
					'name' => 'Max',
				),
				602 => array(
					'slug' => 'prime-video',
					'name' => 'Prime Video',
				),
				603 => array(
					'slug' => 'netflix',
					'name' => 'Netflix',
				),
				604 => array(
					'slug' => 'hulu',
					'name' => 'Hulu',
				),
			),
		);

		$this->assertSame( $sample, json_decode( json_encode( $out['terms'] ), true ) );
		$this->assertSame( array( 90002, 90005, 90006, 90008 ), array_column( $out['shows'], 'id' ) );
		$this->assertSame( array( 90006 ), $out['shows'][0]['similar'] );
	}
}
