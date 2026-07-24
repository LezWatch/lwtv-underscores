<?php
/**
 * Make a calendar of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Calendar as Build_Calendar;
use LWTV\_Helpers\Calendar_Object_Pool;

class Display_Calendar {

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 *
	 * @return string
	 */
	public function get_shows( $calendar, $date_query ) {
		$display = Calendar_Object_Pool::get_display();
		$today   = $display->today;
		$tz      = $display->timezone;

		$date_query_datetime = $display->build_datetime( $date_query );

		// If we have no shows, we need to display a message.
		if ( ! $calendar ) {
			return '<p>There are no shows on the air for the week starting ' . $today->format( 'F d, Y' ) . '.</p>';
		}

		$header  = $this->get_header();
		$display = $this->get_3_weeks( $calendar );

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
	 * @param  array  $calendar Processed calendar data
	 * @return string
	 */
	public function get_3_weeks( $calendar ) {
		$tbody = '<tbody>';

		$tbody .= $this->get_week( 'previous', $calendar );
		$tbody .= $this->get_week( 'this', $calendar );
		$tbody .= $this->get_week( 'next', $calendar );

		$tbody .= '</tbody>';

		return $tbody;
	}

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  string $week
	 * @param  array  $calendar Processed calendar data
	 * @return string
	 */
	public function get_week( $week = 'this', $calendar = array() ) {
		$display = Calendar_Object_Pool::get_display();
		$today   = $display->today;
		$tz      = $display->timezone;

		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : $today->format( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification

		// Get the dates
		$this_datetime = $display->build_datetime( $get_tvdate, 'start' );
		$next_datetime = $display->build_datetime( $get_tvdate, 'end' );
		$prev_datetime = $display->build_datetime( $get_tvdate, 'previous' );

		// Use processed calendar data instead of generating new data
		$calendar_week = $calendar;

		$row = '<tr>';

		// Build the week of days.
		$week_datetime = match ( $week ) {
			'previous' => $prev_datetime,
			'next'     => $next_datetime,
			default    => $this_datetime,
		};

		$week_of_days = $display->get_week_of_days( $week_datetime );

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
				// Use pre-processed data from Data Processor
				$show_name    = esc_html( $show['show_name'] );
				$lwtv_date    = $show['time_data']['formatted_time'];
				$show_content = ( is_array( $show['title'] ) ) ? $show_name . $show['episode_badge'] : $show_name;
				$cell        .= '<li class="list-group-item list-group-item-action list-group-item' . $highlight . '"><small>' . $lwtv_date . '</br>' . $show_content . '</small></li>';
			}
		}

		$cell .= '</ul>';
		$cell .= '</td>';

		return $cell;
	}
}
