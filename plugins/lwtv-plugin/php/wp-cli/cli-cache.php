<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * Diagnostics for the statistics cache: confirm that stats transients are
 * actually evicted from a persistent object cache (Redis) on invalidation, and
 * inspect when each cached view was last built.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\_Components\Transients;

/**
 * LezWatch.TV commands to inspect and verify the statistics cache.
 */
class WP_CLI_LWTV_Cache {

	/**
	 * Show the state of the statistics cache.
	 *
	 * Reports whether a persistent object cache is active, how many stats
	 * transients are tracked in the index, and each tracked key's cached state
	 * and last-built time.
	 *
	 * ## OPTIONS
	 *
	 * [--year=<year>]
	 * : Only show tracked keys for a given year (e.g. the /this-year/ caches).
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv cache check
	 *     wp lwtv cache check --year=2026
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function check( $args, $assoc_args = array() ) {
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$year   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'year', '' );

		\WP_CLI::log( 'Persistent object cache (Redis, etc.) active: ' . ( wp_using_ext_object_cache() ? 'yes' : 'no' ) );

		$index = get_option( Transients::STATS_INDEX_OPTION, array() );
		$index = is_array( $index ) ? $index : array();

		\WP_CLI::log( 'Tracked stats transients in index: ' . count( $index ) );

		if ( empty( $index ) ) {
			\WP_CLI::success( 'Index is empty (nothing built yet, or freshly cleared).' );
			return;
		}

		$rows = array();
		foreach ( $index as $key => $built ) {
			if ( '' !== $year && ! str_ends_with( (string) $key, '_' . $year ) ) {
				continue;
			}
			$rows[] = array(
				'key'    => $key,
				'cached' => ( false !== lwtv_plugin()->get_transient( $key ) ) ? 'yes' : 'no',
				'built'  => wp_date( 'Y-m-d H:i:s', (int) $built ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::warning( 'No tracked keys matched.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'key', 'cached', 'built' ) );
	}

	/**
	 * Verify that a stats transient really is evicted on invalidation.
	 *
	 * Sets a canary stats transient, confirms it is cached, runs the statistics
	 * cache invalidation synchronously, then confirms the canary is gone. This
	 * proves eviction reaches a persistent object cache (Redis) rather than
	 * only deleting rows from wp_options.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv cache verify
	 */
	public function verify() {
		$canary = 'scores_lwtv_cache_diagnostic';

		\WP_CLI::log( 'Persistent object cache active: ' . ( wp_using_ext_object_cache() ? 'yes' : 'no' ) );

		lwtv_plugin()->set_transient( $canary, 'canary', HOUR_IN_SECONDS );

		if ( false === lwtv_plugin()->get_transient( $canary ) ) {
			\WP_CLI::error( 'Could not set the canary transient; aborting.' );
		}
		\WP_CLI::log( 'Canary set and confirmed cached: ' . $canary );

		// Invalidate + process synchronously (normally deferred to shutdown).
		$transients = new Transients();
		$transients->invalidate_statistics_cache( 'post_type_characters' );
		$transients->process_deferred_cache_invalidation();

		if ( false === lwtv_plugin()->get_transient( $canary ) ) {
			\WP_CLI::success( 'Eviction verified: the canary was cleared from the cache.' );
		} else {
			lwtv_plugin()->delete_transient( $canary );
			\WP_CLI::error( 'Eviction FAILED: the canary survived invalidation. Stats caches are not being cleared from the object cache.' );
		}
	}
}

\WP_CLI::add_command( 'lwtv cache', 'WP_CLI_LWTV_Cache' );
