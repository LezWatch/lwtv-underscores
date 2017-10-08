<?php

	/**
	 * Adds My Awesome widget.
	 */

class My_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'my_widget', // Base ID
			'My Awesome Widget', // Name
			array( 'description' => __( 'My Awesome widget I made myself', 'yikes_starter' ) ) // Args
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
		$title     = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'YIKES Example Widget';
		$title     = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$textarea  = isset( $instance['textarea'] ) && ! empty( trim( $instance['textarea'] ) ) ? $instance['textarea'] : '';
		$textarea2 = isset( $instance['textarea2'] ) && ! empty( trim( $instance['textarea2'] ) ) ? $instance['textarea2'] : '';

		/** Output widget HTML BEGIN **/
		echo $before_widget;
		echo '<ul>';

		// If the title is set
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		// If text is entered in the first textarea
		if ( $textarea ) {
			echo '	<li>' . $textarea . '</li>';
		}

		// If text is entered in the second textarea
		if ( $textarea2 ) {
			echo '	<li>' . $textarea2 . '</li>';
		}

		echo '</ul>';
		echo $after_widget;
		/** Output widget HTML BEGIN **/
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */

	public function update( $new_instance, $old_instance ) {

		// Set old settings to new $instance array
		$instance = $old_instance;

		// Update each setting to new values entered by user
		$instance['title']     = strip_tags( $new_instance['title'] );
		$instance['textarea']  = ($new_instance['textarea']);
		$instance['textarea2'] = ($new_instance['textarea2']);

		return $instance;
	}

	/**
	 * Back-end widget form.
	 */

	public function form( $instance ) {

		$title     = isset( $instance['title'] ) ? $instance['title'] : '';
		$textarea  = isset( $instance['textarea'] ) ? $instance['textarea'] : '';
		$textarea2 = isset( $instance['textarea2'] ) ? $instance['textarea2'] : '';

	?>

	<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional)' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
	</p>

	<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'textarea' ) ); ?>"><?php esc_html_e( 'Enter text below:' ); ?></label>
		<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'textarea' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'textarea' ) ); ?>"><?php echo esc_html( $textarea ); ?></textarea>
	</p>

	<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'textarea2' ) ); ?>"><?php esc_html_e( 'Enter more text below:' ); ?></label>
		<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'textarea2' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'textarea2' ) ); ?>"><?php echo esc_html( $textarea2 ); ?></textarea>
	</p>

	<?php
	}

}

// Register My_Widget widget
function register_my_widget() {
	register_widget( 'My_Widget' );
}
add_action( 'widgets_init', 'register_my_widget' );