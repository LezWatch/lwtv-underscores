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
		'ai-agents',
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
	 * CMB2 option key - all settings stored under this single option.
	 */
	public const OPTION_KEY = 'lwtv_debug_logging_options';

	/**
	 * Constructor
	 */
	public function __construct() {
		// CMB2 handles form submission automatically
	}

	/**
	 * Initialize the settings page
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'cmb2_admin_init', array( $this, 'lwtv_register_main_options_metabox' ) );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode_enabled(): bool {
		// WP_DEBUG takes precedence
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		$options = get_option( self::OPTION_KEY, array() );
		return ! empty( $options['debug_mode'] );
	}

	/**
	 * Get the enabled log topics.
	 *
	 * @return array
	 */
	public function get_enabled_topics(): array {
		$options = get_option( self::OPTION_KEY, array() );
		$topics  = isset( $options['log_topics'] ) ? $options['log_topics'] : array();

		// If no topics selected, return all valid topics
		if ( empty( $topics ) ) {
			return self::VALID_LOG_TOPICS;
		}

		return array_intersect( $topics, self::VALID_LOG_TOPICS );
	}

	/**
	 * Build description text with dynamic status info.
	 *
	 * @return string
	 */
	private function get_debug_mode_description(): string {
		$log_file   = WP_CONTENT_DIR . '/debug-lwtv.log';
		$log_exists = file_exists( $log_file );

		$description = esc_html__( 'Enable debug logging for LWTV plugin.', 'lwtv-underscores' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$description .= '<br><strong>' . esc_html__( 'Note: WP_DEBUG is enabled in wp-config.php, so debug mode is always on.', 'lwtv-underscores' ) . '</strong>';
		}

		$description .= '<br><br>';

		if ( $log_exists ) {
			$description .= sprintf(
				/* translators: 1: log file path, 2: file size */
				esc_html__( 'Log file: %1$s (%2$s)', 'lwtv-underscores' ),
				'<code>' . esc_html( $log_file ) . '</code>',
				esc_html( size_format( filesize( $log_file ) ) )
			);
		} else {
			$description .= sprintf(
				/* translators: %s: log file path */
				esc_html__( 'Log file will be created at: %s', 'lwtv-underscores' ),
				'<code>' . esc_html( $log_file ) . '</code>'
			);
		}

		return $description;
	}

	/**
	 * Build options array for log topics multicheck field.
	 *
	 * @return array
	 */
	private function get_log_topics_options(): array {
		$options = array();

		foreach ( self::VALID_LOG_TOPICS as $topic ) {
			$options[ $topic ] = ucwords( str_replace( '-', ' ', $topic ) );
		}

		return $options;
	}

	/**
	 * Register the CMB2 options page and fields.
	 *
	 * @return void
	 */
	public function lwtv_register_main_options_metabox(): void {
		$main_options = new_cmb2_box(
			array(
				'id'           => 'lwtv_debug_logging_options_page',
				'title'        => esc_html__( 'Debug Logging', 'lwtv-underscores' ),
				'object_types' => array( 'options-page' ),
				'option_key'   => self::OPTION_KEY,
				'parent_slug'  => 'lwtv',
				'menu_title'   => esc_html__( 'Debug Logging', 'lwtv-underscores' ),
				'capability'   => 'activate_plugins',
				'show_names'   => true,
			)
		);

		$main_options->add_field(
			array(
				'name' => esc_html__( 'Debug Mode', 'lwtv-underscores' ),
				'desc' => $this->get_debug_mode_description(),
				'id'   => 'debug_mode',
				'type' => 'checkbox',
			)
		);

		$main_options->add_field(
			array(
				'name'    => esc_html__( 'Log Topics', 'lwtv-underscores' ),
				'desc'    => esc_html__( 'Select which topics to log. If no topics are selected, all topics will be logged.', 'lwtv-underscores' ),
				'id'      => 'log_topics',
				'type'    => 'multicheck',
				'options' => $this->get_log_topics_options(),
			)
		);
	}
}
