<?php
/**
 * WP CLI Commands for LezWatch.TV Scheduler
 *
 * These commands manage Action Scheduler operations and background tasks.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Features\Missed_Schedule;

/**
 * LezWatch.TV scheduler commands to manage background tasks.
 */
class WP_CLI_LWTV_Scheduler {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * Construct to block facet from munging results.
	 */
	public function __construct() {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter( 'fwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * Manage scheduler operations
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of scheduler operation to perform.
	 * ---
	 * options:
	 *   - missed
	 *   - tmdb
	 *   - status
	 * ---
	 *
	 * [<action>]
	 * : Optional. Action to perform. missed uses [status|trigger], tmdb uses [status|trigger].
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check missed schedule status
	 *     $ wp lwtv scheduler missed status
	 *     Success: Missed schedule status retrieved.
	 *
	 *     # Trigger missed schedule check
	 *     $ wp lwtv scheduler missed trigger
	 *     Success: Missed schedule check triggered.
	 *
	 *     # Check TMDB batch status
	 *     $ wp lwtv scheduler tmdb status
	 *     Success: TMDB batch status retrieved.
	 *
	 *     # Trigger TMDB batch processing
	 *     $ wp lwtv scheduler tmdb trigger
	 *     Success: TMDB batch processing triggered.
	 *
	 *     # Check overall scheduler status
	 *     $ wp lwtv scheduler status
	 *     Success: Scheduler status retrieved.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {

		$this->format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$type   = isset( $args[0] ) ? $args[0] : 'status';
		$action = ( isset( $args[1] ) ) ? $args[1] : null;

		try {
			$this->run_scheduler_command( $type, $action );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Run scheduler command
	 *
	 * @param string $type   Type of scheduler operation
	 * @param string $action Action to perform
	 * @return void
	 */
	private function run_scheduler_command( string $type, ?string $action ): void {
		switch ( $type ) {
			case 'missed':
				$this->run_missed_schedule( $action );
				break;
			case 'tmdb':
				$this->run_tmdb_batch( $action );
				break;
			case 'status':
				$this->run_overall_status();
				break;
			default:
				\WP_CLI::error( 'Invalid scheduler type. Use: missed, tmdb, or status' );
				break;
		}
	}

	/**
	 * Run missed schedule commands
	 *
	 * @param string|null $action The action to perform (status|trigger)
	 * @return void
	 */
	private function run_missed_schedule( ?string $action ): void {
		$missed_schedule = new Missed_Schedule();

		switch ( $action ) {
			case 'status':
				$status = $missed_schedule->get_scheduler_status();
				\WP_CLI::log( 'Missed Schedule Status:' );
				\WP_CLI::log( '  Action Scheduler Available: ' . ( $status['action_scheduler_available'] ? 'Yes' : 'No' ) );
				\WP_CLI::log( '  Current Method: ' . $status['current_method'] );

				if ( $status['action_scheduler_available'] && $status['next_scheduled_check'] ) {
					\WP_CLI::log( '  Next Scheduled Check: ' . gmdate( 'Y-m-d H:i:s', $status['next_scheduled_check'] ) );
				} else {
					\WP_CLI::log( '  Next Scheduled Check: Not scheduled' );
				}

				\WP_CLI::success( 'Missed schedule status retrieved.' );
				break;

			case 'trigger':
				\WP_CLI::log( 'Triggering missed schedule check...' );
				$result = $missed_schedule->trigger_check();
				\WP_CLI::log( $result );
				\WP_CLI::success( 'Missed schedule check triggered.' );
				break;

			default:
				\WP_CLI::log( 'Running missed schedule check...' );
				$result = $missed_schedule->missed_schedule();
				\WP_CLI::log( $result );
				\WP_CLI::success( 'Missed schedule check completed.' );
				break;
		}
	}

	/**
	 * Run TMDB batch commands
	 *
	 * @param string|null $action The action to perform (status|trigger)
	 * @return void
	 */
	private function run_tmdb_batch( ?string $action ): void {
		switch ( $action ) {
			case 'status':
				$status = lwtv_plugin()->get_tmdb_batch_status();
				\WP_CLI::log( 'TMDB Batch Status:' );
				\WP_CLI::log( '  Queued Posts: ' . $status['queued_posts_count'] );

				if ( $status['next_scheduled'] ) {
					\WP_CLI::log( '  Next Scheduled: ' . gmdate( 'Y-m-d H:i:s', $status['next_scheduled'] ) );
				} else {
					\WP_CLI::log( '  Next Scheduled: Not scheduled' );
				}

				\WP_CLI::log( '  Current Request Count: ' . $status['current_request_count'] . '/' . \LWTV\Schedulers\TMDB_Batch_Task::RATE_LIMIT_REQUESTS );
				\WP_CLI::log( '  Rate Limit Hits: ' . $status['rate_limit_hits'] );

				\WP_CLI::success( 'TMDB batch status retrieved.' );
				break;

			case 'trigger':
				\WP_CLI::log( 'Triggering TMDB batch processing...' );
				// Schedule immediate processing
				as_schedule_single_action( time(), \LWTV\Schedulers\TMDB_Batch_Task::AS_HOOK );
				\WP_CLI::success( 'TMDB batch processing triggered.' );
				break;

			default:
				\WP_CLI::log( 'TMDB batch processing is handled automatically via Action Scheduler.' );
				\WP_CLI::log( 'Use "wp lwtv scheduler tmdb status" to check current status.' );
				\WP_CLI::success( 'TMDB batch information displayed.' );
				break;
		}
	}

	/**
	 * Run overall scheduler status
	 *
	 * @return void
	 */
	private function run_overall_status(): void {
		$scheduler_status = lwtv_plugin()->get_scheduler_status();
		$missed_schedule  = new Missed_Schedule();
		$missed_status    = $missed_schedule->get_scheduler_status();
		$tmdb_status      = lwtv_plugin()->get_tmdb_batch_status();

		\WP_CLI::log( 'LezWatch.TV Scheduler Status:' );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'General Scheduler:' );
		\WP_CLI::log( '  Action Scheduler Available: ' . ( $scheduler_status['action_scheduler_available'] ? 'Yes' : 'No' ) );
		\WP_CLI::log( '  WordPress Cron Enabled: ' . ( $scheduler_status['wordpress_cron_enabled'] ? 'Yes' : 'No' ) );
		\WP_CLI::log( '  Current Scheduler: ' . $scheduler_status['current_scheduler'] );
		\WP_CLI::log( '' );

		\WP_CLI::log( 'Missed Schedule:' );
		\WP_CLI::log( '  Method: ' . $missed_status['current_method'] );
		if ( $missed_status['action_scheduler_available'] && $missed_status['next_scheduled_check'] ) {
			\WP_CLI::log( '  Next Check: ' . gmdate( 'Y-m-d H:i:s', $missed_status['next_scheduled_check'] ) );
		} else {
			\WP_CLI::log( '  Next Check: Not scheduled' );
		}
		\WP_CLI::log( '' );

		\WP_CLI::log( 'TMDB Batch Processing:' );
		\WP_CLI::log( '  Queued Posts: ' . $tmdb_status['queued_posts_count'] );
		if ( $tmdb_status['next_scheduled'] ) {
			\WP_CLI::log( '  Next Processing: ' . gmdate( 'Y-m-d H:i:s', $tmdb_status['next_scheduled'] ) );
		} else {
			\WP_CLI::log( '  Next Processing: Not scheduled' );
		}
		\WP_CLI::log( '  API Usage: ' . $tmdb_status['current_request_count'] . '/' . \LWTV\Schedulers\TMDB_Batch_Task::RATE_LIMIT_REQUESTS );
		\WP_CLI::log( '  Rate Limit Hits: ' . $tmdb_status['rate_limit_hits'] );

		\WP_CLI::success( 'Scheduler status retrieved.' );
	}
}

\WP_CLI::add_command( 'lwtv scheduler', 'WP_CLI_LWTV_Scheduler' );
