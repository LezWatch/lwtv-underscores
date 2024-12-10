<?php
/**
 * Avatars
 */

namespace LWTV\Features;

class Avatars {

	public function __construct() {
		add_filter( 'get_avatar', array( $this, 'avatar_filter' ), 10, 6 );
	}

	/**
	 * Custom avatar fallback
	 *
	 * @param string $avatar
	 * @param mixed  $id_or_email
	 * @param int    $size
	 * @param string $default
	 * @param string $alt
	 * @param array  $args
	 *
	 * @return string
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function avatar_filter( $avatar, $id_or_email, $size, $default_avatar, $alt, $args ): string {
		// Defaults:
		$name  = '';
		$email = '';
		$user  = false;

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

		// If the person has a gravatar, use it.
		if ( $this->validate_gravatar( $email ) ) {
			return $avatar;
		}

		// If they have a user account but no gravatar, use the default.
		if ( $user ) {
			return $default_avatar;
		}

		// If they don't have a user account, generate an avatar using their initials.
		return $this->generate_initials_avatar( $id_or_email, $size, $name, $email );
	}

	/**
	 * Generate initials avatar
	 *
	 * @param string $id_or_email
	 * @param int    $size
	 * @param string $name
	 * @param string $email
	 *
	 * @return string
	 */
	public function generate_initials_avatar( $id_or_email, $size, $name, $email ): string {

		// If no display name, use the first character of the email
		if ( empty( $name ) ) {
			$name = ! empty( $email ) ? substr( $email, 0, 1 ) : 'L'; // Use first character of email or '?'
		}

		// Extract initials
		$words    = explode( ' ', $name );
		$initials = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );

		// Convert the first byte of a string to a value between 0 and 255. If there are two initials, add the second byte.
		$numberized = ord( $initials[0] ) + ( isset( $initials[1] ) ? ord( $initials[1] ) : 0 );

		// Generate hex color based on initials
		$random_color = '#' . str_pad( dechex( $numberized ), 6, '0', STR_PAD_LEFT );

		// Determine if the input is a comment object
		$is_comment_object = is_object( $id_or_email ) && isset( $id_or_email->comment_author );

		// Set padding style based on whether it's a comment
		$padding_style = $is_comment_object ? 'padding:5px;' : '';

		// Create initials avatar
		$initialized_avatar = sprintf(
			'<div style="width:%1$spx;height:%1$spx;border-radius:50%%;background-color:%2$s;position:relative;display:inline-block;%4$s">
			<span style="position:absolute;top:50%%;left:50%%;transform:translate(-50%%,-50%%);font-size:%3$spx;font-weight:bold;color:white;height:unset;">%5$s</span>
		</div>',
			$size,
			$random_color,
			floor( $size / 2.3 ), // Adjust font size based on avatar size
			$padding_style,
			$initials
		);

		return $initialized_avatar;
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

		if ( isset( $response['response']['code'] ) && 404 === $response['response']['code'] ) {
			$has_valid_avatar = false;
		} else {
			$has_valid_avatar = true;
		}

		return $has_valid_avatar;
	}
}
