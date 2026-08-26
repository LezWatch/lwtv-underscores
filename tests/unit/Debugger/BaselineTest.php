<?php
/**
 * Unit tests for baseline diffing: what counts as new, what stays open, what
 * reads as resolved, and the two cases that decide whether the numbers can be
 * trusted at all -- a first run, and identity that survives reworded messages.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Baseline;
use LWTV\Debugger\Build\Findings;

class BaselineTest extends TestCase {

	/**
	 * Two findings on two shows.
	 *
	 * @return array
	 */
	private function findings(): array {
		return array(
			Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
			Findings::make( 11, 'post_type_shows', 'show-missing-trope' ),
		);
	}

	/*
	 * key() / snapshot()
	 */

	public function test_key_is_post_and_issue(): void {
		$this->assertSame(
			'10:show-no-genres',
			Baseline::key( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) )
		);
	}

	public function test_key_ignores_the_message(): void {
		// A renamed show must not make an old problem look new.
		$before = Findings::make( 10, 'post_type_characters', 'char-no-years', 'No years on air set for Buffy.' );
		$after  = Findings::make( 10, 'post_type_characters', 'char-no-years', 'No years on air set for Buffy the Vampire Slayer.' );

		$this->assertSame( Baseline::key( $before ), Baseline::key( $after ) );
	}

	public function test_two_urls_on_one_term_are_two_findings(): void {
		/*
		 * The case the third key part exists for. A watch provider term can have
		 * several broken URLs; without the URL in the key they collapse into one,
		 * and fixing one of three would read as having resolved all of them.
		 */
		$first = Findings::make_for_term( 55, 'lez_watch_urls', 'watch-url-broken', '', array(), 'https://example.com/one' );
		$other = Findings::make_for_term( 55, 'lez_watch_urls', 'watch-url-broken', '', array(), 'https://example.com/two' );

		$this->assertNotSame( Baseline::key( $first ), Baseline::key( $other ) );
		$this->assertCount( 2, Baseline::snapshot( array( $first, $other ) ) );
	}

	public function test_fixing_one_url_resolves_only_that_one(): void {
		$first = Findings::make_for_term( 55, 'lez_watch_urls', 'watch-url-broken', '', array(), 'https://example.com/one' );
		$other = Findings::make_for_term( 55, 'lez_watch_urls', 'watch-url-broken', '', array(), 'https://example.com/two' );

		$out = Baseline::diff( array( $other ), Baseline::snapshot( array( $first, $other ) ) );

		$this->assertCount( 1, $out['resolved'] );
		$this->assertSame( 'https://example.com/one', $out['resolved'][0]['identity'] );
		$this->assertSame( 1, $out['summary']['open'] );
	}

	public function test_a_finding_with_no_identity_keys_on_object_and_type(): void {
		$this->assertSame(
			'55:watch-term-no-urls',
			Baseline::key( Findings::make_for_term( 55, 'lez_watch_urls', 'watch-term-no-urls' ) )
		);
	}

	public function test_snapshot_stores_identity_only(): void {
		$snapshot = Baseline::snapshot( $this->findings() );

		$this->assertSame( array( '10:show-no-genres', '11:show-missing-trope' ), array_keys( $snapshot ) );
		$this->assertSame(
			array(
				'post_id'    => 10,
				'issue_type' => 'show-no-genres',
			),
			$snapshot['10:show-no-genres']
		);
	}

	public function test_snapshot_skips_findings_with_no_post(): void {
		$snapshot = Baseline::snapshot( array( Findings::make( 0, 'post_type_shows', 'show-no-genres' ) ) );

		$this->assertSame( array(), $snapshot );
	}

	/*
	 * diff()
	 */

	public function test_everything_is_open_on_a_first_run(): void {
		// Calling a decade of accumulated problems "new" on launch day would be
		// false, and would teach everyone to ignore the number.
		$out = Baseline::diff( $this->findings(), array(), true );

		$this->assertSame( 0, $out['summary']['new'] );
		$this->assertSame( 2, $out['summary']['open'] );
		$this->assertTrue( $out['summary']['first_run'] );
		$this->assertSame( Baseline::OPEN, $out['findings'][0]['status'] );
	}

	public function test_unseen_findings_are_new_against_a_baseline(): void {
		$baseline = Baseline::snapshot( array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ) );

		$out = Baseline::diff( $this->findings(), $baseline );

		$this->assertSame( Baseline::OPEN, $out['findings'][0]['status'] );
		$this->assertSame( Baseline::NEW_ISSUE, $out['findings'][1]['status'] );
		$this->assertSame( 1, $out['summary']['new'] );
		$this->assertSame( 1, $out['summary']['open'] );
	}

	public function test_a_new_issue_on_a_known_post_is_still_new(): void {
		// Identity is per issue, not per post: a show already in the report can
		// still acquire a problem.
		$baseline = Baseline::snapshot( array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ) );

		$out = Baseline::diff(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			),
			$baseline
		);

		$this->assertSame( 1, $out['summary']['new'] );
		$this->assertSame( Baseline::NEW_ISSUE, $out['findings'][1]['status'] );
	}

	public function test_baseline_entries_not_found_again_are_resolved(): void {
		$baseline = Baseline::snapshot(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 99, 'post_type_shows', 'show-missing-thumb' ),
			)
		);

		$out = Baseline::diff( array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ), $baseline );

		$this->assertCount( 1, $out['resolved'] );
		$this->assertSame( 99, $out['resolved'][0]['post_id'] );
		$this->assertSame( Baseline::RESOLVED, $out['resolved'][0]['status'] );
		$this->assertSame( 1, $out['summary']['resolved'] );
	}

	public function test_a_clean_run_resolves_everything(): void {
		$out = Baseline::diff( array(), Baseline::snapshot( $this->findings() ) );

		$this->assertSame( array(), $out['findings'] );
		$this->assertCount( 2, $out['resolved'] );
		$this->assertSame( 0, $out['summary']['total'] );
	}

	public function test_an_unchanged_run_is_all_open(): void {
		$out = Baseline::diff( $this->findings(), Baseline::snapshot( $this->findings() ) );

		$this->assertSame( 0, $out['summary']['new'] );
		$this->assertSame( 2, $out['summary']['open'] );
		$this->assertSame( 0, $out['summary']['resolved'] );
	}

	public function test_summary_counts_by_issue(): void {
		$out = Baseline::diff( $this->findings(), array(), true );

		$this->assertSame(
			array(
				'show-no-genres'     => 1,
				'show-missing-trope' => 1,
			),
			$out['summary']['by_issue']
		);
	}

	/*
	 * tag() — the partial-run path.
	 */

	public function test_tag_marks_status_without_reporting_resolved(): void {
		// A recheck visits only the flagged posts. It can say whether what it
		// looked at is new; it must not conclude anything about the rest.
		$baseline = Baseline::snapshot(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 99, 'post_type_shows', 'show-missing-thumb' ),
			)
		);

		$tagged = Baseline::tag(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			),
			$baseline
		);

		$this->assertCount( 2, $tagged );
		$this->assertSame( Baseline::OPEN, $tagged[0]['status'] );
		$this->assertSame( Baseline::NEW_ISSUE, $tagged[1]['status'] );
	}

	/*
	 * row_status()
	 */

	public function test_row_status_prefers_new(): void {
		$this->assertSame( Baseline::NEW_ISSUE, Baseline::row_status( array( Baseline::OPEN, Baseline::NEW_ISSUE ) ) );
		$this->assertSame( Baseline::OPEN, Baseline::row_status( array( Baseline::OPEN, Baseline::OPEN ) ) );
	}

	/*
	 * describe_summary()
	 */

	public function test_describe_summary_reads_as_a_sentence(): void {
		$out = Baseline::diff( $this->findings(), Baseline::snapshot( array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ) ) );

		$this->assertSame( '1 new, 1 open.', Baseline::describe_summary( $out['summary'] ) );
	}

	public function test_describe_summary_mentions_resolved_only_when_there_are_some(): void {
		$out = Baseline::diff(
			array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ),
			Baseline::snapshot(
				array(
					Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
					Findings::make( 99, 'post_type_shows', 'show-missing-thumb' ),
				)
			)
		);

		$this->assertSame( '0 new, 1 open, 1 resolved since the last run.', Baseline::describe_summary( $out['summary'] ) );
	}

	public function test_describe_summary_flags_a_first_run(): void {
		$out = Baseline::diff( $this->findings(), array(), true );

		$this->assertStringContainsString( 'first run', Baseline::describe_summary( $out['summary'] ) );
	}

	public function test_describe_summary_of_nothing(): void {
		$this->assertSame( 'Nothing outstanding.', Baseline::describe_summary( Baseline::diff( array(), array() )['summary'] ) );
	}

	/*
	 * Rows carry the status through grouping.
	 */

	public function test_grouped_rows_take_the_worst_status_on_the_row(): void {
		$out = Baseline::diff(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			),
			Baseline::snapshot( array( Findings::make( 10, 'post_type_shows', 'show-no-genres' ) ) )
		);

		$rows = Findings::group_by_post( $out['findings'] );

		$this->assertSame( array( Baseline::OPEN, Baseline::NEW_ISSUE ), $rows[10]['statuses'] );
		$this->assertSame( Baseline::NEW_ISSUE, $rows[10]['status'] );
	}

	public function test_grouped_rows_have_no_status_when_undiffed(): void {
		// "Not compared yet" must not read as "nothing is new".
		$rows = Findings::group_by_post( $this->findings() );

		$this->assertArrayNotHasKey( 'status', $rows[10] );
	}
}
