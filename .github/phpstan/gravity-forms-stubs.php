<?php
/**
 * Minimal Gravity Forms stubs for PHPStan. Never loaded by WordPress.
 *
 * Gravity Forms is a paid plugin with no published stubs package, and PHPStan can't
 * analyse a class that extends one it can't find (GF_Approvals extends GFFeedAddOn),
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
}

class GFCommon {
	public static function __callStatic( $name, $args ) {}
}

function gform_update_meta( $entry_id, $meta_key, $meta_value, $form_id = null ) {}
function gform_get_meta( $entry_id, $meta_key ) {}
