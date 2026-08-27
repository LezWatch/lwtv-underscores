<?php
/**
 * Unit tests for the actor completeness rules. Barely a rule, but the two
 * findings are independent and an actor can be missing both.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Actor_Completeness_Rules;

class ActorCompletenessRulesTest extends TestCase {

	/**
	 * Issue types from a rule result.
	 *
	 * @param  array $findings Findings.
	 * @return array<string>
	 */
	private function types( array $findings ): array {
		return array_column( $findings, 'issue_type' );
	}

	public function test_a_complete_actor_reports_nothing(): void {
		$actor = array(
			'post_id'   => 7,
			'has_image' => true,
			'has_bio'   => true,
		);

		$this->assertSame( array(), Actor_Completeness_Rules::evaluate( $actor ) );
	}

	public function test_a_missing_image_is_reported(): void {
		$actor = array(
			'post_id'   => 7,
			'has_image' => false,
			'has_bio'   => true,
		);

		$this->assertSame( array( 'actor-no-image' ), $this->types( Actor_Completeness_Rules::evaluate( $actor ) ) );
	}

	public function test_a_missing_bio_is_reported(): void {
		$actor = array(
			'post_id'   => 7,
			'has_image' => true,
			'has_bio'   => false,
		);

		$this->assertSame( array( 'actor-no-bio' ), $this->types( Actor_Completeness_Rules::evaluate( $actor ) ) );
	}

	public function test_a_brand_new_actor_is_missing_both(): void {
		$actor = array(
			'post_id'   => 7,
			'has_image' => false,
			'has_bio'   => false,
		);

		$this->assertSame(
			array( 'actor-no-image', 'actor-no-bio' ),
			$this->types( Actor_Completeness_Rules::evaluate( $actor ) )
		);
	}

	public function test_evaluate_ignores_an_actor_with_no_id(): void {
		$actor = array(
			'post_id'   => 0,
			'has_image' => false,
			'has_bio'   => false,
		);

		$this->assertSame( array(), Actor_Completeness_Rules::evaluate( $actor ) );
	}
}
