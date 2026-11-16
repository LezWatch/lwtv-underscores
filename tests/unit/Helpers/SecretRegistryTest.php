<?php
/**
 * Unit tests for the credential registry and its resolution rules.
 *
 * Secret_Registry is the pure half of the Secrets API wrapper: which
 * credentials exist, where each one used to live, how a lookup result plus a
 * fallback becomes a value, and what a value must look like before it is
 * stored. The WordPress half (_Helpers\Secrets) only gathers the inputs.
 *
 * No real credentials appear here. Every value is an obvious placeholder.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Secret_Registry;

class SecretRegistryTest extends TestCase {

	/*
	 * The registry.
	 */

	public function test_every_credential_the_site_uses_is_registered(): void {
		$this->assertSame(
			array( 'postiz-api-key', 'tmdb-api-key', 'gemini-api-key', 'google-service-account', 'tvmaze-ical-url' ),
			Secret_Registry::names()
		);
	}

	public function test_full_names_are_namespaced_under_lwtv(): void {
		$this->assertSame( 'lwtv/tmdb-api-key', Secret_Registry::full_name( 'tmdb-api-key' ) );
	}

	public function test_every_full_name_passes_cores_name_rules(): void {
		// Core: 'ns/key', each segment [a-z0-9] with inner [a-z0-9_-], 172 chars max.
		foreach ( Secret_Registry::names() as $name ) {
			$full = Secret_Registry::full_name( $name );
			$this->assertMatchesRegularExpression( '#^[a-z0-9]([a-z0-9_-]*[a-z0-9])?/[a-z0-9]([a-z0-9_-]*[a-z0-9])?$#', $full );
			$this->assertLessThanOrEqual( 172, strlen( $full ) );
		}
	}

	public function test_unknown_names_are_refused(): void {
		$this->assertFalse( Secret_Registry::is_known( 'api-key' ) );
		$this->assertFalse( Secret_Registry::is_known( 'lwtv/tmdb-api-key' ) ); // Short names only.
		$this->assertTrue( Secret_Registry::is_known( 'tmdb-api-key' ) );
	}

	public function test_fallbacks_point_at_where_each_credential_lived_before(): void {
		$this->assertSame(
			array(
				'type' => 'option',
				'name' => 'options_lwtv_postiz_api_key',
			),
			Secret_Registry::fallback( 'postiz-api-key' )
		);
		$this->assertSame(
			array(
				'type' => 'constant',
				'name' => 'TMDB_API',
			),
			Secret_Registry::fallback( 'tmdb-api-key' )
		);
		$this->assertSame(
			array(
				'type' => 'constant',
				'name' => 'LWTV_GEMINI_API_KEY',
			),
			Secret_Registry::fallback( 'gemini-api-key' )
		);
		$this->assertSame(
			array(
				'type' => 'file',
				'name' => 'LWTV_GOOGLE_SERVICE_ACCOUNT',
			),
			Secret_Registry::fallback( 'google-service-account' )
		);
		$this->assertSame(
			array(
				'type' => 'constant',
				'name' => 'TV_MAZE',
			),
			Secret_Registry::fallback( 'tvmaze-ical-url' )
		);
	}

	public function test_every_plaintext_copy_of_the_postiz_key_is_listed_for_cleanup(): void {
		// The live ACF row, the row the CMB2 migration wrote without the
		// options_ prefix, and both ACF field-key reference rows.
		$this->assertSame(
			array( 'options_lwtv_postiz_api_key', '_options_lwtv_postiz_api_key', 'lwtv_postiz_api_key', '_lwtv_postiz_api_key' ),
			Secret_Registry::legacy_options( 'postiz-api-key' )
		);
		$this->assertSame( array(), Secret_Registry::legacy_options( 'tmdb-api-key' ) );
	}

	public function test_the_old_cmb2_array_copy_of_the_postiz_key_is_listed_for_cleanup(): void {
		$this->assertSame(
			array(
				'option' => 'lwtv_auto_posting_options',
				'key'    => 'lwtv_postiz_api_key',
			),
			Secret_Registry::legacy_array( 'postiz-api-key' )
		);
		$this->assertNull( Secret_Registry::legacy_array( 'gemini-api-key' ) );
	}

	/*
	 * resolve(): lookup state + fallback -> value and source.
	 */

	public function test_a_stored_secret_wins_over_the_fallback(): void {
		$this->assertSame(
			array(
				'value'  => 'from-store',
				'source' => 'secret',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_FOUND, 'from-store', 'from-constant', 'constant' )
		);
	}

	public function test_a_missing_secret_uses_the_fallback(): void {
		$this->assertSame(
			array(
				'value'  => 'from-constant',
				'source' => 'constant',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_MISSING, '', 'from-constant', 'constant' )
		);
	}

	public function test_no_secrets_api_uses_the_fallback(): void {
		// Pre-7.2, or a site where the API is not loaded.
		$this->assertSame(
			array(
				'value'  => 'from-option',
				'source' => 'option',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_UNAVAILABLE, '', 'from-option', 'option' )
		);
	}

	public function test_an_unreadable_secret_falls_back_but_says_so(): void {
		// e.g. WP_SECRETS_KEY changed or missing. The caller logs; the source
		// tells status output that the store has a value it could not open.
		$this->assertSame(
			array(
				'value'  => 'from-constant',
				'source' => 'constant-after-error',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_ERROR, '', 'from-constant', 'constant' )
		);
	}

	public function test_an_unreadable_secret_with_no_fallback_is_an_error_not_none(): void {
		$this->assertSame(
			array(
				'value'  => '',
				'source' => 'error',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_ERROR, '', '', 'constant' )
		);
	}

	public function test_an_empty_stored_secret_counts_as_missing(): void {
		$this->assertSame(
			array(
				'value'  => 'from-constant',
				'source' => 'constant',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_FOUND, '', 'from-constant', 'constant' )
		);
	}

	public function test_nothing_anywhere_is_none(): void {
		$this->assertSame(
			array(
				'value'  => '',
				'source' => 'none',
			),
			Secret_Registry::resolve( Secret_Registry::STATE_MISSING, '', '', 'constant' )
		);
	}

	/*
	 * validate(): what may be stored.
	 */

	public function test_a_plain_key_is_accepted_and_trimmed(): void {
		$this->assertSame(
			array(
				'value' => 'abc123',
				'error' => '',
			),
			Secret_Registry::validate( 'tmdb-api-key', "  abc123\n" )
		);
	}

	public function test_an_empty_value_is_refused(): void {
		$this->assertNotSame( '', Secret_Registry::validate( 'tmdb-api-key', " \n" )['error'] );
	}

	public function test_a_key_with_inner_whitespace_is_refused(): void {
		// A paste that picked up two lines, or a label and a value.
		$this->assertNotSame( '', Secret_Registry::validate( 'gemini-api-key', "abc\ndef" )['error'] );
		$this->assertNotSame( '', Secret_Registry::validate( 'postiz-api-key', 'API key: abc' )['error'] );
	}

	public function test_an_unknown_name_is_refused(): void {
		$this->assertNotSame( '', Secret_Registry::validate( 'banana', 'abc' )['error'] );
	}

	public function test_the_tvmaze_feed_must_be_an_https_url(): void {
		$this->assertSame( '', Secret_Registry::validate( 'tvmaze-ical-url', 'https://api.tvmaze.com/ical/followed?token=placeholder' )['error'] );
		$this->assertNotSame( '', Secret_Registry::validate( 'tvmaze-ical-url', 'http://api.tvmaze.com/ical/followed?token=placeholder' )['error'] );
		$this->assertNotSame( '', Secret_Registry::validate( 'tvmaze-ical-url', 'placeholder' )['error'] );
	}

	public function test_a_service_account_key_must_be_the_json_not_a_path(): void {
		$json = (string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no WordPress here.
			array(
				'type'         => 'service_account',
				'client_email' => 'bot@example.iam.gserviceaccount.com',
				'private_key'  => "-----BEGIN PRIVATE KEY-----\nplaceholder\n-----END PRIVATE KEY-----\n",
			)
		);

		$this->assertSame(
			array(
				'value' => $json,
				'error' => '',
			),
			Secret_Registry::validate( 'google-service-account', $json . "\n" )
		);
		$this->assertNotSame( '', Secret_Registry::validate( 'google-service-account', '/home/lwtv/keys/service-account.json' )['error'] );
	}

	public function test_a_service_account_key_missing_its_fields_is_refused(): void {
		$this->assertNotSame( '', Secret_Registry::validate( 'google-service-account', '{"type":"service_account","client_email":"bot@example.com"}' )['error'] );
		$this->assertNotSame( '', Secret_Registry::validate( 'google-service-account', '{"type":"authorized_user","client_email":"a","private_key":"b"}' )['error'] );
	}

	public function test_validation_errors_never_echo_the_value(): void {
		foreach ( array( 'tmdb-api-key', 'tvmaze-ical-url', 'google-service-account' ) as $name ) {
			$error = Secret_Registry::validate( $name, 'sekrit placeholder value' )['error'];
			$this->assertNotSame( '', $error );
			$this->assertStringNotContainsString( 'sekrit', $error );
		}
	}

	/*
	 * redact(): scrubbing values out of messages before they are logged.
	 */

	public function test_redact_replaces_every_occurrence(): void {
		$this->assertSame( 'bad key [redacted] at [redacted]', Secret_Registry::redact( 'bad key abc123 at abc123', array( 'abc123' ) ) );
	}

	public function test_redact_ignores_empty_values(): void {
		// str_replace( '', ... ) would be a no-op anyway; pinned so it stays one.
		$this->assertSame( 'message', Secret_Registry::redact( 'message', array( '', 'zzz' ) ) );
	}
}
