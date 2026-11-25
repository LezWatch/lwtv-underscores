<?php
/**
 * Unit tests for building a show's Meilisearch document.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Show_Document;

class ShowDocumentTest extends TestCase {

	/**
	 * Build an otherwise-empty show, overriding one input.
	 *
	 * @param array $overrides Input name => value.
	 * @return array
	 */
	private function build( array $overrides = array() ): array {
		$in = array_merge(
			array(
				'title'      => 'Foo',
				'alt_rows'   => false,
				'airdates'   => array(
					'start'  => '',
					'finish' => '',
				),
				'worth_it'   => null,
				'score'      => null,
				'characters' => array(),
				'actors'     => array(),
				'stations'   => array(),
				'nations'    => array(),
			),
			$overrides
		);

		return Show_Document::build( 5, $in['title'], $in['alt_rows'], $in['airdates'], $in['worth_it'], $in['score'], $in['characters'], $in['actors'], $in['stations'], $in['nations'], '/show/foo/', '' );
	}

	public function test_charmed_2018(): void {
		$doc = Show_Document::build(
			32399,
			'Charmed (2018)',
			array(
				array(
					'lezshows_alt_show_name' => 'Charmed Reboot',
					'type'                   => 'en',
				),
			),
			array(
				'start'  => '2018',
				'finish' => '2022',
			),
			'Yes',
			'64.45',
			array( 'Mel Vera', 'Maggie Vera', 'Mel Vera' ),
			array(
				7 => array(
					'title'   => 'Melonie Diaz',
					'visible' => true,
				),
				8 => array(
					'title'   => 'Private Person',
					'visible' => false,
				),
			),
			array( 'The CW' ),
			array( 'USA' ),
			'/show/charmed-2018/',
			'/thumb.jpg'
		);

		$this->assertSame( 32399, $doc['id'] );
		$this->assertSame( 'Charmed', $doc['name'] );
		$this->assertSame( '2018', $doc['qualifier'] );
		$this->assertSame( array( 'Charmed Reboot' ), $doc['alt_names'] );
		$this->assertSame( array( 'Mel Vera', 'Maggie Vera' ), $doc['characters'] );
		$this->assertSame( array( 'Melonie Diaz' ), $doc['actors'] );
		$this->assertSame( '2018 2022', $doc['years'] );
		$this->assertSame( 'Yes', $doc['worth_it'] );
		$this->assertSame( 3, $doc['worth_it_rank'] );
		$this->assertSame( 64.45, $doc['score'] );
		$this->assertSame( array( 'The CW' ), $doc['stations'] );
		$this->assertSame( array( 'USA' ), $doc['nations'] );
		$this->assertSame( '/show/charmed-2018/', $doc['url'] );
		$this->assertSame( '/thumb.jpg', $doc['thumb'] );
		$this->assertSame(
			array( 'id', 'name', 'qualifier', 'alt_names', 'characters', 'actors', 'years', 'worth_it', 'worth_it_rank', 'score', 'stations', 'nations', 'url', 'thumb' ),
			array_keys( $doc )
		);
	}

	public function test_empty_show_builds(): void {
		$doc = $this->build();

		$this->assertSame( 'Foo', $doc['name'] );
		$this->assertSame( '', $doc['qualifier'] );
		$this->assertSame( array(), $doc['alt_names'] );
		$this->assertSame( array(), $doc['characters'] );
		$this->assertSame( array(), $doc['actors'] );
		$this->assertSame( '', $doc['years'] );
		$this->assertSame( '', $doc['worth_it'] );
		$this->assertSame( 0, $doc['worth_it_rank'] );
		$this->assertSame( 0.0, $doc['score'] );
		$this->assertSame( array(), $doc['stations'] );
		$this->assertSame( array(), $doc['nations'] );
	}

	/**
	 * @return array
	 */
	public static function worth_it(): array {
		return array(
			'yes'     => array( 'Yes', 'Yes', 3 ),
			'meh'     => array( 'Meh', 'Meh', 2 ),
			'tbd'     => array( 'TBD', 'TBD', 1 ),
			'no'      => array( 'No', 'No', 0 ),
			'missing' => array( null, '', 0 ),
			'empty'   => array( '', '', 0 ),
			'unknown' => array( 'Bogus', '', 0 ),
		);
	}

	/**
	 * @param mixed  $value Stored value.
	 * @param string $label Expected worth_it.
	 * @param int    $rank  Expected rank.
	 */
	#[DataProvider( 'worth_it' )]
	public function test_worth_it_rank( $value, string $label, int $rank ): void {
		$doc = $this->build( array( 'worth_it' => $value ) );

		$this->assertSame( $label, $doc['worth_it'] );
		$this->assertSame( $rank, $doc['worth_it_rank'] );
	}

	/**
	 * @return array
	 */
	public static function scores(): array {
		return array(
			'numeric string' => array( '31.87', 31.87 ),
			'over 100'       => array( '120', 100.0 ),
			'negative'       => array( '-5', 0.0 ),
			'not a number'   => array( 'abc', 0.0 ),
			'float'          => array( 64.5, 64.5 ),
		);
	}

	/**
	 * @param mixed $value    Stored score.
	 * @param float $expected Document score.
	 */
	#[DataProvider( 'scores' )]
	public function test_score( $value, float $expected ): void {
		$this->assertSame( $expected, $this->build( array( 'score' => $value ) )['score'] );
	}

	public function test_alt_names_normalized(): void {
		$rows = array(
			array( 'lezshows_alt_show_name' => 'Land of Desire' ),
			array( 'lezshows_alt_show_name' => '   ' ),
			array( 'lezshows_alt_show_name' => '' ),
			array( 'lezshows_alt_show_name' => 'Jo &amp; Co' ),
			array( 'lezshows_alt_show_name' => 'Land of Desire' ),
			array( 'field_lwtv_lezshows_alt_show_name' => 'Raw Mode Name' ),
			'not a row',
			array( 'type' => 'en' ),
		);

		$this->assertSame( array( 'Land of Desire', 'Jo & Co', 'Raw Mode Name' ), Show_Document::alt_names( $rows ) );
	}

	public function test_alt_names_empty_inputs(): void {
		$this->assertSame( array(), Show_Document::alt_names( false ) );
		$this->assertSame( array(), Show_Document::alt_names( null ) );
		$this->assertSame( array(), Show_Document::alt_names( '' ) );
	}

	public function test_titles_decoded_and_deduped(): void {
		$doc = $this->build(
			array(
				'characters' => array( 'Jo &amp; Co', 'Jo & Co', ' ' ),
				'actors'     => array(
					1 => array(
						'title'   => 'Kate &amp; Co',
						'visible' => true,
					),
					2 => array(
						'title'   => 'Kate & Co',
						'visible' => true,
					),
					3 => array( 'visible' => true ),
				),
			)
		);

		$this->assertSame( array( 'Jo & Co' ), $doc['characters'] );
		$this->assertSame( array( 'Kate & Co' ), $doc['actors'] );
	}

	public function test_terms_cleaned(): void {
		$doc = $this->build(
			array(
				'stations' => array( 'The CW', '', 'The CW', 'BBC &amp; Co' ),
				'nations'  => array( 'USA' ),
			)
		);

		$this->assertSame( array( 'The CW', 'BBC & Co' ), $doc['stations'] );
		$this->assertSame( array( 'USA' ), $doc['nations'] );
	}
}
