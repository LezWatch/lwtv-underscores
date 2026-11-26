<?php
/**
 * Name: Search Settings Store
 * Description: The Search Settings option: the switch and the timeout.
 *
 * One option, `lwtv_meili_settings`, autoloaded because Search_Modal reads
 * it on every front-end request: { version: 1, switch, timeout_ms }.
 *
 * LWTV_MEILI_MODAL in wp-config.php wins over the option while it is
 * defined. seed() copies a valid constant into an empty option, so
 * removing the define later changes nothing for visitors.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Search_Settings;

class Search_Settings_Store {

	const OPTION   = 'lwtv_meili_settings';
	const CONSTANT = 'LWTV_MEILI_MODAL';

	/**
	 * Returns array{ bool defined, mixed value } for the constant. A seam, so
	 * the option-only path can be checked without editing wp-config.php.
	 *
	 * @var \Closure
	 */
	private \Closure $constant;

	/**
	 * @param \Closure|null $constant Returns array{ bool, mixed }; null reads LWTV_MEILI_MODAL.
	 */
	public function __construct( ?\Closure $constant = null ) {
		$this->constant = $constant ?? static fn(): array => array( defined( self::CONSTANT ), defined( self::CONSTANT ) ? constant( self::CONSTANT ) : null );
	}

	/**
	 * The option, with defaults for anything bad or missing.
	 *
	 * @return array{version: int, switch: string, timeout_ms: int}
	 */
	public function get(): array {
		return Search_Settings::read( get_option( self::OPTION ) );
	}

	/**
	 * The switch in effect and where it comes from.
	 *
	 * @return array{mode: string, source: string, raw: ?string}
	 */
	public function effective(): array {
		list( $defined, $value ) = ( $this->constant )();

		return Search_Settings::effective( (bool) $defined, $value, $this->get() );
	}

	/**
	 * Whether the constant is defined, and its value as text.
	 *
	 * @return array{defined: bool, value: ?string, valid: bool}
	 */
	public function constant(): array {
		list( $defined, $value ) = ( $this->constant )();

		return array(
			'defined' => (bool) $defined,
			'value'   => ( $defined && is_scalar( $value ) ) ? (string) $value : null,
			// Read the way search reads it: 'Editors ' counts as editors.
			'valid'   => Search_Settings::constant_valid( (bool) $defined, $value ),
		);
	}

	/**
	 * The timeout, in milliseconds.
	 *
	 * @return int
	 */
	public function timeout_ms(): int {
		return $this->get()['timeout_ms'];
	}

	/**
	 * The timeout, in seconds, for server-side requests.
	 *
	 * @return float
	 */
	public function timeout_seconds(): float {
		return $this->timeout_ms() / 1000;
	}

	/**
	 * Validate and save. The switch can't change while the constant is defined.
	 *
	 * @param mixed $input { switch?, timeout_ms? }.
	 * @return array{ok: bool, errors: array<string, string>, values: array, status: int}
	 */
	public function save( mixed $input ): array {
		$checked = Search_Settings::validate( $input );

		if ( ! empty( $checked['errors'] ) ) {
			return array(
				'ok'     => false,
				'errors' => $checked['errors'],
				'values' => $this->get(),
				'status' => 400,
			);
		}

		if ( isset( $checked['values']['switch'] ) && $this->constant()['defined'] ) {
			return array(
				'ok'     => false,
				'errors' => array( 'switch' => 'The switch is set in wp-config.php (LWTV_MEILI_MODAL). Remove that line to manage it here.' ),
				'values' => $this->get(),
				'status' => 409,
			);
		}

		$merged = array_merge( $this->get(), $checked['values'] );
		update_option( self::OPTION, $merged, true );

		return array(
			'ok'     => true,
			'errors' => array(),
			'values' => $merged,
			'status' => 200,
		);
	}

	/**
	 * Copy a valid constant into an option with no switch saved yet.
	 *
	 * @return bool Whether it wrote.
	 */
	public function seed(): bool {
		list( $defined, $value ) = ( $this->constant )();
		$stored                  = get_option( self::OPTION );
		$mode                    = Search_Settings::seed( (bool) $defined, $value, $stored );

		if ( null === $mode ) {
			return false;
		}

		$settings           = Search_Settings::read( $stored );
		$settings['switch'] = $mode;

		return update_option( self::OPTION, $settings, true );
	}
}
