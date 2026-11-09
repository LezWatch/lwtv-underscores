<?php
/**
 * WP CLI Commands for the Content to Add sheet triage.
 *
 * Queue or run the Gravity Forms "new show/actor/character" triage for one entry.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Plugins\Gravity_Forms\Submission_Triage;
use LWTV\Plugins\Gravity_Forms\Triage_Runner;

/**
 * LezWatch.TV Content to Add sheet triage commands.
 */
class WP_CLI_LWTV_Triage {

	/**
	 * Queue or run the Content to Add sheet triage for a contact form entry.
	 *
	 * ## OPTIONS
	 *
	 * <entry_id>
	 * : The Gravity Forms entry ID.
	 *
	 * [--now]
	 * : Run it right now and print what happened, instead of queueing it. This runs as the final attempt: if Gemini fails, it does not show an error but writes the raw-text fallback row to the real sheet and marks the entry done. Only Sheets, configuration and header errors are shown as errors.
	 *
	 * [--force]
	 * : Clear the "done" and "held" markers first, so a finished or held entry runs again. Rows this entry already wrote are not duplicated (the Form Entry column prevents that), but if the first run wrote a fallback row and Gemini now succeeds, the named rows are added beside it.
	 *
	 * ## EXAMPLES
	 *
	 *     # Queue an entry for the sheet
	 *     $ wp lwtv triage 4521
	 *
	 *     # Release a held entry and run it now
	 *     $ wp lwtv triage 4521 --force --now
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args ) {
		$entry_id = absint( $args[0] ?? 0 );
		$now      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'now', false );
		$force    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		if ( $entry_id <= 0 ) {
			\WP_CLI::error( 'Please give a valid entry ID.' );
		}

		if ( ! class_exists( '\GFAPI' ) ) {
			\WP_CLI::error( 'Gravity Forms is not active.' );
		}

		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			\WP_CLI::error( 'Could not load entry: ' . $entry->get_error_message() );
		}
		if ( ! is_array( $entry ) ) {
			\WP_CLI::error( 'Entry not found.' );
		}

		if ( ! Submission_Triage::is_triage_entry( $entry ) ) {
			\WP_CLI::error( 'Entry ' . $entry_id . ' is not a contact form entry about a new show, actor or character.' );
		}

		if ( ! Submission_Triage::is_configured() ) {
			\WP_CLI::error( 'Sheet triage is not configured. Missing: ' . implode( ', ', $this->missing_constants() ) . '.' );
		}

		if ( $force ) {
			gform_delete_meta( $entry_id, Submission_Triage::META_DONE );
			gform_delete_meta( $entry_id, Submission_Triage::META_HELD );
			\WP_CLI::log( 'Cleared the done and held markers.' );
		} elseif ( gform_get_meta( $entry_id, Submission_Triage::META_DONE ) ) {
			\WP_CLI::error( 'Entry ' . $entry_id . ' is already on the sheet. Use --force to run it again.' );
		} else {
			$held = (string) gform_get_meta( $entry_id, Submission_Triage::META_HELD );
			if ( '' !== $held ) {
				\WP_CLI::error( 'Entry ' . $entry_id . ' is held (' . $held . '). Use --force to release it.' );
			}
		}

		if ( ! $now ) {
			if ( ! Submission_Triage::queue( $entry_id ) ) {
				\WP_CLI::error( 'Could not queue entry ' . $entry_id . '. See the entry notes and the submissions debug log.' );
			}
			\WP_CLI::success( 'Queued entry ' . $entry_id . '. Action Scheduler (group lwtv) will run it shortly.' );
			return;
		}

		try {
			$lines = ( new Triage_Runner() )->run( $entry_id, true );

			foreach ( $lines as $line ) {
				\WP_CLI::line( $line );
			}
		} catch ( \Throwable $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		\WP_CLI::success( 'Finished entry ' . $entry_id . '.' );
	}

	/**
	 * Which wp-config constants are missing or unusable? Names only, never values.
	 *
	 * @return array<int,string>
	 */
	private function missing_constants(): array {
		$missing = array();

		if ( ! defined( 'LWTV_GEMINI_API_KEY' ) || ! is_string( LWTV_GEMINI_API_KEY ) || '' === LWTV_GEMINI_API_KEY ) {
			$missing[] = 'LWTV_GEMINI_API_KEY';
		}
		if ( ! defined( 'LWTV_GOOGLE_SERVICE_ACCOUNT' ) || ! is_string( LWTV_GOOGLE_SERVICE_ACCOUNT ) || '' === LWTV_GOOGLE_SERVICE_ACCOUNT ) {
			$missing[] = 'LWTV_GOOGLE_SERVICE_ACCOUNT';
		} elseif ( ! is_readable( LWTV_GOOGLE_SERVICE_ACCOUNT ) ) {
			$missing[] = 'LWTV_GOOGLE_SERVICE_ACCOUNT (the key file is not readable)';
		}

		return $missing;
	}
}

\WP_CLI::add_command( 'lwtv triage', 'WP_CLI_LWTV_Triage' );
