<?php
/**
 * Name: Calendar
 * Description: Code to display the calendar
 * Version: 1.0
 */

namespace LWTV\Calendar;

use LWTV\_Components\Calendar as Build_Calendar;
use LWTV\Calendar\Display_List;

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
		// Enqueue the scripts to make the tabs work with links.
		wp_enqueue_script( 'lwtv-calendar', LWTV_PLUGIN_URL . '/assets/js/calendar-tabs.js', array( 'jquery' ), LWTV_THEME_VERSION['lwtv-underscores'], true );

		$today = $this->today->format( 'Y-m-d' );

		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification
		$date_query = ( ( strtotime( $get_tvdate ) !== false ) && ( $get_tvdate !== $today ) ) ? $get_tvdate : 'today';
		$get_tvview = isset( $_GET['tvview'] ) ? sanitize_text_field( $_GET['tvview'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification

		// Get the dates
		$start_datetime = self::build_datetime( $date_query, 'start' );
		$end_datetime   = self::build_datetime( $date_query, 'next' );
		$prev_datetime  = self::build_datetime( $date_query, 'previous' );

		/**
		 * Header
		 */
		$return = $this->get_header( $start_datetime );

		/**
		 * Calendar itself
		 */
		$cal_this_week = ( new Build_Calendar() )->generate_tvmaze_calendar( $start_datetime->format( 'Y-m-d' ) );
		$cal_next_week = ( new Build_Calendar() )->generate_tvmaze_calendar( $end_datetime->format( 'Y-m-d' ) );
		$cal_last_week = ( new Build_Calendar() )->generate_tvmaze_calendar( $prev_datetime->format( 'Y-m-d' ) );
		$calendar      = array_merge( $cal_this_week, $cal_next_week, $cal_last_week );

		// Check if we have valid calendar data
		if ( empty( $calendar ) ) {
			return $this->get_tvmaze_error_message();
		}

		ksort( $calendar );

		// If we have no shows, we need to display a message.
		if ( isset( $calendar['none'] ) || empty( $calendar ) || ! array( $calendar ) ) {
			$return .= $this->get_empty_calendar( $start_datetime, $end_datetime );
		} else {
			$return .= $this->get_tab_navigation( $calendar, $get_tvview, $date_query );
		}

		/**
		 * Footer Section.
		 */
		$return .= $this->get_footer( $date_query, $prev_datetime, $end_datetime, $get_tvview );

		return '<div class="lwtv-calendar-block">' . $return . '</div>';
	}

	/**
	 * Get the header for the calendar
	 *
	 * @param  object $start_datetime Start date
	 * @return string                 The header
	 */
	private function get_header( $start_datetime ) {
		// Make sure we always start on Sunday.
		if ( 'Sun' !== $start_datetime->format( 'D' ) ) {
			$start_datetime->modify( 'last Sunday' );
		}

		// Build out end date
		$end_datetime = clone $start_datetime;
		$end_datetime->modify( '+6 days' );

		return '<h2 class="lwtv-calendar-week">Week of ' . $start_datetime->format( 'F d, Y' ) . ' - ' . $end_datetime->format( 'F d, Y' ) . ' </h2>';
	}

	/**
	 * Get the tab navigation for the calendar
	 *
	 * @param  array  $calendar The calendar
	 * @param  string $tv_view  The view
	 * @return string           The tab navigation
	 */
	private function get_tab_navigation( $calendar, $tv_view = 'list', $date_query = 'today' ) {
		$navigation  = '<p>All times are displayed as US/Eastern, but are reflective of their original air date and time.</p>';
		$navigation .= '<p>Be advised, airdates and times are subject to change without notice. Always check your local listings.<p>';
		$navigation .= '<a name="caltop"></a>';

		$current_calendar = $this->get_current_calendar( $calendar, $date_query );

		// Get the show list.
		$show_tabs = array(
			'list'     => ( new Display_List() )->get_shows( $current_calendar, $date_query ),
			'grid'     => ( new Display_Grid() )->get_shows( $current_calendar, $date_query ),
			'calendar' => ( new Display_Calendar() )->get_shows( $current_calendar, $date_query ),
		);

		$tab_content = $this->get_tab_content( $show_tabs, $tv_view );

		$navigation .= '<div class="tab-content" id="calendarTabContent">' . $tab_content . '</div>';

		return $navigation;
	}

	/**
	 * Get the current calendar
	 *
	 * @param  array  $calendar
	 * @param  string $date_query
	 * @return array
	 */
	private function get_current_calendar( $calendar, $date_query ) {

		// See if the transient exists.
		$transient = lwtv_plugin()->get_transient( 'lwtv_calendar_' . $date_query );

		if ( false !== $transient ) {
			return $transient;
		}

		$today = $this->today->format( 'Y-m-d' );

		// Use the same logic as the Calendar view - get the full week regardless of the query
		$start_datetime = $this->build_datetime( $date_query, 'start' );
		$end_datetime   = $this->build_datetime( $date_query, 'end' );

		$start_date = $start_datetime->format( 'Y-m-d' );
		$end_date   = $end_datetime->format( 'Y-m-d' );

		$current_calendar = array_filter(
			$calendar,
			function ( $date ) use ( $start_date, $end_date ) {
				return $date >= $start_date && $date <= $end_date;
			},
			ARRAY_FILTER_USE_KEY
		);

		// Set the transient.
		lwtv_plugin()->set_transient( 'lwtv_calendar_' . $date_query, $current_calendar, 60 * 60 * 24 );

		return $current_calendar;
	}

	/**
	 * Get the tab content for the calendar
	 *
	 * @param  array  $show_tabs The tabs
	 * @param  string $tv_view   The view
	 * @return string            The tab content
	 */
	private function get_tab_content( $show_tabs, $tv_view ) {
		// Build the tabs
		$tab_list = '';

		foreach ( $show_tabs as $tab => $content ) {
			// Active tab
			$active = ( $tab === $tv_view ) ? ' active' : '';

			$tab_list .= '<li class="nav-item" role="presentation">';
			$tab_list .= '<a class="nav-link' . $active . '" id="' . $tab . '-tab" data-bs-toggle="tab" data-bs-target="#' . $tab . '-tab-pane" type="button" role="tab" aria-controls="' . $tab . '-tab-pane" aria-selected="' . ( $tv_view === $tab ? 'true' : 'false' ) . '">' . ucfirst( $tab ) . '</a>';
			$tab_list .= '</li>';
		}

		// Tab Navigation
		$tab_content = '<ul class="nav nav-tabs" id="calendarTab" role="tablist">' . $tab_list . '</ul>';

		// Tab Content
		foreach ( $show_tabs as $tab => $content ) {
			$tab_content .= '<div class="tab-pane fade' . ( $tv_view === $tab ? ' show active' : '' ) . '" id="' . $tab . '-tab-pane" role="tabpanel" aria-labelledby="' . $tab . '-tab" tabindex="0">' . $content . '</div>';
		}

		return $tab_content;
	}

	/**
	 * Get the footer for the calendar
	 *
	 * @param  string $date_query    The date we're building nav for
	 * @param  object $prev_datetime Last week
	 * @param  object $end_datetime  Next week
	 * @param  string $get_tvview    The view
	 *
	 * @return string                The footer
	 */
	private function get_footer( $date_query, $prev_datetime, $end_datetime, $get_tvview ) {
		// Add Navigation:
		$footer = $this->get_footer_navigation( $date_query, $prev_datetime->format( 'Y-m-d' ), $end_datetime->format( 'Y-m-d' ), $get_tvview );

		// Powered by:
		$footer .= '<p><small><a href="https://www.tvmaze.com" target="_new">Powered by TVMaze.</a></small></p>';

		return $footer;
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
	 * Generate navigation
	 *
	 * All dates are 'Y-m-d'
	 *
	 * @param  string $date    The date we're building nav for
	 * @param  string $last    Last week
	 * @param  string $next    Next week
	 * @param  string $tv_view The view
	 *
	 * @return string       HTML output for the navigation
	 */
	private function get_footer_navigation( $date, $last, $next, $tv_view ) {
		$today = $this->today->format( 'Y-m-d' );

		// Query Args
		$last_query_args = array(
			'tvdate' => $last,
			'tvview' => $tv_view,
		);
		$next_query_args = array(
			'tvdate' => $next,
			'tvview' => $tv_view,
		);

		// Build navigation links:
		$this_week      = add_query_arg( array( 'tvview' => $tv_view ), get_permalink() );
		$last_week      = add_query_arg( $last_query_args, get_permalink() );
		$last_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-left-circle.svg', icon: 'svg-chevron-circle-left', max_size: '14' );
		$next_week      = add_query_arg( $next_query_args, get_permalink() );
		$next_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-right-circle.svg', icon: 'svg-chevron-circle-right', max_size: '14' );

		// Last week:
		$navigation = '<nav aria-label="Calendar Navigation" role="navigation" class="yikes-pagination"><ul class="pagination justify-content-center"><li class="page-item first me-auto"><a href="' . $last_week . '" class="page-link">' . $last_week_icon . ' Last Week</a></li>';

		// We only show 'this week' when it's NOT this week
		if ( 'today' !== $date && $today !== $date ) {
			$navigation .= '<li class="page-item"><a href="' . $this_week . '" class="page-link">This Week</a></li>';
		}

		// Next week:
		$navigation .= '<li class="page-item last ms-auto"><a href="' . $next_week . '" class="page-link">Next Week ' . $next_week_icon . ' </a></li></ul></nav>';

		return $navigation;
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
	 * Get the subnav for the calendar
	 *
	 * @param  array  $calendar The calendar
	 */
	public function get_subnav( $calendar, $prefix = 'list', $date_query = null ) {

		$header = '<div class="ep-calendar-subnav p-3 list-group nav list-group-horizontal justify-content-center">';
		$today  = $this->today->format( 'Y-m-d' );

		// Loop through the days of the week.
		$week_of_days = $this->get_week_of_days( $date_query );

		foreach ( $week_of_days as $weekday ) {
			$weekday_object = new \DateTime( $weekday, $this->timezone );
			if ( isset( $calendar[ $weekday ] ) ) {
				$day        = $weekday;
				$show_day   = new \DateTime( $day, $this->timezone );
				$link_color = ( $day === $today ) ? 'link-info' : 'link-subnav';
				$header    .= '<a href="#' . $prefix . '_' . strtolower( $show_day->format( 'l' ) ) . '" class="' . $link_color . ' nav-item list-group-item-light link-offset-2 nav-link">' . $show_day->format( 'l' ) . '</a>';
			} else {
				$header .= '<span class="link-secondary nav-item list-group-item-light link-offset-2 nav-link">' . $weekday_object->format( 'l' ) . '</span>';
			}
		}

		$header .= '</div>';

		return $header;
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
		$today = ( null === $datetime ) ? $this->today : $datetime;

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
