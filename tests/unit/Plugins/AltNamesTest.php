<?php
/**
 * Unit tests for the live search "also known as" matcher.
 *
 * Accent folding is injected rather than shimmed, the same way NameKeyTest
 * does it; the fold below is a small fixed map standing in for remove_accents().
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\SearchWP\Build\Alt_Names;

class AltNamesTest extends TestCase {

	/**
	 * A slice of the Languages list, in its real "English - native" shape.
	 */
	const LANGUAGES = array(
		'en'    => 'English',
		'en-GB' => 'English (United Kingdom)',
		'es'    => 'Spanish - español',
		'es-MX' => 'Spanish (Mexico) - español (México)',
	);

	/**
	 * A deterministic stand-in for remove_accents().
	 *
	 * @return callable
	 */
	private function fold(): callable {
		return static function ( $text ) {
			return strtr(
				(string) $text,
				array(
					'é' => 'e',
					'ñ' => 'n',
					'Ñ' => 'N',
				)
			);
		};
	}

	/**
	 * One repeater row, shaped the way ACF's get_field() returns it.
	 *
	 * @param string $name Alternate name.
	 * @param mixed  $type Language code.
	 * @return array
	 */
	private function row( string $name, $type = 'en' ): array {
		return array(
			'lezshows_alt_show_name' => $name,
			'type'                   => $type,
		);
	}

	public function test_matching_name_comes_back_with_its_language(): void {
		$rows = array( $this->row( 'Cable Girls', 'en' ) );

		$this->assertSame(
			array(
				array(
					'name'     => 'Cable Girls',
					'language' => 'English',
				),
			),
			Alt_Names::matching( $rows, 'Cable Girls', self::LANGUAGES )
		);
	}

	public function test_match_is_case_insensitive_and_partial(): void {
		$rows = array( $this->row( 'Cable Girls' ) );

		$this->assertCount( 1, Alt_Names::matching( $rows, 'cable', self::LANGUAGES ) );
		$this->assertCount( 1, Alt_Names::matching( $rows, 'GIRL', self::LANGUAGES ) );
	}

	public function test_every_word_must_appear_but_order_does_not_matter(): void {
		$rows = array( $this->row( 'Cable Girls' ) );

		$this->assertCount( 1, Alt_Names::matching( $rows, 'girls cable', self::LANGUAGES ) );
		$this->assertSame( array(), Alt_Names::matching( $rows, 'cable guys', self::LANGUAGES ) );
	}

	public function test_non_matching_names_are_left_out(): void {
		$rows = array(
			$this->row( 'Cable Girls', 'en' ),
			$this->row( 'Chicas del cable', 'es' ),
		);

		$result = Alt_Names::matching( $rows, 'girls', self::LANGUAGES );

		$this->assertSame( array( 'Cable Girls' ), array_column( $result, 'name' ) );
	}

	public function test_empty_or_blank_term_matches_nothing(): void {
		$rows = array( $this->row( 'Cable Girls' ) );

		$this->assertSame( array(), Alt_Names::matching( $rows, '', self::LANGUAGES ) );
		$this->assertSame( array(), Alt_Names::matching( $rows, "  \t ", self::LANGUAGES ) );
	}

	public function test_accents_fold_when_a_fold_is_given(): void {
		$rows = array( $this->row( 'Señorita', 'es' ) );

		$this->assertSame( array(), Alt_Names::matching( $rows, 'senorita', self::LANGUAGES ) );
		$this->assertCount( 1, Alt_Names::matching( $rows, 'senorita', self::LANGUAGES, $this->fold() ) );
	}

	public function test_the_original_spelling_is_returned_not_the_folded_one(): void {
		$rows   = array( $this->row( 'Señorita', 'es' ) );
		$result = Alt_Names::matching( $rows, 'senorita', self::LANGUAGES, $this->fold() );

		$this->assertSame( 'Señorita', $result[0]['name'] );
	}

	public function test_native_script_suffix_is_dropped_from_the_label(): void {
		$rows   = array( $this->row( 'Las Chicas', 'es' ) );
		$result = Alt_Names::matching( $rows, 'chicas', self::LANGUAGES );

		$this->assertSame( 'Spanish', $result[0]['language'] );
	}

	public function test_regional_label_is_flattened_so_it_can_sit_in_parentheses(): void {
		$rows = array(
			$this->row( 'Cable Girls', 'en-GB' ),
			$this->row( 'Chicas del cable', 'es-MX' ),
		);

		$this->assertSame(
			array( 'English, United Kingdom' ),
			array_column( Alt_Names::matching( $rows, 'girls', self::LANGUAGES ), 'language' )
		);
		$this->assertSame(
			array( 'Spanish, Mexico' ),
			array_column( Alt_Names::matching( $rows, 'chicas', self::LANGUAGES ), 'language' )
		);
	}

	public function test_unknown_or_missing_language_gives_an_empty_label(): void {
		$rows = array(
			$this->row( 'Cable Girls', 'xx' ),
			$this->row( 'Cable Ladies', null ),
			array( 'lezshows_alt_show_name' => 'Cable Women' ),
		);

		$this->assertSame(
			array( '', '', '' ),
			array_column( Alt_Names::matching( $rows, 'cable', self::LANGUAGES ), 'language' )
		);
	}

	public function test_blank_and_malformed_rows_are_skipped(): void {
		$rows = array(
			$this->row( '' ),
			$this->row( '   ' ),
			'not a row',
			array( 'type' => 'en' ),
			$this->row( 'Cable Girls' ),
		);

		$this->assertSame(
			array( 'Cable Girls' ),
			array_column( Alt_Names::matching( $rows, 'cable', self::LANGUAGES ), 'name' )
		);
	}

	public function test_duplicate_names_are_listed_once(): void {
		$rows = array(
			$this->row( 'Cable Girls', 'en' ),
			$this->row( 'cable girls', 'en-GB' ),
		);

		$result = Alt_Names::matching( $rows, 'cable', self::LANGUAGES );

		$this->assertCount( 1, $result );
		$this->assertSame( 'English', $result[0]['language'] );
	}

	public function test_non_array_input_is_treated_as_no_names(): void {
		// get_field() returns false or null when the repeater is empty.
		$this->assertSame( array(), Alt_Names::matching( false, 'cable', self::LANGUAGES ) );
		$this->assertSame( array(), Alt_Names::matching( null, 'cable', self::LANGUAGES ) );
	}
}
