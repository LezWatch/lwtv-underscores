<?php
/**
 * Unit tests for the provider-to-service map.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\Service_Map;

class ServiceMapTest extends TestCase {

	private function provider( string $name, string $logo = '/a.jpg' ): array {
		return array(
			'name' => $name,
			'logo' => $logo,
		);
	}

	public function test_ad_tier_folds_into_base_service(): void {
		$map = Service_Map::build(
			array(
				8    => $this->provider( 'Netflix' ),
				1796 => $this->provider( 'Netflix Standard with Ads' ),
			),
			array()
		);

		$this->assertSame( 'netflix', $map['providers'][8]['service'] );
		$this->assertSame( 'netflix', $map['providers'][1796]['service'] );
		$this->assertSame( array( 'netflix' => array( 'name' => 'Netflix' ) ), $map['services'] );
	}

	public function test_resellers_and_spellings_share_a_service(): void {
		$map = Service_Map::build(
			array(
				10 => $this->provider( 'AMC+ Amazon Channel' ),
				20 => $this->provider( 'AMC Plus Apple TV channel' ),
				30 => $this->provider( 'AMC+' ),
			),
			array()
		);

		foreach ( array( 10, 20, 30 ) as $id ) {
			$this->assertSame( 'amc-plus', $map['providers'][ $id ]['service'] );
		}
		$this->assertSame( array( 'amc-plus' => array( 'name' => 'AMC+' ) ), $map['services'] );
	}

	public function test_prefix_without_ads_does_not_fold(): void {
		$map = Service_Map::build(
			array(
				1 => $this->provider( 'Apple TV' ),
				2 => $this->provider( 'Apple TV Plus' ),
				3 => $this->provider( 'Max' ),
				4 => $this->provider( 'maxdome Store' ),
			),
			array()
		);

		$this->assertNotSame( $map['providers'][1]['service'], $map['providers'][2]['service'] );
		$this->assertNotSame( $map['providers'][3]['service'], $map['providers'][4]['service'] );
		$this->assertCount( 4, $map['services'] );
	}

	public function test_override_wins_over_auto(): void {
		$map = Service_Map::build(
			array( 1899 => $this->provider( 'HBO Max' ) ),
			array(
				1899 => array(
					'slug' => 'max',
					'name' => 'Max',
				),
			)
		);

		$this->assertSame( 'max', $map['providers'][1899]['service'] );
		$this->assertSame( array( 'max' => array( 'name' => 'Max' ) ), $map['services'] );
	}

	public function test_override_carries_to_ad_tier(): void {
		$map = Service_Map::build(
			array(
				1899 => $this->provider( 'HBO Max' ),
				1900 => $this->provider( 'HBO Max Basic with Ads' ),
			),
			array(
				1899 => array(
					'slug' => 'max',
					'name' => 'Max',
				),
			)
		);

		$this->assertSame( 'max', $map['providers'][1900]['service'] );
		$this->assertCount( 1, $map['services'] );
	}

	public function test_logo_blank_is_null(): void {
		$map = Service_Map::build(
			array(
				1 => $this->provider( 'Hulu', '' ),
				2 => $this->provider( 'Tubi', '/t.png' ),
			),
			array()
		);

		$this->assertNull( $map['providers'][1]['logo'] );
		$this->assertSame( '/t.png', $map['providers'][2]['logo'] );
		$this->assertSame( 'Hulu', $map['providers'][1]['name'] );
	}

	public function test_name_with_no_letters_gets_its_own_id_service(): void {
		$map = Service_Map::build( array( 5 => $this->provider( '!!!' ) ), array() );

		$this->assertSame( 'service-5', $map['providers'][5]['service'] );
		$this->assertSame( array( 'service-5' => array( 'name' => '!!!' ) ), $map['services'] );
	}

	public function test_slug_table_is_deterministic(): void {
		$this->assertSame( 'canal-plus-series', Service_Map::slug( 'Canal+ Séries' ) );
		$this->assertSame( 'maori-plus', Service_Map::slug( 'Māori+' ) );
		$this->assertSame( 'belas-artes-a-la-carte', Service_Map::slug( 'Belas Artes à la Carte' ) );
		$this->assertSame( 'strassen-tv', Service_Map::slug( 'Straßen TV' ) );
		$this->assertSame( 'oeuvre', Service_Map::slug( 'Œuvre' ) );
		$this->assertSame( '', Service_Map::slug( 'ТНТ' ) );
		$this->assertSame( 'service-10', Service_Map::slug( '10' ) );
		$this->assertSame( 'service-5', Service_Map::slug( '5' ) );
	}

	public function test_empty_slug_group_falls_back_to_provider_id(): void {
		$map = Service_Map::build(
			array(
				77 => $this->provider( '★' ),
				78 => $this->provider( 'ТНТ' ),
			),
			array()
		);

		$this->assertSame( 'service-77', $map['providers'][77]['service'] );
		$this->assertSame( 'service-78', $map['providers'][78]['service'] );
		$this->assertSame( array( 'name' => '★' ), $map['services']['service-77'] );
		$this->assertSame( array( 'name' => 'ТНТ' ), $map['services']['service-78'] );
	}

	public function test_names_that_slug_alike_get_an_id_suffix(): void {
		$map = Service_Map::build(
			array(
				3 => $this->provider( 'Café' ),
				9 => $this->provider( 'Cafe' ),
			),
			array()
		);

		$this->assertSame( 'cafe', $map['providers'][3]['service'] );
		$this->assertSame( 'cafe-9', $map['providers'][9]['service'] );
		$this->assertSame( array( 'cafe', 'cafe-9' ), array_keys( $map['services'] ) );
	}

	public function test_slug(): void {
		$this->assertSame( 'prime-video', Service_Map::slug( 'Prime Video' ) );
		$this->assertSame( 'amc-plus', Service_Map::slug( 'AMC+' ) );
		$this->assertSame( 'a-b', Service_Map::slug( '  A & B!! ' ) );
	}

	public function test_parse_ids(): void {
		$this->assertSame( array( 8, 1796 ), Service_Map::parse_ids( ' 8, 1796 8 x -2 ' ) );
		$this->assertSame( array(), Service_Map::parse_ids( '' ) );
	}

	public function test_output_is_sorted(): void {
		$map = Service_Map::build(
			array(
				9 => $this->provider( 'Zee5' ),
				3 => $this->provider( 'Acorn TV' ),
			),
			array()
		);

		$this->assertSame( array( 3, 9 ), array_keys( $map['providers'] ) );
		$this->assertSame( array( 'acorn-tv', 'zee5' ), array_keys( $map['services'] ) );
	}

	public function test_ad_tier_needs_tier_words_not_any_words(): void {
		$map = Service_Map::build(
			array(
				1 => $this->provider( 'Apple TV' ),
				2 => $this->provider( 'Apple TV Plus Basic with Ads' ),
				3 => $this->provider( 'Netflix' ),
				4 => $this->provider( 'Netflix Standard with Ads' ),
				5 => $this->provider( 'Netflix basic with Ads' ),
			),
			array()
		);

		$this->assertNotSame( 'apple-tv', $map['providers'][2]['service'] );
		$this->assertSame( 'netflix', $map['providers'][4]['service'] );
		$this->assertSame( 'netflix', $map['providers'][5]['service'] );
	}

	public function test_reseller_only_group_uses_stripped_name(): void {
		$map = Service_Map::build( array( 10 => $this->provider( 'AMC+ Amazon Channel' ) ), array() );

		$this->assertSame( 'amc-plus', $map['providers'][10]['service'] );
		$this->assertSame( array( 'amc-plus' => array( 'name' => 'AMC+' ) ), $map['services'] );
	}

	public function test_auto_group_sharing_override_slug_merges_into_override(): void {
		$map = Service_Map::build(
			array(
				1    => $this->provider( 'Max' ),
				1899 => $this->provider( 'HBO Max' ),
			),
			array(
				1899 => array(
					'slug' => 'max',
					'name' => 'Max',
				),
			)
		);

		$this->assertSame( 'max', $map['providers'][1]['service'] );
		$this->assertSame( 'max', $map['providers'][1899]['service'] );
		$this->assertSame( array( 'max' => array( 'name' => 'Max' ) ), $map['services'] );
	}
}
