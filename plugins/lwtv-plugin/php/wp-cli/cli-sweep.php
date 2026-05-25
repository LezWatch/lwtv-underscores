<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description: WP-CLI: Sweep
 *
 * The code that runs the Bury Your Queers API service
 *
 */

use LWTV\Rest_API\BYQ;

class WP_CLI_LWTV_Sweep extends \WP_CLI_Command {

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
	 * Sweep the cache
	 *
	 * @param array $args The arguments passed to the command.
	 * @param array $assoc_args The associative arguments passed to the command.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {

		$this->format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$type   = isset( $args[0] ) ? $args[0] : 'death';
		$action = ( isset( $args[1] ) ) ? $args[1] : null;

		try {
			$this->run_sweep_command( $type, $action );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}
	/**
	 * Sweeps the BYQ death cache and flushes the entire object cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv sweep-death
	 *
	 * @when after_wp_load
	 */
	public function run_sweep_command( string $type, ?string $action ): void {
		switch ( $type ) {
			case 'death':
				$this->run_death_sweep( $action );
				break;
			default:
				\WP_CLI::error( 'Invalid sweep type. Use: death' );
				break;
		}
	}

	/**
	 * Sweeps the BYQ death cache and flushes the entire object cache.
	 *
	 * @param string|null $action The action to perform (currently unused)
	 * @return void
	 */
	private function run_death_sweep( ?string $action ): void {
		if ( 'status' === $action ) {
			// return the last death cache status
			$last_death_cache = get_option( 'lwtv_last_death_cache' );
			WP_CLI::log( 'Last death cache status: ' . $last_death_cache );
			return;
		}

		WP_CLI::log( 'Sweeping BYQ death cache...' );
		( new BYQ() )->invalidate_death_list_cache();
		WP_CLI::success( 'BYQ death cache swept.' );

		$this->sweep_opcache_and_object_cache();
	}

	/**
	 * Sweep OpCache and Object Cache
	 *
	 * @return void
	 */
	private function sweep_opcache_and_object_cache(): void {
		WP_CLI::log( 'Flushing object cache...' );
		wp_cache_flush();
		WP_CLI::success( 'Object cache flushed.' );
	}
}

\WP_CLI::add_command( 'lwtv sweep', 'WP_CLI_LWTV_Sweep' );
