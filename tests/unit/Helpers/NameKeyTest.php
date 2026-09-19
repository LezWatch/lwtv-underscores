<?php
/**
 * Unit tests for the comparable name key.
 *
 * The problem being solved: an editor adds an actor by typing a name, and the
 * same person arrives spelled more than one way. "Bae Doona" and "Doona Bae"
 * are the same actor with the surname on either end -- a habit for Korean,
 * Chinese and Japanese names, and easy to forget in either direction. A
 * duplicate check keyed on the title as written sees two different people, and
 * the database quietly gains a second Bae Doona under a -2 slug.
 *
 * Two tiers on purpose. variants() is strict -- every token present on both
 * sides, order irrelevant -- and is what should drive a warning an editor
 * trusts. ends() is deliberately loose, catching the dropped middle name that
 * variants() cannot, and is expected to produce some false pairs; a match there
 * is a prompt to look, never a verdict.
 *
 * Accent folding is injected throughout rather than shimmed, because WordPress's
 * remove_accents() branches on get_locale() and so fails the bar set in
 * tests/bootstrap.php. The fold below is a fixed two-character map: enough for
 * these cases, and deterministic.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Name_Key;

class NameKeyTest extends TestCase {

	/**
	 * A deterministic stand-in for remove_accents().
	 *
	 * @return callable
	 */
	private function fold(): callable {
		return static function ( $text ) {
			return strtr(
				(string) $text,
				array(
					'ë' => 'e',
					'é' => 'e',
					'Á' => 'A',
					'ó' => 'o',
				)
			);
		};
	}

	/**
	 * Do two names share any strict key?
	 *
	 * @param string $one A name.
	 * @param string $two Another name.
	 *
	 * @return bool
	 */
	private function variants_match( string $one, string $two ): bool {
		return (bool) array_intersect(
			Name_Key::variants( $one, $this->fold() ),
			Name_Key::variants( $two, $this->fold() )
		);
	}

	/*
	 * variants() -- the strict tier.
	 */

	public function test_surname_first_keys_the_same_as_surname_last(): void {
		// The case this whole exercise exists for.
		$this->assertTrue( $this->variants_match( 'Bae Doona', 'Doona Bae' ) );
	}

	public function test_a_comma_form_keys_the_same_as_plain(): void {
		// "Moennig, Katherine" is how a name arrives pasted out of a cast list.
		$this->assertTrue( $this->variants_match( 'Moennig, Katherine', 'Katherine Moennig' ) );
	}

	public function test_accents_are_folded_before_comparison(): void {
		$this->assertTrue( $this->variants_match( 'Zoë Kravitz', 'Zoe Kravitz' ) );
		$this->assertTrue( $this->variants_match( 'Áine Rose Daly', 'Aine Rose Daly' ) );
	}

	public function test_a_hyphenated_given_name_matches_the_joined_spelling(): void {
		// Korean reading: "Doo-na" is "Doona", one name with a hyphen in it.
		$this->assertTrue( $this->variants_match( 'Bae Doo-na', 'Bae Doona' ) );
	}

	public function test_a_double_barrel_matches_the_spaced_spelling(): void {
		// Western reading: "Mary-Louise" is two names. Both readings are emitted
		// precisely because one rule cannot serve both conventions.
		$this->assertTrue( $this->variants_match( 'Mary-Louise Parker', 'Mary Louise Parker' ) );
	}

	public function test_both_hyphen_readings_survive_an_order_flip(): void {
		$this->assertTrue( $this->variants_match( 'Kim Ji-won', 'Ji-won Kim' ) );
	}

	public function test_a_hyphen_produces_two_keys_and_nothing_else_does(): void {
		$this->assertCount( 2, Name_Key::variants( 'Bae Doo-na', $this->fold() ) );
		$this->assertCount( 1, Name_Key::variants( 'Bae Doona', $this->fold() ) );
	}

	public function test_a_trailing_parenthetical_is_dropped(): void {
		$this->assertTrue( $this->variants_match( 'Jane Doe (actress)', 'Jane Doe' ) );
	}

	public function test_apostrophes_join_rather_than_split(): void {
		// Curly against straight, which is what wptexturize hands back and why
		// the lookup reads the raw title.
		$this->assertTrue( $this->variants_match( 'Rosie O’Donnell', "Rosie O'Donnell" ) );
		$this->assertSame( array( 'odonnell rosie' ), Name_Key::variants( "Rosie O'Donnell", $this->fold() ) );
	}

	public function test_whitespace_noise_is_irrelevant(): void {
		$this->assertTrue( $this->variants_match( '  Elliot   Page ', 'Elliot Page' ) );
	}

	public function test_a_mononym_keys_to_itself(): void {
		$this->assertSame( array( 'cher' ), Name_Key::variants( 'Cher', $this->fold() ) );
	}

	public function test_a_name_with_nothing_comparable_keys_to_nothing(): void {
		$this->assertSame( array(), Name_Key::variants( '', $this->fold() ) );
		$this->assertSame( array(), Name_Key::variants( '   ', $this->fold() ) );
		$this->assertSame( array(), Name_Key::variants( '---', $this->fold() ) );
	}

	public function test_a_name_in_its_own_script_is_kept_not_discarded(): void {
		// It will not meet its romanization, which is a documented limit. What it
		// must not do is tokenise to nothing and key as empty, because an empty
		// key would collide with every other unkeyable name.
		$this->assertSame( array( '배두나' ), Name_Key::variants( '배두나', $this->fold() ) );
	}

	public function test_a_dropped_middle_name_is_not_a_strict_match(): void {
		// Strictly every token must be present, so this one is ends()' job.
		$this->assertFalse( $this->variants_match( 'Sarah Michelle Gellar', 'Sarah Gellar' ) );
	}

	public function test_generational_suffixes_stay_distinct_at_the_strict_tier(): void {
		// Robert Downey Sr. and Jr. are both real actors, and the strict tier is
		// what drives the warning editors are meant to trust.
		$this->assertFalse( $this->variants_match( 'Robert Downey Jr.', 'Robert Downey Sr.' ) );
	}

	/*
	 * ends() -- the loose tier.
	 */

	public function test_ends_catches_a_dropped_middle_name(): void {
		$this->assertSame(
			Name_Key::ends( 'Sarah Michelle Gellar', $this->fold() ),
			Name_Key::ends( 'Sarah Gellar', $this->fold() )
		);
	}

	public function test_ends_is_order_insensitive_too(): void {
		$this->assertSame(
			Name_Key::ends( 'Bae Doona', $this->fold() ),
			Name_Key::ends( 'Doona Bae', $this->fold() )
		);
	}

	public function test_ends_drops_a_generational_suffix(): void {
		$this->assertSame(
			Name_Key::ends( 'Robert Downey', $this->fold() ),
			Name_Key::ends( 'Robert Downey Jr.', $this->fold() )
		);
	}

	public function test_ends_never_strips_the_only_token_it_has(): void {
		// An actor credited as a bare suffix would otherwise key to nothing and
		// collide with every other unkeyable name.
		$this->assertSame( array( 'iv' ), Name_Key::ends( 'IV', $this->fold() ) );
	}

	public function test_ends_uses_the_outermost_parts_of_a_hyphenated_name(): void {
		// "ji", not "jiwon" -- ends() reads a hyphen as a word boundary because
		// it wants the outer edges of the name.
		$this->assertSame( array( 'ji kim' ), Name_Key::ends( 'Ji-won Kim', $this->fold() ) );
	}

	public function test_ends_on_a_two_token_name_is_just_the_sorted_pair(): void {
		$this->assertSame( array( 'elliot page' ), Name_Key::ends( 'Elliot Page', $this->fold() ) );
	}

	public function test_ends_keys_a_mononym_once_not_twice(): void {
		$this->assertSame( array( 'cher' ), Name_Key::ends( 'Cher', $this->fold() ) );
	}

	public function test_ends_has_nothing_to_say_about_an_empty_name(): void {
		$this->assertSame( array(), Name_Key::ends( '', $this->fold() ) );
	}

	/*
	 * compare() -- the single-pair convenience, and the tier it reports.
	 */

	public function test_compare_reports_the_strict_tier(): void {
		$this->assertSame( 'full', Name_Key::compare( 'Bae Doona', 'Doona Bae', $this->fold() ) );
	}

	public function test_compare_reports_the_loose_tier(): void {
		$this->assertSame( 'ends', Name_Key::compare( 'Sarah Michelle Gellar', 'Sarah Gellar', $this->fold() ) );
	}

	public function test_compare_reports_nothing_for_unrelated_names(): void {
		$this->assertSame( '', Name_Key::compare( 'Elliot Page', 'Kate Moennig', $this->fold() ) );
	}

	public function test_compare_finds_no_match_across_romanization_systems(): void {
		// A documented limit, asserted so nobody later reads the miss as a bug.
		$this->assertSame( '', Name_Key::compare( 'Zhang Ziyi', 'Chang Tzu-i', $this->fold() ) );
	}

	public function test_two_different_people_sharing_a_name_look_like_a_match(): void {
		// Unavoidable, and the reason none of this may ever hard-block a save:
		// "Li Wei" and "Wei Li" may be one actor entered twice or two actors.
		// Only a human can say, which is what the dupe override records.
		$this->assertSame( 'full', Name_Key::compare( 'Li Wei', 'Wei Li', $this->fold() ) );
	}

	public function test_a_suffix_pair_lands_in_the_loose_tier_only(): void {
		// Downey Sr. against Downey Jr.: not a strict match, but ends() drops the
		// suffix and so pairs them. A known pair an editor acknowledges once.
		$this->assertSame( 'ends', Name_Key::compare( 'Robert Downey Jr.', 'Robert Downey Sr.', $this->fold() ) );
	}
}
