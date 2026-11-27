<?php
/**
 * Unit tests for the app availability body.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\App_Availability;

class AppAvailabilityTest extends TestCase {

	private function provider( int $id, string $name ): array {
		return array(
			'id'   => $id,
			'name' => $name,
			'logo' => '/' . $id . '.jpg',
		);
	}

	private function region( array $types ): array {
		return array_merge(
			array(
				'link'     => 'https://example.test/',
				'flatrate' => array(),
				'free'     => array(),
				'ads'      => array(),
				'rent'     => array(),
				'buy'      => array(),
			),
			$types
		);
	}

	private function decode( array $body ): array {
		return json_decode( json_encode( $body ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	public function test_sample_show_compacts_to_ids(): void {
		$body = App_Availability::build(
			array(
				90003 => array( 'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ) ),
			),
			array(),
			array()
		);

		$out = $this->decode( $body );
		$this->assertSame( array( 'US' => array( 'flatrate' => array( 8 ) ) ), $out['shows']['90003'] );
		$this->assertSame( 'netflix', $out['providers']['8']['service'] );
		$this->assertSame( '/8.jpg', $out['providers']['8']['logo'] );
		$this->assertSame( array( 'name' => 'Netflix' ), $out['services']['netflix'] );
	}

	public function test_empty_types_regions_and_shows_are_dropped(): void {
		$body = App_Availability::build(
			array(
				1 => array( 'US' => $this->region( array() ) ),
				2 => array(
					'US' => $this->region( array() ),
					'GB' => $this->region( array( 'buy' => array( $this->provider( 5, 'Apple TV' ) ) ) ),
				),
				3 => array(),
			),
			array(),
			array()
		);

		$out = $this->decode( $body );
		$this->assertSame( array( '2' ), array_map( 'strval', array_keys( $out['shows'] ) ) );
		$this->assertSame( array( 'GB' ), array_keys( $out['shows']['2'] ) );
		$this->assertSame( array( 'GB' ), array_keys( $out['regions'] ) );
	}

	public function test_region_services_ranked_by_streamable_show_count(): void {
		$stored = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$stored[ $i ] = array( 'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ) );
		}
		for ( $i = 4; $i <= 5; $i++ ) {
			$stored[ $i ] = array( 'US' => $this->region( array( 'ads' => array( $this->provider( 9, 'Max' ) ) ) ) );
		}
		for ( $i = 6; $i <= 10; $i++ ) {
			$stored[ $i ] = array( 'US' => $this->region( array( 'buy' => array( $this->provider( 10, 'Apple TV' ) ) ) ) );
		}

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array( 'netflix', 'max' ), $out['regions']['US']['services'] );
	}

	public function test_ties_sort_by_slug_and_a_show_counts_once(): void {
		$stored = array(
			1 => array(
				'US' => $this->region(
					array(
						'flatrate' => array( $this->provider( 20, 'Zed' ), $this->provider( 21, 'Zed Standard with Ads' ) ),
						'ads'      => array( $this->provider( 30, 'Alpha' ) ),
					)
				),
			),
		);

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array( 'alpha', 'zed' ), $out['regions']['US']['services'] );
	}

	public function test_region_name_from_map_or_code(): void {
		$stored = array(
			1 => array(
				'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ),
				'XK' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ),
			),
		);

		$out = $this->decode( App_Availability::build( $stored, array(), array( 'US' => 'United States' ) ) );

		$this->assertSame( 'United States', $out['regions']['US']['name'] );
		$this->assertSame( 'XK', $out['regions']['XK']['name'] );
	}

	public function test_region_with_only_purchase_options_has_no_services(): void {
		$stored = array( 1 => array( 'US' => $this->region( array( 'rent' => array( $this->provider( 10, 'Apple TV' ) ) ) ) ) );

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array(), $out['regions']['US']['services'] );
	}

	public function test_attribution_exact(): void {
		$body = App_Availability::build( array(), array(), array() );

		$this->assertSame(
			array(
				'text' => 'Streaming data from JustWatch.',
				'url'  => 'https://www.justwatch.com/',
			),
			$body['attribution']
		);
	}

	public function test_rent_and_buy_kept_in_shows(): void {
		$stored = array( 1 => array( 'US' => $this->region( array( 'rent' => array( $this->provider( 2, 'Vudu' ) ) ) ) ) );

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array( 'US' => array( 'rent' => array( 2 ) ) ), $out['shows']['1'] );
	}

	public function test_empty_inputs_encode_as_objects(): void {
		$json = json_encode( App_Availability::build( array(), array(), array() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$this->assertStringContainsString( '"services":{}', $json );
		$this->assertStringContainsString( '"providers":{}', $json );
		$this->assertStringContainsString( '"regions":{}', $json );
		$this->assertStringContainsString( '"shows":{}', $json );
	}

	public function test_unclean_stored_meta_is_tolerated(): void {
		$stored = array(
			5 => 'garbage',
			3 => array(
				'us'   => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ),
				'GB'   => array(
					'link'     => '',
					'flatrate' => array(
						array(
							'id'   => 8,
							'name' => 'Netflix',
						),
						array(
							'id'   => 8,
							'name' => 'Netflix',
						),
					),
				),
				'NOPE' => 'junk',
			),
		);

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array( '3' ), array_map( 'strval', array_keys( $out['shows'] ) ) );
		$this->assertSame( array( 'GB' => array( 'flatrate' => array( 8 ) ) ), $out['shows']['3'] );
		$this->assertNull( $out['providers']['8']['logo'] );
	}

	public function test_shows_and_regions_are_ordered(): void {
		$stored = array(
			20 => array( 'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ) ),
			10 => array(
				'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ),
				'CA' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ),
			),
		);

		$out = $this->decode( App_Availability::build( $stored, array(), array() ) );

		$this->assertSame( array( '10', '20' ), array_map( 'strval', array_keys( $out['shows'] ) ) );
		$this->assertSame( array( 'CA', 'US' ), array_keys( $out['regions'] ) );
	}

	public function test_provider_index_keeps_first_seen_name_and_logo(): void {
		$stored = array(
			1 => array( 'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix' ) ) ) ) ),
			2 => array( 'US' => $this->region( array( 'flatrate' => array( $this->provider( 8, 'Netflix Renamed' ) ) ) ) ),
		);

		$this->assertSame(
			array(
				8 => array(
					'name' => 'Netflix',
					'logo' => '/8.jpg',
				),
			),
			App_Availability::provider_index( $stored )
		);
	}
}
