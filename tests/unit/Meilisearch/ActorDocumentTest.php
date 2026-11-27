<?php
/**
 * Unit tests for building an actor's Meilisearch document.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Actor_Document;

class ActorDocumentTest extends TestCase {

	/**
	 * Build an otherwise-empty actor, overriding some inputs.
	 *
	 * @param array $overrides Input name => value.
	 * @return array
	 */
	private function build( array $overrides = array() ): array {
		$in = array_merge(
			array(
				'title'      => 'Foo Bar',
				'other_rows' => false,
				'characters' => array(),
				'shows'      => array(),
				'birth'      => '',
				'death'      => '',
				'privacy'    => '',
				'bio'        => '',
				'queer'      => '',
				'char_count' => '',
			),
			$overrides
		);

		return Actor_Document::build(
			14089,
			$in['title'],
			$in['other_rows'],
			$in['characters'],
			$in['shows'],
			$in['birth'],
			$in['death'],
			$in['privacy'],
			$in['bio'],
			$in['queer'],
			$in['char_count'],
			'https://example.test/actor/foo/',
			'https://example.test/foo.jpg'
		);
	}

	public function test_shape(): void {
		$doc = $this->build();

		$this->assertSame( array( 'id', 'name', 'other_names', 'characters', 'shows', 'years', 'bio', 'queer', 'character_count', 'url', 'thumb' ), array_keys( $doc ) );
		$this->assertSame( 14089, $doc['id'] );
		$this->assertSame( 0, $doc['queer'] );
		$this->assertSame( 0, $doc['character_count'] );
		$this->assertSame( 'https://example.test/actor/foo/', $doc['url'] );
		$this->assertSame( 'https://example.test/foo.jpg', $doc['thumb'] );
	}

	public function test_name_decoded(): void {
		$this->assertSame( "Rosie O'Donnell", $this->build( array( 'title' => 'Rosie O&#039;Donnell ' ) )['name'] );
	}

	public function test_other_names_by_key_and_name(): void {
		$rows = array(
			array( 'field_lwtv_lezactors_other_name' => 'Ellen Page' ),
			array( 'lezactors_other_name' => 'E. Page' ),
			array( 'lezactors_other_name' => '' ),
			array( 'lezactors_other_name' => 'Ellen Page' ),
			array( 'lezactors_other_name' => 'Zoë &amp; Co' ),
			'not a row',
		);

		$this->assertSame( array( 'Ellen Page', 'E. Page', 'Zoë & Co' ), $this->build( array( 'other_rows' => $rows ) )['other_names'] );
	}

	public function test_other_names_not_rows(): void {
		foreach ( array( false, null, '', 0 ) as $rows ) {
			$this->assertSame( array(), $this->build( array( 'other_rows' => $rows ) )['other_names'] );
		}
	}

	public function test_other_name_equal_to_title_dropped(): void {
		$rows = array(
			array( 'lezactors_other_name' => 'elliot page' ),
			array( 'lezactors_other_name' => 'Ellen Page' ),
		);

		$this->assertSame(
			array( 'Ellen Page' ),
			$this->build(
				array(
					'title'      => 'Elliot Page',
					'other_rows' => $rows,
				)
			)['other_names']
		);
	}

	/**
	 * @return array<string, array{mixed, mixed, string}>
	 */
	public static function years_provider(): array {
		return array(
			'Y-m-d birth'     => array( '1968-07-07', '', '1968' ),
			'Y-n-j birth'     => array( '1975-1-25', '', '1975' ),
			'Ymd birth'       => array( '19750125', '', '1975' ),
			'death only'      => array( '', '2020-03-01', '2020' ),
			'both'            => array( '1940-01-01', '2020-03-01', '1940 2020' ),
			'zero date'       => array( '0000-00-00', '', '' ),
			'junk'            => array( 'unknown', '', '' ),
			'nothing'         => array( null, false, '' ),
			'same year twice' => array( '2001-01-01', '2001-12-31', '2001' ),
		);
	}

	#[DataProvider( 'years_provider' )]
	public function test_years( $birth, $death, string $expected ): void {
		$this->assertSame(
			$expected,
			$this->build(
				array(
					'birth' => $birth,
					'death' => $death,
				)
			)['years']
		);
	}

	public function test_hide_dob_drops_birth_keeps_death(): void {
		$in = array(
			'birth' => '1940-01-01',
			'death' => '2020-03-01',
		);

		$this->assertSame( '2020', $this->build( $in + array( 'privacy' => array( 'hide_dob' ) ) )['years'] );
		$this->assertSame(
			'',
			$this->build(
				array(
					'birth'   => '1940-01-01',
					'privacy' => array( 'hide_socials', 'hide_dob' ),
				)
			)['years']
		);
		// Only an array means a privacy choice was saved.
		$this->assertSame( '1940 2020', $this->build( $in + array( 'privacy' => 'hide_dob' ) )['years'] );
	}

	public function test_bio_collapsed(): void {
		$this->assertSame( 'Line one. Line two & more', $this->build( array( 'bio' => "  Line one.\n\n Line&nbsp;two &amp; more " ) )['bio'] );
	}

	public function test_queer(): void {
		foreach ( array( '1', 1, true ) as $value ) {
			$this->assertSame( 1, $this->build( array( 'queer' => $value ) )['queer'], (string) json_encode( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no WordPress in unit tests.
		}
		foreach ( array( '', '0', 0, false, null ) as $value ) {
			$this->assertSame( 0, $this->build( array( 'queer' => $value ) )['queer'], (string) json_encode( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no WordPress in unit tests.
		}
	}

	public function test_char_count(): void {
		$this->assertSame( 4, $this->build( array( 'char_count' => '4' ) )['character_count'] );
		foreach ( array( null, 'abc', -2, '' ) as $value ) {
			$this->assertSame( 0, $this->build( array( 'char_count' => $value ) )['character_count'], (string) json_encode( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no WordPress in unit tests.
		}
	}

	public function test_characters_and_shows_deduped(): void {
		$titles = array( 'Sara Sidle', 'Sara Sidle', 'A &amp; B', '', null );
		$doc    = $this->build(
			array(
				'characters' => $titles,
				'shows'      => $titles,
			)
		);

		$this->assertSame( array( 'Sara Sidle', 'A & B' ), $doc['characters'] );
		$this->assertSame( array( 'Sara Sidle', 'A & B' ), $doc['shows'] );
	}
}
