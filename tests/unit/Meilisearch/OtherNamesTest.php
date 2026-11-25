<?php
/**
 * Unit tests for reading other names out of an old actor excerpt.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Other_Names;

class OtherNamesTest extends TestCase {

	/**
	 * @return array<string, array{string, ?array}>
	 */
	public static function excerpt_provider(): array {
		return array(
			'one name'           => array( 'Ellen Page', array( 'Ellen Page' ) ),
			'comma list'         => array( 'Courteney Cox Arquette, Courteney Cox-Arquette', array( 'Courteney Cox Arquette', 'Courteney Cox-Arquette' ) ),
			'newlines'           => array( "Name One\nName Two\r\nName Three", array( 'Name One', 'Name Two', 'Name Three' ) ),
			'empties and dupes'  => array( 'Name One, , Name One', array( 'Name One' ) ),
			'entities'           => array( 'Zoë &amp; Co', array( 'Zoë & Co' ) ),
			'hyphenated'         => array( 'Mary-Kate Olsen', array( 'Mary-Kate Olsen' ) ),
			'initial'            => array( 'E. Page', array( 'E. Page' ) ),
			'blank'              => array( '   ', array() ),
			'suffix after comma' => array( 'Robert Downey, Jr.', null ),
			'url'                => array( 'https://example.com/x', null ),
			'www'                => array( 'see www.example.com', null ),
			'quote'              => array( '"A quote from her"', null ),
			'curly quote'        => array( '“A quote”', null ),
			'colon'              => array( 'She said: hello', null ),
			'sentences'          => array( 'She starred in many films. Later she moved.', null ),
			'long piece'         => array( 'One two three four five six', null ),
		);
	}

	#[DataProvider( 'excerpt_provider' )]
	public function test_from_excerpt( string $excerpt, ?array $expected ): void {
		$this->assertSame( $expected, Other_Names::from_excerpt( $excerpt ) );
	}

	public function test_saved_true_when_rows_hold_the_names(): void {
		$rows = array(
			array( 'field_lwtv_lezactors_other_name' => 'Ellen Page' ),
			array( 'field_lwtv_lezactors_other_name' => 'E. Page' ),
		);

		$this->assertTrue( Other_Names::saved( $rows, array( 'Ellen Page', 'E. Page' ) ) );
	}

	public function test_saved_false_when_the_write_did_not_land(): void {
		$this->assertFalse( Other_Names::saved( false, array( 'Ellen Page' ) ) );
		$this->assertFalse( Other_Names::saved( null, array( 'Ellen Page' ) ) );
		$this->assertFalse( Other_Names::saved( array( array( 'field_lwtv_lezactors_other_name' => 'Ellen Page' ) ), array( 'Ellen Page', 'E. Page' ) ) );
		// ACF couldn't resolve the key and stored the raw array under a plain meta name.
		$this->assertFalse( Other_Names::saved( array( array( 'wrong_key' => 'Ellen Page' ) ), array( 'Ellen Page' ) ) );
	}
}
