<?php
/**
 * Unit tests for the queer-consistency rules. Both directions of disagreement
 * are their own problem: a missing tag is a gap in our data, a tag with no queer
 * actor behind it is a claim we cannot support.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Queer_Rules;

class QueerRulesTest extends TestCase {

	/**
	 * A character with actors.
	 *
	 * @param  bool $flagged Carries the queer-irl cliché.
	 * @param  bool $queer   At least one linked actor is queer.
	 * @return array
	 */
	private function character( bool $flagged, bool $queer ): array {
		return array(
			'post_id'       => 42,
			'has_actors'    => true,
			'flagged_queer' => $flagged,
			'actor_queer'   => $queer,
		);
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

	public function test_a_queer_actor_and_the_tag_agree(): void {
		$this->assertSame( array(), Queer_Rules::evaluate( $this->character( true, true ) ) );
	}

	public function test_a_straight_actor_and_no_tag_agree(): void {
		$this->assertSame( array(), Queer_Rules::evaluate( $this->character( false, false ) ) );
	}

	public function test_a_queer_actor_with_no_tag_is_a_missing_tag(): void {
		$this->assertSame(
			array( 'char-missing-queer-irl' ),
			$this->types( Queer_Rules::evaluate( $this->character( false, true ) ) )
		);
	}

	public function test_a_tag_with_no_queer_actor_is_an_unsupported_claim(): void {
		$this->assertSame(
			array( 'char-no-queer-actor' ),
			$this->types( Queer_Rules::evaluate( $this->character( true, false ) ) )
		);
	}

	public function test_no_actors_is_its_own_finding(): void {
		// Nothing to compare the cliché against, and a character nobody plays is
		// a gap in its own right.
		$character = array(
			'post_id'       => 42,
			'has_actors'    => false,
			'flagged_queer' => false,
			'actor_queer'   => false,
		);

		$this->assertSame(
			array( 'char-no-actors-listed' ),
			$this->types( Queer_Rules::evaluate( $character ) )
		);
	}

	public function test_no_actors_reports_only_that(): void {
		// Even when tagged: the tag cannot be judged without actors.
		$character = array(
			'post_id'       => 42,
			'has_actors'    => false,
			'flagged_queer' => true,
			'actor_queer'   => false,
		);

		$this->assertCount( 1, Queer_Rules::evaluate( $character ) );
	}

	public function test_evaluate_ignores_a_character_with_no_id(): void {
		$character            = $this->character( false, true );
		$character['post_id'] = 0;

		$this->assertSame( array(), Queer_Rules::evaluate( $character ) );
	}
}
