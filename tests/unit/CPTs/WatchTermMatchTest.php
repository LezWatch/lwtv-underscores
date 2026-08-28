<?php
/**
 * Unit tests for suggesting an existing provider term for an unregistered host.
 *
 * The cases that matter are the near-duplicates the live data actually produced
 * — "Lesflicks" beside "LezFlicks", "FX" beside "FX Networks" — plus the `+`
 * providers, where the domain spells out what the name punctuates.
 *
 * Equally important is what must NOT match. A wrong suggestion pre-selects a
 * dropdown, so a false positive here quietly points a host at the wrong
 * provider.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watch_Term_Match as Match_Term;

class WatchTermMatchTest extends TestCase {

	/**
	 * A realistic slice of the live lez_watch_urls terms.
	 *
	 * @return array<int, string>
	 */
	private function terms(): array {
		return array(
			28118 => 'Paramount+',
			28130 => 'Disney+',
			28161 => 'AcornTV',
			28664 => 'HBO Max',
			28671 => 'LezFlicks',
			28689 => 'FX Networks',
			28702 => 'Seed&amp;Spark',
			28712 => 'Revry',
			28677 => 'Tubi',
			28128 => 'YouTube',
		);
	}

	/*
	 * canonical()
	 */

	public function test_canonical_strips_spaces_and_case(): void {
		$this->assertSame( 'hbomax', Match_Term::canonical( 'HBO Max' ) );
	}

	public function test_canonical_spells_out_plus(): void {
		$this->assertSame( 'paramountplus', Match_Term::canonical( 'Paramount+' ) );
		$this->assertSame( 'disneyplus', Match_Term::canonical( 'Disney+' ) );
	}

	public function test_canonical_spells_out_ampersand(): void {
		$this->assertSame( 'seedandspark', Match_Term::canonical( 'Seed&Spark' ) );
	}

	public function test_canonical_decodes_entities_first(): void {
		// WordPress stores term names encoded, so this is the raw stored form.
		$this->assertSame( 'seedandspark', Match_Term::canonical( 'Seed&amp;Spark' ) );
		$this->assertSame( 'uandalibi', Match_Term::canonical( 'U&amp;Alibi' ) );
	}

	public function test_canonical_of_nothing_useful(): void {
		$this->assertSame( '', Match_Term::canonical( '' ) );
		$this->assertSame( '', Match_Term::canonical( '—' ) );
	}

	/*
	 * The matches worth having.
	 */

	public function test_matches_on_the_registrable_label(): void {
		$this->assertSame(
			28664,
			Match_Term::suggest( 'hbomax.com', 'Hbomax', $this->terms() )
		);
	}

	public function test_matches_a_plus_provider_from_its_domain(): void {
		$this->assertSame(
			28118,
			Match_Term::suggest( 'paramountplus.com', 'Paramountplus', $this->terms() )
		);
		$this->assertSame(
			28130,
			Match_Term::suggest( 'disneyplus.com', 'Disneyplus', $this->terms() )
		);
	}

	public function test_matches_on_the_domain_with_dots_removed(): void {
		// The label alone is "acorn"; only acorn.tv => "acorntv" reaches AcornTV.
		$this->assertSame(
			28161,
			Match_Term::suggest( 'acorn.tv', 'Acorn', $this->terms() )
		);
	}

	public function test_matches_through_a_generic_subdomain(): void {
		// 'watch.' is stripped by Host_Name, leaving revry.tv => "revry".
		$this->assertSame(
			28712,
			Match_Term::suggest( 'watch.revry.tv', 'Revry', $this->terms() )
		);
	}

	public function test_matches_on_the_discovered_site_name(): void {
		// The domain says nothing useful, but the site called itself HBO Max.
		$this->assertSame(
			28664,
			Match_Term::suggest( 'play.hbonow.com', 'HBO Max', $this->terms() )
		);
	}

	public function test_matches_the_near_duplicate_that_caused_this(): void {
		// A host whose proposed name differs only in capitalisation from an
		// existing term is exactly how "Lesflicks" gained a twin.
		$this->assertSame(
			28671,
			Match_Term::suggest( 'lesflicksvod.com', 'Lezflicks', $this->terms() )
		);
	}

	public function test_matches_a_name_with_an_ampersand_from_its_domain(): void {
		$this->assertSame(
			28702,
			Match_Term::suggest( 'seedandspark.com', 'Seedandspark', $this->terms() )
		);
	}

	/*
	 * The matches that must not happen.
	 */

	public function test_no_match_for_an_unrelated_host(): void {
		$this->assertSame( 0, Match_Term::suggest( 'nowhere.example', 'Nowhere', $this->terms() ) );
	}

	public function test_does_not_match_on_a_prefix(): void {
		// "Tubi" exists; tubitv.com is plausibly the same company, but matching
		// it would mean matching prefixes, and prefixes match far too much.
		$this->assertSame( 0, Match_Term::suggest( 'tubitv.com', 'Tubitv', $this->terms() ) );
	}

	public function test_does_not_match_a_longer_official_name(): void {
		// "FX Networks" must not be suggested for fxnetwork.com (no trailing s).
		$this->assertSame( 0, Match_Term::suggest( 'fxnetwork.com', 'Fxnetwork', $this->terms() ) );
	}

	public function test_does_not_match_a_bare_public_suffix(): void {
		$this->assertSame( 0, Match_Term::suggest( 'youtube.com', '', array( 99 => 'com' ) ) );
	}

	public function test_no_terms_means_no_suggestion(): void {
		$this->assertSame( 0, Match_Term::suggest( 'hbomax.com', 'HBO Max', array() ) );
	}

	public function test_empty_host_and_name_means_no_suggestion(): void {
		$this->assertSame( 0, Match_Term::suggest( '', '', $this->terms() ) );
	}

	/*
	 * Ordering and precedence.
	 */

	public function test_the_discovered_name_wins_over_the_domain(): void {
		$terms = array(
			1 => 'Disney+',
			2 => 'Hulu',
		);

		// Domain says hulu, the site says Disney+. The site's own word wins,
		// because candidates() puts the proposed name first.
		$this->assertSame( 1, Match_Term::suggest( 'hulu.com', 'Disney+', $terms ) );
	}

	public function test_first_term_keeps_a_contested_canonical_form(): void {
		$terms = array(
			10 => 'HBO Max',
			20 => 'hbomax',
		);

		$this->assertSame( 10, Match_Term::suggest( 'hbomax.com', 'Hbomax', $terms ) );
	}

	/*
	 * candidates()
	 */

	public function test_candidates_are_unique_and_ordered(): void {
		$this->assertSame(
			array( 'hbomax', 'hbomaxcom' ),
			Match_Term::candidates( 'hbomax.com', 'HBO Max' )
		);
	}

	public function test_candidates_drops_empties(): void {
		$this->assertSame(
			array( 'hulu', 'hulucom' ),
			Match_Term::candidates( 'hulu.com', '' )
		);
	}
}
