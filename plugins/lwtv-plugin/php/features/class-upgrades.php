<?php
/**
 * Upgrade Control
 *
 * Controls the upgrades and updates for the site, preventing overrides for our theme, and not
 * allowing auto updates on the dev site.
 */

namespace LWTV\Features;

// Prevent auto upgrades if we're on the dev site.
if ( defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE ) {
	return;
}

class Upgrades {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Run finders and actions on inits.
	 *
	 * @return void
	 */
	public function init(): void {

		// Disable update notifications for your theme. This doesn't change auto updates, but it does hide things.
		add_filter( 'site_transient_update_themes', array( $this, 'theme_update_notification' ) );

		// Protect the theme from being updated.
		add_filter( 'http_request_args', array( $this, 'protect_theme_override' ), 10, 2 );

		// Prevent auto upgrades if we're on the dev site.
		$update_return = ( defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE ) ? '__return_false' : '__return_true';

		// Allow Updates to core, plugins, and themes:
		add_filter( 'auto_update_core', $update_return );
		add_filter( 'auto_update_plugin', $update_return );
		add_filter( 'auto_update_theme', $update_return );

		// Don't update core themes and plugins (we don't need them)
		define( 'CORE_UPGRADE_SKIP_NEW_BUNDLED', true );

		// Force updates even if Git is there.
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false', 1 );

		// Suspend or force emails (false == no email ; true == yes email)
		add_filter( 'auto_core_update_send_email', '__return_false', 1 );
		add_filter( 'automatic_updates_send_debug_email', '__return_true', 1 );

		// Disable email update alerts for themes and plugins.
		add_filter( 'auto_plugin_update_send_email', '__return_false' );
		add_filter( 'auto_theme_update_send_email', '__return_false' );
	}

	/**
	 * Protect the theme from being updated.
	 *
	 * In case some idiot ever submits lwtv-underscores as a theme, this prevents
	 * it from being updated.
	 *
	 * Look, this should never happen, but the last thing we want is for this theme to
	 * get updated by some rando with a grudge.
	 *
	 * @param array  $response The response array.
	 * @param string $url      The URL being requested.
	 *
	 * @return array
	 */
	public function protect_theme_override( $response, $url ) {
		if ( 0 === strpos( $url, 'https://api.wordpress.org/themes/update-check' ) ) {
			$themes = json_decode( $response['body']['themes'] );
			unset( $themes->themes->{get_option( 'template' )} );
			unset( $themes->themes->{get_option( 'stylesheet' )} );
			$response['body']['themes'] = wp_json_encode( $themes );
		}
		return $response;
	}

	/**
	 * Disable update notifications for your theme. This doesn't change auto updates, but it does hide things.
	 *
	 * @param object $value The value to filter.
	 *
	 * @return object
	 */
	public function theme_update_notification( $value ) {
		if ( isset( $value ) && is_object( $value ) ) {
			unset( $value->response['lwtv-underscores'] );
		}
		return $value;
	}
}
