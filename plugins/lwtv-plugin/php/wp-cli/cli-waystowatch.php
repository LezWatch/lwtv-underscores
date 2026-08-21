<?php
/*
 * WP CLI Commands for Ways to Watch hosts.
 *
 * Two jobs:
 *   hosts  - which hosts are in use, how many shows use them, do they have a
 *            lez_watch_urls term, and what name do we currently render.
 *   enrich - ask hosts with no term what they call themselves, and cache it, so
 *            the long tail stops rendering as 'Tubitv' and 'Onemorelesbian'.
 *
 * New shows arrive with new hosts continuously, so `enrich` is worth running on
 * a schedule rather than once. It only ever touches hosts it has not already
 * asked about, so repeat runs are cheap.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Shows\Host_Name;
use LWTV\CPTs\Shows\Watch_Host_Names;
use LWTV\CPTs\Shows\Watch_Hosts;

/**
 * LezWatch.TV commands for Ways to Watch hosts.
 */
class WP_CLI_LWTV_WaysToWatch {

	/**
	 * How many hosts to enrich when --limit is not given.
	 */
	const DEFAULT_LIMIT = 25;

	/**
	 * Pause between requests, in milliseconds. These are unrelated third-party
	 * hosts, so this is politeness rather than a rate limit.
	 */
	const DEFAULT_SLEEP_MS = 500;

	/**
	 * Inspect or enrich Ways to Watch hosts.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 *   - hosts: list hosts in use with show counts, term status and rendered name.
	 *   - enrich: fetch og:site_name for hosts with no term, and cache it.
	 *   - forget: clear the enrichment cache.
	 *
	 * [--limit=<number>]
	 * : For `enrich`, how many hosts to process. For `hosts`, how many rows to show.
	 * ---
	 * default: 25
	 * ---
	 *
	 * [--all]
	 * : Process or show everything, ignoring --limit.
	 *
	 * [--min-shows=<number>]
	 * : Only consider hosts used by at least this many shows. Useful for `enrich`
	 * when you'd rather spend requests on hosts people actually reach.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--unregistered]
	 * : For `hosts`, show only hosts with no lez_watch_urls term.
	 *
	 * [--dry-run]
	 * : For `enrich`, report what would be cached without writing anything.
	 *
	 * [--recheck]
	 * : For `enrich`, also re-ask hosts already checked.
	 *
	 * [--sleep=<ms>]
	 * : Milliseconds between requests.
	 * ---
	 * default: 500
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format for `hosts`.
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
	 *     # What's out there, and what are we calling it?
	 *     $ wp lwtv waystowatch hosts --all
	 *
	 *     # Just the ones lacking a term, worst offenders first.
	 *     $ wp lwtv waystowatch hosts --unregistered --all
	 *
	 *     # See what enrichment would find, without writing.
	 *     $ wp lwtv waystowatch enrich --dry-run
	 *
	 *     # Enrich everything unregistered, politely.
	 *     $ wp lwtv waystowatch enrich --all
	 *
	 *     # Only bother with hosts used by 3+ shows.
	 *     $ wp lwtv waystowatch enrich --all --min-shows=3
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		switch ( $args[0] ?? '' ) {
			case 'hosts':
				$this->run_hosts( $assoc_args );
				break;
			case 'enrich':
				$this->run_enrich( $assoc_args );
				break;
			case 'forget':
				Watch_Host_Names::forget();
				\WP_CLI::success( 'Enrichment cache cleared.' );
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: hosts, enrich, forget' );
		}
	}

	/**
	 * List hosts in use.
	 *
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	private function run_hosts( array $assoc_args ): void {
		$format       = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$show_all     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$limit        = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$min_shows    = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'min-shows', 1 ) );
		$unreg_only   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'unregistered', false );
		$hosts        = Watch_Hosts::in_use();
		$rows         = array();
		$unregistered = 0;

		foreach ( $hosts as $host => $count ) {
			$term = Watch_Hosts::term_for( $host );

			if ( ! $term ) {
				++$unregistered;
			}

			if ( $unreg_only && $term ) {
				continue;
			}

			if ( $count < $min_shows ) {
				continue;
			}

			$rows[] = array(
				'host'     => $host,
				'shows'    => $count,
				'term'     => $term ? $term->name : '-',
				'enriched' => Watch_Host_Names::get( $host ) ?? '-',
				'rendered' => $term ? $term->name : ( Watch_Host_Names::get( $host ) ?? Host_Name::guess( $host ) ),
			);
		}

		if ( ! $show_all && $limit > 0 ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'host', 'shows', 'term', 'enriched', 'rendered' ) );

		if ( 'table' === $format ) {
			\WP_CLI::log(
				sprintf(
					'%d distinct hosts in use, %d with no term.',
					count( $hosts ),
					$unregistered
				)
			);
		}
	}

	/**
	 * Ask unregistered hosts what they call themselves.
	 *
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	private function run_enrich( array $assoc_args ): void {
		$dry_run     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$process_all = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$recheck     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'recheck', false );
		$limit       = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$min_shows   = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'min-shows', 1 ) );
		$sleep_ms    = max( 0, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS ) );

		$targets = array();
		foreach ( Watch_Hosts::in_use() as $host => $count ) {
			if ( $count < $min_shows ) {
				continue;
			}

			// A term already names this host better than any meta tag will.
			if ( Watch_Hosts::term_for( $host ) ) {
				continue;
			}

			if ( ! $recheck && Watch_Host_Names::is_checked( $host ) ) {
				continue;
			}

			$targets[ $host ] = $count;
		}

		if ( empty( $targets ) ) {
			\WP_CLI::success( 'Nothing to enrich. Every host in use either has a term or has already been asked.' );
			return;
		}

		if ( ! $process_all && $limit > 0 ) {
			$targets = array_slice( $targets, 0, $limit, true );
		}

		if ( $dry_run ) {
			\WP_CLI::log( 'DRY RUN — hosts will be fetched, but nothing will be cached.' );
		}

		\WP_CLI::log( sprintf( 'Asking %d host(s)...', count( $targets ) ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Fetching', count( $targets ) );
		$rows     = array();
		$found    = 0;
		$none     = 0;
		$failed   = 0;
		$last     = array_key_last( $targets );

		foreach ( $targets as $host => $count ) {
			$progress->tick();

			$result = Watch_Hosts::discover_name( $host );

			if ( 'error' === $result['status'] ) {
				++$failed;
			} elseif ( '' !== $result['name'] ) {
				++$found;
				$rows[] = array(
					'host'   => $host,
					'shows'  => $count,
					'was'    => Host_Name::guess( $host ),
					'now'    => $result['name'],
					'source' => $result['source'],
				);
				if ( ! $dry_run ) {
					Watch_Host_Names::set( $host, $result['name'], $result['source'] );
				}
			} else {
				++$none;
				// Record the miss so we don't ask again every run. Errors are
				// deliberately NOT recorded, so a blip doesn't become permanent.
				if ( ! $dry_run ) {
					Watch_Host_Names::set( $host, '', Watch_Host_Names::SOURCE_NONE );
				}
			}

			if ( $sleep_ms > 0 && $host !== $last ) {
				usleep( $sleep_ms * 1000 );
			}
		}

		$progress->finish();

		if ( ! empty( $rows ) ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'host', 'shows', 'was', 'now', 'source' ) );
		}

		\WP_CLI::log(
			sprintf(
				'%d named, %d published nothing usable, %d unreachable (will retry next run).',
				$found,
				$none,
				$failed
			)
		);

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run complete. Nothing cached.' );
			return;
		}

		\WP_CLI::success( sprintf( '%d host name(s) cached.', $found ) );
	}
}

\WP_CLI::add_command( 'lwtv waystowatch', 'WP_CLI_LWTV_WaysToWatch' );
