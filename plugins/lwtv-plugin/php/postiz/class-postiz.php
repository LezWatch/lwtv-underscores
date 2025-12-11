<?php
/**
 * Postiz API Integration
 *
 * Handles posting to Postiz when new content is published
 *
 * @package lwtv-plugin
 */

namespace LWTV\Postiz;

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
		$this->api_key = defined( 'POSTIZ_API_KEY' ) ? POSTIZ_API_KEY : get_option( 'lwtv_postiz_api_key', '' );
		$this->api_url = defined( 'POSTIZ_API_URL' ) ? POSTIZ_API_URL : get_option( 'lwtv_postiz_api_url', '' );

		// Get channel IDs - support both constant and new channel structure from settings
		if ( defined( 'POSTIZ_CHANNEL_IDS' ) ) {
			$this->channel_ids = POSTIZ_CHANNEL_IDS;
		} else {
			$this->channel_ids = $this->extract_channel_ids_from_settings();
		}

		// Ensure channel_ids is an array
		if ( ! is_array( $this->channel_ids ) && ! empty( $this->channel_ids ) ) {
			$this->channel_ids = array( $this->channel_ids );
		}
	}

	/**
	 * Extract channel IDs from the settings option
	 *
	 * The settings store channels as an array of arrays with 'name', 'channel_id', and 'active' keys.
	 * This method extracts just the channel_id values for active channels only.
	 *
	 * @return array Array of channel IDs
	 */
	private function extract_channel_ids_from_settings() {
		$channels    = get_option( 'lwtv_postiz_channels', array() );
		$channel_ids = array();

		if ( is_array( $channels ) ) {
			foreach ( $channels as $channel ) {
				// Only include active channels (default to active if not set for backwards compatibility)
				$is_active = isset( $channel['active'] ) ? $channel['active'] : true;

				if ( $is_active && isset( $channel['channel_id'] ) && ! empty( $channel['channel_id'] ) ) {
					$channel_ids[] = $channel['channel_id'];
				}
			}
		}

		return $channel_ids;
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
	 * Check if a type is triggered and enabled
	 *
	 * @param string $type The type to check (otd, new_posts, new_shows)
	 * @return bool
	 */
	public function is_type_triggered_enabled( $type ) {
		$triggers = get_option( 'lwtv_postiz_triggers', array() );
		if ( empty( $triggers ) ) {
			return true;
		}

		return in_array( $type, $triggers, true );
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
			'type'      => get_option( 'lwtv_postiz_post_type', 'draft' ), // Get the value from the options
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

		// Check for errors from build_posts
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

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
	 * Get the images for a post
	 *
	 * @param int $post_id The post ID
	 * @return array The images
	 */
	public function get_images( $post_id ) {
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
	 * @param string $type    Type of OTD (character, show)
	 *
	 * @return array The tags
	 */
	public function get_tags( $purpose, $post_id, $type = null ) {
		$title   = get_the_title( $post_id );
		$hashtag = '#' . implode( '', array_map( 'ucfirst', explode( '-', $title ) ) );
		$tags    = array(
			$this->create_tag( '#lwtv' ),
			$this->create_tag( $hashtag ),
		);

		if ( 'otd' === $purpose && null !== $type ) {
			$tags[] = ( new Of_The_Day() )->create_tag( $type );
		} else {
			$tags[] = ( new New_Post() )->create_tag( $post_id );
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
	public function build_posts( $content, $options ) {

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

		if ( empty( $options['group'] ) || empty( $options['image'] ) || empty( $options['tags'] ) ) {
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
	public function post_exists( $content ) {
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
	public function log_otd_message( $message, $type, $content, $post_id, $data ) {
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
	 * Log a new post-related message with consistent formatting
	 *
	 * @param string $message The log message
	 * @param int    $post_id The post ID
	 * @return void
	 */
	public function log_new_post_message( $message, $post_id ) {
		if ( function_exists( 'lwtv_plugin' ) && method_exists( lwtv_plugin(), 'error_log' ) ) {
			lwtv_plugin()->error_log(
				'postiz',
				sprintf(
					'%s: %d',
					$message,
					$post_id
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
