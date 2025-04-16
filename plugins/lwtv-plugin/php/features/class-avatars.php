<?php
/**
 * Avatars as SVGs, not default Gravatars.
 */

namespace LWTV\Features;

class Avatars {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'get_avatar', array( $this, 'filter_avatar' ), 10, 6 );
		add_filter( 'get_avatar_url', array( $this, 'filter_avatar_url' ), 10, 3 );
	}

	/**
	 * Filter avatar HTML.
	 *
	 * @param string $avatar      Avatar HTML.
	 * @param mixed  $id_or_email User ID, email, or comment object.
	 * @param int    $size        Avatar size.
	 * @param string $default_url Default avatar URL.
	 * @param string $alt         Alt text.
	 * @param array  $args        Avatar arguments.
	 * @return string Filtered avatar HTML.
	 */
	public function filter_avatar( $avatar, $id_or_email, $size, $default_url, $alt, $args ) {

		$details = $this->get_details_from_id_or_email( $id_or_email );
		$email   = $details['email'];
		$name    = $details['name'];

		// If the person has a gravatar, use it.
		if ( $this->validate_gravatar( $email ) ) {
			return $avatar;
		}

		// If the person has a local avatar, use it.
		if ( $this->validate_local_avatar( $email, $size ) ) {
			return $avatar;
		}

		$avatar_url = $this->get_avatar_url( $id_or_email, $args, $name );

		$class = array( 'avatar', 'avatar-' . (int) $size, 'photo' );
		if ( ! empty( $args['class'] ) ) {
			$class = array_merge( $class, (array) $args['class'] );
		}

		return sprintf(
			"<img alt='%s' src='%s' class='%s' height='%d' width='%d' %s/>",
			esc_attr( $alt ),
			esc_url( $avatar_url, array( 'http', 'https', 'data' ) ),
			esc_attr( implode( ' ', $class ) ),
			(int) $size,
			(int) $size,
			$args['extra_attr']
		);
	}

	/**
	 * Get details from ID or email.
	 *
	 * @param  mixed $id_or_email User ID, email, or comment object.
	 * @return array
	 */
	public function get_details_from_id_or_email( $id_or_email, $alt = '' ): array {
		// Check if it's a numeric user ID
		if ( is_numeric( $id_or_email ) ) {
			$user  = get_userdata( $id_or_email );
			$name  = $user ? $user->display_name : '';
			$email = $user ? $user->user_email : '';
		} elseif ( is_object( $id_or_email ) ) {
			$name  = $id_or_email->comment_author;
			$email = $id_or_email->comment_author_email;
		} elseif ( is_string( $id_or_email ) ) {
			$user  = get_user_by( 'email', $id_or_email );
			$email = $user ? $user->user_email : $id_or_email;
			$name  = $user ? $user->display_name : $alt; // Use display name or alt
		}

		$details = array(
			'email' => $email,
			'name'  => $name,
		);

		return $details;
	}

	/**
	 * Filter avatar URL.
	 *
	 * @param string $url         Avatar URL.
	 * @param mixed  $id_or_email User ID, email, or comment object.
	 * @param array  $args        Avatar arguments.
	 * @return string Filtered avatar URL.
	 */
	public function filter_avatar_url( $url, $id_or_email, $args ) {
		$details = $this->get_details_from_id_or_email( $id_or_email );
		$email   = $details['email'];

		// If the person has a gravatar, use it.
		if ( $this->validate_gravatar( $email ) ) {
			return $url;
		}

		// If the person has a local avatar, use it.
		if ( $this->validate_local_avatar( $email, $args['size'] ) ) {
			return $url;
		}

		return $this->get_avatar_url( $id_or_email, $args );
	}

	/**
	 * Get avatar URL.
	 *
	 * @param mixed  $id_or_email User ID, email, or comment object.
	 * @param array  $args        Avatar arguments.
	 * @return string Filtered avatar URL.
	 */
	public function get_avatar_url( $id_or_email, $args, $name = '?' ) {
		$user = false;

		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', absint( $id_or_email ) );
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} elseif ( $id_or_email instanceof \WP_User ) {
			$user = $id_or_email;
		} elseif ( $id_or_email instanceof \WP_Comment ) {
			$user = get_user_by( 'id', $id_or_email->user_id );

			// Special-case for comments.
			if ( ! $user ) {
				return $this->get_avatar_svg( $id_or_email->comment_author );
			}
		}

		if ( ! $user ) {
			return $this->get_avatar_svg( '?' );
		}

		return $this->get_avatar_svg( $name );
	}

	/**
	 * Get the default avatar URL.
	 *
	 * @param  string|null $name Name to derive avatar from.
	 * @return string Default avatar URL.
	 */
	public function get_avatar_svg( string $name = '?' ): string {
		$initials = $this->get_initials( $name );

		$tmpl = <<<"END"
			<?xml version="1.0" encoding="UTF-8"?>
			<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
				<rect width="100%%" height="100%%" fill="%s"/>
				<text
					fill="#fff"
					font-family="sans-serif"
					font-size="26"
					font-weight="500"
					dominant-baseline="middle"
					text-anchor="middle"
					x="50%%"
					y="55%%"
				>
					%s
				</text>
			</svg>
			END;

		// Get the color for the avatar.
		$color = $this->make_color( $name );

		$data = sprintf(
			$tmpl,
			esc_attr( $color ),
			esc_xml( $initials )
		);

		$uri = 'data:image/svg+xml;base64,' . base64_encode( $data );
		return $uri;
	}

	/**
	 * Validate Gravatar
	 *
	 * @param string $email
	 *
	 * @return bool
	 */
	public function validate_gravatar( $email ): bool {
		// Craft a potential url and test its headers
		$hash = md5( strtolower( trim( $email ) ) );

		$avatar_url = 'http://www.gravatar.com/avatar/' . $hash . '?d=404';
		$response   = wp_remote_get( $avatar_url );

		if ( is_wp_error( $response ) || ( isset( $response['response']['code'] ) && 404 === $response['response']['code'] ) ) {
			$has_valid_avatar = false;
		} else {
			$has_valid_avatar = true;
		}

		return $has_valid_avatar;
	}

	/**
	 * Validate Local Avatar
	 *
	 * @param string $email
	 *
	 * @return bool
	 */
	public function validate_local_avatar( $email, $size ) {
		if ( ! class_exists( 'Simple_Local_Avatars' ) ) {
			return false;
		}

		$simple_local_avatar_url = ( new \Simple_Local_Avatars() )->get_simple_local_avatar_url( $email, $size );
		if ( $simple_local_avatar_url ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate the color based on the name.
	 */
	public function make_color( string $name ) {
		$generate_hash  = md5( strtolower( trim( $name ) ) );
		$base_rgb_array = sscanf( $generate_hash, '%2x%2x%2x' );

		// Convert RGB array to hex color string
		return sprintf( '#%02x%02x%02x', $base_rgb_array[0], $base_rgb_array[1], $base_rgb_array[2] );
	}

	/**
	 * Turn name into initials.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function get_initials( string $name ): string {
		// Turn Name into Array
		$words    = explode( ' ', $name );
		$initials = '';

		$name_length = count( $words );
		if ( $name_length > 2 ) {
			$raw_words = $words;
			$words     = array(
				$raw_words[0],
				$raw_words[ $name_length - 1 ],
			);
		}

		// For each word in the array, get the first letter and it's associated number
		foreach ( $words as $word ) {
			$initials .= strtoupper( substr( $word, 0, 1 ) );
		}

		return $initials;
	}
}
