<?php
/**
 * Name: Index Job
 * Description: The state machine for Search Settings' background index jobs.
 *
 * Pure. Three kinds, one job per index at a time:
 *
 * - apply:   settings → wait → done
 * - setup:   check → create (→ wait for create) → settings → batch… → prune → wait → done
 * - reindex: batch… → prune → wait → done
 *
 * Batches page by post ID (last_id), not by offset, so a post trashed or
 * published while a job runs can't shift a page and skip its neighbour.
 *
 * The runner does one unit of work per step and reports an event; advance()
 * says what comes next. A job that isn't running ignores every event, so a
 * cancelled job never moves again.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Index_Job {

	const APPLY   = 'apply';
	const SETUP   = 'setup';
	const REINDEX = 'reindex';
	const KINDS   = array( self::APPLY, self::SETUP, self::REINDEX );

	const RUNNING   = 'running';
	const DONE      = 'done';
	const FAILED    = 'failed';
	const CANCELLED = 'cancelled';

	const S_CHECK    = 'check';
	const S_CREATE   = 'create';
	const S_SETTINGS = 'settings';
	const S_BATCH    = 'batch';
	const S_PRUNE    = 'prune';
	const S_WAIT     = 'wait';

	const GONE = 'The index no longer exists.';

	/**
	 * Why a job can't start, or null when it can.
	 *
	 * @param array|null $current     The index's current or last job.
	 * @param string     $kind        Kind to start.
	 * @param string     $index_state An Index_State constant.
	 * @return string|null
	 */
	public static function can_start( ?array $current, string $kind, string $index_state ): ?string {
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return 'Unknown job.';
		}
		if ( self::is_running( $current ) ) {
			return sprintf( 'A %s job is already running on this index.', (string) $current['kind'] );
		}
		if ( Index_State::UNKNOWN === $index_state ) {
			return 'Could not reach the search server to check this index.';
		}
		if ( self::SETUP === $kind ) {
			return Index_State::MISSING === $index_state ? null : 'This index is already set up.';
		}

		return Index_State::EXISTS === $index_state ? null : 'This index isn\'t set up here. Use Set up.';
	}

	/**
	 * A new running job at its first step.
	 *
	 * @param string $kind  Kind.
	 * @param int    $total Posts expected (0 for apply).
	 * @param int    $now   Unix time.
	 * @return array
	 */
	public static function start( string $kind, int $total, int $now ): array {
		$first = array(
			self::APPLY   => self::S_SETTINGS,
			self::SETUP   => self::S_CHECK,
			self::REINDEX => self::S_BATCH,
		);

		return array(
			'kind'     => $kind,
			'state'    => self::RUNNING,
			'step'     => $first[ $kind ] ?? self::S_CHECK,
			'page'     => self::REINDEX === $kind ? 1 : 0,
			// Keyset cursor: the highest post ID read so far, so a post
			// trashed mid-job can't shift the next page and skip one.
			'last_id'  => 0,
			'sent'     => 0,
			'total'    => $total,
			'task'     => null,
			'message'  => '',
			'started'  => $now,
			'finished' => 0,
		);
	}

	/**
	 * Whether a job is running.
	 *
	 * @param array|null $job Job.
	 * @return bool
	 */
	public static function is_running( ?array $job ): bool {
		return is_array( $job ) && self::RUNNING === ( $job['state'] ?? '' );
	}

	/**
	 * The job after an event.
	 *
	 * @param array  $job   Job.
	 * @param string $event exists, missing, created, task_ok, settings_sent, batch_sent, pruned, failed, cancel.
	 * @param array  $data  task, sent, last, message, as the event needs.
	 * @param int    $now   Unix time.
	 * @return array
	 */
	public static function advance( array $job, string $event, array $data, int $now ): array {
		if ( ! self::is_running( $job ) ) {
			return $job;
		}

		$step = (string) ( $job['step'] ?? '' );

		switch ( $event ) {
			case 'cancel':
				return self::finish( $job, self::CANCELLED, (string) ( $job['message'] ?? '' ), $now );

			case 'failed':
				return self::finish( $job, self::FAILED, (string) ( $data['message'] ?? 'The job failed.' ), $now );

			case 'missing':
				if ( self::SETUP === $job['kind'] && self::S_CHECK === $step ) {
					return array_merge(
						$job,
						array(
							'step' => self::S_CREATE,
							'task' => null,
						)
					);
				}
				return self::finish( $job, self::FAILED, self::GONE, $now );

			case 'exists':
				if ( self::SETUP === $job['kind'] && in_array( $step, array( self::S_CHECK, self::S_CREATE ), true ) ) {
					return array_merge(
						$job,
						array(
							'step' => self::S_SETTINGS,
							'task' => null,
						)
					);
				}
				return $job;

			case 'created':
				return array_merge(
					$job,
					array(
						'step' => self::S_CREATE,
						'task' => self::task( $data ),
					)
				);

			case 'task_ok':
				if ( self::S_CREATE === $step ) {
					return array_merge(
						$job,
						array(
							'step' => self::S_SETTINGS,
							'task' => null,
						)
					);
				}
				if ( self::S_WAIT === $step ) {
					return self::finish( $job, self::DONE, '', $now );
				}
				return $job;

			case 'settings_sent':
				if ( self::APPLY === $job['kind'] ) {
					return array_merge(
						$job,
						array(
							'step' => self::S_WAIT,
							'task' => self::task( $data ),
						)
					);
				}
				return array_merge(
					$job,
					array(
						'step'    => self::S_BATCH,
						'page'    => 1,
						'last_id' => 0,
						'task'    => self::task( $data ),
					)
				);

			case 'batch_sent':
				$sent    = (int) $job['sent'] + (int) ( $data['sent'] ?? 0 );
				$last_id = isset( $data['last_id'] ) ? (int) $data['last_id'] : (int) ( $job['last_id'] ?? 0 );
				if ( ! empty( $data['last'] ) ) {
					return array_merge(
						$job,
						array(
							'step'    => self::S_PRUNE,
							'sent'    => $sent,
							'last_id' => $last_id,
						)
					);
				}
				return array_merge(
					$job,
					array(
						'page'    => (int) $job['page'] + 1,
						'sent'    => $sent,
						'last_id' => $last_id,
					)
				);

			case 'pruned':
				return array_merge(
					$job,
					array(
						'step' => self::S_WAIT,
						'task' => self::task( $data ),
					)
				);
		}

		return $job;
	}

	/**
	 * A finished job.
	 *
	 * @param array  $job     Job.
	 * @param string $state   DONE, FAILED or CANCELLED.
	 * @param string $message Message.
	 * @param int    $now     Unix time.
	 * @return array
	 */
	private static function finish( array $job, string $state, string $message, int $now ): array {
		return array_merge(
			$job,
			array(
				'state'    => $state,
				'message'  => $message,
				'finished' => $now,
			)
		);
	}

	/**
	 * A task uid from event data.
	 *
	 * @param array $data Event data.
	 * @return int|null
	 */
	private static function task( array $data ): ?int {
		return is_numeric( $data['task'] ?? null ) ? (int) $data['task'] : null;
	}
}
