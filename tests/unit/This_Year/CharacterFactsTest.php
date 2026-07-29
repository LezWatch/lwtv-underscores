<?php
/**
 * Unit tests for the per-character "facts for a year" transform behind the
 * Characters On Air panel extras (in 2+ shows, debuting this year).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Character_Facts;

class CharacterFactsTest extends TestCase {

	public function test_counts_distinct_shows_appearing_this_year(): void {
		$show_group = array(
			array(
				'show'    => 10,
				'appears' => array( '2019', '2020' ),
			),
			array(
				'show'    => 20,
				'appears' => array( '2020' ),
			),
			array(
				'show'    => 30,
				'appears' => array( '2018' ), // not this year
			),
		);

		// 2020: shows 10 and 20 → 2 distinct shows this year.
		$this->assertSame( 2, Character_Facts::for_year( $show_group, 2020 )['shows_this_year'] );
	}

	public function test_same_show_two_relationships_counts_once(): void {
		$show_group = array(
			array(
				'show'    => 10,
				'appears' => array( '2020' ),
			),
			array(
				'show'    => 10, // same show, different relationship (e.g. role change)
				'appears' => array( '2020' ),
			),
		);

		$this->assertSame( 1, Character_Facts::for_year( $show_group, 2020 )['shows_this_year'] );
	}

	public function test_debuted_true_when_earliest_appearance_is_this_year(): void {
		$show_group = array(
			array(
				'show'    => 10,
				'appears' => array( '2020', '2021' ),
			),
		);

		$this->assertTrue( Character_Facts::for_year( $show_group, 2020 )['debuted'] );
	}

	public function test_debuted_false_when_they_appeared_in_an_earlier_year(): void {
		$show_group = array(
			array(
				'show'    => 10,
				'appears' => array( '2015' ),
			),
			array(
				'show'    => 20,
				'appears' => array( '2020' ),
			),
		);

		$this->assertFalse( Character_Facts::for_year( $show_group, 2020 )['debuted'] );
	}

	public function test_ignores_invalid_years(): void {
		$show_group = array(
			array(
				'show'    => 10,
				'appears' => array( '', '0', 'n/a', '2020' ),
			),
		);

		$facts = Character_Facts::for_year( $show_group, 2020 );
		$this->assertSame( 1, $facts['shows_this_year'] );
		$this->assertTrue( $facts['debuted'] );
	}

	public function test_empty_show_group(): void {
		$this->assertSame(
			array(
				'shows_this_year' => 0,
				'debuted'         => false,
			),
			Character_Facts::for_year( array(), 2020 )
		);
	}
}
