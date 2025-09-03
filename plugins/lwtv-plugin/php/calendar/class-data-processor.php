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

use LWTV\_Helpers\{ Calendar_Object_Pool, Calendar_Meta_Batcher };

class Data_Processor {

	/**
	 * Process raw calendar data into a unified structure
	 *
	 * @param  array  $raw_calendar Raw calendar data from TVMaze
	 * @param  string $date_query   Date query for caching
	 * @return array                Processed calendar data
	 */
	public function process_calendar_data( array $raw_calendar, string $date_query = 'today' ): array {
		// Check for cached processed data
		$cache_key   = 'lwtv_processed_calendar_' . $date_query;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data;
		}

		// Batch load all meta data for calendar shows
		Calendar_Meta_Batcher::batch_load_calendar_data( $raw_calendar );

		// Get shared instances from object pool
		$display = Calendar_Object_Pool::get_display();
		$names   = Calendar_Object_Pool::get_names();
		$tvmaze  = Calendar_Object_Pool::get_tvmaze();

		$processed_data = array();

		foreach ( $raw_calendar as $date => $shows ) {
			$processed_data[ $date ] = array();

			foreach ( $shows as $show ) {
				$processed_show            = $this->process_show_data( $show, $display, $names, $tvmaze );
				$processed_data[ $date ][] = $processed_show;
			}
		}

		// Cache the processed data for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $processed_data, HOUR_IN_SECONDS );

		return $processed_data;
	}

	/**
	 * Process individual show data
	 *
	 * @param  array  $show    Raw show data
	 * @param  Display $display Display instance
	 * @param  Names   $names   Names instance
	 * @param  TVMaze  $tvmaze  TVMaze instance
	 * @return array            Processed show data
	 */
	private function process_show_data( array $show, Display $display, Names $names, TVMaze $tvmaze ): array {
		// Process show names and IDs
		$processed_show = array(
			'show_name' => $names->make( $show['show_name'], 'tvmaze', 'name' ),
			'show_id'   => $names->make( $show['show_name'], 'lwtv', 'id' ),
			'title'     => $show['title'],
			'timestamp' => $show['timestamp'],
			'native_tz' => $show['native_tz'] ?? '',
		);

		// Get timezone if not already set
		if ( empty( $processed_show['native_tz'] ) && ! empty( $processed_show['show_id'] ) ) {
			$processed_show['native_tz'] = $tvmaze->get_timezone( $processed_show['show_id'] );
		}

		// Process time data
		$show_time = $display->get_showtime( $show, false );
		$timezone  = $display->get_tz_abbreviation();

		$processed_show['time_data'] = array(
			'show_time'      => $show_time,
			'timezone'       => $timezone,
			'lwtv_date'      => $show_time->format( '@ g:i A' ) . ' (' . $timezone . ')',
			'formatted_time' => $display->get_showtime( $show, true ),
		);

		// Process display-specific data
		$processed_show['display_data'] = array(
			'is_today'        => $this->is_today( $show_time, $display->today ),
			'is_past'         => $this->is_past( $show_time, $display->today ),
			'dot_class'       => $this->get_dot_class( $show_time, $display->today ),
			'highlight_class' => $this->get_highlight_class( $show_time, $display->today ),
		);

		// Process episode count for multiple episodes
		if ( is_array( $show['title'] ) ) {
			$processed_show['episode_count'] = count( $show['title'] );
			$processed_show['episode_badge'] = ' <span class="badge text-bg-primary rounded-pill">' . count( $show['title'] ) . '</span>';
		} else {
			$processed_show['episode_count'] = 1;
			$processed_show['episode_badge'] = '';
		}

		return $processed_show;
	}

	/**
	 * Check if show is airing today
	 *
	 * @param  \DateTime $show_time Show time
	 * @param  \DateTime $today     Today's date
	 * @return bool
	 */
	private function is_today( \DateTime $show_time, \DateTime $today ): bool {
		return $show_time->format( 'Y-m-d' ) === $today->format( 'Y-m-d' );
	}

	/**
	 * Check if show is in the past
	 *
	 * @param  \DateTime $show_time Show time
	 * @param  \DateTime $today     Today's date
	 * @return bool
	 */
	private function is_past( \DateTime $show_time, \DateTime $today ): bool {
		return $show_time <= $today;
	}

	/**
	 * Get dot class for show status
	 *
	 * @param  \DateTime $show_time Show time
	 * @param  \DateTime $today     Today's date
	 * @return string
	 */
	private function get_dot_class( \DateTime $show_time, \DateTime $today ): string {
		return $this->is_past( $show_time, $today ) ? 'ep-calendar-dot ep-calendar-dot-past' : 'ep-calendar-dot';
	}

	/**
	 * Get highlight class for show status
	 *
	 * @param  \DateTime $show_time Show time
	 * @param  \DateTime $today     Today's date
	 * @return string
	 */
	private function get_highlight_class( \DateTime $show_time, \DateTime $today ): string {
		if ( $this->is_today( $show_time, $today ) ) {
			return 'table-info';
		}
		return '';
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
			$cache_key = 'lwtv_processed_calendar_' . $date_query;
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
