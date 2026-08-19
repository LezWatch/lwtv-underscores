<?php

/**
 * Adds The LWTV Calendar widget.
 */

use LWTV\_Components\Calendar as Build_Calendar;
use LWTV\Calendar\Data_Processor;

class LWTV_Calendar_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'lwtv_calendar', // Base ID
			'LWTV Calendar', // Name
			array( 'description' => __( 'Displays today\'s calendar.', 'lwtv-underscores' ) ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 */
	public function widget( $args, $instance ) {
		// Get what's needed from $args array ($args populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		$calendar = $this->get_calendar();

		// Get what's needed from $instance array ($instance populated with user inputs from widget form)
		$title = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Newest Show';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo $before_widget;

		// Display the calendar.

		echo '<div class="card">';
		echo '<div class="card-header"><h4><span class="float-left">' . lwtv_plugin()->get_symbolicon( 'calendar-alt.svg', 'fa-calendar-alt' ) . '</span> On Today</h4></div>';

		echo '<div class="card-body">';
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo $calendar;
		echo '</div>';
		echo '</div>';

		/** Output widget HTML END **/
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo $after_widget;
	}

	/**
	 * Get the calendar.
	 *
	 * @return string The calendar.
	 */
	private function get_calendar() {
		$calendar = ( new Build_Calendar() )->generate_tvmaze_calendar( 'today', 'day' );

		$today = gmdate( 'Y-m-d' );

		// Check if calendar data is available
		if ( empty( $calendar ) ) {
			return '<div class="alert alert-warning">' . __( 'Calendar data temporarily unavailable. Please check back later.', 'lwtv-underscores' ) . '</div>';
		}

		// Process calendar data using Data Processor
		$data_processor     = new Data_Processor();
		$processed_calendar = $data_processor->process_calendar_data( $calendar, 'today' );

		if ( ! isset( $processed_calendar[ $today ] ) ) {
			return '<div class="alert alert-info">' . __( 'No Shows Found for today', 'lwtv-underscores' ) . '</div>';
		}

		$shows = $processed_calendar[ $today ];

		// Output the calendar.
		$output     = '<div class="list-group">';
		$show_count = 0;
		foreach ( $shows as $show ) {
			if ( 3 === $show_count ) {
				$more_count = count( $shows ) - 3;
				/* translators: %d: number of additional shows to view */
				$output .= '<a class="list-group-item list-group-item-primary text-center" data-bs-toggle="collapse" data-bs-target="#collapseCalendar" aria-expanded="false" aria-controls="collapseCalendar">' . sprintf( _n( 'View %d More Show', 'View %d More Shows', $more_count, 'lwtv-underscores' ), $more_count ) . '</a>';
				$output .= '<div class="collapse" id="collapseCalendar">';
			}

			// Use pre-processed data from Data Processor. show_link is
			// pre-escaped markup, and already points at the right permalink -
			// building a /show/<ID>/ URL here produced a broken link.
			$show_link = $show['show_link'];
			$lwtv_date = $show['time_data']['lwtv_date'];
			$episodes  = $show['episode_badge'];

			$output .= '<li class="list-group-item">' . $show_link . ' ' . $lwtv_date . $episodes . '</li>';

			++$show_count;
		}

		if ( $show_count > 3 ) {
			$output .= '</div>';
		}

		$output .= '<a class="list-group-item list-group-item-secondary text-center" href="' . home_url( '/calendar/' ) . '"><strong>' . __( 'View Full Calendar', 'lwtv-underscores' ) . '</strong></a>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */
	public function update( $new_instance, $old_instance ) {

		// Set old settings to new $instance array
		$instance = $old_instance;

		// Update each setting to new values entered by user
		$instance['title'] = wp_strip_all_tags( $new_instance['title'] );

		return $instance;
	}

	/**
	 * Back-end widget form.
	 */
	public function form( $instance ) {

		$title = isset( $instance['title'] ) ? $instance['title'] : '';

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional)' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}
}

// Register LWTV_Calendar_Widget widget
function register_lwtv_calendar() { // phpcs:ignore
	register_widget( 'LWTV_Calendar_Widget' );
}
add_action( 'widgets_init', 'register_lwtv_calendar' );

