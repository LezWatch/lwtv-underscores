<?php
/**
 * Unit tests for the on-air comparison. The year is a parameter, which is what
 * makes these possible: a rule that asks the clock what day it is can only be
 * tested on the days the answer happens to suit.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\On_Air_Rules;

class OnAirRulesTest extends TestCase {

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
	 * should_be_on_air()
	 */

	public function test_a_show_still_airing_is_on_air(): void {
		$this->assertSame(
			'yes',
			On_Air_Rules::should_be_on_air( array( 'start' => '2004', 'finish' => 'current' ), 2026 )
		);
	}

	public function test_a_finished_show_is_not_on_air(): void {
		$this->assertSame(
			'no',
			On_Air_Rules::should_be_on_air( array( 'start' => '2004', 'finish' => '2009' ), 2026 )
		);
	}

	public function test_a_show_airing_across_this_year_is_on_air(): void {
		$this->assertSame(
			'yes',
			On_Air_Rules::should_be_on_air( array( 'start' => '2024', 'finish' => '2027' ), 2026 )
		);
	}

	public function test_the_boundary_years_count_as_on_air(): void {
		$this->assertSame( 'yes', On_Air_Rules::should_be_on_air( array( 'start' => '2026', 'finish' => '2026' ), 2026 ) );
		$this->assertSame( 'no', On_Air_Rules::should_be_on_air( array( 'start' => '2027', 'finish' => '2028' ), 2026 ) );
	}

	public function test_missing_airdates_are_not_on_air(): void {
		$this->assertSame( 'no', On_Air_Rules::should_be_on_air( array( 'start' => '', 'finish' => '' ), 2026 ) );
		$this->assertSame( 'no', On_Air_Rules::should_be_on_air( array( 'start' => '2004', 'finish' => '' ), 2026 ) );
		$this->assertSame( 'no', On_Air_Rules::should_be_on_air( array(), 2026 ) );
	}

	public function test_the_same_show_answers_differently_in_a_different_year(): void {
		// The reason the year is injected. This show ended in 2009.
		$airdates = array( 'start' => '2004', 'finish' => '2009' );

		$this->assertSame( 'yes', On_Air_Rules::should_be_on_air( $airdates, 2006 ) );
		$this->assertSame( 'no', On_Air_Rules::should_be_on_air( $airdates, 2026 ) );
	}

	/*
	 * evaluate()
	 */

	public function test_an_agreeing_flag_reports_nothing(): void {
		$show = array(
			'post_id'  => 10,
			'on_air'   => 'no',
			'airdates' => array( 'start' => '2004', 'finish' => '2009' ),
			'year'     => 2026,
		);

		$this->assertSame( array(), On_Air_Rules::evaluate( $show ) );
	}

	public function test_a_disagreeing_flag_is_reported_with_both_values(): void {
		$show = array(
			'post_id'  => 10,
			'on_air'   => 'yes',
			'airdates' => array( 'start' => '2004', 'finish' => '2009' ),
			'year'     => 2026,
		);

		$findings = On_Air_Rules::evaluate( $show );

		$this->assertSame( array( 'show-onair-mismatch' ), $this->types( $findings ) );
		$this->assertSame( 'On-air meta (yes) does not match actual on-air status (no).', $findings[0]['message'] );
		$this->assertSame( array( 'meta' => 'yes', 'actual' => 'no' ), $findings[0]['context'] );
		$this->assertTrue( $findings[0]['fixable'] );
	}

	public function test_no_stored_flag_is_its_own_finding(): void {
		$show = array(
			'post_id'  => 10,
			'on_air'   => '',
			'airdates' => array( 'start' => '2004', 'finish' => 'current' ),
			'year'     => 2026,
		);

		$this->assertSame( array( 'show-onair-no-data' ), $this->types( On_Air_Rules::evaluate( $show ) ) );
	}

	public function test_the_flag_is_compared_case_insensitively(): void {
		// It has been written by hand and by ACF over the years.
		$show = array(
			'post_id'  => 10,
			'on_air'   => 'Yes',
			'airdates' => array( 'start' => '2004', 'finish' => 'current' ),
			'year'     => 2026,
		);

		$this->assertSame( array(), On_Air_Rules::evaluate( $show ) );
	}

	public function test_no_airdates_but_a_matching_flag_reports_nothing(): void {
		/*
		 * The dead half of the original condition: it tested the *computed* value
		 * for emptiness too, but that is always 'yes' or 'no'. A show with no
		 * airdates computes to 'no', and if the flag says 'no' there is nothing to
		 * report here — the Shows check reports the missing airdates itself.
		 */
		$show = array(
			'post_id'  => 10,
			'on_air'   => 'no',
			'airdates' => array( 'start' => '', 'finish' => '' ),
			'year'     => 2026,
		);

		$this->assertSame( array(), On_Air_Rules::evaluate( $show ) );
	}

	public function test_evaluate_ignores_a_show_with_no_id(): void {
		$this->assertSame( array(), On_Air_Rules::evaluate( array( 'post_id' => 0, 'on_air' => '' ) ) );
	}
}
