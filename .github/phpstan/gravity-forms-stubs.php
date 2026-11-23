<?php
/**
 * Minimal Gravity Forms stubs for PHPStan. Never loaded by WordPress.
 *
 * Gravity Forms is a paid plugin with no published stubs package, and PHPStan can't
 * analyse a class that extends one it can't find (GF_Slack_Approvals extends GFFeedAddOn),
 * and it won't let that error into the baseline. These declare just enough for
 * discovery. The magic __call / __callStatic methods mean any GF method call is
 * accepted and typed as mixed, so these stubs don't check GF API usage; they only
 * stop the crash.
 *
 * Lives under .github/ so it's excluded from PHPCS and from the deploy rsync.
 *
 * @package lwtv-underscores
 */

// phpcs:ignoreFile

class GFForms {
	public static function __callStatic( $name, $args ) {}
}

class GFAddOn {
	protected $_version;
	protected $_min_gravityforms_version;
	protected $_slug;
	protected $_path;
	protected $_full_path;
	protected $_title;
	protected $_short_title;

	public function __call( $name, $args ) {}
	public static function __callStatic( $name, $args ) {}
}

class GFFeedAddOn extends GFAddOn {}

class GFAPI {
	public static function __callStatic( $name, $args ) {}

	/** @return array|\WP_Error */
	public static function get_entry( $entry_id ) {}

	/** @return int|\WP_Error|null */
	public static function add_note( $entry_id, $user_id, $user_name, $note, $note_type = 'user', $sub_type = null ) {}

	/** @return list<array<string,mixed>> */
	public static function get_forms( $active = true, $trash = false, $sort_column = 'id', $sort_dir = 'ASC' ) {}

	/** @return int */
	public static function count_entries( $form_ids, $search_criteria = array() ) {}

	/** @return list<array<string,mixed>>|\WP_Error */
	public static function get_feeds( $feed_ids = null, $form_ids = null, $addon_slug = null, $is_active = true ) {}

	/** @return bool */
	public static function current_user_can_any( $capabilities ) {}

	/** @return bool|\WP_Error */
	public static function update_entry_property( $entry_id, $property, $value ) {}
}

class GFFormDisplay {
	public static function __callStatic( $name, $args ) {}

	/** @return string */
	public static function get_confirmation_message( $confirmation, $form, $entry, $aux_data = array() ) {}
}

class GFCommon {
	public static function __callStatic( $name, $args ) {}
}

function gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {}
function gform_get_meta( $entry_id, $meta_key ) {}
function gform_delete_meta( $entry_id, $meta_key = '' ) {}
