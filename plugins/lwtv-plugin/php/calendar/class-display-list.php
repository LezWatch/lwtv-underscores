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
	public function get_shows( $calendar, $today, $tz ) {

		// Header Sub Navigation
		$header = '<div class="ep-calendar-subnav text-bg-light p-3"><ul class="nav justify-content-center">';
		foreach ( $calendar as $day => $shows ) {
			$show_day = new \DateTime( $day, $tz );

			$link_color = ( $day === $today->format( 'Y-m-d' ) ) ? 'link-info' : 'link-dark';

			$header .= '<li class="nav-item"><a class="' . $link_color . ' link-offset-2 nav-link" href="#' . strtolower( $show_day->format( 'ldS' ) ) . '">' . $show_day->format( 'l' ) . '</a></li>';
		}
		$header .= '</ul></div>';

		$table = '<table class="table">';

		foreach ( $calendar as $day => $shows ) {
			$highlight = ( $day === $today->format( 'Y-m-d' ) ) ? ' table-info' : '';
			$show_day  = new \DateTime( $day, $tz );

			$today_date = $show_day->format( 'F d, Y' );
			if ( $day === $today->format( 'Y-m-d' ) ) {
				$today_date .= '&nbsp;&nbsp;<button type="button" class="btn btn-info btn-sm" disabled><a name="today">Today</a></button>';
			}

			$table .= '<thead class="thead-light"><tr class="table-secondary lwtvc-heading' . $highlight . '" data-date="' . $show_day->format( 'Y-m-d' ) . '"><th colspan="3"><span class="ep-calendar-heading-date">' . $today_date . '</span><span class="ep-calendar-heading-weekday">' . $show_day->format( 'l' ) . '</span></th></tr></thead><tbody>';

			foreach ( $shows as $show ) {
				$show_time = new \DateTime( '@' . $show['timestamp'], $tz );
				$lwtv_date = ( new Display() )->get_showtime( $show, $tz );

				// Determine if the show is airing now, soon, or later.
				$dot_time = ( $show_time <= $today ) ? 'ep-calendar-dot ep-calendar-dot-past' : 'ep-calendar-dot';

				// Show Name (may be URL if we have a link)
				$show['show_name'] = lwtv_plugin()->get_show_name_for_calendar( $show['show_name'] );
				$show['show_id']   = lwtv_plugin()->get_show_name_for_calendar( $show['show_name'], 'lwtv', 'id' );
				$show['native_tz'] = lwtv_plugin()->get_tvmaze_show_timezone( $show['show_id'] );

				// Build output
				$show_content  = '<div class="ep-calendar-title">';
				$show_content .= ( is_array( $show['title'] ) ) ? $this->display_multiple_episodes_list( $show ) : $this->display_single_episode_list( $show );
				$show_content .= '</div>';

				// Return it all!
				$table .= '<tr class="ep-calendar-item' . $highlight . '"><td class="ep-calendar-item-time">' . $lwtv_date . '</td><td class="ep-calendar-marker"><span class="' . $dot_time . '"></span></td><td class="ep-calendar-item-title">' . $show_content . '</td></tr>';
			}

			$table .= '</tbody>';
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
