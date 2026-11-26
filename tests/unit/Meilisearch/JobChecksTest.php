<?php
/**
 * Unit tests for the job checks: failed tasks, the prune keep-set, a valid constant.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Job_Checks;
use LWTV\Meilisearch\Build\Search_Settings;

class JobChecksTest extends TestCase {

	public function test_first_failed_task_error(): void {
		$response = array(
			'results' => array(
				array(
					'uid'    => 12,
					'status' => 'failed',
					'error'  => array(
						'code'    => 'invalid_document_id',
						'message' => 'Document identifier `a b` is invalid.',
					),
				),
				array(
					'uid'    => 9,
					'status' => 'failed',
					'error'  => array( 'code' => 'x' ),
				),
			),
		);

		$this->assertSame( 'Task 12 failed: invalid_document_id — Document identifier `a b` is invalid.', Job_Checks::first_failure( $response ) );
		$this->assertNull( Job_Checks::first_failure( array( 'results' => array() ) ) );
		$this->assertNull( Job_Checks::first_failure( array() ) );
		$this->assertSame(
			'Task 3 failed: canceled',
			Job_Checks::first_failure(
				array(
					'results' => array(
						array(
							'uid'    => 3,
							'status' => 'canceled',
						),
					),
				)
			)
		);
	}

	public function test_keep_set_spares_posts_published_mid_job_and_prunes_skipped_ones(): void {
		// 5 was seen but not built (a hide_all actor): pruned. 7 was published
		// after its page was read (not seen): kept. 2 was trashed before its page: gone.
		$keep = Job_Checks::keep( array( 1, 3, 4 ), array( 1, 3, 4, 5 ), array( 1, 3, 4, 5, 7 ) );

		$this->assertSame( array( 1, 3, 4, 7 ), $keep );
	}

	public function test_keep_set_with_nothing_published(): void {
		$this->assertSame( array(), Job_Checks::keep( array(), array(), array() ) );
		$this->assertSame( array( 8 ), Job_Checks::keep( array( 8 ), array( 8 ), array() ) );
	}

	public function test_constant_validity_matches_how_search_reads_it(): void {
		$this->assertTrue( Search_Settings::constant_valid( true, 'editors' ) );
		$this->assertTrue( Search_Settings::constant_valid( true, 'Editors ' ) );
		$this->assertTrue( Search_Settings::constant_valid( true, 'EVERYONE' ) );
		$this->assertTrue( Search_Settings::constant_valid( true, 'off' ) );
		$this->assertFalse( Search_Settings::constant_valid( true, 'yes' ) );
		$this->assertFalse( Search_Settings::constant_valid( true, true ) );
		$this->assertFalse( Search_Settings::constant_valid( false, 'editors' ) );
	}
}
