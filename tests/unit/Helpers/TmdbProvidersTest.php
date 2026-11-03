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
	 * normalise(): things that are not an answer
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
	 * normalise the shape
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
	 * normalise(): what gets dropped
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
	 * normalise(): order and duplicates
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

		$out = Tmdb_Providers::normalise(
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
	 * Per-country reading. Used by`for_country()`, `countries()`, `default_country()`, and `payload()`
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
}
