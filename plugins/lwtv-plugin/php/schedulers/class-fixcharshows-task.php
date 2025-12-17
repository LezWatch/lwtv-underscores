<?php
/**
 * Task handler for fixing character show group meta data on posts.
 *
 * This scheduled task processes posts to ensure that the 'lezchars_show_group'
 * post meta is properly formatted, converting any nested arrays in the 'show'
 * field to scalar values. This helps maintain data consistency for character
 * show group associations.
 *
 * Usage:
 *   - The task is triggered via the 'lwtv_fixcharshows_task' action hook.
 *   - Intended for use in scheduled or batch operations to clean up post meta.
 *
 * @package LWTV\Schedulers
 */

namespace LWTV\Schedulers;

class FixCharShows_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'lwtv_fixcharshows_task', array( $this, 'process_fix_char_shows_task' ) );
	}

	/**
	 * Process the scheduled fix character shows task
	 *
	 * @param int $post_id The post ID to process
	 * @return void
	 */
	public function process_fix_char_shows_task( int $post_id ): void {
		$all_shows = get_post_meta( $post_id, 'lezchars_show_group', true );
		$new_shows = array();

		// If it's not an array, we're good.
		if ( ! is_array( $all_shows ) ) {
			return;
		}

		lwtv_plugin()->error_log( 'characters', "Processing fix character shows task for ID: {$post_id}" );

		foreach ( $all_shows as $each_show ) {
			// If it's an array, de-array it.
			if ( isset( $each_show['show'] ) && is_array( $each_show['show'] ) ) {
				$each_show['show'] = reset( $each_show['show'] );
			}
			$new_shows[] = $each_show;
		}

		update_post_meta( $post_id, 'lezchars_show_group', $new_shows );
	}
}
