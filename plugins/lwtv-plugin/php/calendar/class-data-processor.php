<?php
/**
 * Calendar Data Processor
 *
 * Pre-processes calendar data to eliminate redundant processing across views.
 * This provides significant performance improvements by computing all values once
 * and reusing them across List, Grid, and Calendar views.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Calendar_Object_Pool;

class Data_Processor {

	/**
	 * Transient prefix for processed calendar data.
	 *
	 * Bump the version suffix whenever the shape of the processed array
	 * changes, so cached payloads from an older shape are ignored rather
	 * than served to views that no longer understand them.
	 */
	const CACHE_PREFIX = 'lwtv_processed_calendar_v4_';

	/**
	 * Process raw calendar data into a unified structure
	 *
	 * @param  array  $raw_calendar Raw calendar data from TVMaze
	 * @param  string $date_query   Date query for caching
	 * @return array                Processed calendar data
	 */
	public function process_calendar_data( array $raw_calendar, string $date_query = 'today' ): array {
		// Check for cached processed data
		$cache_key   = self::CACHE_PREFIX . $date_query;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data;
		}

		// Get shared instances from object pool
		$display = Calendar_Object_Pool::get_display();
		$names   = Calendar_Object_Pool::get_names();

		$processed_data = array();

		foreach ( $raw_calendar as $date => $shows ) {
			$processed_data[ $date ] = array();

			foreach ( $shows as $show ) {
				$processed_show            = $this->process_show_data( $show, $display, $names );
				$processed_data[ $date ][] = $processed_show;
			}
		}

		// Cache the processed data for 1 day
		lwtv_plugin()->set_transient( $cache_key, $processed_data, DAY_IN_SECONDS );

		return $processed_data;
	}

	/**
	 * Process individual show data
	 *
	 * @param  array  $show    Raw show data
	 * @param  Display $display Display instance
	 * @param  Names   $names   Names instance
	 * @return array            Processed show data
	 */
	private function process_show_data( array $show, Display $display, Names $names ): array {
		// Process show names and IDs.
		//
		// `show_name` is plain text and must be escaped at the point of output.
		// `show_link` is ready-to-print HTML (a link when we have a local show
		// to point at) and must NOT be escaped again.
		//
		// resolve() does the name/ID lookup once - calling make() for each
		// would repeat up to two get_page_by_path() queries per show.
		$resolved  = $names->resolve( $show['show_name'] );
		$show_name = $resolved['name'];
		$show_id   = $resolved['id'];

		$processed_show = array(
			'show_name' => $show_name,
			'show_link' => $names->get_link( $show_name, $show_id ),
			'show_id'   => $show_id,
			'title'     => $show['title'],
			'timestamp' => $show['timestamp'],
		);

		// Process time data.
		//
		// `lwtv_date` is the sidebar widget's label. The agenda derives its own
		// time label and ISO airtime in LWTV\Calendar\Build\Agenda, because the
		// raw timestamp is offset-shifted and needs unpicking first.
		$show_time = $display->get_showtime( $show, false );
		$timezone  = $display->get_tz_abbreviation();

		$processed_show['time_data'] = array(
			'lwtv_date' => $show_time->format( '@ g:i A' ) . ' (' . $timezone . ')',
		);

		// Process episode count for multiple episodes
		if ( is_array( $show['title'] ) ) {
			$processed_show['episode_badge'] = ' <span class="badge text-bg-secondary rounded-pill">' . count( $show['title'] ) . '</span>';
		} else {
			$processed_show['episode_badge'] = '';
		}

		return $processed_show;
	}

	/**
	 * Clear processed calendar cache
	 *
	 * @param  string $date_query Date query to clear (optional)
	 * @return void
	 */
	public function clear_cache( string $date_query = '' ): void {
		if ( empty( $date_query ) ) {
			// Clear all processed calendar caches
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lwtv_processed_calendar_%'" );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_lwtv_processed_calendar_%'" );
		} else {
			// Clear specific cache
			$cache_key = self::CACHE_PREFIX . $date_query;
			lwtv_plugin()->delete_transient( $cache_key );
		}
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public function get_cache_stats(): array {
		global $wpdb;

		$cache_keys = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_lwtv_processed_calendar_%'
			AND option_name NOT LIKE '%_timeout_%'"
		);

		$stats = array(
			'cache_count' => count( $cache_keys ),
			'cache_keys'  => array(),
		);

		foreach ( $cache_keys as $cache ) {
			$key                   = str_replace( '_transient_', '', $cache->option_name );
			$stats['cache_keys'][] = $key;
		}

		return $stats;
	}
}
