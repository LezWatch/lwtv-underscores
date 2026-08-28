<?php
/**
 * Tests for Show_Tropes, the pure extraction of show_tropes_score()'s math.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Scoring\Show_Tropes;
use PHPUnit\Framework\TestCase;

final class ShowTropesTest extends TestCase {

	public function test_no_tropes_at_all_scores_eighty(): void {
		$this->assertSame( 80.0, Show_Tropes::score( array(), false, 0 ) );
	}

	public function test_the_none_slug_scores_eighty_even_alongside_other_tropes(): void {
		$this->assertSame( 80.0, Show_Tropes::score( array( 'none', 'dead-queers' ), true, 0 ) );
	}

	public function test_tropes_present_but_uncategorized_score_seventy(): void {
		// 'literary-inspired' is a real, purely descriptive trope slug that is
		// in none of GOOD/MAYBE/BAD/PLOY.
		$this->assertSame( 70.0, Show_Tropes::score( array( 'literary-inspired' ), false, 0 ) );
	}

	public function test_only_maybe_tropes_score_full_marks(): void {
		// good=0 maybe=2 bad=0 ploy=0, any=2, base=2 => (2/2)*100 = 100.
		$this->assertSame( 100.0, Show_Tropes::score( array( 'coming-out', 'subtext' ), false, 0 ) );
	}

	public function test_bad_tropes_outweighing_good_floor_at_zero(): void {
		// any=1 base=-1, not > 0, so score is 0 rather than negative.
		$this->assertSame( 0.0, Show_Tropes::score( array( 'queerbashing' ), false, 0 ) );
	}

	public function test_intersectionality_bonus_is_added_and_capped_at_fifteen(): void {
		// base score 0 (one bad, one maybe cancel out) + min(5*3, 15) = 15.
		$this->assertSame( 15.0, Show_Tropes::score( array( 'queerbashing', 'coming-out' ), false, 5 ) );
	}

	public function test_death_without_happy_ending_cuts_the_score_by_a_third(): void {
		// maybe=1 => score 100, dead-queers present, no happy-ending => *0.66.
		$this->assertSame( 66.0, Show_Tropes::score( array( 'coming-out', 'dead-queers' ), false, 0 ) );
	}

	public function test_death_with_happy_ending_cuts_the_score_by_a_quarter(): void {
		// happy-ending is itself a GOOD trope: good=1 => score 100, dead-queers
		// present, happy-ending present => *0.75.
		$this->assertSame( 75.0, Show_Tropes::score( array( 'happy-ending', 'dead-queers' ), false, 0 ) );
	}

	public function test_death_override_cancels_the_death_deduction(): void {
		$this->assertSame( 100.0, Show_Tropes::score( array( 'coming-out', 'dead-queers' ), true, 0 ) );
	}

	public function test_the_none_slug_does_not_protect_against_the_death_deduction(): void {
		// 'none' short-circuits to 80, but does not short-circuit the death
		// deduction that applies afterward: 80 * 0.66 = 52.8 (compared with a
		// delta to tolerate float rounding, unlike this file's other cases
		// where the multiplication happens to land on an exact float).
		$this->assertEqualsWithDelta( 52.8, Show_Tropes::score( array( 'none', 'dead-queers' ), false, 0 ), PHP_FLOAT_EPSILON * 100 );
	}
}
