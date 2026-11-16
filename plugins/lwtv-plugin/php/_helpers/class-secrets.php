<?php
/**
 * Name: Secrets
 * Description: Read and write the site's credentials through the WordPress 7.2
 * Secrets API, falling back to the old wp-config constants and options.
 *
 * Every credential read in the plugin goes through Secrets::get(). It asks the
 * store first; if the store has nothing (or WordPress is older than 7.2) it
 * reads the constant, file, or option the credential used to live in. Which
 * credentials exist, and the resolution rules, live in Secret_Registry.
 *
 * No capability checks here, matching core: enforce them at the edge. The only
 * writer is `wp lwtv secrets` (WP-CLI), where shell access is the gate.
 *
 * See docs/operations/secrets.md.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secrets {

	/**
	 * Resolved values for this request, keyed by short name.
	 *
	 * Holds plaintext for the life of the request, the same exposure the
	 * wp-config constants had. TMDB backfills read the key thousands of times.
	 *
	 * @var array<string, array{value: string, source: string}>
	 */
	private static $resolved = array();

	/**
	 * Short names already reported to the error log this request.
	 *
	 * @var array<string, bool>
	 */
	private static $reported = array();

	/**
	 * Is the Secrets API loaded?
	 *
	 * @return bool
	 */
	public static function api_available(): bool {
		return function_exists( 'wp_get_secret' ) && function_exists( 'wp_set_secret' );
	}

	/**
	 * The credential's value, or '' when it is not configured anywhere.
	 *
	 * @param string $name Short name from Secret_Registry.
	 * @return string
	 */
	public static function get( string $name ): string {
		return self::lookup( $name )['value'];
	}

	/**
	 * Where the value came from: secret, constant, file, option, *-after-error, error, or none.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function source( string $name ): string {
		return self::lookup( $name )['source'];
	}

	/**
	 * Is the credential configured anywhere?
	 *
	 * @param string $name Short name.
	 * @return bool
	 */
	public static function has( string $name ): bool {
		return '' !== self::get( $name );
	}

	/**
	 * Read straight from the store, without the fallback.
	 *
	 * @param string $name Short name.
	 * @return array{state: string, value: string, error: ?\WP_Error}
	 */
	public static function from_store( string $name ): array {
		if ( ! self::api_available() ) {
			return array(
				'state' => Secret_Registry::STATE_UNAVAILABLE,
				'value' => '',
				'error' => null,
			);
		}

		self::forget_cached_row( $name );

		$secret = wp_get_secret( Secret_Registry::full_name( $name ) );

		if ( null === $secret ) {
			return array(
				'state' => Secret_Registry::STATE_MISSING,
				'value' => '',
				'error' => null,
			);
		}

		$value = is_wp_error( $secret ) ? $secret : $secret->reveal();

		if ( is_wp_error( $value ) ) {
			return array(
				'state' => Secret_Registry::STATE_ERROR,
				'value' => '',
				'error' => $value,
			);
		}

		return array(
			'state' => Secret_Registry::STATE_FOUND,
			'value' => (string) $value,
			'error' => null,
		);
	}

	/**
	 * The fallback value: the old constant, the file it points to, or the old option.
	 *
	 * @param string $name Short name.
	 * @return string '' when nothing is there.
	 */
	public static function fallback_value( string $name ): string {
		$fallback = Secret_Registry::fallback( $name );

		switch ( $fallback['type'] ) {
			case 'constant':
				return ( defined( $fallback['name'] ) && is_string( constant( $fallback['name'] ) ) ) ? trim( (string) constant( $fallback['name'] ) ) : '';

			case 'file':
				$path = ( defined( $fallback['name'] ) && is_string( constant( $fallback['name'] ) ) ) ? (string) constant( $fallback['name'] ) : '';
				if ( '' === $path || ! is_readable( $path ) ) {
					return '';
				}
				$raw = file_get_contents( $path );
				return is_string( $raw ) ? trim( $raw ) : '';

			case 'option':
				$value = get_option( $fallback['name'], '' );
				return is_string( $value ) ? trim( $value ) : '';
		}

		return '';
	}

	/**
	 * Store a value. Validates first; never echoes the value in an error.
	 *
	 * @param string $name  Short name.
	 * @param string $value Plaintext.
	 * @return true|\WP_Error
	 */
	public static function set( string $name, string $value ) {
		if ( ! self::api_available() ) {
			return new \WP_Error( 'lwtv_secrets_unavailable', 'The Secrets API is not available. It needs WordPress 7.2 or later.' );
		}

		$checked = Secret_Registry::validate( $name, $value );
		if ( '' !== $checked['error'] ) {
			return new \WP_Error( 'lwtv_secret_invalid', $checked['error'] );
		}

		self::forget_cached_row( $name );
		$result = wp_set_secret( Secret_Registry::full_name( $name ), $checked['value'] );
		self::flush( $name );

		return $result;
	}

	/**
	 * Drop this request's resolved copy, so the next get() reads again.
	 *
	 * @param string $name Short name, or '' for all.
	 * @return void
	 */
	public static function flush( string $name = '' ): void {
		if ( '' === $name ) {
			self::$resolved = array();
		} else {
			unset( self::$resolved[ $name ] );
		}
	}

	/**
	 * Resolve once per request.
	 *
	 * @param string $name Short name.
	 * @return array{value: string, source: string}
	 */
	private static function lookup( string $name ): array {
		if ( isset( self::$resolved[ $name ] ) ) {
			return self::$resolved[ $name ];
		}

		if ( ! Secret_Registry::is_known( $name ) ) {
			self::report( $name, 'Unknown secret name requested.' );
			return array(
				'value'  => '',
				'source' => 'none',
			);
		}

		$stored   = self::from_store( $name );
		$resolved = Secret_Registry::resolve(
			$stored['state'],
			$stored['value'],
			self::fallback_value( $name ),
			Secret_Registry::fallback( $name )['type']
		);

		if ( null !== $stored['error'] ) {
			self::report(
				$name,
				sprintf(
					'The stored secret could not be read (%s: %s). %s',
					$stored['error']->get_error_code(),
					$stored['error']->get_error_message(),
					'error' === $resolved['source'] ? 'No fallback is configured.' : 'Using the fallback.'
				)
			);
		}

		self::$resolved[ $name ] = $resolved;
		return $resolved;
	}

	/**
	 * Drop any object-cache copy of core's option row for this secret.
	 *
	 * Core's default store keeps each secret in a non-autoloaded option. Web
	 * requests cache those in Redis; WP-CLI does not load the object cache. So
	 * a `wp lwtv secrets set` from the shell reaches the database but not the
	 * copy (or the `notoptions` "doesn't exist" record) web requests read.
	 * Same problem, same fix as Uncached_Option. See
	 * docs/architecture/caching.md#cli-and-web-cache-tiers.
	 *
	 * Gated on the store actually in use, not on whether a secrets.php drop-in
	 * is present: a drop-in that only swaps the keyring still stores in options.
	 *
	 * @param string $name Short name.
	 * @return void
	 */
	private static function forget_cached_row( string $name ): void {
		if ( ! function_exists( '_wp_secrets_get_store' ) || ! ( _wp_secrets_get_store() instanceof \WP_Secrets_Option_Store ) ) {
			return;
		}

		Uncached_Option::forget( \WP_Secrets_Option_Store::SITE_PREFIX . Secret_Registry::full_name( $name ) );
	}

	/**
	 * Log a problem at most once an hour per name. Messages carry no values.
	 *
	 * A lost WP_SECRETS_KEY would otherwise log on every web request for every
	 * credential it touches. The in-request flag saves a transient read on repeats.
	 *
	 * @param string $name    Short name.
	 * @param string $message Message.
	 * @return void
	 */
	private static function report( string $name, string $message ): void {
		if ( isset( self::$reported[ $name ] ) ) {
			return;
		}
		self::$reported[ $name ] = true;

		$throttle = 'lwtv_secret_reported_' . md5( $name );
		if ( get_transient( $throttle ) ) {
			return;
		}
		set_transient( $throttle, 1, HOUR_IN_SECONDS );

		if ( function_exists( 'lwtv_plugin' ) ) {
			lwtv_plugin()->error_log( 'secrets', Secret_Registry::full_name( $name ) . ': ' . $message );
		}
	}
}
