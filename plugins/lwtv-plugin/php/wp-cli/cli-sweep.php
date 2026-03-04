<?php

/**
 * Description: WP-CLI: Sweep
 *
 * The code that runs the Bury Your Queers API service
 *
 */

use LWTV\Rest_API\BYQ;

class WP_CLI_LWTV_Sweep extends \WP_CLI_Command {

	/**
	 * Sweeps the BYQ death cache and flushes the entire object cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv sweep-death
	 *
	 * @when after_wp_load
	 */
	public function sweep_death( $args, $assoc_args ) {
		WP_CLI::log( 'Sweeping BYQ death cache...' );
		( new BYQ() )->invalidate_death_list_cache();
		WP_CLI::success( 'BYQ death cache swept.' );

		WP_CLI::log( 'Flushing object cache...' );
		wp_cache_flush();
		WP_CLI::success( 'Object cache flushed.' );
	}
}
