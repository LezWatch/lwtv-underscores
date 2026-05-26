<?php
/**
 * Calendar Builder
 *
 * Add the code to build the calendar.
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Calendar\Generate_Calendar;
use LWTV\Plugins\Cache;

class Calendar implements Component {
	/**
	 * Constructor
	 */
	public function init() {
		// Empty
	}

	/**
	 * Generate the calendar
	 *
	 * @param  string $when     string of a day [today, tomorrow]
	 * @param  string $timespan timespan of the calendar [week, month, etc]
	 *
	 * @return array        array of all the shows on that day
	 */
	public function generate_tvmaze_calendar( $when, $timespan = 'week' ): array {
		$tvmaze_url = $this->get_tvmaze_ics();
		if ( false === $tvmaze_url ) {
			lwtv_plugin()->debug_log( 'calendar', 'TVMaze ICS file not found or inaccessible' );
			return array();
		}

		// Check if file is readable and has content
		if ( ! is_readable( $tvmaze_url ) || filesize( $tvmaze_url ) === 0 ) {
			lwtv_plugin()->debug_log( 'calendar', 'TVMaze ICS file is not readable or empty' );
			return array();
		}

		$calendar_data = ( new Generate_Calendar() )->make( $tvmaze_url, $timespan, $when );

		// Log if no calendar data was generated
		if ( empty( $calendar_data ) ) {
			lwtv_plugin()->debug_log( 'calendar', 'No calendar data generated from TVMaze ICS file' );
		}

		return $calendar_data;
	}

	/**
	 * Get the TV Maze ICS file
	 *
	 * @return string|false
	 */
	public function get_tvmaze_ics() {
		$upload_dir  = wp_upload_dir();
		$tvmaze_file = $upload_dir['basedir'] . '/tvmaze.ics';

		if ( ! file_exists( $tvmaze_file ) ) {
			return false;
		}

		return $tvmaze_file;
	}

	/**
	 * Download TV Maze
	 *
	 * Saves the ICS data to a file so we're not overloading the API.
	 *
	 * @param $ics_file  Location of ICS file (optional)
	 *
	 * @return void
	 */
	public function download_tvmaze( $ics_file = null ): void {
		$ics_file = ( is_null( $ics_file ) ) ? self::get_tvmaze_ics() : $ics_file;

		// Fail if there's no ICS.
		if ( false === $ics_file ) {
			return;
		}

		$response = wp_remote_get( TV_MAZE );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $ics_file, $response['body'] );
		}

		// Clear the cache
		$calendar_urls = array(
			get_home_url( null, '/wp-content/uploads/tvmaze.ics' ),
			get_home_url( null, '/calendar/' ),
			get_home_url( null, '/about/calendar/' ),
		);

		( new Cache() )->clean_any_urls( $calendar_urls );
	}
}
