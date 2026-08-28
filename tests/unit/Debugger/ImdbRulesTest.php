<?php
/**
 * Unit tests for the IMDb rules, run against both levels — the point of one
 * class serving both checks is that they cannot drift apart, so most of these
 * assert on shows and actors together.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Imdb_Rules;

class ImdbRulesTest extends TestCase {

	/**
	 * A post whose IMDb ID is fine.
	 *
	 * @param  array $overrides Keys to replace.
	 * @return array
	 */
	private function item( array $overrides = array() ): array {
		return array_merge(
			array(
				'post_id'   => 10,
				'imdb'      => 'tt0330251',
				'canonical' => '',
				'exempt'    => false,
				'no_oracle' => false,
			),
			$overrides
		);
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
	 * A good ID, both levels.
	 */

	public function test_a_valid_show_id_reports_nothing(): void {
		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $this->item() ) );
	}

	public function test_a_valid_actor_id_reports_nothing(): void {
		$item = $this->item( array( 'imdb' => 'nm0000123' ) );

		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item ) );
	}

	public function test_an_unknown_level_reports_nothing(): void {
		$this->assertSame( array(), Imdb_Rules::evaluate( 'sandwich', $this->item() ) );
	}

	public function test_evaluate_ignores_a_post_with_no_id(): void {
		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $this->item( array( 'post_id' => 0 ) ) ) );
	}

	/*
	 * Not set.
	 */

	public function test_a_missing_show_id_is_reported(): void {
		$item = $this->item( array( 'imdb' => '' ) );

		$this->assertSame( array( 'show-imdb-not-set' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) ) );
	}

	public function test_a_missing_actor_id_is_reported(): void {
		$item = $this->item( array( 'imdb' => '' ) );

		$this->assertSame( array( 'actor-imdb-not-set' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item ) ) );
	}

	public function test_an_exempt_post_may_have_no_id(): void {
		// A web series that was never on IMDb is not missing anything.
		$item = $this->item( array( 'imdb' => '', 'exempt' => true ) );

		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) );
	}

	public function test_whitespace_is_not_an_id(): void {
		$item = $this->item( array( 'imdb' => '   ' ) );

		$this->assertSame( array( 'show-imdb-not-set' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) ) );
	}

	/*
	 * Malformed.
	 */

	public function test_a_malformed_show_id_names_the_example_and_the_value(): void {
		$item     = $this->item( array( 'imdb' => 'banana' ) );
		$findings = Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item );

		$this->assertSame( array( 'show-imdb-invalid' ), $this->types( $findings ) );
		$this->assertSame( 'IMDb ID is invalid (ex: tt12345) -- banana', $findings[0]['message'] );
		$this->assertSame( array( 'imdb' => 'banana' ), $findings[0]['context'] );
	}

	public function test_the_actor_example_is_an_actor_id(): void {
		$item     = $this->item( array( 'imdb' => 'banana' ) );
		$findings = Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item );

		$this->assertStringContainsString( 'ex: nm12345', $findings[0]['message'] );
	}

	public function test_a_person_id_is_invalid_on_a_show(): void {
		// The prefixes are not interchangeable: 'nm' is a person, 'tt' a title.
		$item = $this->item( array( 'imdb' => 'nm0000123' ) );

		$this->assertSame( array( 'show-imdb-invalid' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) ) );
	}

	public function test_a_title_id_is_invalid_on_an_actor(): void {
		$item = $this->item( array( 'imdb' => 'tt0330251' ) );

		$this->assertSame( array( 'actor-imdb-invalid' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item ) ) );
	}

	/*
	 * A pasted URL — its own diagnosis, and the only repairable one.
	 */

	public function test_a_pasted_actor_url_is_its_own_finding(): void {
		// The real case: someone pasted the IMDb page instead of the ID.
		$item     = $this->item( array( 'imdb' => 'https://www.imdb.com/fr/name/nm10688602/' ) );
		$findings = Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item );

		$this->assertSame( array( 'actor-imdb-url-pasted' ), $this->types( $findings ) );
		$this->assertTrue( $findings[0]['fixable'] );
		$this->assertSame( 'nm10688602', $findings[0]['context']['extracted'] );
		$this->assertStringContainsString( 'nm10688602', $findings[0]['message'] );
	}

	public function test_a_pasted_show_url_is_its_own_finding(): void {
		$item = $this->item( array( 'imdb' => 'https://www.imdb.com/title/tt0330251/' ) );

		$this->assertSame( array( 'show-imdb-url-pasted' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) ) );
	}

	public function test_url_variants_all_yield_the_id(): void {
		foreach (
			array(
				'https://www.imdb.com/name/nm10688602/',
				'https://imdb.com/name/nm10688602',
				'http://www.imdb.com/fr/name/nm10688602/?ref_=nv_sr_1',
				'https://m.imdb.com/name/nm10688602/#bio',
			) as $url
		) {
			$this->assertSame( 'nm10688602', Imdb_Rules::id_from_url( $url, Imdb_Rules::ACTOR ), $url );
		}
	}

	public function test_a_wrong_prefix_in_the_url_is_not_extracted(): void {
		/*
		 * A title URL in an actor's field is a different mistake -- probably the
		 * wrong record entirely -- and turning it into a valid-looking wrong
		 * answer would be worse than leaving it visible.
		 */
		$this->assertSame( '', Imdb_Rules::id_from_url( 'https://www.imdb.com/title/tt0330251/', Imdb_Rules::ACTOR ) );
		$this->assertSame( '', Imdb_Rules::id_from_url( 'https://www.imdb.com/name/nm10688602/', Imdb_Rules::SHOW ) );
	}

	public function test_a_title_url_in_an_actor_field_stays_plain_invalid(): void {
		$item = $this->item( array( 'imdb' => 'https://www.imdb.com/title/tt0330251/' ) );

		$this->assertSame( array( 'actor-imdb-invalid' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item ) ) );
	}

	public function test_only_imdb_urls_are_extracted(): void {
		// Some other site's URL containing something ID-shaped is not evidence.
		$this->assertSame( '', Imdb_Rules::id_from_url( 'https://example.com/name/nm10688602/', Imdb_Rules::ACTOR ) );
		$this->assertSame( '', Imdb_Rules::id_from_url( 'https://notimdb.com/name/nm10688602/', Imdb_Rules::ACTOR ) );
	}

	public function test_junk_is_still_just_invalid(): void {
		$item = $this->item( array( 'imdb' => 'banana' ) );

		$this->assertSame( array( 'actor-imdb-invalid' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item ) ) );
		$this->assertFalse( Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item )[0]['fixable'] );
	}

	public function test_a_bare_id_is_never_a_url(): void {
		$this->assertSame( '', Imdb_Rules::id_from_url( 'nm10688602', Imdb_Rules::ACTOR ) );
	}

	public function test_an_unknown_level_extracts_nothing(): void {
		$this->assertSame( '', Imdb_Rules::id_from_url( 'https://www.imdb.com/name/nm10688602/', 'sandwich' ) );
	}

	/*
	 * Stale.
	 */

	public function test_a_disagreeing_oracle_is_reported(): void {
		$item     = $this->item( array( 'canonical' => 'tt9999999' ) );
		$findings = Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item );

		$this->assertSame( array( 'show-imdb-stale' ), $this->types( $findings ) );
		$this->assertStringContainsString( 'TVMaze', $findings[0]['message'] );
		$this->assertStringContainsString( 'Ignore TVMaze Match', $findings[0]['message'] );
		$this->assertSame(
			array(
				'imdb'      => 'tt0330251',
				'canonical' => 'tt9999999',
			),
			$findings[0]['context']
		);
	}

	public function test_the_actor_oracle_is_tmdb(): void {
		$item     = $this->item(
			array(
				'imdb'      => 'nm0000123',
				'canonical' => 'nm9999999',
			)
		);
		$findings = Imdb_Rules::evaluate( Imdb_Rules::ACTOR, $item );

		$this->assertSame( array( 'actor-imdb-stale' ), $this->types( $findings ) );
		$this->assertStringContainsString( 'TMDB', $findings[0]['message'] );
		// No override to offer for actors, so no advice about ticking one.
		$this->assertStringNotContainsString( 'Ignore', $findings[0]['message'] );
	}

	public function test_an_agreeing_oracle_reports_nothing(): void {
		$item = $this->item( array( 'canonical' => 'tt0330251' ) );

		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) );
	}

	public function test_an_empty_canonical_stays_silent(): void {
		/*
		 * "No disagreement recorded" covers both verified-clean and
		 * never-checked. A row implying "verified" for a post nobody has looked
		 * at would be worse than no row at all.
		 */
		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $this->item() ) );
	}

	public function test_an_ignored_post_skips_the_staleness_check(): void {
		$item = $this->item(
			array(
				'canonical' => 'tt9999999',
				'no_oracle' => true,
			)
		);

		$this->assertSame( array(), Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) );
	}

	public function test_the_ignore_flag_does_not_excuse_a_malformed_id(): void {
		// The override waives the oracle comparison, not basic validity.
		$item = $this->item(
			array(
				'imdb'      => 'banana',
				'no_oracle' => true,
			)
		);

		$this->assertSame( array( 'show-imdb-invalid' ), $this->types( Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) ) );
	}

	/*
	 * The three cases are mutually exclusive.
	 */

	public function test_only_one_finding_per_post(): void {
		// A missing ID cannot also be malformed or stale.
		$item = $this->item(
			array(
				'imdb'      => '',
				'canonical' => 'tt9999999',
			)
		);

		$this->assertCount( 1, Imdb_Rules::evaluate( Imdb_Rules::SHOW, $item ) );
	}
}
