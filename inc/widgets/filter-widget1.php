<?php

	/**
	 * Adds the Filter Container Top widget.
	 */

class Filter_Top extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'filter_top', // Base ID
			'LWTV Filter Container Top', // Name
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

		// Get what's needed from $instanse array ($instance populated with user inputs from widget form)
		$title       = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Filter';
		$title       = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$fontawesome = isset( $instance['fontawesome'] ) && ! empty( trim( $instance['fontawesome'] ) ) ? $instance['fontawesome'] : '';

		/** Output widget HTML BEGIN **/

		echo '<div class="card card-filter">
				<div class="card-header">
					<h4>';

		// If a fontawesome icon is set
		if ( $fontawesome ) {

			switch ( $fontawesome ) {
				case 'fa-television':
				case 'fa-tv':
					$icon = lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' );
					break;
				case 'fa-vcard':
				case 'fa-address-card':
					$icon = lwtv_yikes_symbolicons( 'contact-card.svg', 'fa-address-card' );
					break;
				case 'fa-users':
					$icon = lwtv_yikes_symbolicons( 'award-academy.svg', 'fa-man' );
					break;
				default:
					$icon = '<i class="fa ' . $fontawesome . ' float-right" aria-hidden="true"></i>';
			}

			echo '<span class="float-right">' . lwtv_sanitized( $icon ) . '</span>';
		}

		// If the title is set
		if ( $title ) {
			echo lwtv_sanitized( $title );
		}

		echo '</h4>
			</div>
			<div class="card-body">';

		/** Output widget HTML BEGIN **/
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */

	public function update( $new_instance, $old_instance ) {

		// Set old settings to new $instance array
		$instance = $old_instance;

		// Update each setting to new values entered by user
		$instance['title']       = wp_strip_all_tags( $new_instance['title'] );
		$instance['fontawesome'] = ( $new_instance['fontawesome'] );

		return $instance;
	}

	/**
	 * Back-end widget form.
	 */

	public function form( $instance ) {

		$title       = isset( $instance['title'] ) ? $instance['title'] : '';
		$fontawesome = isset( $instance['fontawesome'] ) ? $instance['fontawesome'] : '';
		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional)' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'fontawesome' ) ); ?>"><?php esc_html_e( 'FontAwesome Class:' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'fontawesome' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'fontawesome' ) ); ?>" type="text" value="<?php echo esc_attr( $fontawesome ); ?>" />
		</p>

		<?php
	}

}

// Register Filter_Top widget
function register_filter_top() {
	register_widget( 'Filter_Top' );
}
add_action( 'widgets_init', 'register_filter_top' );
