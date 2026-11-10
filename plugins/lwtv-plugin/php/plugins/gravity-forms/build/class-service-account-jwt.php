<?php
/**
 * Name: Service Account JWT
 * Description: Pure builder for a signed Google service-account JWT assertion.
 *
 * Takes the decoded service-account key, a clock value and a scope, and returns
 * the RS256-signed assertion to exchange for an access token. No WordPress or
 * HTTP calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Account_Jwt {

	const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

	/**
	 * Assertion lifetime in seconds.
	 */
	const LIFETIME = 3600;

	/**
	 * Build a signed JWT.
	 *
	 * @param array  $key   Decoded key JSON: client_email, private_key, optional token_uri.
	 * @param int    $now   Current Unix time.
	 * @param string $scope OAuth scope.
	 * @return string
	 * @throws \UnexpectedValueException If key fields are missing or signing fails.
	 */
	public static function build( array $key, int $now, string $scope ): string {
		$email = isset( $key['client_email'] ) && is_string( $key['client_email'] ) ? $key['client_email'] : '';
		$pem   = isset( $key['private_key'] ) && is_string( $key['private_key'] ) ? $key['private_key'] : '';

		if ( '' === $email || '' === $pem ) {
			throw new \UnexpectedValueException( 'Service account key is missing client_email or private_key.' );
		}

		$token_uri = ! empty( $key['token_uri'] ) && is_string( $key['token_uri'] ) ? $key['token_uri'] : self::DEFAULT_TOKEN_URI;

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$claims = array(
			'iss'   => $email,
			'scope' => $scope,
			'aud'   => $token_uri,
			'iat'   => $now,
			'exp'   => $now + self::LIFETIME,
		);

		$signing_input = self::base64url( (string) json_encode( $header ) ) . '.' . self::base64url( (string) json_encode( $claims ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure class, no WordPress calls.

		$signature = '';
		$private   = @openssl_pkey_get_private( $pem ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a bad key is reported by the exception below.
		if ( false === $private || ! openssl_sign( $signing_input, $signature, $private, OPENSSL_ALGO_SHA256 ) ) {
			throw new \UnexpectedValueException( 'Could not sign the service account assertion with the private key.' );
		}

		return $signing_input . '.' . self::base64url( $signature );
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
