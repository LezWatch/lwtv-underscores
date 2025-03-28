<?php
/*
 * MonsterInsights for WordPress hooks
 *
 * Automates adding Site Notes to MonsterInsights when posts are published and plugins are activated/deactivated.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Plugins;

class MonsterInsights {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		if ( ! function_exists( 'monsterinsights_add_site_note' ) ) {
			return;
		}

		add_action( 'publish_post', array( $this, 'create_site_note_on_post_publish' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'create_site_note_on_plugin_change' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'create_site_note_on_plugin_change' ), 10, 2 );
	}

	/**
	 * Create a site note on post publish
	 *
	 * @param int $post_id The post ID.
	 * @param WP_Post $post The post object.
	 */
	public function create_site_note_on_post_publish( $post_id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// If term doesn't exist, return
		$term = get_term_by( 'slug', 'blog-post', 'monsterinsights_note_category' );
		if ( ! $term ) {
			return;
		}

		$post_title = $post->post_title;
		$is_sticky  = is_sticky( $post_id );
		$note_args  = array(
			'note'        => 'New Post: ' . sanitize_text_field( $post_title ),
			'author_id'   => $post->post_author,
			'date'        => $post->post_date,
			'category_id' => $term->term_id,
			'important'   => $is_sticky,
		);

		monsterinsights_add_site_note( $note_args );
	}

	/**
	 * Create a site note on plugin activation/deactivation
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory
	 * @param bool $network_wide Whether to enable the plugin for all sites in the network
	 */
	public function create_site_note_on_plugin_change( $plugin, $network_wide = false ) {
		// If term doesn't exist, return
		$term = get_term_by( 'slug', 'website-updates', 'monsterinsights_note_category' );
		if ( ! $term ) {
			return;
		}

		// Get plugin data
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
		$plugin_name = $plugin_data['Name'];

		// Determine if this is activation or deactivation
		$action = ( current_filter() === 'activated_plugin' ) ? 'activated' : 'deactivated';

		// Create the note message
		$note = sprintf( 'Plugin %s: %s', $action, sanitize_text_field( $plugin_name ) );

		// If it's network wide, add that information
		if ( $network_wide ) {
			$note .= ' (network wide)';
		}

		$note_args = array(
			'note'        => $note,
			'author_id'   => get_current_user_id(),
			'date'        => current_time( 'Y-m-d' ),
			'category_id' => $term->term_id,
			'important'   => true,
		);

		monsterinsights_add_site_note( $note_args );
	}
}
