<?php
/**
 * Name: Uncached Option
 * Description: Read and write a non-autoloaded option straight from the database.
 *
 * WP-CLI runs without the object-cache drop-in that web requests use, so a CLI
 * write (cron, Action Scheduler via `wp cron event run`) reaches the database but
 * not Redis, and the web side keeps serving its old copy. Stores that both sides
 * write go through here. See docs/architecture/caching.md#cli-and-web-cache-tiers.
 *
 * For non-autoloaded options only: autoloaded values are read from `alloptions`,
 * which this does not touch.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Uncached_Option {

	/**
	 * get_option(), after dropping any cached copy.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $fallback Returned when the option does not exist.
	 * @return mixed
	 */
	public static function get( string $name, $fallback = false ) {
		self::forget( $name );

		return get_option( $name, $fallback );
	}

	/**
	 * update_option( ..., autoload false ), after dropping any cached copy.
	 *
	 * The cache is cleared first because update_option() compares against the
	 * cached value and skips the write when they match. A stale copy that
	 * happens to equal the new value would leave the database unchanged.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( string $name, $value ): void {
		self::forget( $name );

		update_option( $name, $value, false );
	}

	/**
	 * Drop the option from this process's object cache, including the
	 * `notoptions` record that would otherwise make get_option() skip the
	 * database for an option another process has since created.
	 *
	 * @param string $name Option name.
	 * @return void
	 */
	public static function forget( string $name ): void {
		wp_cache_delete( $name, 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( is_array( $notoptions ) && isset( $notoptions[ $name ] ) ) {
			unset( $notoptions[ $name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
