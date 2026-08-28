<?php
/**
 * Debug Settings Page
 *
 * Provides admin UI to toggle debug mode and select which topics to log.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Debugging {

	/**
	 * Valid log topics that can be enabled/disabled.
	 *
	 * This is the vocabulary, and it is used in two directions:
	 * Plugins\Acf populates the `log_topics` checkbox from it, and
	 * Build\Log_Rules refuses to write a topic that is not in it. A topic
	 * missing from this list therefore cannot be logged at all -- which is why
	 * `imdb-verify` and `show-score` were added on 2026-08-27. Both were already
	 * being logged by live code and neither was declared, so under the old
	 * fail-open rule they wrote unconditionally and could never be switched off.
	 *
	 * Add the topic here first, then log it.
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
		'imdb-verify',
		'is-queer',
		'missed-schedule',
		'postiz',
		'scheduler',
		'shadow-taxonomy',
		'show-score',
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
				'page_title'  => __( 'Debugging Tools', 'lwtv' ),
				'menu_title'  => __( 'Debugging Tools', 'lwtv' ),
				'parent_slug' => 'lwtv',
				'capability'  => 'activate_plugins',
				'menu_slug'   => 'lwtv-debugging',
				'post_id'     => 'option',
			)
		);
	}
}
