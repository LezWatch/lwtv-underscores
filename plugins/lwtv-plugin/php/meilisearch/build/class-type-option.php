<?php
/**
 * Name: Type Option
 * Description: Parses the `wp lwtv meili --type=` option.
 *
 * Pure. No value means characters, so commands from before the shows index
 * do what they always did. A typo stops the command rather than quietly
 * running against characters.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Type_Option {

	/**
	 * Index types a --type value selects.
	 *
	 * @param string|null $value   Option value; null or '' for the default.
	 * @param string[]    $known   Index types, in registry order.
	 * @param string      $fallback Type when no value is given.
	 * @return string[]
	 * @throws \InvalidArgumentException For an unknown type.
	 */
	public static function parse( ?string $value, array $known, string $fallback = 'characters' ): array {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array( $fallback );
		}

		if ( 'all' === $value ) {
			return array_values( $known );
		}

		if ( in_array( $value, $known, true ) ) {
			return array( $value );
		}

		throw new \InvalidArgumentException( sprintf( 'Unknown --type "%s". Use one of: %s.', $value, implode( ', ', array_merge( $known, array( 'all' ) ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI message, not HTML.
	}
}
