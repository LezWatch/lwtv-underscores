<?php
/**
 * Name: Modal Config
 * Description: Shapes the browser config for the Meilisearch search modal.
 *
 * Pure. Only the search key goes in: build() never receives the indexing
 * key, and the top-level keys are fixed, so nothing else can slip into the
 * page. Numbers stay ints so wp_json_encode prints them as JSON numbers.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Modal_Config {

	/**
	 * Tab order. 'content' is shown as Posts.
	 */
	const TABS = array( 'shows', 'characters', 'actors', 'content' );

	/**
	 * Results per tab.
	 */
	const LIMIT = 10;

	/**
	 * Pause in typing before a search, in ms.
	 */
	const DEBOUNCE = 150;

	/**
	 * How long to wait for Meilisearch before falling back, in ms.
	 */
	const TIMEOUT = 2000;

	/**
	 * Per-type string groups folded into each tab.
	 */
	const TAB_STRINGS = array( 'labels', 'one', 'many' );

	/**
	 * The server's origin (scheme, host, port), for a preconnect hint.
	 *
	 * @param string $url Meilisearch URL.
	 * @return string '' unless it's an http(s) URL with a host.
	 */
	public static function origin( string $url ): string {
		$parts = parse_url( trim( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure class, no WordPress in unit tests.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] ) . '://' . $parts['host'];

		return isset( $parts['port'] ) ? $origin . ':' . (int) $parts['port'] : $origin;
	}

	/**
	 * Build the config.
	 *
	 * @param string                $url         Meilisearch URL.
	 * @param string                $search_key  Public search-only key.
	 * @param array<string, string> $uids        Index uid per type.
	 * @param string                $search_page Full results page URL.
	 * @param array<string, mixed>  $strings     labels/one/many per type, plus flat strings.
	 * @return array<string, mixed>
	 */
	public static function build( string $url, string $search_key, array $uids, string $search_page, array $strings ): array {
		$tabs = array();
		foreach ( self::TABS as $type ) {
			if ( empty( $uids[ $type ] ) ) {
				continue;
			}
			$tabs[] = array(
				'type'  => $type,
				'uid'   => (string) $uids[ $type ],
				'label' => (string) ( $strings['labels'][ $type ] ?? $type ),
				'one'   => (string) ( $strings['one'][ $type ] ?? '%d' ),
				'many'  => (string) ( $strings['many'][ $type ] ?? '%d' ),
			);
		}

		$flat = array();
		foreach ( $strings as $key => $value ) {
			if ( in_array( $key, self::TAB_STRINGS, true ) || ! is_string( $value ) ) {
				continue;
			}
			$flat[ $key ] = $value;
		}

		return array(
			'url'        => rtrim( trim( $url ), '/' ),
			'key'        => $search_key,
			'searchPage' => $search_page,
			'limit'      => self::LIMIT,
			'debounce'   => self::DEBOUNCE,
			'timeout'    => self::TIMEOUT,
			'tabs'       => $tabs,
			'strings'    => $flat,
		);
	}
}
