<?php

	/**
	 * Adds The LWTV Recently Added Show widget.
	 */

class LWTV_Show extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'lwtv_show', // Base ID
			'LWTV Recent Show', // Name
			array( 'description' => __( 'Displays the show most recently added to the database.', 'yikes_starter' ) ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 */

	public function widget( $args, $instance ) {
		global $post;

		// start a Queery
		$show_args = array(
			'post_type'      => 'post_type_shows',
			'posts_per_page' => '1',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		// Get what's needed from $args array ($args populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		// Get what's needed from $instanse array ($instance populated with user inputs from widget form)
		$title = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'YIKES Example Widget';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo $before_widget;

		$queery = new WP_Query( $show_args );
		while ( $queery->have_posts() ) {
			$queery->the_post();

			$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
			$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;

			echo '<div class="card">';
			echo '<div class="card-header"><h4>Recently Added Show <span class="float-right">' . lwtv_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '</span></h4></div>';

			// Featured Image
			echo '<a href="' . esc_url( get_the_permalink() ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput
			echo the_post_thumbnail(
				'postloop-img',
				array(
					'class' => 'card-img-top',
					'alt'   => $thumb_title,
					'title' => $thumb_title,
				)
			);
			echo '</a>';

			// Body
			echo '<div class="card-body">';

			// Title
			// phpcs:ignore WordPress.Security.EscapeOutput
			echo '<h4 class="card-title">' . get_the_title() . '</h4>';

			// Content
			echo '<div class="card-text">';

			// Stations
			$stations = get_the_terms( $post, 'lez_stations' );
			if ( $stations ) {
				echo '<div class="card-meta-item"><strong>Airs On:</strong> ';
				$station_string = '';
				foreach ( $stations as $station ) {
					$station_string .= $station->name . ', ';
				}
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo trim( $station_string, ', ' );
				echo '</div>';
			}

			// Airdates
			$field   = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
			$start   = isset( $field['start'] ) ? $field['start'] : '';
			$finish  = isset( $field['finish'] ) ? $field['finish'] : '';
			$airdate = $start . ' - ' . $finish;
			if ( $start === $finish || '' === $finish ) {
				$airdate = $start;
			}
			echo '<div class="card-meta-item"><strong>Airdates:</strong> ' . esc_html( $airdate ) . '</div>';

			// Excerpt
			echo '<div class="card-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div>';

			// End Content
			echo '</div></div>';

			// Button
			echo '<div class="card-footer"><a href="' . esc_url( get_the_permalink() ) . '" class="btn btn-outline-primary">Show Profile</a></div>';

			// End Body
			echo '</div>';

			wp_reset_postdata();

			// phpcs:ignore WordPress.Security.EscapeOutput
			echo $after_widget;
			/** Output widget HTML END **/
		}

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

// Register LWTV_Show widget
function register_lwtv_show() {
	register_widget( 'LWTV_Show' );
}
add_action( 'widgets_init', 'register_lwtv_show' );
