<?php
/**
 * One-shot admin notices, stashed per user between a POST and the page after it.
 *
 * The admin_post_* handlers on our tools screens all end the same way: do the
 * work, then wp_safe_redirect() back to the tab. A redirect throws away
 * everything the handler knew, so the result has to be parked somewhere the next
 * request can find it. That is what this does.
 *
 * Callers keep their own thin set_notice()/show_notice() pair. This holds the
 * mechanics they were each copying; they hold the three things that genuinely
 * differ.
 *
 * @package LWTV
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notice {

	/**
	 * How long a stashed notice survives, in minutes.
	 */
	const TTL_MINUTES = 5;

	/**
	 * Stash a notice for the current user.
	 *
	 * @param  string $prefix  The caller's transient prefix; the user ID is
	 *                         appended, so two screens cannot clobber each other.
	 * @param  string $type    'success', 'error' or 'info'.
	 * @param  string $message Text, or markup if the caller shows it unescaped.
	 * @param  string $link    Optional URL to offer afterwards.
	 * @return void
	 */
	public static function set( string $prefix, string $type, string $message, string $link = '' ): void {
		set_transient(
			self::key( $prefix ),
			array(
				'type'    => $type,
				'message' => $message,
				'link'    => $link,
			),
			self::TTL_MINUTES * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Print the pending notice, if there is one, and clear it.
	 *
	 * Cleared before it is printed rather than after, so a fatal while rendering
	 * cannot leave a notice that reappears on every subsequent page load.
	 *
	 * @param  string $prefix       The caller's transient prefix.
	 * @param  string $link_text    Anchor text for the stashed link.
	 * @param  bool   $allow_markup Whether the message may contain HTML. False
	 *                              escapes it; only pass true when the caller
	 *                              authors the message itself.
	 * @return void
	 */
	public static function show( string $prefix, string $link_text = '', bool $allow_markup = false ): void {
		$key    = self::key( $prefix );
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$message = (string) $notice['message'];
		$link    = (string) ( $notice['link'] ?? '' );
		$text    = ( '' !== $link_text ) ? $link_text : __( 'Edit', 'lwtv' );
		?>
		<div class="notice <?php echo esc_attr( self::css_class( (string) ( $notice['type'] ?? '' ) ) ); ?> is-dismissible">
			<p>
				<?php
				if ( $allow_markup ) {
					echo wp_kses_post( $message );
				} else {
					echo esc_html( $message );
				}
				?>
				<?php if ( '' !== $link ) : ?>
					<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $text ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * The WordPress notice class for a notice type.
	 *
	 * Anything unrecognised reads as a success.
	 *
	 * @param  string $type 'success', 'error' or 'info'.
	 * @return string
	 */
	public static function css_class( string $type ): string {
		switch ( $type ) {
			case 'error':
				return 'notice-error';
			case 'info':
				return 'notice-info';
		}

		return 'notice-success';
	}

	/**
	 * The transient key for the current user.
	 *
	 * @param  string $prefix The caller's transient prefix.
	 * @return string
	 */
	private static function key( string $prefix ): string {
		return $prefix . get_current_user_id();
	}
}
