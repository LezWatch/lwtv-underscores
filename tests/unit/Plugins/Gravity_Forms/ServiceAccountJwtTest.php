<?php
/**
 * Unit tests for the Google service-account JWT builder.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Service_Account_Jwt;

class ServiceAccountJwtTest extends TestCase {

	/**
	 * Decode a base64url segment.
	 *
	 * @param string $segment Segment.
	 * @return array
	 */
	private function decode( string $segment ): array {
		return json_decode( base64_decode( strtr( $segment, '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	public function test_builds_verifiable_rs256_jwt(): void {
		$res = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		openssl_pkey_export( $res, $pem );
		$public = openssl_pkey_get_details( $res )['key'];

		$jwt   = Service_Account_Jwt::build(
			array(
				'client_email' => 'bot@example.iam.gserviceaccount.com',
				'private_key'  => $pem,
			),
			1700000000,
			'https://www.googleapis.com/auth/spreadsheets'
		);
		$parts = explode( '.', $jwt );

		$this->assertCount( 3, $parts );
		$this->assertStringNotContainsString( '=', $jwt );

		$header = $this->decode( $parts[0] );
		$claims = $this->decode( $parts[1] );
		$this->assertSame( 'RS256', $header['alg'] );
		$this->assertSame( 'JWT', $header['typ'] );
		$this->assertSame( 'bot@example.iam.gserviceaccount.com', $claims['iss'] );
		$this->assertSame( 'https://oauth2.googleapis.com/token', $claims['aud'] );
		$this->assertSame( 'https://www.googleapis.com/auth/spreadsheets', $claims['scope'] );
		$this->assertSame( 1700000000, $claims['iat'] );
		$this->assertSame( 1700003600, $claims['exp'] );

		$sig = base64_decode( strtr( $parts[2], '-_', '+/' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$this->assertSame( 1, openssl_verify( $parts[0] . '.' . $parts[1], $sig, $public, OPENSSL_ALGO_SHA256 ) );
	}

	public function test_custom_token_uri_is_the_audience(): void {
		$res = openssl_pkey_new( array( 'private_key_bits' => 2048 ) );
		openssl_pkey_export( $res, $pem );
		$jwt    = Service_Account_Jwt::build(
			array(
				'client_email' => 'a@b.c',
				'private_key'  => $pem,
				'token_uri'    => 'https://example.test/token',
			),
			1,
			's'
		);
		$claims = $this->decode( explode( '.', $jwt )[1] );
		$this->assertSame( 'https://example.test/token', $claims['aud'] );
	}

	public function test_throws_without_private_key(): void {
		$this->expectException( \UnexpectedValueException::class );
		Service_Account_Jwt::build( array( 'client_email' => 'a@b.c' ), 1, 's' );
	}

	public function test_throws_on_unusable_private_key(): void {
		$this->expectException( \UnexpectedValueException::class );
		Service_Account_Jwt::build(
			array(
				'client_email' => 'a@b.c',
				'private_key'  => 'not a key',
			),
			1,
			's'
		);
	}
}
