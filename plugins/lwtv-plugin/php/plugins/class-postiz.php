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
			'image'     => array(),
			'settings'  => array(),
			'group'     => wp_generate_uuid4(), // Generate unique group ID
			'tags'      => array(),
			'shortLink' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build posts array - one for each channel
		$posts = $this->build_posts( $content, $options );

		// Build the payload
		$payload = array(
			'type'      => $options['type'],
			'date'      => $options['date'],
			'tags'      => $options['tags'],
			'shortLink' => $options['shortLink'],
			'posts'     => $posts,
		);

		// Make the API request
		$response = $this->make_api_request( '/posts', $payload );

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

		lwtv_plugin()->error_log( 'postiz', 'Making API request to: ' . $url . ' with method: ' . $method . ' and data: ' . wp_json_encode( $data ) );

		// For GET requests, append data as query parameters
		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

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

		// Log the response
		lwtv_plugin()->error_log( 'postiz', 'API Response: ' . wp_json_encode( $response ) );

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
			$error_messages = $this->extract_error_messages( $decoded_body );

			// Format error message (use extracted messages or fall back to raw body)
			$message_source = ! empty( $error_messages ) ? $error_messages : $response_body;
			$error_message  = $this->format_api_error_message( $response_code, $message_source );

			return new \WP_Error(
				'postiz_api_error',
				$error_message,
				array(
					'status'   => $response_code,
					'response' => $decoded_body,
				)
			);
		}

		lwtv_plugin()->error_log( 'postiz', 'API Response: ' . wp_json_encode( $decoded_body ) );

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

		// Get Images and Tags
		$images = $this->get_images( $post_id );
		$tags   = $this->get_tags( $type, $post_id );

		// Options for the post
		$options = array(
			'group'     => 'otd_' . $type . '_' . gmdate( 'Y-m-d' ),
			'image'     => $images,
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
			$this->log_otd_message( 'Postiz is not configured. Skipping OTD added', $type, $content, $post_id, $data );
			return;
		}

		// Check if the OTD already exists in Postiz
		$exists = $this->post_exists( $content );
		if ( $exists ) {
			$this->log_otd_message( 'OTD already exists in Postiz in at least one channel. Skipping', $type, $content, $post_id, $data );
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
	 * Get the images for a post
	 *
	 * @param int $post_id The post ID
	 * @return array The images
	 */
	private function get_images( $post_id ) {
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

		return $images;
	}

	/**
	 * Get the tags for a post
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param int    $post_id The post ID
	 * @return array The tags
	 */
	private function get_tags( $type, $post_id ) {
		$title   = get_the_title( $post_id );
		$hashtag = '#' . implode( '', array_map( 'ucfirst', explode( '-', $title ) ) );
		$tags    = array(
			$this->create_tag( '#lwtv' ),
			$this->create_tag( $hashtag ),
		);

		switch ( $type ) {
			case 'character':
				$tags[] = $this->create_tag( '#LWTVcotd' );
				break;
			case 'show':
				$tags[] = $this->create_tag( '#LWTVsotd' );
				break;
		}

		return $tags;
	}

	/**
	 * Build the posts array
	 *
	 * @param string $content The content to post
	 * @param array  $options The options for the post
	 * @return array The posts array
	 */
	private function build_posts( $content, $options ) {

		if ( empty( $this->channel_ids ) ) {
			return new \WP_Error(
				'postiz_no_channels',
				'No channels configured. Please set POSTIZ_CHANNEL_IDS.',
				array( 'status' => 500 )
			);
		}

		if ( empty( $content ) ) {
			return new \WP_Error(
				'postiz_no_content',
				'No content provided. Please provide content to post.',
				array( 'status' => 500 )
			);
		}

		if ( empty( $options['group'] ) || empty( $options['image'] || empty( $options['tags'] ) ) ) {
			return new \WP_Error(
				'postiz_no_required_fields',
				'No required fields provided. Please provide a group, images, and tags for the post. Group: ' . $options['group'] . ' Images: ' . wp_json_encode( $options['image'] ) . ' Tags: ' . wp_json_encode( $options['tags'] ),
				array( 'status' => 500 )
			);
		}

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
						'image'   => $options['image'],
					),
				),
				'group'       => $options['group'],
				'settings'    => $options['settings'],
			);

			$posts[] = $post_data;
		}

		return $posts;
	}

	/**
	 * Check if the OTD already exists in Postiz
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @return bool True if the OTD exists, false otherwise
	 */
	private function post_exists( $content ) {
		// Get all posts made for the last 24 hours
		$start_date = rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-48 hours' ) ) );
		$end_date   = rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z' ) );

		$posts = array();
		foreach ( $this->channel_ids as $channel_id ) {
			$payload  = array(
				'startDate'   => $start_date,
				'endDate'     => $end_date,
				'integration' => $channel_id,
			);
			$response = $this->make_api_request( '/posts', $payload, 'GET' );

			if ( is_wp_error( $response ) ) {
				lwtv_plugin()->error_log( 'postiz', 'Error checking if OTD already exists in Postiz: ' . $response->get_error_message() );
				continue;
			}

			// Check if the posts are empty
			if ( is_null( $response['data']['posts'] ) || empty( $response['data']['posts'] ) ) {
				lwtv_plugin()->error_log( 'postiz', 'No posts found in response: ' . wp_json_encode( $response ) );
				continue;
			}

			// Add to the posts array
			$posts = array_merge( $posts, $response['data']['posts'] );
		}

		// Loop through the posts and check if the content matches
		foreach ( $posts as $post ) {
			lwtv_plugin()->error_log( 'postiz', 'Checking if OTD already exists in Postiz: ' . wp_json_encode( $post ) );
			if ( empty( $post['content'] ) || 'published' !== $post['status'] ) {
				continue;
			}

			if ( $post['content'] === $content ) {
				lwtv_plugin()->error_log( 'postiz', 'OTD already exists in Postiz: ' . wp_json_encode( $post ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Log an OTD-related message with consistent formatting
	 *
	 * @param string $message The log message
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @param array  $data    Additional data about the OTD
	 * @return void
	 */
	private function log_otd_message( $message, $type, $content, $post_id, $data ) {
		if ( function_exists( 'lwtv_plugin' ) && method_exists( lwtv_plugin(), 'error_log' ) ) {
			lwtv_plugin()->error_log(
				'postiz',
				sprintf(
					'%s: %s - %s - %d - %s',
					$message,
					$type,
					$content,
					$post_id,
					wp_json_encode( $data )
				)
			);
		}
	}

	/**
	 * Create a tag array structure
	 *
	 * @param string $value The tag value
	 * @param string $label Optional. The tag label. Defaults to value if not provided.
	 * @return array Tag array with 'value' and 'label' keys
	 */
	private function create_tag( $value, $label = null ) {
		if ( null === $label ) {
			$label = $value;
		}

		return array(
			'value' => $value,
			'label' => $label,
		);
	}

	/**
	 * Extract error messages from API response body
	 *
	 * @param array|mixed $decoded_body The decoded response body
	 * @return array Array of error messages
	 */
	private function extract_error_messages( $decoded_body ) {
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

		return $error_messages;
	}

	/**
	 * Format API error message
	 *
	 * @param int          $response_code The HTTP response code
	 * @param array|string $messages_or_body Array of error messages or raw response body
	 * @return string Formatted error message
	 */
	private function format_api_error_message( $response_code, $messages_or_body ) {
		if ( is_array( $messages_or_body ) && ! empty( $messages_or_body ) ) {
			$message = implode( '; ', $messages_or_body );
		} else {
			$message = is_string( $messages_or_body ) ? $messages_or_body : '';
		}

		return sprintf(
			'Postiz API returned error %d: %s',
			$response_code,
			$message
		);
	}
}
