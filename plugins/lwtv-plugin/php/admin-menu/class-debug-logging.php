<?php
/**
 * Debug Logging Settings Page
 *
 * Provides admin UI to toggle debug mode and select which topics to log.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Debug_Logging {

	/**
	 * Valid log topics that can be enabled/disabled.
	 */
	public const VALID_LOG_TOPICS = array(
		'actors',
		'ai-agents',
		'buryqueers',
		'caching',
		'characters',
		'calculations',
		'calendar',
		'death',
		'is-queer',
		'missed-schedule',
		'postiz',
		'scheduler',
		'shadow-taxonomy',
		'shows',
		'statistics',
		'taxsync',
		'this-year',
		'tmdb',
		'validator',
		'wp-cli',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Void
	}

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'acf/init', array( $this, 'register_options_page' ) );
	}

	/**
	 * Register the ACF options sub-page.
	 *
	 * @return void
	 */
	public function register_options_page(): void {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}
		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Debug Logging', 'lwtv' ),
				'menu_title'  => __( 'Debug Logging', 'lwtv' ),
				'parent_slug' => 'lwtv',
				'capability'  => 'activate_plugins',
				'menu_slug'   => 'lwtv-debug-logging',
				'post_id'     => 'option',
			)
		);
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode_enabled(): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}
		return (bool) get_field( 'debug_mode', 'option' );
	}

	/**
	 * Get the enabled log topics.
	 *
	 * @return array
	 */
	public function get_enabled_topics(): array {
		$topics = get_field( 'log_topics', 'option' );
		$topics = is_array( $topics ) ? $topics : array();

		if ( empty( $topics ) ) {
			return self::VALID_LOG_TOPICS;
		}

		return array_intersect( $topics, self::VALID_LOG_TOPICS );
	}
}
