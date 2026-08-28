<?php
/**
 * Unit tests for the Bury Your Queers rules, and in particular the gate that
 * decides when a missing trope is worth reporting. That gate was wrong for as
 * long as it was tangled up with the ACF reads (DEBUGGER-REVIEW.md 1.9c), and
 * the table at the bottom of this file is what it should do.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Byq_Rules;

class ByqRulesTest extends TestCase {

	/**
	 * One show row.
	 *
	 * @param  int    $show_id   Show ID.
	 * @param  bool   $has_trope Whether the show carries dead-queers.
	 * @param  string $title     Show title.
	 * @return array
	 */
	private function show( int $show_id, bool $has_trope, string $title = 'A Show' ): array {
		return array(
			'show_id'   => $show_id,
			'title'     => $title,
			'has_trope' => $has_trope,
		);
	}

	/**
	 * A dead character.
	 *
	 * @param  array $shows          Show rows.
	 * @param  bool  $has_death_year Whether a death year is recorded.
	 * @return array
	 */
	private function character( array $shows, bool $has_death_year = true ): array {
		return array(
			'post_id'        => 42,
			'has_death_year' => $has_death_year,
			'shows'          => $shows,
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

	/*
	 * death_year()
	 */

	public function test_a_recorded_death_year_reports_nothing(): void {
		$this->assertSame( array(), Byq_Rules::death_year( $this->character( array(), true ) ) );
	}

	public function test_a_missing_death_year_is_reported(): void {
		$this->assertSame(
			array( 'char-no-death-year' ),
			$this->types( Byq_Rules::death_year( $this->character( array(), false ) ) )
		);
	}

	/*
	 * tropes() — the invariant is on the shows that DO carry it.
	 */

	public function test_one_show_with_the_trope_is_correct(): void {
		// The Show A / Show B case: killed on A, was also on B, which never
		// killed her and correctly has no trope.
		$shows = array(
			$this->show( 1, true, 'Show A' ),
			$this->show( 2, false, 'Show B' ),
		);

		$this->assertSame( array(), Byq_Rules::tropes( 42, $shows ) );
	}

	public function test_her_only_show_missing_the_trope_is_reported(): void {
		$findings = Byq_Rules::tropes( 42, array( $this->show( 1, false, 'Show A' ) ) );

		$this->assertSame( array( 'char-show-no-byq-trope' ), $this->types( $findings ) );
		$this->assertStringContainsString( 'Show A', $findings[0]['message'] );
		$this->assertSame( array( 'show_id' => 1 ), $findings[0]['context'] );
	}

	public function test_no_show_with_the_trope_reports_every_one(): void {
		// Nobody recorded the death anywhere, so one of these is the right fix.
		$shows = array(
			$this->show( 1, false, 'Show A' ),
			$this->show( 2, false, 'Show B' ),
		);

		$this->assertCount( 2, Byq_Rules::tropes( 42, $shows ) );
	}

	public function test_one_of_three_with_the_trope_is_correct(): void {
		$shows = array(
			$this->show( 1, true ),
			$this->show( 2, false ),
			$this->show( 3, false ),
		);

		$this->assertSame( array(), Byq_Rules::tropes( 42, $shows ) );
	}

	public function test_two_shows_claiming_a_death_surfaces_the_character(): void {
		// Legitimate if another queer character died on the second show, but
		// worth an eyeball.
		$shows = array(
			$this->show( 1, true ),
			$this->show( 2, true ),
			$this->show( 3, false ),
		);

		$this->assertCount( 1, Byq_Rules::tropes( 42, $shows ) );
	}

	public function test_the_gate_can_only_suppress_never_invent(): void {
		// Every show carries the trope, so there is nothing missing to report,
		// even though two-with-the-trope fails the invariant.
		$shows = array(
			$this->show( 1, true ),
			$this->show( 2, true ),
		);

		$this->assertSame( array(), Byq_Rules::tropes( 42, $shows ) );
	}

	/*
	 * evaluate() — the two rules together, and the 1.9c behaviour table.
	 */

	public function test_a_death_year_reports_even_when_the_tropes_are_fine(): void {
		/*
		 * The 1.9c regression. The missing death year used to be counted into the
		 * trope gate, where it cancelled itself out: this character was dropped
		 * from the report entirely.
		 */
		$character = $this->character(
			array(
				$this->show( 1, true ),
				$this->show( 2, false ),
			),
			false
		);

		$this->assertSame( array( 'char-no-death-year' ), $this->types( Byq_Rules::evaluate( $character ) ) );
	}

	public function test_both_problems_report_together(): void {
		$character = $this->character( array( $this->show( 1, false ) ), false );

		$this->assertSame(
			array( 'char-no-death-year', 'char-show-no-byq-trope' ),
			$this->types( Byq_Rules::evaluate( $character ) )
		);
	}

	public function test_a_character_with_no_shows_reports_nothing(): void {
		// A bigger problem than either of these, and the Characters check reports
		// it as char-no-shows.
		$this->assertSame( array(), Byq_Rules::evaluate( $this->character( array(), false ) ) );
	}

	public function test_evaluate_ignores_a_character_with_no_id(): void {
		$character            = $this->character( array( $this->show( 1, false ) ), false );
		$character['post_id'] = 0;

		$this->assertSame( array(), Byq_Rules::evaluate( $character ) );
	}

	public function test_a_correct_character_reports_nothing(): void {
		$character = $this->character(
			array(
				$this->show( 1, true ),
				$this->show( 2, false ),
			),
			true
		);

		$this->assertSame( array(), Byq_Rules::evaluate( $character ) );
	}
}
