<?php
/*
 * Transients
 *
 */
namespace LWTV\_Components;

class Transients implements Component, Templater {

	/*
	 * Init
	 */
	public function init(): void {
		// Null
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'delete_transient' => array( $this, 'delete_transient' ),
			'get_transient'    => array( $this, 'get_transient' ),
			'set_transient'    => array( $this, 'set_transient' ),
		);
	}

	/**
	 * Get Transient
	 *
	 * A wrapper to default to false if you're developing.
	 *
	 * @param  string      $transient The Transient name
	 * @return string|bool            Transient value (or false)
	 */
	public static function get_transient( $transient ) {
		if ( defined( 'LWTV_DISABLE_TRANSIENTS' ) && LWTV_DISABLE_TRANSIENTS ) {
			return false;
		}

		return get_transient( $transient );
	}

	/**
	 * Set Transient
	 *
	 * A wrapper to default to false if you're developing.
	 *
	 * @param  string      $transient The Transient name
	 * @return void
	 */
	public static function set_transient( $transient, $value, $expiration = 60 * 60 * 24 ) {
		if ( defined( 'LWTV_DISABLE_TRANSIENTS' ) && LWTV_DISABLE_TRANSIENTS ) {
			return;
		}

		set_transient( $transient, $value, $expiration );
	}

	/**
	 * Delete Transient
	 *
	 * A wrapper to delete a transient.
	 *
	 * @param  string      $transient The Transient name
	 * @return void
	 */
	public static function delete_transient( $transient ) {
		delete_transient( $transient );
	}
}
