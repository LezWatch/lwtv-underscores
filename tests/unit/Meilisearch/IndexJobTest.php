<?php
/**
 * Unit tests for the index job state machine.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Index_Job;

class IndexJobTest extends TestCase {

	/**
	 * Advance through events, returning the final job.
	 *
	 * @param array $job    Job.
	 * @param array $events List of array( event, data ).
	 * @return array
	 */
	private function run_events( array $job, array $events ): array {
		foreach ( $events as $e ) {
			$job = Index_Job::advance( $job, $e[0], $e[1] ?? array(), 100 );
		}
		return $job;
	}

	public function test_can_start(): void {
		$running = Index_Job::start( Index_Job::REINDEX, 3, 1 );

		$this->assertIsString( Index_Job::can_start( $running, Index_Job::APPLY, 'exists' ) );
		$this->assertIsString( Index_Job::can_start( null, Index_Job::SETUP, 'exists' ) );
		$this->assertIsString( Index_Job::can_start( null, Index_Job::REINDEX, 'missing' ) );
		$this->assertIsString( Index_Job::can_start( null, Index_Job::APPLY, 'missing' ) );
		$this->assertIsString( Index_Job::can_start( null, Index_Job::REINDEX, 'unknown' ) );
		$this->assertIsString( Index_Job::can_start( null, 'fresh', 'exists' ) );

		$this->assertNull( Index_Job::can_start( null, Index_Job::REINDEX, 'exists' ) );
		$this->assertNull( Index_Job::can_start( null, Index_Job::APPLY, 'exists' ) );
		$this->assertNull( Index_Job::can_start( null, Index_Job::SETUP, 'missing' ) );

		$done = Index_Job::advance( $running, 'cancel', array(), 2 );
		$this->assertNull( Index_Job::can_start( $done, Index_Job::REINDEX, 'exists' ) );
	}

	public function test_start_shapes(): void {
		$this->assertSame(
			array(
				'kind'     => 'reindex',
				'state'    => 'running',
				'step'     => 'batch',
				'page'     => 1,
				'last_id'  => 0,
				'sent'     => 0,
				'total'    => 930,
				'task'     => null,
				'message'  => '',
				'started'  => 50,
				'finished' => 0,
			),
			Index_Job::start( Index_Job::REINDEX, 930, 50 )
		);
		$this->assertSame( 'settings', Index_Job::start( Index_Job::APPLY, 0, 1 )['step'] );
		$this->assertSame( 0, Index_Job::start( Index_Job::APPLY, 0, 1 )['page'] );
		$this->assertSame( 'check', Index_Job::start( Index_Job::SETUP, 930, 1 )['step'] );
	}

	public function test_apply_happy_path(): void {
		$job = Index_Job::start( Index_Job::APPLY, 0, 1 );

		$job = Index_Job::advance( $job, 'settings_sent', array( 'task' => 7 ), 2 );
		$this->assertSame( array( 'wait', 7, 'running' ), array( $job['step'], $job['task'], $job['state'] ) );

		$job = Index_Job::advance( $job, 'task_ok', array(), 3 );
		$this->assertSame( array( 'done', 3 ), array( $job['state'], $job['finished'] ) );
	}

	public function test_reindex_happy_path(): void {
		$job = Index_Job::start( Index_Job::REINDEX, 930, 1 );

		$job = Index_Job::advance(
			$job,
			'batch_sent',
			array(
				'sent'    => 500,
				'last'    => false,
				'last_id' => 4021,
			),
			2
		);
		$this->assertSame( array( 'batch', 2, 500, 4021 ), array( $job['step'], $job['page'], $job['sent'], $job['last_id'] ) );

		$job = Index_Job::advance(
			$job,
			'batch_sent',
			array(
				'sent'    => 430,
				'last'    => true,
				'last_id' => 98811,
			),
			3
		);
		$this->assertSame( array( 'prune', 2, 930, 98811 ), array( $job['step'], $job['page'], $job['sent'], $job['last_id'] ) );

		$job = Index_Job::advance( $job, 'pruned', array( 'task' => 12 ), 4 );
		$this->assertSame( array( 'wait', 12 ), array( $job['step'], $job['task'] ) );

		$job = Index_Job::advance( $job, 'task_ok', array(), 5 );
		$this->assertSame( array( 'done', 930, 5 ), array( $job['state'], $job['sent'], $job['finished'] ) );
	}

	public function test_setup_happy_path(): void {
		$job = Index_Job::start( Index_Job::SETUP, 930, 1 );

		$job = Index_Job::advance( $job, 'missing', array(), 2 );
		$this->assertSame( array( 'create', null ), array( $job['step'], $job['task'] ) );

		$job = Index_Job::advance( $job, 'created', array( 'task' => 3 ), 2 );
		$this->assertSame( array( 'create', 3 ), array( $job['step'], $job['task'] ) );

		$job = Index_Job::advance( $job, 'task_ok', array(), 3 );
		$this->assertSame( array( 'settings', null ), array( $job['step'], $job['task'] ) );

		$job = Index_Job::advance( $job, 'settings_sent', array( 'task' => 4 ), 4 );
		$this->assertSame( array( 'batch', 1, 4, 0 ), array( $job['step'], $job['page'], $job['task'], $job['last_id'] ) );

		$job = $this->run_events(
			$job,
			array(
				array(
					'batch_sent',
					array(
						'sent' => 930,
						'last' => true,
					),
				),
				array( 'pruned', array( 'task' => 9 ) ),
				array( 'task_ok' ),
			)
		);
		$this->assertSame( array( 'done', 930 ), array( $job['state'], $job['sent'] ) );
	}

	public function test_setup_finds_the_index_already_made(): void {
		$at_check = Index_Job::advance( Index_Job::start( Index_Job::SETUP, 1, 1 ), 'exists', array(), 2 );
		$this->assertSame( array( 'settings', 'running' ), array( $at_check['step'], $at_check['state'] ) );

		$at_create = $this->run_events( Index_Job::start( Index_Job::SETUP, 1, 1 ), array( array( 'missing' ), array( 'exists' ) ) );
		$this->assertSame( array( 'settings', 'running' ), array( $at_create['step'], $at_create['state'] ) );
	}

	public function test_index_gone_mid_job_fails(): void {
		$job = $this->run_events(
			Index_Job::start( Index_Job::REINDEX, 930, 1 ),
			array(
				array(
					'batch_sent',
					array(
						'sent' => 500,
						'last' => false,
					),
				),
				array( 'missing' ),
			)
		);

		$this->assertSame( 'failed', $job['state'] );
		$this->assertSame( 'The index no longer exists.', $job['message'] );
		$this->assertSame( 500, $job['sent'] );

		$apply = Index_Job::advance( Index_Job::start( Index_Job::APPLY, 0, 1 ), 'missing', array(), 2 );
		$this->assertSame( 'failed', $apply['state'] );
	}

	public function test_cancel_stops_everything(): void {
		$job = $this->run_events(
			Index_Job::start( Index_Job::REINDEX, 930, 1 ),
			array(
				array(
					'batch_sent',
					array(
						'sent' => 500,
						'last' => false,
					),
				),
				array( 'cancel' ),
			)
		);
		$this->assertSame( array( 'cancelled', 2, 500, 100 ), array( $job['state'], $job['page'], $job['sent'], $job['finished'] ) );

		$after = $this->run_events(
			$job,
			array(
				array(
					'batch_sent',
					array(
						'sent' => 430,
						'last' => true,
					),
				),
				array( 'task_ok' ),
			)
		);
		$this->assertSame( $job, $after );
	}

	public function test_failed_keeps_progress(): void {
		$job = $this->run_events(
			Index_Job::start( Index_Job::REINDEX, 930, 1 ),
			array(
				array(
					'batch_sent',
					array(
						'sent' => 500,
						'last' => false,
					),
				),
				array( 'failed', array( 'message' => 'HTTP 500: internal — boom' ) ),
			)
		);

		$this->assertSame( array( 'failed', 500, 'HTTP 500: internal — boom' ), array( $job['state'], $job['sent'], $job['message'] ) );
		$this->assertSame( $job, Index_Job::advance( $job, 'cancel', array(), 200 ) );
	}

	public function test_a_batch_without_a_last_id_keeps_the_cursor(): void {
		$job = Index_Job::advance(
			Index_Job::start( Index_Job::REINDEX, 1, 1 ),
			'batch_sent',
			array(
				'sent' => 0,
				'last' => true,
			),
			2
		);

		$this->assertSame( 0, $job['last_id'] );
	}

	public function test_is_running(): void {
		$this->assertTrue( Index_Job::is_running( Index_Job::start( Index_Job::APPLY, 0, 1 ) ) );
		$this->assertFalse( Index_Job::is_running( null ) );
		$this->assertFalse( Index_Job::is_running( array( 'state' => 'done' ) ) );
	}

	public function test_connection_changed_only_when_a_recorded_fingerprint_differs(): void {
		$job = Index_Job::start( Index_Job::REINDEX, 10, 100 );
		$this->assertFalse( Index_Job::connection_changed( $job, 'abc' ), 'an old job with no fingerprint is left alone' );

		$job['connection'] = 'abc';
		$this->assertFalse( Index_Job::connection_changed( $job, 'abc' ) );
		$this->assertTrue( Index_Job::connection_changed( $job, 'xyz' ) );
	}

	public function test_fingerprint_hides_nothing_secret_and_tracks_url_and_uid(): void {
		$a = Index_Job::fingerprint( 'https://search.example.com', 'local_shows' );

		$this->assertSame( $a, Index_Job::fingerprint( 'https://search.example.com', 'local_shows' ) );
		$this->assertNotSame( $a, Index_Job::fingerprint( 'https://search.example.com', 'staging_shows' ) );
		$this->assertNotSame( $a, Index_Job::fingerprint( 'https://other.example.com', 'local_shows' ) );
	}
}
