<?php
/**
 * New Post
 *
 * @package lwtv-plugin
 */

namespace LWTV\Postiz;

class New_Post extends Postiz {

	/**
	 * Constructor - register hook for new posts
	 */
	public function __construct() {
		$postiz = new Postiz();
		if ( $postiz->is_enabled() && $postiz->is_type_triggered_enabled( 'new_posts' ) ) {
			add_action( 'publish_post', array( $this, 'handle_new_post_added' ), 10, 2 );
		}
	}

	/**
	 * Handle the publish_post action
	 *
	 * @param int $post_id The post ID
	 * @return void
	 */
	public function handle_new_post_added( $post_id ) {
		// To Be Built...
		$post_type        = get_post_type( $post_id );
		$valid_post_types = array( 'post', 'post_type_shows' );
		if ( ! in_array( $post_type, $valid_post_types, true ) ) {
			parent::log_new_post_message( 'Invalid post type. Skipping', $post_id );
			return;
		}

		// Get the content and post it
		$content = $this->get_post_content( $post_id );
		$result  = $this->post_new_post( $content, $post_id );

		// Log errors if any
		if ( is_wp_error( $result ) ) {
			parent::log_new_post_message( 'Failed to post new post to Postiz: ' . $result->get_error_message(), $post_id );
		} else {
			// Log success
			parent::log_new_post_message( 'New post posted to Postiz', $post_id );
		}

		return $result;
	}

	/**
	 * Get the content for the new post
	 *
	 * @param int $post_id The post ID
	 * @return string The content
	 */
	private function get_post_content( $post_id ) {
		// Get the extracted content
		$content = get_post_meta( $post_id, 'extracted_content', true );
		return $content;
	}

	/**
	 * Create a tag for the new post
	 *
	 * @param string $post_id The post ID
	 * @return array The tag
	 */
	public function create_tag( $post_id ) {
		$post_type = get_post_type( $post_id );

		switch ( $post_type ) {
			case 'post':
				return array(
					array(
						'value' => '#NewPost',
						'label' => '#NewPost',
					),
				);
			case 'post_type_shows':
				// ToDo: See if there are any hashtags for the show in post meta
				$show_name = get_the_title( $post_id );
				$show_name = str_replace( ' ', '', $show_name );
				$show_name = strtolower( $show_name );
				$show_tag  = '#' . $show_name;
				return array(
					array(
						'value' => $show_tag,
						'label' => $show_tag,
					),
				);
			default:
				return array();
		}
	}

	/**
	 * Post "New Post" content to Postiz
	 *
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function post_new_post( $content, $post_id ) {
		$post_type = get_post_type( $post_id );

		// Options for the post
		$options = array(
			'group'     => $post_type . '_' . $post_id,
			'image'     => parent::get_images( $post_id ),
			'tags'      => parent::get_tags( 'post', $post_id ),
			'shortLink' => false,
		);

		// Create the post
		return parent::create_post( $content, $options );
	}
}
