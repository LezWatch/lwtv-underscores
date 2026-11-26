<?php
/**
 * Name: Search Rules Page
 * Description: Search > Rules, the admin page for synonyms.
 *
 * Admins only. The top-level Search menu (Search_Menu) opens this page. The page is a React app (the `admin/` npm workspace, built
 * by `npm run build:workspace`) that talks to Search_Rules_Rest; this class
 * only adds its submenu item, prints the mount point and loads the build on
 * its own screen.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Rules_Page {

	const SLUG       = 'lwtv_meili_rules';
	const CAPABILITY = 'manage_options';

	/**
	 * Hook suffix of the page, set when the menu item is added.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the page as the Search menu's first item, labelled Rules.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		$hook = add_submenu_page( Search_Menu::PARENT, __( 'Search Rules', 'lwtv-underscores' ), __( 'Rules', 'lwtv-underscores' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );

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
			'lwtvMeiliRules',
			array(
				'restPath'  => 'lwtv/v1/meili-rules',
				'pollMs'    => 2000,
				'pollMaxMs' => 60000,
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
			<h1><?php esc_html_e( 'Search Rules', 'lwtv-underscores' ); ?></h1>
			<div id="lwtv-meili-rules-root"></div>
			<noscript><p><?php esc_html_e( 'The Search Rules page needs JavaScript.', 'lwtv-underscores' ); ?></p></noscript>
		</div>
		<?php
	}
}
