<?php
/**
 * Unit tests for the actor death rules.
 *
 * The birth-date conflict cases carry the weight here. Every other verdict is a
 * bookkeeping distinction, but that one is the only thing standing between a
 * wrong Q-ID and the site telling readers a living actor has died -- so the
 * tests below pin both directions of it: a real contradiction must be caught,
 * and a coarse WikiData date must NOT be mistaken for one.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Actor_Death_Rules;

class ActorDeathRulesTest extends TestCase {

	/**
	 * A living actor we hold complete data for, resolved by stored Q-ID.
	 *
	 * @param  array $item Values to replace.
	 * @return array
	 */
	private function actor( array $item = array() ): array {
		return array_merge(
			array(
				'our_death'  => '',
				'our_birth'  => '19760525',
				'qid'        => 'Q12345',
				'source'     => 'meta',
				'fetched'    => true,
				'wiki_death' => '',
				'wiki_birth' => '1976-05-25',
			),
			$item
		);
	}

	/**
	 * @param  array $item Collected actor data.
	 * @return string
	 */
	private function verdict( array $item ): string {
		return Actor_Death_Rules::evaluate( $item )['verdict'];
	}

	/*
	 * evaluate() -- verdicts
	 */

	public function test_an_actor_we_already_have_a_death_date_for_is_skipped(): void {
		$this->assertSame(
			Actor_Death_Rules::HAS_DATE,
			$this->verdict( $this->actor( array( 'our_death' => '20240101' ) ) )
		);
	}

	public function test_a_stored_death_date_short_circuits_before_anything_else(): void {
		// Everything downstream is broken or contradictory; none of it should
		// matter, because we are not looking this actor up at all.
		$this->assertSame(
			Actor_Death_Rules::HAS_DATE,
			$this->verdict(
				$this->actor(
					array(
						'our_death'  => '20240101',
						'qid'        => '',
						'source'     => 'imdb-ambiguous',
						'fetched'    => false,
						'wiki_birth' => '1900-01-01',
					)
				)
			)
		);
	}

	public function test_a_whitespace_only_death_date_does_not_count_as_having_one(): void {
		$this->assertSame(
			Actor_Death_Rules::FOUND,
			$this->verdict(
				$this->actor(
					array(
						'our_death'  => '   ',
						'wiki_death' => '2024-01-01',
					)
				)
			)
		);
	}

	public function test_no_death_claim_on_wikidata_means_alive(): void {
		$this->assertSame( Actor_Death_Rules::ALIVE, $this->verdict( $this->actor() ) );
	}

	public function test_a_death_claim_we_lack_is_found(): void {
		$result = Actor_Death_Rules::evaluate( $this->actor( array( 'wiki_death' => '2024-03-11' ) ) );

		$this->assertSame( Actor_Death_Rules::FOUND, $result['verdict'] );
		$this->assertSame( '2024-03-11', $result['death'] );
		$this->assertNotSame( '', $result['action'] );
	}

	public function test_an_unresolvable_actor_is_not_reported_as_alive(): void {
		$this->assertSame(
			Actor_Death_Rules::NO_IDENTITY,
			$this->verdict(
				$this->actor(
					array(
						'qid'    => '',
						'source' => '',
					)
				)
			)
		);
	}

	public function test_an_imdb_id_matching_several_items_is_ambiguous_not_missing(): void {
		$this->assertSame(
			Actor_Death_Rules::AMBIGUOUS,
			$this->verdict(
				$this->actor(
					array(
						'qid'    => '',
						'source' => 'imdb-ambiguous',
					)
				)
			)
		);
	}

	public function test_a_failed_entity_fetch_is_its_own_verdict(): void {
		$this->assertSame(
			Actor_Death_Rules::NO_DATA,
			$this->verdict( $this->actor( array( 'fetched' => false ) ) )
		);
	}

	public function test_a_contradicting_birth_date_suppresses_the_death_claim(): void {
		$result = Actor_Death_Rules::evaluate(
			$this->actor(
				array(
					'wiki_death' => '2024-03-11',
					'wiki_birth' => '1952-11-02',
				)
			)
		);

		$this->assertSame( Actor_Death_Rules::SUSPECT, $result['verdict'] );
		// The date still travels, so the human can see what we declined to trust.
		$this->assertSame( '2024-03-11', $result['death'] );
	}

	public function test_a_verdict_with_no_death_claim_carries_no_date(): void {
		$this->assertSame( '', Actor_Death_Rules::evaluate( $this->actor() )['death'] );
	}

	/*
	 * evaluate() -- reporting
	 */

	public function test_correct_data_is_never_reportable(): void {
		$this->assertFalse( Actor_Death_Rules::is_reportable( Actor_Death_Rules::HAS_DATE ) );
		$this->assertFalse( Actor_Death_Rules::is_reportable( Actor_Death_Rules::ALIVE ) );
	}

	public function test_every_problem_verdict_is_reportable_with_an_action(): void {
		foreach ( array( Actor_Death_Rules::FOUND, Actor_Death_Rules::SUSPECT, Actor_Death_Rules::AMBIGUOUS, Actor_Death_Rules::NO_IDENTITY, Actor_Death_Rules::NO_DATA ) as $verdict ) {
			$this->assertTrue( Actor_Death_Rules::is_reportable( $verdict ), $verdict . ' should be reportable' );
			$this->assertNotSame( '', Actor_Death_Rules::REPORTABLE[ $verdict ], $verdict . ' should have an action' );
		}
	}

	public function test_only_metadata_gaps_count_as_unresolved(): void {
		$this->assertTrue( Actor_Death_Rules::is_unresolved( Actor_Death_Rules::NO_IDENTITY ) );
		$this->assertTrue( Actor_Death_Rules::is_unresolved( Actor_Death_Rules::AMBIGUOUS ) );
		$this->assertTrue( Actor_Death_Rules::is_unresolved( Actor_Death_Rules::NO_DATA ) );

		// These two are findings about a person, not about our metadata, so they
		// must never be hidden behind --unresolved.
		$this->assertFalse( Actor_Death_Rules::is_unresolved( Actor_Death_Rules::FOUND ) );
		$this->assertFalse( Actor_Death_Rules::is_unresolved( Actor_Death_Rules::SUSPECT ) );
	}

	/*
	 * birth_dates_conflict()
	 */

	public function test_the_same_date_in_our_format_and_wikidatas_does_not_conflict(): void {
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '19760525', '1976-05-25' ) );
	}

	public function test_different_dates_conflict(): void {
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '19760525', '1952-11-02' ) );
	}

	public function test_a_different_year_alone_conflicts(): void {
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '19760525', '1977-05-25' ) );
	}

	public function test_a_different_day_alone_conflicts(): void {
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '19760525', '1976-05-26' ) );
	}

	public function test_a_year_only_wikidata_date_agreeing_on_the_year_does_not_conflict(): void {
		// WikiData's year-precision form. Treating this as a mismatch would
		// suppress real death findings, which is the expensive direction.
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '19760525', '1976-00-00' ) );
	}

	public function test_a_year_only_wikidata_date_disagreeing_on_the_year_conflicts(): void {
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '19760525', '1952-00-00' ) );
	}

	public function test_a_month_precision_date_agreeing_so_far_does_not_conflict(): void {
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '19760525', '1976-05-00' ) );
	}

	public function test_a_month_precision_date_disagreeing_conflicts(): void {
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '19760525', '1976-11-00' ) );
	}

	public function test_a_missing_birth_date_on_either_side_is_not_a_conflict(): void {
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '', '1976-05-25' ) );
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '19760525', '' ) );
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '', '' ) );
	}

	public function test_an_unparseable_date_is_not_a_conflict(): void {
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( 'unknown', '1976-05-25' ) );
	}

	public function test_an_unconverted_migration_date_compares_correctly(): void {
		// m/d/Y rows the ACF migration left behind. Read as Ymd these would look
		// like the year 0525, and every one of them would false-positive.
		$this->assertFalse( Actor_Death_Rules::birth_dates_conflict( '5/25/1976', '1976-05-25' ) );
		$this->assertTrue( Actor_Death_Rules::birth_dates_conflict( '5/25/1976', '1952-11-02' ) );
	}

	/*
	 * date_parts()
	 */

	public function test_date_parts_reads_our_raw_acf_format(): void {
		$this->assertSame(
			array(
				'year'  => '1976',
				'month' => '05',
				'day'   => '25',
			),
			Actor_Death_Rules::date_parts( '19760525' )
		);
	}

	public function test_date_parts_reads_wikidatas_format(): void {
		$this->assertSame(
			array(
				'year'  => '1976',
				'month' => '05',
				'day'   => '25',
			),
			Actor_Death_Rules::date_parts( '1976-05-25' )
		);
	}

	public function test_date_parts_pads_a_single_digit_migration_date(): void {
		$this->assertSame(
			array(
				'year'  => '1976',
				'month' => '05',
				'day'   => '02',
			),
			Actor_Death_Rules::date_parts( '5/2/1976' )
		);
	}

	public function test_date_parts_blanks_unknown_components(): void {
		$this->assertSame(
			array(
				'year'  => '1976',
				'month' => '',
				'day'   => '',
			),
			Actor_Death_Rules::date_parts( '1976-00-00' )
		);
	}

	public function test_date_parts_accepts_a_bare_year(): void {
		$this->assertSame(
			array(
				'year'  => '1976',
				'month' => '',
				'day'   => '',
			),
			Actor_Death_Rules::date_parts( '1976' )
		);
	}

	public function test_date_parts_gives_up_cleanly_on_junk(): void {
		$empty = array(
			'year'  => '',
			'month' => '',
			'day'   => '',
		);

		$this->assertSame( $empty, Actor_Death_Rules::date_parts( '' ) );
		$this->assertSame( $empty, Actor_Death_Rules::date_parts( 'circa 1976' ) );
		$this->assertSame( $empty, Actor_Death_Rules::date_parts( '197' ) );
	}
}
