<?php
/**
 * Sheet Triage Task Handler
 *
 * Runs Triage_Runner for one Gravity Forms entry on Action Scheduler, retrying
 * Google/Gemini hiccups with exponential backoff. Never lets an exception
 * escape: a failure ends up as an error note on the entry.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Submission_Triage;
use LWTV\Plugins\Gravity_Forms\Transient_Error;
use LWTV\Plugins\Gravity_Forms\Triage_Runner;

/**
 * Class Sheet_Triage_Task
 */
class Sheet_Triage_Task {

	/**
	 * Action Scheduler hook name. Args: array( $entry_id, $attempt ).
	 */
	const AS_HOOK = 'lwtv_sheet_triage_task';

	/**
	 * Action Scheduler group name.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Attempts in all, including the first.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::AS_HOOK, array( $this, 'run' ), 10, 2 );
	}

	/**
	 * Action Scheduler handler.
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $attempt  0-based attempt number.
	 * @return void
	 */
	public function run( $entry_id, $attempt = 0 ): void {
		$entry_id = (int) $entry_id;
		$attempt  = max( 0, (int) $attempt );

		if ( $entry_id <= 0 ) {
			return;
		}

		try {
			// Held after queueing (marked spam meanwhile): wait for the release.
			if ( '' !== (string) gform_get_meta( $entry_id, Submission_Triage::META_HELD ) ) {
				Submission_Triage::debug( 'Entry ' . $entry_id . ' is held; skipping its queued sheet triage.' );
				return;
			}

			( new Triage_Runner() )->run( $entry_id, $attempt >= self::MAX_ATTEMPTS - 1 );
		} catch ( Transient_Error $e ) {
			if ( $attempt < self::MAX_ATTEMPTS - 1 && $this->reschedule( $entry_id, $attempt ) ) {
				Submission_Triage::debug( 'Sheet triage for entry ' . $entry_id . ' failed (attempt ' . ( $attempt + 1 ) . '), retrying: ' . $e->getMessage() );
				return;
			}
			$this->fail( $entry_id, $e->getMessage() );
		} catch ( \Throwable $e ) {
			// \RuntimeException (configuration, missing header, bad entry) and anything unexpected.
			$this->fail( $entry_id, $e->getMessage() );
		}
	}

	/**
	 * Schedule the next attempt.
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $attempt  Attempt that just failed.
	 * @return bool Whether it was scheduled.
	 */
	private function reschedule( int $entry_id, int $attempt ): bool {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		$delay = min( 3600, 60 * 2 ** $attempt );
		return (bool) as_schedule_single_action( time() + $delay, self::AS_HOOK, array( $entry_id, $attempt + 1 ), self::AS_GROUP );
	}

	/**
	 * Record a failure that will not be retried.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $message  Error message.
	 * @return void
	 */
	private function fail( int $entry_id, string $message ): void {
		Submission_Triage::error( 'Sheet triage failed for entry ' . $entry_id . ': ' . $message );

		try {
			Submission_Triage::note( $entry_id, 'Sheet triage failed: ' . $message, 'error' );
		} catch ( \Throwable $e ) {
			Submission_Triage::error( 'Could not add the failure note to entry ' . $entry_id . ': ' . $e->getMessage() );
		}
	}
}
