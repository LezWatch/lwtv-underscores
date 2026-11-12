<?php
/*
 * WP CLI Commands for TMDB watch providers.
 * See docs/plans/tmdb-watch-providers.md#fetch-and-refresh-phase-2.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\_Helpers\Tmdb_Providers;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Schedulers\Watch_Providers_Task;

/**
 * LezWatch.TV commands for TMDB watch providers.
 */
class WP_CLI_LWTV_Watch_Providers {

	/**
	 * Columns for a refresh or per-show row.
	 */
	public const FIELDS = array( 'post_id', 'show', 'tmdb_id', 'result', 'countries', 'us' );

	/**
	 * Refresh or report on stored TMDB watch providers.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 *   - status: counts per refresh tier (on air weekly, ended monthly, no finish
	 *     date never), or one show's stored data with --post. No API calls.
	 *   - refresh: fetch now, in this process, instead of waiting for Action Scheduler.
	 *
	 * [--post=<id>]
	 * : One show. With refresh, fetched whether or not it is due.
	 *
	 * [--all]
	 * : With refresh, every show with a TMDB ID, due or not, in every tier.
	 *
	 * [--limit=<number>]
	 * : With refresh, how many shows. Ignored with --post.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--dry-run]
	 * : With refresh, list the shows that would be fetched. No API calls.
	 *
	 * [--format=<format>]
	 * : Output format.
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
	 *     # How many shows in each tier are fresh, due or never checked?
	 *     $ wp lwtv watchproviders status
	 *
	 *     # What is stored for one show? Compare with its themoviedb.org watch page.
	 *     $ wp lwtv watchproviders status --post=1234
	 *
	 *     # Fetch one show now.
	 *     $ wp lwtv watchproviders refresh --post=1234
	 *
	 *     # Work through the next 100 due shows.
	 *     $ wp lwtv watchproviders refresh --limit=100
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$action = $args[0] ?? '';
		$format = $assoc_args['format'] ?? 'table';
		$post   = isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0;

		if ( isset( $assoc_args['post'] ) && CPT_Shows::SLUG !== get_post_type( $post ) ) {
			\WP_CLI::error( 'That is not a show.' );
		}

		$task = new Watch_Providers_Task();

		switch ( $action ) {
			case 'status':
				if ( $post ) {
					\WP_CLI\Utils\format_items( $format, array( $this->row( $post, '' ) ), self::FIELDS );
					break;
				}

				$status = $task->get_status();
				$next   = $status['next_scheduled'] ? gmdate( 'Y-m-d H:i:s', (int) $status['next_scheduled'] ) . ' UTC' : 'none';

				\WP_CLI\Utils\format_items( $format, $status['tiers'], array_keys( $status['tiers'][0] ) );

				// Keep machine-readable formats parseable: the run time is for people.
				if ( 'table' === $format ) {
					\WP_CLI::log( 'Next run: ' . $next );
				}
				break;
			case 'refresh':
				$this->run_refresh( $task, $post, $assoc_args, $format );
				break;
			default:
				\WP_CLI::error( 'Use status or refresh.' );
		}
	}

	/**
	 * Fetch now.
	 *
	 * @param Watch_Providers_Task $task       The task.
	 * @param int                  $post       One show, or 0.
	 * @param array                $assoc_args Flags.
	 * @param string               $format     Output format.
	 * @return void
	 */
	private function run_refresh( Watch_Providers_Task $task, int $post, array $assoc_args, string $format ): void {
		$ids = $post ? array( $post ) : $task->stale_ids( max( 1, absint( $assoc_args['limit'] ?? 30 ) ), isset( $assoc_args['all'] ) );

		if ( empty( $ids ) ) {
			\WP_CLI::success( 'Nothing is due.' );
			return;
		}

		$dry  = isset( $assoc_args['dry-run'] );
		$rows = array();
		$last = count( $ids ) - 1;

		foreach ( $ids as $index => $id ) {
			$rows[] = $this->row( $id, $dry ? 'would fetch' : $task->refresh_post( $id ) );

			if ( ! $dry && $index < $last ) {
				usleep( Watch_Providers_Task::DELAY_US );
			}
		}

		\WP_CLI\Utils\format_items( $format, $rows, self::FIELDS );
	}

	/**
	 * One show as a row: what is stored now.
	 *
	 * @param int    $post_id Show ID.
	 * @param string $result  What refresh did, or ''.
	 * @return array<string, mixed>
	 */
	private function row( int $post_id, string $result ): array {
		$data    = Watch_Providers_Task::stored( $post_id );
		$checked = (int) get_post_meta( $post_id, Watch_Providers_Task::META_CHECKED, true );

		return array(
			'post_id'   => $post_id,
			'show'      => get_post_field( 'post_title', $post_id ),
			'tmdb_id'   => get_post_meta( $post_id, 'lezshows_tmdb_id', true ),
			'result'    => '' !== $result ? $result : ( $checked ? 'checked ' . gmdate( 'Y-m-d', $checked ) : 'never checked' ),
			'countries' => count( Tmdb_Providers::countries( $data, Tmdb_Providers::DISPLAY_TYPES ) ),
			'us'        => implode( ', ', array_column( Tmdb_Providers::for_country( $data, 'US', Tmdb_Providers::DISPLAY_TYPES ), 'name' ) ),
		);
	}
}

\WP_CLI::add_command( 'lwtv watchproviders', 'WP_CLI_LWTV_Watch_Providers' );
