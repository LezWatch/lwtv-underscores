<?php
/**
 * Unit tests for the per-character triage decision.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Triage_Decision;

class TriageDecisionTest extends TestCase {

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

	private function to_add_row( int $row, string $name, string $show, string $entry = '' ): array {
		return array(
			'row'   => $row,
			'cells' => array(
				'Name'       => $name,
				'Show'       => $show,
				'Status'     => 'Needs Verification',
				'Form Entry' => $entry,
			),
		);
	}

	public function test_new_when_nowhere(): void {
		$d = Triage_Decision::decide( 'Satya', 'Blue', 1, array(), $this->tabs() );
		$this->assertSame( 'new', $d['action'] );
		$this->assertSame( 'not_found', $d['reason'] );
	}

	public function test_skip_when_on_lwtv_first_name(): void {
		$d = Triage_Decision::decide( 'Satya', 'Blue', 1, array( 'Satya Patel' ), $this->tabs() );
		$this->assertSame( 'skip', $d['action'] );
		$this->assertSame( 'lwtv', $d['reason'] );
	}

	public function test_lwtv_beats_to_add(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 214, 'Satya', 'Blue' ) ) ) );
		$d    = Triage_Decision::decide( 'Satya', 'Blue', 1, array( 'Satya' ), $tabs );
		$this->assertSame( 'skip', $d['action'] );
		$this->assertSame( 'lwtv', $d['reason'] );
	}

	public function test_append_single_to_add_match(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 214, 'Satya', 'Blue' ) ) ) );
		$d    = Triage_Decision::decide( 'Satya', 'Blue', 1, array(), $tabs );
		$this->assertSame( 'append', $d['action'] );
		$this->assertSame( 214, $d['row'] );
	}

	public function test_ambiguous_to_add_makes_new(): void {
		$tabs = $this->tabs(
			array(
				'to_add' => array(
					$this->to_add_row( 40, 'Sam Lee', 'X' ),
					$this->to_add_row( 12, 'Sam', 'X' ),
				),
			)
		);
		$d    = Triage_Decision::decide( 'Sam', 'X', 1, array(), $tabs );
		$this->assertSame( 'new', $d['action'] );
		$this->assertSame( 'ambiguous', $d['reason'] );
		$this->assertSame( array( 12, 40 ), $d['rows'] );
	}

	public function test_show_must_match_too(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 214, 'Satya', 'Other Show' ) ) ) );
		$d    = Triage_Decision::decide( 'Satya', 'Blue', 1, array(), $tabs );
		$this->assertSame( 'new', $d['action'] );
	}

	public function test_skip_completed_and_questionable(): void {
		$completed = array(
			'row'   => 5,
			'cells' => array(
				'Name'      => 'Satya',
				'Show'      => 'Blue',
				'Status'    => 'Canonical',
				'Queerness' => 'Yes',
			),
		);
		$d         = Triage_Decision::decide( 'Satya', 'Blue', 1, array(), $this->tabs( array( 'completed' => array( $completed ) ) ) );
		$this->assertSame( 'skip', $d['action'] );
		$this->assertSame( 'completed', $d['reason'] );
		$this->assertStringContainsString( 'Canonical', $d['detail'] );

		$questionable          = $completed;
		$questionable['cells'] = array_merge(
			$completed['cells'],
			array(
				'Status'    => 'Debunked',
				'Queerness' => 'No',
			)
		);
		$d                     = Triage_Decision::decide( 'Satya', 'Blue', 1, array(), $this->tabs( array( 'questionable' => array( $questionable ) ) ) );
		$this->assertSame( 'skip', $d['action'] );
		$this->assertSame( 'questionable', $d['reason'] );
		$this->assertStringContainsString( 'Debunked', $d['detail'] );
	}

	public function test_skip_legacy_mention(): void {
		$legacy = array(
			'row'   => 3,
			'cells' => array(
				'Show' => 'A Man on the Inside',
				'Info' => 'Julie - blah. Didi - interested in Julie',
			),
		);
		$d      = Triage_Decision::decide( 'Didi', 'A Man on the Inside', 1, array(), $this->tabs( array( 'legacy_new' => array( $legacy ) ) ) );
		$this->assertSame( 'skip', $d['action'] );
		$this->assertSame( 'legacy_new', $d['reason'] );

		$d = Triage_Decision::decide( 'Didi', 'A Man on the Inside', 1, array(), $this->tabs( array( 'legacy_existing' => array( $legacy ) ) ) );
		$this->assertSame( 'legacy_existing', $d['reason'] );
	}

	public function test_placeholder_rows_never_block(): void {
		$row = array(
			'row'   => 9,
			'cells' => array(
				'Name'      => 'Multiple',
				'Show'      => 'Avatar: The Last Airbender',
				'Status'    => 'Debunked',
				'Queerness' => 'No',
			),
		);
		$d   = Triage_Decision::decide( 'Uma', 'Avatar: The Last Airbender', 1, array(), $this->tabs( array( 'questionable' => array( $row ) ) ) );
		$this->assertSame( 'new', $d['action'] );
	}

	public function test_done_on_retry_new_row(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 1083, 'Satya', 'Blue', '4521' ) ) ) );
		$d    = Triage_Decision::decide( 'Satya', 'Blue', 4521, array(), $tabs );
		$this->assertSame( 'done', $d['action'] );
		$this->assertSame( 1083, $d['row'] );

		$d = Triage_Decision::decide( 'Satya', 'Blue', 4600, array(), $tabs );
		$this->assertSame( 'append', $d['action'] );
		$this->assertSame( 1083, $d['row'] );
	}

	public function test_done_on_retry_append(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 214, 'Satya', 'Blue', '4100, 4521' ) ) ) );
		$d    = Triage_Decision::decide( 'Satya', 'Blue', 4521, array(), $tabs );
		$this->assertSame( 'done', $d['action'] );
	}

	public function test_done_on_retry_fallback(): void {
		$tabs = $this->tabs( array( 'to_add' => array( $this->to_add_row( 77, '', 'Blue', '4521' ) ) ) );
		$d    = Triage_Decision::decide( '', 'Blue', 4521, array(), $tabs );
		$this->assertSame( 'done', $d['action'] );
		$this->assertSame( 77, $d['row'] );

		$d = Triage_Decision::decide( '', 'Blue', 4522, array(), $tabs );
		$this->assertSame( 'new', $d['action'] );
	}

	public function test_note_line_examples(): void {
		$this->assertSame(
			'Satya (Blue): added to To Add.',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'new',
					'reason' => 'not_found',
				)
			)
		);
		$this->assertSame(
			'Satya (Blue): appended to To Add row 214.',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'append',
					'reason' => 'to_add',
					'row'    => 214,
				)
			)
		);
		$this->assertSame(
			'Satya (Blue): matched To Add rows 12, 40; added a new row instead.',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'new',
					'reason' => 'ambiguous',
					'rows'   => array( 12, 40 ),
				)
			)
		);
		$this->assertSame(
			'Satya (Blue): already on LWTV, skipped.',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'skip',
					'reason' => 'lwtv',
				)
			)
		);
		$this->assertSame(
			'Satya (Blue): on Questionable (Debunked, No), skipped.',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'skip',
					'reason' => 'questionable',
					'detail' => 'Debunked, No',
				)
			)
		);
		$this->assertSame(
			'Satya (Blue): already written by this entry (row 1083).',
			Triage_Decision::note_line(
				'Satya',
				'Blue',
				array(
					'action' => 'done',
					'reason' => 'done',
					'row'    => 1083,
				)
			)
		);
		$this->assertStringStartsWith(
			'(unnamed) (Blue):',
			Triage_Decision::note_line(
				'',
				'Blue',
				array(
					'action' => 'new',
					'reason' => 'not_found',
				)
			)
		);
	}
}
