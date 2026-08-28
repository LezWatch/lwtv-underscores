<?php
/**
 * Unit tests for duplicate detection. Every bug this check has shipped was a
 * comparison rather than a query — see DEBUGGER-REVIEW.md 1.9b — so each of the
 * three has a test here.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Duplicate_Rules;

class DuplicateRulesTest extends TestCase {

	/**
	 * A numerically-suffixed show that really is a duplicate.
	 *
	 * @param  array $overrides Keys to replace.
	 * @return array
	 */
	private function candidate( array $overrides = array() ): array {
		$candidate = array(
			'post_id'   => 99,
			'post_type' => 'post_type_shows',
			'slug'      => 'the-l-word-2',
			'title'     => 'The L Word',
			'imdb'      => 'tt0330251',
			'override'  => '',
			'original'  => array(
				'id'    => 10,
				'slug'  => 'the-l-word',
				'imdb'  => 'tt0330251',
				'title' => 'The L Word',
				'url'   => 'https://lezwatchtv.com/show/the-l-word/',
			),
		);

		return array_merge( $candidate, $overrides );
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
	 * base_slug() / has_suffix() — the 1.9b suffix bug.
	 */

	public function test_a_two_digit_suffix_is_stripped(): void {
		// The original assumed a two-character '-2', so '-10' and up were mangled.
		$this->assertSame( 'the-l-word', Duplicate_Rules::base_slug( 'the-l-word-10' ) );
		$this->assertSame( 'the-l-word', Duplicate_Rules::base_slug( 'the-l-word-217' ) );
	}

	public function test_a_single_digit_suffix_is_stripped(): void {
		$this->assertSame( 'the-l-word', Duplicate_Rules::base_slug( 'the-l-word-2' ) );
	}

	public function test_a_slug_with_no_suffix_is_unchanged(): void {
		$this->assertSame( 'the-l-word', Duplicate_Rules::base_slug( 'the-l-word' ) );
		$this->assertFalse( Duplicate_Rules::has_suffix( 'the-l-word' ) );
	}

	public function test_only_a_trailing_number_counts(): void {
		// Internal digits are part of the name, not a suffix.
		$this->assertSame( 'buffy-2-the-vampire', Duplicate_Rules::base_slug( 'buffy-2-the-vampire' ) );
	}

	public function test_a_number_named_show_is_not_suffixed(): void {
		/*
		 * 90210 is a real title, and a bare number is not a suffix: this check's
		 * candidate query is `post_name REGEXP '-[0-9]+$'`, which requires the
		 * hyphen, so 90210 never becomes a candidate in the first place.
		 *
		 * Note this differs from Show_Rules::numeric_suffix(), which explodes on
		 * '-' and therefore does treat '90210' as suffixed. That is not an
		 * inconsistency to harmonise away — each matches the data its own check
		 * works from, and the Shows version needs the self-finding guard *because*
		 * it is looser here.
		 */
		$this->assertFalse( Duplicate_Rules::has_suffix( '90210' ) );
		$this->assertTrue( Duplicate_Rules::has_suffix( '90210-2' ) );
	}

	/*
	 * is_acknowledged() — the 1.9b override bug.
	 */

	public function test_an_acf_true_false_override_is_honoured(): void {
		// ACF stores '1', never a real boolean, so the old `true !== $override`
		// test could never be false and the override was silently ignored.
		$this->assertTrue( Duplicate_Rules::is_acknowledged( '1' ) );
	}

	public function test_an_unset_or_off_override_is_not_an_acknowledgement(): void {
		$this->assertFalse( Duplicate_Rules::is_acknowledged( '' ) );
		$this->assertFalse( Duplicate_Rules::is_acknowledged( '0' ) );
	}

	public function test_an_acknowledged_duplicate_reports_nothing(): void {
		$this->assertSame( array(), Duplicate_Rules::evaluate( $this->candidate( array( 'override' => '1' ) ) ) );
	}

	/*
	 * evaluate()
	 */

	public function test_a_matching_imdb_id_is_a_duplicate(): void {
		$findings = Duplicate_Rules::evaluate( $this->candidate() );

		$this->assertSame( array( 'show-is-duplicate' ), $this->types( $findings ) );
		$this->assertSame( array( 'original_id' => 10 ), $findings[0]['context'] );
		$this->assertStringContainsString( 'is a duplicate of', $findings[0]['message'] );
		$this->assertStringContainsString( 'https://lezwatchtv.com/show/the-l-word/', $findings[0]['message'] );
	}

	public function test_two_missing_imdb_ids_are_not_a_match(): void {
		// The third 1.9b bug: an isset() test that was always true, so every
		// suffixed post with no IMDb ID matched a same-named post with none.
		$candidate             = $this->candidate( array( 'imdb' => '' ) );
		$candidate['original'] = array( 'id' => 10, 'imdb' => '' ) + $candidate['original'];

		$this->assertSame( array(), Duplicate_Rules::evaluate( $candidate ) );
	}

	public function test_different_imdb_ids_are_not_a_match(): void {
		$candidate                     = $this->candidate();
		$candidate['original']['imdb'] = 'tt9999999';

		$this->assertSame( array(), Duplicate_Rules::evaluate( $candidate ) );
	}

	public function test_one_missing_imdb_id_is_not_a_match(): void {
		$this->assertSame( array(), Duplicate_Rules::evaluate( $this->candidate( array( 'imdb' => '' ) ) ) );
	}

	public function test_no_original_means_nothing_to_duplicate(): void {
		$this->assertSame( array(), Duplicate_Rules::evaluate( $this->candidate( array( 'original' => array() ) ) ) );
	}

	public function test_a_post_finding_itself_is_not_a_duplicate(): void {
		// 90210: stripping the "suffix" finds the post you started with.
		$candidate                   = $this->candidate( array( 'post_id' => 10 ) );
		$candidate['original']['id'] = 10;

		$this->assertSame( array(), Duplicate_Rules::evaluate( $candidate ) );
	}

	public function test_actors_get_their_own_issue_type(): void {
		$candidate = $this->candidate( array( 'post_type' => 'post_type_actors' ) );

		$this->assertSame( array( 'actor-is-duplicate' ), $this->types( Duplicate_Rules::evaluate( $candidate ) ) );
	}

	public function test_an_unknown_post_type_reports_nothing(): void {
		// Silence beats a finding whose level no surface knows how to render.
		$candidate = $this->candidate( array( 'post_type' => 'post_type_something_else' ) );

		$this->assertSame( array(), Duplicate_Rules::evaluate( $candidate ) );
	}

	public function test_evaluate_ignores_a_candidate_with_no_id(): void {
		$this->assertSame( array(), Duplicate_Rules::evaluate( $this->candidate( array( 'post_id' => 0 ) ) ) );
	}
}
