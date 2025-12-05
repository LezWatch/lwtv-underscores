<?php
/*
 * LezWatch.TV Admin Menu
 *
 */

namespace LWTV\_Components;

use LWTV\Admin_Menu\Auto_Posting;
use LWTV\Admin_Menu\Exclusions;
use LWTV\Admin_Menu\Validation;

class Admin_Menu implements Component {

	/**
	 * Local Variables
	 */
	protected $page_id = null;

	/**
	 * Auto_Posting instance
	 *
	 * @var Auto_Posting
	 */
	protected $auto_posting = null;

	/*
	 * Construct
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Initialize Auto_Posting early so form handler is registered before admin_post fires
		$this->auto_posting = new Auto_Posting();
	}

	/*
	 * Settings
	 *
	 * Create our settings page
	 */
	public function add_settings_page() {
		global $submenu;

		// Add main menu
		add_menu_page( 'lwtv-plugin', 'LezWatch.TV', 'read', 'lwtv', array( $this, 'settings_page' ), lwtv_plugin()->get_icon_svg(), 2 );

		add_submenu_page( 'lwtv', 'Welcome', 'Welcome', 'read', 'lwtv', array( $this, 'settings_page' ) );

		//phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$submenu['lwtv'][] = array( 'Monitors', 'read', esc_url( 'https://uptime.ipstenu.com/status/lwtv-admin' ) );

		//phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$submenu['lwtv'][] = array( 'Status', 'read', esc_url( 'https://status.lezwatchtv.com/' ) );

		( new Validation() )->init();

		// Only admins can access this part:
		if ( current_user_can( 'activate_plugins' ) ) {
			( new Exclusions() )->init();

			add_submenu_page( 'lwtv', 'Auto-Posting', 'Auto-Posting', 'activate_plugins', 'lwtv_auto_posting', array( $this, 'auto_posting_page' ) );

			//phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$submenu['lwtv'][] = array( 'Scheduled Actions', 'read', admin_url( 'tools.php?page=action-scheduler' ) );
		}

		//phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$submenu['lwtv'][] = array( 'Documentation', 'read', esc_url( 'https://docs.lezwatchtv.com/' ) );

		//phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$submenu['lwtv'][] = array( 'Slack', 'read', esc_url( 'https://lezwatchtv.slack.com/' ) );
	}

	/*
	 * Settings Page Content
	 */
	public function settings_page() {
		?>
		<div class="wrap">

			<h1>Welcome to Editing LezWatch.TV</h1>

			<div class="lwtv-tools-container">

				<h3>Welcome!</h3>

				<p>If you're reading this page, it's because you're here to help make the LWTV world a little better. We love you for it.</p>

				<p>There are some links to the side for handy-dandy tools that may help you along your way to better understanding everything that goes in to making LezWatch.TV so shucky-darn awesome.</p>

				<p>As always, if you have any questions, give us a shout in the <code>#editors</code> channel in Slack! Remember, there are no bad questions, just bad documentation.</p>

				<ul>
				<?php
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=lwtv_data_check' ) ) . '">Data Validation</a></li>';

				echo '<li><a href="https://status.lezwatch.tv/" target="_blank">Monitor Status</a></li>';

				// Only admins can access this part:
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=lwtv_exclusion_check' ) ) . '">Exclusion Checker</a></li>';
				}
				?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue Scripts
	 *
	 * @param  string $hook Page we're on.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		// Load only on /admin.php?page=lwtv_data_check
		$my_hooks = array( 'toplevel_page_lwtv', 'lezwatch-tv_page_lwtv_data_check', 'lezwatch-tv_page_lwtv_monitor_check', 'lezwatch-tv_page_lwtv_exclusion_check' );
		if ( in_array( $hook, $my_hooks, true ) ) {
				wp_enqueue_style( 'lwtv_data_check_admin', LWTV_PLUGIN_URL . '/assets/css/lwtv-tools.css', array(), '1.0.0' );
		}
	}

	/**
	 * Auto-Posting Page
	 *
	 * @return void
	 */
	public function auto_posting_page(): void {
		if ( null !== $this->auto_posting ) {
			$this->auto_posting->render_page();
		}
	}
}
