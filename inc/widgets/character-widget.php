<?php

/**
 * Adds The LWTV Recently Added Character widget.
 */

class LWTV_Character extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'lwtv_character', // Base ID
			'LWTV Recent Character', // Name
			array( 'description' => __( 'Displays the character most recently added to the database.', 'lwtv-underscores' ) ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 */

	public function widget( $args, $instance ) {

		// start a Queery
		$char_args = array(
			'post_type'      => 'post_type_characters',
			'posts_per_page' => '1',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		// Get what's needed from $args array (populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		// Get what's needed from $instance array (populated with user inputs from widget form)
		$title = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'Newest Character';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		echo wp_kses_post( $before_widget );

		$queery = new WP_Query( $char_args );
		while ( $queery->have_posts() ) {
			$queery->the_post();

			$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
			$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;

			echo '<div class="card">';
			echo '<div class="card-header"><h4><span class="float-left">' . lwtv_symbolicons( 'contact-card.svg', 'fa-address-card' ) . '</span> Recently Added Character</h4></div>';

			// Featured Image
			echo '<div class="character-image-wrapper">';
			echo '<a href="' . esc_url( get_the_permalink() ) . '">';
			the_post_thumbnail(
				'character-img',
				array(
					'class' => 'card-img-top',
					'alt'   => $thumb_title,
					'title' => $thumb_title,
				)
			);
			echo '</a>';
			echo '</div>';

			echo '<div class="card-body">';
				// Title
				echo '<h4 class="card-title">' . esc_html( get_the_title() ) . '</h4>';
				echo '<div class="card-text">';
					// Only show one show
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo lwtv_plugin()->get_character_data( get_the_ID(), 'oneshow' );
					// Actor
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo lwtv_plugin()->get_character_data( get_the_ID(), 'oneactor' );
				echo '</div>
			</div>';

			// Button
			echo '<div class="card-footer">
					<a href="' . esc_url( get_the_permalink() ) . '" class="btn btn-outline-primary">Character Profile</a>
				  </div>';

			echo '</div>';

			wp_reset_postdata();

			echo wp_kses_post( $after_widget );
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

// Register LWTV_Character widget
function register_lwtv_character() { // phpcs:ignore
	register_widget( 'LWTV_Character' );
}
add_action( 'widgets_init', 'register_lwtv_character' );
