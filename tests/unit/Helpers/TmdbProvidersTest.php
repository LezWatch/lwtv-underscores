<?php
/**
 * Unit tests for the TMDB watch-providers normaliser.
 *
 * /tv/{id}/watch/providers returns JustWatch data keyed by country. The response
 * is third-party input that ends up in post meta and in an href, so the
 * normaliser keeps only what we render and refuses what could not be rendered
 * safely.
 *
 * See docs/plans/tmdb-watch-providers.md#normaliser-pure.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Tmdb_Providers;

class TmdbProvidersTest extends TestCase {

	/**
	 * One provider as TMDB sends it.
	 *
	 * @param int    $id       provider_id.
	 * @param string $name     provider_name.
	 * @param int    $priority display_priority.
	 * @param string $logo     logo_path.
	 *
	 * @return array
	 */
	private function provider( int $id, string $name, int $priority = 0, string $logo = '/abc123.jpg' ): array {
		return array(
			'logo_path'        => $logo,
			'provider_id'      => $id,
			'provider_name'    => $name,
			'display_priority' => $priority,
		);
	}

	/**
	 * A response with the given countries.
	 *
	 * @param array $results Country => block.
	 *
	 * @return array
	 */
	private function response( array $results ): array {
		return array(
			'id'      => 126167,
			'results' => $results,
		);
	}

	private function us_block( array $flatrate = array(), array $free = array(), array $ads = array() ): array {
		return array(
			'link'     => 'https://www.themoviedb.org/tv/126167/watch?locale=US',
			'flatrate' => $flatrate,
			'free'     => $free,
			'ads'      => $ads,
		);
	}

	/*
	 * Normalising: things that are not an answer
	 */

	public function test_non_array_is_empty(): void {
		$this->assertSame( array(), Tmdb_Providers::normalise( false ) );
		$this->assertSame( array(), Tmdb_Providers::normalise( null ) );
		$this->assertSame( array(), Tmdb_Providers::normalise( 'nope' ) );
	}

	public function test_error_body_is_empty(): void {
		$this->assertSame(
			array(),
			Tmdb_Providers::normalise(
				array(
					'status_message' => 'Invalid API key',
					'status_code'    => 7,
				)
			)
		);
	}

	public function test_missing_or_bad_results_is_empty(): void {
		$this->assertSame( array(), Tmdb_Providers::normalise( array( 'id' => 1 ) ) );
		$this->assertSame(
			array(),
			Tmdb_Providers::normalise(
				array(
					'id'      => 1,
					'results' => 'x',
				)
			)
		);
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array() ) ) );
	}

	/*
	 * Normalising: shape
	 */

	public function test_keeps_id_name_and_logo_only(): void {
		$out = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( array( $this->provider( 8, 'Netflix', 0, '/netflix.jpg' ) ) ) ) ) );

		$this->assertSame(
			array(
				'id'   => 8,
				'name' => 'Netflix',
				'logo' => '/netflix.jpg',
			),
			$out['US']['flatrate'][0]
		);
	}

	public function test_every_country_has_all_five_type_lists(): void {
		$out = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( array( $this->provider( 8, 'Netflix' ) ) ) ) ) );

		foreach ( array( 'flatrate', 'free', 'ads', 'rent', 'buy' ) as $type ) {
			$this->assertArrayHasKey( $type, $out['US'] );
			$this->assertIsArray( $out['US'][ $type ] );
		}
	}

	public function test_keeps_the_country_link(): void {
		$out = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( array( $this->provider( 8, 'Netflix' ) ) ) ) ) );

		$this->assertSame( 'https://www.themoviedb.org/tv/126167/watch?locale=US', $out['US']['link'] );
	}

	public function test_country_codes_are_uppercased_and_sorted(): void {
		$block = $this->us_block( array( $this->provider( 8, 'Netflix' ) ) );
		$out   = Tmdb_Providers::normalise(
			$this->response(
				array(
					'gb' => $block,
					'CA' => $block,
				)
			)
		);

		$this->assertSame( array( 'CA', 'GB' ), array_keys( $out ) );
	}

	/*
	 * Normalising: what gets dropped
	 */

	public function test_country_with_no_providers_is_dropped(): void {
		$out = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block() ) ) );

		$this->assertSame( array(), $out );
	}

	public function test_malformed_country_code_is_dropped(): void {
		$block = $this->us_block( array( $this->provider( 8, 'Netflix' ) ) );
		$out   = Tmdb_Providers::normalise(
			$this->response(
				array(
					'USA' => $block,
					'1'   => $block,
					'U'   => $block,
				)
			)
		);

		$this->assertSame( array(), $out );
	}

	public function test_non_tmdb_link_drops_the_country(): void {
		$bad = $this->us_block( array( $this->provider( 8, 'Netflix' ) ) );

		$bad['link'] = 'https://evil.example/watch';
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array( 'US' => $bad ) ) ) );

		$bad['link'] = 'javascript:alert(1)';
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array( 'US' => $bad ) ) ) );

		$bad['link'] = 'http://www.themoviedb.org/tv/1/watch';
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array( 'US' => $bad ) ) ) );

		$bad['link'] = 'https://themoviedb.org.evil.example/tv/1/watch';
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array( 'US' => $bad ) ) ) );

		unset( $bad['link'] );
		$this->assertSame( array(), Tmdb_Providers::normalise( $this->response( array( 'US' => $bad ) ) ) );
	}

	public function test_provider_without_id_or_name_is_dropped(): void {
		$out = Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array(
							array( 'provider_name' => 'No ID' ),
							array(
								'provider_id'   => 0,
								'provider_name' => 'Zero',
							),
							array(
								'provider_id'   => 9,
								'provider_name' => '   ',
							),
							'not an array',
							$this->provider( 8, 'Netflix' ),
						)
					),
				)
			)
		);

		$this->assertCount( 1, $out['US']['flatrate'] );
		$this->assertSame( 8, $out['US']['flatrate'][0]['id'] );
	}

	public function test_bad_logo_path_becomes_empty_string(): void {
		$out = Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array(
							$this->provider( 1, 'A', 0, 'https://evil.example/x.jpg' ),
							$this->provider( 2, 'B', 1, '/../../x.jpg' ),
							$this->provider( 3, 'C', 2, '' ),
							$this->provider( 4, 'D', 3, '/ok_1-2.png' ),
						)
					),
				)
			)
		);

		$logos = array_column( $out['US']['flatrate'], 'logo', 'id' );

		$this->assertSame( '', $logos[1] );
		$this->assertSame( '', $logos[2] );
		$this->assertSame( '', $logos[3] );
		$this->assertSame( '/ok_1-2.png', $logos[4] );
	}

	/*
	 * Normalising: order and duplicates
	 */

	public function test_providers_are_ordered_by_display_priority(): void {
		$out = Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array(
							$this->provider( 3, 'Third', 20 ),
							$this->provider( 1, 'First', 1 ),
							$this->provider( 2, 'Second', 5 ),
						)
					),
				)
			)
		);

		$this->assertSame( array( 1, 2, 3 ), array_column( $out['US']['flatrate'], 'id' ) );
	}

	public function test_equal_or_missing_priority_keeps_response_order(): void {
		$no_priority = array(
			'provider_id'   => 7,
			'provider_name' => 'No Priority',
		);
		$out         = Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array(
							$no_priority,
							$this->provider( 5, 'Five', 0 ),
							$this->provider( 6, 'Six', 0 ),
						)
					),
				)
			)
		);

		// Missing priority sorts last; the two zeros keep the order TMDB sent.
		$this->assertSame( array( 5, 6, 7 ), array_column( $out['US']['flatrate'], 'id' ) );
	}

	public function test_duplicate_ids_within_a_list_collapse(): void {
		$out = Tmdb_Providers::normalise(
			$this->response( array( 'US' => $this->us_block( array( $this->provider( 8, 'Netflix', 0 ), $this->provider( 8, 'Netflix', 1 ) ) ) ) )
		);

		$this->assertCount( 1, $out['US']['flatrate'] );
	}

	public function test_names_are_trimmed(): void {
		$out = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( array( $this->provider( 8, '  Netflix ' ) ) ) ) ) );

		$this->assertSame( 'Netflix', $out['US']['flatrate'][0]['name'] );
	}


	/*
	 * Per-country reading: for_country, countries, default_country and payload
	 */

	private function two_countries(): array {
		return Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array( $this->provider( 8, 'Netflix', 0 ), $this->provider( 15, 'Hulu', 1 ) ),
						array(),
						array( $this->provider( 300, 'Pluto TV', 2 ), $this->provider( 8, 'Netflix', 3 ) )
					),
					'GB' => array(
						'link'     => 'https://www.themoviedb.org/tv/126167/watch?locale=GB',
						'flatrate' => array( $this->provider( 8, 'Netflix', 0 ), $this->provider( 39, 'Now TV', 1 ) ),
						'rent'     => array( $this->provider( 2, 'Apple TV', 0 ) ),
					),
					'DE' => array(
						'link' => 'https://www.themoviedb.org/tv/126167/watch?locale=DE',
						'buy'  => array( $this->provider( 2, 'Apple TV', 0 ) ),
					),
				)
			)
		);
	}

	private const SHOWN = array( 'flatrate', 'free', 'ads' );

	public function test_for_country_lists_the_displayed_types_in_type_order(): void {
		$list = Tmdb_Providers::for_country( $this->two_countries(), 'US', self::SHOWN );

		$this->assertSame( array( 8, 15, 300 ), array_column( $list, 'id' ) );
	}

	public function test_for_country_dedupes_a_provider_in_two_types(): void {
		// Netflix is flatrate and ads in the US fixture.
		$ids = array_column( Tmdb_Providers::for_country( $this->two_countries(), 'US', self::SHOWN ), 'id' );

		$this->assertSame( 1, count( array_keys( $ids, 8, true ) ) );
	}

	public function test_for_country_ignores_types_not_asked_for(): void {
		$this->assertSame( array( 8, 39 ), array_column( Tmdb_Providers::for_country( $this->two_countries(), 'GB', self::SHOWN ), 'id' ) );
		$this->assertSame( array( 8, 39, 2 ), array_column( Tmdb_Providers::for_country( $this->two_countries(), 'GB', array( 'flatrate', 'rent' ) ), 'id' ) );
	}

	public function test_for_country_is_case_insensitive_and_empty_when_unknown(): void {
		$this->assertSame( array( 8, 39 ), array_column( Tmdb_Providers::for_country( $this->two_countries(), 'gb', self::SHOWN ), 'id' ) );
		$this->assertSame( array(), Tmdb_Providers::for_country( $this->two_countries(), 'FR', self::SHOWN ) );
	}

	public function test_for_country_carries_name_and_logo(): void {
		$this->assertSame(
			array(
				'id'   => 8,
				'name' => 'Netflix',
				'logo' => '/abc123.jpg',
			),
			Tmdb_Providers::for_country( $this->two_countries(), 'US', self::SHOWN )[0]
		);
	}

	public function test_countries_are_those_with_a_displayed_provider(): void {
		// DE is buy-only, so it is stored but not offered.
		$this->assertSame( array( 'GB', 'US' ), Tmdb_Providers::countries( $this->two_countries(), self::SHOWN ) );
		$this->assertSame( array( 'DE', 'GB', 'US' ), Tmdb_Providers::countries( $this->two_countries(), array( 'flatrate', 'buy' ) ) );
		$this->assertSame( array(), Tmdb_Providers::countries( array(), self::SHOWN ) );
	}

	public function test_default_country_prefers_the_detected_one(): void {
		$this->assertSame( 'GB', Tmdb_Providers::default_country( $this->two_countries(), self::SHOWN, 'GB' ) );
		$this->assertSame( 'GB', Tmdb_Providers::default_country( $this->two_countries(), self::SHOWN, 'gb' ) );
	}

	public function test_default_country_falls_back_to_us(): void {
		$this->assertSame( 'US', Tmdb_Providers::default_country( $this->two_countries(), self::SHOWN, '' ) );
		// DE has nothing displayable, so a German visitor gets the US list.
		$this->assertSame( 'US', Tmdb_Providers::default_country( $this->two_countries(), self::SHOWN, 'DE' ) );
	}

	public function test_default_country_without_us_is_the_first_available(): void {
		$data = Tmdb_Providers::normalise(
			$this->response(
				array(
					'GB' => $this->us_block( array( $this->provider( 8, 'Netflix' ) ) ),
					'CA' => $this->us_block( array( $this->provider( 8, 'Netflix' ) ) ),
				)
			)
		);

		$this->assertSame( 'CA', Tmdb_Providers::default_country( $data, self::SHOWN, '' ) );
	}

	public function test_default_country_of_nothing_is_empty(): void {
		$this->assertSame( '', Tmdb_Providers::default_country( array(), self::SHOWN, 'US' ) );
	}

	public function test_payload_holds_each_provider_once(): void {
		$payload = Tmdb_Providers::payload( $this->two_countries(), self::SHOWN );

		// Apple TV is rent or buy everywhere, so it is not offered.
		$this->assertArrayNotHasKey( 2, $payload['providers'] );
		$this->assertSame(
			array(
				'name' => 'Netflix',
				'logo' => '/abc123.jpg',
			),
			$payload['providers'][8]
		);
		$this->assertCount( 4, $payload['providers'] );
	}

	public function test_payload_countries_carry_link_and_provider_ids(): void {
		$payload = Tmdb_Providers::payload( $this->two_countries(), self::SHOWN );

		$this->assertSame( array( 'GB', 'US' ), array_keys( $payload['countries'] ) );
		$this->assertSame( array( 8, 15, 300 ), $payload['countries']['US']['ids'] );
		$this->assertSame( 'https://www.themoviedb.org/tv/126167/watch?locale=GB', $payload['countries']['GB']['link'] );
	}

	public function test_payload_of_nothing_is_empty_shape(): void {
		$this->assertSame(
			array(
				'providers' => array(),
				'countries' => array(),
			),
			Tmdb_Providers::payload( array(), self::SHOWN )
		);
	}

	/*
	 * Real data: a trimmed /tv/1399/watch/providers response (Game of Thrones).
	 */

	private function fixture(): array {
		return Tmdb_Providers::normalise( json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/tmdb/watch-providers-1399.json' ), true ) );
	}

	public function test_fixture_keeps_every_country_with_a_slugged_link(): void {
		$data = $this->fixture();

		$this->assertSame( array( 'CA', 'ES', 'GB', 'JP', 'RU', 'US' ), array_keys( $data ) );
		$this->assertSame( 'https://www.themoviedb.org/tv/1399-game-of-thrones/watch?locale=US', $data['US']['link'] );
	}

	public function test_fixture_keeps_every_logo(): void {
		foreach ( $this->fixture() as $code => $country ) {
			foreach ( Tmdb_Providers::TYPES as $type ) {
				foreach ( $country[ $type ] as $provider ) {
					$this->assertNotSame( '', $provider['logo'], $code . ' ' . $provider['name'] );
				}
			}
		}
	}

	public function test_fixture_trims_provider_names(): void {
		$this->assertSame( 'Movistar Plus+ Ficción Total', $this->fixture()['ES']['flatrate'][0]['name'] );
	}

	public function test_fixture_ads_only_country_is_shown(): void {
		$this->assertContains( 'RU', Tmdb_Providers::countries( $this->fixture(), self::SHOWN ) );
	}

	public function test_variant_is_dropped_when_its_service_is_listed(): void {
		$names = array_column( Tmdb_Providers::for_country( $this->fixture(), 'US', self::SHOWN ), 'name' );

		$this->assertSame( array( 'HBO Max' ), $names );
	}

	public function test_variant_is_dropped_whichever_comes_first(): void {
		$names = array_column( Tmdb_Providers::for_country( $this->fixture(), 'CA', self::SHOWN ), 'name' );

		$this->assertSame( array( 'Crave' ), $names );
	}

	public function test_variant_is_kept_when_it_is_the_only_way_to_the_service(): void {
		$names = array_column( Tmdb_Providers::for_country( $this->fixture(), 'JP', self::SHOWN ), 'name' );

		$this->assertSame( array( 'HBO Max on U-Next' ), $names );
	}

	public function test_plan_variant_is_dropped_and_word_prefix_is_not_a_variant(): void {
		$data = Tmdb_Providers::normalise(
			$this->response(
				array(
					'US' => $this->us_block(
						array(
							$this->provider( 1796, 'Netflix basic with Ads', 1 ),
							$this->provider( 8, 'Netflix', 2 ),
							$this->provider( 9, 'Max', 3 ),
							$this->provider( 20, 'maxdome Store', 4 ),
						)
					),
				)
			)
		);

		$names = array_column( Tmdb_Providers::for_country( $data, 'US', self::SHOWN ), 'name' );

		$this->assertSame( array( 'Netflix', 'Max', 'maxdome Store' ), $names );
	}

	/**
	 * Names in one country's flatrate list, after for_country().
	 *
	 * @param array<int, string> $names Provider names, in TMDB's order.
	 *
	 * @return array<int, string>
	 */
	private function shown( array $names ): array {
		$list = array();
		foreach ( $names as $index => $name ) {
			$list[] = $this->provider( 100 + $index, $name, $index );
		}

		$data = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( $list ) ) ) );

		return array_column( Tmdb_Providers::for_country( $data, 'US', self::SHOWN ), 'name' );
	}

	public function test_resellers_of_an_unlisted_service_collapse_to_the_first(): void {
		// Doctor Who, US: no direct AMC+, three resellers, two spellings.
		$this->assertSame(
			array( 'AMC Plus Apple TV channel', 'Kanopy' ),
			$this->shown( array( 'AMC Plus Apple TV channel', 'AMC+ Amazon Channel', 'Kanopy', 'AMC+ Roku Premium Channel' ) )
		);
	}

	public function test_direct_service_beats_resellers_under_another_spelling(): void {
		$this->assertSame(
			array( 'AMC+' ),
			$this->shown( array( 'AMC Plus Apple TV channel', 'AMC+', 'AMC+ Amazon Channel' ) )
		);
	}

	public function test_spellings_of_one_service_collapse(): void {
		$this->assertSame( array( 'Disney+' ), $this->shown( array( 'Disney+', 'Disney Plus' ) ) );
		$this->assertSame( array( 'AcornTV Amazon Channel' ), $this->shown( array( 'AcornTV Amazon Channel', 'Acorn TV Apple TV channel' ) ) );
		$this->assertSame( array( 'BritBox Amazon Channel' ), $this->shown( array( 'BritBox Amazon Channel', 'Britbox Apple TV channel' ) ) );
	}

	public function test_reseller_suffix_is_matched_whatever_its_case(): void {
		$this->assertSame( array( 'ARD Plus' ), $this->shown( array( 'ARD Plus Amazon channel', 'ARD Plus', 'ARD Plus Apple TV channel' ) ) );
	}

	public function test_services_named_channel_are_not_resellers(): void {
		$this->assertSame(
			array( 'The Roku Channel', 'Starz Roku Premium Channel', 'Plex Channel', 'FOD Channel Amazon Channel' ),
			$this->shown( array( 'The Roku Channel', 'Starz Roku Premium Channel', 'Plex Channel', 'FOD Channel Amazon Channel' ) )
		);
	}

	public function test_different_services_on_one_reseller_stay(): void {
		$this->assertSame(
			array( 'Shudder Amazon Channel', 'Starz Amazon Channel' ),
			$this->shown( array( 'Shudder Amazon Channel', 'Starz Amazon Channel' ) )
		);
	}

	/*
	 * The strip: what one country's strip shows.
	 */

	public function test_strip_caps_the_logos_and_counts_the_rest(): void {
		$list = array();
		for ( $i = 1; $i <= 11; $i++ ) {
			$list[] = $this->provider( $i, 'Service ' . $i, $i );
		}

		$data  = Tmdb_Providers::normalise( $this->response( array( 'US' => $this->us_block( $list ) ) ) );
		$strip = Tmdb_Providers::strip( $data, self::SHOWN, 'us', 8 );

		$this->assertSame( 'US', $strip['country'] );
		$this->assertSame( 'https://www.themoviedb.org/tv/126167/watch?locale=US', $strip['link'] );
		$this->assertCount( 8, $strip['shown'] );
		$this->assertSame( 'Service 1', $strip['shown'][0]['name'] );
		$this->assertSame( 3, $strip['more'] );
	}

	public function test_strip_under_the_cap_has_nothing_more(): void {
		$strip = Tmdb_Providers::strip( $this->fixture(), self::SHOWN, 'US', 8 );

		$this->assertSame( array( 'HBO Max' ), array_column( $strip['shown'], 'name' ) );
		$this->assertSame( 0, $strip['more'] );
	}

	public function test_strip_for_a_country_with_nothing_to_show_is_empty(): void {
		$this->assertSame( array(), Tmdb_Providers::strip( $this->fixture(), self::SHOWN, 'ZZ', 8 ) );
	}

	public function test_logo_url_is_built_from_the_base(): void {
		$this->assertSame( 'https://image.tmdb.org/t/p/w92/abc.png', Tmdb_Providers::logo_url( '/abc.png' ) );
		$this->assertSame( '', Tmdb_Providers::logo_url( '' ) );
	}
}
