<?php
/**
 * Unit tests for the To Add write plan (decide all, updates before new rows).
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Row_Builder;
use LWTV\Plugins\Gravity_Forms\Build\Triage_Plan;

class TriagePlanTest extends TestCase {

	private const MAP = array(
		'Status'      => 0,
		'Queerness'   => 1,
		'Name'        => 2,
		'Actor'       => 3,
		'Show'        => 4,
		'Cliches'     => 5,
		'Sexuality'   => 6,
		'Gender'      => 7,
		'Description' => 8,
		'Image'       => 9,
		'Form Entry'  => 10,
	);

	private const ENTRY = 4521;

	private const LAST_ROW = 1084;

	private function character( string $name, array $over = array() ): array {
		return array_merge( Row_Builder::fallback_character(), array( 'name' => $name ), $over );
	}

	private function row( int $row, string $name, string $show, array $over = array() ): array {
		$cells = array_fill_keys( array_keys( self::MAP ), '' );
		return array(
			'row'   => $row,
			'cells' => array_merge(
				$cells,
				array(
					'Status'      => 'Unreviewed',
					'Name'        => $name,
					'Show'        => $show,
					'Actor'       => 'Someone Human',
					'Description' => 'Human notes.',
				),
				$over
			),
		);
	}

	private function tabs( array $to_add ): array {
		return array(
			'to_add'          => $to_add,
			'completed'       => array(),
			'questionable'    => array(),
			'legacy_new'      => array(),
			'legacy_existing' => array(),
		);
	}

	private function plan( array $characters, array $to_add ): array {
		return Triage_Plan::build( $characters, 'Blue', 'Satya and Lara are a couple.', self::ENTRY, '2026-11-09', array(), $this->tabs( $to_add ), self::MAP, self::LAST_ROW );
	}

	public function test_append_and_new_are_split_and_new_rows_go_past_the_last_row(): void {
		// Row 150 is blank on the sheet, so Sheet_Table::rows() never returns it.
		$to_add = array( $this->row( 149, 'Other', 'Elsewhere' ), $this->row( 151, 'Other Two', 'Elsewhere' ), $this->row( 200, 'Satya', 'Blue' ) );
		$plan   = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Lara' ) ), $to_add );

		$this->assertCount( 1, $plan['updates'] );
		$this->assertSame( 0, $plan['updates'][0]['item'] );
		$this->assertSame( 200, $plan['updates'][0]['row'] );
		$this->assertSame(
			array(
				'A' => 'Needs Verification',
				'I' => "Human notes.\n\n— Added 2026-11-09 from form entry #4521 —\nSatya and Lara are a couple.",
				'K' => '4521',
			),
			$plan['updates'][0]['letters']
		);

		$this->assertCount( 1, $plan['appends'] );
		$this->assertSame( 1, $plan['appends'][0]['item'] );
		$this->assertSame( self::LAST_ROW + 1, $plan['appends'][0]['row'] );
		$this->assertSame( 'Lara', $plan['appends'][0]['values'][2] );
		$this->assertSame( '4521', $plan['appends'][0]['values'][10] );
	}

	public function test_update_is_planned_even_when_the_new_character_comes_first(): void {
		$plan = $this->plan( array( $this->character( 'Lara' ), $this->character( 'Satya' ) ), array( $this->row( 200, 'Satya', 'Blue' ) ) );

		$this->assertSame( 1, $plan['updates'][0]['item'] );
		$this->assertSame( 200, $plan['updates'][0]['row'] );
		$this->assertSame( 0, $plan['appends'][0]['item'] );
		$this->assertSame( self::LAST_ROW + 1, $plan['appends'][0]['row'] );
	}

	public function test_update_only_fills_blank_cells(): void {
		$plan = $this->plan(
			array(
				$this->character(
					'Satya',
					array(
						'actor'  => 'Jane Doe',
						'gender' => 'Cisgender',
					)
				),
			),
			array( $this->row( 200, 'Satya', 'Blue' ) )
		);

		// Actor (D) is already filled by a human, so only Gender (H) is filled.
		$this->assertSame( array( 'A', 'I', 'K', 'H' ), array_keys( $plan['updates'][0]['letters'] ) );
		$this->assertSame( 'Cisgender', $plan['updates'][0]['letters']['H'] );
	}

	public function test_several_new_rows_get_consecutive_rows(): void {
		$plan = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Lara' ) ), array() );

		$this->assertSame( array(), $plan['updates'] );
		$this->assertSame( array( self::LAST_ROW + 1, self::LAST_ROW + 2 ), array_column( $plan['appends'], 'row' ) );
	}

	public function test_later_character_sees_a_planned_row_and_is_done(): void {
		$plan = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Satya Patel' ) ), array() );

		$this->assertCount( 1, $plan['appends'] );
		$this->assertSame( 'done', $plan['items'][1]['decision']['action'] );
		$this->assertSame( self::LAST_ROW + 1, $plan['items'][1]['decision']['row'] );

		// The note reports the row the API actually wrote.
		$lines = Triage_Plan::note_lines( $plan, 'Blue', array( self::LAST_ROW + 1 => 1090 ) );
		$this->assertSame(
			array(
				'Satya (Blue): added to To Add.',
				'Satya Patel (Blue): already written by this entry (row 1090).',
			),
			$lines
		);
	}

	public function test_later_character_sees_an_updated_row_and_is_done(): void {
		$plan = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Satya Patel' ) ), array( $this->row( 200, 'Satya', 'Blue' ) ) );

		$this->assertCount( 1, $plan['updates'] );
		$this->assertSame( array(), $plan['appends'] );
		$this->assertSame( 'done', $plan['items'][1]['decision']['action'] );
		$this->assertSame( 200, $plan['items'][1]['decision']['row'] );
	}

	public function test_retry_after_the_update_landed_only_adds_the_new_row(): void {
		$to_add = array(
			$this->row(
				200,
				'Satya',
				'Blue',
				array(
					'Status'     => 'Needs Verification',
					'Form Entry' => '4521',
				)
			),
		);
		$plan   = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Lara' ) ), $to_add );

		$this->assertSame( array(), $plan['updates'] );
		$this->assertSame( 'done', $plan['items'][0]['decision']['action'] );
		$this->assertCount( 1, $plan['appends'] );
		$this->assertSame( self::LAST_ROW + 1, $plan['appends'][0]['row'] );
	}

	public function test_retry_after_everything_landed_writes_nothing(): void {
		$to_add = array(
			$this->row( 200, 'Satya', 'Blue', array( 'Form Entry' => '4100, 4521' ) ),
			$this->row( 1085, 'Lara', 'Blue', array( 'Form Entry' => '4521' ) ),
		);
		$plan   = Triage_Plan::build( array( $this->character( 'Satya' ), $this->character( 'Lara' ) ), 'Blue', 'x', self::ENTRY, '2026-11-09', array(), $this->tabs( $to_add ), self::MAP, 1085 );

		$this->assertSame( array(), $plan['updates'] );
		$this->assertSame( array(), $plan['appends'] );
	}

	public function test_append_to_a_row_not_read_from_the_sheet_throws(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->plan( array( $this->character( 'Satya' ) ), array( $this->row( self::LAST_ROW + 3, 'Satya', 'Blue' ) ) );
	}

	public function test_note_lines_can_be_limited_to_landed_items(): void {
		$plan  = $this->plan( array( $this->character( 'Satya' ), $this->character( 'Lara' ) ), array( $this->row( 200, 'Satya', 'Blue' ) ) );
		$lines = Triage_Plan::note_lines( $plan, 'Blue', array(), array( 0 ) );

		$this->assertSame( array( 'Satya (Blue): appended to To Add row 200.' ), $lines );
	}
}
