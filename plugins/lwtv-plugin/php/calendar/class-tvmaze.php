<?php
/**
 * Name: TVMaze Calls
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\{ Calendar_Object_Pool, Calendar_Meta_Batcher };

class TVMaze {

	/**
	 * Get the timezone for a show
	 *
	 * @param  int $show_id
	 * @return string $timezone
	 */
	public function get_timezone( $show_id ) {
		// If TV Maze is disabled, bail early
		if ( ! defined( 'TV_MAZE' ) || ! TV_MAZE ) {
			return '';
		}

		// If there's no show ID, bail early
		$show_id = intval( $show_id );
		if ( ! $show_id || 0 === $show_id ) {
			return '';
		}

		// If the timezone is set in the show's meta, use that
		$timezone = Calendar_Meta_Batcher::get_meta( $show_id, 'lezshows_tvmaze_timezone' );
		if ( $timezone ) {
			return $timezone;
		}

		// Check if there's a transient for the timezone.
		$network_transient = lwtv_plugin()->get_transient( 'lezshows_tvmaze_timezone_' . $show_id );

		if ( 'missing' === $network_transient ) {
			return '';
		}

		// Otherwise, get the show info from the TVMaze API
		$show_info = $this->get_tvmaze_info_show( $show_id );
		if ( ! $show_info ) {
			return '';
		}

		// Get the timezone from the TVMaze API
		$networks = $show_info['network'] ?? array();
		if ( empty( $networks ) ) {
			lwtv_plugin()->set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', YEAR_IN_SECONDS );
			return '';
		}

		$country = $networks['country'] ?? array();
		if ( empty( $country ) ) {
			lwtv_plugin()->set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', MONTH_IN_SECONDS );
			return '';
		}

		$timezone = $country['timezone'] ?? '';
		if ( empty( $timezone ) ) {
			lwtv_plugin()->set_transient( 'lezshows_tvmaze_timezone_' . $show_id, 'missing', WEEK_IN_SECONDS );
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
	public function get_tvmaze_info_show( $show_id, $maybe_show_name = '' ): mixed {
		// If it's not a show, bail early.
		if ( 'post_type_shows' !== get_post_type( $show_id ) ) {
			return false;
		}

		// If a name is passed, use that. Otherwise, get the show name.
		$show_name = $maybe_show_name ?? get_the_title( $show_id );

		$tvmaze_id = Calendar_Meta_Batcher::get_meta( $show_id, 'lezshows_tvmaze_id' );
		$imdb_id   = Calendar_Meta_Batcher::get_meta( $show_id, 'lezshows_imdb' );

		if ( $tvmaze_id ) {
			// Use TV Maze ID if we have it.
			$show_info = wp_remote_get( 'https://api.tvmaze.com/shows/' . $tvmaze_id );
		} elseif ( $imdb_id ) {
			// Use IMDB if we can.
			$show_info = wp_remote_get( 'https://api.tvmaze.com/lookup/shows?imdb=' . $imdb_id );
		} else {
			// Check the show namer just in case we have odd versions for TV Maze.
			$names     = Calendar_Object_Pool::get_names();
			$show_name = $names->make( $show_name, 'lwtv', 'name' );

			// Search TV Maze API for show info:
			$show_info = wp_remote_get( 'https://api.tvmaze.com/singlesearch/shows?q=' . $show_name );
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
