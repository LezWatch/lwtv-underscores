<?php
/**
 * Unit tests for the term-finding triage split.
 *
 * The load-bearing property is the fail-safe direction: a row whose show count
 * cannot be read must land in the worklist, never in the "unused" pile. Getting
 * that backwards would quietly hide a finding that does affect readers, which is
 * the one outcome worse than a noisy list.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Format\Triage;

class TriageTest extends TestCase {

	/**
	 * A from_term_findings()-shaped display row.
	 *
	 * @param string $term  Provider name.
	 * @param mixed  $shows Show count, or null to omit the key entirely.
	 * @return array<string, mixed>
	 */
	private function row( string $term, $shows = null ) {
		$row = array(
			'id'     => 28118,
			'term'   => $term,
			'url'    => 'https://' . strtolower( $term ) . '.com',
			'health' => 'broken',
		);

		if ( null !== $shows ) {
			$row['shows'] = $shows;
		}

		return $row;
	}

	public function test_rows_with_shows_are_the_worklist(): void {
		$split = Triage::by_impact( array( $this->row( 'Netflix', 3 ) ) );

		$this->assertCount( 1, $split['affecting'] );
		$this->assertSame( array(), $split['unused'] );
		$this->assertSame( 'Netflix', $split['affecting'][0]['term'] );
	}

	public function test_rows_with_no_shows_are_unused(): void {
		$split = Triage::by_impact( array( $this->row( 'Go90', 0 ) ) );

		$this->assertSame( array(), $split['affecting'] );
		$this->assertCount( 1, $split['unused'] );
		$this->assertSame( 'Go90', $split['unused'][0]['term'] );
	}

	public function test_an_absent_show_count_stays_in_the_worklist(): void {
		// Rows::from_term_findings() only copies 'shows' when the context had it,
		// so a finding cached before the key existed has no count at all. "We do
		// not know" is not "nobody watches it" and must not be filed as such.
		$split = Triage::by_impact( array( $this->row( 'Lesflicks' ) ) );

		$this->assertCount( 1, $split['affecting'] );
		$this->assertSame( array(), $split['unused'] );
	}

	public function test_an_unreadable_show_count_stays_in_the_worklist(): void {
		foreach ( array( '', 'lots', array( 1, 2 ), null, true ) as $value ) {
			$row   = $this->row( 'CW Seed' );
			$split = Triage::by_impact( array( array_merge( $row, array( 'shows' => $value ) ) ) );

			$this->assertCount(
				1,
				$split['affecting'],
				'A show count of ' . var_export( $value, true ) . ' should not be read as zero.' // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);
		}
	}

	public function test_a_numeric_string_count_is_read_as_a_number(): void {
		// Term meta and cached findings both round-trip through serialisation,
		// so '0' is as likely to arrive as 0.
		$split = Triage::by_impact( array( $this->row( 'BIFL', '0' ) ) );

		$this->assertCount( 1, $split['unused'] );
	}

	public function test_a_negative_count_is_unused_not_a_worklist_row(): void {
		$split = Triage::by_impact( array( $this->row( 'LineTV', -1 ) ) );

		$this->assertCount( 1, $split['unused'] );
	}

	public function test_order_is_preserved_within_each_group(): void {
		// The caller has already sorted by severity then by show count, so the
		// split must not reorder anything -- only separate it.
		$split = Triage::by_impact(
			array(
				$this->row( 'Netflix', 9 ),
				$this->row( 'Go90', 0 ),
				$this->row( 'Hulu', 4 ),
				$this->row( 'BIFL', 0 ),
				$this->row( 'Peacock', 1 ),
			)
		);

		$this->assertSame( array( 'Netflix', 'Hulu', 'Peacock' ), array_column( $split['affecting'], 'term' ) );
		$this->assertSame( array( 'Go90', 'BIFL' ), array_column( $split['unused'], 'term' ) );
	}

	public function test_both_groups_are_lists_not_sparse_arrays(): void {
		// Renderers index these with array_column() and foreach; preserved keys
		// from the input would make $split['unused'][0] a miss.
		$split = Triage::by_impact(
			array(
				$this->row( 'Netflix', 9 ),
				$this->row( 'Go90', 0 ),
			)
		);

		$this->assertSame( array( 0 ), array_keys( $split['unused'] ) );
		$this->assertSame( array( 0 ), array_keys( $split['affecting'] ) );
	}

	public function test_nothing_in_nothing_out(): void {
		$split = Triage::by_impact( array() );

		$this->assertSame( array(), $split['affecting'] );
		$this->assertSame( array(), $split['unused'] );
	}

	public function test_is_unused_is_the_same_predicate_the_split_uses(): void {
		// The renderer asks per row whether to offer Retire; that answer has to
		// agree with which table the row was put in.
		$this->assertTrue( Triage::is_unused( $this->row( 'Go90', 0 ) ) );
		$this->assertFalse( Triage::is_unused( $this->row( 'Netflix', 3 ) ) );
		$this->assertFalse( Triage::is_unused( $this->row( 'Lesflicks' ) ) );
	}
}
