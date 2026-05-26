<?php
/*
 * Validation: Check on-air status of shows
 *
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Validation;
use LWTV\Debugger\OnAir as OnAir_Debugger;

class OnAir_Checker {
	/**
	 * Output the results of on air checking...
	 */
	public static function make() {
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_on_air_problems' );

		// If rerun was clicked, gotta check 'em all.
		if ( ( isset( $_POST['rerun'] ) && check_admin_referer( 'run_onair_checker_clicked' ) ) || false === $items ) {
			$items = ( new OnAir_Debugger() )->find_on_air_problems();
		}

		// If recheck was clicked, only check the problem children.
		if ( isset( $_POST['recheck'] ) && check_admin_referer( 'run_onair_checker_clicked' ) && false !== $items ) {
			$items = ( new OnAir_Debugger() )->find_on_air_problems( $items );
		}

		// Get the last run time.
		$last_run = ( new Validation() )->last_run( 'onair_problems' );

		// Default.
		$button  = 'Run Scan';
		$is_name = 'rerun';

		if ( empty( $items ) || ! is_array( $items ) ) {
			?>
			<div class="lwtv-tools-container lwtv-tools-container__alert">
				<h3><span class="dashicons dashicons-yes"></span> Excellent!</h3>
				<div id="lwtv-tools-alerts">
					<p>All shows have the correct on-air status.</p>
					<?php echo wp_kses_post( $last_run ); ?>
				</div>
			</div>
			<?php
		} elseif ( false === $items ) {
			$button  = 'Full Scan';
			$is_name = 'rerun';
			?>
			<div class="lwtv-tools-container lwtv-tools-container__alert">
				<h3><span class="dashicons dashicons-dissmiss"></span> Bogus!</h3>
				<div id="lwtv-tools-alerts">
					<p>Something has gone wrong. Please run a full scan. If this repeats, let Mika know.</p>
					<?php echo wp_kses_post( $last_run ); ?>
				</div>
			</div>
			<?php
		}

		?>
		<form action="admin.php?page=lwtv_data_check&tab=tab_onair_checker" method="post">
			<?php wp_nonce_field( 'run_onair_checker_clicked' ); ?>
			<input type="hidden" value="true" name=<?php echo esc_attr( $is_name ); ?> />
			<?php submit_button( $button ); ?>
		</form>
		<?php
	}
}
