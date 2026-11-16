<?php
/**
 * WordPress 7.2 Secrets API stubs for PHPStan. Never loaded by WordPress.
 *
 * php-stubs/wordpress-stubs doesn't ship wp-includes/secrets.php yet. These
 * declare the signatures _Helpers\Secrets and `wp lwtv secrets` call, copied
 * from 7.2-beta4. Delete this file (and its scanFiles entry) once the stubs
 * package covers 7.2.
 *
 * Lives under .github/ so it's excluded from PHPCS and from the deploy rsync.
 *
 * @package lwtv-underscores
 */

// phpcs:ignoreFile

final class WP_Secret_Version {
	const CURRENT  = 'current';
	const PREVIOUS = 'previous';
}

final class WP_Secret {
	/** @return string|WP_Error */
	public function reveal() {}
	public function fingerprint(): string {}
	public function get_name(): string {}
}

interface WP_Secrets_Store {}

class WP_Secrets_Option_Store implements WP_Secrets_Store {
	const SITE_PREFIX    = '_wp_secret_';
	const NETWORK_PREFIX = '_wp_network_secret_';
}

/** @return true|WP_Error */
function wp_set_secret( string $name, $value ) {}

/** @return WP_Secret|null|WP_Error */
function wp_get_secret( string $name, string $version = WP_Secret_Version::CURRENT ) {}

/** @return true|WP_Error */
function wp_delete_secret( string $name ) {}

/** @return true|WP_Error */
function wp_retire_secret_version( string $name ) {}

/** @return true|WP_Error */
function wp_import_option_as_secret( string $option, string $name ) {}

/** @return list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}>|WP_Error */
function wp_list_secrets( string $namespace = '' ) {}

function wp_using_secrets_dropin(): bool {}

function _wp_secrets_get_store(): WP_Secrets_Store {}
