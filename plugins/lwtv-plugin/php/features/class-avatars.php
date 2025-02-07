<?php
/**
 * Avatars
 */

namespace LWTV\Features;

class Avatars {

	/**
	 * Letter to numbers
	 */
	private $letter_values = array(
		'a' => 0xFF0000, // Red
		'b' => 0xFFFF00, // Green
		'c' => 0x00FF00, // Blue
		'd' => 0x0000FF, // Yellow
		'e' => '13', // Light Blue
		'f' => '14', // Navy Blue
		'g' => '15', // Dark Blue
		'h' => '16', // Purple
		'i' => '17', // Magenta
		'j' => '18', // Black
		'k' => '-1', // Dark Gray
		'l' => '-2', // Dark Green
		'm' => '13', // Light Blue
		'n' => '-12', // Navy Blue
		'o' => '-11', // Purple
		'p' => '-10', // Magenta
		'q' => '-9',  // Dark Green
		'r' => '-8',   // Black
		's' => '-7',   // Gray
		't' => '-6',   // Navy Blue
		'u' => '-5',   // Magenta
		'v' => '-4',   // Purple
		'w' => '-3',   // Dark Green
		'x' => '-2',   // Navy Blue
		'y' => '-1',   // Black
		'z' => '0',
	);

	/**
	 * Constructor.
	 */
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
			$default_avatar = str_replace( 'wp-content/plugins/lwtv-plugin', 'wp-content/themes/lwtv-underscores/plugins/lwtv-plugin', $default_avatar );
			return '<img alt="" src="' . $default_avatar . '" srcset="' . $default_avatar . '" class="avatar avatar-32 photo" height="32" width="32" loading="lazy" decoding="async">';
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

		$initials  = $this->get_initials( $name );
		$hex_color = $this->make_color( $initials );

		// Determine if the input is a comment object
		$is_comment_object = is_object( $id_or_email ) && isset( $id_or_email->comment_author );

		// Set padding style based on whether it's a comment
		$padding_style = $is_comment_object ? 'padding:5px;' : '';

		// Tweak Size for Admin pages
		$size  = is_admin() ? $size - 10 : $size;
		$admin = is_admin() ? 'float: left;margin-right: 10px;margin-top: 1px;' : '';

		// Create initials avatar
		$initialized_avatar = sprintf(
			'<div style="width:%1$spx;height:%1$spx;border-radius:50%%;background-color:%2$s;position: relative;%3$sdisplay:inline-block;%5$s">
			<span style="position:absolute;top:50%%;left:50%%;transform:translate(-50%%,-50%%);font-size:%4$spx;font-weight:bold;color:white;height:unset;">%6$s</span>
		</div>',
			$size, // %1$s
			$hex_color, // %2$s
			$admin, // %3$s
			floor( $size / 2.3 ), // Adjust font size based on avatar size %4$s
			$padding_style, // %5$s
			$initials // %6$s
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

		if ( is_wp_error( $response ) || ( isset( $response['response']['code'] ) && 404 === $response['response']['code'] ) ) {
			$has_valid_avatar = false;
		} else {
			$has_valid_avatar = true;
		}

		return $has_valid_avatar;
	}

	/**
	 * Generate the color based on initials
	 */
	public function make_color( string $initials ) {
		$result = '';
		foreach ( str_split( $initials ) as $char ) {
			$char = strtolower( $char );
			if ( array_key_exists( $char, $this->letter_values ) ) {
				$result .= $this->letter_values[ $char ];
			}
		}

		$clean_string = sprintf( '%02x', $result );
		$hex_code     = substr( md5( $clean_string ), 0, 6 );

		return '#' . $hex_code;
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

		// For each word in the array, get the first letter and it's associated number
		foreach ( $words as $word ) {
			$initials .= strtoupper( substr( $word, 0, 1 ) );
		}

		return $initials;
	}
}
