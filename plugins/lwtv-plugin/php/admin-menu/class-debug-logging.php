<?php
/**
 * Debug Logging Settings Page
 *
 * Provides admin UI to toggle debug mode and select which topics to log.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu;

class Debug_Logging {

	/**
	 * Valid log topics that can be enabled/disabled.
	 */
	public const VALID_LOG_TOPICS = array(
		'actors',
		'buryqueers',
		'caching',
		'characters',
		'calculations',
		'calendar',
		'death',
		'is-queer',
		'missed-schedule',
		'postiz',
		'scheduler',
		'shadow-taxonomy',
		'shows',
		'statistics',
		'taxsync',
		'this-year',
		'tmdb',
		'validator',
		'wp-cli',
	);

	/**
	 * Option name for enabled log topics.
	 */
	private const OPTION_LOG_TOPICS = 'lwtv_debug_logging_topics';

	/**
	 * Option name for debug mode toggle.
	 */
	private const OPTION_DEBUG_MODE = 'lwtv_debug_mode_enabled';

	/**
	 * Constructor - register form handler immediately.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		// Additional initialization if needed in the future
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	private function is_debug_mode_enabled(): bool {
		// WP_DEBUG takes precedence
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		return (bool) get_option( self::OPTION_DEBUG_MODE, false );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$debug_enabled  = $this->is_debug_mode_enabled();
		$wp_debug_on    = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$enabled_topics = get_option( self::OPTION_LOG_TOPICS, array() );
		$log_file       = WP_CONTENT_DIR . '/debug-lwtv.log';
		$log_exists     = file_exists( $log_file );
		?>
		<div class="wrap">
			<h1>Debug Logging</h1>

			<?php $this->render_notices(); ?>

			<!-- Debug Mode Status -->
			<div class="card" style="max-width: 800px; margin-bottom: 20px;">
				<h2 style="margin-top: 0;">Debug Mode Status</h2>

				<p>
					<strong>Current Status:</strong>
					<?php if ( $debug_enabled ) : ?>
						<span style="color: #00a32a; font-weight: bold;">✓ ENABLED</span>
					<?php else : ?>
						<span style="color: #d63638; font-weight: bold;">✗ DISABLED</span>
					<?php endif; ?>
				</p>

				<?php if ( $wp_debug_on ) : ?>
					<p class="description">
						<em>Note: WP_DEBUG is enabled in wp-config.php, so debug mode is always on.</em>
					</p>
				<?php else : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'lwtv_debug_toggle', 'lwtv_debug_nonce' ); ?>
						<input type="hidden" name="lwtv_action" value="toggle_debug_mode">
						<p>
							<button type="submit" class="button <?php echo $debug_enabled ? 'button-secondary' : 'button-primary'; ?>">
								<?php echo $debug_enabled ? 'Disable Debug Mode' : 'Enable Debug Mode'; ?>
							</button>
						</p>
					</form>
				<?php endif; ?>

				<?php if ( $log_exists ) : ?>
					<p class="description">
						Log file: <code><?php echo esc_html( $log_file ); ?></code>
						(<?php echo esc_html( size_format( filesize( $log_file ) ) ); ?>)
					</p>
				<?php else : ?>
					<p class="description">
						Log file will be created at: <code><?php echo esc_html( $log_file ); ?></code>
					</p>
				<?php endif; ?>
			</div>

			<!-- Log Topics Selection -->
			<div class="card" style="max-width: 800px;">
				<h2 style="margin-top: 0;">Log Topics</h2>

				<p class="description">
					Select which topics to log. If no topics are selected, <strong>all topics will be logged</strong>.
				</p>

				<form method="post" action="">
					<?php wp_nonce_field( 'lwtv_topics_save', 'lwtv_topics_nonce' ); ?>
					<input type="hidden" name="lwtv_action" value="save_topics">

					<fieldset style="margin: 15px 0;">
						<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
							<?php foreach ( self::VALID_LOG_TOPICS as $topic ) : ?>
								<label style="display: flex; align-items: center; gap: 6px;">
									<input
										type="checkbox"
										name="log_topics[]"
										value="<?php echo esc_attr( $topic ); ?>"
										<?php checked( in_array( $topic, $enabled_topics, true ) ); ?>
									>
									<span><?php echo esc_html( ucwords( str_replace( '-', ' ', $topic ) ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<p style="margin-top: 15px;">
						<button type="submit" class="button button-primary">Save Topics</button>
						<button type="button" class="button" id="lwtv-select-all">Select All</button>
						<button type="button" class="button" id="lwtv-select-none">Clear All</button>
					</p>
				</form>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const checkboxes = document.querySelectorAll('input[name="log_topics[]"]');

			document.getElementById('lwtv-select-all').addEventListener('click', function() {
				checkboxes.forEach(function(cb) { cb.checked = true; });
			});

			document.getElementById('lwtv-select-none').addEventListener('click', function() {
				checkboxes.forEach(function(cb) { cb.checked = false; });
			});
		});
		</script>
		<?php
	}

	/**
	 * Render admin notices for form submission feedback.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['lwtv_msg'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['lwtv_msg'] ) );

		$notices = array(
			'debug_enabled'  => array( 'success', 'Debug mode has been enabled.' ),
			'debug_disabled' => array( 'success', 'Debug mode has been disabled.' ),
			'topics_saved'   => array( 'success', 'Log topics have been saved.' ),
		);

		if ( isset( $notices[ $message ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notices[ $message ][0] ),
				esc_html( $notices[ $message ][1] )
			);
		}
	}

	/**
	 * Handle form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		// Check if this is our form submission
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['lwtv_action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_text_field( wp_unslash( $_POST['lwtv_action'] ) );

		// Check capability
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'You do not have permission to access this page.', 'Error', array( 'response' => 403 ) );
		}

		$redirect_url = admin_url( 'admin.php?page=lwtv_debug_logging' );

		switch ( $action ) {
			case 'toggle_debug_mode':
				$this->handle_toggle_debug_mode( $redirect_url );
				break;

			case 'save_topics':
				$this->handle_save_topics( $redirect_url );
				break;
		}
	}

	/**
	 * Handle debug mode toggle.
	 *
	 * @param string $redirect_url The URL to redirect to after processing.
	 *
	 * @return void
	 */
	private function handle_toggle_debug_mode( string $redirect_url ): void {
		// Verify nonce
		if ( ! isset( $_POST['lwtv_debug_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lwtv_debug_nonce'] ) ), 'lwtv_debug_toggle' ) ) {
			wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
		}

		$current = (bool) get_option( self::OPTION_DEBUG_MODE, false );
		$new_val = ! $current;

		update_option( self::OPTION_DEBUG_MODE, $new_val );

		$message = $new_val ? 'debug_enabled' : 'debug_disabled';
		wp_safe_redirect( add_query_arg( 'lwtv_msg', $message, $redirect_url ) );
		exit;
	}

	/**
	 * Handle saving log topics.
	 *
	 * @param string $redirect_url The URL to redirect to after processing.
	 *
	 * @return void
	 */
	private function handle_save_topics( string $redirect_url ): void {
		// Verify nonce
		if ( ! isset( $_POST['lwtv_topics_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lwtv_topics_nonce'] ) ), 'lwtv_topics_save' ) ) {
			wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
		}

		// Get and sanitize topics
		$submitted_topics = isset( $_POST['log_topics'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['log_topics'] ) ) : array();

		// Only keep valid topics
		$valid_topics = array_intersect( $submitted_topics, self::VALID_LOG_TOPICS );

		update_option( self::OPTION_LOG_TOPICS, $valid_topics );

		wp_safe_redirect( add_query_arg( 'lwtv_msg', 'topics_saved', $redirect_url ) );
		exit;
	}
}
