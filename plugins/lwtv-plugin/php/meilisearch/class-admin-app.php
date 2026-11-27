<?php
/**
 * Name: Admin App
 * Description: Loads the one Search admin app bundle on a Search screen.
 *
 * Rules and Settings share one build (`admin/build/index.js`): the bundle
 * mounts whichever screen's root the page printed. Each page passes its own
 * start-up config as a window global, printed as JSON so numbers stay numbers.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_App {

	const HANDLE = 'lwtv-meili-admin';

	/**
	 * Enqueue the bundle, its styles and translations, plus one screen's config.
	 *
	 * @param string $window_var Window global name, e.g. lwtvMeiliRules.
	 * @param array  $config Start-up config.
	 * @return bool False when the build is missing (local dev before a build).
	 */
	public static function enqueue( string $window_var, array $config ): bool {
		$dir        = __DIR__ . '/admin/build/';
		$asset_file = $dir . 'index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return false;
		}

		$asset = require $asset_file;
		$asset = is_array( $asset ) ? $asset : array();
		$url   = LWTV_PLUGIN_URL . '/php/meilisearch/admin/build/';
		$deps  = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array();
		$ver   = (string) ( $asset['version'] ?? '' );

		wp_enqueue_script( self::HANDLE, $url . 'index.js', $deps, $ver, true );
		wp_set_script_translations( self::HANDLE, 'lwtv-underscores' );
		wp_add_inline_script( self::HANDLE, 'window.' . $window_var . ' = ' . wp_json_encode( $config ) . ';', 'before' );

		wp_enqueue_style( 'wp-components' );

		// wp-scripts names the CSS of the style.scss files index.js imports style-index.css.
		if ( file_exists( $dir . 'style-index.css' ) ) {
			wp_enqueue_style( self::HANDLE, $url . 'style-index.css', array( 'wp-components' ), $ver );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		return true;
	}

	/**
	 * Notice shown when the app hasn't been built.
	 *
	 * @return void
	 */
	public static function missing_build_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'The Search admin app has not been built. Run `npm run build:workspace`.', 'lwtv-underscores' )
		);
	}
}
