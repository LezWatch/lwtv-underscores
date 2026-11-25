<?php
/**
 * Name: Index Settings
 * Description: Loads and validates an index's versioned settings file.
 *
 * Pure: JSON in, settings payload out. The payload is the body for
 * PATCH /indexes/{uid}/settings, checked against the Meilisearch v1.57 docs:
 * https://www.meilisearch.com/docs/reference/api/settings/update-all-settings
 * https://www.meilisearch.com/docs/learn/relevancy/ranking_rules
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Index_Settings {

	/**
	 * Primary key, set when the index is created.
	 */
	const PRIMARY_KEY = 'id';

	/**
	 * Keys the settings file must have.
	 */
	const REQUIRED = array( 'searchableAttributes', 'rankingRules' );

	/**
	 * Absolute path to a type's settings file.
	 *
	 * @param string $type Content type.
	 * @return string
	 */
	public static function path( string $type = 'characters' ): string {
		return dirname( __DIR__ ) . '/config/' . $type . '.json';
	}

	/**
	 * Decode and validate a settings file.
	 *
	 * @param string $json File contents.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When the JSON is invalid or a required key is missing.
	 */
	public static function load( string $json ): array {
		$settings = json_decode( $json, true );

		if ( ! is_array( $settings ) ) {
			throw new \InvalidArgumentException( 'Index settings are not valid JSON.' );
		}

		foreach ( self::REQUIRED as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Index settings are missing "%s".', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Key name from a constant.
			}
		}

		return $settings;
	}
}
