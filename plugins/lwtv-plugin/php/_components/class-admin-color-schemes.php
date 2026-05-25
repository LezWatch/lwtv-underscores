<?php
/*
 * LezWatch.TV Admin Color Schemes
 *
 * Forked from abandoned WordPress plugin "Admin Color Schemes" by Helen, Ryelle, and Mel (among others).
 *
 */

namespace LWTV\_Components;

use function add_action;
use function wp_admin_css_color;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Color_Schemes implements Component {
	/*
	 * Construct
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'add_colors' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'add_editor_themes' ) );
		add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Helper function to get stylesheet URL.
	 *
	 * @param string $color The folder name for this color scheme.
	 *
	 * @return string
	 */
	private function get_color_url( $color ) {
		$suffix = is_rtl() ? '-rtl' : '';
		return LWTV_PLUGIN_URL . "/php/admin-color-schemes/$color/colors$suffix.css?v=" . LWTV_THEME_VERSION['lwtv-underscores'];
	}

	/**
	 * Register color schemes.
	 */
	public function add_colors() {
		wp_admin_css_color(
			// The color name needs to stay misspelled for back-compat.
			'vinyard',
			__( 'Vineyard', 'lwtv-underscores' ),
			self::get_color_url( 'vineyard' ),
			array( '#301D25', '#462b36', '#ba8752', '#eabe3f' ),
			array(
				'base'    => '#f1f2f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'primary',
			__( 'Primary', 'lwtv-underscores' ),
			self::get_color_url( 'primary' ),
			array( '#282b48', '#35395c', '#e46713', '#e7c03a' ),
			array(
				'base'    => '#f1f2f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'80s-kid',
			__( '80\'s Kid', 'lwtv-underscores' ),
			self::get_color_url( '80s-kid' ),
			array( '#0c4da1', '#d13674', '#28b811' ),
			array(
				'base'    => '#e4e5e7',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'aubergine',
			__( 'Aubergine', 'lwtv-underscores' ),
			self::get_color_url( 'aubergine' ),
			array( '#4c4b5f', '#585a61', '#ba5b32', '#da9b49' ),
			array(
				'base'    => '#e4e4e7',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'cruise',
			__( 'Cruise', 'lwtv-underscores' ),
			self::get_color_url( 'cruise' ),
			array( '#292B46', '#36395c', '#cda200', '#79b591' ),
			array(
				'base'    => '#f1f1f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'flat',
			__( 'Flat', 'lwtv-underscores' ),
			self::get_color_url( 'flat' ),
			array( '#1F2C39', '#2c3e50', '#1abc9c', '#f39c12' ),
			array(
				'base'    => '#f1f2f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'lawn',
			__( 'Lawn', 'lwtv-underscores' ),
			self::get_color_url( 'lawn' ),
			array( '#0F1515', '#1e2a29', '#5D824B', '#a7b145' ),
			array(
				'base'    => '#f1f3f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'seashore',
			__( 'Seashore', 'lwtv-underscores' ),
			self::get_color_url( 'seashore' ),
			array( '#F8F6F1', '#d5cdad', '#7D6B5C', '#456a7f' ),
			array(
				'base'    => '#533C2F',
				'focus'   => '#F8F6F1',
				'current' => '#F8F6F1',
			)
		);

		wp_admin_css_color(
			'kirk',
			__( 'Kirk', 'lwtv-underscores' ),
			self::get_color_url( 'kirk' ),
			array( '#bd3854', '#5f1b29', '#321017' ),
			array(
				'base'    => '#fefcf7',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'contrast-blue',
			__( 'High Contrast Blue', 'lwtv-underscores' ),
			self::get_color_url( 'contrast-blue' ),
			array( '#22466d', '#5c98c8', '#a5cde8', '#dae9f3', '#9d2f4d' ),
			array(
				'base'    => '#151923',
				'focus'   => '#151923',
				'current' => '#151923',
			)
		);

		wp_admin_css_color(
			'adderley',
			__( 'Adderley', 'admin-color-schemes' ),
			self::get_color_url( 'adderley' ),
			array( '#bde7f0', '#216bce', '#1730e5' ),
			array(
				'base'    => '#f1f3f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);

		wp_admin_css_color(
			'modern-evergreen',
			__( 'Modern Evergreen', 'admin-color-schemes' ),
			self::get_color_url( 'modern-evergreen' ),
			array( '#1e8060', '#0f4232', '#1e1e1e', '#3855e1' ),
			array(
				'base'    => '#f1f3f3',
				'focus'   => '#fff',
				'current' => '#fff',
			)
		);
	}

	/**
	 * Add our theme custom properties to the editor.
	 */
	public function add_editor_themes() {
		wp_enqueue_style(
			'lwtv-custom-editor-themes',
			LWTV_PLUGIN_URL . '/php/admin-color-schemes/editor.css',
			array(),
			LWTV_THEME_VERSION['lwtv-underscores']
		);
	}

	/**
	 * Add the WordPress version to the body class, in format `wp-XX`.
	 * This allows for some conditional styling depending on version.
	 *
	 * @param string $classes Space-separated list of CSS classes.
	 * @return string Filtered class names.
	 */
	public function admin_body_class( $classes ) {
		list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

		$classes .= ' wp-' . substr( str_replace( '.', '', $display_version ), 0, 2 );

		return $classes;
	}
}
