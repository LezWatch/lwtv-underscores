<?php
/*
 * Dashboard Widget
 *
 * Shows tools as a dashboard widget.
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Status;

class Dashboard_Widgets implements Component {

	/*
	 * Construct
	 */
	public function init() {
		add_action( 'wp_dashboard_setup', array( $this, 'custom_dashboard_widgets' ) );
	}

	/**
	 * Custom Dashboard Widget
	 */
	public function custom_dashboard_widgets() {
		if ( current_user_can( 'upload_files' ) ) {
			wp_add_dashboard_widget( 'lwtv_data_check_widget', 'LezWatch.TV Tools', array( $this, 'check_dashboard_content' ) );
		}
	}

	/**
	 * Check dashboard content
	 *
	 * Echos page.
	 *
	 * @return void
	 */
	public function check_dashboard_content() {
		// Get Last Status. Status::all() always hands back an array, which matters
		// on a fresh install -- unsetting an offset on `false` is a fatal on PHP 8.
		$options   = Status::all();
		$timestamp = (int) ( $options['timestamp'] ?? 0 );
		unset( $options['timestamp'] );
		?>
		<div class="main">
			<?php if ( $timestamp ) : ?>
				<p>The tools were last run on <strong><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'F j, Y H:i:s' ) ); ?></strong>.</p>
			<?php else : ?>
				<p>The tools have not been run yet.</p>
			<?php endif; ?>

			<p><strong>Current Status</strong></p>

			<ul>
				<?php
				$output = '';
				foreach ( $options as $an_option ) {
					if ( ! is_array( $an_option ) || empty( $an_option['name'] ) ) {
						continue;
					}
					$count = isset( $an_option['count'] ) ? (int) $an_option['count'] : 0;
					if ( $count > 0 ) {
						$output .= '<li>&bull; ' . esc_html( $an_option['name'] ) . ' - ' . (int) $count . '</li>';
					}
				}

				if ( empty( $output ) ) {
					$output = '<p>No issues found! Celebrate gay times!</p>';
				}

				echo wp_kses_post( $output );

				?>
			</ul>

			<a href="<?php echo esc_url_raw( admin_url( 'admin.php?page=lwtv_data_check' ) ); ?>" class="button-secondary">Go to the Tools Dashboard</a>
		</div>

		<?php
	}
}
