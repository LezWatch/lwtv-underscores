<?php
/**
 * Name: Search Menu
 * Description: The top-level "Search" admin menu that holds the search pages.
 *
 * Admins only (manage_options). The menu opens Search Settings, its first
 * item; Rules and Compare follow as submenus of PARENT. Page slugs (and so their URLs,
 * admin.php?page=…) don't change with the menu.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Menu {

	/**
	 * Parent slug for every search page: the page the menu opens.
	 */
	const PARENT = Search_Settings_Page::SLUG;

	const CAPABILITY = 'manage_options';

	/**
	 * Right after the LezWatch.TV menu (position 2).
	 */
	const POSITION = 3;

	private Search_Settings_Page $settings;

	/**
	 * @param Search_Settings_Page $settings The page the menu opens.
	 */
	public function __construct( Search_Settings_Page $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the hooks. Runs before the pages add their submenus (20–22).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
	}

	/**
	 * Add the top-level menu.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_menu_page(
			__( 'Search Settings', 'lwtv-underscores' ),
			__( 'Search', 'lwtv-underscores' ),
			self::CAPABILITY,
			self::PARENT,
			array( $this->settings, 'render' ),
			lwtv_plugin()->get_menu_icon( 'meilisearch', 'dashicons-search' ),
			self::POSITION
		);
	}
}
