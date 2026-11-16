<?php
/*
 * Environment Settings
 *
 */

namespace LWTV\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Environment {

	// Default environment.
	public $default_env_type;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( function_exists( 'wp_get_environment_type' ) && ! empty( wp_get_environment_type() ) ) {
			$this->default_env_type = 'uilabs-' . wp_get_environment_type();
		}

		if ( defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE ) {
			$this->default_env_type = 'uilabs-development';
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'admin_body_class', array( &$this, 'admin_body_class' ) );
	}

	/**
	 * Load the environment colors in the admin, except on production.
	 */
	public function admin_enqueue_scripts(): void {
		if ( 'uilabs-production' !== $this->default_env_type ) {
			wp_enqueue_style( 'ui-labs-identity', LWTV_PLUGIN_URL . '/assets/css/environment.css', array(), LWTV_PLUGIN_VERSION );
		}
	}

	/**
	 * Identify Server UI Experiment
	 *
	 * Allows users to easily spot whether they are logged in to their development, staging, or live server.
	 */
	public function admin_body_class( $classes ) {
		if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
			$classes .= ' ' . $this->default_env_type . ' ';
		}
		return $classes;
	}
}
