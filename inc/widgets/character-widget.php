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
			array( 'description' => __( 'Displays the character most recently added to the database.', 'yikes_starter' ) ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 */

	public function widget( $args, $instance ) {

		// start a Queery
		$char_args = array(
			'post_type' => 'post_type_characters',
			'posts_per_page' => '1', 
			'orderby' => 'date', 
			'order' => 'DESC'
		);

		// Get what's needed from $args array ($args populated with options from widget area register_sidebar function)
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		// Get what's needed from $instanse array ($instance populated with user inputs from widget form)
		$title     = isset( $instance['title'] ) && ! empty( trim( $instance['title'] ) ) ? $instance['title'] : 'YIKES Example Widget';
		$title     = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		/** Output widget HTML BEGIN **/
		echo $before_widget;

		$queery = new WP_Query( $char_args );
		while ($queery->have_posts()) {
			$queery->the_post();

		echo '<div class="card">';
		echo '<div class="card-header"><h4>Recently Added Character <span class="float-right">' . lwtv_yikes_symbolicons( 'contact_cards.svg', 'fa-address-card' ) . '</span></h4></div>';

		// Featured Image
		echo '<div class="character-image-wrapper">';
		echo '<a href="' . get_the_permalink()  .'">';
		echo the_post_thumbnail( 'character-img', array( 'class' => 'card-img-top' ) );
		echo '</a>';
		echo '</div>';

		echo '<div class="card-body">';

		// Title
		echo '<h4 class="card-title">' . get_the_title() .'</h4>';

		echo '<div class="card-text">';

		// Shows
		$shows = get_post_meta( get_the_ID(), 'lezchars_show_group', true );
		foreach ($shows as $show) {
			$show_post = get_post( $show['show']);
			echo '<div class="card-meta-item shows">' . lwtv_yikes_symbolicons( 'tv_flatscreen.svg', 'fa-television' ) . '&nbsp;<a href="' . get_the_permalink($show_post->ID)  .'">' . $show_post->post_title .'</a></div>';
		}
		
		// Actor
		$field = get_post_meta( get_the_ID(), 'lezchars_actor', true );
		$field_value = isset( $field[0] ) ? $field[0] : '';
		echo '<div class="card-meta-item actors">' . lwtv_yikes_symbolicons( 'person.svg', 'fa-user' ) . '&nbsp;' . $field_value  . '</div>';

		echo '</div>
			  </div>';

		// Button
		echo '<div class="card-footer">
				<a href="' . get_the_permalink()  .'" class="btn btn-outline-primary">Character Profile</a>
			  </div>';

		echo '</div>';

		wp_reset_postdata();

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
		$instance['title']     = strip_tags( $new_instance['title'] );

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
function register_lwtv_character() {
	register_widget( 'LWTV_Character' );
}
add_action( 'widgets_init', 'register_lwtv_character' );