<?php
/**
 * Unit tests for the Meilisearch connection settings (URL, index prefix).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Connection;

class ConnectionTest extends TestCase {

	private const DEFAULTS = array(
		'url'    => 'https://search.lwtv.local',
		'prefix' => 'local',
	);

	private const NONE = array(
		'url'    => null,
		'prefix' => null,
	);

	private const UNSAVED = array(
		'url'    => '',
		'prefix' => '',
	);

	public function test_default_url_drops_www_and_port(): void {
		$this->assertSame( 'https://search.lezwatchtv.com', Connection::default_url( 'https://www.LezWatchTV.com/' ) );
		$this->assertSame( 'https://search.lwtv.local', Connection::default_url( 'https://lwtv.local:8443' ) );
		$this->assertSame( '', Connection::default_url( '' ) );
	}

	public function test_default_prefix_is_environment_type(): void {
		$this->assertSame( 'local', Connection::default_prefix( 'local' ) );
		$this->assertSame( 'development', Connection::default_prefix( 'development' ) );
	}

	public function test_normalizes_url_and_prefix(): void {
		$this->assertSame( 'https://search.lwtv.local', Connection::normalize_url( ' https://search.lwtv.local/ ' ) );
		$this->assertSame( 'https://x.test/meili', Connection::normalize_url( 'https://x.test/meili' ) );
		$this->assertSame( 'staging', Connection::normalize_prefix( ' Staging_ ' ) );
		$this->assertSame( '', Connection::normalize_url( '' ) );
		$this->assertSame( '', Connection::normalize_prefix( '' ) );
	}

	public function test_refuses_bad_urls(): void {
		foreach ( array( 'ftp://x.test', 'search.lwtv.local', 'https://', 'https://x.test/?a=1', 'https://x.test/#f', array() ) as $bad ) {
			$this->assertNull( Connection::normalize_url( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_refuses_bad_prefixes(): void {
		foreach ( array( 'has space', 'dot.ted', str_repeat( 'a', 41 ), '_', 5 ) as $bad ) {
			$this->assertNull( Connection::normalize_prefix( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_override_equal_to_default_is_stored_empty(): void {
		$out = Connection::validate(
			array(
				'url'    => 'https://search.lwtv.local/',
				'prefix' => 'LOCAL',
			),
			self::DEFAULTS
		);

		$this->assertSame( self::UNSAVED, $out['values'] );
		$this->assertSame( array(), $out['errors'] );
	}

	public function test_validate_keeps_real_overrides_and_ignores_other_keys(): void {
		$out = Connection::validate(
			array(
				'url'    => 'https://meili.example.com',
				'prefix' => 'qa',
				'switch' => 'everyone',
			),
			self::DEFAULTS
		);

		$this->assertSame(
			array(
				'url'    => 'https://meili.example.com',
				'prefix' => 'qa',
			),
			$out['values']
		);
	}

	public function test_validate_reports_each_bad_field(): void {
		$out = Connection::validate(
			array(
				'url'    => 'nope',
				'prefix' => 'bad prefix',
			),
			self::DEFAULTS
		);

		$this->assertSame( array(), $out['values'] );
		$this->assertSame( array( 'url', 'prefix' ), array_keys( $out['errors'] ) );
	}

	public function test_resolve_uses_defaults_when_nothing_saved(): void {
		$out = Connection::resolve( self::NONE, self::UNSAVED, self::DEFAULTS );

		$this->assertSame(
			array(
				'value'   => 'https://search.lwtv.local',
				'source'  => 'default',
				'default' => 'https://search.lwtv.local',
			),
			$out['url']
		);
		$this->assertSame(
			array(
				'value'   => 'local',
				'source'  => 'default',
				'default' => 'local',
			),
			$out['prefix']
		);
	}

	public function test_resolve_saved_beats_default_and_constant_beats_saved(): void {
		$saved = array(
			'url'    => 'https://saved.test',
			'prefix' => 'saved',
		);

		$out = Connection::resolve( self::NONE, $saved, self::DEFAULTS );
		$this->assertSame( array( 'https://saved.test', 'saved' ), array( $out['url']['value'], $out['prefix']['value'] ) );
		$this->assertSame( array( 'saved', 'saved' ), array( $out['url']['source'], $out['prefix']['source'] ) );

		$constants = array(
			'url'    => 'https://defined.test',
			'prefix' => 'defined',
		);

		$out = Connection::resolve( $constants, $saved, self::DEFAULTS );
		$this->assertSame( array( 'https://defined.test', 'defined' ), array( $out['url']['value'], $out['prefix']['value'] ) );
		$this->assertSame( array( 'constant', 'constant' ), array( $out['url']['source'], $out['prefix']['source'] ) );
	}

	public function test_resolve_empty_prefix_constant_means_no_prefix(): void {
		$out = Connection::resolve(
			array(
				'url'    => null,
				'prefix' => '',
			),
			array(
				'url'    => '',
				'prefix' => 'saved',
			),
			self::DEFAULTS
		);

		$this->assertSame(
			array(
				'value'   => '',
				'source'  => 'constant',
				'default' => 'local',
			),
			$out['prefix']
		);
	}

	public function test_resolve_cleans_constant_values(): void {
		$out = Connection::resolve(
			array(
				'url'    => ' https://defined.test/ ',
				'prefix' => ' has space ',
			),
			self::UNSAVED,
			self::DEFAULTS
		);

		$this->assertSame( 'https://defined.test', $out['url']['value'] );
		// A bad define is still the admin's explicit choice; it's used as given.
		$this->assertSame( 'has space', $out['prefix']['value'] );
	}

	public function test_normalize_url_lowercases_scheme_and_host(): void {
		$this->assertSame( 'https://search.lwtv.local/Path', Connection::normalize_url( 'HTTPS://Search.LWTV.local/Path' ) );

		$out = Connection::validate( array( 'url' => 'https://Search.lwtv.local' ), self::DEFAULTS );
		$this->assertSame( array( 'url' => '' ), $out['values'] );
	}

	public function test_refuses_userinfo_and_whitespace(): void {
		foreach ( array( 'https://user:pw@x.test', 'https://user@x.test', 'https://search .x.com', 'https://x.test/a b' ) as $bad ) {
			$this->assertNull( Connection::normalize_url( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_plain_http_only_for_local_hosts(): void {
		$this->assertNull( Connection::normalize_url( 'http://search.lezwatchtv.com' ) );
		foreach ( array( 'http://localhost:7700', 'http://127.0.0.1:7700', 'http://search.lwtv.local', 'http://meili.test' ) as $ok ) {
			$this->assertSame( $ok, Connection::normalize_url( $ok ), $ok );
		}
	}
}
