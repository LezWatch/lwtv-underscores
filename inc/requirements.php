<?php
/**
 * Hard dependency checks.
 *
 * The site cannot render without ACF Pro (all CPT meta) or Action Scheduler
 * (every background task). Rather than let templates fatal on missing
 * `get_field()` / `as_schedule_single_action()` calls, we stop the front end
 * cold and serve a static maintenance page with a 503.
 *
 * This file is loaded from the very top of functions.php, BEFORE
 * plugins/index.php pulls in the LWTV plugin, so a missing dependency never
 * reaches CPT registration or the show score calculations.
 *
 * Escape hatch: define( 'LWTV_SKIP_REQUIREMENTS_CHECK', true ) in wp-config.php
 * to bypass the gate entirely.
 *
 * @package LWTV Underscores
 */

/**
 * Dependencies the site cannot run without.
 *
 * Each entry maps a human-readable label to a callable check. The checks match
 * the ones already used elsewhere in the codebase:
 * - `class_exists( 'ACF' )` mirrors plugins/lwtv-plugin/php/plugins/class-acf.php
 * - `function_exists( 'as_schedule_single_action' )` mirrors
 *   plugins/lwtv-plugin/php/_components/class-scheduler.php
 *
 * @return array<string, string> Array of plugin name => missing reason.
 */
function lwtv_theme_missing_requirements() {
	static $missing = null;

	if ( null !== $missing ) {
		return $missing;
	}

	$missing = array();

	if ( ! class_exists( 'ACF' ) ) {
		$missing['Advanced Custom Fields Pro'] = 'class ACF';
	}

	if ( ! function_exists( 'as_schedule_single_action' ) ) {
		$missing['Action Scheduler'] = 'function as_schedule_single_action()';
	}

	return $missing;
}

/**
 * Whether this request should be allowed through even with missing dependencies.
 *
 * We never gate wp-admin, wp-login, cron, WP-CLI, AJAX, XML-RPC, or the REST
 * API. If we did, there would be no way to log in and reactivate the plugin
 * that is missing.
 *
 * Note: REST is sniffed from the request URI because `REST_REQUEST` is not
 * defined until `parse_request`, long after this file runs.
 *
 * @return bool
 */
function lwtv_theme_requirements_request_is_exempt() {
	// WP-CLI and cron must keep working so `wp plugin activate` is possible.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return true;
	}

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return true;
	}

	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		return true;
	}

	// wp-admin, including admin-post.php and the plugin screens.
	if ( is_admin() ) {
		return true;
	}

	// wp-login.php, wp-register.php, and the password reset flow.
	if ( isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ), true ) ) {
		return true;
	}

	// REST API. Sniffed from the URI; see note above.
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		if ( str_contains( $request_uri, '/' . rest_get_url_prefix() . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Print an admin notice naming every missing dependency.
 *
 * Not dismissable: this is a site-down condition, not a nag.
 */
function lwtv_theme_requirements_admin_notice() {
	$missing = lwtv_theme_missing_requirements();

	if ( empty( $missing ) ) {
		return;
	}

	echo '<div class="notice notice-error">';
	echo '<p><strong>' . esc_html__( 'LezWatch.TV: the front end is offline.', 'lwtv-underscores' ) . '</strong></p>';
	echo '<p>' . esc_html__( 'The following required plugins are missing or inactive. Visitors are seeing a maintenance page until they are restored:', 'lwtv-underscores' ) . '</p>';
	echo '<ul style="list-style:disc;margin-left:2em;">';
	foreach ( $missing as $plugin_name => $reason ) {
		printf(
			'<li><strong>%1$s</strong> &mdash; %2$s</li>',
			esc_html( $plugin_name ),
			/* translators: %s: the PHP class or function that could not be found. */
			esc_html( sprintf( __( '%s not found', 'lwtv-underscores' ), $reason ) )
		);
	}
	echo '</ul>';
	echo '</div>';
}

/**
 * Run the dependency gate.
 *
 * On a front-end request with missing dependencies this renders the static
 * maintenance page and exits. Everywhere else it registers the admin notice
 * and returns so the site can be repaired.
 */
function lwtv_theme_check_requirements() {
	if ( defined( 'LWTV_SKIP_REQUIREMENTS_CHECK' ) && LWTV_SKIP_REQUIREMENTS_CHECK ) {
		return;
	}

	$missing = lwtv_theme_missing_requirements();

	if ( empty( $missing ) ) {
		return;
	}

	// Always warn in the admin, whichever request we are on.
	add_action( 'admin_notices', 'lwtv_theme_requirements_admin_notice' );

	if ( lwtv_theme_requirements_request_is_exempt() ) {
		return;
	}

	require_once __DIR__ . '/maintenance.php';
	lwtv_theme_render_maintenance_page( $missing );
}
