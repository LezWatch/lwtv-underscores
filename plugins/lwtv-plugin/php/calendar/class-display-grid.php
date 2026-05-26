<?php
/**
 * Make a grid of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\{ Calendar_Object_Pool, Calendar_Meta_Batcher };

class Display_Grid {

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_shows( $calendar, $date_query ) {

		$display = Calendar_Object_Pool::get_display();
		$today   = $display->today;
		$tz      = $display->timezone;

		$date_query_datetime = $display->build_datetime( $date_query );

		$weekly = '<div>';

		// Header Sub Navigation
		$subnav = $display->get_subnav( $calendar, 'grid', $date_query_datetime );
		// Replace list_ with grid_ to jump to the day.
		$subnav = str_replace( 'list_', 'grid_', $subnav );

		$weekly .= $subnav;

		$week_of_days = $display->get_week_of_days( $date_query_datetime );

		// Loop through the days of the week.
		foreach ( $week_of_days as $weekday ) {
			$weekday_object = new \DateTime( $weekday, $tz );

			$today_link = 'grid_' . strtolower( $weekday_object->format( 'l' ) );
			$today_date = $weekday_object->format( 'l jS' );
			if ( $weekday === $today->format( 'Y-m-d' ) ) {
				$today_date .= '&nbsp;&nbsp;<button type="button" class="btn btn-info btn-sm" disabled><a name="today">Today</a></button>';
			}

			$is_when = array(
				'today' => ( $weekday === $today->format( 'Y-m-d' ) ) ? true : false,
				'past'  => ( $weekday_object < $today ) ? true : false,
				'soon'  => ( $weekday_object > $today ) ? true : false,
			);

			// Build the Day Header:
			$weekly .= '<div class="ep-calendar-day dayjump" id="' . $today_link . '" tabindex="-1" data-date="' . $weekday_object->format( 'Y-m-d' ) . '">';
			$weekly .= '<h3 class="ep-calendar-day-heading">' . $today_date . '</h3>';
			$weekly .= '<div class="container text-center"><div class="row row-cols-1 row-cols-md-3 g-4">';

			// If we have no shows, we need to display a message.
			if ( ! isset( $calendar[ $weekday ] ) ) {
				$weekly .= '<p><em>No shows on this day.</em></p>';
			} else {
				// Otherwise, we build the grid!
				foreach ( $calendar[ $weekday ] as $show ) {
					// Use pre-processed data from Data Processor
					$show_name   = $show['show_name'];
					$show_id     = $show['show_id'];
					$lwtv_date   = $show['time_data']['lwtv_date'];
					$native_date = $this->get_native_date( $show );

					// Build output
					$show_content = '';
					if ( is_array( $show['title'] ) ) {
						$show_content .= $this->display_card_grid_multiple( $show, $tz, $is_when );
					} else {
						$show_content .= $this->display_card_grid( $show, $tz, $is_when );
					}

					$weekly .= $show_content;
				}
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
	 * @param  array  $is_when
	 *
	 * @return string
	 */
	private function display_card_grid( array $show, object $tz, array $is_when ): string {
		$image       = ( isset( $show['show_id'] ) ) ? Calendar_Meta_Batcher::get_thumbnail( $show['show_id'], 'thumbnail', array( 'class' => 'calendar-show-img card-img' ) ) : '';
		$lwtv_date   = $show['time_data']['lwtv_date'];
		$native_date = $this->get_native_date( $show );

		$card_class = match ( true ) {
			$is_when['today'] => 'card border-info',
			$is_when['soon']  => 'card border-secondary',
			default           => 'card',
		};

		$head_class = ( $is_when['today'] ) ? 'card-header bg-info' : 'card-header';

		$card  = '<div class="col ep-calendar-grid-col"><div class="container ep-calendar-weekly">';
		$card .= '<div class="' . $card_class . '" style="width: 18rem; display: inline-block;">
			<div class="' . $head_class . '"><strong>' . $lwtv_date . $native_date . $show['episode_badge'] . '</strong></div>
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
	 * @param  array  $is_when
	 *
	 * @return string
	 */
	private function display_card_grid_multiple( array $show, object $tz, array $is_when ): string {
		$image       = ( isset( $show['show_id'] ) ) ? Calendar_Meta_Batcher::get_thumbnail( $show['show_id'], 'thumbnail', array( 'class' => 'calendar-show-img card-img' ) ) : '';
		$lwtv_date   = $show['time_data']['lwtv_date'];
		$native_date = $this->get_native_date( $show );

		$card_class = match ( true ) {
			$is_when['today'] => 'card border-info',
			$is_when['soon']  => 'card border-secondary',
			default           => 'card',
		};

		$head_class = ( $is_when['today'] ) ? 'card-header bg-info' : 'card-header';

		// Build episode list
		$episodes       = count( $show['title'] );
		$ep_badge_class = ( $is_when['today'] ) ? 'badge text-bg-info' : 'badge text-bg-secondary';
		$episodes_badge = ' <span class="' . $ep_badge_class . ' rounded-pill">' . $episodes . ' Episodes</span>';

		$card  = '<div class="col ep-calendar-grid-col"><div class="container ep-calendar-weekly">';
		$card .= '<div class="' . $card_class . '" style="width: 18rem; display: inline-block;">
			<div class="' . $head_class . '"><strong>' . $lwtv_date . $native_date . '</strong></div>
			<div class="card-body" style="flex-direction: row;">
				' . $image . '
				<p class="card-title">' . $show['show_name'] . '</p>
				<p class="card-text"><small><ul class="list-unstyled mb-0">' . $episodes_badge . '</ul></small></p>
			</div>
		</div>';
		$card .= '</div></div>';

		return $card;
	}

	/**
	 * Get native timezone date for display
	 *
	 * @param  array $show Processed show data
	 * @return string
	 */
	private function get_native_date( array $show ): string {
		if ( empty( $show['native_tz'] ) ) {
			return '';
		}

		// Validate timezone before using it
		if ( ! in_array( $show['native_tz'], timezone_identifiers_list(), true ) ) {
			return '';
		}

		$date = new \DateTime( '@' . $show['timestamp'] );
		$date->setTimeZone( new \DateTimeZone( LWTV_TIMEZONE ) );
		$native_tz_time = new \DateTime( '@' . $show['timestamp'] );
		$native_tz_time->setTimeZone( new \DateTimeZone( $show['native_tz'] ) );

		return ( $date->format( 'T' ) !== $native_tz_time->format( 'T' ) ) ? ' / ' . $native_tz_time->format( '@ H:i' ) . ' (' . $native_tz_time->format( 'T' ) . ')' : '';
	}
}
