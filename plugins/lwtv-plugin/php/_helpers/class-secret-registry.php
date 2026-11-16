<?php
/**
 * Name: Secret Registry
 * Description: Which credentials the site stores, where each one used to live,
 * and the rules for resolving and validating them.
 *
 * Pure: no WordPress calls, so it is unit-tested directly. The WordPress side
 * (_Helpers\Secrets) gathers the lookup result and the fallback value and
 * hands them to resolve(). See docs/operations/secrets.md.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secret_Registry {

	/**
	 * Namespace for every secret this site stores ('lwtv/<name>').
	 */
	const PREFIX = 'lwtv/';

	/**
	 * Lookup states handed to resolve().
	 */
	const STATE_FOUND       = 'found';       // The store returned a value.
	const STATE_MISSING     = 'missing';     // The store has no such secret.
	const STATE_ERROR       = 'error';       // The store has it but could not decrypt or release it.
	const STATE_UNAVAILABLE = 'unavailable'; // No Secrets API (WordPress before 7.2).

	/**
	 * Every credential, keyed by short name.
	 *
	 * fallback: where the value lived before the Secrets API, and where it is
	 *           still read from when the store has nothing.
	 *           - constant: a wp-config.php constant holding the value.
	 *           - file:     a wp-config.php constant holding a path to a file whose contents are the value.
	 *           - option:   a wp_options row.
	 * legacy:   option rows holding plaintext copies, deleted by `wp lwtv secrets import`.
 * legacy_array: array{option, key} for a copy held as one key inside an array option.
	 * format:   token (one line, no whitespace), url (https), or service-account (Google JSON key).
	 */
	const SECRETS = array(
		'postiz-api-key'         => array(
			'label'        => 'Postiz API key',
			'fallback'     => array(
				'type' => 'option',
				'name' => 'options_lwtv_postiz_api_key',
			),
			'legacy'       => array( 'options_lwtv_postiz_api_key', '_options_lwtv_postiz_api_key', 'lwtv_postiz_api_key', '_lwtv_postiz_api_key' ),
			// The pre-ACF CMB2 options array, still autoloaded, still holding the key.
			'legacy_array' => array(
				'option' => 'lwtv_auto_posting_options',
				'key'    => 'lwtv_postiz_api_key',
			),
			'format'       => 'token',
		),
		'tmdb-api-key'           => array(
			'label'    => 'TMDB API key',
			'fallback' => array(
				'type' => 'constant',
				'name' => 'TMDB_API',
			),
			'legacy'   => array(),
			'format'   => 'token',
		),
		'gemini-api-key'         => array(
			'label'    => 'Gemini API key',
			'fallback' => array(
				'type' => 'constant',
				'name' => 'LWTV_GEMINI_API_KEY',
			),
			'legacy'   => array(),
			'format'   => 'token',
		),
		'google-service-account' => array(
			'label'    => 'Google service account key (JSON)',
			'fallback' => array(
				'type' => 'file',
				'name' => 'LWTV_GOOGLE_SERVICE_ACCOUNT',
			),
			'legacy'   => array(),
			'format'   => 'service-account',
		),
		'tvmaze-ical-url'        => array(
			'label'    => 'TVMaze followed-shows iCal URL (carries a personal token)',
			'fallback' => array(
				'type' => 'constant',
				'name' => 'TV_MAZE',
			),
			'legacy'   => array(),
			'format'   => 'url',
		),
	);

	/**
	 * Short names, in registry order.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::SECRETS );
	}

	/**
	 * Is this a short name the registry knows?
	 *
	 * @param string $name Short name.
	 * @return bool
	 */
	public static function is_known( string $name ): bool {
		return isset( self::SECRETS[ $name ] );
	}

	/**
	 * The namespaced name the store uses.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function full_name( string $name ): string {
		return self::PREFIX . $name;
	}

	/**
	 * Human label.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function label( string $name ): string {
		return self::SECRETS[ $name ]['label'] ?? $name;
	}

	/**
	 * Where the value lived before, as array{type, name}.
	 *
	 * @param string $name Short name.
	 * @return array{type: string, name: string}
	 */
	public static function fallback( string $name ): array {
		return self::SECRETS[ $name ]['fallback'] ?? array(
			'type' => 'none',
			'name' => '',
		);
	}

	/**
	 * Option rows holding plaintext copies that import deletes.
	 *
	 * @param string $name Short name.
	 * @return string[]
	 */
	public static function legacy_options( string $name ): array {
		return self::SECRETS[ $name ]['legacy'] ?? array();
	}

	/**
	 * A plaintext copy kept as one key inside an array option, or null.
	 *
	 * @param string $name Short name.
	 * @return array{option: string, key: string}|null
	 */
	public static function legacy_array( string $name ): ?array {
		return self::SECRETS[ $name ]['legacy_array'] ?? null;
	}

	/**
	 * Turn a store lookup and a fallback into the value to use.
	 *
	 * The store wins whenever it has a non-empty value. Otherwise the fallback
	 * is used, so a site without the Secrets API, or one whose WP_SECRETS_KEY
	 * is missing, keeps working on its wp-config constants. An unreadable
	 * secret is never silent: the source says so, and the caller logs it.
	 *
	 * @param string $state           One of the STATE_* constants.
	 * @param string $secret_value    The revealed value (only meaningful when found).
	 * @param string $fallback_value  The fallback value, '' if none.
	 * @param string $fallback_source What the fallback is: constant, file, or option.
	 * @return array{value: string, source: string} source: secret, <fallback_source>, <fallback_source>-after-error, error, or none.
	 */
	public static function resolve( string $state, string $secret_value, string $fallback_value, string $fallback_source ): array {
		if ( self::STATE_FOUND === $state && '' !== $secret_value ) {
			return array(
				'value'  => $secret_value,
				'source' => 'secret',
			);
		}

		$errored = ( self::STATE_ERROR === $state );

		if ( '' !== $fallback_value ) {
			return array(
				'value'  => $fallback_value,
				'source' => $errored ? $fallback_source . '-after-error' : $fallback_source,
			);
		}

		return array(
			'value'  => '',
			'source' => $errored ? 'error' : 'none',
		);
	}

	/**
	 * Check and normalise a value before it is stored.
	 *
	 * Error messages describe the problem, never the value.
	 *
	 * @param string $name  Short name.
	 * @param string $value Candidate value, as read from stdin or a file.
	 * @return array{value: string, error: string} error is '' when the value is acceptable.
	 */
	public static function validate( string $name, string $value ): array {
		if ( ! self::is_known( $name ) ) {
			return self::invalid( 'Unknown secret name. Known names: ' . implode( ', ', self::names() ) . '.' );
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return self::invalid( 'The value is empty.' );
		}

		switch ( self::SECRETS[ $name ]['format'] ) {
			case 'service-account':
				$key = json_decode( $value, true );
				if ( ! is_array( $key ) ) {
					return self::invalid( 'The value is not JSON. Pass the contents of the service-account key file, not its path.' );
				}
				if ( 'service_account' !== ( $key['type'] ?? '' ) ) {
					return self::invalid( 'The JSON is not a service-account key ("type" is not "service_account").' );
				}
				foreach ( array( 'client_email', 'private_key' ) as $field ) {
					if ( empty( $key[ $field ] ) || ! is_string( $key[ $field ] ) ) {
						return self::invalid( 'The service-account key has no "' . $field . '".' );
					}
				}
				break;

			case 'url':
				if ( ! preg_match( '#^https://[^\s/]+/\S*$#', $value ) ) {
					return self::invalid( 'The value is not a single https:// URL.' );
				}
				break;

			default:
				if ( preg_match( '/\s/', $value ) ) {
					return self::invalid( 'The value contains whitespace. Paste the key alone, on one line.' );
				}
		}

		return array(
			'value' => $value,
			'error' => '',
		);
	}

	/**
	 * Replace each non-empty value in a message with [redacted].
	 *
	 * @param string   $message Message.
	 * @param string[] $values  Values to scrub.
	 * @return string
	 */
	public static function redact( string $message, array $values ): string {
		foreach ( $values as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$message = str_replace( $value, '[redacted]', $message );
			}
		}
		return $message;
	}

	/**
	 * A failed validation result.
	 *
	 * @param string $error Message.
	 * @return array{value: string, error: string}
	 */
	private static function invalid( string $error ): array {
		return array(
			'value' => '',
			'error' => $error,
		);
	}
}
