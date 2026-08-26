<?php
/**
 * Unit tests for building and grouping debugger findings: registry-sourced
 * copy, per-post message overrides, inline repair advertising, grouping into
 * one row per post, and tolerance of the pre-reshape row shape.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Findings;

class FindingsTest extends TestCase {

	/*
	 * make()
	 */

	public function test_make_pulls_copy_and_fixability_from_the_registry(): void {
		$finding = Findings::make( 4213, 'post_type_shows', 'show-missing-trope' );

		$this->assertSame( 4213, $finding['post_id'] );
		$this->assertSame( 'post_type_shows', $finding['post_type'] );
		$this->assertSame( 'show-missing-trope', $finding['issue_type'] );
		$this->assertSame( 'No tropes set.', $finding['message'] );
		$this->assertSame( array(), $finding['context'] );
		$this->assertTrue( $finding['fixable'] );
		$this->assertSame( 'adds the "none" trope', $finding['fix_label'] );
	}

	public function test_make_accepts_a_per_post_message_override(): void {
		$finding = Findings::make( 99, 'post_type_characters', 'char-no-years', 'No years on air set for Buffy.' );

		$this->assertSame( 'No years on air set for Buffy.', $finding['message'] );
	}

	public function test_make_keeps_context(): void {
		$finding = Findings::make( 7, 'post_type_actors', 'actor-twitter-is-imdb', '', array( 'value' => 'nm0000123' ) );

		$this->assertSame( array( 'value' => 'nm0000123' ), $finding['context'] );
		$this->assertTrue( $finding['fixable'] );
	}

	public function test_make_on_an_unfixable_issue_has_no_fix_label(): void {
		$finding = Findings::make( 1, 'post_type_shows', 'show-no-characters' );

		$this->assertFalse( $finding['fixable'] );
		$this->assertSame( '', $finding['fix_label'] );
	}

	/*
	 * describe()
	 */

	public function test_describe_advertises_the_repair(): void {
		$finding = Findings::make( 1, 'post_type_shows', 'show-missing-trope' );

		$this->assertSame( 'No tropes set. — fixable, adds the "none" trope.', Findings::describe( $finding ) );
	}

	public function test_describe_says_where_a_manual_repair_lives(): void {
		// --fix-it will not do this one, so plain "fixable" would be a promise
		// the CLI does not keep.
		$finding = Findings::make( 1, 'post_type_shows', 'show-no-characters' );

		$this->assertTrue( $finding['manual'] );
		$this->assertStringContainsString( 'fixable in wp-admin', Findings::describe( $finding ) );
	}

	public function test_describe_leaves_unfixable_messages_alone(): void {
		$finding = Findings::make( 1, 'post_type_shows', 'show-no-genres' );

		$this->assertSame( 'No genres.', Findings::describe( $finding ) );
	}

	/*
	 * group_by_post()
	 */

	public function test_group_by_post_collapses_issues_onto_one_row(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
				Findings::make( 11, 'post_type_shows', 'show-no-characters' ),
			)
		);

		$this->assertSame( array( 10, 11 ), array_keys( $rows ) );
		$this->assertSame( array( 'show-no-genres', 'show-missing-trope' ), $rows[10]['issues'] );
		$this->assertSame( array( 'show-missing-trope' ), $rows[10]['fixable'] );
		$this->assertSame( array(), $rows[11]['fixable'] );
	}

	public function test_group_by_post_row_count_still_counts_posts(): void {
		// The admin badge and "N shows need attention" copy read count() on
		// these rows, so three problems on one show must stay one row.
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-no-format' ),
				Findings::make( 10, 'post_type_shows', 'show-no-country' ),
			)
		);

		$this->assertCount( 1, $rows );
	}

	public function test_group_by_post_joins_messages_into_the_problem_blob(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			)
		);

		$this->assertSame(
			'No genres.</br>No tropes set. — fixable, adds the "none" trope.',
			$rows[10]['problem']
		);
	}

	public function test_group_by_post_keeps_raw_messages(): void {
		// The fixability prose belongs to the blob, not the stored message: the
		// admin renders a button instead, and a row has to be rebuildable.
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			)
		);

		$this->assertSame( array( 'No tropes set.' ), $rows[10]['messages'] );
	}

	public function test_group_by_post_does_not_repeat_a_fixable_type(): void {
		// Duplicates would make the fixer run the same repair twice.
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			)
		);

		$this->assertSame( array( 'show-missing-trope' ), $rows[10]['fixable'] );
	}

	public function test_group_by_post_skips_findings_with_no_post(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 0, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
			)
		);

		$this->assertSame( array( 10 ), array_keys( $rows ) );
	}

	public function test_group_by_post_of_nothing_is_nothing(): void {
		$this->assertSame( array(), Findings::group_by_post( array() ) );
	}

	/*
	 * count_by_issue()
	 */

	public function test_count_by_issue_ranks_by_frequency(): void {
		$counts = Findings::count_by_issue(
			array(
				Findings::make( 1, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 2, 'post_type_shows', 'show-missing-trope' ),
				Findings::make( 3, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 4, 'post_type_shows', 'show-no-genres' ),
			)
		);

		$this->assertSame(
			array(
				'show-no-genres'     => 3,
				'show-missing-trope' => 1,
			),
			$counts
		);
	}

	/*
	 * fixable_issues()
	 */

	public function test_fixable_issues_reads_a_grouped_row(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
			)
		);

		$this->assertSame( array( 'show-missing-trope' ), Findings::fixable_issues( $rows[10] ) );
	}

	public function test_fixable_issues_tolerates_a_pre_reshape_row(): void {
		// A week-long transient written before the reshape has no keys but these.
		$old_row = array(
			'url'     => 'https://lezwatchtv.com/show/whatever/',
			'id'      => 10,
			'problem' => 'No genres.</br>No tropes set.',
		);

		$this->assertSame( array(), Findings::fixable_issues( $old_row ) );
	}

	/*
	 * without_issue() — the admin repair pruning one issue off a cached row.
	 */

	public function test_without_issue_rebuilds_the_row(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
				Findings::make( 10, 'post_type_shows', 'show-missing-thumb' ),
			)
		);

		$row = Findings::without_issue( $rows[10], 'show-missing-trope' );

		$this->assertSame( array( 'show-no-genres', 'show-missing-thumb' ), $row['issues'] );
		$this->assertSame( array( 'No genres.', 'No Thumb score.' ), $row['messages'] );
		$this->assertSame( array( 'show-missing-thumb' ), $row['fixable'] );
		$this->assertSame(
			'No genres.</br>No Thumb score. — fixable, sets it to TBD.',
			$row['problem']
		);
	}

	public function test_without_issue_empties_a_row_with_nothing_left(): void {
		// An empty return is the signal to drop the row from the report.
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-missing-trope' ),
			)
		);

		$this->assertSame( array(), Findings::without_issue( $rows[10], 'show-missing-trope' ) );
	}

	public function test_without_issue_ignores_an_issue_the_row_does_not_have(): void {
		$rows = Findings::group_by_post(
			array(
				Findings::make( 10, 'post_type_shows', 'show-no-genres' ),
			)
		);

		$row = Findings::without_issue( $rows[10], 'show-missing-trope' );

		$this->assertSame( array( 'show-no-genres' ), $row['issues'] );
	}

	public function test_without_issue_leaves_a_pre_reshape_row_untouched(): void {
		// No per-issue data to prune, and guessing would corrupt the row.
		$old_row = array(
			'url'     => 'https://lezwatchtv.com/show/whatever/',
			'id'      => 10,
			'problem' => 'No genres.</br>No tropes set.',
		);

		$this->assertSame( $old_row, Findings::without_issue( $old_row, 'show-missing-trope' ) );
	}

	public function test_without_issue_drops_every_copy_of_the_type(): void {
		$row = array(
			'id'       => 10,
			'issues'   => array( 'char-no-years', 'char-no-years', 'char-no-actors' ),
			'messages' => array( 'No years on air set for A.', 'No years on air set for B.', 'No actors listed.' ),
			'fixable'  => array(),
		);

		$updated = Findings::without_issue( $row, 'char-no-years' );

		$this->assertSame( array( 'char-no-actors' ), $updated['issues'] );
		$this->assertSame( array( 'No actors listed.' ), $updated['messages'] );
	}

	/*
	 * problem_from()
	 */

	public function test_problem_from_pairs_issues_with_messages(): void {
		$this->assertSame(
			'No genres.</br>No tropes set. — fixable, adds the "none" trope.',
			Findings::problem_from(
				array( 'show-no-genres', 'show-missing-trope' ),
				array( 'No genres.', 'No tropes set.' )
			)
		);
	}

	public function test_problem_from_survives_a_missing_issue_type(): void {
		// Never expected, but a truncated cached row must not fatal a page.
		$this->assertSame(
			'No genres.',
			Findings::problem_from( array(), array( 'No genres.' ) )
		);
	}

	public function test_fixable_issues_drops_types_with_no_registered_repair(): void {
		$row = array(
			'id'      => 10,
			'fixable' => array( 'show-missing-trope', 'show-invented-nonsense' ),
		);

		$this->assertSame( array( 'show-missing-trope' ), Findings::fixable_issues( $row ) );
	}
}
