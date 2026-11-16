<?php
/*
 * Gutenberg Blocks
 */
namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Blocks\Serverside;

class Blocks implements Component {

	protected static $directory;

	/*
	 * Init
	 */
	public function init(): void {
		new Serverside();
	}

	/**
	 * Register any needed hooks/filters.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'action_register_blocks' ), 10 );
		add_filter( 'block_categories_all', array( $this, 'action_add_block_category' ) );

		// Enqueues.
		add_action( 'enqueue_block_assets', array( $this, 'block_assets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'block_editor_assets' ) );
	}

	/**
	 * Get the absolute path to the asset file.
	 *
	 * @param string $path_relative Path relative to this plugin directory root.
	 *
	 * @return string Absolute path to the file.
	 */
	public function path_to( $path_relative ) {
		self::$directory = dirname( __DIR__, 1 ) . '/blocks';
		return sprintf( '%s/%s', self::$directory, ltrim( $path_relative, '/\\' ) );
	}

	/**
	 * Register all blocks living in the "/blocks/" folder in the theme.
	 */
	public function action_register_blocks() {
		$folders = glob( $this->path_to( '/build/*' ) );

		foreach ( $folders as $folder ) {
			$block_json_file = sprintf( '%s/block.json', $folder );

			if ( file_exists( $block_json_file ) ) {
				register_block_type( $block_json_file );
			}
		}
	}

	/**
	 * Adding a new (custom) block category.
	 *
	 * @param   array $block_categories       Array of categories for block types.
	 */
	public function action_add_block_category( $block_categories ) {
		$block_categories[] = array(
			'slug'  => 'lezwatch',
			'title' => 'LezWatch.TV Blocks',
			'icon'  => 'smiley',
		);

		return $block_categories;
	}

	/**
	 * Block FRONT END Assets
	 *
	 * These load in the editor and on the front end. Only the editor needs the
	 * 'wp-editor' dependency (so these sheets print after the editor's own
	 * CSS). On the front end it would pull in the editor's whole stylesheet
	 * stack (wp-components, wp-block-editor, wp-editor and friends, about
	 * 470 KB) on every page, and nothing here uses it.
	 */
	public function block_assets() {
		$deps    = is_admin() ? array( 'wp-editor' ) : array();
		$folders = glob( $this->path_to( '/build/*' ) );

		foreach ( $folders as $folder ) {
			$local_css_file = sprintf( '%s/style-block.css', $folder );

			if ( file_exists( $local_css_file ) ) {
				$css_folder = basename( dirname( $local_css_file ) );

				wp_enqueue_style(
					'lwtv-gutenberg-style-' . $css_folder, // Handle.
					LWTV_PLUGIN_URL . '/php/blocks/build/' . $css_folder . '/style-block.css',
					$deps,
					filemtime( $local_css_file )
				);
			}
		}
	}

	/**
	 * Block Editor Assets
	 *
	 * Editor-only styles (block.css). These need their own handle: sharing the
	 * front-end handle meant whichever enqueue ran first won, so block.css never
	 * loaded for any block that also has a style-block.css.
	 */
	public function block_editor_assets() {
		// enqueue_block_assets also fires on the front end; these are editor-only.
		if ( ! is_admin() ) {
			return;
		}

		$folders = glob( $this->path_to( '/build/*' ) );

		foreach ( $folders as $folder ) {
			$local_css_file = sprintf( '%s/block.css', $folder );

			if ( file_exists( $local_css_file ) ) {
				$css_folder = basename( dirname( $local_css_file ) );

				wp_enqueue_style(
					'lwtv-gutenberg-editor-' . $css_folder, // Handle.
					LWTV_PLUGIN_URL . '/php/blocks/build/' . $css_folder . '/block.css',
					array( 'wp-editor' ),
					filemtime( $local_css_file )
				);
			}
		}
	}
}
