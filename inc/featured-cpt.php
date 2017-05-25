<?php
/**
 * LezWatch Featured CPTs
 *
 * A Widget to Display Featured Custom Post Types - customized for LezWatchTV
 *
 * Version:     2.0
 * Author:      Mika Epstein
 * Author URI:  https://halfelf.org
 *
 * FORKED from Featured Custom Post Type Widget For Genesis
 * @package LezWatchFeaturedCPTS
 * @author Jo Waltham
 * @license GPL-2.0+
 *
 */

// if this file is called directly abort
if ( ! defined('WPINC' ) ) {
	die;
}

/**
 * class LWTV_FCPT
 *
 * The basic constructor class that will set up our widget.
 *
 * @since 1.0
 */
class LWTV_FCPT {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}

	/**
	 * Register Widget
	 *
	 * @since 1.0
	 */
	public function register_widget() {
		$this->widget = new LWTV_FCPT_Widget();
		register_widget( $this->widget );
	}

}
new LWTV_FCPT();

/**
 * class LWTV_FCPT_Widget
 *
 * This is a stripped down version of Featured Custom Post
 * Type Widget For Genesis, whis is a forked version of Genesis
 * Featured Post Widget included in the Genesis Framework.
 *
 * @since 1.0
 */
class LWTV_FCPT_Widget extends WP_Widget {

	/**
	 * Holds widget settings defaults, populated in constructor.
	 *
	 * @var array
	 */
	protected $defaults;

	/**
	 * Constructor. Set the default widget options and create widget.
	 *
	 * @since 1.0
	 */
	function __construct() {

		$this->defaults = array(
			'title'                   => '',
			'post_type'               => 'post',
			'posts_num'               => 1,
		);

		$widget_ops = array(
			'classname'   => 'featured-content featuredcpt',
			'description' => 'Displays LezWatch CPTs with thumbnails',
		);

		$control_ops = array(
			'id_base' => 'lezwatch-featured-cpt',
		);

		parent::__construct( 'lezwatch-featured-cpt', 'LezWatch - Featured CPTs', $widget_ops, $control_ops );
	}

	/**
	 * Echo the widget content.
	 *
	 * @since 1.0
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */
	function widget( $args, $instance ) {

		global $wp_query;

		extract( $args );

		//* Merge with defaults
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		echo $before_widget;

		if ( ! empty( $instance['title'] ) )
			echo $before_title . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $after_title;

		// Query Arguments
		$query_args = array(
			'post_type' => $instance['post_type'],
			'showposts' => $instance['posts_num'],
		);

		// Post Type Data
		$the_post_type = get_post_type_object( $instance['post_type'] );

		// Add Post Class Filters
		add_filter( 'post_class', array( $this, 'add_post_classes' ) );

		// The Query
		$wp_query = new WP_Query( $query_args );

		echo '<div class="catgrid">';

		// Displaying the post
		if ( have_posts() ) : while ( have_posts() ) : the_post();

			$thumbnail = get_the_post_thumbnail( '', 'thumbnail', array( 'alt' => get_the_title(), 'title' => get_the_title() ) );
			$title = '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';

			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>				
				<div class="entry-content">
					<?php echo '<a href="' . get_permalink( ) . '">' . $thumbnail . '</a>'; ?>
					<p><span class="title"><?php echo $title; ?></span></p>
				</div>
			</article>
	
			<?php

		endwhile; endif;

		//* Restore original query
		wp_reset_query();

		// Remove extra post classes
		remove_filter( 'post_class', array( $this, 'add_post_classes' ) );

		echo '</div>';

		// Generate the Read More link...
		if( 'post' === $instance[ 'post_type'] ) {
			$postspage   = get_option( 'page_for_posts' );
			$archive_url = get_permalink( get_post( $postspage )->ID );
			$frontpage   = get_option( 'show_on_front' );
			if ( 'posts' === $frontpage ) {
				$archive_url = get_home_url();
			}
		} else {
			$post_type_slug = $the_post_type->rewrite['slug'];
			$archive_url = get_home_url() . '/' . $post_type_slug . 's/?fwp_sort=date_desc'; // PLEASE KEEP PLURALIZATION
		}
		printf(
			'<p class="more-from-category"><a href="%1$s">More New %2$s ...</a></p>',
			esc_url( $archive_url ),
			esc_attr( $the_post_type->labels->name )
		);

		echo $after_widget;

	}

	/**
	 * Update a particular instance.
	 *
	 * This function should check that $new_instance is set correctly.
	 * The newly calculated value of $instance should be returned.
	 * If "false" is returned, the instance won't be saved/updated.
	 *
	 * @since 1.0
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 * @return array Settings to save or bool false to cancel saving
	 */
	function update( $new_instance, $old_instance ) {
		$new_instance['title']     = strip_tags( $new_instance['title'] );
		return $new_instance;
	}

	/**
	 * Echo the settings update form.
	 *
	 * @since 1.0
	 *
	 * @param array $instance Current settings
	 */
	function form( $instance ) {

		//* Merge with defaults
		$instance = wp_parse_args( (array) $instance, $this->defaults );
		$item     = $this->build_lists( $instance );

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title:</label>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>">Post Type:</label>
			<div style="display:inline-block;max-width:90%;">
			<select id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>" style="width:100%" >

				<?php
				echo '<option value="any" '. selected( 'any', $instance['post_type'], false ) .'>Any</option>';
				foreach ( $item->post_type_list as $post_type_item ) {
					$the_post_type = get_post_type_object( $post_type_item );
					echo '<option value="'. esc_attr( $post_type_item ) .'"'. selected( esc_attr( $post_type_item ), $instance['post_type'], false ) .'>'. esc_attr( $the_post_type->labels->singular_name ) .'</option>';
				}

				?>
			</select>
			</div>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'posts_num' ) ); ?>">Number of Posts to Show:</label>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'posts_num' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'posts_num' ) ); ?>" value="<?php echo esc_attr( $instance['posts_num'] ); ?>" size="2" />
		</p>

		<?php

	}

	/**
	 * Build Lists
	 *
	 * build post_type list for widget form use
	 *
	 * @since   1.0
	 * @param  [type] $instance [description]
	 * @return $item           list of post_types
	 */
	function build_lists( $instance ) {

		//* Merge with defaults
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		$item = new stdClass();

		//* Fetch a list of possible post types
		$args = array(
			'public'   => true,
			'_builtin' => false,
		);
		$output = 'names';
		$item->post_type_list = get_post_types( $args, $output );

		//* Add posts to that post_type_list
		$item->post_type_list['post'] = 'post';

		return $item;
	}

	/**
	 * Add Post Classes
	 *
	 * Set up a grid of one-fourth elements for use in a post_class filter.
	 *
	 * @since      1.0
	 * @category   Grid Loop
	 * @param      $classes array An array of the current post classes
	 * @return     $classes array The current post classes with the grid appended
	 * @author     Rob Neu, Mika Epstein
	 */
	function add_post_classes() {
		global $wp_query;

		$classes = array();
		$classes[] = 'grid';
		$classes[] = 'one-fourth';

		//* Add an "odd" class to allow for more control of grid clollapse.
		if ( ( $wp_query->current_post + 1 ) % 2 ) {
			$classes[] = 'odd';
		}

		if ( 0 === $wp_query->current_post || 0 === $wp_query->current_post % '4' ) {
			$classes[] = 'first';
		}

		return $classes;
	}
}