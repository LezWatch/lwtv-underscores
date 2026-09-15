<?php
/**
 * Unit tests for the exclusion registry.
 *
 * qualifies() carries the weight here. Our booleans are stored three different
 * ways -- plain ACF true_false keeps a "0" row, the legacy 'on' fields delete
 * theirs, and the queer override is a select defaulting to the string
 * 'undefined' -- and picking the wrong rule does not error. It silently reports
 * the entire catalogue as overridden, which looks like a data disaster and is
 * really just a mismatched query. So the tests below pin each storage shape.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Exclusion_Registry;

class ExclusionRegistryTest extends TestCase {

	/*
	 * qualifies() -- the three storage shapes
	 */

	public function test_a_ticked_acf_boolean_qualifies(): void {
		$this->assertTrue( Exclusion_Registry::qualifies( '1', '1' ) );
	}

	public function test_an_unticked_acf_boolean_does_not_qualify(): void {
		// The case that matters: ACF true_false leaves this row behind, so a
		// query on EXISTS returns it and only this check keeps it out.
		$this->assertFalse( Exclusion_Registry::qualifies( '0', '1' ) );
	}

	public function test_a_legacy_on_field_qualifies_on_the_string(): void {
		$this->assertTrue( Exclusion_Registry::qualifies( 'on', 'on' ) );
		$this->assertFalse( Exclusion_Registry::qualifies( '1', 'on' ) );
	}

	public function test_a_select_qualifies_on_any_real_choice(): void {
		$this->assertTrue( Exclusion_Registry::qualifies( 'is_queer', Exclusion_Registry::MATCH_ANY ) );
		$this->assertTrue( Exclusion_Registry::qualifies( 'not_queer', Exclusion_Registry::MATCH_ANY ) );
	}

	public function test_the_selects_default_does_not_qualify(): void {
		// 'undefined' is a real stored value, not an absence, and it means
		// "No Selection (Default)".
		$this->assertFalse( Exclusion_Registry::qualifies( 'undefined', Exclusion_Registry::MATCH_ANY ) );
	}

	public function test_empty_and_zero_never_qualify_under_match_any(): void {
		$this->assertFalse( Exclusion_Registry::qualifies( '', Exclusion_Registry::MATCH_ANY ) );
		$this->assertFalse( Exclusion_Registry::qualifies( '0', Exclusion_Registry::MATCH_ANY ) );
		$this->assertFalse( Exclusion_Registry::qualifies( '   ', Exclusion_Registry::MATCH_ANY ) );
	}

	public function test_every_unset_value_is_rejected_by_qualifies(): void {
		// The caller builds a SQL "NOT IN" from this same constant, so the two
		// filters must agree. If someone adds a value here and qualifies() stops
		// honouring it, the query and the PHP would disagree about what counts.
		foreach ( Exclusion_Registry::UNSET_VALUES as $value ) {
			$this->assertFalse(
				Exclusion_Registry::qualifies( $value, Exclusion_Registry::MATCH_ANY ),
				var_export( $value, true ) . ' is in UNSET_VALUES but qualifies() accepts it'
			);
		}
	}

	public function test_whitespace_is_caught_by_php_but_not_by_sql(): void {
		// Documents why qualifies() still runs after the query has filtered: a
		// value of '   ' passes SQL NOT IN and is rejected here.
		$this->assertNotContains( '   ', Exclusion_Registry::UNSET_VALUES );
		$this->assertFalse( Exclusion_Registry::qualifies( '   ', Exclusion_Registry::MATCH_ANY ) );
	}

	/*
	 * describe() -- the Setting column
	 */

	public function test_the_wikidata_toggle_distinguishes_its_two_meanings(): void {
		// The entire reason this column exists. Same toggle, same "1", two
		// completely different editorial statements.
		$this->assertSame(
			'Using Q42',
			Exclusion_Registry::describe(
				'wikidata_ignore',
				array(
					'value'      => '1',
					'manual_qid' => 'Q42',
				)
			)
		);

		$this->assertSame(
			'No WikiData item',
			Exclusion_Registry::describe(
				'wikidata_ignore',
				array(
					'value'      => '1',
					'manual_qid' => '',
				)
			)
		);
	}

	public function test_the_queer_override_shows_the_label_not_the_slug(): void {
		$this->assertSame( 'Is Queer', Exclusion_Registry::describe( 'queer_checker', array( 'value' => 'is_queer' ) ) );
		$this->assertSame( 'Is NOT Queer', Exclusion_Registry::describe( 'queer_checker', array( 'value' => 'not_queer' ) ) );
	}

	public function test_a_tvmaze_override_names_the_id_it_uses_instead(): void {
		$this->assertSame(
			'Using 12345',
			Exclusion_Registry::describe(
				'tvmaze_ignore',
				array(
					'value'     => '1',
					'manual_id' => '12345',
				)
			)
		);
	}

	public function test_describe_never_returns_an_empty_cell_for_a_known_boolean(): void {
		$this->assertSame( 'Yes', Exclusion_Registry::describe( 'no_known_chars', array( 'value' => '1' ) ) );
		$this->assertSame( 'Yes', Exclusion_Registry::describe( 'dead_checker', array( 'value' => 'on' ) ) );
	}

	/*
	 * staleness() -- overrides that have stopped being true
	 */

	public function test_no_known_characters_goes_stale_once_characters_exist(): void {
		// The flag suppresses a panel readers should see. Nothing else on the
		// site would ever mention that it is now wrong.
		$this->assertStringContainsString(
			'6 character',
			Exclusion_Registry::staleness( 'no_known_chars', array( 'char_count' => 6 ) )
		);
	}

	public function test_no_known_characters_is_not_stale_with_no_characters(): void {
		$this->assertSame( '', Exclusion_Registry::staleness( 'no_known_chars', array( 'char_count' => 0 ) ) );
		$this->assertSame( '', Exclusion_Registry::staleness( 'no_known_chars', array() ) );
	}

	public function test_an_ignored_actor_still_holding_a_qid_is_flagged(): void {
		$this->assertStringContainsString(
			'Q999',
			Exclusion_Registry::staleness(
				'wikidata_ignore',
				array(
					'manual_qid' => '',
					'stored_qid' => 'Q999',
				)
			)
		);
	}

	public function test_a_corrected_actor_is_not_flagged_as_stale(): void {
		// Manual Q-ID set: the stored value being different is the point, not a
		// problem, so this must stay quiet.
		$this->assertSame(
			'',
			Exclusion_Registry::staleness(
				'wikidata_ignore',
				array(
					'manual_qid' => 'Q42',
					'stored_qid' => 'Q999',
				)
			)
		);
	}

	public function test_checks_with_nothing_to_go_stale_return_nothing(): void {
		$this->assertSame( '', Exclusion_Registry::staleness( 'dead_checker', array( 'value' => 'on' ) ) );
	}

	/*
	 * staleness() -- the queer override vs the stored flag
	 */

	public function test_a_queer_override_agreeing_with_the_stored_flag_is_quiet(): void {
		$this->assertSame(
			'',
			Exclusion_Registry::staleness(
				'queer_checker',
				array(
					'value'        => 'is_queer',
					'stored_queer' => '1',
				)
			)
		);
		$this->assertSame(
			'',
			Exclusion_Registry::staleness(
				'queer_checker',
				array(
					'value'        => 'not_queer',
					'stored_queer' => '',
				)
			)
		);
	}

	public function test_an_override_the_stored_flag_has_not_caught_up_with_is_flagged(): void {
		// The window between setting an override and recalculating the actor: the
		// actor page reads is_actor_queer() live, while the admin column, the ACF
		// labels, the REST endpoints and the statistics all read the stored flag.
		$this->assertStringContainsString(
			'calc actors',
			Exclusion_Registry::staleness(
				'queer_checker',
				array(
					'value'        => 'not_queer',
					'stored_queer' => '1',
				)
			)
		);
		$this->assertStringContainsString(
			'calc actors',
			Exclusion_Registry::staleness(
				'queer_checker',
				array(
					'value'        => 'is_queer',
					'stored_queer' => '',
				)
			)
		);
	}

	public function test_an_absent_stored_flag_counts_as_not_queer(): void {
		// A never-recalculated actor has no row at all. '' and a missing key must
		// read the same way, because that is how get_post_meta() behaves.
		$this->assertStringContainsString(
			'calc actors',
			Exclusion_Registry::staleness( 'queer_checker', array( 'value' => 'is_queer' ) )
		);
		$this->assertSame(
			'',
			Exclusion_Registry::staleness( 'queer_checker', array( 'value' => 'not_queer' ) )
		);
	}

	public function test_a_zero_stored_flag_counts_as_not_queer(): void {
		// update_post_meta() with a PHP false stores '', but a legacy '0' must not
		// read as queer either.
		$this->assertSame(
			'',
			Exclusion_Registry::staleness(
				'queer_checker',
				array(
					'value'        => 'not_queer',
					'stored_queer' => '0',
				)
			)
		);
	}

	/*
	 * The definitions themselves
	 */

	public function test_every_check_declares_what_the_renderer_needs(): void {
		foreach ( Exclusion_Registry::all() as $key => $check ) {
			foreach ( array( 'name', 'desc', 'cpt', 'meta', 'match', 'column', 'empty', 'context' ) as $required ) {
				$this->assertArrayHasKey( $required, $check, $key . ' is missing ' . $required );
			}

			$this->assertContains(
				$check['cpt'],
				array( Exclusion_Registry::CPT_ACTORS, Exclusion_Registry::CPT_SHOWS ),
				$key . ' names a CPT the renderer cannot map'
			);

			$this->assertIsArray( $check['context'], $key . ' has a non-array context map' );
		}
	}

	public function test_no_check_matches_on_zero(): void {
		// A definition matching '0' would report every unticked post as an
		// override. Cheap to assert, and the failure mode is severe enough that
		// it should not rely on review catching it.
		foreach ( Exclusion_Registry::all() as $key => $check ) {
			$this->assertNotSame( '0', $check['match'], $key . ' matches on an unticked value' );
		}
	}

	public function test_an_unknown_key_is_handled_rather_than_fatal(): void {
		$this->assertSame( array(), Exclusion_Registry::get( 'nope' ) );
		$this->assertFalse( Exclusion_Registry::exists( 'nope' ) );
		$this->assertSame( '', Exclusion_Registry::staleness( 'nope', array() ) );
	}

	public function test_the_registry_still_covers_the_two_original_tabs(): void {
		// Those two tabs have existing URLs people have bookmarked; the slugs
		// are part of the interface.
		$this->assertTrue( Exclusion_Registry::exists( 'queer_checker' ) );
		$this->assertTrue( Exclusion_Registry::exists( 'dead_checker' ) );
	}
}
