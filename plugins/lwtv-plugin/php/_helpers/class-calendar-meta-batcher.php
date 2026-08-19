<?php
/**
 * Calendar Meta Batcher
 *
 * Batches post meta queries to eliminate individual get_post_meta() calls.
 * This provides significant performance improvements by reducing database queries.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Calendar_Meta_Batcher {

	/**
	 * Cache for batched meta data
	 *
	 * @var array
	 */
	private static $meta_cache = array();

	/**
	 * Batch load post meta for multiple show IDs
	 *
	 * @param  array $show_ids Array of show IDs
	 * @return void
	 */
	public static function batch_load_meta( array $show_ids ): void {
		if ( empty( $show_ids ) ) {
			return;
		}

		// Filter out already cached IDs
		$uncached_ids = array_filter(
			$show_ids,
			function ( $id ) {
				return ! isset( self::$meta_cache[ $id ] );
			}
		);

		if ( empty( $uncached_ids ) ) {
			return;
		}

		global $wpdb;

		// Batch query for all needed meta keys
		$meta_keys         = array( 'lezshows_tvmaze_timezone', 'lezshows_tvmaze_id', 'lezshows_imdb' );
		$placeholders      = implode( ',', array_fill( 0, count( $uncached_ids ), '%d' ) );
		$meta_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$sql   = sprintf(
			"SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN (%s)
			AND meta_key IN (%s)",
			$placeholders,
			$meta_placeholders
		);
		$query = $wpdb->prepare( $sql, array_merge( $uncached_ids, $meta_keys ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Organize results by post ID
		foreach ( $uncached_ids as $post_id ) {
			self::$meta_cache[ $post_id ] = array();
		}

		foreach ( $results as $result ) {
			if ( ! isset( self::$meta_cache[ $result->post_id ] ) ) {
				self::$meta_cache[ $result->post_id ] = array();
			}
			self::$meta_cache[ $result->post_id ][ $result->meta_key ] = $result->meta_value;
		}
	}

	/**
	 * Get post meta value from cache
	 *
	 * @param  int    $post_id Post ID
	 * @param  string $meta_key Meta key
	 * @param  mixed  $default_value  Default value if not found
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $meta_key, $default_value = '' ) {
		// Ensure meta is loaded for this post
		if ( ! isset( self::$meta_cache[ $post_id ] ) ) {
			self::batch_load_meta( array( $post_id ) );
		}

		return self::$meta_cache[ $post_id ][ $meta_key ] ?? $default_value;
	}

	/**
	 * Clear all caches
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$meta_cache = array();
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public static function get_cache_stats(): array {
		return array(
			'meta_cache_count' => count( self::$meta_cache ),
			'meta_cache_keys'  => array_keys( self::$meta_cache ),
		);
	}
}
