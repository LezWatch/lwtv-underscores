<?php
/*
 * LezWatch.TV Admin Menu
 *
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Auto_Posting;
use LWTV\Admin_Menu\Debugging;
use LWTV\Admin_Menu\Exclusions;
use LWTV\Admin_Menu\Validation;
use LWTV\Debugger\Repair;
use LWTV\Validator\Watch_Providers;
use LWTV\Validator\Watch_Term_Check;

class Admin_Menu implements Component {

	/**
	 * Auto_Posting instance
	 *
	 * @var Auto_Posting
	 */
	protected $auto_posting = null;

	/**
	 * Debugging instance
	 *
	 * @var Debugging
	 */
	protected $debugging = null;

	/*
	 * Construct
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		( new Auto_Posting() )->init();
		( new Debugging() )->init();

		// Registered here, not in Validation::init(), because that runs on
		// `admin_menu` and admin-post.php never fires it. That is half the
		// reason the old data-check admin_post hook never worked.
		( new Watch_Providers() )->init();
		( new Watch_Term_Check() )->init();
		( new Repair() )->init();
	}

	/*
	 * Settings
	 *
	 * Create our settings page
	 */
	public function add_settings_page() {
		global $submenu;

		// Add main menu
		add_menu_page( 'lwtv-plugin', 'LezWatch.TV', 'read', 'lwtv', array( $this, 'settings_page' ), lwtv_plugin()->get_icon_svg( true ), 2 );

		add_submenu_page( 'lwtv', 'Welcome', 'Welcome', 'read', 'lwtv', array( $this, 'settings_page' ) );

		( new Validation() )->init();

		// Only admins can access this part:
		if ( current_user_can( 'activate_plugins' ) ) {
			//phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$submenu['lwtv'][] = array( 'Scheduled Actions', 'read', admin_url( 'tools.php?page=action-scheduler' ) );

			( new Exclusions() )->init();
		}
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

				<table class="widefat striped">
					<thead>
						<tr><th colspan="2">Documentation and Support</th></tr>
					</thead>
					<tbody>
						<tr>
							<td><a href="https://docs.lezwatchtv.com/" target="_blank">Documentation</a></td>
							<td>Site specific documentation including how to use the tools, editing guides, and more.</td>
						</tr>
						<tr>
							<td><a href="https://slack.lezwatchtv.com/" target="_blank">Slack</a></td>
							<td>Our Slack workspace. This is where you can get help from the team and other editors.</td>
						</tr>
						<tr>
							<td><a href="https://status.ipstenu.com/status/lwtv-admin" target="_blank">Admin Monitors</a></td>
							<td>These are used to check the status of the services outside of the site (TVMaze etc).</td>
						</tr>
						<tr>
							<td><a href="https://status.lezwatchtv.com/" target="_blank">Public Monitors</a></td>
							<td>These are used to check the status of the site itself. It runs on GitHub actions and is monitored by our Admin Monitors.</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2" class="footer">
								<p>If you have any questions, give us a shout in the <code>#editors</code> channel in Slack! Remember, there are no bad questions, just bad documentation.</p>
							</td>
						</tr>
					</tfoot>
				</table>

				<p>&nbsp;</p>
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
		$my_hooks = array( 'toplevel_page_lwtv', 'lezwatch-tv_page_lwtv-auto-posting', 'lezwatch-tv_page_lwtv_data_check', 'lezwatch-tv_page_lwtv_monitor_check', 'lezwatch-tv_page_lwtv_exclusion_check' );
		if ( in_array( $hook, $my_hooks, true ) ) {
				wp_enqueue_style( 'lwtv_data_check_admin', LWTV_PLUGIN_URL . '/assets/css/lwtv-tools.css', array(), '1.2.0' );
		}
	}
}
