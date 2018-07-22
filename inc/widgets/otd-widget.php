<?php

/**
 * Adds The LWTV Of the Day widget.
 */

class LWTV_OTD_Widget extends WP_Widget {

	/**
	 * Holds widget settings defaults, populated in constructor.
	 */
	protected $defaults;
	protected $valid_types;

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {

		$this->valid_types = array( 'character', 'show' );
		$this->defaults    = array(
			'title' => 'Of The Day',
			'type'  => 'character',
		);
		$widget_ops        = array(
			'classname'   => 'widget-lwtv-of-the-day',
			'description' => 'Displays the character or show of the day.',
		);
		$control_ops       = array(
			'id_base' => 'lwtv-of-the-day',
		);

		parent::__construct( 'lwtv-of-the-day', 'LWTV Of The Day', $widget_ops, $control_ops );
	}

	/**
	 * Front-end display of widget.
	 */

	public function widget( $args, $instance ) {
		$instance  = wp_parse_args( (array) $instance, $this->defaults );
		$type      = ( ! empty( $instance['type'] ) ) ? $instance['type'] : 'character';
		$otd_array = get_option( 'lwtv_otd' );
		$the_post  = $otd_array[ $type ]['post'];

		// Get what's needed from $args array ($args populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		// Get what's needed from $instance array ($instance populated with user inputs from widget form)
		$title = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Of This Day ...';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		echo wp_kses_post( $before_widget );

		switch ( $type ) {
			case 'character':
				$title = 'Character of the Day';
				$icon  = lwtv_yikes_symbolicons( 'contact-card.svg', 'fa-address-card' );
				$thumb = 'character-img';
				$text  = lwtv_yikes_chardata( $the_post, 'oneshow' ) . lwtv_yikes_chardata( $the_post, 'oneactor' );
				break;
			case 'show':
				$title = 'Show of the Day';
				$icon  = lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' );
				$thumb = 'postloop-img';
				$text  = ''; // There's a lot, keep reading...
				// Stations
				$stations = get_the_terms( $the_post, 'lez_stations' );
				if ( $stations ) {
					$text          .= '<div class="card-meta-item"><strong>Airs On:</strong> ';
					$station_string = '';
					foreach ( $stations as $station ) {
						$station_string .= $station->name . ', ';
					}
					$text .= trim( $station_string, ', ' ) . '</div>';
				}

				// Airdates
				$field   = get_post_meta( $the_post, 'lezshows_airdates', true );
				$start   = isset( $field['start'] ) ? $field['start'] : '';
				$finish  = isset( $field['finish'] ) ? $field['finish'] : '';
				$airdate = $start . ' - ' . $finish;
				if ( $start === $finish || '' === $finish ) {
					$airdate = $start;
				}
				$text .= '<div class="card-meta-item"><strong>Airdates:</strong> ' . $airdate . '</div>';

				// Excerpt
				$text .= '<div class="card-excerpt">' . get_the_excerpt( $the_post ) . '</div>';

				break;
		}

		$thumb_attribution = get_post_meta( get_post_thumbnail_id( $the_post ), 'lwtv_attribution', true );
		$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $the_post ) : get_the_title( $the_post ) . ' &copy; ' . $thumb_attribution;

		echo '<div class="card">';
			echo '<div class="card-header"><h4>' . esc_html( $title ) . '<span class="float-right">' . lwtv_sanitized( $icon ) . '</span></h4></div>';

			// Featured Image
			echo '<div class="' . esc_attr( $type ) . '-image-wrapper">';
			echo '<a href="' . esc_url( get_the_permalink() ) . '">';
			echo get_the_post_thumbnail( $the_post, $thumb, array(
				'class' => 'card-img-top',
				'alt'   => $thumb_title,
				'title' => $thumb_title,
			) );
			echo '</a>';
			echo '</div>';

			echo '<div class="card-body">';
				// Title
				echo '<h4 class="card-title">' . get_the_title( $the_post ) . '</h4>';
				echo '<div class="card-text">';
				echo wp_kses_post( $text );
				echo '</div>
			</div>';

			// Button
			echo '<div class="card-footer">
				<a href="' . esc_url( get_the_permalink() ) . '" class="btn btn-outline-primary">' . esc_attr( ucfirst( $type ) ) . ' Profile</a>
			</div>';

		echo '</div>';

		echo wp_kses_post( $after_widget );

	}

	/**
	 * Update a particular instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 * @return array Settings to save or bool false to cancel saving
	 */
	public function update( $new_instance, $old_instance ) {
		$new_instance['title'] = wp_strip_all_tags( $new_instance['title'] );

		if ( ! in_array( $new_instance['type'], $this->valid_types, true ) ) {
			$new_instance['type'] = 'character';
		}
		$new_instance['type'] = sanitize_html_class( $new_instance['type'], 'character' );

		return $new_instance;
	}

	/**
	 * Back-end widget form.
	 */

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo 'Title'; ?>: </label>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php echo 'Type'; ?>: </label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" class="widefat">
				<?php
				foreach ( $this->valid_types as $type ) {
					?>
					<option <?php selected( $instance['type'], $type ); ?> value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option>
					<?php
				}
				?>
			</select>
		</p>
		<?php
	}

}

// Register widget and remove the one from the plugin for reasons
function register_lwtv_otd_widget() {
	unregister_widget( 'LWTV_Of_The_Day_Widget' ); // From the BYQ plugin
	register_widget( 'LWTV_OTD_Widget' );
}
add_action( 'widgets_init', 'register_lwtv_otd_Widget' );
