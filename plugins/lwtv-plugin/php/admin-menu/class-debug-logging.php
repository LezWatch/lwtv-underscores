<?php
/*
 * Debug Logging Settings Page
 *
 */

namespace LWTV\Admin_Menu;

class Debug_Logging {

	const VALID_LOG_TOPICS = array(
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

	private const OPTION_LOG_TOPICS = 'lwtv_debug_logging_topics';

	/**
	 * Constructor - register form handler immediately
	 */
	public function __construct() {
		// Register form handler early - admin_post fires before admin_menu
		add_action( 'admin_post_lwtv_debug_logging_save', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Initialize the settings page
	 *
	 * @return void
	 */
	public function init(): void {
		// Additional initialization if needed in the future
	}

	/**
	 * Render the page
	 */
	public function render_page(): void {
		$log_topics = get_option( self::OPTION_LOG_TOPICS, array() );
		?>
		<div class="wrap">
			<h1>Debug Logging</h1>

			<!-- Controller for toggling on debug mode -->
			<form method="post" action="">
				<input type="hidden" name="action" value="toggle_debug_mode">
				<input type="submit" name="submit" value="Toggle Debug Mode">
				<?php wp_nonce_field( 'lwtv_debug_logging_save', 'lwtv_debug_logging_nonce' ); ?>
			</form>

			<?php
			if ( empty( $log_topics ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<p>No log topics are enabled.</p>';
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->render_log_topics( $log_topics );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the log topics
	 *
	 * @param array $log_topics The log topics
	 * @return void
	 */
	public function render_log_topics( $log_topics ): void {
		?>
		<!-- List of log topics -->
		<h2>Log Topics</h2>
		<form method="post" action="">
			<input type="hidden" name="action" value="toggle_log_topics">
			<ul>
				<?php foreach ( self::VALID_LOG_TOPICS as $topic ) { ?>
					<li>
						<input type="checkbox" name="log_topics[]" value="<?php echo esc_attr( $topic ); ?>" <?php checked( in_array( $topic, $log_topics, true ) ); ?>>
						<?php echo esc_html( $topic ); ?>
					</li>
				<?php } ?>
			</ul>
			<?php wp_nonce_field( 'lwtv_debug_logging_save', 'lwtv_debug_logging_nonce' ); ?>
		</form>
		<?php
	}

	/**
	 * Handle form submission
	 */
	public function handle_form_submission(): void {
		// Verify nonce
		if ( ! isset( $_POST['lwtv_debug_logging_nonce'] ) || ! wp_verify_nonce( $_POST['lwtv_debug_logging_nonce'], 'lwtv_debug_logging_save' ) ) {
			wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
		}

		// Check capability
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'You do not have permission to access this page.', 'Error', array( 'response' => 403 ) );
		}

		// Sanitize and save log topics
		$log_topics = isset( $_POST['log_topics'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['log_topics'] ) ) : array();
		update_option( self::OPTION_LOG_TOPICS, $log_topics );
	}
}
