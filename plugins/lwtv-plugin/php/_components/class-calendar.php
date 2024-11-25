<?php
/**
 * Calendar Builder
 *
 * Adds custom post type for TVMaze Show Names
 */

namespace LWTV\_Components;

use LWTV\Calendar\{ Generate_Calendar, ICS_Parser, Names, TVMaze };

class Calendar implements Component, Templater {

	/**
	 * Constructor
	 */
	public function init() {
		new Names();
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
			'generate_calendar'          => array( $this, 'generate_calendar' ),
			'generate_ics_by_date'       => array( $this, 'generate_ics_by_date' ),
			'get_show_name_for_calendar' => array( $this, 'get_show_name_for_calendar' ),
			'download_tvmaze'            => array( $this, 'download_tvmaze' ),
			'get_tvmaze_ics'             => array( $this, 'get_tvmaze_ics' ),
			'get_tvmaze_info'            => array( $this, 'get_tvmaze_info' ),
			'get_tvmaze_show_timezone'   => array( $this, 'get_tvmaze_show_timezone' ),
		);
	}

	/**
	 * Generate the calendar
	 *
	 * @param  string $when     string of a day [today, tomorrow]
	 * @param  string $timespan timespan of the calendar [week, month, etc]
	 *
	 * @return array        array of all the shows on that day
	 */
	public function generate_calendar( $when, $timespan = 'week' ): array {
		$tvmaze_url = lwtv_plugin()->get_tvmaze_ics();
		if ( false === $tvmaze_url ) {
			return array();
		}

		return ( new Generate_Calendar() )->make( $tvmaze_url, $when, $timespan );
	}

	/**
	 * Generate what's on for a specific date
	 *
	 * @param  string $url  URL of calendar
	 * @param  string $when string of a day [today, tomorrow]
	 * @param  string $date date event happens [Y-m-d]
	 *
	 * @return array        array of all the shows on that day
	 */
	public function generate_ics_by_date( $url, $when = 'week', $date = false ): array {
		return ( new ICS_Parser() )->generate_by_date( $url, $when, $date );
	}

	/**
	 * Since TV Maze sometimes uses different names than we do, we have to make a related array that can handle two names.
	 *
	 * @param string $show_name — Display Name of the show
	 * @param string $source    — lwtv or tvmaze
	 *
	 * @return string — The display name
	 */
	public function get_show_name_for_calendar( $show_name, $source = 'lwtv', $output = 'name' ): string {
		return ( new Names() )->make( $show_name, $source, $output );
	}

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
	}

	/**
	 * Get TVMaze Info for a show
	 *
	 * @param  int   $post_id
	 * @return mixed the response body or false
	 */
	public function get_tvmaze_info( $post_id ): mixed {
		// If it's not a show, bail early.
		if ( 'post_type_shows' !== get_post_type( $post_id ) ) {
			return false;
		}

		return ( new TVMaze() )->get_tvmaze_info( $post_id );
	}

	/**
	 * Get the timezone of a show
	 *
	 * @param string $show_name — Display Name of the show
	 *
	 * @return string — The timezone
	 */
	public function get_tvmaze_show_timezone( $show_id ): string {
		// If it's not a show, bail early.
		if ( 'post_type_shows' !== get_post_type( $show_id ) ) {
			return false;
		}

		return ( new TVMaze() )->get_timezone( $show_id );
	}
}
