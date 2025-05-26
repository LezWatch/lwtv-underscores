<?php

	/**
	 * Adds the Filter Container Top widget.
	 */

class LWTV_Filter_Top_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'filter_top', // Base ID
			'LWTV Filter Container Top', // Name
			array( 'description' => __( 'Used to wrap Show/Character filters.', 'lwtv-underscores' ) ) // Args
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

		// Get what's needed from $instance array ($instance populated with user inputs from widget form)
		$title      = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Filter';
		$title      = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$symbolicon = isset( $instance['symbolicon'] ) && ! empty( trim( $instance['symbolicon'] ) ) ? $instance['symbolicon'] : '';

		/** Output widget HTML BEGIN **/

		echo '<div class="card card-filter">
				<div class="card-header">
					<h4>';

		// If a symbolicon icon is set
		if ( $symbolicon ) {
			switch ( $symbolicon ) {
				case 'fa-television':
				case 'fa-tv':
					$icon = lwtv_plugin()->get_symbolicon( svg: 'tv-hd.svg', icon: 'svg-tv' );
					break;
				case 'fa-vcard':
				case 'fa-address-card':
					$icon = lwtv_plugin()->get_symbolicon( svg: 'contact-card.svg', icon: 'svg-address-card' );
					break;
				case 'fa-users':
					$icon = lwtv_plugin()->get_symbolicon( svg: 'award-academy.svg', icon: 'svg-man' );
					break;
				default:
					$symbolicon = str_replace( 'fa-', 'svg-', $symbolicon );
					$icon       = '<span class="' . $symbolicon . ' float-right" aria-hidden="true"></span>';
			}

			// phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="float-right">' . $icon . '</span>&nbsp;';
		}

		// If the title is set
		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput
			echo $title;
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
		$instance['title']      = wp_strip_all_tags( $new_instance['title'] );
		$instance['symbolicon'] = ( $new_instance['symbolicon'] );

		return $instance;
	}

	/**
	 * Back-end widget form.
	 */
	public function form( $instance ) {

		$title      = isset( $instance['title'] ) ? $instance['title'] : '';
		$symbolicon = isset( $instance['symbolicon'] ) ? $instance['symbolicon'] : '';
		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional)' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'symbolicon' ) ); ?>"><?php esc_html_e( 'FontAwesome Class:' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'symbolicon' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'symbolicon' ) ); ?>" type="text" value="<?php echo esc_attr( $symbolicon ); ?>" />
		</p>

		<?php
	}
}

// Register LWTV_Filter_Top_Widget widget
function register_filter_top() { // phpcs:ignore
	register_widget( 'LWTV_Filter_Top_Widget' );
}
add_action( 'widgets_init', 'register_filter_top' );
