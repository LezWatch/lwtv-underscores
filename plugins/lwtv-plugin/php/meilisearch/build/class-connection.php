<?php
/**
 * Name: Connection
 * Description: The Meilisearch URL and index prefix: defaults, checks, and where each value comes from.
 *
 * Pure. A define in wp-config.php wins, then a value saved on Search →
 * Settings, then a default worked out from this site: the URL is
 * https://search.{host} and the prefix is the environment type. Only
 * overrides are saved ('' means "use the default"), so a copied database
 * works out its own defaults instead of keeping another site's.
 *
 * The keys are not here: they stay in wp-config.php.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Connection {

	const SOURCE_CONSTANT = 'constant';
	const SOURCE_SAVED    = 'saved';
	const SOURCE_DEFAULT  = 'default';

	/**
	 * Meilisearch index uids allow only these characters.
	 */
	const PREFIX_PATTERN = '/^[a-z0-9_-]{1,40}$/';

	/**
	 * The default URL: https://search. plus this site's host, lowercased,
	 * with a leading www. removed and no port or path.
	 *
	 * @param string $home_url The site's home URL.
	 * @return string '' when it has no host.
	 */
	public static function default_url( string $home_url ): string {
		$host = parse_url( trim( $home_url ), PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure class, no WordPress.
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		$host = strtolower( $host );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return 'https://search.' . $host;
	}

	/**
	 * The default prefix: the environment type, as WordPress reports it.
	 *
	 * @param string $environment wp_get_environment_type().
	 * @return string
	 */
	public static function default_prefix( string $environment ): string {
		return $environment;
	}

	/**
	 * A cleaned URL: trimmed, no trailing slash.
	 *
	 * @param mixed $value Raw input.
	 * @return string|null '' for empty input; null unless it's https (or http on a local host) with a host, and no spaces, credentials, query or fragment. Scheme and host are lowercased.
	 */
	public static function normalize_url( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$url = rtrim( trim( $value ), '/' );
		if ( '' === $url ) {
			return '';
		}

		// The indexing key goes to this URL: no spaces, no credentials in it.
		if ( 1 === preg_match( '/\s/', $url ) ) {
			return null;
		}

		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure class, no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host   = strtolower( $parts['host'] );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && self::is_local_host( $host ) ) ) {
			return null;
		}

		return $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( $parts['path'] ?? '' );
	}

	/**
	 * Hosts that may use plain http: this machine and local development names.
	 *
	 * @param string $host Lowercased host.
	 * @return bool
	 */
	private static function is_local_host( string $host ): bool {
		return in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' ) || str_ends_with( $host, '.localhost' );
	}

	/**
	 * A cleaned prefix: trimmed, lowercased, no trailing underscore.
	 *
	 * @param mixed $value Raw input.
	 * @return string|null '' for empty input, null when it has characters an index uid can't, or is over 40 long.
	 */
	public static function normalize_prefix( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return '';
		}

		$prefix = rtrim( strtolower( $trimmed ), '_' );

		return 1 === preg_match( self::PREFIX_PATTERN, $prefix ) ? $prefix : null;
	}

	/**
	 * Check input from the page. Only `url` and `prefix` are read. A value
	 * equal to its default is stored as '' so it keeps following the default.
	 *
	 * @param array                              $input    Raw input.
	 * @param array{url: string, prefix: string} $defaults This site's defaults.
	 * @return array{values: array<string, string>, errors: array<string, string>}
	 */
	public static function validate( array $input, array $defaults ): array {
		$values = array();
		$errors = array();

		if ( array_key_exists( 'url', $input ) ) {
			$url = self::normalize_url( $input['url'] );
			if ( null === $url ) {
				$errors['url'] = 'The URL must start with https:// (http:// only for a local host), have a host, and have no spaces, user name, password, ? or # part.';
			} else {
				$values['url'] = $url === $defaults['url'] ? '' : $url;
			}
		}

		if ( array_key_exists( 'prefix', $input ) ) {
			$prefix = self::normalize_prefix( $input['prefix'] );
			if ( null === $prefix ) {
				$errors['prefix'] = 'The prefix can use only letters, digits, - and _, up to 40 characters.';
			} else {
				$values['prefix'] = $prefix === $defaults['prefix'] ? '' : $prefix;
			}
		}

		if ( ! empty( $errors ) ) {
			$values = array();
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * The URL and prefix in effect, and where each comes from.
	 *
	 * @param array{url: ?string, prefix: ?string} $constants The defines' values; null when not defined.
	 * @param array{url: string, prefix: string}   $saved     The saved overrides ('' = none).
	 * @param array{url: string, prefix: string}   $defaults  This site's defaults.
	 * @return array{url: array{value: string, source: string, default: string}, prefix: array{value: string, source: string, default: string}}
	 */
	public static function resolve( array $constants, array $saved, array $defaults ): array {
		$out = array();
		foreach ( array( 'url', 'prefix' ) as $field ) {
			$constant = $constants[ $field ] ?? null;
			$stored   = (string) ( $saved[ $field ] ?? '' );
			$default  = (string) ( $defaults[ $field ] ?? '' );

			if ( null !== $constant ) {
				// A define is used as given, apart from the same trimming the Config does.
				$value  = 'url' === $field ? rtrim( trim( $constant ), '/' ) : rtrim( trim( $constant ), '_' );
				$source = self::SOURCE_CONSTANT;
			} elseif ( '' !== $stored ) {
				$value  = $stored;
				$source = self::SOURCE_SAVED;
			} else {
				$value  = $default;
				$source = self::SOURCE_DEFAULT;
			}

			$out[ $field ] = array(
				'value'   => $value,
				'source'  => $source,
				'default' => $default,
			);
		}

		return $out;
	}
}
