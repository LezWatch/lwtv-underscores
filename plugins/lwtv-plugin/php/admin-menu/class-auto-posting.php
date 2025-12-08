<?php
/**
 * Auto-Posting Settings Page
 *
 * Manages settings for automated posting to social media via Postiz.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Admin_Menu;

class Auto_Posting {

	/**
	 * Option names
	 */
	private const OPTION_API_KEY   = 'lwtv_postiz_api_key';
	private const OPTION_API_URL   = 'lwtv_postiz_api_url';
	private const OPTION_CHANNELS  = 'lwtv_postiz_channels';
	private const OPTION_TRIGGERS  = 'lwtv_postiz_triggers';
	private const OPTION_POST_TYPE = 'lwtv_postiz_post_type';

	/**
	 * Available post types
	 */
	private const AVAILABLE_POST_TYPES = array(
		'draft'    => 'Draft',
		'schedule' => 'Schedule',
		'now'      => 'Immediately',
	);

	/**
	 * Available triggers
	 */
	private const AVAILABLE_TRIGGERS = array(
		'new_posts'  => 'New Posts',
		'new_shows'  => 'New Shows',
		'of_the_day' => 'Of The Day',
	);

	/**
	 * Constructor - register form handler immediately
	 */
	public function __construct() {
		// Register form handler early - admin_post fires before admin_menu
		add_action( 'admin_post_lwtv_auto_posting_save', array( $this, 'handle_form_submission' ) );
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
	 * Handle form submission
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		// Verify nonce
		if ( ! isset( $_POST['lwtv_auto_posting_nonce'] ) || ! wp_verify_nonce( $_POST['lwtv_auto_posting_nonce'], 'lwtv_auto_posting_save' ) ) {
			wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
		}

		// Check capability
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'You do not have permission to access this page.', 'Error', array( 'response' => 403 ) );
		}

		// Sanitize and save API key
		$api_key = isset( $_POST['lwtv_postiz_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['lwtv_postiz_api_key'] ) ) : '';
		update_option( self::OPTION_API_KEY, $api_key );

		// Sanitize and save API URL
		$api_url = isset( $_POST['lwtv_postiz_api_url'] ) ? esc_url_raw( $_POST['lwtv_postiz_api_url'] ) : '';
		update_option( self::OPTION_API_URL, $api_url );

		// Sanitize and save channels
		$channels = array();
		if ( isset( $_POST['lwtv_postiz_channels'] ) && is_array( $_POST['lwtv_postiz_channels'] ) ) {
			foreach ( $_POST['lwtv_postiz_channels'] as $channel ) {
				$name       = isset( $channel['name'] ) ? sanitize_text_field( wp_unslash( $channel['name'] ) ) : '';
				$channel_id = isset( $channel['channel_id'] ) ? sanitize_text_field( wp_unslash( $channel['channel_id'] ) ) : '';
				$active     = isset( $channel['active'] ) && '1' === $channel['active'];

				// Only add if both fields have values
				if ( ! empty( $name ) && ! empty( $channel_id ) ) {
					$channels[] = array(
						'name'       => $name,
						'channel_id' => $channel_id,
						'active'     => $active,
					);
				}
			}
		}
		update_option( self::OPTION_CHANNELS, $channels );

		// Sanitize and save triggers
		$triggers = array();
		if ( isset( $_POST['lwtv_postiz_triggers'] ) && is_array( $_POST['lwtv_postiz_triggers'] ) ) {
			foreach ( $_POST['lwtv_postiz_triggers'] as $trigger ) {
				$trigger = sanitize_key( $trigger );
				if ( array_key_exists( $trigger, self::AVAILABLE_TRIGGERS ) ) {
					$triggers[] = $trigger;
				}
			}
		}
		update_option( self::OPTION_TRIGGERS, $triggers );

		// Sanitize and save post type
		$post_type = isset( $_POST['lwtv_postiz_post_type'] ) ? sanitize_key( $_POST['lwtv_postiz_post_type'] ) : 'schedule';
		if ( ! array_key_exists( $post_type, self::AVAILABLE_POST_TYPES ) ) {
			$post_type = 'schedule';
		}
		update_option( self::OPTION_POST_TYPE, $post_type );

		// Redirect back with success message
		wp_safe_redirect( add_query_arg( 'message', 'saved', admin_url( 'admin.php?page=lwtv_auto_posting' ) ) );
		exit;
	}

	/**
	 * Render the settings page
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Get current values
		$api_key   = get_option( self::OPTION_API_KEY, '' );
		$api_url   = defined( 'POSTIZ_API_URL' ) ? POSTIZ_API_URL : get_option( self::OPTION_API_URL, '' );
		$channels  = get_option( self::OPTION_CHANNELS, array() );
		$triggers  = get_option( self::OPTION_TRIGGERS, array() );
		$post_type = get_option( self::OPTION_POST_TYPE, 'schedule' );

		// Ensure channels is an array
		if ( ! is_array( $channels ) ) {
			$channels = array();
		}

		// Show admin notice if saved
		if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully.', 'lwtv-underscores' ); ?></p>
			</div>
			<?php
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto-Posting Settings', 'lwtv-underscores' ); ?></h1>

			<p>Configure automated posting to social media platforms via <a href="https://postiz.com/" target="_blank">Postiz</a>.</p>
			<p>We <a href="https://postiz.ipstenu.com/" target="_blank">self-host Postiz</a>, and access to Postiz is limited to site admins. If you are an admin, ask Mika.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'lwtv_auto_posting_save', 'lwtv_auto_posting_nonce' ); ?>
				<input type="hidden" name="action" value="lwtv_auto_posting_save">

				<table class="form-table" role="presentation">
					<tbody>
						<!-- API Key -->
						<tr>
							<th scope="row">
								<label for="lwtv_postiz_api_key"><?php esc_html_e( 'API Key', 'lwtv-underscores' ); ?></label>
							</th>
							<td>
								<input type="password" name="lwtv_postiz_api_key" id="lwtv_postiz_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
								<p class="description"><?php esc_html_e( 'Enter your Postiz API key.', 'lwtv-underscores' ); ?></p>
							</td>
						</tr>

						<!-- API URL -->
						<tr>
							<th scope="row">
								<label for="lwtv_postiz_api_url"><?php esc_html_e( 'API URL', 'lwtv-underscores' ); ?></label>
							</th>
							<td>
								<input type="text" name="lwtv_postiz_api_url" id="lwtv_postiz_api_url" value="<?php echo esc_url( $api_url ); ?>" class="regular-text" autocomplete="off">
							</td>
						</tr>

						<!-- Post Type for Postiz: Should be a dropdown for schedule, draft, publish. -->
						<tr>
							<th scope="row">
								<label for="lwtv_postiz_post_type"><?php esc_html_e( 'Postiz Post Type', 'lwtv-underscores' ); ?></label>
							</th>
							<td>
								<select name="lwtv_postiz_post_type" id="lwtv_postiz_post_type">
									<?php foreach ( self::AVAILABLE_POST_TYPES as $type_key => $type_label ) : ?>
										<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $post_type, $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<!-- Channels (Repeatable) -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Channels', 'lwtv-underscores' ); ?></th>
							<td>
								<div id="lwtv-channels-container">
									<table class="widefat fixed striped" role="presentation">
										<thead>
											<tr>
												<th scope="col" class="column-name"><?php esc_html_e( 'Channel Name', 'lwtv-underscores' ); ?></th>
												<th scope="col" class="column-channel-id"><?php esc_html_e( 'Channel ID', 'lwtv-underscores' ); ?></th>
												<th scope="col" class="column-active"><?php esc_html_e( 'Active', 'lwtv-underscores' ); ?></th>
												<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'lwtv-underscores' ); ?></th>
											</tr>
										</thead>
										<tbody id="lwtv-channels-tbody">
											<?php
											if ( empty( $channels ) ) {
												?>
												<tr class="no-items">
													<td class="colspanchange" colspan="4"><?php esc_html_e( 'No channels added yet.', 'lwtv-underscores' ); ?></td>
												</tr>
												<?php
											} else {
												foreach ( $channels as $index => $channel ) {
													$this->render_channel_row( $index, $channel );
												}
											}
											?>
										</tbody>
									</table>
								</div>
								<br />
								<button type="button" class="button" id="lwtv-add-channel"><?php esc_html_e( 'Add Channel', 'lwtv-underscores' ); ?></button>
								<p class="description"><?php esc_html_e( 'Add the channels you want to post to. Get the Channel ID from your Postiz dashboard.', 'lwtv-underscores' ); ?></p>
							</td>
						</tr>

						<!-- Triggers (Multicheck) -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Triggers', 'lwtv-underscores' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php esc_html_e( 'Triggers', 'lwtv-underscores' ); ?></span></legend>
									<?php foreach ( self::AVAILABLE_TRIGGERS as $trigger_key => $trigger_label ) : ?>
										<label>
											<input type="checkbox" name="lwtv_postiz_triggers[]" value="<?php echo esc_attr( $trigger_key ); ?>" <?php checked( in_array( $trigger_key, $triggers, true ) ); ?>>
											<?php echo esc_html( $trigger_label ); ?>
										</label><br>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Select which events should trigger automatic posts.', 'lwtv-underscores' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'lwtv-underscores' ) ); ?>
			</form>
		</div>

		<?php $this->render_inline_scripts(); ?>
		<?php
	}

	/**
	 * Render a single channel row
	 *
	 * @param int   $index   Row index.
	 * @param array $channel Channel data (optional).
	 * @return void
	 */
	private function render_channel_row( int $index, array $channel = array() ): void {
		$name       = isset( $channel['name'] ) ? $channel['name'] : '';
		$channel_id = isset( $channel['channel_id'] ) ? $channel['channel_id'] : '';
		$active     = isset( $channel['active'] ) ? $channel['active'] : true; // Default to active for new channels
		?>
		<tr class="lwtv-channel-row">
			<td class="column-name" data-colname="<?php esc_attr_e( 'Channel Name', 'lwtv-underscores' ); ?>">
				<input type="text" name="lwtv_postiz_channels[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'e.g., Bluesky', 'lwtv-underscores' ); ?>">
			</td>
			<td class="column-channel-id" data-colname="<?php esc_attr_e( 'Channel ID', 'lwtv-underscores' ); ?>">
				<input type="password" name="lwtv_postiz_channels[<?php echo esc_attr( $index ); ?>][channel_id]" value="<?php echo esc_attr( $channel_id ); ?>" placeholder="<?php esc_attr_e( 'Channel ID from Postiz', 'lwtv-underscores' ); ?>">
			</td>
			<td class="column-active" data-colname="<?php esc_attr_e( 'Active', 'lwtv-underscores' ); ?>">
				<input type="checkbox" name="lwtv_postiz_channels[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( $active ); ?>>
			</td>
			<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'lwtv-underscores' ); ?>">
				<button type="button" class="button lwtv-remove-channel"><?php esc_html_e( 'Remove', 'lwtv-underscores' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render inline JavaScript for repeatable fields
	 *
	 * @return void
	 */
	private function render_inline_scripts(): void {
		?>
		<script type="text/javascript">
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				var tbody = document.getElementById('lwtv-channels-tbody');
				var addButton = document.getElementById('lwtv-add-channel');

				if (!tbody || !addButton) {
					return;
				}

				// Add new channel row
				addButton.addEventListener('click', function() {
					// Remove "no items" row if present
					var noItems = tbody.querySelector('.no-items');
					if (noItems) {
						noItems.remove();
					}

					var rows = tbody.querySelectorAll('.lwtv-channel-row');
					var newIndex = rows.length;

					var newRow = document.createElement('tr');
					newRow.className = 'lwtv-channel-row';
					newRow.innerHTML = '<td class="column-name" data-colname="<?php echo esc_attr( __( 'Channel Name', 'lwtv-underscores' ) ); ?>">' +
						'<input type="text" name="lwtv_postiz_channels[' + newIndex + '][name]" value="" placeholder="<?php echo esc_attr( __( 'e.g., Bluesky', 'lwtv-underscores' ) ); ?>">' +
						'</td>' +
						'<td class="column-channel-id" data-colname="<?php echo esc_attr( __( 'Channel ID', 'lwtv-underscores' ) ); ?>">' +
						'<input type="password" name="lwtv_postiz_channels[' + newIndex + '][channel_id]" value="" placeholder="<?php echo esc_attr( __( 'Channel ID from Postiz', 'lwtv-underscores' ) ); ?>">' +
						'</td>' +
						'<td class="column-active" data-colname="<?php echo esc_attr( __( 'Active', 'lwtv-underscores' ) ); ?>">' +
						'<input type="checkbox" name="lwtv_postiz_channels[' + newIndex + '][active]" value="1" checked>' +
						'</td>' +
						'<td class="column-actions" data-colname="<?php echo esc_attr( __( 'Actions', 'lwtv-underscores' ) ); ?>">' +
						'<button type="button" class="button lwtv-remove-channel"><?php echo esc_js( __( 'Remove', 'lwtv-underscores' ) ); ?></button>' +
						'</td>';

					tbody.appendChild(newRow);
					reindexRows();
				});

				// Remove channel row (event delegation)
				tbody.addEventListener('click', function(e) {
					if (e.target && e.target.classList.contains('lwtv-remove-channel')) {
						var rows = tbody.querySelectorAll('.lwtv-channel-row');
						if (rows.length > 1) {
							e.target.closest('.lwtv-channel-row').remove();
							reindexRows();
						} else {
							// Clear the fields instead of removing the last row
							var row = e.target.closest('.lwtv-channel-row');
							var inputs = row.querySelectorAll('input');
							inputs.forEach(function(input) {
								if (input.type === 'checkbox') {
									input.checked = false;
								} else {
									input.value = '';
								}
							});
						}
					}
				});

				// Reindex all rows after add/remove
				function reindexRows() {
					var rows = tbody.querySelectorAll('.lwtv-channel-row');
					rows.forEach(function(row, index) {
						var inputs = row.querySelectorAll('input');
						inputs.forEach(function(input) {
							var name = input.getAttribute('name');
							if (name) {
								input.setAttribute('name', name.replace(/\[\d+\]/, '[' + index + ']'));
							}
						});
					});
				}
			});
		})();
		</script>
		<?php
	}
}

