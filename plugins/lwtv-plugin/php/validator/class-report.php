<?php
/*
 * One renderer for every findings report on the Data Validation screen.
 *
 * This replaced ten near-identical classes of ~105 lines each, which differed
 * only in a findings key, a scanner method, a nonce name and three strings.
 * They had already drifted: the on-air view's table header said "Duplicate", its
 * translator comment said "number of dupes", and its sentence read "The
 * following miss-matched on-air checks been found". Copy-paste is how that
 * happens, and a config array is how it stops.
 *
 * Everything a report needs now lives in Admin_Menu\Validation::TOOL_TABS, which
 * means a check cannot have a tab without a scanner, or a scanner without copy —
 * the two failure modes recorded as 1.2 and 1.6.
 *
 * Watch Providers and Watch Term Check are deliberately not here: they render
 * term-shaped findings and run real work behind admin-post handlers, so they
 * keep their own classes.
 */

namespace LWTV\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Validation;
use LWTV\Debugger\Findings_Store;

class Report {

	/**
	 * Capability required to view a report or ask for a re-scan.
	 *
	 * The same cap the submenu page is registered with. Checked again here
	 * because make() is a public static: the menu registration guards the route,
	 * not the method, and a scan walks every post in a CPT.
	 */
	const CAPABILITY = 'upload_files';

	/**
	 * Render one report.
	 *
	 * @param  string $tab    Tab slug, without the `tab_` prefix.
	 * @param  array  $config That tab's entry from Validation::TOOL_TABS.
	 * @return void
	 */
	public static function make( string $tab, array $config ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to view this report.', 'lwtv' ) . '</p>';
			return;
		}

		$nonce = 'run_' . $tab . '_clicked';
		$items = self::items( $config, $nonce );

		/*
		 * `false` means the findings were missing and the scan has just run, so
		 * by this point $items is always an array. The old templates each carried
		 * an `elseif ( false === $items )` "Bogus!" branch after an `empty()`
		 * check that had already caught false -- ten copies of unreachable code.
		 */
		if ( empty( $items ) ) {
			self::render_clean( $config );
			$button = 'Run Scan';
			$name   = 'rerun';
		} else {
			self::render_problems( $config, $items );
			$button = 'Recheck';
			$name   = 'recheck';
		}

		self::render_form( $tab, $nonce, $button, $name );
	}

	/**
	 * The findings to show: cached, re-scanned, or re-checked.
	 *
	 * @param  array  $config Tab config.
	 * @param  string $nonce  Nonce action for this tab.
	 * @return array
	 */
	private static function items( array $config, string $nonce ): array {
		$items   = Findings_Store::load( $config['findings'] );
		$scanner = $config['scanner'];

		// Rerun: check everything. Also the path when there is no cache at all.
		if ( ( isset( $_POST['rerun'] ) && check_admin_referer( $nonce ) ) || false === $items ) {
			$items = self::scan( $scanner );
		}

		// Recheck: only revisit what was already flagged.
		if ( isset( $_POST['recheck'] ) && check_admin_referer( $nonce ) && ! empty( $items ) ) {
			$items = self::scan( $scanner, $items );
		}

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Call a scanner.
	 *
	 * @param  array $scanner array( class, method ).
	 * @param  array $items   Findings to re-check, or empty for a full scan.
	 * @return mixed
	 */
	private static function scan( array $scanner, array $items = array() ) {
		list( $class, $method ) = $scanner;

		return ( new $class() )->$method( $items );
	}

	/**
	 * Nothing to report.
	 *
	 * @param  array $config Tab config.
	 * @return void
	 */
	private static function render_clean( array $config ): void {
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Excellent!', 'lwtv' ); ?></h3>
			<div id="lwtv-tools-alerts">
				<p><?php echo esc_html( $config['clean'] ); ?></p>
				<?php echo wp_kses_post( ( new Validation() )->last_run( $config['option'] ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The findings table, with its heading and copy.
	 *
	 * @param  array $config Tab config.
	 * @param  array $items  Findings.
	 * @return void
	 */
	private static function render_problems( array $config, array $items ): void {
		$count = count( $items );

		/*
		 * Complete sentences per plural form rather than a fragment spliced into
		 * a shared tail. The old version built "The following " . _n( 'show
		 * needs', 'shows need' ) . " your attention.", which reads fine in
		 * English and is close to untranslatable -- and is how the on-air view
		 * ended up ungrammatical.
		 */
		$sentence = _n( $config['dirty'][0], $config['dirty'][1], $count, 'lwtv' );
		?>
		<div class="lwtv-tools-container lwtv-tools-container__alert">
			<h3>
				<span class="dashicons dashicons-warning"></span>
				<?php
				printf(
					/* translators: %d: number of items needing attention. */
					esc_html__( 'Problems (%d)', 'lwtv' ),
					(int) $count
				);
				?>
			</h3>
			<div id="lwtv-tools-alerts">
				<p><?php echo esc_html( $sentence ); ?></p>
				<?php if ( ! empty( $config['note'] ) ) : ?>
					<p><?php echo wp_kses_post( $config['note'] ); ?></p>
				<?php endif; ?>
				<?php echo wp_kses_post( ( new Validation() )->last_run( $config['option'] ) ); ?>
			</div>
		</div>

		<div class="lwtv-tools-table">
			<table class="widefat fixed" cellspacing="0">
				<thead><tr>
					<th class="manage-column column-character" scope="col"><?php echo esc_html( $config['column'] ); ?></th>
					<th class="manage-column column-problem" scope="col"><?php esc_html_e( 'Problem', 'lwtv' ); ?></th>
					<th class="manage-column column-date" scope="col"><?php esc_html_e( 'Last Updated', 'lwtv' ); ?></th>
				</tr></thead>
				<tbody>
					<?php ( new Validation() )->table_content( $items ); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * The rerun / recheck button.
	 *
	 * @param  string $tab    Tab slug.
	 * @param  string $nonce  Nonce action.
	 * @param  string $button Button label.
	 * @param  string $name   Field name, 'rerun' or 'recheck'.
	 * @return void
	 */
	private static function render_form( string $tab, string $nonce, string $button, string $name ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin.php?page=lwtv_data_check&tab=tab_' . $tab ) ); ?>" method="post">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" value="true" name="<?php echo esc_attr( $name ); ?>" />
			<?php submit_button( $button ); ?>
		</form>
		<?php
	}
}
