<?php
/**
 * Plugin Controller
 */

if ( is_plugin_active( 'lwtv-plugin/functions.php' ) ) {
	deactivate_plugins( 'lwtv-plugin/functions.php' );
}

// Define First Year with queers:
define( 'LWTV_FIRST_YEAR', '1961' );

// Define when the site started (Sept 2013):
define( 'LWTV_CREATED_YEAR', '2013' );

// Plugin Home:
define( 'LWTV_PLUGIN_PATH', get_template_directory() . '/plugins/lwtv-plugin' );
define( 'LWTV_PLUGIN_URL', get_stylesheet_directory_uri() . '/plugins/lwtv-plugin' );

// Timezones:
define( 'LWTV_TIMEZONE', 'America/New_York' );
define( 'LWTV_SERVER_TIMEZONE', 'America/Los_Angeles' );

/**
 * Symbolicons
 */
$upload_dir = wp_upload_dir();
if ( ! defined( 'LWTV_SYMBOLICONS_PATH' ) ) {
	define( 'LWTV_SYMBOLICONS_PATH', $upload_dir['basedir'] . '/lezpress-icons/symbolicons/' );
}
if ( ! defined( 'LWTV_SYMBOLICONS_URL' ) ) {
	define( 'LWTV_SYMBOLICONS_URL', $upload_dir['baseurl'] . '/lezpress-icons/symbolicons/' );
}

// Load the lwtv plugin
require_once __DIR__ . '/lwtv-plugin/functions.php';

// Load Addons
require_once __DIR__ . '/lesbians.php';
