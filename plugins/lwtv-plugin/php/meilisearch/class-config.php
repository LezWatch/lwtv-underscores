<?php
/**
 * Name: Config
 * Description: Meilisearch connection settings: the keys from wp-config.php, the URL and prefix resolved by Build\Connection.
 *
 * load() takes the keys from their constants only. The URL and the index
 * prefix come from their define, else a value saved on Search → Settings,
 * else this site's default (Build\Connection). Holds keys in memory only. missing() reports constant NAMES, never values,
 * so it is safe to print.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

use LWTV\Meilisearch\Build\Connection;
use LWTV\Meilisearch\Build\Search_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Config {

	/**
	 * Required constants, in the order missing() reports them. The URL isn't
	 * one: it always has a default.
	 */
	const REQUIRED = array( 'LWTV_MEILI_INDEX_KEY', 'LWTV_MEILI_SEARCH_KEY' );

	/**
	 * Optional define for the server URL.
	 */
	const URL_CONSTANT = 'LWTV_MEILI_URL';

	/**
	 * Optional per-environment prefix for every index uid.
	 */
	const PREFIX_CONSTANT = 'LWTV_MEILI_INDEX_PREFIX';

	private string $url;
	private string $index_key;
	private string $search_key;
	private string $prefix;

	/**
	 * @param string $url        Server URL.
	 * @param string $index_key  Server-side indexing key.
	 * @param string $search_key Public, search-only key.
	 * @param string $prefix     Index uid prefix, e.g. 'local', 'staging', 'production'.
	 */
	public function __construct( string $url, string $index_key, string $search_key, string $prefix = '' ) {
		$this->url        = rtrim( trim( $url ), '/' );
		$this->index_key  = trim( $index_key );
		$this->search_key = trim( $search_key );
		$this->prefix     = rtrim( trim( $prefix ), '_' );
	}

	/**
	 * Build for this site: keys from their constants, URL and prefix resolved.
	 *
	 * @return Config
	 */
	public static function load(): Config {
		$connection = self::connection();

		return new self(
			$connection['url']['value'],
			self::constant_value( 'LWTV_MEILI_INDEX_KEY' ) ?? '',
			self::constant_value( 'LWTV_MEILI_SEARCH_KEY' ) ?? '',
			$connection['prefix']['value']
		);
	}

	/**
	 * The URL and prefix in effect, where each comes from, and the defaults.
	 * Never holds a key.
	 *
	 * @return array{url: array{value: string, source: string, default: string}, prefix: array{value: string, source: string, default: string}}
	 */
	public static function connection(): array {
		$saved = Search_Settings::read( get_option( Search_Settings_Store::OPTION ) );

		return Connection::resolve(
			array(
				'url'    => self::constant_value( self::URL_CONSTANT ),
				'prefix' => self::constant_value( self::PREFIX_CONSTANT ),
			),
			array(
				'url'    => $saved['url'],
				'prefix' => $saved['prefix'],
			),
			self::defaults()
		);
	}

	/**
	 * This site's default URL and prefix.
	 *
	 * @return array{url: string, prefix: string}
	 */
	public static function defaults(): array {
		return array(
			'url'    => Connection::default_url( (string) home_url() ),
			'prefix' => Connection::default_prefix( wp_get_environment_type() ),
		);
	}

	/**
	 * A constant's value as text, or null when it isn't defined (or isn't scalar).
	 *
	 * @param string $name Constant name.
	 * @return string|null
	 */
	private static function constant_value( string $name ): ?string {
		return ( defined( $name ) && is_scalar( constant( $name ) ) ) ? (string) constant( $name ) : null;
	}

	/**
	 * True when the URL and both keys are set.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return empty( $this->missing() );
	}

	/**
	 * Names of required constants that are empty or undefined. The URL is
	 * listed only when it resolved to '' (no define, nothing saved, and no
	 * host to build a default from).
	 *
	 * @return string[]
	 */
	public function missing(): array {
		$names = '' === $this->url ? array( self::URL_CONSTANT ) : array();

		$values = array( $this->index_key, $this->search_key );
		foreach ( self::REQUIRED as $i => $name ) {
			if ( '' === $values[ $i ] ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Server URL, without a trailing slash.
	 *
	 * @return string
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Server host, for CLI banners.
	 *
	 * @return string
	 */
	public function host(): string {
		return (string) wp_parse_url( $this->url, PHP_URL_HOST );
	}

	/**
	 * Index uid for a content type: '{prefix}_{type}', or '{type}' with no prefix.
	 *
	 * @param string $type Content type, e.g. 'characters'.
	 * @return string
	 */
	public function index( string $type ): string {
		return '' === $this->prefix ? $type : $this->prefix . '_' . $type;
	}

	/**
	 * Server-side indexing key. Never print or log it.
	 *
	 * @return string
	 */
	public function index_key(): string {
		return $this->index_key;
	}

	/**
	 * Public search-only key, safe for the browser.
	 *
	 * @return string
	 */
	public function search_key(): string {
		return $this->search_key;
	}

	/**
	 * True when the search key is set and isn't the indexing key, so it's
	 * safe to print into a page. Compares in memory; never print either.
	 *
	 * @return bool
	 */
	public function search_key_is_public(): bool {
		return '' !== $this->search_key && ! hash_equals( $this->index_key, $this->search_key );
	}
}
