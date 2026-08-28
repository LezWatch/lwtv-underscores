<?php
/**
 * Unit tests for the character rules — clichés, the dead-without-a-date
 * cross-check that BYQ and the death statistics both depend on, show rows, and
 * actors. Untestable until the ACF and term reads moved into
 * Collect\Character_Collector.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Character_Rules;
use LWTV\Debugger\Build\Issue_Registry;

class CharacterRulesTest extends TestCase {

	/**
	 * A character with nothing wrong with them.
	 *
	 * @param  array $overrides Keys to replace.
	 * @return array
	 */
	private function character( array $overrides = array() ): array {
		$character = array(
			'post_id'    => 42,
			'cliches'    => array( 'none' ),
			'last_death' => '',
			'has_actors' => true,
			'shows'      => array(
				array(
					'show_id'   => 10,
					'title'     => 'The L Word',
					'has_years' => true,
					'has_role'  => true,
				),
			),
		);

		return array_merge( $character, $overrides );
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
	 * evaluate()
	 */

	public function test_a_complete_character_has_no_findings(): void {
		$this->assertSame( array(), Character_Rules::evaluate( $this->character() ) );
	}

	public function test_evaluate_ignores_a_character_with_no_id(): void {
		$this->assertSame( array(), Character_Rules::evaluate( $this->character( array( 'post_id' => 0 ) ) ) );
	}

	public function test_evaluate_collects_from_every_rule(): void {
		$character = $this->character(
			array(
				'cliches'    => array(),
				'has_actors' => false,
				'shows'      => array(),
			)
		);

		$this->assertSame(
			array( 'char-missing-cliche', 'char-no-shows', 'char-no-actors' ),
			$this->types( Character_Rules::evaluate( $character ) )
		);
	}

	/*
	 * cliches()
	 */

	public function test_no_cliches_is_reported(): void {
		$character = $this->character( array( 'cliches' => array() ) );

		$this->assertSame( array( 'char-missing-cliche' ), $this->types( Character_Rules::cliches( $character ) ) );
	}

	public function test_the_none_cliche_counts_as_having_one(): void {
		$this->assertSame( array(), Character_Rules::cliches( $this->character() ) );
	}

	/*
	 * death()
	 */

	public function test_dead_without_a_date_is_reported(): void {
		$character = $this->character(
			array(
				'cliches'    => array( 'dead' ),
				'last_death' => '',
			)
		);

		$this->assertSame( array( 'char-dead-no-date' ), $this->types( Character_Rules::death( $character ) ) );
	}

	public function test_dead_with_a_date_is_fine(): void {
		$character = $this->character(
			array(
				'cliches'    => array( 'dead' ),
				'last_death' => '2009-03-08',
			)
		);

		$this->assertSame( array(), Character_Rules::death( $character ) );
	}

	public function test_a_living_character_with_no_date_is_fine(): void {
		$character = $this->character(
			array(
				'cliches'    => array( 'none' ),
				'last_death' => '',
			)
		);

		$this->assertSame( array(), Character_Rules::death( $character ) );
	}

	public function test_dead_is_matched_exactly(): void {
		// 'dead-queers' is a show trope, not this cliché; a substring match
		// would flag every character on a show that has one.
		$character = $this->character(
			array(
				'cliches'    => array( 'dead-queers' ),
				'last_death' => '',
			)
		);

		$this->assertSame( array(), Character_Rules::death( $character ) );
	}

	/*
	 * shows()
	 */

	public function test_no_shows_is_one_finding(): void {
		$character = $this->character( array( 'shows' => array() ) );

		$this->assertSame( array( 'char-no-shows' ), $this->types( Character_Rules::shows( $character ) ) );
	}

	public function test_a_row_with_no_years_names_the_show(): void {
		$character = $this->character(
			array(
				'shows' => array(
					array(
						'show_id'   => 10,
						'title'     => 'The L Word',
						'has_years' => false,
						'has_role'  => true,
					),
				),
			)
		);

		$findings = Character_Rules::shows( $character );

		$this->assertSame( array( 'char-no-years' ), $this->types( $findings ) );
		$this->assertSame( 'No years on air set for The L Word.', $findings[0]['message'] );
	}

	public function test_a_row_with_no_role_names_the_show(): void {
		$character = $this->character(
			array(
				'shows' => array(
					array(
						'show_id'   => 10,
						'title'     => 'The L Word',
						'has_years' => true,
						'has_role'  => false,
					),
				),
			)
		);

		$findings = Character_Rules::shows( $character );

		$this->assertSame( 'No role set for The L Word.', $findings[0]['message'] );
	}

	public function test_a_row_naming_no_show_is_reported_and_not_named(): void {
		// The old copy appended the title unconditionally, so this read
		// "No role set for ." Naming nothing is better than naming nothing badly.
		$character = $this->character(
			array(
				'shows' => array(
					array(
						'show_id'   => 0,
						'title'     => '',
						'has_years' => false,
						'has_role'  => false,
					),
				),
			)
		);

		$findings = Character_Rules::shows( $character );

		$this->assertSame(
			array( 'char-no-years', 'char-no-role', 'char-no-show-name' ),
			$this->types( $findings )
		);
		$this->assertSame( 'No years on air set.', $findings[0]['message'] );
		$this->assertSame( 'No role set.', $findings[1]['message'] );
	}

	public function test_each_bad_row_reports_separately(): void {
		$character = $this->character(
			array(
				'shows' => array(
					array(
						'show_id'   => 10,
						'title'     => 'The L Word',
						'has_years' => false,
						'has_role'  => true,
					),
					array(
						'show_id'   => 11,
						'title'     => 'Buffy',
						'has_years' => false,
						'has_role'  => true,
					),
				),
			)
		);

		$findings = Character_Rules::shows( $character );

		$this->assertCount( 2, $findings );
		$this->assertSame( 'No years on air set for The L Word.', $findings[0]['message'] );
		$this->assertSame( 'No years on air set for Buffy.', $findings[1]['message'] );
	}

	public function test_a_good_row_reports_nothing(): void {
		$this->assertSame( array(), Character_Rules::shows( $this->character() ) );
	}

	/*
	 * actors()
	 */

	public function test_no_actors_is_reported(): void {
		$character = $this->character( array( 'has_actors' => false ) );

		$this->assertSame( array( 'char-no-actors' ), $this->types( Character_Rules::actors( $character ) ) );
	}

	public function test_having_actors_is_fine(): void {
		$this->assertSame( array(), Character_Rules::actors( $this->character() ) );
	}

	/*
	 * The collector contract.
	 */

	public function test_the_rules_declare_what_they_need(): void {
		$this->assertSame( array( 'lezchars_last_death' ), Character_Rules::meta_keys() );
		$this->assertSame( array( 'lez_cliches' ), Character_Rules::taxonomies() );
	}

	public function test_every_issue_type_used_is_registered(): void {
		$character = $this->character(
			array(
				'cliches'    => array( 'dead' ),
				'last_death' => '',
				'has_actors' => false,
				'shows'      => array(
					array(
						'show_id'   => 0,
						'title'     => '',
						'has_years' => false,
						'has_role'  => false,
					),
				),
			)
		);

		foreach ( $this->types( Character_Rules::evaluate( $character ) ) as $issue_type ) {
			$this->assertTrue( Issue_Registry::exists( $issue_type ), $issue_type . ' is not in the registry' );
		}
	}
}
