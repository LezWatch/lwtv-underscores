<?php
/**
 * Unit tests for the app catalogue show record.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use LWTV\Rest_API\Build\App_Show_Record;

class AppShowRecordTest extends TestCase {

	/**
	 * A raw show modelled on the sample "The Lantern Years", with overrides.
	 *
	 * @param array $overrides Raw keys to replace.
	 *
	 * @return array
	 */
	private function raw( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'           => 90001,
				'slug'         => 'the-lantern-years',
				'title'        => 'The Lantern Years',
				'url'          => 'https://lezwatchtv.com/show/the-lantern-years/',
				'start'        => '2019',
				'finish'       => '2023',
				'on_air'       => 'no',
				'terms'        => array(
					'formats'   => array(
						401 => array(
							'slug' => 'tv-show',
							'name' => 'TV Show',
						),
					),
					'countries' => array(
						501 => array(
							'slug' => 'usa',
							'name' => 'USA',
						),
					),
					'stations'  => array(
						601 => array(
							'slug' => 'max',
							'name' => 'Max',
						),
					),
					'genres'    => array(
						301 => array(
							'slug' => 'drama',
							'name' => 'Drama',
						),
					),
					'tropes'    => array(
						102 => array(
							'slug' => 'law-enforcement',
							'name' => 'Law Enforcement',
						),
					),
				),
				'trigger_term' => 'low',
				'trigger_meta' => '',
				'star'         => 'gold',
				'score'        => '84.4',
				'tmdb_id'      => '210001',
				'similar'      => array( '90002' ),
			),
			$overrides
		);
	}

	public function test_full_record_matches_contract_shape(): void {
		$record = App_Show_Record::build( $this->raw() );

		$this->assertSame(
			array(
				'id',
				'slug',
				'title',
				'url',
				'year_start',
				'year_end',
				'on_air',
				'formats',
				'countries',
				'stations',
				'genres',
				'tropes',
				'queer_death',
				'trigger',
				'star',
				'lwtv_score',
				'viewer_score',
				'viewer_votes',
				'tmdb_id',
				'similar',
			),
			array_keys( $record )
		);
		$this->assertSame(
			array(
				'id'           => 90001,
				'slug'         => 'the-lantern-years',
				'title'        => 'The Lantern Years',
				'url'          => 'https://lezwatchtv.com/show/the-lantern-years/',
				'year_start'   => 2019,
				'year_end'     => 2023,
				'on_air'       => false,
				'formats'      => array( 401 ),
				'countries'    => array( 501 ),
				'stations'     => array( 601 ),
				'genres'       => array( 301 ),
				'tropes'       => array( 102 ),
				'queer_death'  => false,
				'trigger'      => 'low',
				'star'         => 'gold',
				'lwtv_score'   => 84,
				'viewer_score' => null,
				'viewer_votes' => null,
				'tmdb_id'      => 210001,
				'similar'      => array( 90002 ),
			),
			$record
		);
	}

	public function test_term_ids_are_sorted_ascending(): void {
		$terms           = $this->raw()['terms'];
		$terms['genres'] = array(
			9 => array(
				'slug' => 'b',
				'name' => 'B',
			),
			3 => array(
				'slug' => 'a',
				'name' => 'A',
			),
		);
		$record          = App_Show_Record::build( $this->raw( array( 'terms' => $terms ) ) );

		$this->assertSame( array( 3, 9 ), $record['genres'] );
	}

	public function test_trigger_term_beats_meta_and_legacy_aliases_normalise(): void {
		$this->assertSame( 'med', App_Show_Record::build( $this->raw( array( 'trigger_term' => 'medium' ) ) )['trigger'] );
		$this->assertSame(
			'low',
			App_Show_Record::build(
				$this->raw(
					array(
						'trigger_term' => 'low',
						'trigger_meta' => 'on',
					)
				)
			)['trigger']
		);
		$this->assertSame(
			'high',
			App_Show_Record::build(
				$this->raw(
					array(
						'trigger_term' => '',
						'trigger_meta' => 'on',
					)
				)
			)['trigger']
		);
		$this->assertNull(
			App_Show_Record::build(
				$this->raw(
					array(
						'trigger_term' => '',
						'trigger_meta' => '',
					)
				)
			)['trigger']
		);
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'trigger_term' => 'none' ) ) )['trigger'] );
		$this->assertNull(
			App_Show_Record::build(
				$this->raw(
					array(
						'trigger_term' => '',
						'trigger_meta' => 'none',
					)
				)
			)['trigger']
		);
	}

	public function test_year_end_null_while_on_air(): void {
		$record = App_Show_Record::build(
			$this->raw(
				array(
					'on_air' => 'yes',
					'finish' => '2026',
				)
			)
		);

		$this->assertTrue( $record['on_air'] );
		$this->assertNull( $record['year_end'] );
	}

	public function test_still_airing_and_unknown_years(): void {
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'finish' => 'current' ) ) )['year_end'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'start' => '' ) ) )['year_start'] );
		$this->assertSame( 2019, App_Show_Record::build( $this->raw( array( 'start' => '2019-05-01' ) ) )['year_start'] );
		$this->assertTrue( App_Show_Record::build( $this->raw( array( 'on_air' => 'yes' ) ) )['on_air'] );
		$this->assertFalse( App_Show_Record::build( $this->raw( array( 'on_air' => '' ) ) )['on_air'] );
		$this->assertFalse( App_Show_Record::build( $this->raw( array( 'on_air' => 'no' ) ) )['on_air'] );
	}

	public function test_star_outside_known_tiers_is_null(): void {
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'star' => 'platinum' ) ) )['star'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'star' => '' ) ) )['star'] );
		$this->assertSame( 'anti', App_Show_Record::build( $this->raw( array( 'star' => 'anti' ) ) )['star'] );
	}

	public function test_score_rounds_and_clamps_and_blank_is_null(): void {
		$this->assertSame( 85, App_Show_Record::build( $this->raw( array( 'score' => '84.5' ) ) )['lwtv_score'] );
		$this->assertSame( 0, App_Show_Record::build( $this->raw( array( 'score' => '-3' ) ) )['lwtv_score'] );
		$this->assertSame( 100, App_Show_Record::build( $this->raw( array( 'score' => '104' ) ) )['lwtv_score'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'score' => '' ) ) )['lwtv_score'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'score' => 'n/a' ) ) )['lwtv_score'] );
	}

	public function test_queer_death_from_trope_slug(): void {
		$terms           = $this->raw()['terms'];
		$terms['tropes'] = array(
			103 => array(
				'slug' => 'dead-queers',
				'name' => 'Dead Queers',
			),
		);
		$record          = App_Show_Record::build( $this->raw( array( 'terms' => $terms ) ) );

		$this->assertTrue( $record['queer_death'] );
		$this->assertSame( array( 103 ), $record['tropes'] );
	}

	public function test_similar_casts_dedupes_and_drops_self(): void {
		$record = App_Show_Record::build( $this->raw( array( 'similar' => array( '5', 5, 'x', 0, 90001 ) ) ) );
		$this->assertSame( array( 5 ), $record['similar'] );

		$this->assertSame( array(), App_Show_Record::build( $this->raw( array( 'similar' => '' ) ) )['similar'] );
		$this->assertSame( array(), App_Show_Record::build( $this->raw( array( 'similar' => 'nope' ) ) )['similar'] );
	}

	public function test_title_is_plain_text(): void {
		$record = App_Show_Record::build( $this->raw( array( 'title' => 'Gentleman Jack&#8217;s <b>Return</b>' ) ) );

		$this->assertSame( 'Gentleman Jack’s Return', $record['title'] );
	}

	public function test_tmdb_id_zero_or_blank_is_null(): void {
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'tmdb_id' => '0' ) ) )['tmdb_id'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'tmdb_id' => '' ) ) )['tmdb_id'] );
		$this->assertNull( App_Show_Record::build( $this->raw( array( 'tmdb_id' => null ) ) )['tmdb_id'] );
	}
}
