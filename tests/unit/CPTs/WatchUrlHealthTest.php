<?php
/**
 * Unit tests for classifying a probed Ways to Watch provider URL.
 *
 * The cases that matter are the ones a status code cannot see. quibi.com still
 * resolves and still returns HTTP 200; it is an online casino now. Those are
 * covered here alongside the ordinary dead-link handling.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watch_Url_Health;

class WatchUrlHealthTest extends TestCase {

	/**
	 * Build a probe result, defaulting to a perfectly healthy one.
	 *
	 * @param array $overrides Keys to change.
	 * @return array
	 */
	private function probe( array $overrides = array() ): array {
		return array_merge(
			array(
				'error'     => '',
				'code'      => 200,
				'final_url' => '',
				'site_name' => '',
				'body'      => '',
			),
			$overrides
		);
	}

	/*
	 * Status codes
	 */

	public function test_healthy_url_is_ok(): void {
		$result = Watch_Url_Health::classify( $this->probe(), 'Netflix', 'https://netflix.com' );

		$this->assertSame( Watch_Url_Health::STATUS_OK, $result['status'] );
		$this->assertSame( '', $result['problem'] );
	}

	public function test_all_2xx_codes_are_healthy(): void {
		foreach ( array( 200, 201, 204, 299 ) as $code ) {
			$result = Watch_Url_Health::classify( $this->probe( array( 'code' => $code ) ), 'Netflix', 'https://netflix.com' );
			$this->assertSame( Watch_Url_Health::STATUS_OK, $result['status'], 'HTTP ' . $code );
		}
	}

	public function test_transport_error_is_broken(): void {
		$result = Watch_Url_Health::classify(
			$this->probe( array( 'error' => 'cURL error 6: Could not resolve host' ) ),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
		$this->assertStringContainsString( 'Could not resolve host', $result['problem'] );
	}

	public function test_error_wins_over_a_stale_status_code(): void {
		// A transport error with a leftover code must not read as healthy.
		$result = Watch_Url_Health::classify(
			$this->probe(
				array(
					'error' => 'Operation timed out',
					'code'  => 200,
				)
			),
			'Netflix',
			'https://netflix.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}

	public function test_404_and_410_are_broken(): void {
		foreach ( array( 404, 410 ) as $code ) {
			$result = Watch_Url_Health::classify( $this->probe( array( 'code' => $code ) ), 'Netflix', 'https://netflix.com' );
			$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'], 'HTTP ' . $code );
		}
	}

	public function test_refusals_are_blocked_not_broken(): void {
		// These are about us, not about the link, so they must not be filed
		// alongside genuinely dead URLs.
		foreach ( array( 401, 403, 407, 429, 451 ) as $code ) {
			$result = Watch_Url_Health::classify( $this->probe( array( 'code' => $code ) ), 'Hulu', 'https://hulu.com' );
			$this->assertSame( Watch_Url_Health::STATUS_BLOCKED, $result['status'], 'HTTP ' . $code );
		}
	}

	public function test_server_errors_are_review(): void {
		foreach ( array( 500, 502, 503 ) as $code ) {
			$result = Watch_Url_Health::classify( $this->probe( array( 'code' => $code ) ), 'Hulu', 'https://hulu.com' );
			$this->assertSame( Watch_Url_Health::STATUS_REVIEW, $result['status'], 'HTTP ' . $code );
		}
	}

	public function test_missing_status_code_is_broken_not_silent(): void {
		$result = Watch_Url_Health::classify( $this->probe( array( 'code' => 0 ) ), 'Hulu', 'https://hulu.com' );

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}

	public function test_exhausted_redirects_are_review(): void {
		$result = Watch_Url_Health::classify( $this->probe( array( 'code' => 301 ) ), 'Hulu', 'https://hulu.com' );

		$this->assertSame( Watch_Url_Health::STATUS_REVIEW, $result['status'] );
	}

	/*
	 * Parked domains
	 */

	public function test_parking_page_copy_is_broken(): void {
		$result = Watch_Url_Health::classify(
			$this->probe( array( 'body' => '<h1>Quibi.com</h1><p>This domain is for sale. Inquire about this domain.</p>' ) ),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
		$this->assertStringContainsString( 'parking', $result['problem'] );
	}

	public function test_parking_service_assets_are_broken(): void {
		$result = Watch_Url_Health::classify(
			$this->probe( array( 'body' => '<script src="https://img.sedoparking.com/x.js"></script>' ) ),
			'Seeso',
			'https://seeso.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}

	public function test_parked_detection_is_case_insensitive(): void {
		// classify() lowercases before matching, so markers hit whatever the
		// page's own capitalisation is.
		$this->assertTrue( Watch_Url_Health::looks_parked( 'buy this domain' ) );

		$result = Watch_Url_Health::classify(
			$this->probe( array( 'body' => 'BUY THIS DOMAIN' ) ),
			'Seeso',
			'https://seeso.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}

	public function test_ordinary_body_is_not_parked(): void {
		$this->assertFalse( Watch_Url_Health::looks_parked( '' ) );
		$this->assertFalse( Watch_Url_Health::looks_parked( '<h1>watch free movies and tv shows online</h1>' ) );
	}

	/*
	 * Off-site redirects
	 */

	public function test_redirect_within_the_same_domain_is_not_reported(): void {
		$this->assertSame( '', Watch_Url_Health::offsite_redirect( 'https://netflix.com', 'https://www.netflix.com/browse' ) );
		$this->assertSame( '', Watch_Url_Health::offsite_redirect( 'https://abc.go.com', 'https://go.com/abc' ) );
	}

	public function test_redirect_to_another_company_is_reported(): void {
		$this->assertSame(
			'luckystarcasino.com',
			Watch_Url_Health::offsite_redirect( 'https://quibi.com', 'https://luckystarcasino.com/slots' )
		);
	}

	public function test_compound_suffixes_are_not_mistaken_for_a_move(): void {
		// Naive "last two labels" comparison would read both of these as
		// 'co.uk' and call a genuine move a match.
		$this->assertSame( '', Watch_Url_Health::offsite_redirect( 'https://bbc.co.uk/iplayer', 'https://www.bbc.co.uk/iplayer' ) );
		$this->assertSame( 'itv.co.uk', Watch_Url_Health::offsite_redirect( 'https://bbc.co.uk', 'https://itv.co.uk' ) );
	}

	public function test_unknown_final_url_is_not_reported(): void {
		// Not every transport tells us where we landed; absence isn't a move.
		$this->assertSame( '', Watch_Url_Health::offsite_redirect( 'https://netflix.com', '' ) );
	}

	public function test_offsite_redirect_surfaces_as_review(): void {
		$result = Watch_Url_Health::classify(
			$this->probe( array( 'final_url' => 'https://luckystarcasino.com/' ) ),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_REVIEW, $result['status'] );
		$this->assertStringContainsString( 'luckystarcasino.com', $result['problem'] );
	}

	/*
	 * Provider name drift
	 */

	public function test_matching_name_is_fine(): void {
		$this->assertTrue( Watch_Url_Health::name_matches( 'Netflix', 'Netflix', 'netflix.com' ) );
	}

	public function test_punctuation_and_case_are_ignored(): void {
		$this->assertTrue( Watch_Url_Health::name_matches( 'BBC iPlayer', 'bbc  iplayer', 'bbc.co.uk' ) );
	}

	public function test_name_inside_a_tagline_still_matches(): void {
		$this->assertTrue(
			Watch_Url_Health::name_matches( 'Tubi', 'Tubi - Watch Free Movies and TV Shows Online', 'tubitv.com' )
		);
	}

	public function test_shorter_published_name_still_matches(): void {
		// 'Prime Video' is inside 'Amazon Prime Video'.
		$this->assertTrue( Watch_Url_Health::name_matches( 'Amazon Prime Video', 'Prime Video', 'amazon.com' ) );
	}

	public function test_hostname_confirms_when_the_term_name_does_not(): void {
		// A term named for a product on a domain named for the broadcaster.
		$this->assertTrue( Watch_Url_Health::name_matches( 'BBC iPlayer', 'CBC Gem', 'gem.cbc.ca' ) );
	}

	public function test_no_published_name_is_not_evidence(): void {
		$this->assertTrue( Watch_Url_Health::name_matches( 'Netflix', '', 'netflix.com' ) );
	}

	public function test_two_letter_published_name_is_not_judged(): void {
		$this->assertTrue( Watch_Url_Health::name_matches( 'Netflix', 'GO', 'netflix.com' ) );
	}

	public function test_short_known_names_are_not_judged(): void {
		// Term 'GO' on go.com: both known names are two characters, which
		// collide with too much ordinary text to be evidence.
		$this->assertTrue( Watch_Url_Health::name_matches( 'GO', 'Lucky Star Casino', 'go.com' ) );
	}

	public function test_drifted_name_is_caught(): void {
		$this->assertFalse( Watch_Url_Health::name_matches( 'Quibi', 'Lucky Star Casino', 'quibi.com' ) );
	}

	public function test_drifted_name_surfaces_as_review_with_both_names(): void {
		$result = Watch_Url_Health::classify(
			$this->probe( array( 'site_name' => 'Lucky Star Casino' ) ),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_REVIEW, $result['status'] );
		$this->assertStringContainsString( 'Lucky Star Casino', $result['problem'] );
		$this->assertStringContainsString( 'Quibi', $result['problem'] );
	}

	public function test_acronym_terms_are_judged(): void {
		// Three characters is the floor, so HBO is comparable.
		$this->assertTrue( Watch_Url_Health::name_matches( 'HBO Max', 'Max', 'hbomax.com' ) );
		$this->assertFalse( Watch_Url_Health::name_matches( 'HBO Max', 'Bet365', 'hbomax.com' ) );
	}

	/*
	 * Precedence
	 */

	public function test_a_dead_status_code_is_reported_before_content_signals(): void {
		// A 404 body can say anything; the code is the actionable fact.
		$result = Watch_Url_Health::classify(
			$this->probe(
				array(
					'code'      => 404,
					'body'      => 'this domain is for sale',
					'site_name' => 'Lucky Star Casino',
				)
			),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
		$this->assertStringContainsString( '404', $result['problem'] );
	}

	public function test_parking_is_reported_before_name_drift(): void {
		$result = Watch_Url_Health::classify(
			$this->probe(
				array(
					'body'      => 'buy this domain',
					'site_name' => 'Lucky Star Casino',
				)
			),
			'Quibi',
			'https://quibi.com'
		);

		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}

	public function test_missing_probe_keys_do_not_fatal(): void {
		$result = Watch_Url_Health::classify( array(), 'Netflix', 'https://netflix.com' );

		// An empty probe has no status code, which is a real problem, not a pass.
		$this->assertSame( Watch_Url_Health::STATUS_BROKEN, $result['status'] );
	}
}
