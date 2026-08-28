<?php
/**
 * Tests for Show_Rating, the pure extraction of show_score()'s point tables.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Scoring\Show_Rating;
use PHPUnit\Framework\TestCase;

final class ShowRatingTest extends TestCase {

	public function test_base_ratings_are_summed_and_tripled(): void {
		// (5+5+5) * 3 = 45, with no worth-it/star/trigger/bonus contribution.
		$this->assertSame( 45, Show_Rating::score( 5, 5, 5, '', '', '', false ) );
	}

	public function test_base_ratings_are_clamped_at_five(): void {
		// A rating above 5 must not out-earn a perfect 5 -- preserves the
		// min( $rating, 5 ) clamp from the pre-extraction code.
		$this->assertSame( 45, Show_Rating::score( 7, 9, 20, '', '', '', false ) );
	}

	public function test_unknown_worth_it_or_star_value_contributes_nothing(): void {
		$this->assertSame( 0, Show_Rating::score( 0, 0, 0, 'Unrated', 'no-such-tier', '', false ) );
	}

	public function test_trigger_aliases_score_identically_to_their_canonical_slug(): void {
		$this->assertSame(
			Show_Rating::score( 0, 0, 0, '', '', 'high', false ),
			Show_Rating::score( 0, 0, 0, '', '', 'on', false )
		);
		$this->assertSame(
			Show_Rating::score( 0, 0, 0, '', '', 'med', false ),
			Show_Rating::score( 0, 0, 0, '', '', 'medium', false )
		);
	}

	public function test_full_show_matches_a_hand_worked_example(): void {
		// base (3+4+5)*3=36, worth_it 'No'=-10, star 'silver'=+10,
		// trigger 'low'=-5, shows-we-love +40 => 71.
		$this->assertSame( 71, Show_Rating::score( 3, 4, 5, 'No', 'silver', 'low', true ) );
	}
}
