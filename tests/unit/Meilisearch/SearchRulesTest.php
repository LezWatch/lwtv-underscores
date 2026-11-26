<?php
/**
 * Unit tests for the search rules (synonyms) normalizer and converter.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Search_Rules;

class SearchRulesTest extends TestCase {

	/**
	 * Normalize with no stop words.
	 *
	 * @param mixed $raw Raw input.
	 * @return array
	 */
	private function norm( $raw ): array {
		return Search_Rules::normalize( $raw, array() );
	}

	/**
	 * Rows of the messages of one kind.
	 *
	 * @param array  $messages Errors or warnings.
	 * @param string $kind     two_way or one_way.
	 * @return int[]
	 */
	private function rows( array $messages, string $kind ): array {
		return array_values( array_map( fn( $m ) => $m['row'], array_filter( $messages, fn( $m ) => $kind === $m['kind'] ) ) );
	}

	public function test_word_cleans_case_and_spacing(): void {
		$this->assertSame( 'non binary', Search_Rules::word( '  Non   Binary ' ) );
		$this->assertSame( 'chicás', Search_Rules::word( 'Chicás' ) );
		$this->assertSame( 'a b', Search_Rules::word( "a\t\n b" ) );
	}

	public function test_two_way_group_is_cleaned_and_deduplicated(): void {
		$out = $this->norm( array( 'two_way' => array( array( 'faq', 'FAQ', 'Frequently  asked questions' ) ) ) );

		$this->assertSame( array( array( 'faq', 'frequently asked questions' ) ), $out['synonyms']['two_way'] );
		$this->assertSame( array(), $out['errors'] );
	}

	public function test_two_way_group_needs_two_words(): void {
		$out = $this->norm( array( 'two_way' => array( array( 'faq', 'a', 'b' ), array( 'Enby', 'enby ' ) ) ) );

		$this->assertSame( array( 1 ), $this->rows( $out['errors'], 'two_way' ) );
	}

	public function test_empty_two_way_groups_are_dropped_silently(): void {
		$out = $this->norm( array( 'two_way' => array( array(), array( '  ', '' ), array( 'a', 'b' ) ) ) );

		$this->assertSame( array( array( 'a', 'b' ) ), $out['synonyms']['two_way'] );
		$this->assertSame( array(), $out['errors'] );
	}

	public function test_one_way_entry_is_cleaned(): void {
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => 'TLW',
						'to'   => array( 'The L Word' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'from' => 'tlw',
					'to'   => array( 'the l word' ),
				),
			),
			$out['synonyms']['one_way']
		);
		$this->assertSame( array(), $out['errors'] );
	}

	public function test_one_way_needs_from_and_to(): void {
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => '',
						'to'   => array( 'x' ),
					),
					array(
						'from' => 'y',
						'to'   => array(),
					),
					array( 'to' => array( 'z' ) ),
				),
			)
		);

		$this->assertSame( array( 0, 1, 2 ), $this->rows( $out['errors'], 'one_way' ) );
		$this->assertSame( array(), $out['synonyms']['one_way'] );
	}

	public function test_blank_one_way_rows_are_dropped_silently(): void {
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => ' ',
						'to'   => array(),
					),
				),
			)
		);

		$this->assertSame( array(), $out['synonyms']['one_way'] );
		$this->assertSame( array(), $out['errors'] );
	}

	public function test_one_way_from_is_removed_from_its_own_to(): void {
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => 'tlw',
						'to'   => array( 'TLW', 'the l word' ),
					),
					array(
						'from' => 'ds9',
						'to'   => array( 'DS9' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'from' => 'tlw',
					'to'   => array( 'the l word' ),
				),
			),
			$out['synonyms']['one_way']
		);
		$this->assertSame( array( 1 ), $this->rows( $out['errors'], 'one_way' ) );
	}

	public function test_word_in_two_groups_is_an_error_naming_the_other_group(): void {
		$out = $this->norm(
			array(
				'two_way' => array(
					array( 'nonbinary', 'enby' ),
					array( 'faq', 'frequently asked questions' ),
					array( 'Non  Binary', 'NonBinary' ),
				),
			)
		);

		$errors = array_values( array_filter( $out['errors'], fn( $e ) => 2 === $e['row'] ) );
		$this->assertCount( 1, $errors );
		$this->assertSame( 'two_way', $errors[0]['kind'] );
		$this->assertStringContainsString( 'nonbinary, enby', $errors[0]['message'] );

		// The first group is flagged too, so the error shows on both rows.
		$this->assertSame( array( 0, 2 ), $this->rows( $out['errors'], 'two_way' ) );
		$first = array_values( array_filter( $out['errors'], fn( $e ) => 0 === $e['row'] ) );
		$this->assertStringContainsString( 'non binary, nonbinary', $first[0]['message'] );
	}

	public function test_same_from_twice_is_an_error_on_the_second(): void {
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => 'tlw',
						'to'   => array( 'the l word' ),
					),
					array(
						'from' => 'TLW ',
						'to'   => array( 'the l word generation q' ),
					),
				),
			)
		);

		$this->assertSame( array( 1 ), $this->rows( $out['errors'], 'one_way' ) );
	}

	public function test_stop_word_is_a_warning_not_an_error(): void {
		$out = Search_Rules::normalize( array( 'two_way' => array( array( 'from', 'since' ) ) ), array( 'from', 'on' ) );

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( array( 0 ), $this->rows( $out['warnings'], 'two_way' ) );
		$this->assertStringContainsString( 'from', $out['warnings'][0]['message'] );
		$this->assertSame( array( array( 'from', 'since' ) ), $out['synonyms']['two_way'] );
	}

	public function test_stop_word_in_one_way_is_a_warning(): void {
		$out = Search_Rules::normalize(
			array(
				'one_way' => array(
					array(
						'from' => 'x',
						'to'   => array( 'on' ),
					),
				),
			),
			array( 'from', 'on' )
		);

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( array( 0 ), $this->rows( $out['warnings'], 'one_way' ) );
	}

	public function test_word_over_100_characters_is_an_error(): void {
		$long = str_repeat( 'a', 101 );
		$out  = $this->norm( array( 'two_way' => array( array( $long, 'b' ) ) ) );

		$this->assertSame( array( 0 ), $this->rows( $out['errors'], 'two_way' ) );

		$ok = $this->norm( array( 'two_way' => array( array( str_repeat( 'é', 100 ), 'b' ) ) ) );
		$this->assertSame( array(), $ok['errors'] );
	}

	public function test_too_many_groups_is_one_error_and_keeps_500(): void {
		$groups = array();
		for ( $i = 0; $i < 501; $i++ ) {
			$groups[] = array( 'a' . $i, 'b' . $i );
		}
		$out = $this->norm( array( 'two_way' => $groups ) );

		$this->assertCount( 1, $out['errors'] );
		$this->assertSame( -1, $out['errors'][0]['row'] );
		$this->assertSame( 'two_way', $out['errors'][0]['kind'] );
		$this->assertStringContainsString( 'too many groups', strtolower( $out['errors'][0]['message'] ) );
		$this->assertCount( 500, $out['synonyms']['two_way'] );
	}

	public function test_too_many_one_way_entries_is_one_error(): void {
		$rows = array();
		for ( $i = 0; $i < 501; $i++ ) {
			$rows[] = array(
				'from' => 'f' . $i,
				'to'   => array( 't' ),
			);
		}
		$out = $this->norm( array( 'one_way' => $rows ) );

		$this->assertCount( 1, $out['errors'] );
		$this->assertSame( -1, $out['errors'][0]['row'] );
		$this->assertCount( 500, $out['synonyms']['one_way'] );
	}

	public function test_group_with_too_many_words_is_an_error(): void {
		$words = array();
		for ( $i = 0; $i <= Search_Rules::MAX_GROUP_WORDS; $i++ ) {
			$words[] = 'w' . $i;
		}
		$out = $this->norm( array( 'two_way' => array( array_slice( $words, 0, Search_Rules::MAX_GROUP_WORDS ), $words ) ) );

		$this->assertSame( 20, Search_Rules::MAX_GROUP_WORDS );
		$this->assertSame( array( 1 ), array_values( array_unique( $this->rows( array_filter( $out['errors'], fn( $e ) => false !== strpos( $e['message'], 'most' ) ), 'two_way' ) ) ) );
	}

	public function test_one_way_with_too_many_to_words_is_an_error(): void {
		$to = array();
		for ( $i = 0; $i <= Search_Rules::MAX_TO; $i++ ) {
			$to[] = 't' . $i;
		}
		$out = $this->norm(
			array(
				'one_way' => array(
					array(
						'from' => 'a',
						'to'   => array_slice( $to, 0, Search_Rules::MAX_TO ),
					),
					array(
						'from' => 'b',
						'to'   => $to,
					),
				),
			)
		);

		$this->assertSame( 20, Search_Rules::MAX_TO );
		$this->assertSame( array( 1 ), $this->rows( $out['errors'], 'one_way' ) );
		$this->assertCount( 1, $out['synonyms']['one_way'] );
	}

	public function test_a_huge_group_does_not_blow_up(): void {
		$words = array();
		for ( $i = 0; $i < 4000; $i++ ) {
			$words[] = 'w' . $i;
		}
		$out = $this->norm( array( 'two_way' => array( $words ) ) );

		$this->assertNotEmpty( $out['errors'] );
		$this->assertSame( array(), $out['synonyms']['two_way'] );
		$this->assertSame( array(), Search_Rules::to_meilisearch( $out['synonyms'] ) );
	}

	public function test_to_meilisearch_two_way_group(): void {
		$map = Search_Rules::to_meilisearch(
			array(
				'two_way' => array( array( 'c', 'a', 'b' ) ),
				'one_way' => array(),
			)
		);

		$this->assertSame(
			array(
				'a' => array( 'b', 'c' ),
				'b' => array( 'a', 'c' ),
				'c' => array( 'a', 'b' ),
			),
			$map
		);
	}

	public function test_to_meilisearch_one_way_entry(): void {
		$map = Search_Rules::to_meilisearch(
			array(
				'two_way' => array(),
				'one_way' => array(
					array(
						'from' => 'x',
						'to'   => array( 'y' ),
					),
				),
			)
		);

		$this->assertSame( array( 'x' => array( 'y' ) ), $map );
	}

	public function test_to_meilisearch_merges_and_deduplicates(): void {
		$map = Search_Rules::to_meilisearch(
			array(
				'two_way' => array( array( 'tlw', 'the l word' ) ),
				'one_way' => array(
					array(
						'from' => 'tlw',
						'to'   => array( 'the l word', 'generation q' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				'the l word' => array( 'tlw' ),
				'tlw'        => array( 'generation q', 'the l word' ),
			),
			$map
		);
	}

	public function test_to_meilisearch_of_nothing_is_empty(): void {
		$this->assertSame( array(), Search_Rules::to_meilisearch( array() ) );
	}

	public function test_same_ignores_order(): void {
		$a = array(
			'x' => array( 'y', 'z' ),
			'y' => array( 'x' ),
		);
		$b = array(
			'y' => array( 'x' ),
			'x' => array( 'z', 'y' ),
		);

		$this->assertTrue( Search_Rules::same( $a, $b ) );
		$this->assertTrue( Search_Rules::same( array(), array() ) );
	}

	public function test_same_spots_a_missing_value(): void {
		$a = array( 'x' => array( 'y', 'z' ) );
		$b = array( 'x' => array( 'y' ) );

		$this->assertFalse( Search_Rules::same( $a, $b ) );
		$this->assertFalse( Search_Rules::same( $a, array() ) );
	}

	public function test_hostile_shapes_give_empty_synonyms(): void {
		$empty = array(
			'two_way' => array(),
			'one_way' => array(),
		);

		foreach ( array( 'a string', null, array( 'two_way' => 'x' ), array( 'one_way' => 5 ), 42 ) as $raw ) {
			$out = $this->norm( $raw );
			$this->assertSame( $empty, $out['synonyms'] );
			$this->assertSame( array(), $out['errors'] );
		}
	}

	public function test_non_string_words_are_dropped_and_ints_cast(): void {
		$out = $this->norm(
			array(
				'two_way' => array( array( 'a', array( 'b' ), true, 100, null, 1.5 ), 'not a group' ),
				'one_way' => array(
					array(
						'from' => array( 'x' ),
						'to'   => array( 'y' ),
					),
					array(
						'from' => 99,
						'to'   => 'not a list',
					),
					array(
						'from' => 'b99',
						'to'   => array( 'brooklyn nine-nine', false ),
					),
				),
			)
		);

		$this->assertSame( array( array( 'a', '100' ) ), $out['synonyms']['two_way'] );
		$this->assertSame(
			array(
				array(
					'from' => 'b99',
					'to'   => array( 'brooklyn nine-nine' ),
				),
			),
			$out['synonyms']['one_way']
		);
		// A 'from' that isn't a string counts as missing; a 'to' that isn't a list counts as empty.
		$this->assertSame( array( 0, 1 ), $this->rows( $out['errors'], 'one_way' ) );
	}

	public function test_invalid_utf8_words_are_dropped(): void {
		$out = $this->norm( array( 'two_way' => array( array( "bad\xC3\x28", 'a', 'b' ) ) ) );

		$this->assertSame( array( array( 'a', 'b' ) ), $out['synonyms']['two_way'] );
	}

	public function test_extra_keys_are_ignored(): void {
		$out = $this->norm(
			array(
				'two_way'  => array( array( 'a', 'b' ) ),
				'one_way'  => array(
					array(
						'from'    => 'x',
						'to'      => array( 'y' ),
						'applied' => 'z',
					),
				),
				'applied'  => array( 'at' => 1 ),
				'pins'     => array( 1 ),
				'key'      => 'secret',
				'redirect' => 'x',
			)
		);

		$this->assertSame( array( 'two_way', 'one_way' ), array_keys( $out['synonyms'] ) );
		$this->assertSame( array( 'from', 'to' ), array_keys( $out['synonyms']['one_way'][0] ) );
	}

	public function test_html_words_are_kept_literally(): void {
		$out = $this->norm( array( 'two_way' => array( array( '<img src=x onerror=alert(1)>', 'b' ) ) ) );

		$this->assertSame( '<img src=x onerror=alert(1)>', $out['synonyms']['two_way'][0][0] );
	}

	public function test_starter_list_is_valid(): void {
		$dir   = dirname( __DIR__, 3 ) . '/plugins/lwtv-plugin/php/meilisearch/config';
		$stops = array();
		foreach ( glob( $dir . '/*.json' ) as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $json ) && is_array( $json['stopWords'] ?? null ) ) {
				$stops = array_merge( $stops, $json['stopWords'] );
			}
		}
		$this->assertContains( 'from', $stops );

		$starter = json_decode( (string) file_get_contents( $dir . '/synonyms-starter.json' ), true );
		$out     = Search_Rules::normalize( $starter, array_unique( $stops ) );

		$this->assertSame( array(), $out['errors'] );
		$this->assertSame( array(), $out['warnings'] );
		$this->assertCount( 4, $out['synonyms']['two_way'] );
		$this->assertCount( 12, $out['synonyms']['one_way'] );
		$this->assertSame( $starter, $out['synonyms'], 'The starter file is stored already normalized.' );
		// kdrama finds Korean shows through their nation (shows.json searches nations).
		$this->assertContains( array( 'kdrama', 'k-drama', 'korean drama', 'south korea' ), $out['synonyms']['two_way'] );
	}
}
