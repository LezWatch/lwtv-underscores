<?php
/**
 * Unit tests for the actor rules. The IMDb-in-a-social-field cases matter most:
 * their repair deletes the value, so a false positive costs someone their
 * social link, and the digit-count guard below is what stops a plausible short
 * handle from tripping it.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Actor_Rules;
use LWTV\Debugger\Build\Issue_Registry;

class ActorRulesTest extends TestCase {

	/**
	 * An actor with nothing wrong with them.
	 *
	 * @param  array $meta Meta values to replace.
	 * @return array
	 */
	private function actor( array $meta = array() ): array {
		return array(
			'post_id' => 7,
			'meta'    => array_merge(
				array(
					'lezactors_char_count' => 3,
					'lezactors_birth'      => '1970-01-01',
					'lezactors_death'      => '',
					'lezactors_wikipedia'  => 'https://en.wikipedia.org/wiki/Someone',
					'lezactors_homepage'   => 'https://example.com',
					'lezactors_instagram'  => 'someone',
					'lezactors_twitter'    => 'someone',
					'lezactors_imdb'       => 'nm0000123',
				),
				$meta
			),
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
	 * evaluate()
	 */

	public function test_a_complete_actor_has_no_findings(): void {
		$this->assertSame( array(), Actor_Rules::evaluate( $this->actor() ) );
	}

	public function test_evaluate_ignores_an_actor_with_no_id(): void {
		$actor            = $this->actor();
		$actor['post_id'] = 0;

		$this->assertSame( array(), Actor_Rules::evaluate( $actor ) );
	}

	public function test_evaluate_collects_from_every_rule(): void {
		$actor = $this->actor(
			array(
				'lezactors_char_count' => '',
				'lezactors_wikipedia'  => 'https://example.com/not-a-wiki',
				'lezactors_instagram'  => 'nm0000123',
				'lezactors_twitter'    => 'has spaces',
			)
		);

		$this->assertSame(
			array(
				'actor-no-characters',
				'actor-wikipedia-invalid',
				'actor-instagram-is-imdb',
				'actor-twitter-invalid',
			),
			$this->types( Actor_Rules::evaluate( $actor ) )
		);
	}

	/*
	 * characters()
	 */

	public function test_no_characters_is_reported(): void {
		$actor = $this->actor( array( 'lezactors_char_count' => '' ) );

		$this->assertSame( array( 'actor-no-characters' ), $this->types( Actor_Rules::characters( $actor ) ) );
	}

	public function test_a_zero_character_count_is_reported(): void {
		$actor = $this->actor( array( 'lezactors_char_count' => '0' ) );

		$this->assertSame( array( 'actor-no-characters' ), $this->types( Actor_Rules::characters( $actor ) ) );
	}

	/*
	 * wikipedia()
	 */

	public function test_a_non_wikipedia_url_in_the_wikipedia_field_is_reported(): void {
		$actor = $this->actor( array( 'lezactors_wikipedia' => 'https://example.com/someone' ) );

		$this->assertSame( array( 'actor-wikipedia-invalid' ), $this->types( Actor_Rules::wikipedia( $actor ) ) );
	}

	public function test_any_language_subdomain_is_accepted(): void {
		$actor = $this->actor( array( 'lezactors_wikipedia' => 'https://de.wikipedia.org/wiki/Jemand' ) );

		$this->assertSame( array(), Actor_Rules::wikipedia( $actor ) );
	}

	public function test_an_empty_wikipedia_field_is_fine(): void {
		$actor = $this->actor( array( 'lezactors_wikipedia' => '' ) );

		$this->assertSame( array(), Actor_Rules::wikipedia( $actor ) );
	}

	/*
	 * social()
	 */

	public function test_a_clean_handle_is_fine(): void {
		$this->assertSame( array(), Actor_Rules::social( $this->actor(), 'instagram' ) );
		$this->assertSame( array(), Actor_Rules::social( $this->actor(), 'twitter' ) );
	}

	public function test_an_empty_handle_is_fine(): void {
		$actor = $this->actor( array( 'lezactors_instagram' => '' ) );

		$this->assertSame( array(), Actor_Rules::social( $actor, 'instagram' ) );
	}

	public function test_a_malformed_handle_is_reported_with_its_value(): void {
		$actor    = $this->actor( array( 'lezactors_instagram' => 'not a handle!' ) );
		$findings = Actor_Rules::social( $actor, 'instagram' );

		$this->assertSame( array( 'actor-instagram-invalid' ), $this->types( $findings ) );
		$this->assertSame( 'Instagram ID is invalid -- not a handle!', $findings[0]['message'] );
		$this->assertSame( array( 'value' => 'not a handle!' ), $findings[0]['context'] );
	}

	public function test_an_imdb_id_in_the_instagram_field_is_reported(): void {
		$actor    = $this->actor( array( 'lezactors_instagram' => 'nm0000123' ) );
		$findings = Actor_Rules::social( $actor, 'instagram' );

		$this->assertSame( array( 'actor-instagram-is-imdb' ), $this->types( $findings ) );
		$this->assertSame( 'Instagram ID is an IMDb ID: nm0000123', $findings[0]['message'] );
		$this->assertTrue( $findings[0]['fixable'] );
	}

	public function test_an_imdb_id_in_the_twitter_field_is_reported(): void {
		$actor = $this->actor( array( 'lezactors_twitter' => 'nm1234567' ) );

		$this->assertSame( array( 'actor-twitter-is-imdb' ), $this->types( Actor_Rules::social( $actor, 'twitter' ) ) );
	}

	public function test_a_malformed_handle_is_never_also_an_imdb_finding(): void {
		// The IMDb repair deletes the value, so it must not be offered for a
		// handle we merely failed to parse.
		$actor    = $this->actor( array( 'lezactors_instagram' => 'nm 0000123' ) );
		$findings = Actor_Rules::social( $actor, 'instagram' );

		$this->assertCount( 1, $findings );
		$this->assertSame( 'actor-instagram-invalid', $findings[0]['issue_type'] );
	}

	/*
	 * looks_like_actor_imdb()
	 */

	public function test_a_real_imdb_id_is_recognised(): void {
		$this->assertTrue( Actor_Rules::looks_like_actor_imdb( 'nm0000123' ) );
		$this->assertTrue( Actor_Rules::looks_like_actor_imdb( 'nm12345678' ) );
	}

	public function test_a_short_nm_handle_is_not_an_imdb_id(): void {
		// 'nm2020' is a plausible Instagram handle. Debug_Tool::validate_imdb()
		// accepts it; this guard is why the repair will not eat it.
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( 'nm2020' ) );
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( 'nm1' ) );
	}

	public function test_six_digits_is_the_boundary(): void {
		$this->assertTrue( Actor_Rules::looks_like_actor_imdb( 'nm123456' ) );
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( 'nm12345' ) );
	}

	public function test_a_show_imdb_id_is_not_an_actor_one(): void {
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( 'tt0330251' ) );
	}

	public function test_an_ordinary_handle_is_not_an_imdb_id(): void {
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( 'someone' ) );
		$this->assertFalse( Actor_Rules::looks_like_actor_imdb( '' ) );
	}

	/*
	 * homepage()
	 */

	public function test_an_ordinary_homepage_is_fine(): void {
		$this->assertSame( array(), Actor_Rules::homepage( $this->actor() ) );
	}

	public function test_a_wikipedia_homepage_with_no_wikipedia_set_is_movable(): void {
		$actor = $this->actor(
			array(
				'lezactors_homepage'  => 'https://en.wikipedia.org/wiki/Someone',
				'lezactors_wikipedia' => '',
			)
		);

		$findings = Actor_Rules::homepage( $actor );

		$this->assertSame( array( 'actor-homepage-is-wikipedia' ), $this->types( $findings ) );
		$this->assertTrue( $findings[0]['fixable'] );
	}

	public function test_a_homepage_duplicating_wikipedia_is_removable(): void {
		$actor = $this->actor(
			array(
				'lezactors_homepage'  => 'https://en.wikipedia.org/wiki/Someone',
				'lezactors_wikipedia' => 'https://en.wikipedia.org/wiki/Someone',
			)
		);

		$this->assertSame( array( 'actor-homepage-dupe-wiki' ), $this->types( Actor_Rules::homepage( $actor ) ) );
	}

	public function test_two_different_wikipedia_urls_need_a_human(): void {
		$actor = $this->actor(
			array(
				'lezactors_homepage'  => 'https://en.wikipedia.org/wiki/Someone_Else',
				'lezactors_wikipedia' => 'https://en.wikipedia.org/wiki/Someone',
			)
		);

		$findings = Actor_Rules::homepage( $actor );

		$this->assertSame( array( 'actor-homepage-wikipedia' ), $this->types( $findings ) );
		$this->assertFalse( $findings[0]['fixable'] );
		$this->assertStringContainsString( 'Someone_Else', $findings[0]['message'] );
	}

	public function test_messages_carry_raw_values(): void {
		// Escaping belongs to the renderer. Pre-escaping meant the admin escaped
		// an already-escaped string and displayed the entities.
		$actor    = $this->actor( array( 'lezactors_instagram' => 'a&b c' ) );
		$findings = Actor_Rules::social( $actor, 'instagram' );

		$this->assertStringContainsString( 'a&b c', $findings[0]['message'] );
		$this->assertStringNotContainsString( '&amp;', $findings[0]['message'] );
	}

	/*
	 * The collector contract.
	 */

	public function test_meta_keys_cover_every_field_the_rules_read(): void {
		$keys = Actor_Rules::meta_keys();

		foreach ( array( 'lezactors_char_count', 'lezactors_wikipedia', 'lezactors_homepage', 'lezactors_instagram', 'lezactors_twitter' ) as $key ) {
			$this->assertContains( $key, $keys );
		}
	}

	public function test_every_issue_type_used_is_registered(): void {
		$actor = $this->actor(
			array(
				'lezactors_char_count' => '',
				'lezactors_wikipedia'  => 'https://example.com',
				'lezactors_homepage'   => 'https://en.wikipedia.org/wiki/Someone',
				'lezactors_instagram'  => 'nm0000123',
				'lezactors_twitter'    => 'nm0000123',
			)
		);

		foreach ( $this->types( Actor_Rules::evaluate( $actor ) ) as $issue_type ) {
			$this->assertTrue( Issue_Registry::exists( $issue_type ), $issue_type . ' is not in the registry' );
		}
	}
}
