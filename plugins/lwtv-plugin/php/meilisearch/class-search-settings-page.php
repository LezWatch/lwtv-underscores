<?php
/**
 * Name: Search Settings Page
 * Description: Search > Settings: the switch, the fallback timeout and index status.
 *
 * Admins only. The screen is part of the one Search admin app (Admin_App);
 * this class adds the submenu item, prints the mount point and loads the
 * bundle on its own screen. Search_Settings_Rest is its API.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Settings_Page {

	const SLUG       = 'lwtv_meili_settings';
	const CAPABILITY = 'manage_options';

	/**
	 * Hook suffix of the page, set when the menu item is added.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Register the hooks. After Rules and Compare, so Settings is listed last.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the page under the Search menu, labelled Settings.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		$hook = add_submenu_page( Search_Menu::PARENT, __( 'Search Settings', 'lwtv-underscores' ), __( 'Settings', 'lwtv-underscores' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Load the app on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$loaded = Admin_App::enqueue(
			'lwtvMeiliSettings',
			array(
				'restPath'  => 'lwtv/v1/meili-settings',
				'pollMs'    => 2000,
				// A full reindex can run past a minute; keep polling while a job reports progress.
				'pollMaxMs' => 600000,
			)
		);
		if ( ! $loaded ) {
			add_action( 'admin_notices', array( Admin_App::class, 'missing_build_notice' ) );
		}
	}

	/**
	 * The mount point for the app.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Settings', 'lwtv-underscores' ); ?></h1>
			<div id="lwtv-meili-settings-root"></div>
			<noscript><p><?php esc_html_e( 'The Search Settings page needs JavaScript.', 'lwtv-underscores' ); ?></p></noscript>
		</div>
		<?php
	}
}
