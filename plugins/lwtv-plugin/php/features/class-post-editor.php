<?php
/*
 * Post Editor: Customizations for the block editor interface.
 *
 * @version 1.0.0
 * @package lwtv-plugin
 */

namespace LWTV\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Editor {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_head', array( $this, 'single_scroll_on_all_screens' ) );
	}

	/**
	 * Restore single-scroll post editor on ALL screens.
	 *
	 * WP 7.0 split the block editor into two independently scrolling regions
	 * (content and metaboxes). On narrow screens this forces editors to manage
	 * two separate scroll positions, which is unusable. Below 1280px we revert
	 * the interface skeleton to a single, naturally flowing document.
	 */
	public function single_scroll_on_all_screens(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}
		?>
		<style>

		</style>
		<?php
	}
}
