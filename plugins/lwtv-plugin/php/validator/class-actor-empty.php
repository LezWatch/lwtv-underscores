<?php
/*
 * Validation: Incomplete Actors For LezWatch.TV
 *
 * Actors with no photo or no biography. The scan has existed since the debugger
 * did, but had no tab, no CLI entry and no cron slot -- its only trace was a
 * count on the intro page with nothing to click. See DEBUGGER-REVIEW.md 1.7.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Validation;

use LWTV\Debugger\Actors as Actors_Debugger;

class Actor_Empty {
	/**
	 * Output the results of the incomplete actor check.
	 */
	public static function make() {

		$items = lwtv_plugin()->get_transient( Actors_Debugger::TRANSIENT_EMPTY );

		// If rerun was clicked, gotta check 'em all.
		if ( ( isset( $_POST['rerun'] ) && check_admin_referer( 'run_actor_empty_clicked' ) ) || false === $items ) {
			$items = ( new Actors_Debugger() )->find_actors_incomplete();
		}

		// If recheck was clicked, only check the problem children.
		if ( isset( $_POST['recheck'] ) && check_admin_referer( 'run_actor_empty_clicked' ) && false !== $items ) {
			$items = ( new Actors_Debugger() )->find_actors_incomplete( $items );
		}

		// Get the last run time.
		$last_run = ( new Validation() )->last_run( 'actor_empty' );

		// Default.
		$button  = 'Run Scan';
		$is_name = 'rerun';

		if ( empty( $items ) || ! is_array( $items ) ) {
			?>
			<div class="lwtv-tools-container lwtv-tools-container__alert">
				<h3><span class="dashicons dashicons-yes"></span> Excellent!</h3>
				<div id="lwtv-tools-alerts">
					<p>Every actor has a photo and a biography.</p>
					<?php echo wp_kses_post( $last_run ); ?>
				</div>
			</div>
			<?php
		} else {
			$button  = 'Recheck';
			$is_name = 'recheck';

			$count = count( $items );
			// translators: %s is the number of actors.
			$have = _n( 'actor is', 'actors are', $count );
			?>
			<div class="lwtv-tools-container lwtv-tools-container__alert">
				<h3><span class="dashicons dashicons-warning"></span> Problems (<?php echo (int) $count; ?>)</h3>
				<div id="lwtv-tools-alerts">
					<p>The following <?php echo esc_html( $have ); ?> missing a photo, a biography, or both.</p>
					<?php echo wp_kses_post( $last_run ); ?>
				</div>
			</div>

			<div class="lwtv-tools-table">
				<table class="widefat fixed" cellspacing="0">
					<thead><tr>
						<th id="character" class="manage-column column-character" scope="col">Actor</th>
						<th id="problem" class="manage-column column-problem" scope="col">Problem</th>
						<th id="date" class="manage-column column-date" scope="col">Last Updated</th>
					</tr></thead>

					<tbody>
						<?php
						( new Validation() )->table_content( $items );
						?>
					</tbody>
				</table>
			</div>
			<?php
		}

		?>
		<form action="admin.php?page=lwtv_data_check&tab=tab_actor_empty" method="post">
			<?php wp_nonce_field( 'run_actor_empty_clicked' ); ?>
			<input type="hidden" value="true" name=<?php echo esc_attr( $is_name ); ?> />
			<?php submit_button( $button ); ?>
		</form>
		<?php
	}
}
