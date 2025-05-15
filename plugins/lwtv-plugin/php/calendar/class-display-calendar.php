<?php
/**
 * Make a calendar of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

use LWTV\_Components\Calendar as Build_Calendar;

class Display_Calendar {

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 *
	 * @return string
	 */
	public function get_shows( $calendar, $date_query ) {
		$today = ( new Display() )->today;
		$tz    = ( new Display() )->timezone;

		$date_query_datetime = ( new Display() )->build_datetime( $date_query );

		// If we have no shows, we need to display a message.
		if ( ! $calendar ) {
			return '<p>There are no shows on the air for the week starting ' . $today->format( 'F d, Y' ) . '.</p>';
		}

		$header  = $this->get_header();
		$display = $this->get_3_weeks();

		$table = '<table class="table table-bordered table-striped border-dark ep-calendar-calendar">' . $header . $display . '</table>';

		return $table;
	}

	/**
	 * Generate the header for the calendar.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_header() {
		$thead = '<thead class="ep-calendar-thead-calendar table-success"><tr class="lwtvc-heading">';

		// Calendar headers are days of the week
		$days = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );

		foreach ( $days as $day ) {
			$thead .= '<th scope="col" class="col-1"><span class="ep-calendar-heading-date">' . $day . '</span></th>';
		}

		$thead .= '</tr></thead>';

		return $thead;
	}

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_3_weeks() {
		$tbody = '<tbody>';

		$tbody .= $this->get_week( 'previous' );
		$tbody .= $this->get_week( 'this' );
		$tbody .= $this->get_week( 'next' );

		$tbody .= '</tbody>';

		return $tbody;
	}

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  string $week
	 * @return string
	 */
	public function get_week( $week = 'this' ) {
		$today = ( new Display() )->today;
		$tz    = ( new Display() )->timezone;

		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : $today->format( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification

		// Get the dates
		$this_datetime = ( new Display() )->build_datetime( $get_tvdate, 'start' );
		$next_datetime = ( new Display() )->build_datetime( $get_tvdate, 'end' );
		$prev_datetime = ( new Display() )->build_datetime( $get_tvdate, 'previous' );

		// Get the calendar week.
		$calendar_week = match ( $week ) {
			'this'     => ( new Build_Calendar() )->generate_tvmaze_calendar( $this_datetime->format( 'Y-m-d' ) ),
			'previous' => ( new Build_Calendar() )->generate_tvmaze_calendar( $prev_datetime->format( 'Y-m-d' ) ),
			'next'     => ( new Build_Calendar() )->generate_tvmaze_calendar( $next_datetime->format( 'Y-m-d' ) ),
		};

		$row = '<tr>';

		// Build the week of days.
		$week_datetime = match ( $week ) {
			'previous' => $prev_datetime,
			'next'     => $next_datetime,
			default    => $this_datetime,
		};

		$week_of_days = ( new Display() )->get_week_of_days( $week_datetime );

		// Loop through the week of days and display the shows.
		foreach ( $week_of_days as $weekday ) {
			$show_array = ( isset( $calendar_week[ $weekday ] ) ) ? $calendar_week[ $weekday ] : array();
			$row       .= $this->build_shows_for_day( $show_array, $weekday, $today, $tz );
		}

		$row .= '</tr>';

		return $row;
	}

	/**
	 * Build the shows for a specific day.
	 *
	 * @param  array  $shows
	 * @param  string $date
	 * @param  object $today
	 * @return string
	 */
	public function build_shows_for_day( $shows, $date, $today, $tz ) {
		$highlight = ( $date === $today->format( 'Y-m-d' ) ) ? '-info' : '-light';
		$active    = ( $date === $today->format( 'Y-m-d' ) ) ? 'active' : 'list-group-item-secondary';
		$date_fmt  = new \DateTime( $date, $tz );

		$cell  = '<td class="ep-calendar-td-calendar">';
		$cell .= '<ul class="list-group list-group-flush">';
		$cell .= '<li class="list-group-item list-group-item list-group-item-action ' . $active . '"><strong>' . $date_fmt->format( 'M jS' ) . '</strong></li>';

		if ( empty( $shows ) ) {
			$cell .= '<li class="list-group-item list-group-item-action list-group-item' . $highlight . ' disabled"><small>No Shows</small></li>';
		} else {
			foreach ( $shows as $show ) {
				$show['show_name'] = ( new Names() )->make( $show['show_name'], 'tvmaze', 'name' );
				$lwtv_date         = ( new Display() )->get_showtime( $show, true );
				$show_content      = ( is_array( $show['title'] ) ) ? $show['show_name'] . ' <span class="badge text-bg-secondary badge-pill">' . count( $show['title'] ) . '</span>' : $show['show_name'];
				$cell             .= '<li class="list-group-item list-group-item-action list-group-item' . $highlight . '"><small>' . $lwtv_date . '</br>' . $show_content . '</small></li>';
			}
		}

		$cell .= '</ul>';
		$cell .= '</td>';

		return $cell;
	}
}
