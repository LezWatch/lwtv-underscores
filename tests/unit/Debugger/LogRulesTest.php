<?php
/**
 * Tests for LWTV\Debugger\Build\Log_Rules.
 *
 * @package LWTV
 */

namespace LWTV\Tests\Unit\Debugger;

use LWTV\Debugger\Build\Log_Rules;
use PHPUnit\Framework\TestCase;

class LogRulesTest extends TestCase {

	/**
	 * A stand-in vocabulary. The real one is Admin_Menu\Debugging::VALID_LOG_TOPICS,
	 * which is not loaded here -- these rules take the list as an argument
	 * precisely so they do not need it.
	 *
	 * @var array<string>
	 */
	private const VALID = array( 'statistics', 'caching', 'shadow-taxonomy', 'is-queer' );

	/*
	 * line() and topic_from_line()
	 */

	public function test_a_line_keeps_the_established_format(): void {
		$this->assertSame(
			"[2026-08-27 14:15:00] [Statistics] Warmed 12 caches.\n",
			Log_Rules::line( 'statistics', 'Warmed 12 caches.', '2026-08-27 14:15:00' )
		);
	}

	/**
	 * ucwords() splits on spaces, not hyphens, so a hyphenated topic keeps its
	 * lowercase tail. This is not a bug to fix: existing log files are full of
	 * it, and topic_from_line() lowercases anyway.
	 */
	public function test_a_hyphenated_topic_is_only_capitalised_once(): void {
		$this->assertStringContainsString(
			'[Shadow-taxonomy]',
			Log_Rules::line( 'shadow-taxonomy', 'Synced.', '2026-08-27 14:15:00' )
		);
	}

	public function test_a_topic_survives_the_round_trip(): void {
		foreach ( self::VALID as $topic ) {
			$line = Log_Rules::line( $topic, 'Something happened.', '2026-08-27 14:15:00' );
			$this->assertSame( $topic, Log_Rules::topic_from_line( $line ) );
		}
	}

	public function test_a_line_that_is_not_ours_has_no_topic(): void {
		$this->assertSame( '', Log_Rules::topic_from_line( 'PHP Warning: something else entirely' ) );
		$this->assertSame( '', Log_Rules::topic_from_line( '' ) );
		$this->assertSame( '', Log_Rules::topic_from_line( '[2026-08-27 14:15:00] no topic bracket' ) );
	}

	/*
	 * topic_enabled() -- the deliberate fail-closed reversal
	 */

	public function test_no_topics_ticked_now_means_silence(): void {
		$this->assertFalse( Log_Rules::topic_enabled( 'statistics', array(), self::VALID ) );
	}

	public function test_a_ticked_topic_is_enabled(): void {
		$this->assertTrue( Log_Rules::topic_enabled( 'statistics', array( 'statistics', 'caching' ), self::VALID ) );
	}

	public function test_an_unticked_topic_is_not_enabled(): void {
		$this->assertFalse( Log_Rules::topic_enabled( 'is-queer', array( 'statistics' ), self::VALID ) );
	}

	public function test_an_unknown_topic_is_refused_even_when_ticked(): void {
		// A typo cannot be ticked in the UI, so it could never be turned on --
		// and under the old fail-open rule it could never be turned off either.
		$this->assertFalse( Log_Rules::topic_enabled( 'statitsics', array( 'statitsics' ), self::VALID ) );
	}

	public function test_an_empty_topic_is_refused(): void {
		$this->assertFalse( Log_Rules::topic_enabled( '', self::VALID, self::VALID ) );
	}

	public function test_is_known_topic_is_case_sensitive(): void {
		$this->assertTrue( Log_Rules::is_known_topic( 'caching', self::VALID ) );
		$this->assertFalse( Log_Rules::is_known_topic( 'Caching', self::VALID ) );
	}

	/*
	 * should_rotate()
	 */

	public function test_rotation_triggers_at_the_threshold_not_past_it(): void {
		$this->assertTrue( Log_Rules::should_rotate( 1000, 1000 ) );
		$this->assertTrue( Log_Rules::should_rotate( 1001, 1000 ) );
		$this->assertFalse( Log_Rules::should_rotate( 999, 1000 ) );
	}

	public function test_an_empty_log_never_rotates(): void {
		$this->assertFalse( Log_Rules::should_rotate( 0, Log_Rules::ROTATE_AT ) );
	}

	public function test_a_zero_threshold_disables_rotation(): void {
		$this->assertFalse( Log_Rules::should_rotate( PHP_INT_MAX, 0 ) );
	}

	public function test_the_hard_cap_is_above_the_cron_threshold(): void {
		// Otherwise the mid-request backstop would fire before cron ever got a
		// chance, moving rotation into the hot path by accident.
		$this->assertGreaterThan( Log_Rules::ROTATE_AT, Log_Rules::MAX_BYTES );
	}

	/*
	 * rotated_name()
	 */

	public function test_the_stamp_goes_before_the_extension(): void {
		$this->assertSame(
			'debug-lwtv-20260827-141500.log',
			Log_Rules::rotated_name( 'debug-lwtv.log', '20260827-141500' )
		);
	}

	public function test_a_name_with_no_extension_gets_the_stamp_appended(): void {
		$this->assertSame( 'debuglog-20260827-141500', Log_Rules::rotated_name( 'debuglog', '20260827-141500' ) );
	}

	public function test_rotated_names_sort_chronologically(): void {
		$names = array(
			Log_Rules::rotated_name( 'debug-lwtv.log', '20260827-141500' ),
			Log_Rules::rotated_name( 'debug-lwtv.log', '20250101-000000' ),
			Log_Rules::rotated_name( 'debug-lwtv.log', '20260827-090000' ),
		);
		$sorted = $names;
		sort( $sorted, SORT_STRING );

		$this->assertSame(
			array(
				'debug-lwtv-20250101-000000.log',
				'debug-lwtv-20260827-090000.log',
				'debug-lwtv-20260827-141500.log',
			),
			$sorted,
			'prunable() relies on name order being time order.'
		);
	}

	/*
	 * prunable()
	 */

	public function test_nothing_is_pruned_below_the_keep_count(): void {
		$files = array( 'a-20260101-000000.log', 'a-20260102-000000.log' );
		$this->assertSame( array(), Log_Rules::prunable( $files, 5 ) );
	}

	public function test_exactly_keep_files_prunes_nothing(): void {
		$files = array( 'a-1.log', 'a-2.log', 'a-3.log' );
		$this->assertSame( array(), Log_Rules::prunable( $files, 3 ) );
	}

	public function test_the_oldest_are_pruned_first(): void {
		$files = array(
			'a-20260103-000000.log',
			'a-20260101-000000.log',
			'a-20260104-000000.log',
			'a-20260102-000000.log',
		);

		$this->assertSame(
			array( 'a-20260101-000000.log', 'a-20260102-000000.log' ),
			Log_Rules::prunable( $files, 2 )
		);
	}

	public function test_keeping_none_prunes_everything(): void {
		$files = array( 'a-1.log', 'a-2.log' );
		$this->assertCount( 2, Log_Rules::prunable( $files, 0 ) );
	}

	public function test_a_negative_keep_is_treated_as_zero(): void {
		$this->assertCount( 2, Log_Rules::prunable( array( 'a-1.log', 'a-2.log' ), -3 ) );
	}

	/*
	 * filter_lines() and tail()
	 */

	private function lines(): array {
		return array(
			Log_Rules::line( 'statistics', 'Warmed shows.', '2026-08-27 10:00:00' ),
			Log_Rules::line( 'caching', 'Flushed the lot.', '2026-08-27 11:00:00' ),
			Log_Rules::line( 'statistics', 'Warmed characters.', '2026-08-27 12:00:00' ),
			"\n",
			Log_Rules::line( 'shadow-taxonomy', 'Synced 4 terms.', '2026-08-27 13:00:00' ),
		);
	}

	public function test_no_filter_keeps_everything_except_blank_lines(): void {
		$this->assertCount( 4, Log_Rules::filter_lines( $this->lines() ) );
	}

	public function test_filtering_by_topic(): void {
		$found = Log_Rules::filter_lines( $this->lines(), 'statistics' );
		$this->assertCount( 2, $found );
		$this->assertStringContainsString( 'Warmed shows.', $found[0] );
		$this->assertStringContainsString( 'Warmed characters.', $found[1] );
	}

	public function test_filtering_by_topic_is_case_insensitive_at_the_boundary(): void {
		// The stored tag is 'Statistics'; a caller typing --topic=Statistics
		// should still work.
		$this->assertCount( 2, Log_Rules::filter_lines( $this->lines(), 'STATISTICS' ) );
	}

	public function test_a_hyphenated_topic_filters_correctly(): void {
		$found = Log_Rules::filter_lines( $this->lines(), 'shadow-taxonomy' );
		$this->assertCount( 1, $found );
		$this->assertStringContainsString( 'Synced 4 terms.', $found[0] );
	}

	public function test_filtering_by_search_text(): void {
		$found = Log_Rules::filter_lines( $this->lines(), '', 'warmed' );
		$this->assertCount( 2, $found, 'Search should be case-insensitive.' );
	}

	public function test_topic_and_search_both_apply(): void {
		$this->assertCount( 1, Log_Rules::filter_lines( $this->lines(), 'statistics', 'characters' ) );
		$this->assertCount( 0, Log_Rules::filter_lines( $this->lines(), 'caching', 'characters' ) );
	}

	public function test_an_unmatched_topic_returns_nothing(): void {
		$this->assertSame( array(), Log_Rules::filter_lines( $this->lines(), 'postiz' ) );
	}

	public function test_tail_returns_the_newest(): void {
		$found = Log_Rules::tail( Log_Rules::filter_lines( $this->lines() ), 2 );
		$this->assertCount( 2, $found );
		$this->assertStringContainsString( 'Warmed characters.', $found[0] );
		$this->assertStringContainsString( 'Synced 4 terms.', $found[1] );
	}

	public function test_tail_of_more_than_exists_returns_all(): void {
		$this->assertCount( 4, Log_Rules::tail( Log_Rules::filter_lines( $this->lines() ), 99 ) );
	}

	public function test_tail_of_zero_or_less_returns_all(): void {
		$this->assertCount( 4, Log_Rules::tail( Log_Rules::filter_lines( $this->lines() ), 0 ) );
		$this->assertCount( 4, Log_Rules::tail( Log_Rules::filter_lines( $this->lines() ), -1 ) );
	}
}
