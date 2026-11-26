<?php
/**
 * Name: Search Settings
 * Description: The Search Settings page's values: the switch and the timeout.
 *
 * Pure. The switch decides who gets Meilisearch search (off, editors,
 * everyone). When LWTV_MEILI_MODAL is defined in wp-config.php it wins,
 * read exactly as Modal_Switch::mode() reads it; otherwise the option is
 * used. The timeout (ms) is how long every search surface waits before it
 * falls back. Bad input is refused, never clamped.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Settings {

	const VERSION = 1;

	const DEFAULT_SWITCH = Modal_Switch::OFF;

	const TIMEOUT_DEFAULT = 2000;
	const TIMEOUT_MIN     = 500;
	const TIMEOUT_MAX     = 5000;
	const TIMEOUT_STEP    = 100;

	const SWITCHES = array( Modal_Switch::OFF, Modal_Switch::EDITORS, Modal_Switch::EVERYONE );

	/**
	 * The option with nothing saved.
	 *
	 * @return array{version: int, switch: string, timeout_ms: int}
	 */
	public static function defaults(): array {
		return array(
			'version'    => self::VERSION,
			'switch'     => self::DEFAULT_SWITCH,
			'timeout_ms' => self::TIMEOUT_DEFAULT,
		);
	}

	/**
	 * The stored option, with any bad or missing field replaced by its default.
	 *
	 * @param mixed $stored Raw option value.
	 * @return array{version: int, switch: string, timeout_ms: int}
	 */
	public static function read( mixed $stored ): array {
		$out    = self::defaults();
		$stored = is_array( $stored ) ? $stored : array();

		if ( self::is_switch( $stored['switch'] ?? null ) ) {
			$out['switch'] = $stored['switch'];
		}
		if ( self::is_timeout( $stored['timeout_ms'] ?? null ) ) {
			$out['timeout_ms'] = $stored['timeout_ms'];
		}

		return $out;
	}

	/**
	 * Check input from the page. Only `switch` and `timeout_ms` are read.
	 *
	 * @param mixed $input Raw input.
	 * @return array{values: array<string, string|int>, errors: array<string, string>}
	 */
	public static function validate( mixed $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$values = array();
		$errors = array();

		if ( array_key_exists( 'switch', $input ) ) {
			if ( self::is_switch( $input['switch'] ) ) {
				$values['switch'] = $input['switch'];
			} else {
				$errors['switch'] = 'Choose off, editors or everyone.';
			}
		}

		if ( array_key_exists( 'timeout_ms', $input ) ) {
			if ( self::is_timeout( $input['timeout_ms'] ) ) {
				$values['timeout_ms'] = $input['timeout_ms'];
			} else {
				$errors['timeout_ms'] = sprintf( 'The timeout must be a whole number of milliseconds from %d to %d, in steps of %d.', self::TIMEOUT_MIN, self::TIMEOUT_MAX, self::TIMEOUT_STEP );
			}
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * The switch in effect, and where it comes from.
	 *
	 * @param bool  $defined  Whether LWTV_MEILI_MODAL is defined.
	 * @param mixed $constant Its value.
	 * @param array $settings The option, as read().
	 * @return array{mode: string, source: string, raw: ?string}
	 */
	public static function effective( bool $defined, mixed $constant, array $settings ): array {
		if ( $defined ) {
			return array(
				'mode'   => Modal_Switch::mode( $constant ),
				'source' => 'constant',
				'raw'    => is_scalar( $constant ) ? (string) $constant : null,
			);
		}

		return array(
			'mode'   => self::is_switch( $settings['switch'] ?? null ) ? $settings['switch'] : self::DEFAULT_SWITCH,
			'source' => 'option',
			'raw'    => null,
		);
	}

	/**
	 * The value to copy from the constant into the option, so removing the
	 * define later changes nothing; null when there's nothing to copy.
	 *
	 * @param bool  $defined  Whether LWTV_MEILI_MODAL is defined.
	 * @param mixed $constant Its value.
	 * @param mixed $stored   Raw option value.
	 * @return string|null
	 */
	public static function seed( bool $defined, mixed $constant, mixed $stored ): ?string {
		if ( ! $defined || ( is_array( $stored ) && self::is_switch( $stored['switch'] ?? null ) ) ) {
			return null;
		}

		$mode = Modal_Switch::mode( $constant );
		if ( Modal_Switch::OFF !== $mode ) {
			return $mode;
		}

		// Only an explicit "off" seeds off; a typo seeds nothing.
		return ( is_string( $constant ) && 'off' === strtolower( trim( $constant ) ) ) ? Modal_Switch::OFF : null;
	}

	/**
	 * Whether a defined constant is a value search understands: anything
	 * Modal_Switch::mode() reads as editors or everyone (it trims and
	 * lowercases), or an explicit off. Anything else reads as off by mistake.
	 *
	 * @param bool  $defined  Whether LWTV_MEILI_MODAL is defined.
	 * @param mixed $constant Its value.
	 * @return bool
	 */
	public static function constant_valid( bool $defined, mixed $constant ): bool {
		return null !== self::seed( $defined, $constant, null );
	}

	/**
	 * Whether a value is a valid switch, exactly.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_switch( mixed $value ): bool {
		return is_string( $value ) && in_array( $value, self::SWITCHES, true );
	}

	/**
	 * Whether a value is a valid timeout, exactly.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_timeout( mixed $value ): bool {
		return is_int( $value ) && $value >= self::TIMEOUT_MIN && $value <= self::TIMEOUT_MAX && 0 === $value % self::TIMEOUT_STEP;
	}
}
