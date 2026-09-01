<?php
/**
 * Unit tests for the findings-to-rows seam.
 *
 * Only from_term_findings() is covered: from_findings() calls get_permalink(),
 * which needs a live site. The interesting property here is the one the class
 * docblock claims -- the row is an *additive* superset, so a finding cached
 * before a context key existed still renders rather than erroring.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Format\Rows;

class RowsTest extends TestCase {

	/**
	 * A make_for_term()-shaped finding.
	 *
	 * @param array $context Context payload.
	 * @return array<string, mixed>
	 */
	private function finding( array $context ): array {
		return array(
			'post_id'     => 28118,
			'post_type'   => 'lez_watch_urls',
			'object_kind' => 'term',
			'issue_type'  => 'watch-url-broken',
			'message'     => 'Did not answer.',
			'context'     => $context,
		);
	}

	public function test_show_ids_are_lifted_out_of_context(): void {
		$rows = Rows::from_term_findings(
			array(
				$this->finding(
					array(
						'url'      => 'https://paramountplus.com',
						'term'     => 'Paramount+',
						'shows'    => 2,
						'health'   => 'broken',
						'show_ids' => array( 10, 20 ),
					)
				),
			)
		);

		$this->assertSame( array( 10, 20 ), $rows[0]['show_ids'] );
		$this->assertSame( 2, $rows[0]['shows'] );
	}

	public function test_a_finding_cached_before_show_ids_existed_omits_the_key(): void {
		$rows = Rows::from_term_findings(
			array(
				$this->finding(
					array(
						'url'    => 'https://paramountplus.com',
						'term'   => 'Paramount+',
						'shows'  => 2,
						'health' => 'broken',
					)
				),
			)
		);

		$this->assertArrayNotHasKey( 'show_ids', $rows[0] );
		$this->assertSame( 2, $rows[0]['shows'] );
	}

	public function test_reason_is_lifted_out_of_context(): void {
		$rows = Rows::from_term_findings(
			array(
				$this->finding(
					array(
						'url'    => 'https://secretmessageproductions.com',
						'term'   => 'Secret Message Productions',
						'health' => 'review',
						'reason' => 'name_mismatch',
					)
				),
			)
		);

		$this->assertSame( 'name_mismatch', $rows[0]['reason'] );
	}

	public function test_a_finding_cached_before_reason_existed_omits_the_key(): void {
		$rows = Rows::from_term_findings(
			array(
				$this->finding(
					array(
						'url'    => 'https://paramountplus.com',
						'term'   => 'Paramount+',
						'health' => 'broken',
					)
				),
			)
		);

		$this->assertArrayNotHasKey( 'reason', $rows[0] );
	}

	public function test_an_empty_show_id_list_is_still_lifted(): void {
		// Distinct from the key being absent: this term has no shows, which the
		// renderer must be able to tell from "we don't know".
		$rows = Rows::from_term_findings( array( $this->finding( array( 'show_ids' => array() ) ) ) );

		$this->assertSame( array(), $rows[0]['show_ids'] );
	}

	public function test_the_rest_of_the_row_is_untouched(): void {
		$rows = Rows::from_term_findings(
			array(
				$this->finding(
					array(
						'url'      => 'https://paramountplus.com',
						'term'     => 'Paramount+',
						'show_ids' => array( 10 ),
					)
				),
			)
		);

		$this->assertSame( 28118, $rows[0]['id'] );
		$this->assertSame( 'term', $rows[0]['object_kind'] );
		$this->assertSame( 'lez_watch_urls', $rows[0]['object_type'] );
		$this->assertSame( array( 'watch-url-broken' ), $rows[0]['issues'] );
		$this->assertSame( array( 'Did not answer.' ), $rows[0]['messages'] );
	}
}
