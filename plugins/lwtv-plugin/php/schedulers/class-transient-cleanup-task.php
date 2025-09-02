<?php
/**
 * Transient Cleanup Task
 *
 * Handles Action Scheduler-based cleanup of expired transients
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

/**
 * Class Transient_Cleanup_Task
 */
class Transient_Cleanup_Task {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_transient_cleanup_task';

	/**
	 * Transient keys for status tracking
	 */
	const STATUS_TRANSIENT = 'lwtv_transient_cleanup_status';

	/**
	 * Batch size for cleanup operations
	 */
	const BATCH_SIZE = 50;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register Action Scheduler hook
		add_action( self::AS_HOOK, array( $this, 'process_cleanup' ) );

		// Schedule daily cleanup if not already scheduled
		$this->schedule_daily_cleanup();
	}

	/**
	 * Schedule daily cleanup
	 *
	 * @return void
	 */
	private function schedule_daily_cleanup(): void {
		// Only schedule if Action Scheduler is available
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			return;
		}

		if ( ! \as_next_scheduled_action( 'lwtv_daily_transient_cleanup' ) ) {
			\as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'lwtv_daily_transient_cleanup' );
		}

		add_action( 'lwtv_daily_transient_cleanup', array( $this, 'trigger_cleanup' ) );
	}

	/**
	 * Trigger cleanup processing
	 *
	 * @return bool True if cleanup was triggered, false otherwise
	 */
	public function trigger_cleanup(): bool {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->error_log( 'transient-cleanup', 'Action Scheduler not available for transient cleanup' );
			return false;
		}

		// Check if cleanup is already in progress
		if ( $this->is_cleanup_in_progress() ) {
			lwtv_plugin()->error_log( 'transient-cleanup', 'Transient cleanup already in progress' );
			return false;
		}

		// Schedule immediate cleanup
		$scheduled = \as_schedule_single_action( time(), self::AS_HOOK );

		if ( $scheduled ) {
			$this->update_status(
				array(
					'last_triggered' => time(),
					'status'         => 'scheduled',
				)
			);

			lwtv_plugin()->error_log( 'transient-cleanup', 'Transient cleanup scheduled' );
			return true;
		}

		lwtv_plugin()->error_log( 'transient-cleanup', 'Failed to schedule transient cleanup' );
		return false;
	}

	/**
	 * Process cleanup of expired transients
	 *
	 * @return void
	 */
	public function process_cleanup(): void {
		global $wpdb;

		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			lwtv_plugin()->error_log( 'transient-cleanup', 'Action Scheduler not available for transient cleanup' );
			return;
		}

		$this->update_status(
			array(
				'status'     => 'processing',
				'started_at' => time(),
			)
		);

		lwtv_plugin()->error_log( 'transient-cleanup', 'Starting transient cleanup process' );

		$cleaned_count   = 0;
		$total_processed = 0;
		$batch_count     = 0;

		// Process expired transients in batches
		do {
			++$batch_count;

			// Get batch of expired transients
			$expired_transients = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name FROM $wpdb->options
					WHERE option_name LIKE %s
					AND option_value < %d
					LIMIT %d",
					$wpdb->esc_like( '_transient_timeout_' ) . '%',
					time(),
					self::BATCH_SIZE
				)
			);

			$batch_size = count( $expired_transients );

			if ( empty( $expired_transients ) ) {
				break;
			}

			$batch_cleaned = 0;

			foreach ( $expired_transients as $transient ) {
				$transient_name = str_replace( '_transient_timeout_', '', $transient->option_name );

				// Delete the transient and its timeout
				$deleted = delete_transient( $transient_name );

				if ( $deleted ) {
					++$batch_cleaned;
					++$cleaned_count;
				}

				++$total_processed;
			}

			// Update status after each batch
			$this->update_status(
				array(
					'batches_processed'  => $batch_count,
					'total_processed'    => $total_processed,
					'cleaned_count'      => $cleaned_count,
					'last_batch_size'    => $batch_size,
					'last_batch_cleaned' => $batch_cleaned,
				)
			);

			lwtv_plugin()->error_log(
				'transient-cleanup',
				sprintf(
					'Processed batch %d: %d/%d transients cleaned',
					$batch_count,
					$batch_cleaned,
					$batch_size
				)
			);

			// Add delay between batches to prevent overwhelming the database
			if ( ! empty( $expired_transients ) ) {
				sleep( 1 );
			}
		} while ( self::BATCH_SIZE === $batch_size );

		// Final status update
		$this->update_status(
			array(
				'status'            => 'completed',
				'completed_at'      => time(),
				'batches_processed' => $batch_count,
				'total_processed'   => $total_processed,
				'cleaned_count'     => $cleaned_count,
			)
		);

		lwtv_plugin()->error_log(
			'transient-cleanup',
			sprintf(
				'Transient cleanup completed: %d transients cleaned in %d batches',
				$cleaned_count,
				$batch_count
			)
		);

		// Schedule next cleanup if there are still expired transients
		$remaining_expired = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->options
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		if ( $remaining_expired > 0 ) {
			\as_schedule_single_action( time() + 300, self::AS_HOOK ); // 5 minute delay
			lwtv_plugin()->error_log(
				'transient-cleanup',
				sprintf(
					'Scheduled next cleanup in 5 minutes - %d expired transients remaining',
					$remaining_expired
				)
			);
		}
	}

	/**
	 * Check if cleanup is in progress
	 *
	 * @return bool True if cleanup is in progress, false otherwise
	 */
	private function is_cleanup_in_progress(): bool {
		$status = lwtv_plugin()->get_transient( self::STATUS_TRANSIENT );

		if ( ! is_array( $status ) ) {
			return false;
		}

		// Check if cleanup started within the last 30 minutes
		if ( isset( $status['started_at'] ) && ( time() - $status['started_at'] ) < 1800 ) {
			return 'processing' === $status['status'];
		}

		return false;
	}

	/**
	 * Get cleanup status
	 *
	 * @return array Status information
	 */
	public function get_cleanup_status(): array {
		$status          = lwtv_plugin()->get_transient( self::STATUS_TRANSIENT );
		$next_scheduled  = \as_next_scheduled_action( self::AS_HOOK );
		$daily_scheduled = \as_next_scheduled_action( 'lwtv_daily_transient_cleanup' );

		$default_status = array(
			'status'             => 'idle',
			'last_triggered'     => null,
			'started_at'         => null,
			'completed_at'       => null,
			'batches_processed'  => 0,
			'total_processed'    => 0,
			'cleaned_count'      => 0,
			'last_batch_size'    => 0,
			'last_batch_cleaned' => 0,
		);

		$status = is_array( $status ) ? array_merge( $default_status, $status ) : $default_status;

		return array_merge(
			$status,
			array(
				'next_scheduled'             => $next_scheduled,
				'daily_scheduled'            => $daily_scheduled,
				'action_scheduler_available' => lwtv_plugin()->is_action_scheduler_available(),
				'is_in_progress'             => $this->is_cleanup_in_progress(),
			)
		);
	}

	/**
	 * Update status information
	 *
	 * @param array $status Status data to update
	 * @return void
	 */
	private function update_status( array $status ): void {
		$current_status = lwtv_plugin()->get_transient( self::STATUS_TRANSIENT );
		$current_status = is_array( $current_status ) ? $current_status : array();

		$updated_status = array_merge( $current_status, $status );
		lwtv_plugin()->set_transient( self::STATUS_TRANSIENT, $updated_status, HOUR_IN_SECONDS );
	}

	/**
	 * Clear status information
	 *
	 * @return void
	 */
	private function clear_status(): void {
		lwtv_plugin()->delete_transient( self::STATUS_TRANSIENT );
	}

	/**
	 * Get current expired transient count
	 *
	 * @return int Number of expired transients
	 */
	public function get_expired_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->options
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);
	}

	/**
	 * Clean up transients by pattern
	 *
	 * @param string $pattern Pattern to match (e.g., 'wpseo_', 'lwtv_')
	 * @return int Number of transients cleaned
	 */
	public function cleanup_by_pattern( string $pattern ): int {
		global $wpdb;

		$cleaned_count = 0;

		// Get transients matching pattern
		$matching_transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options
				WHERE option_name LIKE %s",
				'_transient_' . $wpdb->esc_like( $pattern ) . '%'
			)
		);

		foreach ( $matching_transients as $transient ) {
			$transient_name = str_replace( '_transient_', '', $transient->option_name );

			if ( delete_transient( $transient_name ) ) {
				++$cleaned_count;
			}
		}

		lwtv_plugin()->error_log(
			'transient-cleanup',
			sprintf(
				'Pattern cleanup completed: %d transients cleaned for pattern "%s"',
				$cleaned_count,
				$pattern
			)
		);

		return $cleaned_count;
	}
}
