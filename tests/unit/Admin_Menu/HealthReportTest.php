<?php
/**
 * Unit tests for the dashboard Data Health report.
 *
 * The widget is the first thing an editor sees, so the failures that matter
 * are quiet ones: a check that drops to 0 because its findings expired, a new
 * check that vanishes because nobody gave it a severity, a dead check hidden
 * behind the newest timestamp, and admin-only job data leaking to editors.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Health_Report;

class HealthReportTest extends TestCase {

	const NOW = 1800000000;

	const JOBS = array(
		'available' => true,
		'failed'    => 3,
		'overdue'   => 1,
	);

	/**
	 * One check's inputs, clean and freshly run unless overridden.
	 */
	private function check( array $over = array() ): array {
		return array_merge(
			array(
				'name'     => 'A check',
				'severity' => 'fix_now',
				'link_tab' => 'a_check',
				'count'    => 0,
				'cached'   => true,
				'stored'   => 0,
				'new'      => 0,
				'resolved' => 0,
				'last'     => self::NOW,
			),
			$over
		);
	}

	private function rows( array $report, string $tier ): array {
		return array_column( $report['tiers'][ $tier ] ?? array(), 'key' );
	}

	public function test_rows_group_into_tiers_in_fixed_order(): void {
		$report = Health_Report::build(
			array(
				'imdb'  => $this->check(
					array(
						'severity' => 'backlog',
						'count'    => 9,
					)
				),
				'dupes' => $this->check(
					array(
						'severity' => 'fix_now',
						'count'    => 2,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( array( 'fix_now', 'backlog' ), array_keys( $report['tiers'] ) );
		$this->assertSame( array( 'imdb' ), $this->rows( $report, 'backlog' ) );
	}

	public function test_zero_count_rows_are_dropped_and_empty_tiers_hidden(): void {
		$report = Health_Report::build(
			array(
				'clean' => $this->check( array( 'severity' => 'attention' ) ),
				'dirty' => $this->check( array( 'count' => 1 ) ),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( array( 'fix_now' ), array_keys( $report['tiers'] ) );
		$this->assertSame( array( 'dirty' ), $this->rows( $report, 'fix_now' ) );
	}

	public function test_rows_sort_by_count_desc_then_name(): void {
		$report = Health_Report::build(
			array(
				'b' => $this->check(
					array(
						'name'  => 'Bravo',
						'count' => 5,
					)
				),
				'a' => $this->check(
					array(
						'name'  => 'Alpha',
						'count' => 5,
					)
				),
				'c' => $this->check(
					array(
						'name'  => 'Charlie',
						'count' => 9,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( array( 'c', 'a', 'b' ), $this->rows( $report, 'fix_now' ) );
	}

	public function test_missing_or_unknown_severity_falls_back_to_attention(): void {
		$no_severity = $this->check( array( 'count' => 1 ) );
		unset( $no_severity['severity'] );

		$report = Health_Report::build(
			array(
				'unset' => $no_severity,
				'typo'  => $this->check(
					array(
						'severity' => 'urgent',
						'count'    => 1,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( array( 'attention' ), array_keys( $report['tiers'] ) );
		$this->assertEqualsCanonicalizing( array( 'unset', 'typo' ), $this->rows( $report, 'attention' ) );
	}

	public function test_expired_findings_use_stored_count_and_flag_as_of(): void {
		$report = Health_Report::build(
			array(
				'expired' => $this->check(
					array(
						'cached' => false,
						'count'  => 0,
						'stored' => 12,
					)
				),
				'live'    => $this->check(
					array(
						'count'  => 3,
						'stored' => 40,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$rows = array_column( $report['tiers']['fix_now'], null, 'key' );
		$this->assertSame( 12, $rows['expired']['count'] );
		$this->assertTrue( $rows['expired']['as_of'] );
		$this->assertSame( 3, $rows['live']['count'] );
		$this->assertFalse( $rows['live']['as_of'] );
	}

	public function test_stale_at_exactly_eight_days_is_not_stale_one_second_more_is(): void {
		$report = Health_Report::build(
			array(
				'edge' => $this->check(
					array(
						'name' => 'Edge',
						'last' => self::NOW - Health_Report::STALE_AFTER,
					)
				),
				'over' => $this->check(
					array(
						'name' => 'Over',
						'last' => self::NOW - Health_Report::STALE_AFTER - 1,
					)
				),
			),
			self::JOBS,
			self::NOW,
			true
		);

		$this->assertSame( 8 * DAY_IN_SECONDS, Health_Report::STALE_AFTER );
		$this->assertSame( array( 'Over' ), array_column( $report['stale'], 'name' ) );
	}

	public function test_check_not_on_cron_is_never_stale(): void {
		// Watch Providers only scans when someone opens its tab, so an old
		// `last` is normal for it, not a missed slot.
		$report = Health_Report::build(
			array(
				'manual' => $this->check(
					array(
						'name' => 'Manual',
						'cron' => false,
						'last' => self::NOW - 60 * DAY_IN_SECONDS,
					)
				),
				'never'  => $this->check(
					array(
						'name' => 'Never manual',
						'cron' => false,
						'last' => 0,
					)
				),
			),
			self::JOBS,
			self::NOW,
			true
		);

		$this->assertSame( array(), $report['stale'] );
	}

	public function test_never_run_check_is_stale_but_has_no_row(): void {
		$report = Health_Report::build(
			array(
				'never' => $this->check(
					array(
						'name' => 'Never',
						'last' => 0,
					)
				),
			),
			self::JOBS,
			self::NOW,
			true
		);

		$this->assertSame( array(), $report['tiers'] );
		$this->assertSame(
			array(
				array(
					'name' => 'Never',
					'last' => 0,
				),
			),
			$report['stale']
		);
	}

	public function test_editor_sees_no_stale_and_no_jobs(): void {
		$report = Health_Report::build(
			array(
				'never' => $this->check(
					array(
						'last'  => 0,
						'count' => 2,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( array(), $report['stale'] );
		$this->assertNull( $report['jobs'] );
		$this->assertSame( array( 'never' ), $this->rows( $report, 'fix_now' ) );
	}

	public function test_admin_gets_jobs_passed_through_including_unavailable(): void {
		$on = Health_Report::build( array(), self::JOBS, self::NOW, true );
		$this->assertSame( self::JOBS, $on['jobs'] );

		$off = Health_Report::build(
			array(),
			array(
				'available' => false,
				'failed'    => 0,
				'overdue'   => 0,
			),
			self::NOW,
			true
		);
		$this->assertFalse( $off['jobs']['available'] );
	}

	public function test_last_checked_is_newest_last(): void {
		$report = Health_Report::build(
			array(
				'old'   => $this->check( array( 'last' => self::NOW - 500 ) ),
				'new'   => $this->check( array( 'last' => self::NOW - 10 ) ),
				'never' => $this->check( array( 'last' => 0 ) ),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame( self::NOW - 10, $report['last_checked'] );
	}

	public function test_all_clear_when_no_rows(): void {
		$clean = Health_Report::build( array( 'a' => $this->check() ), self::JOBS, self::NOW, false );
		$this->assertTrue( $clean['all_clear'] );

		$dirty = Health_Report::build( array( 'a' => $this->check( array( 'count' => 1 ) ) ), self::JOBS, self::NOW, false );
		$this->assertFalse( $dirty['all_clear'] );
	}

	public function test_rows_carry_name_link_and_trend_inputs(): void {
		$report = Health_Report::build(
			array(
				'watch_hosts' => $this->check(
					array(
						'name'     => 'Contested Watch Hosts',
						'link_tab' => 'watch_providers',
						'count'    => 4,
						'new'      => 2,
						'resolved' => 1,
					)
				),
			),
			self::JOBS,
			self::NOW,
			false
		);

		$this->assertSame(
			array(
				'key'      => 'watch_hosts',
				'name'     => 'Contested Watch Hosts',
				'count'    => 4,
				'new'      => 2,
				'resolved' => 1,
				'link_tab' => 'watch_providers',
				'as_of'    => false,
				'last'     => self::NOW,
			),
			$report['tiers']['fix_now'][0]
		);
	}

	public function test_stale_summary_counts_checks(): void {
		$this->assertSame( "12 checks haven't run in over 8 days.", Health_Report::stale_summary( 12 ) );
		$this->assertSame( "2 checks haven't run in over 8 days.", Health_Report::stale_summary( 2 ) );
	}

	public function test_trend_formats(): void {
		$this->assertSame( '+5 new, 3 fixed', Health_Report::trend( 5, 3 ) );
		$this->assertSame( '+5 new', Health_Report::trend( 5, 0 ) );
		$this->assertSame( '3 fixed', Health_Report::trend( 0, 3 ) );
		$this->assertSame( '+1 new, 1 fixed', Health_Report::trend( 1, 1 ) );
		$this->assertSame( '', Health_Report::trend( 0, 0 ) );
	}
}
