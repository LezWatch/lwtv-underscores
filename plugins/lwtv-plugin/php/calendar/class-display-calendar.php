<?php
/**
 * Make a calendar of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

class Display_Calendar {

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_shows( $calendar, $today, $tz ) {
		// If we have no shows, we need to display a message.
		if ( ! $calendar || ! $tz ) {
			return '<p>There are no shows on the air for the week starting ' . $today->format( 'F d, Y' ) . '.</p>';
		}

		$header  = $this->get_header();
		$display = $this->get_3_weeks( $calendar, $today, $tz );

		$table = '<table class="table table-bordered border-dark ep-calendar-calendar">' . $header . $display . '</table>';

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
		$thead = '<thead class="thead-light"><tr class="table-secondary lwtvc-heading">';

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
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_3_weeks( $calendar, $today, $tz ) {
		$tbody = '<tbody>';

		$tbody .= $this->get_week( $today, $tz, 'previous' );
		$tbody .= $this->get_week( $today, $tz, 'this', $calendar );
		$tbody .= $this->get_week( $today, $tz, 'next' );

		$tbody .= '</tbody>';

		return $tbody;
	}

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  object $today
	 * @param  object $tz
	 * @param  string $week
	 * @param  array  $calendar
	 * @return string
	 */
	public function get_week( $today, $tz, $week = 'this', $calendar = null ) {
		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification
		$date_query = ( ( strtotime( $get_tvdate ) !== false ) && ( $get_tvdate !== $today->format( 'Y-m-d' ) ) ) ? $get_tvdate : 'today';

		// Get the dates
		$this_datetime = ( new Display() )->build_datetime( $date_query, $tz, 'start' );
		$next_datetime = ( new Display() )->build_datetime( $date_query, $tz, 'end' );
		$prev_datetime = ( new Display() )->build_datetime( $date_query, $tz, 'previous' );

		// If the calendar is empty, assume it's this week.
		$calendar = ( $calendar ) ?? lwtv_plugin()->generate_tvshow_calendar( $this_datetime->format( 'Y-m-d' ) );

		// Get the calendar week.
		$calendar_week = match ( $week ) {
			'previous' => lwtv_plugin()->generate_tvshow_calendar( $prev_datetime->format( 'Y-m-d' ) ),
			'next'     => lwtv_plugin()->generate_tvshow_calendar( $next_datetime->format( 'Y-m-d' ) ),
			default    => $calendar,
		};

		$row = '<tr>';

		// Build the week of days.
		$week_datetime = match ( $week ) {
			'previous' => $prev_datetime,
			'next'     => $next_datetime,
			default    => $this_datetime,
		};

		$week_of_days = array();
		for ( $i = 1; $i <= 7; $i++ ) {
			if ( 1 !== $i ) {
				$week_datetime->modify( 'tomorrow' );
			}
			$week_of_days[] = $week_datetime->format( 'Y-m-d' );
		}

		// Loop through the week of days and display the shows.
		foreach ( $week_of_days as $weekday ) {
			if ( isset( $calendar_week[ $weekday ] ) ) {
				$row .= $this->build_shows_for_day( $calendar_week[ $weekday ], $weekday, $today, $tz );
			} else {
				$row .= $this->build_shows_for_day( array(), $weekday, $today, $tz );
			}
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
		$active    = ( $date === $today->format( 'Y-m-d' ) ) ? 'active' : '';
		$date_fmt  = new \DateTime( $date, $tz );

		$cell  = '<td class="table' . $highlight . '">';
		$cell .= '<ul class="list-group">';
		$cell .= '<li class="list-group-item list-group-item list-group-item-action ' . $active . '"><strong>' . $date_fmt->format( 'M dS' ) . '</strong></li>';

		if ( empty( $shows ) ) {
			$cell .= '<li class="list-group-item list-group-item-action list-group-item' . $highlight . ' disabled"><small>No Shows</small></li>';
		} else {
			foreach ( $shows as $show ) {
				$show['show_name'] = lwtv_plugin()->get_show_name_for_calendar( $show['show_name'], 'tvmaze' );
				$lwtv_date         = ( new Display() )->get_showtime( $show, $tz );
				$show_content      = ( is_array( $show['title'] ) ) ? $show['show_name'] . ' <span class="badge text-bg-secondary badge-pill">' . count( $show['title'] ) . '</span>' : $show['show_name'];
				$cell             .= '<li class="list-group-item list-group-item-action list-group-item' . $highlight . '"><small>' . $lwtv_date . '</br>' . $show_content . '</small></li>';
			}
		}

		$cell .= '</ul>';
		$cell .= '</td>';

		return $cell;
	}
}
