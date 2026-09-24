<?php
/**
 * Name: Queue Store
 * Description: Background work queues kept in non-autoloaded options.
 *
 * A queue is a store, not a cache: nothing can rebuild it, so it must not live
 * in a transient. See docs/architecture/caching.md#cache-vs-store.
 *
 * Keys keep the names the queues had as transients. The first read of a key
 * with no option yet adopts any leftover transient of the same name, once, then
 * deletes it.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Queue_Store {

	/**
	 * Read a queue.
	 *
	 * @param string $key Option name.
	 * @return array The stored array, or an empty one.
	 */
	public static function get( string $key ): array {
		$stored = get_option( $key, null );

		if ( null === $stored ) {
			$stored = self::adopt_legacy_transient( $key );
		}

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Write a queue. An empty array is stored rather than deleted, so a drained
	 * queue never re-triggers the legacy carry-over.
	 *
	 * @param string $key   Option name.
	 * @param array  $value Queue contents.
	 * @return void
	 */
	public static function set( string $key, array $value ): void {
		update_option( $key, $value, false );
	}

	/**
	 * Move a pre-options transient into the option, once.
	 *
	 * @param string $key Option name, which is also the old transient name.
	 * @return array Whatever the transient held, or an empty array.
	 */
	private static function adopt_legacy_transient( string $key ): array {
		$legacy = lwtv_plugin()->get_transient( $key );
		$value  = is_array( $legacy ) ? $legacy : array();

		update_option( $key, $value, false );
		lwtv_plugin()->delete_transient( $key );

		return $value;
	}
}
