<?php
/*
 * WP CLI Commands for Ways to Watch hosts.
 *
 * Three jobs:
 *   hosts    - which hosts are in use, how many shows use them, do they have a
 *              lez_watch_urls term, and what name do we currently render.
 *   enrich   - ask hosts with no term what they call themselves, and cache it,
 *              so the long tail stops rendering as 'Tubitv' and 'Onemorelesbian'.
 *   termurls - read-only audit of what is actually stored in the term URL rows,
 *              and whether matching terms on host rather than on an exact URL
 *              string would change any meanings.
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
use LWTV\CPTs\Shows\Watch_Term_Url_Audit;

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
	 *   - termurls: audit what is stored in the lez_watch_urls term URL rows. Read-only.
	 *
	 * [--limit=<number>]
	 * : For `enrich`, how many hosts to process. For `hosts` and `termurls`, how
	 * many rows to show.
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
	 * [--flagged]
	 * : For `termurls`, show only rows with something wrong with them.
	 *
	 * [--blocking]
	 * : For `termurls`, show only rows that would block host-based matching.
	 * Implies --flagged.
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
	 * : Output format for `hosts` and `termurls`.
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
	 *     # Is host-based term matching safe to ship? Read-only.
	 *     $ wp lwtv waystowatch termurls --all
	 *
	 *     # Just the rows that need a decision.
	 *     $ wp lwtv waystowatch termurls --blocking --all
	 *
	 *     # Everything untidy, as a spreadsheet.
	 *     $ wp lwtv waystowatch termurls --flagged --all --format=csv
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
			case 'termurls':
				$this->run_termurls( $assoc_args );
				break;
			case 'forget':
				Watch_Host_Names::forget();
				\WP_CLI::success( 'Enrichment cache cleared.' );
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: hosts, enrich, termurls, forget' );
		}
	}

	/**
	 * Audit the stored term URLs.
	 *
	 * Answers one question: would switching term matching from an exact URL
	 * string to a normalised host change what any existing term means?
	 *
	 * Writes nothing, fetches nothing. Two queries -- the term URLs and the
	 * hosts in use -- and everything after that is pure.
	 *
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	private function run_termurls( array $assoc_args ): void {
		$format        = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$show_all      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$limit         = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$blocking_only = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'blocking', false );
		$flagged_only  = $blocking_only || (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'flagged', false );

		$report   = Watch_Term_Url_Audit::inspect( Watch_Hosts::term_urls(), Watch_Hosts::in_use() );
		$totals   = $report['totals'];
		$is_table = 'table' === $format;

		if ( 0 === $totals['rows'] ) {
			\WP_CLI::warning( 'No term URLs found at all. Either no term has a URL, or the ACF repeater meta is not the shape Watch_Hosts::term_urls() expects.' );
			return;
		}

		$rows = array();
		foreach ( $report['rows'] as $row ) {
			if ( $blocking_only && ! $row['blocking'] ) {
				continue;
			}

			if ( $flagged_only && array() === $row['flags'] ) {
				continue;
			}

			$rows[] = array(
				'term_id'  => $row['term_id'],
				'term'     => $row['term'],
				'url'      => $row['url'],
				'host'     => '' === $row['host'] ? '?' : $row['host'],
				'shows'    => $row['shows'],
				'blocking' => $row['blocking'] ? 'YES' : '',
				'flags'    => array() === $row['flags'] ? '-' : implode( ', ', $row['flags'] ),
			);
		}

		if ( ! $show_all && $limit > 0 && count( $rows ) > $limit ) {
			$shown = count( $rows );
			$rows  = array_slice( $rows, 0, $limit );

			if ( $is_table ) {
				\WP_CLI::log( sprintf( 'Showing %d of %d matching rows. Use --all for the rest.', $limit, $shown ) );
			}
		}

		if ( array() !== $rows ) {
			\WP_CLI\Utils\format_items(
				$format,
				$rows,
				array( 'term_id', 'term', 'url', 'host', 'shows', 'blocking', 'flags' )
			);
		} elseif ( $is_table ) {
			\WP_CLI::log( 'No rows matched that filter.' );
		}

		// The summary is the actual deliverable, so anything other than a table
		// stops here rather than polluting machine-readable output.
		if ( ! $is_table ) {
			return;
		}

		\WP_CLI::log( '' );
		\WP_CLI::log(
			sprintf(
				'%d URL row(s) across %d term(s), reducing to %d distinct host(s).',
				$totals['rows'],
				$totals['terms'],
				$totals['hosts']
			)
		);

		if ( array() !== $report['flag_counts'] ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'What is stored:' );

			$blocking_flags = Watch_Term_Url_Audit::blocking_flags();
			foreach ( $report['flag_counts'] as $flag => $count ) {
				\WP_CLI::log(
					sprintf(
						'  %-16s %4d  %s',
						$flag,
						$count,
						in_array( $flag, $blocking_flags, true ) ? 'BLOCKING - needs a decision' : 'cosmetic - host matching fixes this'
					)
				);
			}
		}

		if ( array() !== $report['collisions'] ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Host collisions -- two or more terms reduce to one host:' );

			foreach ( $report['collisions'] as $host => $terms ) {
				$named = array();
				foreach ( $terms as $term_id => $term_name ) {
					$named[] = sprintf( '%s (#%d)', $term_name, $term_id );
				}

				\WP_CLI::log( sprintf( '  %s  ->  %s', $host, implode( ' vs ', $named ) ) );
			}
		}

		\WP_CLI::log( '' );

		if ( 0 === $totals['blocking'] && 0 === $totals['collisions'] ) {
			\WP_CLI::success(
				sprintf(
					'Host matching is safe to ship. %d row(s) are merely untidy; nothing changes meaning.',
					$totals['flagged']
				)
			);
			return;
		}

		\WP_CLI::warning(
			sprintf(
				'%d blocking row(s) and %d host collision(s). Host matching would change what these mean -- decide each one before Phase 1. Run with --blocking --all to list them.',
				$totals['blocking'],
				$totals['collisions']
			)
		);
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
