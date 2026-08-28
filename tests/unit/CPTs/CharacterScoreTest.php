<?php
/**
 * Tests for Character_Score's two scoring models.
 *
 * This class exists to hold ONE copy of maths that used to exist in two places:
 * the live calculation and the preview command that was used to calibrate it. The
 * extraction was made with an explicit promise -- that it changed no scores -- and
 * these tests are what turns that promise into something checkable.
 *
 * Only legacy() and longevity() are covered here, because only they are pure.
 * gather() reads meta, taxonomy and ACF, and is verified against the running site
 * via `wp lwtv score-preview`, whose stored-meta divergence warning is the real
 * regression test for the extraction: if the shared legacy() stops reproducing
 * what the site stored, every row prints a warning.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Character_Score;
use LWTV\CPTs\Shows\Longevity;
use PHPUnit\Framework\TestCase;

final class CharacterScoreTest extends TestCase {

	/**
	 * Transparent (#655) as the reference fixture, matching the decomposition
	 * recorded in docs/plans/show-score-longevity.md:
	 *
	 *   base (roles)      +41
	 *   queer-irl bonus  +190   <- 19 characters x 10
	 *   no-cliches          0
	 *   dead penalty      -10   <- 2 dead x -5
	 *   trans adjustment  -10
	 *   ------------------------
	 *   uncapped total    211    2.1x over the cap
	 *
	 * @param array $overrides Keys to replace.
	 *
	 * @return array
	 */
	private function transparent( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'         => 655,
				'divisor'    => 1,
				'char_roles' => array(
					'regular'   => 5,
					'recurring' => 6,
					'guest'     => 4,
				),
				'queer_irl'  => 19,
				'none'       => 0,
				'dead'       => 2,
				'trans'      => 3,
				'trans_irl'  => 1,
				'characters' => array(),
			),
			$overrides
		);
	}

	/*
	 * legacy() - the model the site stores today
	 */

	public function test_the_legacy_decomposition_matches_the_recorded_one(): void {
		$out = Character_Score::legacy( $this->transparent() );

		$this->assertSame( 41, $out['parts']['base (roles)'] );
		$this->assertSame( 190, $out['parts']['queer-irl bonus'] );
		$this->assertSame( 0, $out['parts']['no-cliches bonus'] );
		$this->assertSame( -10, $out['parts']['dead penalty'] );
		$this->assertSame( -10, $out['parts']['trans adjustment'] );
		$this->assertSame( 211.0, $out['raw'] );
	}

	public function test_the_queer_irl_bonus_alone_overruns_the_cap(): void {
		// The finding that reshaped the whole model: one term is 1.9x the entire
		// ceiling, so roles, deaths and longevity were all inert noise beneath it.
		$out = Character_Score::legacy( $this->transparent() );

		$this->assertGreaterThan( 100, $out['parts']['queer-irl bonus'] );
		$this->assertSame( 100.0, $out['score'] );
	}

	public function test_the_format_divisor_applies_before_the_clamp(): void {
		// 211 / 2 = 105.5, still over the cap; 211 / 1.5 = 140.67, likewise. The
		// ordering only becomes visible below the cap, so test it there too.
		$this->assertSame( 100.0, Character_Score::legacy( $this->transparent( array( 'divisor' => 2 ) ) )['score'] );

		$small = $this->transparent(
			array(
				'queer_irl' => 4,
				'divisor'   => 2,
			)
		);

		// 41 + 40 + 0 - 10 - 10 = 61, halved = 30.5.
		$out = Character_Score::legacy( $small );
		$this->assertSame( 61.0, $out['raw'] );
		$this->assertSame( 30.5, $out['score'] );
	}

	public function test_the_trans_term_rewards_when_casting_keeps_up(): void {
		// trans_irl >= trans flips the sign of the whole term, from a per-character
		// penalty to a per-character bonus. Faithfully preserved.
		$out = Character_Score::legacy( $this->transparent( array( 'trans_irl' => 3 ) ) );

		$this->assertSame( 30, $out['parts']['trans adjustment'] );
	}

	public function test_the_legacy_model_still_has_no_lower_bound(): void {
		// `min( 100, ... )` caps the top and leaves the bottom open, so a show can
		// score below zero -- Killing Eve stored -6. This is a real bug and it is
		// asserted here ON PURPOSE: the extraction promised to change no scores, so
		// the bug must survive it. The new model is what fixes it.
		$out = Character_Score::legacy(
			$this->transparent(
				array(
					'char_roles' => array(),
					'queer_irl'  => 0,
					'dead'       => 4,
					'trans'      => 2,
					'trans_irl'  => 0,
				)
			)
		);

		$this->assertSame( -30.0, $out['raw'] );
		$this->assertSame( -30.0, $out['score'] );
	}

	public function test_a_zero_raw_score_skips_the_divisor(): void {
		// `0 !== $raw` guards the division. It cannot matter arithmetically -- 0
		// over anything is 0 -- but it is in the original and removing it would be
		// an undocumented change to a function that promised none.
		$out = Character_Score::legacy(
			$this->transparent(
				array(
					'char_roles' => array(),
					'queer_irl'  => 0,
					'dead'       => 0,
					'trans'      => 0,
					'trans_irl'  => 0,
					'divisor'    => 2,
				)
			)
		);

		$this->assertSame( 0.0, $out['raw'] );
		$this->assertSame( 0.0, $out['score'] );
	}

	public function test_missing_role_meta_is_not_fatal(): void {
		// lezshows_char_roles is written by show_character_data(); a show that has
		// never been calculated has no such meta, and get_post_meta returns ''.
		$out = Character_Score::legacy( $this->transparent( array( 'char_roles' => '' ) ) );

		$this->assertSame( 0, $out['parts']['base (roles)'] );
	}

	public function test_the_actor_check_reduces_the_queer_irl_bonus(): void {
		// The Tambor Takedown reaching the score for the first time. 19 tagged
		// characters, 6 whose first-billed actor is actually queer: +190 becomes
		// +60, which on Transparent is the difference between 2.1x over the cap and
		// under it. legacy() reads queer_irl_scored, which gather() resolves from
		// the flag -- so this asserts the wiring, not the flag.
		$out = Character_Score::legacy(
			$this->transparent(
				array(
					'queer_irl'        => 19,
					'queer_irl_scored' => 6,
				)
			)
		);

		$this->assertSame( 60, $out['parts']['queer-irl bonus'] );
		$this->assertSame( 81.0, $out['raw'] );
		$this->assertSame( 81.0, $out['score'] );
	}

	public function test_the_tagged_count_is_used_when_nothing_was_resolved(): void {
		// A caller that gathered without the actor check has no queer_irl_scored
		// key. Falling back to the tagged count keeps the legacy score correct; the
		// alternative -- treating a missing key as zero -- would award no queer-irl
		// credit at all and look like a scoring collapse.
		$data = $this->transparent();
		unset( $data['queer_irl_scored'] );

		$this->assertSame( 190, Character_Score::legacy( $data )['parts']['queer-irl bonus'] );
	}

	/*
	 * longevity() - the model behind the flag
	 */

	public function test_longevity_sums_contributions_and_saturates(): void {
		// The ceiling is passed explicitly throughout this section. SATURATION_K is
		// a tuning constant that has already been recalibrated twice (9 -> 15 -> 10);
		// a unit test that fails when it moves is testing the tuning, not the maths.
		$data = $this->transparent(
			array(
				'characters' => array(
					array( 'contribution' => 6.0 ),
					array( 'contribution' => 4.0 ),
					array( 'contribution' => 5.0 ),
				),
			)
		);

		$out = Character_Score::longevity( $data, 15.0 );

		$this->assertSame( 15.0, $out['raw'] );
		$this->assertSame( 15.0, $out['divided'] );

		// X equal to the ceiling is the half-way point of the curve, by definition.
		$this->assertSame( 50.0, round( $out['score'], 6 ) );
	}

	public function test_longevity_saturates_at_the_configured_ceiling(): void {
		// The property that must hold whatever SATURATION_K is set to: feeding X
		// equal to the constant returns exactly 50.
		$data = $this->transparent(
			array(
				'characters' => array( array( 'contribution' => Longevity::SATURATION_K ) ),
			)
		);

		$this->assertSame( 50.0, round( Character_Score::longevity( $data )['score'], 6 ) );
	}

	public function test_longevity_applies_the_format_divisor_before_saturating(): void {
		// Saturation is non-linear, so dividing after it would not be the same
		// operation. The divisor has to land on X, not on the score.
		$data = $this->transparent(
			array(
				'divisor'    => 2,
				'characters' => array( array( 'contribution' => 30.0 ) ),
			)
		);

		$out = Character_Score::longevity( $data, 15.0 );

		$this->assertSame( 15.0, $out['divided'] );
		$this->assertSame( 50.0, round( $out['score'], 6 ) );
	}

	public function test_longevity_honours_a_ceiling_override(): void {
		// What --k sweeps. Same X, different K, different score.
		$data = $this->transparent(
			array(
				'characters' => array( array( 'contribution' => 15.0 ) ),
			)
		);

		$this->assertSame( 50.0, round( Character_Score::longevity( $data, 15.0 )['score'], 6 ) );
		$this->assertSame( 25.0, round( Character_Score::longevity( $data, 45.0 )['score'], 6 ) );
	}

	public function test_longevity_never_returns_a_negative(): void {
		// The whole point of the new shape. character_value() cannot go negative,
		// so neither can the sum, so neither can the score -- no show is punished
		// for documenting a dead one-scene character.
		$data = $this->transparent( array( 'characters' => array() ) );

		$this->assertSame( 0.0, Character_Score::longevity( $data )['raw'] );
		$this->assertGreaterThanOrEqual( 0.0, Character_Score::longevity( $data )['score'] );
	}

	/*
	 * strongest_role() - one character, one role
	 */

	public function test_the_stronger_of_two_roles_wins_either_way_round(): void {
		$this->assertSame( 'regular', Character_Score::strongest_role( 'guest', 'regular' ) );
		$this->assertSame( 'regular', Character_Score::strongest_role( 'regular', 'guest' ) );
		$this->assertSame( 'recurring', Character_Score::strongest_role( 'guest', 'recurring' ) );
		$this->assertSame( 'regular', Character_Score::strongest_role( 'recurring', 'regular' ) );
	}

	public function test_an_unrecognised_role_never_wins(): void {
		$this->assertSame( 'guest', Character_Score::strongest_role( 'guest', 'cameo' ) );
		$this->assertSame( '', Character_Score::strongest_role( '', 'cameo' ) );
		$this->assertSame( 'guest', Character_Score::strongest_role( '', 'guest' ) );
	}

	public function test_the_role_hierarchy_follows_points_not_declaration_order(): void {
		// strongest_role() ranks by ROLE_POINTS values rather than by position in
		// that array, so reordering the constant cannot silently invert the
		// hierarchy. This asserts the property the implementation relies on.
		$points = Longevity::ROLE_POINTS;

		$this->assertGreaterThan( $points['recurring'], $points['regular'] );
		$this->assertGreaterThan( $points['guest'], $points['recurring'] );

		$strongest = array_keys( $points, max( $points ), true )[0];

		foreach ( array_keys( $points ) as $role ) {
			$this->assertSame( $strongest, Character_Score::strongest_role( $role, $strongest ) );
		}
	}
}
