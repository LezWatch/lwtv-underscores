<?php
/**
 * Unit tests for the documented /stats/years/ shape.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\Death_Years;

class DeathYearsTest extends TestCase {

	public function test_keyed_newest_first_with_zero_years_filled(): void {
		$rows = array(
			array(
				'death_year'  => 2001,
				'death_count' => 3,
			),
			array(
				'death_year'  => 2003,
				'death_count' => 1,
			),
		);

		$this->assertSame(
			array(
				2003 => array(
					'name'  => 2003,
					'count' => 1,
					'url'   => 'https://example.test/this-year/2003/',
				),
				2002 => array(
					'name'  => 2002,
					'count' => 0,
					'url'   => 'https://example.test/this-year/2002/',
				),
				2001 => array(
					'name'  => 2001,
					'count' => 3,
					'url'   => 'https://example.test/this-year/2001/',
				),
			),
			Death_Years::keyed( $rows, 2001, 2003, 'https://example.test/this-year/' )
		);
	}

	public function test_ignores_out_of_range_and_malformed_rows(): void {
		$rows = array(
			array(
				'death_year'  => 1950,
				'death_count' => 9,
			),
			array( 'death_year' => 2001 ),
			'junk',
			array(
				'death_year'  => '2002',
				'death_count' => '4',
			),
		);

		$result = Death_Years::keyed( $rows, 2001, 2002, 'https://example.test/this-year' );

		$this->assertSame( array( 2002, 2001 ), array_keys( $result ) );
		$this->assertSame( 4, $result[2002]['count'] );
		$this->assertSame( 0, $result[2001]['count'] );
		$this->assertSame( 'https://example.test/this-year/2001/', $result[2001]['url'] );
	}

	public function test_duplicate_years_are_summed(): void {
		$rows = array(
			array(
				'death_year'  => 2001,
				'death_count' => 2,
			),
			array(
				'death_year'  => 2001,
				'death_count' => 5,
			),
		);

		$this->assertSame( 7, Death_Years::keyed( $rows, 2001, 2001, 'https://example.test/this-year/' )[2001]['count'] );
	}
}
