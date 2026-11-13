<?php
/**
 * Unit tests for the character export's show-list formatting.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\Export_Rows;

class ExportRowsTest extends TestCase {

	/**
	 * Title lookup stand-in for get_the_title().
	 *
	 * @return callable
	 */
	private function titles(): callable {
		return static function ( $id ) {
			return 'Show ' . $id;
		};
	}

	public function test_formats_each_show_row(): void {
		$rows = array(
			array(
				'show'    => 5,
				'type'    => 'regular',
				'appears' => array( '2019', '2020' ),
			),
			array(
				'show'    => array( 6 ),
				'type'    => 'guest',
				'appears' => array( '2021' ),
			),
		);

		$this->assertSame(
			'Show 5 - regular (2019;2020); Show 6 - guest (2021)',
			Export_Rows::show_list( $rows, $this->titles() )
		);
	}

	public function test_non_array_field_is_empty(): void {
		$this->assertSame( '', Export_Rows::show_list( false, $this->titles() ) );
		$this->assertSame( '', Export_Rows::show_list( '', $this->titles() ) );
		$this->assertSame( '', Export_Rows::show_list( null, $this->titles() ) );
	}

	public function test_skips_rows_without_a_show(): void {
		$rows = array(
			false,
			'junk',
			array( 'type' => 'regular' ),
			array(
				'show' => array(),
				'type' => 'regular',
			),
			array(
				'show'    => 7,
				'type'    => 'recurring',
				'appears' => array( '2001' ),
			),
		);

		$this->assertSame( 'Show 7 - recurring (2001)', Export_Rows::show_list( $rows, $this->titles() ) );
	}

	public function test_missing_or_scalar_appears_and_type(): void {
		$rows = array(
			array( 'show' => 8 ),
			array(
				'show'    => 9,
				'type'    => 'guest',
				'appears' => '1999',
			),
		);

		$this->assertSame( 'Show 8 -  (); Show 9 - guest (1999)', Export_Rows::show_list( $rows, $this->titles() ) );
	}
}
