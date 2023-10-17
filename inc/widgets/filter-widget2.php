<?php

	/**
	 * Adds the Filter Container Top widget.
	 */

class Filter_Bottom extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'filter_bottom', // Base ID
			'LWTV Filter Container Bottom', // Name
			array( 'description' => __( 'Used to wrap Show/Character filters.', 'yikes_starter' ) ) // Args
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

		/** Output widget HTML BEGIN **/

		echo '	</div><!-- .card-body -->
		</div><!-- .card -->';

		/** Output widget HTML BEGIN **/
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */

	public function update( $new_instance, $old_instance ) {

		// Set old settings to new $instance array
		$instance = $old_instance;

		// Update each setting to new values entered by user

		return $instance;
	}

	/**
	 * Back-end widget form.
	 */

	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? $instance['title'] : '';
		$fontawesome = isset( $instance['fontawesome'] ) ? $instance['fontawesome'] : '';
	}
}

// Register Filter_Bottom widget
function register_filter_bottom() { // phpcs:ignore
	register_widget( 'Filter_Bottom' );
}
add_action( 'widgets_init', 'register_filter_bottom' );
