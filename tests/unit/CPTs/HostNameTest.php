<?php
/**
 * Unit tests for deriving a provider display name from a hostname, and for the
 * candidate host list used to match lez_watch_urls terms.
 *
 * Cases are drawn from real Ways to Watch hosts on the site, including the ones
 * the previous suffix-stripping approach got wrong.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watching\Host_Name;

class HostNameTest extends TestCase {

	/*
	 * normalise()
	 */

	public function test_normalise_lowercases_and_drops_www(): void {
		$this->assertSame( 'netflix.com', Host_Name::normalise( 'WWW.Netflix.com' ) );
		$this->assertSame( 'netflix.com', Host_Name::normalise( 'netflix.com' ) );
	}

	public function test_normalise_keeps_meaningful_subdomains(): void {
		// 'abc' is the brand here, not noise.
		$this->assertSame( 'abc.go.com', Host_Name::normalise( 'abc.go.com' ) );
	}

	public function test_normalise_trims_stray_dots_and_space(): void {
		$this->assertSame( 'hulu.com', Host_Name::normalise( '  .hulu.com.  ' ) );
	}

	/*
	 * registrable_label() - simple cases
	 */

	public function test_registrable_label_simple_domain(): void {
		$this->assertSame( 'netflix', Host_Name::registrable_label( 'www.netflix.com' ) );
		$this->assertSame( 'hulu', Host_Name::registrable_label( 'hulu.com' ) );
		$this->assertSame( 'cbs', Host_Name::registrable_label( 'www.cbs.com' ) );
	}

	public function test_registrable_label_strips_generic_subdomains(): void {
		$this->assertSame( 'amazon', Host_Name::registrable_label( 'watch.amazon.com' ) );
		$this->assertSame( 'hbomax', Host_Name::registrable_label( 'play.hbomax.com' ) );
		$this->assertSame( 'max', Host_Name::registrable_label( 'play.max.com' ) );
	}

	public function test_registrable_label_strips_stacked_generic_subdomains(): void {
		$this->assertSame( 'foo', Host_Name::registrable_label( 'watch.play.foo.com' ) );
	}

	/*
	 * registrable_label() - compound public suffixes
	 *
	 * These are the cases the old '.co.uk before .co' ordering kept getting
	 * wrong, or missing entirely.
	 */

	public function test_registrable_label_handles_compound_suffixes(): void {
		$this->assertSame( 'tvnz', Host_Name::registrable_label( 'www.tvnz.co.nz' ) );
		$this->assertSame( 'uktv', Host_Name::registrable_label( 'alibi.uktv.co.uk' ) );
		$this->assertSame( 'abc', Host_Name::registrable_label( 'iview.abc.net.au' ) );
		$this->assertSame( 'channel4', Host_Name::registrable_label( 'www.channel4.com' ) );
	}

	public function test_registrable_label_does_not_over_strip_compound_suffix(): void {
		// Only two labels and both look like a suffix: keep what's there rather
		// than returning ''.
		$this->assertSame( 'co', Host_Name::registrable_label( 'co.uk' ) );
	}

	/*
	 * registrable_label() - edge cases
	 */

	public function test_registrable_label_bare_hostname(): void {
		$this->assertSame( 'localhost', Host_Name::registrable_label( 'localhost' ) );
	}

	public function test_registrable_label_empty_input(): void {
		$this->assertSame( '', Host_Name::registrable_label( '' ) );
		$this->assertSame( '', Host_Name::registrable_label( '   ' ) );
	}

	public function test_registrable_label_subdomain_that_is_only_a_prefix(): void {
		// 'go.' is a generic prefix, but stripping it must not empty the host.
		$this->assertSame( 'go', Host_Name::registrable_label( 'go.com' ) );
	}

	/*
	 * guess()
	 */

	public function test_guess_uppercases_short_labels_as_acronyms(): void {
		$this->assertSame( 'CBS', Host_Name::guess( 'www.cbs.com' ) );
		$this->assertSame( 'HBO', Host_Name::guess( 'www.hbo.com' ) );
		$this->assertSame( 'IFC', Host_Name::guess( 'www.ifc.com' ) );
		$this->assertSame( 'IQ', Host_Name::guess( 'www.iq.com' ) );
	}

	public function test_guess_capitalises_longer_labels(): void {
		$this->assertSame( 'Netflix', Host_Name::guess( 'www.netflix.com' ) );
		$this->assertSame( 'Crunchyroll', Host_Name::guess( 'www.crunchyroll.com' ) );
		$this->assertSame( 'Globo', Host_Name::guess( 'gshow.globo.com' ) );
	}

	public function test_guess_regression_cases_from_the_old_ltrim_bug(): void {
		// ltrim( 'watch.amazon.com', 'watch.' ) used to yield 'mazon.com'.
		$this->assertSame( 'Amazon', Host_Name::guess( 'watch.amazon.com' ) );
		// ltrim( 'gshow.globo.com', 'gshow.' ) used to yield 'lobo.com'.
		$this->assertSame( 'Globo', Host_Name::guess( 'gshow.globo.com' ) );
	}

	public function test_guess_regression_cases_from_the_suffix_list(): void {
		// Previously 'Rtve.' with a trailing dot, because 'es' had no leading dot.
		$this->assertSame( 'Rtve', Host_Name::guess( 'www.rtve.es' ) );
		// Previously 'Therokuchannel.roku'.
		$this->assertSame( 'Roku', Host_Name::guess( 'therokuchannel.roku.com' ) );
		// Previously 'Iview.abc.net.au' untouched.
		$this->assertSame( 'ABC', Host_Name::guess( 'iview.abc.net.au' ) );
	}

	public function test_guess_never_returns_empty(): void {
		$this->assertSame( 'Watch Online', Host_Name::guess( '' ) );
		$this->assertSame( 'Watch Online', Host_Name::guess( '...' ) );
	}

	/*
	 * host_candidates()
	 */

	public function test_host_candidates_orders_most_specific_first(): void {
		$out = Host_Name::host_candidates( 'abc.go.com' );

		$this->assertSame( 'abc.go.com', $out[0], 'The full host must be tried first.' );
		$this->assertContains( 'go.com', $out );
		$this->assertLessThan(
			array_search( 'go.com', $out, true ),
			array_search( 'abc.go.com', $out, true ),
			'A term on the full host must win over one on the registrable domain.'
		);
	}

	public function test_host_candidates_offers_www_variants(): void {
		$out = Host_Name::host_candidates( 'globo.com' );

		$this->assertContains( 'globo.com', $out );
		$this->assertContains( 'www.globo.com', $out );
	}

	public function test_host_candidates_finds_registrable_domain_without_a_prefix_list(): void {
		// Nothing anywhere lists 'gshow.', yet globo.com is still offered.
		$this->assertContains( 'globo.com', Host_Name::host_candidates( 'gshow.globo.com' ) );
	}

	public function test_host_candidates_stops_at_the_registrable_domain(): void {
		$out = Host_Name::host_candidates( 'iview.abc.net.au' );

		$this->assertContains( 'iview.abc.net.au', $out );
		$this->assertContains( 'abc.net.au', $out );
		$this->assertNotContains( 'net.au', $out, 'Never degrade to a bare public suffix.' );
	}

	public function test_host_candidates_never_degrades_to_a_two_label_suffix(): void {
		$out = Host_Name::host_candidates( 'alibi.uktv.co.uk' );

		$this->assertContains( 'uktv.co.uk', $out );
		$this->assertNotContains( 'co.uk', $out );
	}

	public function test_host_candidates_are_unique(): void {
		$out = Host_Name::host_candidates( 'www.netflix.com' );

		$this->assertSame( array_values( array_unique( $out ) ), $out );
	}

	public function test_host_candidates_empty_input(): void {
		$this->assertSame( array(), Host_Name::host_candidates( '' ) );
	}
}
