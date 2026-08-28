<?php
/**
 * Unit tests for the show rules — the layer that decides whether a show has a
 * problem. These were untestable until the WordPress reads moved into
 * Collect\Show_Collector, and two of the cases below are regressions for bugs
 * that shipped: the airdate check that only read a legacy meta key, and the
 * duplicate matcher that treated two missing IMDb IDs as a match.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Show_Rules;

class ShowRulesTest extends TestCase {

	/**
	 * A show with nothing wrong with it.
	 *
	 * @param  array $overrides Keys to replace.
	 * @return array
	 */
	private function show( array $overrides = array() ): array {
		$show = array(
			'post_id'            => 10,
			'slug'               => 'the-l-word',
			'meta'               => array(
				'lezshows_the_score'         => 90,
				'lezshows_char_count'        => 12,
				'lezshows_worthit_details'   => 'Yes.',
				'lezshows_worthit_rating'    => 'Yes',
				'lezshows_realness_rating'   => 3,
				'lezshows_quality_rating'    => 3,
				'lezshows_screentime_rating' => 3,
				'lezshows_imdb'              => 'tt0330251',
			),
			'terms'              => array(
				'lez_stations' => array( 'showtime' ),
				'lez_country'  => array( 'united-states' ),
				'lez_formats'  => array( 'television' ),
				'lez_genres'   => array( 'drama' ),
				'lez_tropes'   => array( 'none' ),
			),
			'airdates'           => array(
				'start'  => '2004',
				'finish' => '2009',
			),
			'duplicate'          => array(),
			'disabled_character' => null,
		);

		return array_merge( $show, $overrides );
	}

	/**
	 * Issue types from a rule result.
	 *
	 * @param  array $findings Findings.
	 * @return array<string>
	 */
	private function types( array $findings ): array {
		return array_column( $findings, 'issue_type' );
	}

	/*
	 * evaluate()
	 */

	public function test_a_complete_show_has_no_findings(): void {
		$this->assertSame( array(), Show_Rules::evaluate( $this->show() ) );
	}

	public function test_evaluate_ignores_a_show_with_no_id(): void {
		$this->assertSame( array(), Show_Rules::evaluate( $this->show( array( 'post_id' => 0 ) ) ) );
	}

	public function test_evaluate_collects_from_every_rule(): void {
		$show = $this->show(
			array(
				'meta'     => array(),
				'terms'    => array(),
				'airdates' => array(
					'start'  => '',
					'finish' => '',
				),
			)
		);

		$types = $this->types( Show_Rules::evaluate( $show ) );

		$this->assertContains( 'show-no-characters', $types );
		$this->assertContains( 'show-no-genres', $types );
		$this->assertContains( 'show-no-airdates', $types );
	}

	/*
	 * missing_fields()
	 */

	public function test_missing_meta_is_reported(): void {
		$show = $this->show();
		unset( $show['meta']['lezshows_worthit_rating'] );

		$this->assertSame( array( 'show-missing-thumb' ), $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	public function test_missing_terms_are_reported(): void {
		$show = $this->show();
		unset( $show['terms']['lez_tropes'], $show['terms']['lez_genres'] );

		$this->assertSame(
			array( 'show-no-genres', 'show-missing-trope' ),
			$this->types( Show_Rules::missing_fields( $show ) )
		);
	}

	public function test_empty_ok_fields_are_not_reported(): void {
		$show = $this->show();
		unset(
			$show['meta']['lezshows_the_score'],
			$show['meta']['lezshows_worthit_details'],
			$show['meta']['lezshows_realness_rating'],
			$show['meta']['lezshows_quality_rating'],
			$show['meta']['lezshows_screentime_rating']
		);

		$this->assertSame( array(), $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	public function test_skipped_fields_are_not_reported_here(): void {
		// The IMDb ID has its own check, and the duplicate rule needs the value.
		$show = $this->show();
		unset( $show['meta']['lezshows_imdb'] );

		$this->assertNotContains( 'show-no-imdb', $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	/*
	 * acknowledged_by — an editor has ruled on it.
	 */

	public function test_the_no_known_characters_flag_silences_the_finding(): void {
		$show = $this->show(
			array(
				'meta' => array(
					'lezshows_char_count' => '0',
					'lezshows_no_chars'   => 1,
				) + $this->show()['meta'],
			)
		);

		$this->assertNotContains( 'show-no-characters', $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	public function test_without_the_flag_it_is_still_reported(): void {
		$show = $this->show(
			array(
				'meta' => array(
					'lezshows_char_count' => '0',
					'lezshows_no_chars'   => 0,
				) + $this->show()['meta'],
			)
		);

		$this->assertContains( 'show-no-characters', $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	public function test_the_flag_silences_nothing_else(): void {
		// Acknowledging one problem must not acknowledge the rest of the row.
		$show = $this->show(
			array(
				'meta'  => array(
					'lezshows_char_count' => '0',
					'lezshows_no_chars'   => 1,
				),
				'terms' => array(),
			)
		);

		$types = $this->types( Show_Rules::missing_fields( $show ) );

		$this->assertNotContains( 'show-no-characters', $types );
		$this->assertContains( 'show-no-genres', $types );
		$this->assertContains( 'show-missing-thumb', $types );
	}

	public function test_the_acknowledgement_flag_is_collected(): void {
		// The rule cannot see the ruling if the collector never fetches it.
		$this->assertContains( Show_Rules::META_NO_CHARS, Show_Rules::meta_keys() );
	}

	public function test_a_zero_character_count_is_reported(): void {
		// lezshows_char_count comes back as the string '0', and empty( '0' ) is
		// true in PHP. That reads like an accident and is deliberate.
		$show = $this->show( array( 'meta' => array( 'lezshows_char_count' => '0' ) + $this->show()['meta'] ) );

		$this->assertContains( 'show-no-characters', $this->types( Show_Rules::missing_fields( $show ) ) );
	}

	/*
	 * airdates()
	 */

	public function test_no_airdates_at_all_reports_once(): void {
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '',
					'finish' => '',
				),
			)
		);

		$this->assertSame( array( 'show-no-airdates' ), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_a_missing_start_date_is_reported(): void {
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '',
					'finish' => '2009',
				),
			)
		);

		$this->assertSame( array( 'show-no-start-date' ), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_a_missing_end_date_stops_further_comparison(): void {
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '2004',
					'finish' => '',
				),
			)
		);

		$this->assertSame( array( 'show-no-end-date' ), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_a_still_airing_show_is_not_compared(): void {
		// 'current' is not a year, so there is nothing to compare against.
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '2004',
					'finish' => 'current',
				),
			)
		);

		$this->assertSame( array(), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_an_inverted_range_is_reported(): void {
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '2009',
					'finish' => '2004',
				),
			)
		);

		$this->assertSame( array( 'show-airdate-inverted' ), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_a_one_year_show_is_fine(): void {
		// TV movies start and end in the same year.
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '2004',
					'finish' => '2004',
				),
			)
		);

		$this->assertSame( array(), $this->types( Show_Rules::airdates( $show ) ) );
	}

	public function test_migrated_airdates_are_read(): void {
		/*
		 * The 1.1 regression. The check used to read only the legacy serialised
		 * key, so a show migrated to lezshows_airdates_start/_finish reported
		 * "No airdates." and the end-date rules never ran. Reading is the
		 * collector's job now; what this pins is that the rules judge whatever
		 * the collector hands over, with no key knowledge of their own.
		 */
		$show = $this->show(
			array(
				'airdates' => array(
					'start'  => '2004',
					'finish' => '2009',
				),
			)
		);

		$this->assertSame( array(), $this->types( Show_Rules::airdates( $show ) ) );
	}

	/*
	 * numeric_suffix() / base_slug()
	 */

	public function test_numeric_suffix_is_found(): void {
		$this->assertSame( '2', Show_Rules::numeric_suffix( 'the-l-word-2' ) );
		$this->assertSame( '', Show_Rules::numeric_suffix( 'the-l-word' ) );
	}

	public function test_a_number_named_show_looks_suffixed(): void {
		// 90210 is a real show name. Detecting the suffix only starts the check.
		$this->assertSame( '90210', Show_Rules::numeric_suffix( '90210' ) );
	}

	public function test_base_slug_strips_the_suffix(): void {
		$this->assertSame( 'the-l-word', Show_Rules::base_slug( 'the-l-word-2' ) );
		$this->assertSame( '', Show_Rules::base_slug( 'the-l-word' ) );
	}

	/*
	 * duplicate()
	 */

	public function test_no_candidate_means_no_finding(): void {
		$this->assertSame( array(), Show_Rules::duplicate( $this->show() ) );
	}

	public function test_a_matching_imdb_id_is_a_likely_dupe(): void {
		$show = $this->show(
			array(
				'slug'      => 'the-l-word-2',
				'duplicate' => array(
					'id'   => 99,
					'imdb' => 'tt0330251',
				),
			)
		);

		$this->assertSame( array( 'show-duplicate' ), $this->types( Show_Rules::duplicate( $show ) ) );
	}

	public function test_two_missing_imdb_ids_are_not_a_match(): void {
		/*
		 * The 1.9b-era bug: the old test was isset() on a value that was always
		 * set, so every numerically-suffixed show with no IMDb ID matched any
		 * same-named show that also had none.
		 */
		$show = $this->show(
			array(
				'slug'      => 'the-l-word-2',
				'meta'      => array( 'lezshows_imdb' => '' ),
				'duplicate' => array(
					'id'   => 99,
					'imdb' => '',
				),
			)
		);

		$this->assertSame( array(), Show_Rules::duplicate( $show ) );
	}

	public function test_different_imdb_ids_are_not_a_match(): void {
		$show = $this->show(
			array(
				'slug'      => 'the-l-word-2',
				'duplicate' => array(
					'id'   => 99,
					'imdb' => 'tt9999999',
				),
			)
		);

		$this->assertSame( array(), Show_Rules::duplicate( $show ) );
	}

	public function test_a_show_finding_itself_is_not_a_dupe(): void {
		// The 90210 loop.
		$show = $this->show(
			array(
				'slug'      => '90210',
				'duplicate' => array(
					'id'   => 10,
					'imdb' => 'tt0330251',
				),
			)
		);

		$this->assertSame( array(), Show_Rules::duplicate( $show ) );
	}

	/*
	 * intersections()
	 */

	public function test_a_disabled_show_with_no_disabled_character_is_reported(): void {
		$show = $this->show(
			array(
				'terms'              => array( 'lez_intersections' => array( 'disabled' ) ) + $this->show()['terms'],
				'disabled_character' => false,
			)
		);

		$this->assertSame( array( 'show-intersection' ), $this->types( Show_Rules::intersections( $show ) ) );
	}

	public function test_a_disabled_show_with_a_disabled_character_is_fine(): void {
		$show = $this->show(
			array(
				'terms'              => array( 'lez_intersections' => array( 'disabled' ) ) + $this->show()['terms'],
				'disabled_character' => true,
			)
		);

		$this->assertSame( array(), Show_Rules::intersections( $show ) );
	}

	public function test_other_intersections_are_not_cross_checked(): void {
		// Only 'disabled' has a matching character-level term to check against.
		$show = $this->show(
			array(
				'terms'              => array( 'lez_intersections' => array( 'transgender' ) ) + $this->show()['terms'],
				'disabled_character' => null,
			)
		);

		$this->assertSame( array(), Show_Rules::intersections( $show ) );
	}

	public function test_an_unchecked_show_is_not_reported(): void {
		// null means the collector had no reason to look, which is not the same
		// as having looked and found nothing.
		$show = $this->show(
			array(
				'terms'              => array( 'lez_intersections' => array( 'disabled' ) ) + $this->show()['terms'],
				'disabled_character' => null,
			)
		);

		$this->assertSame( array(), Show_Rules::intersections( $show ) );
	}

	/*
	 * The collector contract.
	 */

	public function test_meta_keys_and_taxonomies_come_from_the_checks(): void {
		$this->assertContains( 'lezshows_worthit_rating', Show_Rules::meta_keys() );
		$this->assertContains( 'lez_tropes', Show_Rules::taxonomies() );
	}

	public function test_taxonomies_include_intersections(): void {
		// No CHECKS entry, because an empty one is not a problem -- but the
		// cross-check cannot run without it.
		$this->assertContains( Show_Rules::INTERSECTIONS, Show_Rules::taxonomies() );
	}

	public function test_every_check_names_a_registered_issue(): void {
		foreach ( Show_Rules::CHECKS as $key => $check ) {
			$this->assertArrayHasKey( 'issue', $check, $key );
			$this->assertTrue(
				\LWTV\Debugger\Build\Issue_Registry::exists( $check['issue'] ),
				$check['issue'] . ' is not in the registry'
			);
		}
	}
}
