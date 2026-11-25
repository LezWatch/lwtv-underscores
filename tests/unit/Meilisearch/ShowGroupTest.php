<?php
/**
 * Unit tests for normalizing raw ACF show-group repeater rows.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Show_Group;

class ShowGroupTest extends TestCase {

	public function test_rows_keyed_by_field_key(): void {
		$this->assertSame(
			array(
				array(
					'show' => 187,
					'type' => 'regular',
				),
			),
			Show_Group::normalize(
				array(
					array(
						'field_lwtv_lezchars_show_group_show'    => '187',
						'field_lwtv_lezchars_show_group_type'    => 'regular',
						'field_lwtv_lezchars_show_group_appears' => array( '2001' ),
					),
				)
			)
		);
	}

	public function test_rows_keyed_by_name(): void {
		$this->assertSame(
			array(
				array(
					'show' => 5,
					'type' => 'guest',
				),
			),
			Show_Group::normalize(
				array(
					array(
						'show' => 5,
						'type' => 'guest',
					),
				)
			)
		);
	}

	public function test_array_valued_show_uses_first_id(): void {
		$this->assertSame(
			array(
				array(
					'show' => 9,
					'type' => '',
				),
			),
			Show_Group::normalize( array( array( 'show' => array( '9', '10' ) ) ) )
		);
	}

	public function test_zero_missing_or_junk_rows_skipped(): void {
		$this->assertSame(
			array(),
			Show_Group::normalize( array( array( 'show' => 0 ), array( 'type' => 'regular' ), 'junk', array( 'show' => '' ) ) )
		);
	}

	public function test_non_array_input_is_empty(): void {
		$this->assertSame( array(), Show_Group::normalize( false ) );
		$this->assertSame( array(), Show_Group::normalize( '' ) );
	}

	public function test_non_string_type_becomes_empty(): void {
		$this->assertSame(
			array(
				array(
					'show' => 5,
					'type' => '',
				),
			),
			Show_Group::normalize(
				array(
					array(
						'show' => 5,
						'type' => array( 'x' ),
					),
				)
			)
		);
	}
}
