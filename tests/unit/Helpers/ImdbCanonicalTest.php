<?php
/**
 * Unit tests for the IMDb canonical-ID comparison.
 *
 * IMDb reassigns title and name IDs, leaving the old one working as a redirect.
 * That makes a stale ID invisible to a human -- the link still opens the right
 * page -- while breaking every exact-match API lookup keyed on it. TVMaze's
 * /lookup/shows?imdb= is one such lookup, and Only Murders in the Building is
 * the worked example: TVMaze holds tt11691774, we held tt12851524, and both
 * resolve to the same show on IMDb itself.
 *
 * This is the pure half of the check: given our ID and whatever a third party
 * that stores canonical IMDb IDs holds, decide what to report. Deliberately
 * knows nothing about HTTP -- see Schedulers\Imdb_Verify_Task for the glue.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Imdb_Canonical;

class ImdbCanonicalTest extends TestCase {

	/*
	 * normalise()
	 */

	public function test_normalise_trims_and_lowercases(): void {
		$this->assertSame( 'tt11691774', Imdb_Canonical::normalise( '  TT11691774 ' ) );
	}

	public function test_normalise_strips_a_full_imdb_url(): void {
		// Editors paste URLs into ID fields. Comparing a URL against a bare ID
		// would report a false mismatch on every one of them.
		$this->assertSame( 'tt11691774', Imdb_Canonical::normalise( 'https://www.imdb.com/title/tt11691774/' ) );
		$this->assertSame( 'nm0000123', Imdb_Canonical::normalise( 'https://imdb.com/name/nm0000123' ) );
	}

	public function test_normalise_handles_absent_values(): void {
		$this->assertSame( '', Imdb_Canonical::normalise( '' ) );
		$this->assertSame( '', Imdb_Canonical::normalise( null ) );
		$this->assertSame( '', Imdb_Canonical::normalise( '   ' ) );
	}

	public function test_normalise_rejects_something_that_is_not_an_imdb_id(): void {
		$this->assertSame( '', Imdb_Canonical::normalise( 'not-an-id' ) );
		$this->assertSame( '', Imdb_Canonical::normalise( '12851524' ) );
	}

	/*
	 * verdict()
	 */

	public function test_matching_ids_are_a_match(): void {
		$this->assertSame( 'match', Imdb_Canonical::verdict( 'tt11691774', 'tt11691774' ) );
	}

	public function test_matching_ids_ignore_case_and_whitespace(): void {
		$this->assertSame( 'match', Imdb_Canonical::verdict( ' TT11691774 ', 'tt11691774' ) );
	}

	public function test_a_url_matches_the_bare_id_it_contains(): void {
		$this->assertSame( 'match', Imdb_Canonical::verdict( 'https://www.imdb.com/title/tt11691774/', 'tt11691774' ) );
	}

	public function test_differing_ids_are_stale(): void {
		// The Only Murders case.
		$this->assertSame( 'stale', Imdb_Canonical::verdict( 'tt12851524', 'tt11691774' ) );
	}

	public function test_no_oracle_value_is_not_a_verdict(): void {
		// The third party has the show but never recorded an IMDb link. That says
		// nothing about our ID, so it must not read as stale.
		$this->assertSame( 'no-oracle', Imdb_Canonical::verdict( 'tt12851524', '' ) );
		$this->assertSame( 'no-oracle', Imdb_Canonical::verdict( 'tt12851524', null ) );
	}

	public function test_our_id_missing_is_reported_separately(): void {
		// Already covered by the debugger's "IMDb ID is not set" check, so this
		// must not double-report as stale.
		$this->assertSame( 'not-set', Imdb_Canonical::verdict( '', 'tt11691774' ) );
		$this->assertSame( 'not-set', Imdb_Canonical::verdict( null, 'tt11691774' ) );
	}

	public function test_both_missing_is_not_set(): void {
		$this->assertSame( 'not-set', Imdb_Canonical::verdict( '', '' ) );
	}

	public function test_a_malformed_id_of_ours_is_not_reported_as_stale(): void {
		// Debug_Tool::validate_imdb() already flags malformed IDs. Reporting them
		// as stale as well would put two problems on one row for one fault.
		$this->assertSame( 'not-set', Imdb_Canonical::verdict( 'garbage', 'tt11691774' ) );
	}

	/*
	 * is_stale() - the one-line question the debugger and scheduler both ask
	 */

	public function test_is_stale_is_true_only_for_a_real_disagreement(): void {
		$this->assertTrue( Imdb_Canonical::is_stale( 'tt12851524', 'tt11691774' ) );
	}

	public function test_is_stale_is_false_for_everything_else(): void {
		$this->assertFalse( Imdb_Canonical::is_stale( 'tt11691774', 'tt11691774' ) );
		$this->assertFalse( Imdb_Canonical::is_stale( 'tt12851524', '' ) );
		$this->assertFalse( Imdb_Canonical::is_stale( '', 'tt11691774' ) );
		$this->assertFalse( Imdb_Canonical::is_stale( '', '' ) );
	}

	/*
	 * Cross-kind confusion. A show ID is tt*, a person ID is nm*.
	 */

	public function test_a_person_id_never_matches_a_title_id(): void {
		$this->assertSame( 'stale', Imdb_Canonical::verdict( 'nm0000123', 'tt11691774' ) );
	}

	public function test_normalise_keeps_both_prefixes(): void {
		$this->assertSame( 'tt0452046', Imdb_Canonical::normalise( 'tt0452046' ) );
		$this->assertSame( 'nm0000123', Imdb_Canonical::normalise( 'nm0000123' ) );
	}
}
