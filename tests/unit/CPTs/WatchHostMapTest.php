<?php
/**
 * Unit tests for host-based lez_watch_urls term resolution.
 *
 * The cases that matter are the ones exact-URL matching got wrong on the live
 * data — trailing slashes, `www.`, mixed case, a bare host — plus the two things
 * the old matcher got *right* and must keep getting right: subdomain precedence
 * (a term on 'abc.go.com' beats one on 'go.com') and never degrading to a public
 * suffix.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watch_Host_Map as Map;

class WatchHostMapTest extends TestCase {

	/**
	 * Build a term_urls()-shaped row.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $name    Term name.
	 * @param string $url     Stored URL.
	 * @return array{term_id: int, name: string, url: string}
	 */
	private function row( int $term_id, string $name, string $url ): array {
		return array(
			'term_id' => $term_id,
			'name'    => $name,
			'url'     => $url,
		);
	}

	/**
	 * Resolve one host against a map built from these rows.
	 *
	 * @param array  $rows Rows.
	 * @param string $host Host to look up.
	 * @return int Term ID, or 0.
	 */
	private function resolve( array $rows, string $host ): int {
		return Map::resolve( Map::build( $rows )['map'], $host );
	}

	/*
	 * The shapes exact-URL matching failed on. Each of these is a real row from
	 * the 2026-08-27 audit.
	 */

	public function test_trailing_slash_now_resolves(): void {
		// AcornTV, 11 shows, was rendering as "Acorn".
		$rows = array( $this->row( 28161, 'AcornTV', 'https://acorn.tv/' ) );

		$this->assertSame( 28161, $this->resolve( $rows, 'acorn.tv' ) );
	}

	public function test_www_prefix_now_resolves(): void {
		// Paramount+, 16 shows, was rendering as "Paramountplus".
		$rows = array( $this->row( 28118, 'Paramount+', 'https://www.paramountplus.com' ) );

		$this->assertSame( 28118, $this->resolve( $rows, 'paramountplus.com' ) );
		$this->assertSame( 28118, $this->resolve( $rows, 'www.paramountplus.com' ) );
	}

	public function test_www_and_trailing_slash_together_now_resolve(): void {
		// iQIYI, 6 shows, was rendering as "IQ".
		$rows = array( $this->row( 28621, 'iQIYI', 'https://www.iq.com/' ) );

		$this->assertSame( 28621, $this->resolve( $rows, 'iq.com' ) );
	}

	public function test_mixed_case_resolves(): void {
		$rows = array( $this->row( 1, 'Hulu', 'https://Hulu.com' ) );

		$this->assertSame( 1, $this->resolve( $rows, 'hulu.com' ) );
		$this->assertSame( 1, $this->resolve( $rows, 'HULU.COM' ) );
	}

	public function test_scheme_is_irrelevant_in_both_directions(): void {
		$rows = array( $this->row( 1, 'Hulu', 'http://hulu.com' ) );

		// The stored scheme was http; the lookup is by host, so it does not matter.
		$this->assertSame( 1, $this->resolve( $rows, 'hulu.com' ) );
	}

	public function test_stored_bare_host_resolves(): void {
		$rows = array( $this->row( 1, 'Hulu', 'hulu.com' ) );

		$this->assertSame( 1, $this->resolve( $rows, 'hulu.com' ) );
	}

	public function test_port_resolves(): void {
		$rows = array( $this->row( 1, 'Hulu', 'https://hulu.com:443' ) );

		$this->assertSame( 1, $this->resolve( $rows, 'hulu.com' ) );
	}

	/*
	 * Precedence the old matcher had, which must survive.
	 */

	public function test_more_specific_subdomain_wins(): void {
		$rows = array(
			$this->row( 28684, 'ABC/Disney', 'https://abc.go.com' ),
			$this->row( 99, 'Disney', 'https://go.com' ),
		);

		$this->assertSame( 28684, $this->resolve( $rows, 'abc.go.com' ) );
		$this->assertSame( 99, $this->resolve( $rows, 'go.com' ) );
	}

	public function test_unknown_subdomain_falls_back_to_the_registrable_domain(): void {
		// 'gshow.globo.com' should find a term registered on 'globo.com' without
		// anyone having listed 'gshow.' anywhere.
		$rows = array( $this->row( 28132, 'Globo', 'https://globo.com' ) );

		$this->assertSame( 28132, $this->resolve( $rows, 'gshow.globo.com' ) );
	}

	public function test_never_degrades_to_a_public_suffix(): void {
		// A term on 'bbc.co.uk' must not be reachable from an unrelated .co.uk.
		$rows = array( $this->row( 28676, 'BBC', 'https://bbc.co.uk' ) );

		$this->assertSame( 28676, $this->resolve( $rows, 'bbc.co.uk' ) );
		$this->assertSame( 0, $this->resolve( $rows, 'itv.co.uk' ) );
	}

	public function test_unrelated_host_resolves_to_nothing(): void {
		$rows = array( $this->row( 1, 'Hulu', 'https://hulu.com' ) );

		$this->assertSame( 0, $this->resolve( $rows, 'netflix.com' ) );
	}

	/*
	 * One term, several hosts. The normal case for a provider.
	 */

	public function test_one_term_owns_every_host_it_lists(): void {
		$rows = array(
			$this->row( 28664, 'HBO Max', 'https://play.hbomax.com' ),
			$this->row( 28664, 'HBO Max', 'https://hbo.com' ),
			$this->row( 28664, 'HBO Max', 'https://play.max.com' ),
			$this->row( 28664, 'HBO Max', 'https://hbomax.com' ),
		);

		foreach ( array( 'play.hbomax.com', 'hbo.com', 'play.max.com', 'hbomax.com' ) as $host ) {
			$this->assertSame( 28664, $this->resolve( $rows, $host ), $host );
		}
	}

	/*
	 * Collisions.
	 */

	public function test_two_terms_on_one_host_are_reported_and_first_wins(): void {
		$built = Map::build(
			array(
				$this->row( 28134, 'Lesflicks', 'https://www.lesflicks.com/' ),
				$this->row( 28671, 'LezFlicks', 'https://lesflicks.com' ),
			)
		);

		$this->assertSame( 28134, $built['map']['lesflicks.com'] );
		$this->assertSame(
			array(
				28134 => 'Lesflicks',
				28671 => 'LezFlicks',
			),
			$built['collisions']['lesflicks.com']
		);
	}

	public function test_one_term_listing_a_host_twice_is_not_a_collision(): void {
		$built = Map::build(
			array(
				$this->row( 28126, 'Cartoon Network', 'https://www.cartoonnetwork.com/' ),
				$this->row( 28126, 'Cartoon Network', 'https://cartoonnetwork.com' ),
			)
		);

		$this->assertSame( array(), $built['collisions'] );
		$this->assertSame( 28126, $built['map']['cartoonnetwork.com'] );
	}

	public function test_distinct_hosts_never_collide(): void {
		// fxnetwork.com vs fxnetworks.com -- one letter apart, two terms, and
		// deliberately NOT a collision. They are different hosts.
		$built = Map::build(
			array(
				$this->row( 28668, 'FX', 'https://fxnetwork.com' ),
				$this->row( 28689, 'FX Networks', 'https://fxnetworks.com' ),
			)
		);

		$this->assertSame( array(), $built['collisions'] );
	}

	/*
	 * Junk tolerance. A bad row must cost only itself.
	 */

	public function test_unusable_rows_are_skipped_without_claiming_anything(): void {
		$built = Map::build(
			array(
				$this->row( 1, 'Broken', '' ),
				$this->row( 2, 'Also broken', 'https://' ),
				$this->row( 3, 'Fine', 'https://hulu.com' ),
			)
		);

		$this->assertSame( array( 'hulu.com' => 3 ), $built['map'] );
		$this->assertSame( array(), $built['collisions'] );
	}

	public function test_a_row_with_no_term_id_is_skipped(): void {
		$built = Map::build( array( $this->row( 0, 'No ID', 'https://hulu.com' ) ) );

		$this->assertSame( array(), $built['map'] );
	}

	public function test_empty_map_and_empty_host(): void {
		$this->assertSame( array(), Map::build( array() )['map'] );
		$this->assertSame( 0, Map::resolve( array(), 'hulu.com' ) );
		$this->assertSame( 0, Map::resolve( array( 'hulu.com' => 1 ), '' ) );
	}

	/*
	 * A path on a stored URL is ignored, not honoured. The audit confirmed no
	 * term carries one; if one appears, it claims the host rather than silently
	 * matching nothing, and the audit reports it as blocking.
	 */

	public function test_a_stored_path_claims_the_host(): void {
		$rows = array( $this->row( 1, 'Some Series', 'https://youtube.com/c/somechannel' ) );

		$this->assertSame( 1, $this->resolve( $rows, 'youtube.com' ) );
	}
}
