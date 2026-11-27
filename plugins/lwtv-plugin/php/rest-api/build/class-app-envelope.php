<?php
/**
 * Name: App Envelope
 * Description: The response envelope and plain-text helpers for the lwtv/v2/app routes.
 *
 * Pure: arrays and scalars in, arrays and strings out. The clock is passed in,
 * so this is unit-tested without WordPress.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Envelope {

	/**
	 * Version of the response shape. Bump only for a breaking change.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Put the envelope in front of a response body.
	 *
	 * The data version is taken from the body alone, so it does not change
	 * when only the timestamp does.
	 *
	 * @param array $body Response body.
	 * @param int   $now  Unix timestamp for generated_at.
	 *
	 * @return array
	 */
	public static function wrap( array $body, int $now ): array {
		return array_merge(
			array(
				'schema_version' => self::SCHEMA_VERSION,
				'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
				'data_version'   => self::data_version( $body ),
			),
			$body
		);
	}

	/**
	 * A short fingerprint of the body: it changes when the data changes.
	 *
	 * @param array $body Response body, without the envelope.
	 *
	 * @return string Twelve hex characters.
	 */
	public static function data_version( array $body ): string {
		// Core json_encode: wp_json_encode is WordPress, and this class is pure.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$json = json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );

		return substr( md5( (string) $json ), 0, 12 );
	}

	/**
	 * Plain UTF-8 text: tags removed, entities decoded, edges trimmed.
	 *
	 * @param string $text Text that may carry HTML.
	 *
	 * @return string
	 */
	public static function plain_text( string $text ): string {
		// Core strip_tags: wp_strip_all_tags is WordPress, and this class is pure.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		return trim( html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * A map that always encodes as a JSON object, even when it is empty.
	 *
	 * @param array $map Id-keyed map.
	 *
	 * @return object
	 */
	public static function dict( array $map ): object {
		return (object) $map;
	}
}
