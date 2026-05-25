<?php
// phpcs:disable WordPress.WP.CapitalPDangit.MisspelledInText -- We use APIs.
/**
 * Block Agents
 *
 * Block use of agents, until we can figure out how to limit it by post type. we cant be having hallucinations about data.
 */

namespace LWTV\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class BlockAgents {

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! defined( 'WP_AI_SUPPORT' ) ) {
			define( 'WP_AI_SUPPORT', false );
		}

		// Hide the menu
		add_action( 'admin_menu', array( $this, 'remove_page' ) );
	}

	/**
	 * Hide the menu page
	 */
	public static function remove_page() {
		remove_submenu_page( 'options-general.php', 'options-connectors.php' );
	}
}
