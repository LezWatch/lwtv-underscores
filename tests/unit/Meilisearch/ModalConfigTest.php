<?php
/**
 * Unit tests for the search modal's browser config.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Modal_Config;

class ModalConfigTest extends TestCase {

	private function strings(): array {
		$types = array( 'shows', 'characters', 'actors', 'content' );
		$s     = array(
			'labels'      => array(),
			'one'         => array(),
			'many'        => array(),
			'dialogLabel' => 'Search the site',
			'inputLabel'  => 'Search for',
			'close'       => 'Close',
			'seeAll'      => 'See all results',
			'noResults'   => 'No results for %s',
			'showing'     => 'Showing %s.',
			'unavailable' => 'Search is unavailable right now.',
			'fallback'    => 'Switched to the standard search.',
			'aka'         => 'Also known as: %s',
		);
		foreach ( $types as $t ) {
			$s['labels'][ $t ] = ucfirst( $t );
			$s['one'][ $t ]    = '%d ' . $t;
			$s['many'][ $t ]   = '%d ' . $t . 's';
		}
		return $s;
	}

	private function uids( ?string $skip = null ): array {
		$uids = array(
			'content'    => 'staging_content',
			'shows'      => 'staging_shows',
			'actors'     => 'staging_actors',
			'characters' => 'staging_characters',
		);
		if ( $skip ) {
			unset( $uids[ $skip ] );
		}
		return $uids;
	}

	private function build( string $url = 'https://s.example', ?string $skip = null ): array {
		return Modal_Config::build( $url, 'search-key', $this->uids( $skip ), 'https://lwtv.example/', $this->strings() );
	}

	public function test_numbers_are_ints(): void {
		$c = $this->build();
		$this->assertSame( 10, $c['limit'] );
		$this->assertSame( 150, $c['debounce'] );
		$this->assertSame( 2000, $c['timeout'] );
		$json = json_encode( $c ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP in unit tests.
		$this->assertSame( 10, json_decode( $json, true )['limit'] );
	}

	public function test_tabs_in_fixed_order(): void {
		$c = $this->build();
		$this->assertSame( Modal_Config::TABS, array_column( $c['tabs'], 'type' ) );
		$this->assertSame( 'staging_shows', $c['tabs'][0]['uid'] );
	}

	public function test_tab_folds_strings(): void {
		$tab = $this->build()['tabs'][1];
		$this->assertSame(
			array(
				'type'  => 'characters',
				'uid'   => 'staging_characters',
				'label' => 'Characters',
				'one'   => '%d characters',
				'many'  => '%d characterss',
			),
			$tab
		);
	}

	public function test_tab_without_uid_is_dropped(): void {
		$c = $this->build( 'https://s.example', 'actors' );
		$this->assertCount( 3, $c['tabs'] );
		$this->assertSame( array( 'shows', 'characters', 'content' ), array_column( $c['tabs'], 'type' ) );
	}

	public function test_top_level_keys_are_fixed(): void {
		$this->assertSame(
			array( 'url', 'key', 'searchPage', 'limit', 'debounce', 'timeout', 'tabs', 'strings' ),
			array_keys( $this->build() )
		);
	}

	public function test_strings_are_flat_only(): void {
		$strings = $this->build()['strings'];
		$this->assertSame( 'No results for %s', $strings['noResults'] );
		$this->assertArrayNotHasKey( 'labels', $strings );
		$this->assertArrayNotHasKey( 'one', $strings );
		$this->assertArrayNotHasKey( 'many', $strings );
	}

	public function test_url_trailing_slash_trimmed(): void {
		$this->assertSame( 'https://s.example', $this->build( 'https://s.example/' )['url'] );
	}

	public function test_preconnect_origin_is_scheme_host_and_port_only(): void {
		$this->assertSame( 'https://search.example', Modal_Config::origin( 'https://search.example' ) );
		$this->assertSame( 'https://search.example', Modal_Config::origin( ' https://search.example/ ' ) );
		$this->assertSame( 'https://search.example', Modal_Config::origin( 'https://search.example/meili/' ) );
		$this->assertSame( 'https://search.example:7700', Modal_Config::origin( 'https://search.example:7700/x' ) );
		$this->assertSame( 'http://127.0.0.1:7701', Modal_Config::origin( 'http://127.0.0.1:7701' ) );
	}

	public function test_preconnect_origin_is_empty_for_anything_but_http(): void {
		$this->assertSame( '', Modal_Config::origin( '' ) );
		$this->assertSame( '', Modal_Config::origin( 'search.example' ) );
		$this->assertSame( '', Modal_Config::origin( 'javascript:alert(1)' ) );
		$this->assertSame( '', Modal_Config::origin( 'ftp://search.example' ) );
	}
}
