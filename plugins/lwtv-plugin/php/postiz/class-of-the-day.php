<?php
/**
 * Of The Day
 *
 * @package lwtv-plugin
 */

namespace LWTV\Postiz;

class Of_The_Day extends Postiz {

	/**
	 * Constructor - register hook for OTD posts
	 */
	public function __construct() {
		$postiz = new Postiz();
		if ( $postiz->is_enabled() && $postiz->is_type_triggered_enabled( 'of_the_day' ) ) {
			add_action( 'lwtv_otd_added', array( $this, 'handle_otd_added' ), 10, 4 );
		}
	}

	/**
	 * Handle the lwtv_otd_added action
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @param array  $data    Additional data about the OTD
	 */
	public function handle_otd_added( $type, $content, $post_id, $data ) {
		// Check if the OTD already exists in Postiz
		$exists = parent::post_exists( $content );
		if ( $exists ) {
			parent::log_otd_message( 'OTD already exists in Postiz in at least one channel. Skipping', $type, $content, $post_id, $data );
			return;
		}

		// If this OTD doesn't exist in Postiz, post it
		$result = $this->post_of_the_day( $type, $content, $post_id );

		// Log errors if any
		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'lwtv_plugin' ) && method_exists( lwtv_plugin(), 'error_log' ) ) {
				lwtv_plugin()->error_log(
					'postiz',
					sprintf(
						'Failed to post OTD to Postiz: %s',
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Post "Of The Day" content to Postiz
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function post_of_the_day( $type, $content, $post_id ) {

		// Get Images and Tags
		$images = parent::get_images( $post_id );
		$tags   = parent::get_tags( 'otd', $post_id, $type );

		// Options for the post
		$options = array(
			'group'     => 'otd_' . $type . '_' . gmdate( 'Y-m-d' ),
			'image'     => $images,
			'tags'      => $tags,
			'shortLink' => false,
		);

		// Create the post
		return parent::create_post( $content, $options );
	}

	/**
	 * Create a tag for the OTD
	 *
	 * @param string $type The type of OTD (character, show)
	 * @return array The tag array with 'value' and 'label' keys
	 */
	public function create_tag( $type ) {
		switch ( $type ) {
			case 'character':
				return array(
					'value' => '#LWTVcotd',
					'label' => '#LWTVcotd',
				);
			case 'show':
				return array(
					'value' => '#LWTVsotd',
					'label' => '#LWTVsotd',
				);
			default:
				return array();
		}
	}
}
