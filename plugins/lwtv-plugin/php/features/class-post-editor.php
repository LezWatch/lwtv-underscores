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
			/* WP 7.0 meta boxes fix - show them + normal scrolling */
			.edit-post-meta-boxes-main.is-resizable {
				height: auto !important;
				max-height: 900% !important;  /* allows expansion */
				overflow: visible !important;
			}

			.edit-post-meta-boxes-main__presenter {
				display: none !important;
			}

			.components-resizable-box__container.editor-resizable-editor {
				height: auto !important;
			}

			.editor-visual-editor {
				overflow: visible !important;
			}

			.block-editor-iframe__scale-container iframe {
				min-height: 75vh !important;  /* keeps Gutenberg tall */
			}

			.edit-post-layout__metaboxes {
				margin-bottom: 2rem;
			}

			:root :where(.editor-styles-wrapper)::after {
				height: 10vh !important;
			}
		</style>
		<?php
	}
}
