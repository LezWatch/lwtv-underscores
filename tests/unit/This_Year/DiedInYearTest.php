<?php
/**
 * Unit tests for the "died this year" predicate shared by the This Year dead
 * count and the /this-year/dead/{year}/ list.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Characters_Builder;

class DiedInYearTest extends TestCase {

	/**
	 * Character row in the get_characters_for_year() shape.
	 *
	 * @param string $last_death  Last death date.
	 * @param array  $death_years Death dates.
	 *
	 * @return array
	 */
	private function character( string $last_death, array $death_years = array() ): array {
		return array(
			'slug'        => 'x',
			'name'        => 'X',
			'dead'        => '' !== $last_death || array() !== $death_years,
			'death_years' => $death_years,
			'last_death'  => $last_death,
		);
	}

	public function test_last_death_in_year(): void {
		$this->assertTrue( Characters_Builder::died_in_year( $this->character( '2016-03-03', array( '2016-03-03' ) ), 2016 ) );
		$this->assertTrue( Characters_Builder::died_in_year( $this->character( '20160303' ), 2016 ) );
	}

	public function test_dead_before_the_year_is_not_counted(): void {
		$this->assertFalse( Characters_Builder::died_in_year( $this->character( '2015-10-28', array( '2015-10-28' ) ), 2016 ) );
	}

	public function test_alive_is_not_counted(): void {
		$this->assertFalse( Characters_Builder::died_in_year( $this->character( '' ), 2016 ) );
	}

	public function test_falls_back_to_death_years_without_last_death(): void {
		$this->assertTrue( Characters_Builder::died_in_year( $this->character( '', array( '2014-01-01', '2016-05-05' ) ), 2016 ) );
		$this->assertTrue( Characters_Builder::died_in_year( $this->character( '', array( '20160505' ) ), 2016 ) );
		$this->assertFalse( Characters_Builder::died_in_year( $this->character( '', array( '2014-01-01' ) ), 2016 ) );
	}

	public function test_last_death_wins_over_earlier_death_years(): void {
		// Died in 2016, came back, died again in 2020: counted in 2020 only.
		$this->assertFalse( Characters_Builder::died_in_year( $this->character( '2020-01-01', array( '2016-05-05', '2020-01-01' ) ), 2016 ) );
	}
}
