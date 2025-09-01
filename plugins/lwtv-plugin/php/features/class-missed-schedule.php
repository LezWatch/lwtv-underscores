<?php
/**
 * Publish Missed Schedule
 *
 * This class handles the publication of posts that have missed their scheduled time.
 *
 * MIGRATION TO ACTION SCHEDULER:
 * - When Action Scheduler is available, uses recurring actions every hour
 * - Maintains backward compatibility with transient-based approach
 * - Integrates with health checks system for monitoring
 * - Provides WP-CLI commands for status and manual triggering
 *
 * USAGE:
 * - Automatic: Action Scheduler runs every hour when available
 * - Manual: wp lwtv generate missed-schedule [status|trigger]
 * - Legacy: wp lwtv generate cron hourly (still works)
 *
 * HEALTH CHECKS:
 * - Automatically pings health.ipstenu.com after each run
 * - Requires HEALTHCHECKS_API_KEY to be defined
 * - Check name: {domain}-missed-schedule-check
 */

namespace LWTV\Features;

class Missed_Schedule {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_missed_schedule_check';

	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// Register Action Scheduler hook
		add_action( self::AS_HOOK, array( $this, 'process_missed_schedule' ) );

		// Initialize Action Scheduler if available
		if ( $this->is_action_scheduler_available() ) {
			$this->init_action_scheduler();
		}
	}

	/**
	 * Initialize Action Scheduler recurring action
	 *
	 * @return void
	 */
	private function init_action_scheduler(): void {
		// Only schedule if no existing actions are scheduled
		if ( ! as_next_scheduled_action( self::AS_HOOK ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::AS_HOOK );
			lwtv_plugin()->error_log( 'missed-schedule', 'Scheduled recurring missed schedule check via Action Scheduler' );
		}
	}

	/**
	 * Check if Action Scheduler is available
	 *
	 * @return bool
	 */
	private function is_action_scheduler_available(): bool {
		return lwtv_plugin()->is_action_scheduler_available();
	}

	/**
	 * Process missed schedule (Action Scheduler handler)
	 *
	 * @return void
	 */
	public function process_missed_schedule(): void {
		$result = $this->check_and_publish_missed_posts();
		lwtv_plugin()->error_log( 'missed-schedule', 'Action Scheduler processed missed schedule: ' . $result );

		// Ping health check if available
		$this->ping_health_check();
	}

	/**
	 * Missed schedule fixes. Hopefully.
	 *
	 * This method maintains backward compatibility for WP-CLI calls
	 * while also being used by Action Scheduler.
	 */
	public function missed_schedule(): string {
		// If Action Scheduler is available, use it
		if ( $this->is_action_scheduler_available() ) {
			// For WP-CLI calls, we can still run immediately
			return $this->check_and_publish_missed_posts();
		}

		// Fallback to transient-based approach for backward compatibility
		return $this->missed_schedule_with_transient();
	}

	/**
	 * Legacy transient-based missed schedule check
	 *
	 * @return string
	 */
	private function missed_schedule_with_transient(): string {
		global $wpdb;

		$missed_transient = lwtv_plugin()->get_transient( 'lwtv_missed_schedule' );
		if ( false === ( $missed_transient ) ) {
			// If there's no transient, set it for 15 minutes
			$checktime = ( HOUR_IN_SECONDS / 4 );
			lwtv_plugin()->set_transient( 'lwtv_missed_schedule', 'check_posts', $checktime );
		} else {
			// If there is a transient and it hasn't expired, don't run this at all.
			return 'Missed Schedule check already running.';
		}

		return $this->check_and_publish_missed_posts();
	}

	/**
	 * Check and publish missed posts
	 *
	 * @return string
	 */
	private function check_and_publish_missed_posts(): string {
		global $wpdb;

		$queery = <<<SQL
SELECT ID FROM {$wpdb->posts} WHERE ( ( post_date > 0 && post_date <= %s ) ) AND post_status = 'future' LIMIT 0,10
SQL;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $queery, current_time( 'mysql', 0 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $sql );

		// There are no posts missed schedule so don't run anything.
		if ( ! count( $ids ) ) {
			return 'No posts missed schedule.';
		}

		$published_count = 0;
		foreach ( $ids as $the_id ) {
			if ( ! $the_id ) {
				continue;
			}

			$result = wp_publish_post( $the_id );
			if ( $result ) {
				++$published_count;
				lwtv_plugin()->error_log( 'missed-schedule', "Published missed schedule post ID: {$the_id}" );
			} else {
				lwtv_plugin()->error_log( 'missed-schedule', "Failed to publish missed schedule post ID: {$the_id}" );
			}
		}

		return "Published Missed Schedule Posts. {$published_count} posts published.";
	}

	/**
	 * Get Action Scheduler status
	 *
	 * @return array
	 */
	public function get_scheduler_status(): array {
		$as_available   = $this->is_action_scheduler_available();
		$next_scheduled = null;

		if ( $as_available ) {
			$next_scheduled = as_next_scheduled_action( self::AS_HOOK );
		}

		return array(
			'action_scheduler_available' => $as_available,
			'next_scheduled_check'       => $next_scheduled,
			'current_method'             => $as_available ? 'Action Scheduler' : 'Transient-based',
		);
	}

	/**
	 * Manually trigger missed schedule check
	 *
	 * @return string
	 */
	public function trigger_check(): string {
		if ( $this->is_action_scheduler_available() ) {
			// Schedule an immediate action
			as_schedule_single_action( time(), self::AS_HOOK );
			return 'Missed schedule check scheduled via Action Scheduler.';
		}

		// Fallback to immediate execution
		return $this->missed_schedule();
	}

	/**
	 * Ping health check if available
	 *
	 * @return void
	 */
	private function ping_health_check(): void {
		// Check if health checks are available and configured
		if ( ! defined( 'HEALTHCHECKS_API_KEY' ) ) {
			return;
		}

		// Generate health check name similar to your existing pattern
		$prefix = defined( 'HEALTHCHECKS_PREFIX' ) ? HEALTHCHECKS_PREFIX : 'healthchecks';
		$domain = wp_parse_url( home_url() );
		if ( isset( $domain['host'] ) ) {
			$prefix = str_replace( '.', '-', $domain['host'] );
		}

		$check_name = $prefix . '-missed-schedule-check';
		$check_url  = 'https://health.ipstenu.com/api/v3/checks/' . $check_name . '/ping/';

		// Ping the health check
		$response = wp_remote_post(
			$check_url,
			array(
				'headers' => array(
					'X-Api-Key'    => HEALTHCHECKS_API_KEY,
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			lwtv_plugin()->error_log( 'missed-schedule', 'Health check pinged successfully' );
		} else {
			lwtv_plugin()->error_log( 'missed-schedule', 'Failed to ping health check: ' . $response->get_error_message() );
		}
	}
}
