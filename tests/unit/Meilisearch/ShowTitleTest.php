<?php
/**
 * Unit tests for splitting show titles and building the years string.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Show_Title;

class ShowTitleTest extends TestCase {

	/**
	 * Titles and how they split.
	 *
	 * @return array
	 */
	public static function titles(): array {
		return array(
			'trailing year'          => array( 'Charmed (2018)', 'Charmed', '2018' ),
			'trailing country'       => array( 'Skins (US)', 'Skins', 'US' ),
			'no qualifier'           => array( 'Skins', 'Skins', '' ),
			'brackets mid-title'     => array( 'Foo (Bar) Baz', 'Foo (Bar) Baz', '' ),
			'nested brackets'        => array( 'Foo (Bar (Baz))', 'Foo (Bar (Baz))', '' ),
			'only a bracket'         => array( '(Untitled)', '(Untitled)', '' ),
			'empty bracket'          => array( 'Foo ()', 'Foo', '' ),
			'entities decoded'       => array( 'Mapp &amp; Lucia (2014)', 'Mapp & Lucia', '2014' ),
			'whitespace trimmed'     => array( '  Terra e Paixão ', 'Terra e Paixão', '' ),
			'two words in qualifier' => array( 'O Maidens in Your Savage Season (Live Action)', 'O Maidens in Your Savage Season', 'Live Action' ),
			'earlier bracket kept'   => array( 'Foo (Bar) Baz (2020)', 'Foo (Bar) Baz', '2020' ),
			'unbalanced prefix kept' => array( 'Foo (Bar (2020)', 'Foo (Bar (2020)', '' ),
		);
	}

	/**
	 * @param string $title     Raw title.
	 * @param string $name      Expected name.
	 * @param string $qualifier Expected qualifier.
	 */
	#[DataProvider( 'titles' )]
	public function test_split( string $title, string $name, string $qualifier ): void {
		$this->assertSame(
			array(
				'name'      => $name,
				'qualifier' => $qualifier,
			),
			Show_Title::split( $title )
		);
	}

	/**
	 * Start/finish values and the years string.
	 *
	 * @return array
	 */
	public static function airdates(): array {
		return array(
			'start and finish' => array( '2018', '2022', '2018 2022' ),
			'still airing'     => array( '2024', 'current', '2024' ),
			'no finish'        => array( '2024', '', '2024' ),
			'one season'       => array( '2016', '2016', '2016' ),
			'no start'         => array( '', '2020', '' ),
			'first year wins'  => array( '1998-09-01', '2006', '1998 2006' ),
			'nothing'          => array( '', '', '' ),
		);
	}

	/**
	 * @param string $start    Start value.
	 * @param string $finish   Finish value.
	 * @param string $expected Years string.
	 */
	#[DataProvider( 'airdates' )]
	public function test_years( string $start, string $finish, string $expected ): void {
		$this->assertSame( $expected, Show_Title::years( $start, $finish ) );
	}
}
