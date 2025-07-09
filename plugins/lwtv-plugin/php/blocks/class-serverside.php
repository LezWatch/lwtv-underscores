<?php
/**
 * Register Block Types
 *
 * All this is needed for server side render.
 */

namespace LWTV\Blocks;

use LWTV\Calendar\Display as CalendarBlocks;
use LWTV\Features\Shortcodes;

class Serverside {

	// Directory
	protected static $directory;

	/**
	 * Constructor
	 */
	public function __construct() {
		new Shortcodes();
		self::$directory = __DIR__;

		// Register SSR blocks.
		// author-box
		register_block_type(
			'lwtv/author-box',
			array(
				'attributes'      => array(
					'api_version' => 3,
					'users'       => array( 'type' => 'string' ),
					'format'      => array( 'type' => 'string' ),
				),
				'render_callback' => array( $this, 'render_author_box' ),
			)
		);

		// glossary
		register_block_type(
			'lez-library/glossary',
			array(
				'attributes'      => array(
					'api_version' => 3,
					'taxonomy'    => array( 'type' => 'string' ),
				),
				'render_callback' => array( $this, 'render_glossary' ),
			)
		);

		// TV Show Calendar
		register_block_type(
			'lwtv/tvshow-calendar',
			array(
				'attributes'      => array(
					'api_version' => 3,
				),
				'render_callback' => array( $this, 'render_tvshow_calendar' ),
			)
		);

		// Private Notes
		register_block_type(
			'lez-library/private-note',
			array(
				'attributes'      => array(
					'api_version' => 3,
				),
				'render_callback' => array( $this, 'render_private_blocks' ),
			)
		);
	}

	/**
	 * Render the Author Box
	 */
	public function render_author_box( $attributes ) {
		return ( new Shortcodes() )->author_box( $attributes );
	}

	/**
	 * Render the Glossary
	 */
	public function render_glossary( $attributes ) {
		return ( new Shortcodes() )->glossary( $attributes );
	}

	/**
	 * Render the calendar
	 */
	public function render_tvshow_calendar() {
		// Require the calendar file
		$return = ( new CalendarBlocks() )->make();
		return $return;
	}

	/**
	 * Render private blocks
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The block content.
	 *
	 * @return string The block content.
	 */
	public function render_private_blocks( $attributes, $content ) {
		// If the user is logged in, on the admin or can edit published posts, show the content.
		if ( is_admin() || ( is_user_logged_in() && current_user_can( 'edit_published_posts' ) ) ) {
			return $content;
		}

		return '';
	}
}
