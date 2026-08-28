<?php
/**
 * Unit tests for the lez_watch_urls term URL audit.
 *
 * The audit's job is to say whether switching term matching from exact-URL to
 * normalised-host is safe, so the tests care most about the blocking/cosmetic
 * split and about collision detection. Getting that split wrong in either
 * direction is expensive: a false cosmetic lets one term swallow a whole host,
 * a false blocking stalls the change for nothing.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watch_Term_Url_Audit as Audit;

class WatchTermUrlAuditTest extends TestCase {

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
	 * The flags on the first inspected row.
	 *
	 * @param array<int, array{term_id: int, name: string, url: string}> $rows Rows.
	 * @return array<string>
	 */
	private function flags_for( array $rows ): array {
		$report = Audit::inspect( $rows );

		return $report['rows'][0]['flags'];
	}

	/*
	 * The clean case.
	 */

	public function test_bare_https_host_is_unflagged(): void {
		$report = Audit::inspect( array( $this->row( 1, 'Hulu', 'https://hulu.com' ) ) );

		$this->assertSame( array(), $report['rows'][0]['flags'] );
		$this->assertFalse( $report['rows'][0]['blocking'] );
		$this->assertSame( 'hulu.com', $report['rows'][0]['host'] );
		$this->assertSame( 'https://hulu.com', $report['rows'][0]['bare'] );
		$this->assertSame( 0, $report['totals']['flagged'] );
	}

	/*
	 * Cosmetic flags -- host matching fixes these, the data is just untidy.
	 */

	public function test_trailing_slash_is_cosmetic(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'https://hulu.com/' ) ) );

		$this->assertContains( Audit::FLAG_TRAILING_SLASH, $flags );
		$this->assertNotContains( Audit::FLAG_PATH, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	public function test_uppercase_host_is_cosmetic(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'https://Hulu.com' ) ) );

		$this->assertContains( Audit::FLAG_UPPERCASE, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	public function test_http_scheme_is_cosmetic(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'http://hulu.com' ) ) );

		$this->assertContains( Audit::FLAG_HTTP_SCHEME, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	public function test_www_prefix_is_cosmetic(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'https://www.hulu.com' ) ) );

		$this->assertContains( Audit::FLAG_WWW, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	public function test_port_is_cosmetic(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'https://hulu.com:443' ) ) );

		$this->assertContains( Audit::FLAG_PORT, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	public function test_missing_scheme_is_cosmetic_and_still_yields_a_host(): void {
		$report = Audit::inspect( array( $this->row( 1, 'Hulu', 'hulu.com' ) ) );
		$flags  = $report['rows'][0]['flags'];

		$this->assertContains( Audit::FLAG_NO_SCHEME, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
		$this->assertSame( 'hulu.com', $report['rows'][0]['host'] );
	}

	public function test_protocol_relative_url_is_cosmetic(): void {
		$report = Audit::inspect( array( $this->row( 1, 'Hulu', '//hulu.com' ) ) );

		$this->assertContains( Audit::FLAG_NO_SCHEME, $report['rows'][0]['flags'] );
		$this->assertSame( 'hulu.com', $report['rows'][0]['host'] );
	}

	public function test_cosmetic_flags_stack(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Hulu', 'http://WWW.Hulu.com/' ) ) );

		$this->assertContains( Audit::FLAG_HTTP_SCHEME, $flags );
		$this->assertContains( Audit::FLAG_UPPERCASE, $flags );
		$this->assertContains( Audit::FLAG_WWW, $flags );
		$this->assertContains( Audit::FLAG_TRAILING_SLASH, $flags );
		$this->assertFalse( Audit::is_blocking( $flags ) );
	}

	/*
	 * Blocking flags -- host matching would change what the row means.
	 */

	public function test_path_is_blocking(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Some Web Series', 'https://youtube.com/c/somechannel' ) ) );

		$this->assertContains( Audit::FLAG_PATH, $flags );
		$this->assertTrue( Audit::is_blocking( $flags ) );
	}

	public function test_query_is_blocking(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Thing', 'https://youtube.com/watch?v=abc123' ) ) );

		$this->assertContains( Audit::FLAG_QUERY, $flags );
		$this->assertTrue( Audit::is_blocking( $flags ) );
	}

	public function test_fragment_is_blocking(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Thing', 'https://example.com/#season-2' ) ) );

		$this->assertContains( Audit::FLAG_FRAGMENT, $flags );
		$this->assertTrue( Audit::is_blocking( $flags ) );
	}

	public function test_credentials_are_blocking(): void {
		$flags = $this->flags_for( array( $this->row( 1, 'Thing', 'https://user:pass@example.com' ) ) );

		$this->assertContains( Audit::FLAG_CREDENTIALS, $flags );
		$this->assertTrue( Audit::is_blocking( $flags ) );
	}

	public function test_empty_url_is_unparseable_and_blocking(): void {
		$report = Audit::inspect( array( $this->row( 1, 'Thing', '' ) ) );

		$this->assertSame( array( Audit::FLAG_UNPARSEABLE ), $report['rows'][0]['flags'] );
		$this->assertTrue( $report['rows'][0]['blocking'] );
		$this->assertSame( '', $report['rows'][0]['host'] );
		$this->assertSame( '', $report['rows'][0]['bare'] );
	}

	public function test_scheme_with_no_host_is_unparseable(): void {
		$report = Audit::inspect( array( $this->row( 1, 'Thing', 'https://' ) ) );

		$this->assertSame( array( Audit::FLAG_UNPARSEABLE ), $report['rows'][0]['flags'] );
		$this->assertSame( '', $report['rows'][0]['host'] );
	}

	public function test_a_broken_value_is_never_retried_into_a_scheme_shaped_host(): void {
		// The retry-with-https path must not fire on a value that already has
		// '://', or 'https://' would come back as the host 'https'.
		$report = Audit::inspect( array( $this->row( 1, 'Thing', 'ftp://' ) ) );

		$this->assertNotSame( 'ftp', $report['rows'][0]['host'] );
		$this->assertNotSame( 'https', $report['rows'][0]['host'] );
		$this->assertContains( Audit::FLAG_UNPARSEABLE, $report['rows'][0]['flags'] );
	}

	/*
	 * Collisions: two different terms reducing to one host.
	 */

	public function test_two_terms_on_one_host_collide(): void {
		$report = Audit::inspect(
			array(
				$this->row( 10, 'Netflix', 'https://netflix.com' ),
				$this->row( 20, 'Netflix UK', 'https://www.netflix.com/' ),
			)
		);

		$this->assertArrayHasKey( 'netflix.com', $report['collisions'] );
		$this->assertSame(
			array(
				10 => 'Netflix',
				20 => 'Netflix UK',
			),
			$report['collisions']['netflix.com']
		);
		$this->assertSame( 1, $report['totals']['collisions'] );
	}

	public function test_one_term_on_two_hosts_is_not_a_collision(): void {
		$report = Audit::inspect(
			array(
				$this->row( 10, 'Netflix', 'https://netflix.com' ),
				$this->row( 10, 'Netflix', 'https://netflix.co.uk' ),
			)
		);

		$this->assertSame( array(), $report['collisions'] );
		$this->assertSame( 2, $report['totals']['hosts'] );
		$this->assertSame( 1, $report['totals']['terms'] );
	}

	public function test_same_host_twice_on_one_term_is_a_duplicate_not_a_collision(): void {
		$report = Audit::inspect(
			array(
				$this->row( 10, 'Netflix', 'https://netflix.com' ),
				$this->row( 10, 'Netflix', 'https://www.netflix.com' ),
			)
		);

		$this->assertSame( array(), $report['collisions'] );
		$this->assertContains( Audit::FLAG_DUPLICATE, $report['rows'][1]['flags'] );
		$this->assertFalse( $report['rows'][1]['blocking'] );
	}

	public function test_subdomains_are_distinct_hosts(): void {
		// Host_Name::normalise() keeps meaningful subdomains, so these must not
		// collapse into one another or the audit would invent a collision.
		$report = Audit::inspect(
			array(
				$this->row( 10, 'ABC', 'https://abc.go.com' ),
				$this->row( 20, 'Disney', 'https://go.com' ),
			)
		);

		$this->assertSame( array(), $report['collisions'] );
		$this->assertSame( 2, $report['totals']['hosts'] );
	}

	/*
	 * Show counts and totals.
	 */

	public function test_show_counts_are_attached_by_host(): void {
		$report = Audit::inspect(
			array(
				$this->row( 10, 'Hulu', 'https://www.hulu.com/' ),
				$this->row( 20, 'Nobody', 'https://nowhere.example' ),
			),
			array( 'hulu.com' => 42 )
		);

		$this->assertSame( 42, $report['rows'][0]['shows'] );
		$this->assertSame( 0, $report['rows'][1]['shows'] );
	}

	public function test_flag_counts_and_totals_add_up(): void {
		$report = Audit::inspect(
			array(
				$this->row( 10, 'Clean', 'https://clean.example' ),
				$this->row( 20, 'Slash', 'https://slash.example/' ),
				$this->row( 30, 'Slash Two', 'https://slashtwo.example/' ),
				$this->row( 40, 'Pathy', 'https://pathy.example/a/b' ),
			)
		);

		$this->assertSame( 4, $report['totals']['rows'] );
		$this->assertSame( 3, $report['totals']['flagged'] );
		$this->assertSame( 1, $report['totals']['blocking'] );
		$this->assertSame( 2, $report['flag_counts'][ Audit::FLAG_TRAILING_SLASH ] );
		$this->assertSame( 1, $report['flag_counts'][ Audit::FLAG_PATH ] );
	}

	public function test_empty_input_is_a_clean_empty_report(): void {
		$report = Audit::inspect( array() );

		$this->assertSame( array(), $report['rows'] );
		$this->assertSame( array(), $report['collisions'] );
		$this->assertSame( 0, $report['totals']['rows'] );
		$this->assertSame( 0, $report['totals']['blocking'] );
	}

	public function test_is_blocking_on_an_empty_flag_list(): void {
		$this->assertFalse( Audit::is_blocking( array() ) );
	}

	/*
	 * canonical_urls() -- what set_term_urls() writes back.
	 */

	public function test_canonical_urls_strips_www_slash_and_scheme_variance(): void {
		$this->assertSame(
			array( 'https://vix.com' ),
			Audit::canonical_urls(
				array(
					'https://vix.com/',
					'https://www.vix.com/',
					'http://VIX.com',
				)
			)
		);
	}

	public function test_canonical_urls_keeps_distinct_hosts_in_order(): void {
		$this->assertSame(
			array( 'https://lesflicksvod.vhx.tv', 'https://lesflicks.com', 'https://lesflicksvod.com' ),
			Audit::canonical_urls(
				array(
					'https://lesflicksvod.vhx.tv',
					'https://www.lesflicks.com/',
					'https://lesflicksvod.com',
					'https://lesflicks.com',
				)
			)
		);
	}

	public function test_canonical_urls_keeps_meaningful_subdomains_apart(): void {
		$this->assertSame(
			array( 'https://abc.go.com', 'https://go.com' ),
			Audit::canonical_urls( array( 'https://abc.go.com', 'https://go.com' ) )
		);
	}

	public function test_canonical_urls_drops_unusable_values(): void {
		$this->assertSame(
			array( 'https://paus.tv' ),
			Audit::canonical_urls( array( '', 'https://', 'https://paus.tv', '   ' ) )
		);
	}

	public function test_canonical_urls_on_empty_input(): void {
		$this->assertSame( array(), Audit::canonical_urls( array() ) );
	}

	public function test_canonical_urls_accepts_a_bare_host(): void {
		$this->assertSame( array( 'https://hulu.com' ), Audit::canonical_urls( array( 'hulu.com' ) ) );
	}
}
