<?php
/**
 * Unit tests for the submit-time duplicate verdict.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Duplicate_Verdict;

class DuplicateVerdictTest extends TestCase {

	private function tabs( array $over = array() ): array {
		return array_merge(
			array(
				'to_add'          => array(),
				'completed'       => array(),
				'questionable'    => array(),
				'legacy_new'      => array(),
				'legacy_existing' => array(),
			),
			$over
		);
	}

	private function row( int $row, string $name, string $show, string $status = 'Needs Verification' ): array {
		return array(
			'row'   => $row,
			'cells' => array(
				'Name'       => $name,
				'Show'       => $show,
				'Status'     => $status,
				'Queerness'  => '',
				'Form Entry' => '',
			),
		);
	}

	// Show matching.

	public function test_show_matches_on_title(): void {
		$names = array(
			10 => array( 'The Blue Hour' ),
			11 => array( 'Red' ),
		);
		$this->assertSame( array( 10 ), Duplicate_Verdict::matching_show_ids( 'blue hour', $names ) );
	}

	public function test_show_matches_on_alternate_title(): void {
		$names = array(
			10 => array( 'La Hora Azul', 'The Blue Hour' ),
		);
		$this->assertSame( array( 10 ), Duplicate_Verdict::matching_show_ids( 'Blue Hour!', $names ) );
	}

	public function test_show_match_ignores_case_accents_and_punctuation(): void {
		$names = array( 7 => array( 'Élite' ) );
		$this->assertSame( array( 7 ), Duplicate_Verdict::matching_show_ids( 'ELITE.', $names ) );
	}

	public function test_show_match_returns_each_id_once(): void {
		$names = array( 7 => array( 'Elite', 'Élite' ) );
		$this->assertSame( array( 7 ), Duplicate_Verdict::matching_show_ids( 'elite', $names ) );
	}

	public function test_show_match_empty_title_matches_nothing(): void {
		$this->assertSame( array(), Duplicate_Verdict::matching_show_ids( '  ', array( 1 => array( '' ) ) ) );
	}

	public function test_show_match_does_not_partial_match(): void {
		$this->assertSame( array(), Duplicate_Verdict::matching_show_ids( 'Blue', array( 1 => array( 'Blue Hour' ) ) ) );
	}

	// Verdicts.

	public function test_none_without_characters(): void {
		$v = Duplicate_Verdict::decide( array(), 'Blue', array( 'Satya Patel' ), $this->tabs() );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_added_when_every_character_is_on_lwtv(): void {
		$v = Duplicate_Verdict::decide( array( 'Satya', 'Jo Smith' ), 'Blue', array( 'Satya Patel', 'Jo Smith' ), $this->tabs() );
		$this->assertSame( Duplicate_Verdict::ADDED, $v['verdict'] );
		$this->assertCount( 2, $v['lines'] );
	}

	public function test_none_when_one_character_is_new(): void {
		$v = Duplicate_Verdict::decide( array( 'Satya', 'Morgan' ), 'Blue', array( 'Satya Patel' ), $this->tabs() );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_todo_when_every_character_is_on_to_add(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->row( 5, 'Morgan', 'Blue' ), $this->row( 6, 'Jo', 'Blue' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Morgan', 'Jo' ), 'The Blue', array(), $tabs );
		$this->assertSame( Duplicate_Verdict::TODO, $v['verdict'] );
	}

	public function test_todo_needs_the_show_to_match_too(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->row( 5, 'Morgan', 'Red' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $tabs );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_todo_when_mixed_lwtv_and_to_add(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->row( 5, 'Morgan', 'Blue' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Satya', 'Morgan' ), 'Blue', array( 'Satya Patel' ), $tabs );
		$this->assertSame( Duplicate_Verdict::TODO, $v['verdict'] );
	}

	public function test_todo_when_on_legacy_tab(): void {
		$legacy = array(
			array(
				'row'   => 3,
				'cells' => array(
					'Show' => 'Blue',
					'Info' => 'Morgan is bi, dates Jo.',
				),
			),
		);
		$v      = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $this->tabs( array( 'legacy_new' => $legacy ) ) );
		$this->assertSame( Duplicate_Verdict::TODO, $v['verdict'] );
	}

	public function test_todo_when_to_add_match_is_ambiguous(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->row( 5, 'Morgan', 'Blue' ), $this->row( 9, 'Morgan Lee', 'Blue' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $tabs );
		$this->assertSame( Duplicate_Verdict::TODO, $v['verdict'] );
	}

	public function test_completed_is_not_a_duplicate(): void {
		$tabs = $this->tabs( array( 'completed' => array( $this->row( 5, 'Morgan', 'Blue', 'Added' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $tabs );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_questionable_is_not_a_duplicate(): void {
		$tabs = $this->tabs( array( 'questionable' => array( $this->row( 5, 'Morgan', 'Blue', 'Rejected' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $tabs );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_placeholder_name_is_never_a_duplicate(): void {
		$v = Duplicate_Verdict::decide( array( 'Satya', 'Multiple' ), 'Blue', array( 'Satya Patel' ), $this->tabs() );
		$this->assertSame( Duplicate_Verdict::NONE, $v['verdict'] );
	}

	public function test_lines_say_where_each_character_was_found(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->row( 5, 'Morgan', 'Blue' ) ) ) );
		$v    = Duplicate_Verdict::decide( array( 'Satya', 'Morgan' ), 'Blue', array( 'Satya Patel' ), $tabs );
		$this->assertSame(
			array(
				'Satya (Blue): already on LWTV.',
				'Morgan (Blue): already on To Add row 5.',
			),
			$v['lines']
		);
	}

	public function test_lines_are_empty_when_not_a_duplicate(): void {
		$v = Duplicate_Verdict::decide( array( 'Morgan' ), 'Blue', array(), $this->tabs() );
		$this->assertSame( array(), $v['lines'] );
	}
}
