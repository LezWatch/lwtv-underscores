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
		$q_args = array(
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


		$query = new WP_Query( $q_args );
		while ($query->have_posts()) {
			$query->the_post();

		echo '<div class="card">';
		echo '<div class="card-header">
              <h4><i class="fa fa-address-card float-right" aria-hidden="true"></i> Recently Added Character</h4>
          </div>';

		// Featured Image
		echo the_post_thumbnail( 'medium', array( 'class' => 'card-img-top' ) );

		echo '<div class="card-body">';

		// Title
		echo '<h4 class="card-title">' . the_title() .'</h4>';

		echo '<div class="card-text">';

		// Show
		echo '<div class="card-meta-item"><i class="fa fa-television" aria-hidden="true"></i> <a href="#">' . get_post_meta( $post->ID, 'lezchars_show', true ) .'</a></div>';

		// Actor
		echo '<div class="card-meta-item"><i class="fa fa-user" aria-hidden="true"></i>' . get_post_meta( $post->ID, 'lezchars_actor', true ) .'</div>';

		echo '</div>
            </div>';

        // Button
		echo '<div class="card-footer">
          	<a href="#" class="btn btn-outline-primary">Character Profile</a>
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

	<?php
	}

}

// Register LWTV_Character widget
function register_lwtv_character() {
	register_widget( 'LWTV_Character' );
}
add_action( 'widgets_init', 'register_lwtv_character' );

?>
