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

class Calendar_Meta_Batcher {

	/**
	 * Cache for batched meta data
	 *
	 * @var array
	 */
	private static $meta_cache = array();

	/**
	 * Cache for post thumbnails
	 *
	 * @var array
	 */
	private static $thumbnail_cache = array();

	/**
	 * Cache for pre-generated thumbnail HTML
	 *
	 * @var array
	 */
	private static $thumbnail_html_cache = array();

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
	 * Batch load post thumbnails for multiple show IDs
	 *
	 * @param  array $show_ids Array of show IDs
	 * @return void
	 */
	public static function batch_load_thumbnails( array $show_ids ): void {
		if ( empty( $show_ids ) ) {
			return;
		}

		// Filter out already cached IDs
		$uncached_ids = array_filter(
			$show_ids,
			function ( $id ) {
				return ! isset( self::$thumbnail_cache[ $id ] );
			}
		);

		if ( empty( $uncached_ids ) ) {
			return;
		}

		global $wpdb;

		// Batch query for thumbnail IDs
		$placeholders = implode( ',', array_fill( 0, count( $uncached_ids ), '%d' ) );
		$sql          = sprintf(
			"SELECT p.ID, pm.meta_value as thumbnail_id
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
			WHERE p.ID IN (%s)",
			$placeholders
		);

		$query   = $wpdb->prepare( $sql, $uncached_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Cache thumbnail IDs
		foreach ( $results as $result ) {
			self::$thumbnail_cache[ $result->ID ] = $result->thumbnail_id;
		}
	}

	/**
	 * Pre-generate thumbnail HTML for common sizes
	 *
	 * @param  array $show_ids Array of show IDs
	 * @return void
	 */
	public static function pre_generate_thumbnail_html( array $show_ids ): void {
		if ( empty( $show_ids ) ) {
			return;
		}

		// Common image sizes used in calendar
		$common_sizes = array( 'thumbnail', 'headshot-search', 'relatedshow-img' );

		foreach ( $show_ids as $show_id ) {
			$thumbnail_id = self::$thumbnail_cache[ $show_id ] ?? 0;

			if ( ! $thumbnail_id ) {
				continue;
			}

			foreach ( $common_sizes as $size ) {
				$cache_key = $show_id . '_' . $size;

				if ( ! isset( self::$thumbnail_html_cache[ $cache_key ] ) ) {
					$html = wp_get_attachment_image( $thumbnail_id, $size, false, array( 'class' => 'calendar-show-img card-img' ) );

					self::$thumbnail_html_cache[ $cache_key ] = $html ? $html : '';
				}
			}
		}
	}

	/**
	 * Get post thumbnail HTML from cache
	 *
	 * @param  int    $post_id Post ID
	 * @param  string $size    Image size
	 * @param  array  $attr    Image attributes
	 * @return string
	 */
	public static function get_thumbnail( int $post_id, string $size = 'thumbnail', array $attr = array() ): string {
		// Check if we have pre-generated HTML for this size
		$cache_key = $post_id . '_' . $size;
		if ( isset( self::$thumbnail_html_cache[ $cache_key ] ) ) {
			return self::$thumbnail_html_cache[ $cache_key ];
		}

		// Ensure thumbnail is loaded for this post
		if ( ! isset( self::$thumbnail_cache[ $post_id ] ) ) {
			self::batch_load_thumbnails( array( $post_id ) );
		}

		$thumbnail_id = self::$thumbnail_cache[ $post_id ] ?? 0;

		if ( ! $thumbnail_id ) {
			return '';
		}

		// Generate and cache the HTML
		$thumbnail = wp_get_attachment_image( $thumbnail_id, $size, false, $attr );

		self::$thumbnail_html_cache[ $cache_key ] = $thumbnail ? $thumbnail : '';

		return self::$thumbnail_html_cache[ $cache_key ];
	}

	/**
	 * Get lazy-loaded thumbnail HTML
	 *
	 * @param  int    $post_id Post ID
	 * @param  string $size    Image size
	 * @param  array  $attr    Image attributes
	 * @return string
	 */
	public static function get_lazy_thumbnail( int $post_id, string $size = 'thumbnail', array $attr = array() ): string {
		$thumbnail_html = self::get_thumbnail( $post_id, $size, $attr );

		if ( empty( $thumbnail_html ) ) {
			return '';
		}

		// Convert HTML to add lazy loading
		$thumbnail_html = str_replace( 'class="', 'class="lazy ', $thumbnail_html );
		$thumbnail_html = str_replace( 'src="', 'data-src="', $thumbnail_html );
		$thumbnail_html = str_replace( 'srcset="', 'data-srcset="', $thumbnail_html );

		return $thumbnail_html;
	}

	/**
	 * Batch load all data for calendar shows
	 *
	 * @param  array $calendar Calendar data with show IDs
	 * @return void
	 */
	public static function batch_load_calendar_data( array $calendar ): void {
		$show_ids = array();

		// Extract all unique show IDs from calendar data
		foreach ( $calendar as $date => $shows ) {
			foreach ( $shows as $show ) {
				if ( isset( $show['show_id'] ) && $show['show_id'] > 0 ) {
					$show_ids[] = $show['show_id'];
				}
			}
		}

		// Remove duplicates
		$show_ids = array_unique( $show_ids );

		// Batch load meta and thumbnails
		self::batch_load_meta( $show_ids );
		self::batch_load_thumbnails( $show_ids );

		// Pre-generate thumbnail HTML for common sizes
		self::pre_generate_thumbnail_html( $show_ids );
	}

	/**
	 * Clear all caches
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$meta_cache           = array();
		self::$thumbnail_cache      = array();
		self::$thumbnail_html_cache = array();
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public static function get_cache_stats(): array {
		return array(
			'meta_cache_count'           => count( self::$meta_cache ),
			'thumbnail_cache_count'      => count( self::$thumbnail_cache ),
			'thumbnail_html_cache_count' => count( self::$thumbnail_html_cache ),
			'meta_cache_keys'            => array_keys( self::$meta_cache ),
			'thumbnail_cache_keys'       => array_keys( self::$thumbnail_cache ),
			'thumbnail_html_cache_keys'  => array_keys( self::$thumbnail_html_cache ),
		);
	}
}
