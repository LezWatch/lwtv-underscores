<?php
/**
 * Name: Index Jobs
 * Description: Background Apply settings, Set up and Reindex jobs for Search Settings.
 *
 * One job per index at a time, stored in `lwtv_meili_jobs` (not autoloaded)
 * as { <type>: job } (see Build\Index_Job for the shape and steps). Set up
 * and Reindex run in Action Scheduler, one step per action
 * (`lwtv_meili_job_task`, args [ type ], group `lwtv`), so no request runs
 * long and nothing waits on a task. Apply settings sends its one request
 * right away and finishes when refresh() sees its task succeed.
 *
 * Every step re-reads the job before it works and before it saves, so a
 * job cancelled meanwhile is never moved on or rescheduled. Batches page by
 * post ID; the job keeps the IDs it built and read, and its own task uids,
 * in `lwtv_meili_job_ids_<type>`, so the prune spares posts published mid-job
 * and "done" waits until none of its own tasks failed. A running Set up or
 * Reindex with no step queued or running (a fatal) is marked failed by
 * refresh(). The CLI checks
 * running() and refuses to touch an index a job holds.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_Job;
use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Job_Checks;

class Index_Jobs {

	const OPTION     = 'lwtv_meili_jobs';
	const IDS_OPTION = 'lwtv_meili_job_ids_';
	const HOOK       = 'lwtv_meili_job_task';
	const GROUP      = 'lwtv';

	/**
	 * Seconds a just-started Set up or Reindex may have no step queued before
	 * it counts as stopped (start() writes the job, then queues its step).
	 */
	const STALE_AFTER = 30;

	private Indexes $indexes;
	private Client $client;
	private Reindexer $reindexer;

	/**
	 * @param Indexes   $indexes   Every index.
	 * @param Client    $client    Meilisearch client.
	 * @param Reindexer $reindexer Shared reindex steps.
	 */
	public function __construct( Indexes $indexes, Client $client, Reindexer $reindexer ) {
		$this->indexes   = $indexes;
		$this->client    = $client;
		$this->reindexer = $reindexer;
	}

	/**
	 * Register the step handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'step' ) );
	}

	/**
	 * The index a job type runs on. The filter is for live checks only: it can
	 * swap in a stand-in Index of the same type (a throwaway uid).
	 *
	 * @param string $type Index type.
	 * @return Index|null
	 */
	public function index( string $type ): ?Index {
		if ( ! in_array( $type, $this->indexes->types(), true ) ) {
			return null;
		}

		$index = apply_filters( 'lwtv_meili_job_index', $this->indexes->get( $type ), $type );

		return ( $index instanceof Index && $index->type() === $type ) ? $index : null;
	}

	/**
	 * Every index's current or last job, past this request's cache.
	 *
	 * @return array<string, array>
	 */
	public function all(): array {
		wp_cache_delete( self::OPTION, 'options' );
		$jobs = get_option( self::OPTION, array() );

		return is_array( $jobs ) ? array_filter( $jobs, 'is_array' ) : array();
	}

	/**
	 * One index's current or last job.
	 *
	 * @param string $type Index type.
	 * @return array|null
	 */
	public function get( string $type ): ?array {
		return $this->all()[ $type ] ?? null;
	}

	/**
	 * The job running on an index, if any.
	 *
	 * @param string $type Index type.
	 * @return array|null
	 */
	public function running( string $type ): ?array {
		$job = $this->get( $type );

		return Index_Job::is_running( $job ) ? $job : null;
	}

	/**
	 * Whether Set up and Reindex can run (they need Action Scheduler).
	 *
	 * @return bool
	 */
	public function can_schedule(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Start a job.
	 *
	 * @param string $type Index type.
	 * @param string $kind apply, setup or reindex.
	 * @return array|\WP_Error The job.
	 */
	public function start( string $type, string $kind ) {
		$index = $this->index( $type );
		if ( null === $index ) {
			return new \WP_Error( 'lwtv_meili_job_type', 'Unknown index.', array( 'status' => 400 ) );
		}
		if ( Index_Job::APPLY !== $kind && in_array( $kind, Index_Job::KINDS, true ) && ! $this->can_schedule() ) {
			return new \WP_Error( 'lwtv_meili_job_refused', 'Action Scheduler isn\'t available; use `wp lwtv meili reindex`.', array( 'status' => 409 ) );
		}

		$index->forget_state();
		$refused = Index_Job::can_start( $this->get( $type ), $kind, $index->state() );
		if ( null !== $refused ) {
			return new \WP_Error( 'lwtv_meili_job_refused', $refused, array( 'status' => 409 ) );
		}

		$total = Index_Job::APPLY === $kind ? 0 : $this->reindexer->total( $index );
		delete_option( self::IDS_OPTION . $type );
		$this->write( $type, Index_Job::start( $kind, $total, time() ) );

		if ( Index_Job::APPLY === $kind ) {
			$this->step( $type );
		} else {
			$this->schedule( $type, 0 );
			$this->nudge();
		}

		return $this->get( $type ) ?? array();
	}

	/**
	 * Cancel the running job on an index.
	 *
	 * @param string $type Index type.
	 * @return array|\WP_Error The cancelled job.
	 */
	public function cancel( string $type ) {
		$job = $this->running( $type );
		if ( null === $job ) {
			return new \WP_Error( 'lwtv_meili_job_none', 'No job is running on this index.', array( 'status' => 404 ) );
		}

		$job = Index_Job::advance( $job, 'cancel', array(), time() );
		$this->write( $type, $job );
		delete_option( self::IDS_OPTION . $type );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( $type ), self::GROUP );
		}

		return $job;
	}

	/**
	 * Check a task the job is waiting on, once. Never blocks.
	 *
	 * @param string $type Index type.
	 * @return array|null The job.
	 */
	public function refresh( string $type ): ?array {
		$job = $this->running( $type );
		if ( null === $job ) {
			return $this->get( $type );
		}

		// Set up and Reindex are driven by their Action Scheduler steps; here
		// we only notice one that stopped (a fatal, a failed action) with
		// nothing left queued, so it can't sit "running" and lock the CLI.
		if ( Index_Job::APPLY !== $job['kind'] ) {
			if ( time() - (int) $job['started'] > self::STALE_AFTER && 0 === $this->count_steps( $type, true ) ) {
				$next = Index_Job::advance( $job, 'failed', array( 'message' => 'The background step stopped before the job finished (see Tools → Scheduled Actions). Start it again.' ), time() );
				$this->save( $type, $job, $next );
			}
			return $this->get( $type );
		}

		// Apply sends one request and finishes when its task does.
		if ( Index_Job::S_WAIT !== $job['step'] || null === $job['task'] ) {
			return $job;
		}
		$event = $this->task_event( (int) $job['task'] );
		if ( null !== $event ) {
			$this->save( $type, $job, Index_Job::advance( $job, $event[0], $event[1], time() ) );
		}

		return $this->get( $type );
	}

	/**
	 * Run one step of an index's job (the Action Scheduler handler).
	 *
	 * @param string $type Index type.
	 * @return void
	 */
	public function step( $type ): void {
		$type = (string) $type;
		$job  = $this->running( $type );
		if ( null === $job ) {
			return;
		}

		$index = $this->index( $type );
		$event = null === $index ? array( 'failed', array( 'message' => 'Unknown index.' ) ) : $this->work( $index, $job );

		if ( null === $event ) {
			// Still waiting on a task: look again shortly.
			$this->schedule( $type, 2 );
			return;
		}

		$next = Index_Job::advance( $job, $event[0], $event[1], time() );
		if ( ! $this->save( $type, $job, $next ) ) {
			return;
		}

		if ( ! Index_Job::is_running( $next ) ) {
			return;
		}

		// Apply waits for its task through refresh(); everything else moves on here.
		if ( Index_Job::APPLY !== $next['kind'] ) {
			$this->schedule( $type, Index_Job::S_WAIT === $next['step'] ? 2 : 0 );
		}
	}

	/**
	 * Do the job's current step once and say what happened.
	 *
	 * @param Index $index Index.
	 * @param array $job   Running job.
	 * @return array{0: string, 1: array}|null The event, or null to try again later.
	 */
	private function work( Index $index, array $job ): ?array {
		switch ( $job['step'] ) {
			case Index_Job::S_CHECK:
				return $this->state_event( $index );

			case Index_Job::S_CREATE:
				if ( null !== $job['task'] ) {
					return $this->task_event( (int) $job['task'] );
				}
				$created = $this->client->create_index( $index->uid(), Index_Settings::PRIMARY_KEY );
				$index->forget_state();
				if ( is_wp_error( $created ) ) {
					return false !== strpos( $created->get_error_message(), 'index_already_exists' ) ? array( 'exists', array() ) : self::failed( $created->get_error_message() );
				}
				return isset( $created['taskUid'] ) ? array( 'created', array( 'task' => (int) $created['taskUid'] ) ) : self::failed( 'The search server did not return a task.' );

			case Index_Job::S_SETTINGS:
				$state = $this->state_event( $index );
				if ( 'exists' !== $state[0] ) {
					return $state;
				}
				try {
					$payload = $this->reindexer->settings_payload( $index );
				} catch ( \InvalidArgumentException $e ) {
					return self::failed( $e->getMessage() );
				}
				$sent = $this->client->update_settings( $index->uid(), $payload, Client::SHORT_TIMEOUT );
				if ( is_wp_error( $sent ) ) {
					return self::failed( $sent->get_error_message() );
				}
				if ( ! isset( $sent['taskUid'] ) ) {
					return self::failed( 'The search server did not return a task.' );
				}
				$this->remember( $index->type(), array(), array(), array( (int) $sent['taskUid'] ) );
				return array( 'settings_sent', array( 'task' => (int) $sent['taskUid'] ) );

			case Index_Job::S_BATCH:
				$state = $this->state_event( $index );
				if ( 'exists' !== $state[0] ) {
					return $state;
				}
				$ids  = $this->reindexer->page_ids_after( $index, (int) ( $job['last_id'] ?? 0 ) );
				$page = $this->reindexer->send_page( $index, $ids );
				if ( is_wp_error( $page['result'] ) ) {
					return self::failed( $page['result']->get_error_message() );
				}
				$task = is_array( $page['result'] ) && isset( $page['result']['taskUid'] ) ? array( (int) $page['result']['taskUid'] ) : array();
				$this->remember( $index->type(), $page['built'], $ids, $task );
				return array(
					'batch_sent',
					array(
						'sent'    => count( $page['built'] ),
						'last'    => count( $ids ) < Reindexer::BATCH,
						'last_id' => empty( $ids ) ? (int) ( $job['last_id'] ?? 0 ) : max( $ids ),
					),
				);

			case Index_Job::S_PRUNE:
				$memo   = $this->remembered( $index->type() );
				$keep   = Job_Checks::keep( $memo['built'], $memo['seen'], $this->reindexer->published_ids( $index ) );
				$pruned = $this->reindexer->prune( $index, $keep );
				if ( null === $pruned ) {
					return self::failed( 'No published posts were found, so nothing was pruned.' );
				}
				if ( is_wp_error( $pruned ) ) {
					return self::failed( $pruned->get_error_message() );
				}
				return isset( $pruned['taskUid'] ) ? array( 'pruned', array( 'task' => (int) $pruned['taskUid'] ) ) : self::failed( 'The search server did not return a task.' );

			case Index_Job::S_WAIT:
				$event = null === $job['task'] ? array( 'task_ok', array() ) : $this->task_event( (int) $job['task'] );
				if ( null === $event || 'task_ok' !== $event[0] || Index_Job::APPLY === $job['kind'] ) {
					return $event;
				}
				return $this->tasks_event( $this->remembered( $index->type() )['tasks'] );
		}

		return self::failed( 'Unknown step.' );
	}

	/**
	 * The event for an index's state, checked afresh.
	 *
	 * @param Index $index Index.
	 * @return array{0: string, 1: array}
	 */
	private function state_event( Index $index ): array {
		$index->forget_state();
		$state = $index->state();

		if ( Index_State::EXISTS === $state ) {
			return array( 'exists', array() );
		}
		if ( Index_State::MISSING === $state ) {
			return array( 'missing', array() );
		}

		return self::failed( 'Could not reach the search server to check this index.' );
	}

	/**
	 * Whether any of the job's own document and settings tasks failed: the
	 * prune running last doesn't mean the batches before it were accepted.
	 *
	 * @param int[] $tasks Task uids.
	 * @return array{0: string, 1: array}|null task_ok, failed, or null to look again later.
	 */
	private function tasks_event( array $tasks ): ?array {
		if ( empty( $tasks ) ) {
			return array( 'task_ok', array() );
		}

		$failed = $this->client->list_tasks(
			array(
				'uids'     => implode( ',', array_map( 'intval', $tasks ) ),
				'statuses' => 'failed,canceled',
				'limit'    => 1,
			)
		);
		if ( is_wp_error( $failed ) ) {
			return null;
		}

		$message = Job_Checks::first_failure( $failed );

		return null === $message ? array( 'task_ok', array() ) : self::failed( $message );
	}

	/**
	 * The event for a task, or null while it's still running.
	 *
	 * @param int $task_uid Task uid.
	 * @return array{0: string, 1: array}|null
	 */
	private function task_event( int $task_uid ): ?array {
		$task = $this->client->get_task( $task_uid );
		if ( is_wp_error( $task ) || (int) ( $task['uid'] ?? -1 ) !== $task_uid ) {
			return null;
		}

		$status = (string) ( $task['status'] ?? '' );
		if ( 'succeeded' === $status ) {
			return array( 'task_ok', array() );
		}
		if ( 'failed' === $status || 'canceled' === $status ) {
			$error = is_array( $task['error'] ?? null ) ? $task['error'] : array();
			$code  = (string) ( $error['code'] ?? $status );
			if ( 'index_already_exists' === $code ) {
				return array( 'exists', array() );
			}
			$message = (string) ( $error['message'] ?? '' );
			return self::failed( '' === $message ? $code : $code . ' — ' . $message );
		}

		return null;
	}

	/**
	 * Save a job only if the stored one is still the job this request read,
	 * so a cancel (or a newer job) made meanwhile wins.
	 *
	 * @param string $type Index type.
	 * @param array  $read The job as read before the step.
	 * @param array  $next The job to store.
	 * @return bool Whether it saved.
	 */
	private function save( string $type, array $read, array $next ): bool {
		$stored = $this->get( $type );
		if ( ! Index_Job::is_running( $stored ) || $stored['started'] !== $read['started'] || $stored['step'] !== $read['step'] || $stored['page'] !== $read['page'] ) {
			return false;
		}

		$this->write( $type, $next );
		if ( ! Index_Job::is_running( $next ) ) {
			delete_option( self::IDS_OPTION . $type );
		}

		return true;
	}

	/**
	 * Store a job.
	 *
	 * @param string $type Index type.
	 * @param array  $job  Job.
	 * @return void
	 */
	private function write( string $type, array $job ): void {
		$jobs          = $this->all();
		$jobs[ $type ] = $job;
		update_option( self::OPTION, $jobs, false );
	}

	/**
	 * Start Action Scheduler's own async runner now, if a job is running.
	 *
	 * Its WP-Cron tick can be minutes away (lwtv.local disables WP-Cron and
	 * production runs it from a 10-minute crontab), and its built-in admin
	 * trigger only fires on full admin page loads. The runner is a
	 * non-blocking loopback that keeps going while actions are due, within
	 * Action Scheduler's concurrency limit, so the work still runs there.
	 *
	 * @return void
	 */
	public function nudge(): void {
		if ( ! class_exists( '\ActionScheduler_AsyncRequest_QueueRunner' ) || ! class_exists( '\ActionScheduler' ) ) {
			return;
		}
		foreach ( $this->all() as $job ) {
			if ( Index_Job::is_running( $job ) ) {
				( new \ActionScheduler_AsyncRequest_QueueRunner( \ActionScheduler::store() ) )->maybe_dispatch();
				return;
			}
		}
	}

	/**
	 * Queue the next step, unless one is already pending.
	 *
	 * Only pending steps count: Action Scheduler's as_has_scheduled_action()
	 * also counts the step running right now (including a cancelled job's
	 * last step), which would stop a step queuing its successor, or a job
	 * restarted right after a cancel from queuing its first step.
	 *
	 * @param string $type  Index type.
	 * @param int    $delay Seconds.
	 * @return void
	 */
	private function schedule( string $type, int $delay ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || $this->count_steps( $type, false ) > 0 ) {
			return;
		}
		as_schedule_single_action( time() + $delay, self::HOOK, array( $type ), self::GROUP );
	}

	/**
	 * How many steps are queued for an index (and, optionally, running now).
	 *
	 * @param string $type    Index type.
	 * @param bool   $running Also count a step that is running.
	 * @return int
	 */
	private function count_steps( string $type, bool $running ): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( '\ActionScheduler_Store' ) ) {
			return 0;
		}

		$statuses = $running ? array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ) : array( \ActionScheduler_Store::STATUS_PENDING );
		$count    = 0;
		foreach ( $statuses as $status ) {
			$found  = as_get_scheduled_actions(
				array(
					'hook'     => self::HOOK,
					'args'     => array( $type ),
					'group'    => self::GROUP,
					'status'   => $status,
					'per_page' => 1,
				),
				'ids'
			);
			$count += is_array( $found ) ? count( $found ) : 0;
		}

		return $count;
	}

	/**
	 * Add to the job's memo: IDs built, IDs read, and its own task uids.
	 *
	 * @param string $type  Index type.
	 * @param int[]  $built IDs built into documents.
	 * @param int[]  $seen  IDs the page returned.
	 * @param int[]  $tasks Task uids.
	 * @return void
	 */
	private function remember( string $type, array $built, array $seen, array $tasks ): void {
		$memo = $this->remembered( $type );
		update_option(
			self::IDS_OPTION . $type,
			array(
				'built' => array_merge( $memo['built'], array_map( 'intval', $built ) ),
				'seen'  => array_merge( $memo['seen'], array_map( 'intval', $seen ) ),
				'tasks' => array_merge( $memo['tasks'], array_map( 'intval', $tasks ) ),
			),
			false
		);
	}

	/**
	 * The job's memo so far.
	 *
	 * @param string $type Index type.
	 * @return array{built: int[], seen: int[], tasks: int[]}
	 */
	private function remembered( string $type ): array {
		wp_cache_delete( self::IDS_OPTION . $type, 'options' );
		$memo = get_option( self::IDS_OPTION . $type, array() );
		$memo = is_array( $memo ) ? $memo : array();

		$out = array();
		foreach ( array( 'built', 'seen', 'tasks' ) as $key ) {
			$out[ $key ] = is_array( $memo[ $key ] ?? null ) ? array_map( 'intval', $memo[ $key ] ) : array();
		}

		return $out;
	}

	/**
	 * A failed event.
	 *
	 * @param string $message Log-safe message.
	 * @return array{0: string, 1: array}
	 */
	private static function failed( string $message ): array {
		return array( 'failed', array( 'message' => $message ) );
	}
}
