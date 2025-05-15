<?php
/**
 * Make a list of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

class Display_List {

	/**
	 * Generate the list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_shows( $calendar, $date_query ) {

		$today = ( new Display() )->today;
		$tz    = ( new Display() )->timezone;

		$date_query_datetime = ( new Display() )->build_datetime( $date_query );

		// Header Sub Navigation
		$header = ( new Display() )->get_subnav( $calendar, 'list', $date_query_datetime );
		$table  = '<table class="table">';

		$week_of_days = ( new Display() )->get_week_of_days( $date_query_datetime );

		// Loop through the days of the week.
		foreach ( $week_of_days as $weekday ) {
			$weekday_object = new \DateTime( $weekday, $tz );

			// If we have no shows, we need to display a message.
			if ( ! isset( $calendar[ $weekday ] ) ) {
				$table .= '<thead class="dayjump" id="list_' . $weekday . '"><tr class="lwtvc-heading"><th colspan="3" class="text-bg-secondary"><span class="ep-calendar-heading-date">' . $weekday_object->format( 'l jS' ) . '</span><span class="ep-calendar-heading-backtotop"><a href="#caltop">Back to Top</a></span></th></tr></thead>';
				$table .= '<tbody><tr><td colspan="3"><em>No shows on this day.</em></td></tr></tbody>';
				continue;
			}

			$highlight = ( $weekday === $today->format( 'l' ) ) ? ' table-info' : '';

			$show_day   = new \DateTime( $weekday, $tz );
			$today_link = strtolower( $show_day->format( 'l' ) );
			$today_date = $show_day->format( 'l jS' );

			if ( $weekday === $today->format( 'Y-m-d' ) ) {
				$today_date .= '&nbsp;&nbsp;<button type="button" class="btn btn-info btn-sm" disabled><strong><a name="today">Today</a></strong></button>';
			}

			$table .= '<thead class="dayjump" id="list_' . $today_link . '"><tr class="lwtvc-heading' . $highlight . '" data-date="' . $show_day->format( 'Y-m-d' ) . '"><th colspan="3" class="text-bg-secondary"><span class="ep-calendar-heading-date">' . $today_date . '</span><span class="ep-calendar-heading-backtotop"><a href="#caltop">Back to Top</a></span></th></tr></thead><tbody>';

			foreach ( $calendar[ $weekday ] as $show ) {
				// Show Name (may be URL if we have a link)
				$show['show_name'] = ( new Names() )->make( $show['show_name'], 'tvmaze', 'name' );
				$show['show_id']   = ( new Names() )->make( $show['show_name'], 'lwtv', 'id' );
				$show['native_tz'] = ( new TVMaze() )->get_timezone( $show['show_id'] );

				$show_time = ( new Display() )->get_showtime( $show, false );
				$timezone  = ( new Display() )->get_tz_abbreviation();
				$lwtv_date = $show_time->format( '@ g:i A' ) . ' (' . $timezone . ')';

				// Determine if the show is airing now, soon, or later.
				$dot_time = ( $show_time <= $today ) ? 'ep-calendar-dot ep-calendar-dot-past' : 'ep-calendar-dot';

				// Build output
				$show_content  = '<div class="ep-calendar-title">';
				$show_content .= ( is_array( $show['title'] ) ) ? $this->display_multiple_episodes_list( $show ) : $this->display_single_episode_list( $show );
				$show_content .= '</div>';

				// Return it all!
				$table .= '<tr class="ep-calendar-item' . $highlight . '"><td class="ep-calendar-item-time">' . $lwtv_date . '</td><td class="ep-calendar-marker"><span class="' . $dot_time . '"></span></td><td class="ep-calendar-item-title">' . $show_content . '</td></tr>';

				$table .= '</tbody>';
			}
		}

		$table .= '</table>';

		return $header . $table;
	}

	/**
	 * Generate display for multiple episodes for a show in a day.
	 *
	 * @param  array  $show
	 * @return string
	 */
	private function display_multiple_episodes_list( array $show ): string {
		$show_content  = '<em>' . $show['show_name'] . ' <span class="badge text-bg-secondary badge-pill">' . count( $show['title'] ) . '</span></em>';
		$show_content .= '<ul>';

		foreach ( $show['title'] as $one_show ) {
			$show_content .= '<li>' . $one_show . '</li>';
		}
		$show_content .= '</ul>';

		return $show_content;
	}

	/**
	 * Generate display for single episode in a day.
	 *
	 * @param  array  $show
	 * @return string
	 */
	private function display_single_episode_list( array $show ): string {
		return '<em>' . $show['show_name'] . '</em> - ' . $show['title'];
	}
}
