<?php
/**
 * Name: Calendar
 * Description: Code to display the calendar
 * Version: 1.0
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Calendar as Build_Calendar;
use LWTV\Calendar\Display_Agenda;
use LWTV\Calendar\Data_Processor;

class Display {

	/**
	 * Timezone and Day Objects
	 *
	 * @var object
	 */
	public $timezone = null;
	public $today    = null;

	/**
	 * Constructor
	 *
	 * Set the timezone and today's date.
	 */
	public function __construct() {
		$this->timezone = new \DateTimeZone( LWTV_TIMEZONE );
		$this->today    = new \DateTime( 'now', $this->timezone );
	}

	/**
	 * Make the Calendar
	 *
	 * @return string
	 */
	public function make() {
		// Enqueue the script that resolves each episode's aired state against
		// the visitor's clock. The processed calendar is cached for a day, so
		// this cannot be answered server-side.
		wp_enqueue_script( 'lwtv-calendar-agenda', LWTV_PLUGIN_URL . '/assets/js/calendar-agenda.js', array(), LWTV_THEME_VERSION['lwtv-underscores'], true );

		$today = $this->today->format( 'Y-m-d' );

		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification
		$date_query = ( ( strtotime( $get_tvdate ) !== false ) && ( $get_tvdate !== $today ) ) ? $get_tvdate : 'today';

		// Get the dates
		$start_datetime = self::build_datetime( $date_query, 'start' );
		$end_datetime   = self::build_datetime( $date_query, 'next' );
		$prev_datetime  = self::build_datetime( $date_query, 'previous' );

		/**
		 * Calendar itself
		 *
		 * One week per page - pagination in the footer moves the window. The
		 * ICS parse is the expensive part of this request, so we only ask for
		 * the week we are actually going to render.
		 */
		$calendar = ( new Build_Calendar() )->generate_tvmaze_calendar( $start_datetime->format( 'Y-m-d' ) );

		// Check if we have valid calendar data
		if ( empty( $calendar ) ) {
			return $this->get_tvmaze_error_message();
		}

		// Process calendar data using Data Processor
		$data_processor     = new Data_Processor();
		$processed_calendar = $data_processor->process_calendar_data( $calendar, $date_query );

		ksort( $processed_calendar );

		$return  = $this->get_header( $start_datetime );
		$return .= $this->get_intro();

		// If we have no shows, we need to display a message.
		if ( isset( $calendar['none'] ) || empty( $calendar ) || ! is_array( $calendar ) ) {
			$return .= $this->get_empty_calendar( $start_datetime, $end_datetime );
		} else {
			$return .= ( new Display_Agenda() )->get_shows( $processed_calendar, $this->get_week_of_days( $start_datetime ) );
		}

		$return .= $this->get_footer( $date_query, $prev_datetime, $end_datetime );

		return '<div class="lwtv-calendar-block">' . $return . '</div>';
	}

	/**
	 * Get the header for the calendar.
	 *
	 * With one week per page the visitor needs to know which week they are
	 * looking at, since the agenda's own header only names the timezone.
	 *
	 * @param  object $start_datetime Sunday of the week being shown.
	 * @return string                 The header
	 */
	private function get_header( $start_datetime ) {
		$start = clone $start_datetime;
		$end   = clone $start_datetime;
		$end->modify( '+6 days' );

		return '<h2 class="lwtv-calendar-week">' . sprintf(
			/* translators: 1: start date of the week, 2: end date of the week */
			esc_html__( 'Week of %1$s &ndash; %2$s', 'lwtv' ),
			esc_html( $start->format( 'F j, Y' ) ),
			esc_html( $end->format( 'F j, Y' ) )
		) . '</h2>';
	}

	/**
	 * Get the introductory copy that sits above the schedule.
	 *
	 * @return string The intro copy.
	 */
	private function get_intro() {
		return '<p class="ep-agenda-intro">' . esc_html__( 'Airdates and times are subject to change without notice, and are shown for their original US/Eastern broadcast. Always check your local listings.', 'lwtv' ) . '</p>';
	}

	/**
	 * Get the footer for the calendar
	 *
	 * @param  string $date_query    The date we're building nav for
	 * @param  object $prev_datetime Last week
	 * @param  object $end_datetime  Next week
	 *
	 * @return string                The footer
	 */
	private function get_footer( $date_query, $prev_datetime, $end_datetime ) {
		$footer  = $this->get_footer_navigation( $date_query, $prev_datetime->format( 'Y-m-d' ), $end_datetime->format( 'Y-m-d' ) );
		$footer .= '<p class="ep-agenda-credit"><small><a href="https://www.tvmaze.com" target="_new">' . esc_html__( 'Powered by TVMaze.', 'lwtv' ) . '</a></small></p>';

		return $footer;
	}

	/**
	 * Generate week-to-week navigation.
	 *
	 * All dates are 'Y-m-d'.
	 *
	 * @param  string $date The date we're building nav for
	 * @param  string $last Last week
	 * @param  string $next Next week
	 *
	 * @return string       HTML output for the navigation
	 */
	private function get_footer_navigation( $date, $last, $next ) {
		$today = $this->today->format( 'Y-m-d' );

		$this_week      = esc_url( get_permalink() );
		$last_week      = esc_url( add_query_arg( array( 'tvdate' => $last ), get_permalink() ) );
		$next_week      = esc_url( add_query_arg( array( 'tvdate' => $next ), get_permalink() ) );
		$last_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-left-circle.svg', icon: 'svg-chevron-circle-left', max_size: '14' );
		$next_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-right-circle.svg', icon: 'svg-chevron-circle-right', max_size: '14' );

		$navigation = '<nav aria-label="' . esc_attr__( 'Calendar Navigation', 'lwtv' ) . '" class="lwtv-pagination"><ul class="pagination justify-content-center">';

		$navigation .= '<li class="page-item first me-auto"><a href="' . $last_week . '" class="page-link">' . $last_week_icon . ' ' . esc_html__( 'Last Week', 'lwtv' ) . '</a></li>';

		// We only show 'this week' when it's NOT this week.
		if ( 'today' !== $date && $today !== $date ) {
			$navigation .= '<li class="page-item"><a href="' . $this_week . '" class="page-link">' . esc_html__( 'This Week', 'lwtv' ) . '</a></li>';
		}

		$navigation .= '<li class="page-item last ms-auto"><a href="' . $next_week . '" class="page-link">' . esc_html__( 'Next Week', 'lwtv' ) . ' ' . $next_week_icon . '</a></li>';
		$navigation .= '</ul></nav>';

		return $navigation;
	}

	/**
	 * Get Date/Time
	 *
	 * This will set up the start of the week. It's always Sunday.
	 *
	 * @param  mixed  $date The date
	 * @param  string $type The type of date we're building
	 * @return object       DateTime object
	 */
	public function build_datetime( $date, $type = 'this' ) {

		$datestring = ( 'today' === $date ) ? $this->today->format( 'Y-m-d' ) : $date;
		$datetime   = new \DateTime( $datestring, $this->timezone );

		switch ( $type ) {
			case 'start':
				// Start on the sunday of the week.
				if ( 'Sun' !== $datetime->format( 'D' ) ) {
					$datetime->modify( 'last Sunday' );
				}
				break;
			case 'next':
			case 'end':
				if ( 'Sat' !== $datetime->format( 'D' ) ) {
					$datetime->modify( 'next Sunday' );
				} else {
					$datetime->modify( '+1 day' );
				}
				break;
			case 'previous':
				// For previous week, if it's Sunday we can use last Sunday. Otherwise, we need to go back to the previous Sunday.
				if ( 'Sun' !== $datetime->format( 'D' ) ) {
					$datetime->modify( 'last Sunday' );
				}
				$datetime->modify( '-1 week' );
				break;
			default:
				$datetime->modify( 'today' );
		}

		return $datetime;
	}

	/**
	 * Get the empty calendar
	 *
	 * @param  object $start_datetime Start date
	 * @param  object $end_datetime   End date
	 * @return string                 The empty calendar
	 */
	private function get_empty_calendar( $start_datetime, $end_datetime ) {
		// We can't find anything listed
		$empty = '<p>There are no shows on the air for the week starting ' . $start_datetime->format( 'F d, Y' ) . '.</p>';

		if ( $end_datetime > $this->today ) {
			// End date is in the future
			$empty .= '<p>We only project the calendar 2-4 weeks in advance as future planned airings are subject to change without notice. Jump back to <a href="/calendar/">This Week</a></p>';
		} else {
			// It's the past
			$empty .= '<p>You\'ve gone too far back! We no longer keep historical calendar records. Jump back to <a href="/calendar/">This Week</a></p>';
		}

		return $empty;
	}

	/**
	 * Get the showtime for the calendar
	 *
	 * @param  array  $show      The show
	 * @param  bool   $format    Format the time
	 *
	 * @return string|object     The showtime
	 */
	public function get_showtime( $show, $format = false ): mixed {
		$show_time = new \DateTime( '@' . $show['timestamp'] );

		if ( ! $format ) {
			return $show_time;
		}

		$timezone = $this->get_tz_abbreviation();

		return $show_time->format( '@ g:i A' ) . ' (' . $timezone . ')';
	}

	/**
	 * Get the days of the week
	 *
	 * @param  object $datetime The date
	 *
	 * @return array The days of the week
	 */
	public function get_week_of_days( $datetime = null ) {

		// "Today" is the default. If a date is passed, we use that.
		//
		// Clone it: this method walks the object forward a day at a time, and
		// callers reuse the DateTime they hand us (the list view asks for the
		// week twice - once for the subnav, once for the table).
		$today = ( null === $datetime ) ? clone $this->today : clone $datetime;

		// If today is Sunday, we start from there. Otherwise, we find the last Sunday.
		if ( 'Sun' !== $today->format( 'D' ) ) {
			$today->modify( 'last Sunday' );
		}

		$week_of_days = array();
		for ( $i = 1; $i <= 7; $i++ ) {
			if ( 1 !== $i ) {
				$today->modify( 'tomorrow' );
			}
			$week_of_days[] = $today->format( 'Y-m-d' );
		}

		return $week_of_days;
	}

	public function get_tz_abbreviation( $timezone = null ) {
		$timezone = ( null === $timezone ) ? $this->timezone : $timezone;

		$fake_time = new \DateTime( 'now', $timezone );

		return $fake_time->format( 'T' );
	}

	/**
	 * Get TVMaze error message when calendar data is unavailable
	 *
	 * @return string
	 */
	private function get_tvmaze_error_message(): string {
		$error_message  = '<div class="alert alert-warning" role="alert">';
		$error_message .= '<h4 class="alert-heading">Calendar Temporarily Unavailable</h4>';
		$error_message .= '<p>The TV schedule data is currently unavailable. This could be due to:</p>';
		$error_message .= '<ul>';
		$error_message .= '<li>TVMaze service maintenance</li>';
		$error_message .= '<li>Network connectivity issues</li>';
		$error_message .= '<li>Data synchronization delays</li>';
		$error_message .= '</ul>';
		$error_message .= '<p><strong>Please check back later.</strong></p>';
		$error_message .= '<hr>';
		$error_message .= '<p class="mb-0"><small>Powered by <a href="https://www.tvmaze.com" target="_blank">TVMaze</a></small></p>';
		$error_message .= '</div>';

		return $error_message;
	}
}
