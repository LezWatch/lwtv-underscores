<?php
/**
 * Unit tests for the longevity weighting maths: turning a show's TVMaze season
 * dates into the set of years it actually aired, turning a character's
 * `appears` years into a 0-1 weight against that run, and the saturating curve
 * that replaces the old hard clamp at 100.
 *
 * The point of the model is that headcount stops driving a show's character
 * score. A 50-year soap that cycled through 200 one-episode characters should
 * not outrank a tightly-written five-season drama, and a show should never be
 * penalised for documenting a minor character. The tests below pin both of
 * those properties directly.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Scoring\Longevity;

class ShowLongevityTest extends TestCase {

	/**
	 * Arrested Development, TVMaze show 321 — the reference case.
	 *
	 * Chosen because one show exercises every hard edge at once: a season that
	 * straddles two calendar years, a single-day Netflix drop, and a seven-year
	 * revival gap.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function arrested_development_seasons(): array {
		return array(
			array( 'premiereDate' => '2003-11-02', 'endDate' => '2004-06-06' ),
			array( 'premiereDate' => '2004-11-07', 'endDate' => '2005-04-17' ),
			array( 'premiereDate' => '2005-09-19', 'endDate' => '2006-02-10' ),
			array( 'premiereDate' => '2013-05-26', 'endDate' => '2013-05-26' ),
			array( 'premiereDate' => '2018-05-29', 'endDate' => '2019-03-15' ),
		);
	}

	/*
	 * aired_years_from_seasons()
	 */

	public function test_aired_years_unions_every_season_range(): void {
		$out = Longevity::aired_years_from_seasons( $this->arrested_development_seasons(), 2026 );

		$this->assertSame( array( 2003, 2004, 2005, 2006, 2013, 2018, 2019 ), $out );
	}

	public function test_aired_years_is_the_whole_point_of_this_change(): void {
		// The airdate span says 17 years (2019 - 2003 + 1). The season count
		// says 5. The show actually aired in 7 calendar years. Every character's
		// `share` divides by this number, so being wrong here is wrong everywhere.
		$out = Longevity::aired_years_from_seasons( $this->arrested_development_seasons(), 2026 );

		$this->assertCount( 7, $out );
	}

	public function test_aired_years_counts_a_straddling_season_as_two_years(): void {
		// A US network season running Sept-May covers two calendar years. A
		// season count would record this as 1.
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2003-11-02', 'endDate' => '2004-06-06' ) ),
			2026
		);

		$this->assertSame( array( 2003, 2004 ), $out );
	}

	public function test_aired_years_counts_a_single_day_drop_as_one_year(): void {
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2013-05-26', 'endDate' => '2013-05-26' ) ),
			2026
		);

		$this->assertSame( array( 2013 ), $out );
	}

	public function test_aired_years_excludes_a_revival_gap(): void {
		// The gap years fall out of the union for free -- no hiatus field needed.
		$out = Longevity::aired_years_from_seasons(
			array(
				array( 'premiereDate' => '2001-01-01', 'endDate' => '2001-05-01' ),
				array( 'premiereDate' => '2016-01-01', 'endDate' => '2016-03-01' ),
			),
			2026
		);

		$this->assertSame( array( 2001, 2016 ), $out );
		$this->assertNotContains( 2008, $out );
	}

	public function test_aired_years_treats_a_null_end_date_as_still_airing(): void {
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2025-01-01', 'endDate' => null ) ),
			2026
		);

		$this->assertSame( array( 2025, 2026 ), $out );
	}

	public function test_aired_years_ignores_a_season_that_has_not_aired_yet(): void {
		// An announced-but-unaired season must not inflate the denominator.
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2030-01-01', 'endDate' => null ) ),
			2026
		);

		$this->assertSame( array(), $out );
	}

	public function test_aired_years_skips_a_season_with_no_premiere_date(): void {
		$out = Longevity::aired_years_from_seasons(
			array(
				array( 'premiereDate' => null, 'endDate' => '2004-06-06' ),
				array( 'premiereDate' => '2005-01-01', 'endDate' => '2005-06-01' ),
			),
			2026
		);

		$this->assertSame( array( 2005 ), $out );
	}

	public function test_aired_years_clamps_a_far_future_end_date(): void {
		// Bad data must not generate 70 years of run length.
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2024-01-01', 'endDate' => '2099-01-01' ) ),
			2026
		);

		$this->assertSame( array( 2024, 2025, 2026 ), $out );
	}

	public function test_aired_years_survives_an_end_date_before_the_premiere(): void {
		$out = Longevity::aired_years_from_seasons(
			array( array( 'premiereDate' => '2010-01-01', 'endDate' => '2008-01-01' ) ),
			2026
		);

		$this->assertSame( array( 2010 ), $out );
	}

	public function test_aired_years_returns_empty_for_no_seasons(): void {
		$this->assertSame( array(), Longevity::aired_years_from_seasons( array(), 2026 ) );
	}

	/*
	 * usable_aired_years() - the plausibility guard on tier 2
	 *
	 * TVMaze's season coverage is patchy for long-running shows, so one can come
	 * back with only a handful of its years dated. That is worse than useless: a
	 * short aired-years set shrinks the denominator (raising every weight) AND
	 * gets intersected against each character's `appears`, silently discarding
	 * real screen time. Measured on the live data, 13 shows had their denominator
	 * shrink while mean character weight also fell -- only the intersection can
	 * do that.
	 *
	 * Three signals: the season count (signal 1), a late start (signal 2), and
	 * whether the set can account for the years characters are credited in
	 * (signal 3). Signals 1 and 2 together caught only 6 of those 13 shows, which
	 * is why signal 3 exists -- see the discrimination tests below.
	 */

	public function test_a_complete_aired_set_is_used_as_is(): void {
		$aired = range( 2014, 2019 );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 6, '2014' ) );
	}

	public function test_a_revival_gap_is_kept(): void {
		// The case tier 2 exists for. The X-Files: the set starts at the recorded
		// start year and the hole is in the middle, which is real.
		$aired = array_merge( range( 1993, 2002 ), array( 2016, 2018 ) );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 0, '1993' ) );
	}

	public function test_a_set_that_starts_far_too_late_is_rejected(): void {
		// A show recorded from 1992 whose only dated years are recent: every
		// pre-2018 appearance would be thrown away by the intersection.
		//
		// This was written expecting it to describe Gute Zeiten, schlechte Zeiten.
		// It does not -- GZSZ has 9 dated years but they START at the premiere,
		// with the holes in the middle and end, so signal 2 never fires on it.
		// The shape is real and worth rejecting, it is just rarer than assumed.
		// GZSZ is covered by signal 3 instead, below.
		$aired = range( 2018, 2026 );

		$this->assertSame( array(), Longevity::usable_aired_years( $aired, 0, '1992' ) );
	}

	public function test_one_year_of_slack_on_the_start_is_allowed(): void {
		// A December premiere recorded as the following year, or an airdate that
		// is off by one, must not throw the whole set away.
		$aired = range( 2015, 2020 );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 0, '2014' ) );
	}

	public function test_fewer_aired_years_than_seasons_is_rejected(): void {
		// Fair City: 28 seasons recorded, TVMaze dated 16 years. Two seasons in
		// one calendar year happens, but essentially only in reality TV, which
		// this site does not cover -- so at this scale it means missing seasons.
		$aired = range( 2010, 2025 );

		$this->assertSame( array(), Longevity::usable_aired_years( $aired, 28, '2010' ) );
	}

	public function test_one_season_straddling_a_year_boundary_is_allowed(): void {
		// 6 seasons across 5 calendar years is ordinary Sept-May scheduling.
		$aired = range( 2015, 2019 );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 6, '2015' ) );
	}

	public function test_an_empty_set_stays_empty(): void {
		$this->assertSame( array(), Longevity::usable_aired_years( array(), 5, '2014' ) );
	}

	public function test_an_unparseable_start_skips_the_start_check(): void {
		// No start year to compare against, so only the seasons signal applies.
		$aired = range( 2018, 2026 );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 0, '' ) );
	}

	public function test_the_guard_makes_run_years_fall_through(): void {
		// The whole point: a rejected set must take the denominator with it, so
		// the span is used instead of a badly truncated count.
		$aired = Longevity::usable_aired_years( range( 2018, 2026 ), 0, '1992' );

		$this->assertSame( 35, Longevity::run_years( $aired, 0, '1992', '2026', 2026 ) );
	}

	public function test_the_guard_also_protects_character_years(): void {
		// And it must take the intersection with it. A character credited from
		// 1995 keeps those years rather than having them discarded.
		$aired = Longevity::usable_aired_years( range( 2018, 2026 ), 0, '1992' );

		$this->assertSame( 3, Longevity::character_years( array( 1995, 1996, 1997 ), $aired ) );
	}

	/*
	 * Signal 3 - appearance coverage
	 *
	 * The discrimination that signals 1 and 2 cannot make. Both a revival gap and
	 * a data gap produce a set with holes in it; what separates them is whether
	 * characters were on screen inside the holes. A revival gap is empty there
	 * because the show was not airing. A data gap is populated, and that is proof
	 * the show WAS airing and TVMaze simply has no season dated for it.
	 */

	public function test_a_revival_gap_survives_the_coverage_check(): void {
		// The X-Files. The set has an 13-year hole, but no character is credited
		// inside it, because nothing aired then. The set is right.
		$aired    = array_merge( range( 1993, 2002 ), array( 2016, 2018 ) );
		$credited = array_merge( range( 1993, 2002 ), array( 2016, 2018 ) );

		$this->assertSame(
			$aired,
			Longevity::usable_aired_years( $aired, 0, '1993', $credited )
		);
	}

	public function test_a_middle_gap_with_appearances_in_it_is_rejected(): void {
		// Gute Zeiten, schlechte Zeiten. Dated years start at the premiere so
		// signal 2 abstains, and there is no season count so signal 1 abstains --
		// but characters are credited across the whole 35-year span, most of it in
		// years the set does not contain. That is the set being wrong, not the
		// characters.
		$aired    = array( 1992, 1993, 1994, 2021, 2022, 2023, 2024, 2025, 2026 );
		$credited = range( 1995, 2020 );

		$this->assertSame(
			array(),
			Longevity::usable_aired_years( $aired, 0, '1992', $credited )
		);
	}

	public function test_a_single_stray_credited_year_does_not_reject_a_set(): void {
		// One `appears` typo is a data-entry slip, not evidence the set is short.
		// 4 of 5 credited years inside = 0.80, above the threshold.
		$aired    = range( 2015, 2019 );
		$credited = array( 2015, 2016, 2017, 2018, 1998 );

		$this->assertSame(
			$aired,
			Longevity::usable_aired_years( $aired, 0, '2015', $credited )
		);
	}

	public function test_coverage_abstains_below_the_evidence_floor(): void {
		// Two credited years, one outside. That is 0.50 coverage, but on evidence
		// this thin a bad set and a bad year are indistinguishable, so the signal
		// must not fire. Small shows are exactly where a false rejection hurts.
		$aired    = range( 2015, 2019 );
		$credited = array( 2016, 1998 );

		$this->assertSame(
			$aired,
			Longevity::usable_aired_years( $aired, 0, '2015', $credited )
		);
	}

	public function test_coverage_is_skipped_when_no_credited_years_are_supplied(): void {
		// A caller that has not gathered characters gets signals 1 and 2 only.
		// It must not read as zero coverage.
		$aired = range( 2015, 2019 );

		$this->assertSame( $aired, Longevity::usable_aired_years( $aired, 0, '2015' ) );
	}

	public function test_appearance_coverage_measures_the_union_not_the_tally(): void {
		// A year credited to six characters counts once, so one long-serving
		// regular cannot swamp the measurement.
		$aired    = array( 2015, 2016 );
		$credited = array( 2015, 2015, 2015, 2015, 2016, 2017 );

		$this->assertSame( 2 / 3, Longevity::appearance_coverage( $aired, $credited ) );
	}

	public function test_appearance_coverage_of_nothing_is_total(): void {
		// Nothing to explain is not the same as failing to explain something.
		$this->assertSame( 1.0, Longevity::appearance_coverage( range( 2015, 2019 ), array() ) );
		$this->assertSame( 1.0, Longevity::appearance_coverage( array(), array( 0, 0 ) ) );
	}

	public function test_appearance_coverage_of_a_disjoint_set_is_zero(): void {
		$this->assertSame( 0.0, Longevity::appearance_coverage( range( 2020, 2026 ), range( 1995, 2005 ) ) );
	}

	public function test_the_coverage_verdict_is_reported_distinctly(): void {
		// Each signal has to be attributable, or the CLI cannot report which one
		// fired and the threshold cannot be calibrated against the corpus.
		$this->assertSame(
			Longevity::VERDICT_SEASONS,
			Longevity::aired_years_verdict( range( 2010, 2025 ), 28, '2010' )
		);
		$this->assertSame(
			Longevity::VERDICT_LATE_START,
			Longevity::aired_years_verdict( range( 2018, 2026 ), 0, '1992' )
		);
		$this->assertSame(
			Longevity::VERDICT_COVERAGE,
			Longevity::aired_years_verdict( array( 1992, 1993, 1994 ), 0, '1992', range( 1995, 2020 ) )
		);
		$this->assertSame(
			Longevity::VERDICT_OK,
			Longevity::aired_years_verdict( range( 2015, 2019 ), 0, '2015', range( 2015, 2019 ) )
		);
		$this->assertSame(
			Longevity::VERDICT_NONE,
			Longevity::aired_years_verdict( array(), 0, '2015' )
		);
	}

	public function test_coverage_rejection_recovers_the_character_years(): void {
		// The end-to-end point of signal 3. Before it, a GZSZ character credited
		// across 2000-2010 kept none of those years, because the intersection
		// threw away every year TVMaze had not dated -- so a decade-long regular
		// scored as though they had never been on screen. Now the set is dropped
		// and the years survive.
		$aired    = array( 1992, 1993, 1994, 2021, 2022, 2023, 2024, 2025, 2026 );
		$credited = range( 1995, 2020 );
		$vetted   = Longevity::usable_aired_years( $aired, 0, '1992', $credited );

		$this->assertSame( 11, Longevity::character_years( range( 2000, 2010 ), $vetted ) );

		// And the denominator falls back to the span rather than the 9 dated
		// years, so that regular is measured against the show's real length.
		$this->assertSame( 35, Longevity::run_years( $vetted, 0, '1992', '2026', 2026 ) );
	}

	/*
	 * discarded_years() - a diagnostic, and two signals that did not survive
	 *
	 * These tests exist to stop the discarded ideas being rebuilt. Both were
	 * attempts to sharpen signal 3 by telling a real hiatus (set correct, loose
	 * `appears`) from a data gap (set incomplete), and both failed on evidence:
	 *
	 *  - Hole LOCATION carries no information; the first test below is the
	 *    measurement that killed it.
	 *  - Record SIZE is provably redundant. Whenever |C| > 1.5 x |A|, coverage is
	 *    at most |A|/|C| < 0.667, already under COVERAGE_MIN -- so signal 3 has
	 *    always rejected the set first. Below coverage's evidence floor, where it
	 *    could have added something, it qualifies zero of 304 shows.
	 *
	 * A COVERAGE_MIN test asserting the second property lives with the constants.
	 */

	public function test_coverage_min_makes_a_size_comparison_redundant(): void {
		// The algebra, asserted rather than trusted: a record more than
		// RATIO times richer than the set cannot have coverage above 1/RATIO,
		// so any threshold at or above that point rejects it on coverage alone.
		// This is why there is no size-comparison signal.
		$ratio = 1.5;

		$this->assertGreaterThan(
			1 / $ratio,
			Longevity::COVERAGE_MIN,
			'A size-comparison signal would be reachable, and should be reconsidered.'
		);

		// And a worked instance of it.
		$aired    = range( 2001, 2010 );
		$credited = range( 2001, 2026 );

		$this->assertGreaterThan( $ratio, count( $credited ) / count( $aired ) );
		$this->assertLessThan( Longevity::COVERAGE_MIN, Longevity::appearance_coverage( $aired, $credited ) );
		$this->assertSame(
			Longevity::VERDICT_COVERAGE,
			Longevity::aired_years_verdict( $aired, 0, '2001', $credited )
		);
	}

	public function test_hole_location_cannot_tell_the_two_cases_apart(): void {
		// The measurement that killed the internal-hole signal, kept as a test so
		// nobody rebuilds it. A bad set and a good set, indistinguishable: both
		// put every discarded year inside a hole and none outside the range.
		$gzsz = Longevity::discarded_years(
			array( 1992, 1993, 1994, 2021, 2022, 2023, 2024, 2025, 2026 ),
			range( 1995, 2020 )
		);
		$rick = Longevity::discarded_years(
			array( 2013, 2014, 2015, 2017, 2019, 2020, 2021, 2022, 2023, 2024, 2025 ),
			range( 2013, 2025 )
		);

		$this->assertSame( 0, $gzsz['outside'] );
		$this->assertSame( 0, $rick['outside'] );
		$this->assertSame( 26, $gzsz['hole'] );
		$this->assertSame( 2, $rick['hole'] );
	}

	public function test_discarded_years_outside_the_range_are_counted_apart(): void {
		// Still worth reporting where it does happen: credited before the set
		// begins or after it ends is a harder fact than credited in a gap.
		$out = Longevity::discarded_years( range( 2010, 2015 ), array( 2008, 2012, 2018 ) );

		$this->assertSame( 2, $out['outside'] );
		$this->assertSame( 0, $out['hole'] );
		$this->assertSame( 2, $out['total'] );
	}

	public function test_discarded_years_with_no_aired_set_are_all_unexplained(): void {
		$out = Longevity::discarded_years( array(), array( 2008, 2012 ) );

		$this->assertSame( 2, $out['outside'] );
		$this->assertSame( 2, $out['total'] );
	}

	public function test_a_fully_explained_record_discards_nothing(): void {
		$out = Longevity::discarded_years( range( 2010, 2015 ), range( 2011, 2014 ) );

		$this->assertSame( 0, $out['total'] );
		$this->assertSame( 0, $out['outside'] );
		$this->assertSame( 0, $out['hole'] );
	}

	/*
	 * run_years_detail() - the denominator AND which tier produced it
	 *
	 * The tier has to come from the function that made the choice. When the
	 * preview command re-derived it from the same inputs, the two tests for
	 * "still airing" drifted apart and 12 currently-airing shows were reported as
	 * using a curated season count when their denominator had come from TVMaze.
	 * The scores were right; the explanation of them was wrong.
	 */

	public function test_a_still_airing_show_with_a_season_count_is_not_tier_1(): void {
		// The regression. Euphoria: 3 seasons recorded, still airing, and an
		// aired-years set. Tier 1 excludes still-airing shows, so this MUST
		// report tier 2 -- and a run of 5 years, not 3.
		$out = Longevity::run_years_detail( range( 2019, 2023 ), 3, '2019', '', 2026 );

		$this->assertSame( 2, $out['tier'] );
		$this->assertSame( 5, $out['years'] );
		$this->assertTrue( $out['still_airing'] );
	}

	public function test_the_reported_tier_cannot_contradict_the_denominator(): void {
		// The invariant that caught the bug in the data: with no floor supplied,
		// tier 1 returns min( seasons, span ), so a tier-1 denominator can never
		// exceed the season count. 12 rows in the CSV did, which is what exposed
		// the tier column disagreeing with the tier actually used.
		//
		// ⚠ The credited-years floor can legitimately break this, and that is why
		// every case here passes no floor. `floored` is asserted false so the
		// invariant is being checked under the conditions it holds in, rather than
		// passing by accident of the arguments -- if a future default turned the
		// floor on, this test would fail loudly instead of quietly weakening.
		$cases = array(
			array( range( 2019, 2023 ), 3, '2019', '' ),
			array( range( 2019, 2023 ), 3, '2019', '2023' ),
			array( array(), 8, '2015', '2020' ),
			array( range( 2010, 2025 ), 28, '2010', '2025' ),
			array( array(), 0, '2001', '2001' ),
		);

		foreach ( $cases as $case ) {
			$out = Longevity::run_years_detail( $case[0], $case[1], $case[2], $case[3], 2026 );

			$this->assertFalse( $out['floored'] );

			if ( 1 === $out['tier'] ) {
				$this->assertLessThanOrEqual( $case[1], $out['years'] );
			}

			$this->assertSame( $out['years'], Longevity::run_years( $case[0], $case[1], $case[2], $case[3], 2026 ) );
		}
	}

	public function test_the_floor_is_the_one_thing_allowed_to_break_that_invariant(): void {
		// Stated explicitly so the exception is documented rather than discovered:
		// a floored tier-1 denominator CAN exceed the season count, because the
		// season count was the thing that was wrong.
		$out = Longevity::run_years_detail( array(), 3, '2019', '2023', 2026, array(), 5 );

		$this->assertSame( 1, $out['tier'] );
		$this->assertGreaterThan( 3, $out['years'] );
		$this->assertTrue( $out['floored'] );
	}

	public function test_a_finished_show_with_a_season_count_is_tier_1(): void {
		$out = Longevity::run_years_detail( range( 2014, 2020 ), 5, '2014', '2019', 2026 );

		$this->assertSame( 1, $out['tier'] );
		$this->assertSame( 5, $out['years'] );
		$this->assertFalse( $out['still_airing'] );
	}

	public function test_the_bare_span_reports_tier_4_not_tier_3(): void {
		// Tier 3 is the span LESS hiatus years. With no hiatus data there is
		// nothing subtracted, so calling it tier 3 would overstate what happened.
		$this->assertSame( 4, Longevity::run_years_detail( array(), 0, '2015', '2020', 2026 )['tier'] );
		$this->assertSame( 3, Longevity::run_years_detail( array(), 0, '2015', '2020', 2026, array( 2017 ) )['tier'] );
	}

	/*
	 * The credited-years floor
	 *
	 * A denominator narrower than the span of its own numerators is internally
	 * inconsistent. Found in a live run on The L Word: Generation Q -- 3 seasons
	 * across 5 calendar years, so tier 1 said run_years 3 while its characters
	 * were credited across 5. Every character with 3+ years then had `share`
	 * capped at 1.0, giving the show the largest X in the corpus and making it the
	 * only one whose uncapped total cleared 100.
	 */

	public function test_the_denominator_is_floored_at_the_credited_years(): void {
		// The L Word: Generation Q. 3 seasons, aired 2019-2023, characters
		// credited across all 5 calendar years.
		$out = Longevity::run_years_detail( array(), 3, '2019', '2023', 2026, array(), 5 );

		$this->assertSame( 1, $out['tier'] );
		$this->assertSame( 5, $out['years'] );
		$this->assertTrue( $out['floored'] );
	}

	public function test_the_floor_never_exceeds_the_span(): void {
		// A show cannot have aired in more calendar years than lie between its
		// premiere and its finale, so a mistyped `appears` year cannot run the
		// denominator past the span.
		$out = Longevity::run_years_detail( array(), 2, '2019', '2021', 2026, array(), 40 );

		$this->assertSame( 3, $out['years'] );
		$this->assertSame( 3, $out['span'] );
	}

	public function test_the_floor_does_nothing_when_the_denominator_is_already_bigger(): void {
		$out = Longevity::run_years_detail( array(), 6, '2010', '2020', 2026, array(), 2 );

		$this->assertSame( 6, $out['years'] );
		$this->assertFalse( $out['floored'] );
	}

	public function test_the_floor_is_not_applied_to_exact_aired_years(): void {
		// Tier 2 is authoritative about which years the show existed, and
		// character_years() already intersects against it -- so the numerator
		// cannot exceed the denominator and there is nothing to inflate. Raising
		// it would mean dividing by years the show demonstrably did not air.
		$aired = array( 1993, 1994, 1995, 2016, 2018 );
		$out   = Longevity::run_years_detail( $aired, 0, '1993', '2018', 2026, array(), 20 );

		$this->assertSame( 2, $out['tier'] );
		$this->assertSame( 5, $out['years'] );
		$this->assertFalse( $out['floored'] );
	}

	public function test_the_floor_also_corrects_a_wrong_hiatus(): void {
		// Tier 3 subtracts hiatus years. A character credited during a supposed
		// hiatus is evidence the hiatus data is wrong, so the floor recovers it.
		$out = Longevity::run_years_detail( array(), 0, '2010', '2019', 2026, array( 2013, 2014, 2015 ), 9 );

		$this->assertSame( 3, $out['tier'] );
		$this->assertSame( 9, $out['years'] );
		$this->assertTrue( $out['floored'] );
	}

	public function test_the_floor_is_off_by_default(): void {
		// Callers that have not gathered characters must get the unfloored result
		// rather than a silent zero floor changing their denominator.
		$this->assertSame( 3, Longevity::run_years_detail( array(), 3, '2019', '2023', 2026 )['years'] );
		$this->assertSame( 3, Longevity::run_years( array(), 3, '2019', '2023', 2026 ) );
	}

	public function test_the_floor_removes_the_share_inflation_it_exists_for(): void {
		// The end-to-end point. A character credited in 5 years on a show whose
		// denominator said 3 had share capped at 1.0 -- indistinguishable from a
		// character who was there for every single year. Floored, they differ.
		$unfloored = Longevity::run_years( array(), 3, '2019', '2023', 2026 );
		$floored   = Longevity::run_years( array(), 3, '2019', '2023', 2026, array(), 5 );

		$this->assertSame( 3, $unfloored );
		$this->assertSame( 5, $floored );

		// Three of three reads as total presence; three of five does not.
		$this->assertGreaterThan(
			Longevity::weight( 3, $floored ),
			Longevity::weight( 3, $unfloored )
		);
	}

	public function test_an_unparseable_start_reports_a_tier_rather_than_guessing(): void {
		$out = Longevity::run_years_detail( array(), 5, '', '', 2026 );

		$this->assertSame( 1, $out['years'] );
		$this->assertSame( 4, $out['tier'] );
	}

	/*
	 * run_years() - the tier 1/2/3/4 fallback chain
	 */

	public function test_run_years_prefers_the_curated_season_count(): void {
		// Tier 1 beats tier 2 by choice, not by accuracy: the season count wins
		// even when an exact aired-years set is available.
		$out = Longevity::run_years( array( 2003, 2004, 2013 ), 5, '2003', '2013', 2026 );

		$this->assertSame( 5, $out );
	}

	public function test_run_years_uses_the_season_count_for_a_finished_show(): void {
		// Tier 1. Transparent: span 2014-2019 says 6, but it aired in only 5
		// calendar years because no season landed in 2018. The season count of
		// 5 lands on the truth for free.
		$out = Longevity::run_years( array(), 5, '2014', '2019', 2026 );

		$this->assertSame( 5, $out );
	}

	public function test_run_years_falls_to_exact_aired_years_without_a_season_count(): void {
		// Tier 2. Arrested Development: 5 seasons but 7 calendar years, so with
		// no season count recorded the exact set is what we get -- and it is the
		// more accurate of the two.
		$out = Longevity::run_years( array( 2003, 2004, 2005, 2006, 2013, 2018, 2019 ), 0, '2003', '2019', 2026 );

		$this->assertSame( 7, $out );
	}

	public function test_run_years_uses_exact_years_while_a_show_is_still_airing(): void {
		// Tier 1 is skipped for live shows, so tier 2 takes over -- which matters
		// most for long-running shows mid-run.
		$out = Longevity::run_years( array( 2020, 2021, 2023 ), 2, '2020', 'current', 2026 );

		$this->assertSame( 3, $out );
	}

	public function test_run_years_caps_the_season_count_at_the_span(): void {
		// A streaming show can drop three seasons across two calendar years,
		// but years aired can never exceed the premiere-to-finale span.
		$out = Longevity::run_years( array(), 3, '2020', '2021', 2026 );

		$this->assertSame( 2, $out );
	}

	public function test_run_years_ignores_the_season_count_while_still_airing(): void {
		// A season currently on air may not be counted in the meta yet, so
		// trusting it would understate an ongoing show's run. With no exact
		// years either, this falls all the way to the span.
		$out = Longevity::run_years( array(), 2, '2020', 'current', 2026 );

		$this->assertSame( 7, $out );
	}

	public function test_run_years_ignores_the_season_count_for_a_finish_not_yet_passed(): void {
		// Matches how do_the_math() decides lezshows_on_air: a finish year that
		// has not passed still counts as airing.
		$out = Longevity::run_years( array(), 2, '2020', '2026', 2026 );

		$this->assertSame( 7, $out );
	}

	public function test_run_years_falls_back_to_the_airdate_span(): void {
		// Tier 4: today's behaviour, when there is no season count either.
		$this->assertSame( 11, Longevity::run_years( array(), 0, '2003', '2013', 2026 ) );
	}

	public function test_run_years_resolves_the_current_sentinel(): void {
		$this->assertSame( 7, Longevity::run_years( array(), 0, '2020', 'current', 2026 ) );
	}

	public function test_run_years_treats_an_empty_finish_as_still_airing(): void {
		$this->assertSame( 7, Longevity::run_years( array(), 0, '2020', '', 2026 ) );
	}

	public function test_run_years_counts_a_single_year_show_as_one(): void {
		// Must never be 0 -- it is a denominator.
		$this->assertSame( 1, Longevity::run_years( array(), 0, '2020', '2020', 2026 ) );
	}

	public function test_run_years_subtracts_hiatus_years(): void {
		// Tier 3: The X-Files, off air 2003-2014 inclusive. Span 26, minus 12.
		$out = Longevity::run_years( array(), 0, '1993', '2018', 2026, range( 2003, 2014 ) );

		$this->assertSame( 14, $out );
	}

	public function test_run_years_ignores_hiatus_years_outside_the_span(): void {
		$out = Longevity::run_years( array(), 0, '2020', '2022', 2026, array( 1990, 2050 ) );

		$this->assertSame( 3, $out );
	}

	public function test_run_years_never_returns_less_than_one(): void {
		// Pathological hiatus data must not produce a zero denominator.
		$out = Longevity::run_years( array(), 0, '2020', '2022', 2026, range( 2020, 2022 ) );

		$this->assertSame( 1, $out );
	}

	public function test_run_years_survives_an_unparseable_start(): void {
		$this->assertSame( 1, Longevity::run_years( array(), 5, '', '2013', 2026 ) );
	}

	/*
	 * character_years()
	 */

	public function test_character_years_counts_distinct_years(): void {
		$this->assertSame( 2, Longevity::character_years( array( '2003', '2004', '2004' ) ) );
	}

	public function test_character_years_normalises_a_scalar(): void {
		// A single-selection `appears` row can come back as a bare scalar.
		$this->assertSame( 1, Longevity::character_years( '2003' ) );
	}

	public function test_character_years_handles_absent_data(): void {
		$this->assertSame( 0, Longevity::character_years( '' ) );
		$this->assertSame( 0, Longevity::character_years( null ) );
		$this->assertSame( 0, Longevity::character_years( array() ) );
		$this->assertSame( 0, Longevity::character_years( array( '0', '' ) ) );
	}

	public function test_character_years_intersects_with_the_shows_aired_years(): void {
		// Both sides are sets of calendar years, so a share is meaningful.
		$out = Longevity::character_years( array( 2003, 2004 ), array( 2004, 2005 ) );

		$this->assertSame( 1, $out );
	}

	public function test_character_years_drops_years_the_show_did_not_air(): void {
		// Data-entry error self-corrects instead of needing a clamp.
		$this->assertSame( 0, Longevity::character_years( array( 2003 ), array( 2004 ) ) );
	}

	/*
	 * weight()
	 */

	public function test_weight_is_zero_without_any_years(): void {
		$this->assertSame( 0.0, Longevity::weight( 0, 5 ) );
	}

	public function test_weight_of_a_full_run_regular(): void {
		// share 1.0, curve sqrt(5/8) = 0.790569
		$this->assertEqualsWithDelta( 0.937171, Longevity::weight( 5, 5 ), 0.000001 );
	}

	public function test_weight_of_a_one_season_soap_guest(): void {
		// share 0.02, curve sqrt(1/8) = 0.353553 -- the case the whole change exists for.
		$this->assertEqualsWithDelta( 0.120066, Longevity::weight( 1, 50 ), 0.000001 );
	}

	public function test_weight_of_a_long_tenured_soap_regular(): void {
		// share 0.8, curve capped at 1.0. A deep bench still scores well.
		$this->assertEqualsWithDelta( 0.86, Longevity::weight( 40, 50 ), 0.000001 );
	}

	public function test_weight_reaches_one_only_at_a_full_capped_run(): void {
		$this->assertEqualsWithDelta( 1.0, Longevity::weight( 10, 10 ), 0.000001 );
	}

	public function test_weight_never_exceeds_one_when_years_exceed_the_run(): void {
		$this->assertLessThanOrEqual( 1.0, Longevity::weight( 70, 5 ) );
	}

	public function test_weight_rewards_a_short_format_for_being_fully_present(): void {
		// 3 of 3 years on a web series beats 5 of 50 on a soap. This is the
		// fairness property the curve term exists to protect.
		$this->assertGreaterThan( Longevity::weight( 5, 50 ), Longevity::weight( 3, 3 ) );
	}

	public function test_weight_rises_with_tenure_on_the_same_show(): void {
		$run = 20;

		$this->assertGreaterThan( Longevity::weight( 2, $run ), Longevity::weight( 10, $run ) );
		$this->assertGreaterThan( Longevity::weight( 10, $run ), Longevity::weight( 18, $run ) );
	}

	public function test_weight_tolerates_a_zero_run(): void {
		// Should be unreachable via run_years(), which floors at 1. Guarded anyway
		// because this is a division.
		$this->assertLessThanOrEqual( 1.0, Longevity::weight( 3, 0 ) );
	}

	/*
	 * role_proxy_weight() - fallback when `appears` is empty
	 */

	public function test_role_proxy_weights_by_role(): void {
		$this->assertEqualsWithDelta( 0.7, Longevity::role_proxy_weight( 'regular' ), 0.000001 );
		$this->assertEqualsWithDelta( 0.4, Longevity::role_proxy_weight( 'recurring' ), 0.000001 );
		$this->assertEqualsWithDelta( 0.15, Longevity::role_proxy_weight( 'guest' ), 0.000001 );
	}

	public function test_role_proxy_is_never_zero(): void {
		// Missing `appears` data must not become a score penalty -- that would
		// punish shows for being under-documented.
		$this->assertGreaterThan( 0.0, Longevity::role_proxy_weight( 'nonsense' ) );
	}

	/*
	 * character_value() - role points scaled by the qualities, not added to
	 */

	public function test_character_value_of_an_ideal_regular(): void {
		// 5 x 2.0 (casting) x 1.25 (no clichés)
		$this->assertEqualsWithDelta( 12.5, Longevity::character_value( 'regular', 2.0, true, false ), 0.000001 );
	}

	public function test_character_value_is_never_negative(): void {
		// Was 1 - 5 = -4 under the additive model, which meant documenting a
		// dead one-scene queer character LOWERED a show's score.
		$this->assertEqualsWithDelta( 0.5, Longevity::character_value( 'guest', 1.0, false, true ), 0.000001 );
		$this->assertGreaterThanOrEqual( 0.0, Longevity::character_value( 'guest', 1.0, false, true ) );
	}

	public function test_character_value_of_a_plain_recurring(): void {
		// 2 x 1.25
		$this->assertEqualsWithDelta( 2.5, Longevity::character_value( 'recurring', 1.0, true, false ), 0.000001 );
	}

	public function test_character_value_awards_nothing_for_an_unknown_role(): void {
		$this->assertEqualsWithDelta( 0.0, Longevity::character_value( 'nonsense', 2.0, true, false ), 0.000001 );
	}

	public function test_character_value_rejects_a_negative_casting_multiplier(): void {
		// Guards the one input that could otherwise drive a value below zero.
		$this->assertEqualsWithDelta( 0.0, Longevity::character_value( 'regular', -3.0, false, false ), 0.000001 );
	}

	public function test_good_casting_is_worth_more_in_a_bigger_role(): void {
		// The whole reason the bonus is multiplicative. A flat +10 gave a
		// one-scene guest and a series lead the identical reward.
		$lead  = Longevity::character_value( 'regular', 2.0 ) - Longevity::character_value( 'regular', 1.0 );
		$guest = Longevity::character_value( 'guest', 2.0 ) - Longevity::character_value( 'guest', 1.0 );

		$this->assertGreaterThan( $guest, $lead );
	}

	public function test_killing_a_lead_costs_more_than_killing_a_walk_on(): void {
		// Bury Your Gays scales with how much the show had invested in them.
		$lead_loss  = Longevity::character_value( 'regular', 1.0, false, false ) - Longevity::character_value( 'regular', 1.0, false, true );
		$guest_loss = Longevity::character_value( 'guest', 1.0, false, false ) - Longevity::character_value( 'guest', 1.0, false, true );

		$this->assertGreaterThan( $guest_loss, $lead_loss );
	}

	public function test_character_value_stays_non_negative_under_every_modifier(): void {
		foreach ( array( 'regular', 'recurring', 'guest', 'nonsense' ) as $role ) {
			foreach ( array( 0.0, 0.5, 1.0, 2.0 ) as $casting ) {
				foreach ( array( true, false ) as $dead ) {
					$this->assertGreaterThanOrEqual(
						0.0,
						Longevity::character_value( $role, $casting, true, $dead ),
						$role . ' at casting multiplier ' . $casting
					);
				}
			}
		}
	}

	/*
	 * classify_gender() - three states, not a boolean
	 */

	public function test_classify_gender_recognises_trans_terms(): void {
		$this->assertSame( 'trans-or-nb', Longevity::classify_gender( array( 'trans-woman' ) ) );
	}

	public function test_classify_gender_holds_non_binary_to_the_same_standard(): void {
		// Both slugs turned up unclassified on Transparent, Ari Pfefferman among
		// them. A non-binary role should go to a trans or non-binary actor.
		$this->assertSame( 'trans-or-nb', Longevity::classify_gender( array( 'non-binary' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_gender( array( 'genderqueer' ) ) );
	}

	public function test_classify_gender_recognises_cis_terms(): void {
		$this->assertSame( 'cis', Longevity::classify_gender( array( 'cisgender' ) ) );
		$this->assertSame( 'cis', Longevity::classify_gender( array( 'intersex' ) ) );
	}

	public function test_classify_gender_lets_a_trans_term_win_a_mixed_set(): void {
		$this->assertSame( 'trans-or-nb', Longevity::classify_gender( array( 'cisgender', 'trans-woman' ) ) );
	}

	public function test_an_unknown_gender_term_is_unclassified_not_cis(): void {
		// The whole reason this returns three states. Treating an untriaged term
		// as cis would silently stop assessing those characters the moment
		// someone adds a term to the taxonomy.
		$this->assertSame( 'unclassified', Longevity::classify_gender( array( 'some-new-term' ) ) );
	}

	public function test_no_gender_terms_at_all_is_unclassified(): void {
		$this->assertSame( 'unclassified', Longevity::classify_gender( array() ) );
	}

	/*
	 * classify_actor_gender() - the companion Is_Actor_Trans cannot provide.
	 *
	 * Slugs below are real, from the live lez_actor_gender taxonomy.
	 */

	public function test_actor_check_keeps_the_trans_substring_rule(): void {
		// Same rule as Queeries\Is_Actor_Trans, so the two never disagree about
		// an actor who is trans -- including future trans* slugs.
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'trans-woman' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'trans-masculine' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'two-spirit-trans-man' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'some-new-transmasc-term' ) ) );
	}

	public function test_actor_check_sees_compound_non_binary_slugs(): void {
		// Regression: an exact-match list caught `non-binary` but silently missed
		// every compound form, so 11 unambiguously non-binary actors read as cis
		// and produced false miscast penalties.
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'non-binary' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'non-binary-woman' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'non-binary-intersex' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'non-binary-gender-fluid' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'non-binary-genderqueer' ) ) );
	}

	public function test_actor_check_sees_listed_gender_diverse_slugs(): void {
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'genderqueer' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'genderfluid' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'agender' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'gender-non-conforming' ) ) );
	}

	public function test_deliberately_unlisted_slugs_stay_neutral(): void {
		// Omitted by editorial decision, not oversight. Neutral means no show is
		// ever docked over them -- which is the point, since androgynous often
		// describes presentation and no-label is a refusal to categorise.
		foreach ( array( 'demigender', 'androgynous', 'no-label', 'two-spirit' ) as $slug ) {
			$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( $slug ) ), $slug );
			$this->assertEqualsWithDelta(
				1.0,
				Longevity::casting_multiplier( 'trans-or-nb', false, Longevity::classify_actor_gender( array( $slug ) ) ),
				0.000001,
				$slug . ' must not incur a penalty'
			);
		}
	}

	public function test_actor_check_recognises_cis_actors(): void {
		$this->assertSame( 'cis', Longevity::classify_actor_gender( array( 'cis-woman' ) ) );
		$this->assertSame( 'cis', Longevity::classify_actor_gender( array( 'cis-man' ) ) );
		$this->assertSame( 'cis', Longevity::classify_actor_gender( array( 'cisgender' ) ) );
	}

	public function test_an_unrecorded_actor_gender_is_unknown_not_cis(): void {
		// 37 actors are tagged undefined/unknown. Reading them as cis would dock
		// shows for our own missing data.
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( 'undefined' ) ) );
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( 'unknown' ) ) );
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array() ) );
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( '', '   ' ) ) );
	}

	public function test_an_untriaged_actor_slug_is_unknown_not_cis(): void {
		// Safe default for the slugs still pending an editorial call, and for any
		// term added later: neutral, never a penalty.
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( 'two-spirit' ) ) );
		$this->assertSame( 'unknown', Longevity::classify_actor_gender( array( 'brand-new-term' ) ) );
	}

	public function test_trans_or_nb_wins_over_a_cis_term_on_the_same_actor(): void {
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'cis-woman', 'non-binary' ) ) );
		$this->assertSame( 'trans-or-nb', Longevity::classify_actor_gender( array( 'trans-man', 'cisgender' ) ) );
	}

	/*
	 * casting_multiplier() - one casting decision, one multiplier
	 */

	/**
	 * Every actor classification classify_actor_gender() can return.
	 *
	 * Exists so these tests iterate the real states. An earlier revision passed
	 * booleans here after the parameter became a string; PHP coerced true to "1"
	 * and false to "", which matched no branch, so every trans case silently
	 * returned the neutral 1.0. Four tests failed loudly — and four more passed
	 * while asserting nothing at all.
	 *
	 * @return array<int, string>
	 */
	private function actor_classes(): array {
		return array( 'trans-or-nb', 'cis', 'unknown' );
	}

	public function test_trans_role_with_trans_or_nb_primary_actor_is_boosted(): void {
		$this->assertEqualsWithDelta( 2.0, Longevity::casting_multiplier( 'trans-or-nb', false, 'trans-or-nb' ), 0.000001 );
	}

	public function test_trans_role_with_a_cis_primary_actor_is_penalised(): void {
		$out = Longevity::casting_multiplier( 'trans-or-nb', false, 'cis' );

		$this->assertLessThan( 1.0, $out );
		$this->assertEqualsWithDelta( 0.5, $out, 0.000001 );
	}

	public function test_trans_role_with_an_unrecorded_actor_gender_is_neutral(): void {
		// Only an explicit cis tag earns the penalty. 45 actors carry a slug that
		// classifies as unknown, and a show must not be docked for our data gap.
		$this->assertEqualsWithDelta( 1.0, Longevity::casting_multiplier( 'trans-or-nb', false, 'unknown' ), 0.000001 );
	}

	public function test_a_trans_role_is_judged_only_on_trans_casting(): void {
		// The signals no longer stack. A trans character played by a cis QUEER
		// actor is still a miscast trans role: the queer-irl boost must not
		// offset it, and must not compound with it either.
		$this->assertEqualsWithDelta( 0.5, Longevity::casting_multiplier( 'trans-or-nb', true, 'cis' ), 0.000001 );
		$this->assertEqualsWithDelta( 2.0, Longevity::casting_multiplier( 'trans-or-nb', true, 'trans-or-nb' ), 0.000001 );
	}

	public function test_cis_roles_are_judged_on_queer_casting(): void {
		$this->assertEqualsWithDelta( 2.0, Longevity::casting_multiplier( 'cis', true, 'cis' ), 0.000001 );
		$this->assertEqualsWithDelta( 1.0, Longevity::casting_multiplier( 'cis', false, 'cis' ), 0.000001 );
	}

	public function test_a_cis_role_never_earns_the_trans_boost(): void {
		$this->assertEqualsWithDelta( 1.0, Longevity::casting_multiplier( 'cis', false, 'trans-or-nb' ), 0.000001 );
	}

	public function test_unclassified_characters_never_move_the_score(): void {
		// A gender term nobody has triaged must not swing a score in either
		// direction. It belongs in the unclassified report, not in the maths.
		foreach ( array( true, false ) as $queer ) {
			foreach ( $this->actor_classes() as $actor_class ) {
				$this->assertEqualsWithDelta(
					1.0,
					Longevity::casting_multiplier( 'unclassified', $queer, $actor_class ),
					0.000001,
					'unclassified char, queer=' . var_export( $queer, true ) . ', actor=' . $actor_class
				);
			}
		}
	}

	public function test_the_casting_multiplier_never_compounds_past_its_bounds(): void {
		// Guards the regression this replaced: two stacking x2 multipliers
		// reached x4 and overtook the role hierarchy entirely, so a recurring
		// character outranked a series lead.
		//
		// This is also the test that was silently vacuous while the third
		// argument was a coerced boolean -- every trans case returned 1.0, well
		// inside the bounds, so it asserted nothing. Iterating the real
		// classifications is what gives it teeth.
		$seen = array();

		foreach ( array( 'trans-or-nb', 'cis', 'unclassified' ) as $class ) {
			foreach ( array( true, false ) as $queer ) {
				foreach ( $this->actor_classes() as $actor_class ) {
					$out    = Longevity::casting_multiplier( $class, $queer, $actor_class );
					$seen[] = $out;

					$this->assertGreaterThanOrEqual( 0.5, $out, $class . '/' . $actor_class );
					$this->assertLessThanOrEqual( 2.0, $out, $class . '/' . $actor_class );
				}
			}
		}

		// Proof the loop actually exercised the branches rather than returning a
		// constant: all three distinct multipliers must appear.
		$this->assertEqualsWithDelta( 0.5, min( $seen ), 0.000001 );
		$this->assertEqualsWithDelta( 2.0, max( $seen ), 0.000001 );
		$this->assertContains( 1.0, $seen );
	}

	public function test_miscasting_a_lead_costs_more_than_miscasting_a_guest(): void {
		// The reason this moved off the old show-level aggregate, which applied
		// a flat -5 per character regardless of who they were.
		$miscast = Longevity::casting_multiplier( 'trans-or-nb', false, 'cis' );

		$lead_loss  = Longevity::character_value( 'regular', 1.0 ) - Longevity::character_value( 'regular', $miscast );
		$guest_loss = Longevity::character_value( 'guest', 1.0 ) - Longevity::character_value( 'guest', $miscast );

		$this->assertGreaterThan( $guest_loss, $lead_loss );
	}

	/*
	 * The inversions these changes exist to fix.
	 */

	public function test_a_five_season_lead_outranks_a_queer_cast_one_scene_guest(): void {
		// Real numbers from Transparent, run length 5. Under the additive model
		// Barb (guest, 2 of 5 years, queer actor) scored 4.73 and beat Ari
		// (regular, 5 of 5 years) on 4.69, because +10 was double the 5 points
		// a lead role was worth.
		$lead  = Longevity::character_value( 'regular', 1.0 ) * Longevity::weight( 5, 5 );
		$guest = Longevity::character_value( 'guest', 2.0 ) * Longevity::weight( 2, 5 );

		$this->assertGreaterThan( $guest, $lead );
	}

	public function test_a_series_lead_outranks_a_well_cast_recurring_character(): void {
		// When queer-irl and trans casting stacked to x4, Davina (recurring, 4 of
		// 5 years, both boosts) hit 6.18 and beat Ari (regular, 5 of 5) on 4.69.
		// One combined signal caps the multiplier at x2 and restores the role
		// hierarchy.
		$lead      = Longevity::character_value( 'regular', 1.0 ) * Longevity::weight( 5, 5 );
		$recurring = Longevity::character_value( 'recurring', 2.0 ) * Longevity::weight( 4, 5 );

		$this->assertGreaterThan( $recurring, $lead );
	}

	public function test_a_miscast_dead_lead_still_outranks_a_one_episode_guest(): void {
		// Maura Pfefferman: cis-cast, and dead. Under stacking she was reduced
		// twice for one casting decision and landed at 0.97, below Adriana -- a
		// single-episode guest on 0.98. A protagonist scoring under a walk-on was
		// the tell that the compounding was wrong.
		$miscast = Longevity::casting_multiplier( 'trans-or-nb', false, 'cis' );

		$maura   = Longevity::character_value( 'regular', $miscast, false, true ) * Longevity::weight( 4, 5 );
		$adriana = Longevity::character_value( 'guest', 2.0 ) * Longevity::weight( 1, 5 );

		// Pin the miscast value too. This assertion passed while the argument was
		// a coerced boolean and $miscast was silently 1.0 instead of 0.5 -- Maura
		// still cleared Adriana, so the comparison hid the wrong input.
		$this->assertEqualsWithDelta( 0.5, $miscast, 0.000001 );
		$this->assertGreaterThan( $adriana, $maura );
	}

	/*
	 * saturate() - the smooth ceiling replacing the hard clamp at 100
	 */

	public function test_saturate_is_zero_at_or_below_zero(): void {
		$this->assertSame( 0.0, Longevity::saturate( 0.0 ) );
		$this->assertSame( 0.0, Longevity::saturate( -5.0 ) );
	}

	public function test_saturate_is_fifty_at_the_constant(): void {
		// Reads the constant rather than hardcoding it: SATURATION_K is still
		// being calibrated, and this test is asserting the shape of the curve,
		// not the value of the tunable.
		$this->assertEqualsWithDelta( 50.0, Longevity::saturate( Longevity::SATURATION_K ), 0.000001 );
	}

	public function test_saturate_is_monotonic(): void {
		$previous = -1.0;
		foreach ( array( 1.0, 5.0, 20.0, 40.0, 80.0, 200.0, 1000.0 ) as $raw ) {
			$score = Longevity::saturate( $raw );
			$this->assertGreaterThan( $previous, $score );
			$previous = $score;
		}
	}

	public function test_saturate_never_reaches_one_hundred(): void {
		// No amount of headcount can buy a perfect score. This is what stops
		// shows piling up tied at exactly 100.
		$this->assertLessThan( 100.0, Longevity::saturate( 1000000.0 ) );
	}

	public function test_saturate_gives_diminishing_returns_to_volume(): void {
		// Adding the same amount of raw value is worth less the more you already
		// have -- volume stops being a lever without ever being a penalty.
		$first_gain  = Longevity::saturate( 40.0 ) - Longevity::saturate( 20.0 );
		$second_gain = Longevity::saturate( 60.0 ) - Longevity::saturate( 40.0 );

		$this->assertGreaterThan( $second_gain, $first_gain );
	}

	/*
	 * The properties this whole change exists to guarantee.
	 */

	public function test_a_deep_bench_outscores_a_revolving_door(): void {
		// Two 50-year soaps. One kept ten characters for 35 years each; the
		// other ran sixty characters through for a year apiece. Same run
		// length, same format -- the first must win decisively.
		$deep = 0.0;
		for ( $i = 0; $i < 10; $i++ ) {
			$deep += Longevity::character_value( 'regular', 1.0, true, false ) * Longevity::weight( 35, 50 );
		}

		$revolving = 0.0;
		for ( $i = 0; $i < 60; $i++ ) {
			$revolving += Longevity::character_value( 'guest', 1.0, true, false ) * Longevity::weight( 1, 50 );
		}

		$this->assertGreaterThan( Longevity::saturate( $revolving ), Longevity::saturate( $deep ) );
	}

	public function test_documenting_another_minor_character_never_lowers_the_score(): void {
		// The reason this model is a saturating sum and not an average. Under an
		// average, every one-episode guest drags the score down, which would
		// mean thorough documentation is punished.
		$base = 0.0;
		for ( $i = 0; $i < 6; $i++ ) {
			$base += Longevity::character_value( 'regular', 1.0, true, false ) * Longevity::weight( 4, 5 );
		}

		$with_extra_guest = $base + ( Longevity::character_value( 'guest', 1.0, false, false ) * Longevity::weight( 1, 5 ) );

		$this->assertGreaterThanOrEqual( Longevity::saturate( $base ), Longevity::saturate( $with_extra_guest ) );
	}
}
