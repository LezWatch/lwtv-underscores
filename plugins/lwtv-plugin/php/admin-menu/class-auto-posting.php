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
	 * CMB2 option key - all settings stored under this single option
	 */
	public const OPTION_KEY = 'lwtv_auto_posting_options';

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
	 * Hook in and register a metabox to handle a theme options page and adds a menu item.
	 */
	public function lwtv_register_main_options_metabox() {
		/**
		 * Registers main options page menu item and form.
		 * CMB2 will automatically create the submenu page under 'lwtv'.
		 */
		$main_options = new_cmb2_box(
			array(
				'id'           => 'lwtv_auto_posting_options_page',
				'title'        => esc_html__( 'Auto-Posting Options', 'lwtv-underscores' ),
				'object_types' => array( 'options-page' ),
				'option_key'   => 'lwtv_auto_posting_options',
				'parent_slug'  => 'lwtv',
				'menu_title'   => esc_html__( 'Auto-Posting', 'lwtv-underscores' ),
				'capability'   => 'activate_plugins',
				'show_names'   => true,
			)
		);

		/**
		 * Options fields ids only need
		 * to be unique within this box.
		 * Prefix is not needed.
		 */
		$main_options->add_field(
			array(
				'name' => esc_html__( 'API Key', 'lwtv-underscores' ),
				'desc' => esc_html__( 'Enter your Postiz API key.', 'lwtv-underscores' ),
				'id'   => 'lwtv_postiz_api_key',
				'type' => 'text',
			)
		);

		$main_options->add_field(
			array(
				'name' => esc_html__( 'API URL', 'lwtv-underscores' ),
				'desc' => esc_html__( 'Enter your Postiz API URL.', 'lwtv-underscores' ),
				'id'   => 'lwtv_postiz_api_url',
				'type' => 'text_url',
			)
		);

		$main_options->add_field(
			array(
				'name'    => esc_html__( 'Post Type', 'lwtv-underscores' ),
				'desc'    => esc_html__( 'Posts are sent to Postiz five minutes after the post triggers (otd, new posts, new shows).', 'lwtv-underscores' ),
				'id'      => 'lwtv_postiz_post_type',
				'type'    => 'select',
				'default' => 'schedule',
				'options' => self::AVAILABLE_POST_TYPES,
			)
		);

		$main_options->add_field(
			array(
				'name'    => esc_html__( 'Triggers', 'lwtv-underscores' ),
				'desc'    => esc_html__( 'Select which events should trigger automatic posts.', 'lwtv-underscores' ),
				'id'      => 'lwtv_postiz_triggers',
				'type'    => 'multicheck',
				'options' => self::AVAILABLE_TRIGGERS,
			)
		);

		$main_options->add_field(
			array(
				'name'       => esc_html__( 'Channels', 'lwtv-underscores' ),
				'desc'       => esc_html__( 'Add the channels you want to post to. Get the Channel ID from your Postiz dashboard.', 'lwtv-underscores' ),
				'id'         => 'lwtv_postiz_channels',
				'type'       => 'group',
				'repeatable' => true,
				'options'    => array(
					'group_title'   => esc_html__( 'Channel {#}', 'lwtv-underscores' ),
					'add_button'    => esc_html__( 'Add Channel', 'lwtv-underscores' ),
					'remove_button' => esc_html__( 'Remove Channel', 'lwtv-underscores' ),
					'sortable'      => true,
					'closed'        => false,
				),
				'fields'     => array(
					array(
						'name' => esc_html__( 'Channel Name', 'lwtv-underscores' ),
						'id'   => 'name',
						'type' => 'text',
					),
					array(
						'name' => esc_html__( 'Channel ID', 'lwtv-underscores' ),
						'id'   => 'channel_id',
						'type' => 'text',
					),
					array(
						'name' => esc_html__( 'Active', 'lwtv-underscores' ),
						'id'   => 'active',
						'type' => 'checkbox',
					),
				),
			)
		);
	}
}
