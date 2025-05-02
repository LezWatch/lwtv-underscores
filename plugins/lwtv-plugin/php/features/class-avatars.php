<?php
/**
 * Avatars as SVGs, not default Gravatars.
 */

namespace LWTV\Features;

class Avatars {

	private $gravatar_enabled     = true;
	private $local_avatar_enabled = false;
	private $default_avatar       = LWTV_PLUGIN_URL . '/assets/images/unicorn.svg';
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->local_avatar_enabled = ( is_plugin_active( 'simple-local-avatars/simple-local-avatars.php' ) && class_exists( 'Simple_Local_Avatars' ) );

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
	 *
	 * @return string Filtered avatar HTML.
	 */
	public function filter_avatar( $avatar, $id_or_email, $size, $default_url, $alt, $args ) {

		$details = $this->get_details_from_id_or_email( $id_or_email );
		$email   = $details['email'];
		$name    = $details['name'];
		$is_user = $details['is_user'];

		$class = array( 'avatar', 'avatar-' . (int) $size, 'photo' );
		if ( ! empty( $args['class'] ) ) {
			$class = array_merge( $class, (array) $args['class'] );
		}

		if ( $is_user ) {
			return $this->get_avatar_from_user( $details, $avatar, $size, $class, $args, $alt );
		}

		$svg_already = $this->maybe_get_svg_avatar_url( $email );
		if ( $svg_already ) {
			$avatar_url = $svg_already;
		} else {
			$avatar_url = $this->get_avatar_url( $id_or_email, $args, $name );
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
	public function get_details_from_id_or_email( $id_or_email ): array {
		$details = array(
			'email'   => ( is_string( $id_or_email ) && str_contains( $id_or_email, '@' ) ) ? $id_or_email : '',
			'name'    => '',
			'is_user' => false,
		);

		// If the ID or email is a numeric user ID or a comment with a user ID, get the user data.
		$maybe_user = ( is_numeric( $id_or_email ) || ( is_object( $id_or_email ) && $id_or_email instanceof \WP_Comment && isset( $id_or_email->user_id ) ) ) ? true : false;

		if ( $maybe_user ) {
			$user_id = is_numeric( $id_or_email ) ? $id_or_email : $id_or_email->user_id;
			$user    = get_userdata( $user_id );
			if ( $user ) {
				$details['name']    = $user->display_name;
				$details['email']   = $user->user_email;
				$details['is_user'] = true;
			}
		} elseif ( is_object( $id_or_email ) && $id_or_email instanceof \WP_Comment ) {
			// If the object is a comment, get the comment author and email.
			$details['name']    = $id_or_email->comment_author ?? '';
			$details['email']   = $id_or_email->comment_author_email ?? '';
			$details['is_user'] = false;
		} elseif ( is_string( $id_or_email ) && ! empty( $details['email'] ) ) {
			// If it's a string email, get the user data.
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$details['name']    = $user->display_name;
				$details['is_user'] = true;
			}
		}

		// Return the details.
		return $details;
	}

	/**
	 * Get avatar from user.
	 *
	 * @param array $details Details from ID or email.
	 * @param string $avatar Avatar HTML.
	 *
	 * @return string Filtered avatar HTML.
	 */
	public function get_avatar_from_user( $details, $avatar, $size, $classname, $args, $alt ) {
		// This should never happen, but just in case.
		if ( ! $details['is_user'] ) {
			return $this->default_avatar;
		}

		$email = $details['email'];

		if ( $this->local_avatar_enabled && $this->validate_local_avatar( $email, $size ) ) {
			return $avatar;
		}

		if ( $this->gravatar_enabled && $this->validate_gravatar( $email ) ) {
			return $avatar;
		}

		// return default avatar
		return sprintf(
			"<img alt='%s' src='%s' class='%s' height='%d' width='%d' %s/>",
			esc_attr( $alt ),
			esc_url( $this->default_avatar, array( 'http', 'https', 'data' ) ),
			esc_attr( implode( ' ', $classname ) ),
			(int) $size,
			(int) $size,
			$args['extra_attr']
		);
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
		$email   = $details['email'] ?? '';

		// If the person has a gravatar, use it.
		if ( $this->validate_gravatar( $email ) ) {
			return $url;
		}

		// If the person has a local avatar, use it.
		if ( $this->validate_local_avatar( $email, $args['size'] ) ) {
			return $url;
		}

		$svg_already = $this->maybe_get_svg_avatar_url( $email );
		if ( $svg_already ) {
			return $svg_already;
		}

		return $this->get_avatar_url( $id_or_email, $args, $details['name'] );
	}

	/**
	 * Get avatar URL.
	 *
	 * @param mixed  $id_or_email User ID, email, or comment object.
	 * @param array  $args        Avatar arguments.
	 * @param string $name        Name to derive avatar from.
	 * @return string Filtered avatar URL.
	 */
	public function get_avatar_url( $id_or_email, $args, $name = '?' ) {
		$details = $this->get_details_from_id_or_email( $id_or_email );
		$email   = $details['email'];
		$name    = $details['name'];
		$is_user = $details['is_user'];

		if ( ! $is_user && $id_or_email instanceof \WP_Comment ) {
			$name  = $id_or_email->comment_author ?? '?';
			$email = $id_or_email->comment_author_email ?? '';
		}

		// If there's no email, use the default avatar.
		if ( empty( $email ) ) {
			return $this->default_avatar;
		}

		// We don't need to check if the SVG already exists because you can't get here without checking for it already.
		return $this->make_svg_avatar( $name, $email );
	}

	/**
	 * Make an avatar SVG.
	 *
	 * @param  string|null $name Name to derive avatar from.
	 * @param  string|null $email Email to derive avatar from.
	 *
	 * @return string Default avatar URL.
	 */
	public function make_svg_avatar( string $name = '', string $email = '?' ): string {
		$initials = empty( $name ) ? $this->get_initials( $email ) : $this->get_initials( $name );

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
		$color = $this->make_svg_color( $email );

		$data = sprintf(
			$tmpl,
			esc_attr( $color ),
			esc_xml( $initials )
		);

		// Save the SVG to the filesystem.
		$uri = $this->save_avatar_svg( $email, $data );

		return $uri;
	}

	/**
	 * Save avatar SVG.
	 *
	 * @param string $email
	 * @param string $data
	 *
	 * @return string
	 */
	public function save_avatar_svg( string $email, string $data ): string {
		global $wp_filesystem;

		$upload_dir = wp_upload_dir();
		$folder     = $upload_dir['basedir'] . '/svg-avatars';

		// Make the uploads directory if it doesn't exist.
		if ( ! $wp_filesystem->is_dir( $folder ) ) {
			$wp_filesystem->mkdir( $folder, 0755, true );
		}

		// Convert the email to a filename.
		$filename = strtolower( str_replace( '@', '-', $email ) ) . '.svg';

		// Save the file.
		$file_path = $folder . '/' . $filename;
		$wp_filesystem->put_contents( $file_path, $data, 0644 );

		// Return the URL.
		return $upload_dir['baseurl'] . '/svg-avatars/' . $filename;
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
		$simple_local_avatar_url = ( new \Simple_Local_Avatars() )->get_simple_local_avatar_url( $email, $size );
		if ( $simple_local_avatar_url ) {
			return true;
		}

		return false;
	}

	/**
	 * Maybe get SVG Avatar URL
	 *
	 * @param string $email
	 *
	 * @return string|false
	 */
	public function maybe_get_svg_avatar_url( $email ): mixed {
		global $wp_filesystem;

		$upload_dir = wp_upload_dir();
		$folder     = $upload_dir['basedir'] . '/svg-avatars';
		$url        = $upload_dir['baseurl'] . '/svg-avatars';

		$filename = strtolower( str_replace( '@', '-', $email ) ) . '.svg';

		if ( $wp_filesystem->exists( $folder . '/' . $filename ) ) {
			return $url . '/' . $filename;
		}

		return false;
	}

	/**
	 * Generate the color based on the name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function make_svg_color( string $name ) {
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
		if ( str_contains( $name, '@' ) ) {
			$name = str_replace( '@', ' ', $name );
		}

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
