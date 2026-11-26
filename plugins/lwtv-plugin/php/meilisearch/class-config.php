<?php
/**
 * Name: Config
 * Description: Meilisearch connection settings, read from wp-config.php constants.
 *
 * Holds keys in memory only. missing() reports constant NAMES, never values,
 * so it is safe to print.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Config {

	/**
	 * Required constants, in the order missing() reports them.
	 */
	const REQUIRED = array( 'LWTV_MEILI_URL', 'LWTV_MEILI_INDEX_KEY', 'LWTV_MEILI_SEARCH_KEY' );

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
	 * Build from the wp-config.php constants. Undefined constants read as ''.
	 *
	 * @return Config
	 */
	public static function from_constants(): Config {
		$values = array();
		foreach ( array_merge( self::REQUIRED, array( self::PREFIX_CONSTANT ) ) as $name ) {
			$values[] = ( defined( $name ) && is_scalar( constant( $name ) ) ) ? (string) constant( $name ) : '';
		}

		return new self( ...$values );
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
	 * Names of required constants that are empty or undefined.
	 *
	 * @return string[]
	 */
	public function missing(): array {
		$values = array( $this->url, $this->index_key, $this->search_key );
		$names  = array();
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
