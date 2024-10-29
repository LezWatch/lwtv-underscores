<?php
/**
 * Publish Missed Schedule
 */

namespace LWTV\Features;

class Missed_Schedule {

	/**
	 * Constructor.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {}

	/**
	 * Missed schedule fixes. Hopefully.
	 */
	public function missed_schedule(): string {

		global $wpdb;

		$missed_transient = lwtv_plugin()->get_transient( 'lwtv_missed_schedule' );
		if ( false === ( $missed_transient ) ) {
			// If there's no transient, set it for 15 minutes
			$checktime = ( HOUR_IN_SECONDS / 4 );
			set_transient( 'lwtv_missed_schedule', 'check_posts', $checktime );
		} else {
			// If there is a transient and it hasn't expired, don't run this at all.
			return 'Missed Schedule check already running.';
		}

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

		foreach ( $ids as $the_id ) {
			if ( ! $the_id ) {
				continue;
			}
			wp_publish_post( $the_id );
		}

		return 'Published Missed Schedule Posts. ' . count( $ids ) . ' posts published.';
	}
}
