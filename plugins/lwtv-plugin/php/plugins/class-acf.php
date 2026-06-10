<?php
/*
 * Library: ACF Pro Add Ons
 * Description: Configuration and JSON sync for Advanced Custom Fields Pro.
 * Version: 1.0.0
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF {

	/**
	 * Fields visible to administrators only.
	 * Add field names here as more CPTs are migrated.
	 */
	const ADMIN_ONLY_FIELDS = array(
		'lezactors_queer_override',
		// Shows (added in Phase 2b):
		// 'lezshows_worthit_show_we_love',
		// 'lezshows_byq_override',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		add_filter( 'acf/settings/save_json', array( $this, 'save_json_path' ) );
		add_filter( 'acf/settings/load_json', array( $this, 'load_json_paths' ) );

		// Bridge the `excerpt` ACF textarea to post_excerpt for actors and shows.
		add_filter( 'acf/load_value/name=excerpt', array( $this, 'load_excerpt_from_post' ), 10, 3 );
		add_action( 'acf/save_post', array( $this, 'save_excerpt_to_post' ), 20 );

		// Restrict specific fields to administrators only.
		foreach ( self::ADMIN_ONLY_FIELDS as $field_name ) {
			add_filter( 'acf/prepare_field/name=' . $field_name, array( $this, 'restrict_to_admin' ) );
		}

		add_action( 'acf/input/admin_head', array( $this, 'admin_head_styles' ) );
	}

	/**
	 * Set the directory ACF saves field group JSON files to.
	 *
	 * @return string
	 */
	public function save_json_path(): string {
		return LWTV_PLUGIN_PATH . '/acf-json';
	}

	/**
	 * Add our acf-json directory to ACF's load paths.
	 *
	 * @param array $paths Existing load paths.
	 * @return array
	 */
	public function load_json_paths( array $paths ): array {
		$paths[] = LWTV_PLUGIN_PATH . '/acf-json';
		return $paths;
	}

	/**
	 * Populate the `excerpt` ACF field from post_excerpt when the meta row is empty.
	 *
	 * CMB2 stored this field directly in post_excerpt, not in post_meta.
	 *
	 * @param mixed $value   Current field value (from post_meta).
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_excerpt_from_post( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$value = get_post_field( 'post_excerpt', $post_id );
		}
		return $value;
	}

	/**
	 * Mirror the `excerpt` ACF field value back to post_excerpt on save.
	 *
	 * Keeps post_excerpt in sync so any code reading it directly stays correct.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_excerpt_to_post( int $post_id ): void {
		$cpts = array( 'post_type_actors', 'post_type_shows' );
		if ( ! in_array( get_post_type( $post_id ), $cpts, true ) ) {
			return;
		}

		$excerpt = get_field( 'excerpt', $post_id );
		if ( false === $excerpt || empty( $excerpt ) ) {
			return;
		}

		// Use a direct DB write to avoid re-triggering save_post hooks.
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->posts,
			array( 'post_excerpt' => $excerpt ?? '' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * Output admin-only styles for ACF field layout tweaks.
	 *
	 * @return void
	 */
	public function admin_head_styles(): void {
		?>
		<style>
		.lwtv-acf-col-2 .acf-checkbox-list {
			column-count: 2;
			column-gap: 1.5em;
		}
		.lwtv-acf-col-3 .acf-checkbox-list {
			column-count: 3;
			column-gap: 1em;
		}
		.lwtv-acf-col-2 .acf-checkbox-list li,
		.lwtv-acf-col-3 .acf-checkbox-list li {
			break-inside: avoid;
		}
		</style>
		<?php
	}

	/**
	 * Hide a field from non-administrators.
	 *
	 * Returning false from acf/prepare_field removes the field from the form
	 * without touching the stored value — existing data is preserved.
	 *
	 * @param array|false $field ACF field definition.
	 * @return array|false
	 */
	public function restrict_to_admin( $field ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $field;
		}
		return false;
	}
}
