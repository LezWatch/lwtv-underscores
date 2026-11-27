<?php
/**
 * Unit tests for the Search Settings pure logic.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Search_Settings;

class SearchSettingsTest extends TestCase {

	private const DEFAULTS = array(
		'version'    => 1,
		'switch'     => 'off',
		'timeout_ms' => 2000,
		'url'        => '',
		'prefix'     => '',
	);

	public function test_constants(): void {
		$this->assertSame( 'off', Search_Settings::DEFAULT_SWITCH );
		$this->assertSame( 2000, Search_Settings::TIMEOUT_DEFAULT );
		$this->assertSame( 500, Search_Settings::TIMEOUT_MIN );
		$this->assertSame( 5000, Search_Settings::TIMEOUT_MAX );
		$this->assertSame( 100, Search_Settings::TIMEOUT_STEP );
		$this->assertSame( self::DEFAULTS, Search_Settings::defaults() );
	}

	public function test_read_drops_bad_connection_fields(): void {
		$out = Search_Settings::read(
			array(
				'url'    => array(),
				'prefix' => 'bad prefix',
			)
		);
		$this->assertSame( '', $out['url'] );
		$this->assertSame( '', $out['prefix'] );

		$out = Search_Settings::read(
			array(
				'url'    => 'https://x.test/',
				'prefix' => 'qa',
			)
		);
		$this->assertSame( 'https://x.test', $out['url'] );
		$this->assertSame( 'qa', $out['prefix'] );
	}

	public function test_read_fills_defaults(): void {
		$this->assertSame( self::DEFAULTS, Search_Settings::read( null ) );
		$this->assertSame( self::DEFAULTS, Search_Settings::read( 'junk' ) );
		$this->assertSame(
			self::DEFAULTS,
			Search_Settings::read(
				array(
					'switch'     => 'EVERYONE',
					'timeout_ms' => '3000',
				)
			)
		);
		$this->assertSame(
			array(
				'version'    => 1,
				'switch'     => 'editors',
				'timeout_ms' => 3000,
				'url'        => '',
				'prefix'     => '',
			),
			Search_Settings::read(
				array(
					'switch'     => 'editors',
					'timeout_ms' => 3000,
					'key'        => 'nope',
				)
			)
		);
	}

	public function test_validate_refuses_bad_timeouts(): void {
		foreach ( array( '2000', 2000.5, 499, 5001, 2050, null, array() ) as $bad ) {
			$out = Search_Settings::validate( array( 'timeout_ms' => $bad ) );
			$this->assertSame( array(), $out['values'], var_export( $bad, true ) );
			$this->assertSame( array( 'timeout_ms' ), array_keys( $out['errors'] ), var_export( $bad, true ) );
		}
	}

	public function test_validate_refuses_bad_switches(): void {
		foreach ( array( 'EVERYONE', 'Editors ', true, '', null, 1 ) as $bad ) {
			$out = Search_Settings::validate( array( 'switch' => $bad ) );
			$this->assertSame( array(), $out['values'], var_export( $bad, true ) );
			$this->assertSame( array( 'switch' ), array_keys( $out['errors'] ), var_export( $bad, true ) );
		}
	}

	public function test_validate_accepts_good_values(): void {
		$this->assertSame(
			array(
				'values' => array(
					'switch'     => 'everyone',
					'timeout_ms' => 500,
				),
				'errors' => array(),
			),
			Search_Settings::validate(
				array(
					'switch'     => 'everyone',
					'timeout_ms' => 500,
				)
			)
		);
		$this->assertSame(
			array(
				'values' => array( 'timeout_ms' => 5000 ),
				'errors' => array(),
			),
			Search_Settings::validate( array( 'timeout_ms' => 5000 ) )
		);
		$this->assertSame(
			array(
				'values' => array(),
				'errors' => array(),
			),
			Search_Settings::validate(
				array(
					'key'     => 'x',
					'applied' => 1,
				)
			)
		);
		$this->assertSame(
			array(
				'values' => array(),
				'errors' => array(),
			),
			Search_Settings::validate( 'not an array' )
		);
	}

	public function test_effective_constant_wins(): void {
		$option = Search_Settings::read( array( 'switch' => 'everyone' ) );

		$this->assertSame(
			array(
				'mode'   => 'editors',
				'source' => 'constant',
				'raw'    => 'editors',
			),
			Search_Settings::effective( true, 'editors', $option )
		);
		$this->assertSame(
			array(
				'mode'   => 'off',
				'source' => 'constant',
				'raw'    => '1',
			),
			Search_Settings::effective( true, true, $option )
		);
		$this->assertSame(
			array(
				'mode'   => 'editors',
				'source' => 'constant',
				'raw'    => 'Editors ',
			),
			Search_Settings::effective( true, 'Editors ', $option )
		);
		$this->assertSame(
			array(
				'mode'   => 'off',
				'source' => 'constant',
				'raw'    => null,
			),
			Search_Settings::effective( true, array(), $option )
		);
	}

	public function test_effective_uses_the_option_without_a_constant(): void {
		$this->assertSame(
			array(
				'mode'   => 'everyone',
				'source' => 'option',
				'raw'    => null,
			),
			Search_Settings::effective( false, null, Search_Settings::read( array( 'switch' => 'everyone' ) ) )
		);
		$this->assertSame( 'off', Search_Settings::effective( false, null, Search_Settings::read( null ) )['mode'] );
	}

	public function test_seed(): void {
		$this->assertSame( 'everyone', Search_Settings::seed( true, 'everyone', null ) );
		$this->assertSame( 'editors', Search_Settings::seed( true, 'Editors ', array( 'timeout_ms' => 3000 ) ) );
		$this->assertSame( 'off', Search_Settings::seed( true, 'off', null ) );
		$this->assertNull( Search_Settings::seed( true, 'everyone', array( 'switch' => 'off' ) ) );
		$this->assertNull( Search_Settings::seed( true, 'yes', null ) );
		$this->assertNull( Search_Settings::seed( true, true, null ) );
		$this->assertNull( Search_Settings::seed( false, 'everyone', null ) );
	}
}
