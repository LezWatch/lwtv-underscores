<?php
/**
 * Postiz API Integration
 *
 * Handles posting to Postiz when new content is published
 *
 * @package lwtv-plugin
 */

namespace LWTV\Plugins;

class Postiz {

	/**
	 * API configuration
	 */
	private $api_url;
	private $api_key;
	private $channel_ids;

	/**
	 * Initialize the Postiz integration
	 */
	public function __construct() {
		// Get API configuration from constants or options
		$this->api_url     = defined( 'POSTIZ_API_URL' ) ? POSTIZ_API_URL : 'https://postiz.ipstenu.com/api/public/v1';
		$this->api_key     = defined( 'POSTIZ_API_KEY' ) ? POSTIZ_API_KEY : get_option( 'lwtv_postiz_api_key', '' );
		$this->channel_ids = defined( 'POSTIZ_CHANNEL_IDS' ) ? POSTIZ_CHANNEL_IDS : get_option( 'lwtv_postiz_channel_ids', array() );

		// Ensure channel_ids is an array
		if ( ! is_array( $this->channel_ids ) && ! empty( $this->channel_ids ) ) {
			$this->channel_ids = array( $this->channel_ids );
		}

		// Hook into the OTD action
		add_action( 'lwtv_otd_added', array( $this, 'handle_otd_added' ), 10, 4 );
	}

	/**
	 * Check if Postiz is configured and enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->api_key ) && ! empty( $this->channel_ids );
	}

	/**
	 * Post content to Postiz
	 *
	 * @param string $content The content to post
	 * @param array  $options Optional. Additional options like images, schedule time, etc.
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function create_post( $content, $options = array() ) {
		// Check if enabled
		if ( ! $this->is_enabled() ) {
			return new \WP_Error(
				'postiz_not_configured',
				'Postiz API is not configured. Please set POSTIZ_API_KEY and POSTIZ_CHANNEL_IDS.',
				array( 'status' => 500 )
			);
		}

		// Default options
		$defaults = array(
			'type'      => 'draft', // draft, schedule, publish - for testing, we'll use draft
			'date'      => current_time( 'c' ), // ISO 8601 format
			'images'    => array(),
			'settings'  => array(),
			'group'     => wp_generate_uuid4(), // Generate unique group ID
			'tags'      => array(),
			'shortLink' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build posts array - one for each channel
		$posts = array();
		foreach ( $this->channel_ids as $channel_id ) {
			lwtv_plugin()->error_log( 'postiz', 'Building post for channel: ' . $channel_id );
			$post_data = array(
				'integration' => array(
					'id' => $channel_id,
				),
				'value'       => array(
					array(
						'content' => $content,
					),
				),
				'group'       => $options['group'],
			);

			// Add images if provided
			if ( ! empty( $options['images'] ) ) {
				$post_data['value'][0]['image'] = $options['images'];
			}

			// Add settings if provided
			if ( ! empty( $options['settings'] ) ) {
				$post_data['settings'] = $options['settings'];
			}

			$posts[] = $post_data;
		}

		// Build the payload
		$payload = array(
			'type'      => $options['type'],
			'date'      => $options['date'],
			'tags'      => $options['tags'],
			'shortLink' => $options['shortLink'],
			'posts'     => $posts,
		);

		lwtv_plugin()->error_log( 'postiz', 'Payload: ' . wp_json_encode( $payload ) );

		// Make the API request
		$response = $this->make_api_request( '/posts', $payload );

		// Log the response for debugging
		//lwtv_plugin()->error_log( 'postiz', 'Posted to Postiz: ' . $content . ' | Response: ' . wp_json_encode( $response ) );

		return $response;
	}

	/**
	 * Make an API request to Postiz
	 *
	 * @param string $endpoint The API endpoint (e.g., '/posts')
	 * @param array  $data     The data to send
	 * @param string $method   HTTP method (POST, GET, etc.)
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_api_request( $endpoint, $data = array(), $method = 'POST' ) {
		$url = trailingslashit( $this->api_url ) . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		// Add body for POST/PUT requests
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Make the request
		$response = wp_remote_request( $url, $args );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		// Check for HTTP errors
		if ( $response_code < 200 || $response_code >= 300 ) {
			// Extract structured error messages from the response
			$error_messages = array();

			if ( is_array( $decoded_body ) ) {
				// Extract message field (can be array or string)
				if ( isset( $decoded_body['message'] ) ) {
					if ( is_array( $decoded_body['message'] ) ) {
						$error_messages = $decoded_body['message'];
					} else {
						$error_messages[] = $decoded_body['message'];
					}
				}

				// Extract error field if no messages found
				if ( empty( $error_messages ) && isset( $decoded_body['error'] ) ) {
					$error_messages[] = $decoded_body['error'];
				}
			}

			// Format error message
			if ( ! empty( $error_messages ) ) {
				$error_message = sprintf(
					'Postiz API returned error %d: %s',
					$response_code,
					implode( '; ', $error_messages )
				);
			} else {
				// Fall back to raw response body if structure is unexpected
				$error_message = sprintf(
					'Postiz API returned error %d: %s',
					$response_code,
					$response_body
				);
			}

			return new \WP_Error(
				'postiz_api_error',
				$error_message,
				array(
					'status'   => $response_code,
					'response' => $decoded_body,
				)
			);
		}

		return array(
			'success' => true,
			'code'    => $response_code,
			'data'    => $decoded_body,
		);
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

		// Get featured image if available
		$images = array();
		if ( has_post_thumbnail( $post_id ) ) {
			$image_id  = get_post_thumbnail_id( $post_id );
			$image_url = wp_get_attachment_url( $image_id );

			if ( $image_url ) {
				$images[] = array(
					'id'   => (string) $image_id,
					'path' => $image_url,
				);
			}
		} else {
			$images[] = array(
				'id'   => 'default',
				'path' => get_site_icon_url(),
			);
		}

		$title   = get_the_title( $post_id );
		$hashtag = '#' . implode( '', array_map( 'ucfirst', explode( '-', $title ) ) );
		$tags    = array(
			array(
				'value' => '#lwtv',
				'label' => '#lwtv',
			),
			array(
				'value' => $hashtag,
				'label' => $hashtag,
			),
		);

		switch ( $type ) {
			case 'character':
				$tags[] = array(
					'value' => '#LWTVcotd',
					'label' => '#LWTVcotd',
				);
				break;
			case 'show':
				$tags[] = array(
					'value' => '#LWTVsotd',
					'label' => '#LWTVsotd',
				);
				break;
		}

		// Options for the post
		$options = array(
			'group'     => 'otd_' . $type . '_' . gmdate( 'Y-m-d' ),
			'images'    => $images,
			'tags'      => $tags,
			'shortLink' => false,
		);

		// Create the post
		return $this->create_post( $content, $options );
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
		// Only proceed if Postiz is configured
		if ( ! $this->is_enabled() ) {
			lwtv_plugin()->error_log( 'postiz', 'Postiz is not configured. Skipping OTD added: ' . $type . ' - ' . $content . ' - ' . $post_id . ' - ' . wp_json_encode( $data ) );
			return;
		}

		// TODO: Check if the OTD already exists in Postiz

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
}
