<?php
/**
 * Name: TVMaze Calls
 */

namespace LWTV\Calendar;

class TVMaze {

	/**
	 * Get the timezone for a show
	 *
	 * @param  int $show_id
	 * @return string $timezone
	 */
	public function get_timezone( $show_id ) {
		// If TV Maze is disabled, bail early
		if ( ! defined( 'LWTV_TV_MAZE' ) || ! LWTV_TV_MAZE ) {
			return '';
		}

		// If there's no show ID, bail early
		$show_id = intval( $show_id );
		if ( ! $show_id || 0 === $show_id ) {
			return '';
		}

		// If the timezone is set in the show's meta, use that
		if ( get_post_meta( $show_id, 'lezshows_tvmaze_timezone', true ) ) {
			return get_post_meta( $show_id, 'lezshows_tvmaze_timezone', true );
		}

		// Check if there's a transient for the timezone.
		$network_transient = lwtv_plugin()->get_transient( 'lezshows_tvmaze_timezone_' . $show_id );

		if ( 'missing' === $network_transient ) {
			return '';
		}

		// Otherwise, get the show info from the TVMaze API
		$show_info = $this->get_tvmaze_info( $show_id );
		if ( ! $show_info ) {
			return '';
		}

		// Get the timezone from the TVMaze API
		$networks = $show_info['network'] ?? array();
		if ( empty( $networks ) ) {
			set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', YEAR_IN_SECONDS );
			return '';
		}

		$country = $networks['country'] ?? array();
		if ( empty( $country ) ) {
			set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', MONTH_IN_SECONDS );
			return '';
		}

		$timezone = $country['timezone'] ?? '';
		if ( empty( $timezone ) ) {
			set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', WEEK_IN_SECONDS );
			return '';
		}

		// Save the timezone to the show's meta - Shows can move, but we don't want to keep hitting the API
		update_post_meta( $show_id, 'lezshows_tvmaze_timezone', $timezone );

		return $timezone;
	}

	/**
	 * Get TVMaze Info for a show
	 *
	 * @param  int   $show_id
	 * @return mixed $show_info_decoded - the response body decoded or false
	 */
	public function get_tvmaze_info( $show_id ): mixed {
		$show_name = get_the_title( $show_id );

		if ( get_post_meta( $show_id, 'lezshows_tvmaze_id', true ) ) {
			// Use TV Maze ID if we have it.
			$show_info = wp_remote_get( 'http://api.tvmaze.com/shows/' . get_post_meta( $show_id, 'lezshows_tvmaze_id', true ) );
		} elseif ( get_post_meta( $show_id, 'lezshows_imdb', true ) ) {
			// Use IMDB if we can.
			$show_info = wp_remote_get( 'http://api.tvmaze.com/lookup/shows?imdb=' . get_post_meta( $show_id, 'lezshows_imdb', true ) );
		} else {
			// Check the show namer just in case we have odd versions for TV Maze.
			$show_name = lwtv_plugin()->get_show_name_for_calendar( $show_name, 'lwtv' );

			// Search TV Maze API for show info:
			$show_info = wp_remote_get( 'http://api.tvmaze.com/singlesearch/shows?q=' . $show_name );
		}

		// If we have an error, return false.
		if ( ! isset( $show_info ) || is_wp_error( $show_info ) ) {
			return false;
		}

		// Set the TV Maze ID
		$show_info_decoded = json_decode( $show_info['body'], true );

		// If we have a TV Maze ID, save it to the post. This is in case they change the records.
		if ( isset( $show_info_decoded['id'] ) ) {
			update_post_meta( $show_id, 'lezshows_tvmaze_id', $show_info_decoded['id'] );
		}

		return $show_info_decoded;
	}
}
