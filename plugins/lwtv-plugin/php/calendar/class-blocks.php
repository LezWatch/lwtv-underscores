<?php
/**
 * Name: Calendar
 * Description: Code to display the calendar
 * Version: 1.0
 */

namespace LWTV\Calendar;

class Blocks {

	/**
	 * Make the Calendar
	 *
	 * @return string
	 */
	public function make() {
		// Build out start and end dates.
		$tz    = new \DateTimeZone( LWTV_TIMEZONE );
		$today = new \DateTime( 'today', $tz );

		// Query Variables.
		$get_tvdate = isset( $_GET['tvdate'] ) ? sanitize_text_field( $_GET['tvdate'] ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification
		$date_query = ( ( strtotime( $get_tvdate ) !== false ) && ( $get_tvdate !== $today->format( 'Y-m-d' ) ) ) ? $get_tvdate : 'today';

		// Get the dates
		$start_datetime = self::start_datetime( $date_query, $tz );
		$end_datetime   = self::end_datetime( $date_query, $tz );
		$prev_datetime  = self::prev_datetime( $date_query, $tz );

		/**
		 * Header
		 */
		$return = $this->get_header( $start_datetime, $end_datetime );

		/**
		 * Calendar itself
		 */
		$calendar = lwtv_plugin()->generate_tvshow_calendar( $start_datetime->format( 'Y-m-d' ) );

		// If we have no shows, we need to display a message.
		if ( isset( $calendar['none'] ) || empty( $calendar ) || ! array( $calendar ) ) {
			$return .= $this->get_empty_calendar( $start_datetime, $end_datetime, $today );
		} else {
			$return .= $this->get_tab_navigation( $calendar, $today, $tz );
		}

		/**
		 * Footer Section.
		 */
		$return .= $this->get_footer( $date_query, $today, $prev_datetime, $end_datetime );

		return '<div class="lwtv-calendar-block">' . $return . '</div>';
	}

	/**
	 * Get the header for the calendar
	 *
	 * @param  object $start_datetime Start date
	 * @param  object $end_datetime   End date
	 * @return string                 The header
	 */
	private function get_header( $start_datetime, $end_datetime ) {
		return '<h2 class="lwtv-calendar-week">Week of ' . $start_datetime->format( 'F d, Y' ) . ' - ' . $end_datetime->format( 'F d, Y' ) . ' </h2>';
	}

	/**
	 * Get the tab navigation for the calendar
	 *
	 * @param  array  $calendar The calendar
	 * @param  object $today    Today's date
	 * @param  object $tz       Timezone
	 * @return string           The tab navigation
	 */
	private function get_tab_navigation( $calendar, $today, $tz ) {
		$navigation  = '<p>All times are displayed as US/Eastern, but are reflective of their original air date and time.</p>';
		$navigation .= '<p>Be advised, airdates and times are subject to change without notice. Always check your local listings.<p>';

		// Get the show list.
		$show_tabs = array(
			'list'     => $this->get_shows_list( $calendar, $today, $tz ),
			'grid'     => $this->get_shows_grid( $calendar, $today, $tz ),
			'calendar' => $this->get_shows_calendar( $calendar, $today, $tz ),
		);

		// Build the tabs
		$tab_list = '';
		foreach ( $show_tabs as $tab => $content ) {
			$tab_list .= '<li class="nav-item" role="presentation">';
			$tab_list .= '<a class="nav-link' . ( 'list' === $tab ? ' active' : '' ) . '" id="' . $tab . '-tab" data-bs-toggle="tab" data-bs-target="#' . $tab . '-tab-pane" type="button" role="tab" aria-controls="' . $tab . '-tab-pane" aria-selected="' . ( 'list' === $tab ? 'true' : 'false' ) . '">' . ucfirst( $tab ) . '</a>';
			$tab_list .= '</li>';
		}
		$navigation .= '<ul class="nav nav-tabs" id="myTab" role="tablist">' . $tab_list . '</ul>';

		// Tab Content
		$tab_content = '';
		foreach ( $show_tabs as $tab => $content ) {
			$tab_content .= '<div class="tab-pane fade' . ( 'list' === $tab ? ' show active' : '' ) . '" id="' . $tab . '-tab-pane" role="tabpanel" aria-labelledby="' . $tab . '-tab" tabindex="0">' . $content . '</div>';
		}
		$navigation .= '<div class="tab-content" id="myTabContent">' . $tab_content . '</div>';

		return $navigation;
	}

	/**
	 * Get the footer for the calendar
	 *
	 * @param  string $date_query    The date we're building nav for
	 * @param  object $today         Today's date
	 * @param  object $prev_datetime Last week
	 * @param  object $end_datetime  Next week
	 * @return string                The footer
	 */
	private function get_footer( $date_query, $today, $prev_datetime, $end_datetime ) {
		// NEXT week: Since we set this to Saturday, we have to add a day for the links.
		$end_datetime->modify( '+1 day' );

		// Change today so we can check if the 'this week' button is needed
		$today->modify( 'last Sunday' );

		// Add Navigation:
		$footer = $this->get_footer_navigation( $date_query, $today->format( 'Y-m-d' ), $prev_datetime->format( 'Y-m-d' ), $end_datetime->format( 'Y-m-d' ) );

		// Powered by:
		$footer .= '<p><small><a href="https://www.tvmaze.com" target="_new">Powered by TVMaze.</a></small></p>';

		return $footer;
	}

	/**
	 * Start Date/Time
	 *
	 * This will set up the start of the week. It's always Sunday.
	 *
	 * @param  string $date The date
	 * @return object       DateTime object
	 */
	public function start_datetime( $date, $tz ) {
		$start_datetime = new \DateTime( $date, $tz );

		// If it's not Sunday, we want the previous Sunday
		if ( 'Sun' !== $start_datetime->format( 'D' ) ) {
			$start_datetime->modify( 'last Sunday' );
		}

		return $start_datetime;
	}

	/**
	 * End Date/Time
	 *
	 * This will set up the End of the week. It's always Saturday.
	 *
	 * @param  string $date The date
	 * @return object       DateTime object
	 */
	public function end_datetime( $date, $tz ) {
		$end_datetime = new \DateTime( $date, $tz );

		// If it's not Saturday, we want to jump to the next one
		if ( 'Sat' !== $end_datetime->format( 'D' ) ) {
			$end_datetime->modify( 'next Saturday' );
		}

		return $end_datetime;
	}

	/**
	 * Previous Date/Time
	 *
	 * This will set up the start of the PREVIOUS week. It's always Sunday.
	 *
	 * @param  string $date The date
	 * @return object       DateTime object
	 */
	public function prev_datetime( $date, $tz ) {
		$prev_datetime = new \DateTime( $date, $tz );

		// If it's not Sunday, we want the previous Sunday
		if ( 'Sun' !== $prev_datetime->format( 'D' ) ) {
			$prev_datetime->modify( 'last Sunday' );
		}

		// Now we need to jump back to the previous week...
		$prev_datetime->modify( '1 week ago' );

		return $prev_datetime;
	}

	/**
	 * Generate correct show name in order to be linked
	 * @param  string $name Pretty name of show
	 * @return string       Pretty Name with URL (if exists)
	 */
	public function show_name( $name, $output = 'name' ) {
		return lwtv_plugin()->get_show_name_for_calendar( $name, 'tvmaze', $output );
	}

	/**
	 * Generate navigation
	 *
	 * All dates are 'Y-m-d'
	 *
	 * @param  string $date  The date we're building nav for
	 * @param  string $today Today's date
	 * @param  string $last  Last week
	 * @param  string $next  Next week
	 * @return string       HTML output for the navigation
	 */
	private function get_footer_navigation( $date, $today, $last, $next ) {
		// echo previous and next links:
		$last_week      = add_query_arg( 'tvdate', $last, get_permalink() );
		$last_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-left-circle.svg', fontawesome: 'fa-chevron-circle-left' );
		$next_week      = add_query_arg( 'tvdate', $next, get_permalink() );
		$next_week_icon = lwtv_plugin()->get_symbolicon( svg: 'caret-right-circle.svg', fontawesome: 'fa-chevron-circle-right' );

		$navigation = '<nav aria-label="Calendar Navigation" role="navigation" class="yikes-pagination"><ul class="pagination justify-content-center"><li class="page-item first me-auto"><a href="' . $last_week . '" class="page-link">' . $last_week_icon . ' Last Week</a></li>';

		// ... We only show 'this week' when it's NOT this week
		if ( 'today' !== $date && $today !== $date ) {
			$navigation .= '<li class="page-item"><a href="/calendar/" class="page-link">This Week</a></li>';
		}

		$navigation .= '<li class="page-item last ms-auto"><a href="' . $next_week . '" class="page-link">Next Week ' . $next_week_icon . ' </a></li></ul></nav>';

		return $navigation;
	}

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	private function get_shows_grid( $calendar, $today, $tz ) {
		$weekly = '<div>';

		// Header Sub Navigation
		$weekly .= '<div class="ep-calendar-subnav text-bg-light p-3"><ul class="nav justify-content-center">';
		foreach ( $calendar as $day => $shows ) {
			$show_day = new \DateTime( $day, $tz );

			$link_color = ( $day === $today->format( 'Y-m-d' ) ) ? 'link-info' : 'link-dark';

			$weekly .= '<li class="nav-item"><a class="' . $link_color . ' link-offset-2 nav-link" href="#' . strtolower( $show_day->format( 'ldS' ) ) . '">' . $show_day->format( 'l' ) . '</a></li>';
		}
		$weekly .= '</ul></div>';

		// Grid Itself.
		foreach ( $calendar as $day => $shows ) {
			$show_day = new \DateTime( $day, $tz );

			$today_link = strtolower( $show_day->format( 'ldS' ) );
			$today_date = $show_day->format( 'l dS' );
			if ( $day === $today->format( 'Y-m-d' ) ) {
				$today_date .= '&nbsp;&nbsp;<button type="button" class="btn btn-info btn-sm" disabled><a name="today">Today</a></button>';
			}

			$weekly .= '<div class="ep-calendar-day">';
			$weekly .= '<h3 class="ep-calendar-day-heading"><a name="' . $today_link . '">&nbsp;</a></br>' . $today_date . '</h3>';

			$weekly .= '<div class="container text-center"><div class="row row-cols-1 row-cols-md-2 g-4">';

			$is_when = array(
				'today' => ( $day === $today->format( 'Y-m-d' ) ) ? true : false,
				'past'  => ( $show_day < $today ) ? true : false,
				'soon'  => ( $show_day > $today ) ? true : false,
			);

			foreach ( $shows as $show ) {

				// Show Name (may be URL if we have a link)
				$show['show_name'] = self::show_name( $show['show_name'] );
				$show['show_id']   = self::show_name( $show['show_name'], 'lwtv', 'id' );

				// Show Timezone
				$show['native_tz'] = lwtv_plugin()->get_tvmaze_show_timezone( $show['show_id'] ) ?? '';

				// Build output
				$show_content = '';
				if ( is_array( $show['title'] ) ) {
					$show_content .= $this->display_card_grid_multiple( $show, $tz, $is_when );
				} else {
					$show_content .= $this->display_card_grid( $show, $tz, $is_when );
				}

				$weekly .= $show_content;
			}

			$weekly .= '</div></div>'; // row, container

			$weekly .= '</div>'; // ep-calendar-day
		}
		$weekly .= '</div>';
		return $weekly;
	}

	/**
	 * Generate display for single episode in a day.
	 *
	 * @param  array  $show
	 * @param  object $tz
	 * @param  array   $is_today
	 *
	 * @return string
	 */
	private function display_card_grid( array $show, object $tz, array $is_when ): string {
		$image     = ( isset( $show['show_id'] ) ) ? get_the_post_thumbnail( $show['show_id'], array( 100, 100, true ), array( 'class' => 'calendar-show-img card-img' ) ) : '';
		$date      = new \DateTime( '@' . $show['timestamp'] );
		$show_time = new \DateTime( '@' . $show['timestamp'], $tz );
		$date->setTimeZone( new \DateTimeZone( LWTV_TIMEZONE ) );

		$lwtv_date   = $show_time->format( '@ g:i A' ) . ' (' . $date->format( 'T' ) . ')';
		$native_date = '';
		if ( ! empty( $show['native_tz'] ) ) {
			$native_tz_time = new \DateTime( '@' . $show['timestamp'] );
			$native_tz_time->setTimeZone( new \DateTimeZone( $show['native_tz'] ) );
			$native_date = ( $date->format( 'T' ) !== $native_tz_time->format( 'T' ) ) ? ' / ' . $native_tz_time->format( '@ H:i' ) . ' (' . $native_tz_time->format( 'T' ) . ')' : '';
		}

		$card_class = match ( true ) {
			$is_when['today'] => 'card border-info',
			$is_when['soon']  => 'card border-secondary',
			default           => 'card',
		};

		$head_class = ( $is_when['today'] ) ? 'card-header bg-info' : 'card-header';

		$card  = '<div class="col"><div class="container ep-calendar-weekly">';
		$card .= '<div class="' . $card_class . '" style="width: 18rem;">
			<div class="' . $head_class . '"><strong>' . $lwtv_date . $native_date . '</strong></div>
			<div class="card-body" style="flex-direction: row;">
				' . $image . '
				<p class="card-title">' . $show['show_name'] . '</p>
				<p class="card-text"><small>' . $show['title'] . '</small></p>
			</div>
		</div>';
		$card .= '</div></div>';

		return $card;
	}

	/**
	 * Generate display for multiple episodes for a show in a day.
	 *
	 * @param  array  $show
	 * @param  object $tz
	 * @param  array   $is_today
	 *
	 * @return string
	 */
	private function display_card_grid_multiple( array $show, object $tz, array $is_when ): string {
		$all_episodes = '';
		foreach ( $show['title'] as $one_show ) {
			$episode = array(
				'show_name' => $show['show_name'],
				'title'     => $one_show,
				'timestamp' => $show['timestamp'],
				'show_id'   => $show['show_id'],
			);

			$all_episodes .= $this->display_card_grid( $episode, $tz, $is_when );
		}

		return $all_episodes;
	}

	/**
	 * Generate the weekly calendar of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	private function get_shows_calendar( $calendar, $today, $tz ) {

		if ( ! $calendar || ! $tz ) {
			return '<p>There are no shows on the air for the week starting ' . $today->format( 'F d, Y' ) . '.</p>';
		}

		$table = '<table class="table">';

		$table .= '</table>';

		return $table;
	}

	/**
	 * Generate the list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	private function get_shows_list( $calendar, $today, $tz ) {

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
				$date      = new \DateTime( '@' . $show['timestamp'] );
				$show_time = new \DateTime( '@' . $show['timestamp'], $tz );
				$date->setTimeZone( new \DateTimeZone( LWTV_TIMEZONE ) );
				$lwtv_date = $show_time->format( '@ g:i A' ) . ' (' . $date->format( 'T' ) . ')';

				// Determine if the show is airing now, soon, or later.
				$dot_time = ( $show_time < $today ) ? 'ep-calendar-dot ep-calendar-dot-past' : 'ep-calendar-dot';

				// Show Name (may be URL if we have a link)
				$show['show_name'] = self::show_name( $show['show_name'] );
				$show['show_id']   = self::show_name( $show['show_name'], 'lwtv', 'id' );
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

		return $table;
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

	/**
	 * Get the empty calendar
	 *
	 * @param  object $start_datetime Start date
	 * @param  object $end_datetime   End date
	 * @param  object $today          Today's date
	 * @return string                 The empty calendar
	 */
	private function get_empty_calendar( $start_datetime, $end_datetime, $today ) {
		// We can't find anything listed
		$empty = '<p>There are no shows on the air for the week starting ' . $start_datetime->format( 'F d, Y' ) . '.</p>';

		if ( $end_datetime > $today ) {
			// End date is in the future
			$empty .= '<p>We only project the calendar 2-4 weeks in advance. Future planned airings are subject to change without notice.</p>';
		} else {
			// It's the past
			$empty .= '<p>We don\'t keep historical calendar records, so you won\'t be able to retrieve listings from long ago. Sorry.</p>';
		}

		return $empty;
	}
}
