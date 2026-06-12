<?php
/**
 * Auto-Posting Settings Page
 *
 * Manages settings for automated posting to social media via Postiz.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auto_Posting {

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Initialize the settings page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'acf/init', array( $this, 'register_options_page' ) );
	}

	/**
	 * Register the Auto-Posting options sub-page via ACF.
	 *
	 * @return void
	 */
	public function register_options_page(): void {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Auto-Posting Options', 'lwtv' ),
				'menu_title'  => __( 'Auto-Posting', 'lwtv' ),
				'parent_slug' => 'lwtv',
				'capability'  => 'activate_plugins',
				'menu_slug'   => 'lwtv-auto-posting',
				'post_id'     => 'option',
			)
		);
	}
}
