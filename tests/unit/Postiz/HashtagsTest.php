<?php
/**
 * Unit tests for building social hashtags from a title.
 *
 * Accent folding is injected rather than shimmed (remove_accents() branches on
 * get_locale()); the fold below is a small fixed map.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Postiz;

use PHPUnit\Framework\TestCase;
use LWTV\Postiz\Build\Hashtags;

class HashtagsTest extends TestCase {

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
					'É' => 'E',
					'é' => 'e',
					'ë' => 'e',
					'ñ' => 'n',
				)
			);
		};
	}

	private function tags( string $title ): array {
		return Hashtags::from_title( $title, $this->fold() );
	}

	public function test_a_plain_title_is_camel_cased(): void {
		$this->assertSame( array( '#TheLWord' ), $this->tags( 'The L Word' ) );
		$this->assertSame( array( '#TheLWord' ), $this->tags( 'the l word' ) );
	}

	public function test_a_non_latin_script_is_kept_as_written_not_percent_encoded(): void {
		// Regression: sanitize_title() percent-encoded the Thai into the tag.
		$this->assertSame(
			array( '#GirlRules', '#กฎหลักห้ามรักเธอ' ),
			$this->tags( 'Girl Rules กฎหลัก...ห้ามรักเธอ' )
		);
	}

	public function test_combining_marks_survive(): void {
		// ั and ้ are \p{M}, not \p{L}; dropping them misspells the word.
		$this->assertSame( array( '#ห้ามรักเธอ' ), $this->tags( 'ห้ามรักเธอ' ) );
	}

	public function test_a_non_latin_script_first_still_splits(): void {
		$this->assertSame( array( '#腐女子', '#Fujoshi' ), $this->tags( '腐女子 Fujoshi' ) );
	}

	public function test_accented_latin_also_gets_a_folded_tag(): void {
		$this->assertSame( array( '#Élite', '#Elite' ), $this->tags( 'Élite' ) );
		$this->assertSame( array( '#ZoëAndTheBand', '#ZoeAndTheBand' ), $this->tags( 'Zoë & the Band' ) );
	}

	public function test_an_unaccented_title_gets_no_duplicate(): void {
		$this->assertSame( array( '#Elite' ), $this->tags( 'Elite' ) );
	}

	public function test_the_first_letter_is_uppercased_multibyte_safe(): void {
		$this->assertSame( array( '#Émilie', '#Emilie' ), $this->tags( 'émilie' ) );
	}

	public function test_year_ampersand_and_at_are_cleaned(): void {
		$this->assertSame( array( '#WillAndGrace' ), $this->tags( 'Will & Grace (2017)' ) );
		$this->assertSame( array( '#Tagged' ), $this->tags( 't@gged' ) );
	}

	public function test_html_entities_from_get_the_title_are_decoded(): void {
		$this->assertSame( array( '#WillAndGrace' ), $this->tags( 'Will &amp; Grace' ) );
		$this->assertSame( array( '#GreysAnatomy' ), $this->tags( 'Grey&#8217;s Anatomy' ) );
	}

	public function test_apostrophes_join_rather_than_split(): void {
		$this->assertSame( array( '#GreysAnatomy' ), $this->tags( "Grey's Anatomy" ) );
		$this->assertSame( array( '#GreysAnatomy' ), $this->tags( 'Grey’s Anatomy' ) );
	}

	public function test_punctuation_is_a_word_break(): void {
		$this->assertSame( array( '#SWAT' ), $this->tags( 'S.W.A.T.' ) );
		$this->assertSame( array( '#MrRobot' ), $this->tags( 'Mr. Robot' ) );
	}

	public function test_numbers_attach_to_the_neighbouring_words(): void {
		$this->assertSame( array( '#Tokyo2020' ), $this->tags( 'Tokyo 2020' ) );
		$this->assertSame( array( '#911' ), $this->tags( '9-1-1' ) );
		$this->assertSame( array( '#13ReasonsWhy' ), $this->tags( '13 Reasons Why' ) );
	}

	public function test_nothing_taggable_returns_empty(): void {
		$this->assertSame( array(), $this->tags( '' ) );
		$this->assertSame( array(), $this->tags( ' ... ' ) );
	}

	public function test_as_text_joins_with_spaces(): void {
		$this->assertSame( '#Élite #Elite', Hashtags::as_text( 'Élite', $this->fold() ) );
		$this->assertSame( '', Hashtags::as_text( '', $this->fold() ) );
	}
}
