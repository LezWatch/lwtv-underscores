<?php
/**
 * Tests for Character_Score's longevity model and its role-strength helper.
 *
 * Only longevity() is covered here, because it is the only pure model this
 * class exposes -- legacy() has been retired now that longevity() is the live
 * model. gather() reads meta, taxonomy and ACF, and is verified against the
 * running site via `wp lwtv score-preview` instead.
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
	 * longevity() - the live model
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
