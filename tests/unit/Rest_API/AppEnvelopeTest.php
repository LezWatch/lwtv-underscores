<?php
/**
 * Unit tests for the app catalogue response envelope.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\App_Envelope;

class AppEnvelopeTest extends TestCase {

	public function test_wrap_puts_envelope_first(): void {
		$wrapped = App_Envelope::wrap( array( 'shows' => array() ), 1795737600 );

		$this->assertSame(
			array( 'schema_version', 'generated_at', 'data_version', 'shows' ),
			array_keys( $wrapped )
		);
		$this->assertSame( 1, $wrapped['schema_version'] );
		$this->assertSame( '2026-11-27T00:00:00Z', $wrapped['generated_at'] );
	}

	public function test_data_version_ignores_generated_at(): void {
		$body = array( 'shows' => array( 1, 2, 3 ) );

		$this->assertSame(
			App_Envelope::wrap( $body, 1795737600 )['data_version'],
			App_Envelope::wrap( $body, 1795824000 )['data_version']
		);
	}

	public function test_data_version_changes_with_body(): void {
		$one = App_Envelope::data_version( array( 'a' => 1 ) );
		$two = App_Envelope::data_version( array( 'a' => 2 ) );

		$this->assertNotSame( $one, $two );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $one );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{12}$/', $two );
	}

	public function test_data_version_changes_with_invalid_utf8_body(): void {
		$one = App_Envelope::data_version( array( 'a' => "x\xB1" ) );
		$two = App_Envelope::data_version( array( 'a' => "y\xB1" ) );

		$this->assertNotSame( $one, $two );
	}

	public function test_plain_text_decodes_and_strips(): void {
		$this->assertSame(
			'Tom & Jerry’s Show',
			App_Envelope::plain_text( 'Tom &amp; Jerry&#8217;s <em>Show</em> ' )
		);
	}

	public function test_empty_dict_encodes_as_object(): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure test, no WordPress.
		$this->assertSame( '{}', json_encode( App_Envelope::dict( array() ) ) );
		$this->assertSame(
			'{"101":{"slug":"x"}}',
			json_encode( App_Envelope::dict( array( 101 => array( 'slug' => 'x' ) ) ) )
		);
		// phpcs:enable WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
