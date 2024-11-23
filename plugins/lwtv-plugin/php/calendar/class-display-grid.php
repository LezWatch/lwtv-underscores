<?php
/**
 * Make a grid of all the shows on a specific day for a week.
 */

namespace LWTV\Calendar;

class Display_Grid {

	/**
	 * Generate the weekly list of shows.
	 *
	 * @param  array  $calendar
	 * @param  object $today
	 * @param  object $tz
	 * @return string
	 */
	public function get_shows( $calendar, $today, $tz ) {
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
			$weekly .= '<h3 class="ep-calendar-day-heading" id="' . $today_link . '"></br>' . $today_date . '</h3>';

			$weekly .= '<div class="container text-center"><div class="row row-cols-1 row-cols-md-3 g-4">';

			$is_when = array(
				'today' => ( $day === $today->format( 'Y-m-d' ) ) ? true : false,
				'past'  => ( $show_day < $today ) ? true : false,
				'soon'  => ( $show_day > $today ) ? true : false,
			);

			foreach ( $shows as $show ) {

				// Show Name (may be URL if we have a link)
				$show['show_name'] = lwtv_plugin()->get_show_name_for_calendar( $show['show_name'], 'tvmaze' );
				$show['show_id']   = lwtv_plugin()->get_show_name_for_calendar( $show['show_name'], 'lwtv', 'id' );
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

		$card  = '<div class="col ep-calendar-grid-col"><div class="container ep-calendar-weekly">';
		$card .= '<div class="' . $card_class . '" style="width: 18rem; display: inline-block;">
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
}
