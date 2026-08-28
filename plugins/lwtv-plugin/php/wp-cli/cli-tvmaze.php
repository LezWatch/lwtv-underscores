<?php
/*
 * WP CLI Commands for TVMaze IDs and aired-year data.
 *
 * Backfills lezshows_tvmaze_id for shows that never had a lookup attempted, and
 * optionally derives lezshows_aired_years from each show's season dates.
 *
 * Context: the score-preview run across all 2255 published shows put 1813 on the
 * curated season count and 442 on the raw airdate span. Not one reached the
 * TVMaze tier, because no show has lezshows_aired_years stored. Those 442 are
 * the shows where the denominator is currently least accurate -- still airing,
 * or no season count recorded -- so they are exactly the ones exact aired years
 * would help. See docs/plans/show-score-longevity.md.
 *
 * Deliberately a separate command rather than a lookup inside do_the_math().
 * Per-show HTTP during a bulk recalculation means rate limits, timeouts and
 * partial failures, which would leave some shows scored on one denominator tier
 * and others on another depending on when the API blinked. Scoring reads meta;
 * this command fills the meta.
 *
 * Mirrors the structure of cli-tmdb.php, including its timestamp rule: the
 * checked marker is written on a hit and on a genuine no-match, but never on an
 * API error, so an outage cannot permanently mark shows as unmatched.
 *
 * Two ways in, both exact. First the "Ignore TVMaze Match" toggle on the show,
 * which reveals a manual TVMaze ID field -- a human said "this show is that
 * entry", so it wins outright and needs no API call. Otherwise an IMDb lookup.
 *
 * That toggle with no ID means something different and equally useful: "I looked,
 * there is nothing to match." Criminal Minds: Evolution is the canonical case --
 * TVMaze keeps it on the parent Criminal Minds entry per its continuations
 * policy, so it will never match on its own IMDb ID. Ticking the toggle drops it
 * out of the candidate queries and out of `missing`, so the backlog reflects work
 * still to do rather than decisions already made.
 *
 * What we do NOT do is search by name. /search/shows is fuzzy and
 * /singlesearch/shows is explicitly undefined about which show it returns when
 * titles collide, and a wrong TVMaze ID would feed wrong aired years straight
 * into the show score. An exact match or nothing. The `reconcile` action uses
 * name search purely to propose corrections for human review, never to write.
 *
 * Note this is NOT because TVMaze requires an IMDb entry to be listed. Its
 * inclusion policy (https://www.tvmaze.com/faq/13/shows, checked 2026-08) makes
 * no mention of IMDb; the bar for shows on non-curated web channels is credited
 * cast/crew, sequential numbering, a fixed schedule, and one of notable
 * credits / a $25k verified budget / a later broadcast re-run. So a show without
 * an IMDb ID may well be on TVMaze -- we simply won't guess at which entry it is.
 *
 * Those shows are reported by `status` and left alone. Adding either an IMDb ID
 * or a manual TVMaze ID brings one into scope automatically.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Scoring\Airdates;
use LWTV\CPTs\Shows\Scoring\Longevity;

/**
 * LezWatch.TV commands for TVMaze data.
 */
class WP_CLI_LWTV_TVMaze {

	/**
	 * TV Maze API URL. Same constant the audit and calendar classes use.
	 */
	public const TVMAZE_URL = 'https://api.tvmaze.com';

	/**
	 * Existing ID meta.
	 */
	public const META_TVMAZE = 'lezshows_tvmaze_id';

	/**
	 * Existing IMDb meta, the preferred lookup key.
	 */
	public const META_IMDB = 'lezshows_imdb';

	/**
	 * Timestamp of the last *attempted* lookup, so "TVMaze has nothing" and
	 * "we never asked" stop being indistinguishable.
	 */
	public const META_CHECKED = 'lezshows_tvmaze_checked';

	/**
	 * Derived set of calendar years a show actually aired.
	 */
	public const META_AIRED = 'lezshows_aired_years';

	/**
	 * Editorial toggle: this show has no TVMaze entry of its own, or the
	 * automatic match is wrong. Stops it being reported as a problem.
	 */
	public const META_IGNORE = 'lezshows_tvmaze_ignore';

	/**
	 * Editorial TVMaze ID, revealed by the toggle. Never machine-written.
	 */
	public const META_ID_MANUAL = 'lezshows_tvmaze_id_manual';

	/**
	 * Default number of shows to process when --limit is not given.
	 *
	 * Low on purpose: measure the hit rate on a sample before spending a couple
	 * of thousand API calls.
	 */
	public const DEFAULT_LIMIT = 100;

	/**
	 * Default pause between requests, in milliseconds.
	 *
	 * TVMaze documents its rate limit as "at least 20 calls every 10 seconds"
	 * per IP -- i.e. 2/sec. 500ms sits on that budget. An earlier revision
	 * copied 250ms from cli-tmdb.php, which is 4/sec and twice the documented
	 * allowance; TMDB's limits are simply more generous than TVMaze's.
	 *
	 * Note --with-seasons makes two calls per show, so the effective rate is
	 * halved again. That is intentional headroom rather than waste.
	 */
	public const DEFAULT_SLEEP_MS = 500;

	/**
	 * Extra pause after an HTTP 429, in milliseconds.
	 *
	 * TVMaze asks clients to "back off for a few seconds" and retry rather than
	 * treat a 429 as a permanent failure.
	 */
	public const BACKOFF_MS = 5000;

	/**
	 * User agent. TVMaze strongly recommends a uniquely identifying one so they
	 * can reach out about problems rather than just blocking the IP.
	 */
	public const USER_AGENT = 'LezWatch.TV TVMaze backfill (+https://lezwatchtv.com)';

	/**
	 * Allowed --order values mapped to SQL. Fixed strings, never user input.
	 *
	 * 'oldest' is the default because it makes repeated --limit runs advance
	 * through the backlog. It is a poor sampler though: the oldest posts are the
	 * long-established, mainstream shows, so a hit rate measured that way runs
	 * optimistic. Use 'random' when the number needs to mean something.
	 */
	public const ORDER_CLAUSES = array(
		'oldest' => 'p.ID ASC',
		'newest' => 'p.ID DESC',
		'random' => 'RAND()',
	);

	/**
	 * Memoised editorial overrides: manual IDs and acknowledged non-matches.
	 *
	 * @var array{ids: array<int, int>, ignored: array<int, bool>}|null
	 */
	private ?array $overrides = null;

	/**
	 * Backfill or report on TVMaze IDs.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 *   - status: report coverage without calling the API at all.
	 *   - missing: list every show that still has no TVMaze ID, and why.
	 *   - reconcile: for no-match shows, ask TVMaze by NAME and report where the
	 *     IMDb ID it holds differs from ours. Read-only: proposes, never writes.
	 *   - backfill: look up missing TVMaze IDs.
	 *   - seasons: derive aired years for shows that already have a TVMaze ID.
	 *
	 * [--reason=<reason>]
	 * : missing only. Narrow the list to one cause.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - no-match
	 *   - no-imdb
	 *   - never-checked
	 * ---
	 *
	 * [--format=<format>]
	 * : status and missing. Output format for the tables.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--limit=<number>]
	 * : How many shows to process. Ignored when --all is passed.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--all]
	 * : Process every candidate instead of stopping at --limit.
	 *
	 * [--dry-run]
	 * : Report what would be saved without writing any post meta. NOTE: this
	 * still calls the API, because that's the only way to know the hit rate.
	 *
	 * [--retry-missed]
	 * : Also re-attempt shows previously checked and found to have no match.
	 *
	 * [--with-seasons]
	 * : backfill only. Also derive aired years for each show as it is matched,
	 * saving a second pass over those shows. Costs one extra API call each. Note
	 * this can only reach shows this run matched -- use the `seasons` action for
	 * shows that already had an ID.
	 *
	 * [--refresh]
	 * : seasons only. Re-fetch shows that already have aired years stored.
	 *
	 * [--scoring-only]
	 * : Restrict to shows where aired years could actually change the score.
	 * Because the curated season count (tier 1) is preferred over exact aired
	 * years (tier 2), a finished show with a season count will never consult
	 * aired years at all -- roughly 1813 of 2255 shows. This narrows the run to
	 * the rest: still-airing shows, and shows with no season count recorded.
	 * Meaningful with --with-seasons or the `seasons` action; on `backfill` alone
	 * it just skips useful ID lookups.
	 *
	 * [--sleep=<ms>]
	 * : Milliseconds to pause between API requests. The default sits on TVMaze's
	 * documented allowance of 20 calls per 10 seconds; lower it at your own risk.
	 * ---
	 * default: 500
	 * ---
	 *
	 * [--order=<order>]
	 * : Which end of the backlog to work through.
	 * ---
	 * default: oldest
	 * options:
	 *   - oldest
	 *   - newest
	 *   - random
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Where does coverage stand? No API calls.
	 *     $ wp lwtv tvmaze status
	 *
	 *     # Who is actually missing, and why? Also no API calls.
	 *     $ wp lwtv tvmaze missing
	 *     $ wp lwtv tvmaze missing --reason=no-match --format=csv > no-match.csv
	 *
	 *     # Of those no-matches, which look like a stale IMDb ID on our side?
	 *     # Read-only, proposes corrections for review.
	 *     $ wp lwtv tvmaze reconcile --all --format=csv > stale-imdb.csv
	 *
	 *     # Estimate the hit rate honestly: random sample, nothing written.
	 *     $ wp lwtv tvmaze backfill --dry-run --order=random
	 *
	 *     # Happy with it? Do 100 for real, IMDb lookups only.
	 *     $ wp lwtv tvmaze backfill
	 *
	 *     # Then the rest of the IDs. ~14 min at the default sleep.
	 *     $ wp lwtv tvmaze backfill --all
	 *
	 *     # Then aired years, but only where they can change a score. This is a
	 *     # separate action because `backfill` only ever touches shows MISSING an
	 *     # ID -- so it can never reach the shows that already have one.
	 *     $ wp lwtv tvmaze seasons --all --scoring-only
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$action = $args[0] ?? '';

		switch ( $action ) {
			case 'status':
				$this->run_status( $assoc_args );
				break;
			case 'missing':
				$this->run_missing( $assoc_args );
				break;
			case 'reconcile':
				$this->run_reconcile( $assoc_args );
				break;
			case 'backfill':
				$this->run_backfill( $assoc_args );
				break;
			case 'seasons':
				$this->run_seasons( $assoc_args );
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: status, missing, reconcile, backfill, seasons' );
		}
	}

	/**
	 * Report coverage. Makes no API calls.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_status( array $assoc_args = array() ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$counts = $this->get_counts();

		$rows = array();
		foreach ( $counts as $metric => $value ) {
			$rows[] = array(
				'metric' => $metric,
				'shows'  => $value,
			);
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'metric', 'shows' ) );

		// Break the no-match group down by format. A bare count of 430 cannot
		// distinguish "TVMaze policy excludes these" from "our IMDb IDs are
		// wrong", and those need completely different responses. TVMaze's bar for
		// non-curated web channels is high -- credited cast and crew, sequential
		// numbering, a fixed schedule, plus notable credits or a verified budget
		// or a broadcast re-run -- so a no-match group dominated by web series is
		// expected and fine. One dominated by ordinary series is a data problem.
		$breakdown = $this->get_no_match_breakdown();

		if ( ! empty( $breakdown ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Checked with no match, by format:' );
			\WP_CLI\Utils\format_items( $format, $breakdown, array( 'format', 'shows', 'share' ) );
			\WP_CLI::log(
				'Web series dominating this is expected -- TVMaze excludes most non-curated '
				. 'web-channel shows by policy. Ordinary series here are more likely stale IMDb '
				. 'IDs on our side: check with `wp lwtv debug show_imdb`.'
			);
		}

		if ( $counts['candidates for backfill'] > 0 ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Next: wp lwtv tvmaze backfill --dry-run --order=random' );
		}

		if ( $counts['need aired years (seasons)'] > 0 ) {
			\WP_CLI::log( '' );
			\WP_CLI::log(
				$counts['need aired years (seasons)'] . ' show(s) have a TVMaze ID but no aired years. '
				. '`backfill --with-seasons` cannot reach these -- it only touches shows missing an ID. Use: '
				. 'wp lwtv tvmaze seasons --all --scoring-only'
			);
		}

		if ( $counts['skipped: no IMDb ID'] > 0 ) {
			\WP_CLI::log( '' );
			\WP_CLI::log(
				$counts['skipped: no IMDb ID'] . ' show(s) have no IMDb ID and are skipped. They may still '
				. 'be on TVMaze -- we just will not guess which entry via name search. Add an IMDb ID to '
				. 'bring one into scope.'
			);
		}
	}

	/**
	 * Look up and optionally store missing TVMaze IDs.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_backfill( array $assoc_args ): void {
		$limit        = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$do_all       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$dry_run      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$retry_missed = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'retry-missed', false );
		$with_seasons = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'with-seasons', false );
		$scoring_only = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'scoring-only', false );
		$sleep_ms     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS );
		$order        = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'oldest' );

		if ( ! isset( self::ORDER_CLAUSES[ $order ] ) ) {
			\WP_CLI::error( 'Invalid --order. Use: ' . implode( ', ', array_keys( self::ORDER_CLAUSES ) ) );
		}

		$wanted = $do_all ? 0 : max( 1, $limit );

		// --scoring-only filters in PHP rather than SQL so it can call the same
		// tier test Longevity::run_years() uses. Expressing "finished, and has a
		// season count" in SQL would mean reimplementing the airdate resolution
		// (including the legacy serialized fallback and the 'current' sentinel),
		// and a filter that drifts from the scoring logic is worse than no filter.
		// So fetch unlimited, filter, then slice.
		if ( $scoring_only ) {
			$all_ids  = $this->get_candidates( $order, $retry_missed, 0 );
			$show_ids = array();

			foreach ( $all_ids as $candidate ) {
				if ( $this->aired_years_would_be_used( (int) $candidate ) ) {
					$show_ids[] = (int) $candidate;
					if ( $wanted > 0 && count( $show_ids ) >= $wanted ) {
						break;
					}
				}
			}

			\WP_CLI::log(
				'--scoring-only: ' . count( $show_ids ) . ' of ' . count( $all_ids )
				. ' candidates could actually use aired years.'
			);
		} else {
			$show_ids = $this->get_candidates( $order, $retry_missed, $wanted );
		}

		if ( $with_seasons && ! $scoring_only ) {
			\WP_CLI::log(
				'Note: --with-seasons without --scoring-only spends an extra call on every show, '
				. 'including the ~80% that will never consult aired years.'
			);
		}

		if ( empty( $show_ids ) ) {
			\WP_CLI::success( 'Nothing to do: no shows are missing a TVMaze ID.' );
			return;
		}

		if ( $dry_run ) {
			\WP_CLI::log( \WP_CLI::colorize( '%3DRY RUN -- no meta will be written. API calls still happen.%n' ) );
		}
		\WP_CLI::log( 'Processing ' . count( $show_ids ) . ' show(s), ' . $sleep_ms . 'ms between requests.' );
		\WP_CLI::log( '' );

		$tally = array(
			'matched'           => 0,
			'no match'          => 0,
			'api errors'        => 0,
			'aired years saved' => 0,
		);

		foreach ( $show_ids as $show_id ) {
			$show_id = (int) $show_id;
			$title   = $this->display_title( $show_id );
			$result  = $this->look_up( $show_id );

			switch ( $result['status'] ) {
				case 'error':
					// Deliberately no checked-marker: an outage must not
					// permanently mark this show as unmatched.
					++$tally['api errors'];
					\WP_CLI::warning( $title . ' (#' . $show_id . '): ' . $result['reason'] );
					break;

				case 'none':
					++$tally['no match'];
					if ( ! $dry_run ) {
						update_post_meta( $show_id, self::META_CHECKED, time() );
					}
					break;

				case 'found':
					++$tally['matched'];

					\WP_CLI::log(
						sprintf(
							'%s #%-7d %s tvmaze:%-8d %s',
							$dry_run ? 'would set' : 'set      ',
							$show_id,
							$this->fit( $title, 42 ),
							$result['id'],
							$result['name']
						)
					);

					if ( ! $dry_run ) {
						update_post_meta( $show_id, self::META_TVMAZE, $result['id'] );
						update_post_meta( $show_id, self::META_CHECKED, time() );
					}

					if ( $with_seasons ) {
						usleep( $sleep_ms * 1000 );
						$years = $this->fetch_aired_years( (int) $result['id'] );

						if ( ! empty( $years ) ) {
							++$tally['aired years saved'];
							if ( ! $dry_run ) {
								update_post_meta( $show_id, self::META_AIRED, $years );
							}
							\WP_CLI::log(
								'          aired years: ' . count( $years ) . ' ('
								. min( $years ) . '-' . max( $years ) . ')'
							);
						}
					}
					break;
			}

			usleep( $sleep_ms * 1000 );
		}

		\WP_CLI::log( '' );
		$rows = array();
		foreach ( $tally as $metric => $value ) {
			$rows[] = array(
				'outcome' => $metric,
				'shows'   => $value,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'outcome', 'shows' ) );

		$attempted = $tally['matched'] + $tally['no match'];
		if ( $attempted > 0 ) {
			$hit = $tally['matched'] / $attempted * 100;
			\WP_CLI::log( 'Hit rate: ' . number_format( $hit, 1 ) . '% of ' . $attempted . ' attempted (API errors excluded).' );
		}

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::success( 'Dry run complete. Nothing was written.' );
		} else {
			\WP_CLI::success( 'Backfill complete.' );
		}
	}

	/**
	 * Derive aired years for shows that already have a TVMaze ID.
	 *
	 * Separate from `backfill` because that action's candidates are shows MISSING
	 * an ID, so its --with-seasons flag can only ever reach shows it just matched.
	 * It cannot touch the ~499 shows that already had an ID and no aired years,
	 * nor anything after a completed backfill has emptied the candidate list.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_seasons( array $assoc_args ): void {
		$limit        = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$do_all       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$dry_run      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$scoring_only = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'scoring-only', false );
		$refresh      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'refresh', false );
		$sleep_ms     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS );
		$order        = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'oldest' );

		if ( ! isset( self::ORDER_CLAUSES[ $order ] ) ) {
			\WP_CLI::error( 'Invalid --order. Use: ' . implode( ', ', array_keys( self::ORDER_CLAUSES ) ) );
		}

		$wanted  = $do_all ? 0 : max( 1, $limit );
		$all_ids = $this->get_seasons_candidates( $order, $refresh, 0 );

		if ( $scoring_only ) {
			$filtered = array();
			foreach ( $all_ids as $candidate ) {
				if ( $this->aired_years_would_be_used( (int) $candidate ) ) {
					$filtered[] = (int) $candidate;
				}
			}

			\WP_CLI::log(
				'--scoring-only: ' . count( $filtered ) . ' of ' . count( $all_ids )
				. ' shows with a TVMaze ID could actually use aired years.'
			);

			$all_ids = $filtered;
		}

		$show_ids = ( $wanted > 0 ) ? array_slice( $all_ids, 0, $wanted ) : $all_ids;

		if ( empty( $show_ids ) ) {
			\WP_CLI::success( 'Nothing to do: no shows need aired years.' );
			return;
		}

		if ( $dry_run ) {
			\WP_CLI::log( \WP_CLI::colorize( '%3DRY RUN -- no meta will be written. API calls still happen.%n' ) );
		}
		\WP_CLI::log( 'Processing ' . count( $show_ids ) . ' show(s), ' . $sleep_ms . 'ms between requests.' );
		\WP_CLI::log( '' );

		$tally = array(
			'aired years found' => 0,
			'no season dates'   => 0,
			'api errors'        => 0,
		);

		foreach ( $show_ids as $show_id ) {
			$show_id   = (int) $show_id;
			$title     = $this->display_title( $show_id );
			$tvmaze_id = (int) get_post_meta( $show_id, self::META_TVMAZE, true );
			$years     = $this->fetch_aired_years( $tvmaze_id );

			if ( empty( $years ) ) {
				// fetch_aired_years() cannot distinguish "no usable dates" from a
				// transport failure, so this counts as neither a success nor a
				// permanent no. Nothing is written either way, so a re-run picks
				// it up again.
				++$tally['no season dates'];
				\WP_CLI::log( sprintf( '  --      #%-7d %s tvmaze:%d', $show_id, $this->fit( $title, 42 ), $tvmaze_id ) );
			} else {
				++$tally['aired years found'];

				if ( ! $dry_run ) {
					update_post_meta( $show_id, self::META_AIRED, $years );
				}

				\WP_CLI::log(
					sprintf(
						'%s #%-7d %s %d year(s): %d-%d',
						$dry_run ? 'would set' : 'set      ',
						$show_id,
						$this->fit( $title, 42 ),
						count( $years ),
						min( $years ),
						max( $years )
					)
				);
			}

			usleep( $sleep_ms * 1000 );
		}

		\WP_CLI::log( '' );
		$rows = array();
		foreach ( $tally as $metric => $value ) {
			$rows[] = array(
				'outcome' => $metric,
				'shows'   => $value,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'outcome', 'shows' ) );

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run complete. Nothing was written.' );
		} else {
			\WP_CLI::success( 'Aired years complete. Re-run score-preview --all to re-check SATURATION_K.' );
		}
	}

	/**
	 * Shows with a TVMaze ID that still need aired years.
	 *
	 * @param string $order   One of ORDER_CLAUSES.
	 * @param bool   $refresh Include shows that already have aired years stored.
	 * @param int    $limit   0 for no limit.
	 *
	 * @return array<int, int>
	 */
	private function get_seasons_candidates( string $order, bool $refresh, int $limit ): array {
		global $wpdb;

		// Both interpolations are internal literals: $order was validated against
		// ORDER_CLAUSES, and $aired_clause is one of two fixed strings.
		$order_by     = self::ORDER_CLAUSES[ $order ];
		$aired_clause = $refresh ? '' : 'AND ay.post_id IS NULL';
		$limit_clause = ( $limit > 0 ) ? 'LIMIT ' . (int) $limit : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} ay ON ay.post_id = p.ID AND ay.meta_key = %s AND ay.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   {$aired_clause}
				 ORDER BY {$order_by}
				 {$limit_clause}",
				self::META_TVMAZE,
				self::META_AIRED,
				CPT_Shows::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Resolve one show's TVMaze ID.
	 *
	 * Read-only on purpose. Calendar\TVMaze::get_tvmaze_info_show() performs the
	 * same lookup chain, but writes the ID as a side effect of fetching info,
	 * which --dry-run cannot use. Sharing that method would mean dry-run and the
	 * real run taking different code paths -- exactly the divergence that makes a
	 * dry run untrustworthy.
	 *
	 * IMDb lookups only. TVMaze can also be searched by name, but a name match is
	 * a guess -- /search/shows is fuzzy and /singlesearch/shows is explicitly
	 * undefined about which show it returns when titles collide -- and a wrong
	 * TVMaze ID feeds wrong aired years straight into the show score. The 37
	 * shows with no IMDb ID are skipped rather than guessed at. See the file
	 * header: that is our choice, not a TVMaze listing requirement.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return array{status: string, id: int, name: string, reason: string}
	 */
	private function look_up( int $show_id ): array {
		$out = array(
			'status' => 'none',
			'id'     => 0,
			'name'   => '',
			'reason' => '',
		);

		// An editorially-set ID wins outright: a human looked at both records and
		// said "this show is that TVMaze entry". No API call, no guessing, and it
		// works for shows whose titles do not match TVMaze's at all.
		$manual = $this->manual_tvmaze_id( $show_id );

		if ( $manual > 0 ) {
			$out['status'] = 'found';
			$out['id']     = $manual;
			$out['name']   = '(set by hand)';
			return $out;
		}

		$imdb = trim( (string) get_post_meta( $show_id, self::META_IMDB, true ) );

		// The candidate query requires an IMDb ID, so this is a guard against
		// drift between that query and this method, not an expected path.
		if ( '' === $imdb ) {
			$out['status'] = 'error';
			$out['reason'] = 'no IMDb ID and no curated mapping -- should have been excluded';
			return $out;
		}

		// Documented to answer a match with a 301 to the show's URL, or a 404
		// when the ID is unknown. wp_remote_get follows redirects by default, so
		// this normally lands on 200.
		$response = $this->request( self::TVMAZE_URL . '/lookup/shows?imdb=' . rawurlencode( $imdb ) );

		if ( 'ok' !== $response['status'] ) {
			$out['status'] = $response['status'];
			$out['reason'] = $response['reason'];
			return $out;
		}

		$show = $response['body'];

		if ( ! is_array( $show ) || empty( $show['id'] ) ) {
			return $out;
		}

		$out['status'] = 'found';
		$out['id']     = (int) $show['id'];
		$out['name']   = (string) ( $show['name'] ?? '' );

		return $out;
	}

	/**
	 * The editorially-set TVMaze ID for this show, if any.
	 *
	 * Comes from the "Ignore TVMaze Match" toggle on the show itself, which
	 * reveals a manual ID field. Deliberately NOT lezshows_tvmaze_id: that one is
	 * machine-written, and Calendar\TVMaze::get_tvmaze_info_show() updates it from
	 * whatever the API returns -- including from its /singlesearch/shows name-search
	 * fallback. So a fuzzy match on a show with no IMDb ID can overwrite it with
	 * the wrong ID, and the `if ( $tvmaze_id )` branch then trusts that forever.
	 * A manual value is never overwritten.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return int 0 when no manual ID is set.
	 */
	private function manual_tvmaze_id( int $show_id ): int {
		return (int) ( $this->overrides()['ids'][ $show_id ] ?? 0 );
	}

	/**
	 * Has an editor acknowledged that this show has no TVMaze entry of its own?
	 *
	 * The toggle without an ID means "I looked, there is nothing to match".
	 * Criminal Minds: Evolution is the canonical case -- TVMaze keeps it on the
	 * parent Criminal Minds entry per its continuations policy, so it will never
	 * match on its own IMDb ID and reporting it forever is noise.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return bool
	 */
	private function is_ignored( int $show_id ): bool {
		return isset( $this->overrides()['ignored'][ $show_id ] );
	}

	/**
	 * Both editorial overrides, read once per request.
	 *
	 * One query for the whole set rather than two meta reads per show. These are
	 * expected to be rare -- an escape hatch for stubborn cases, not a backlog --
	 * so scanning a couple of thousand candidates against an in-memory map beats
	 * a query each.
	 *
	 * @return array{ids: array<int, int>, ignored: array<int, bool>}
	 */
	private function overrides(): array {
		if ( null !== $this->overrides ) {
			return $this->overrides;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value
				 FROM {$wpdb->postmeta}
				 WHERE meta_key IN ( %s, %s ) AND meta_value != ''",
				self::META_IGNORE,
				self::META_ID_MANUAL
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$out = array(
			'ids'     => array(),
			'ignored' => array(),
		);

		foreach ( (array) $found as $row ) {
			$post_id = (int) $row['post_id'];

			if ( self::META_ID_MANUAL === $row['meta_key'] ) {
				if ( (int) $row['meta_value'] > 0 ) {
					$out['ids'][ $post_id ] = (int) $row['meta_value'];
				}
				continue;
			}

			// ACF true_false stores "1"/"0", so an explicit falsy value must not
			// count as an acknowledgement.
			if ( ! empty( $row['meta_value'] ) && '0' !== $row['meta_value'] ) {
				$out['ignored'][ $post_id ] = true;
			}
		}

		$this->overrides = $out;

		return $this->overrides;
	}

	/**
	 * One GET, with the shared handling for TVMaze's documented status codes.
	 *
	 * @param string $url Fully-formed URL.
	 *
	 * @return array{status: string, reason: string, body: mixed}
	 */
	private function request( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'user-agent' => self::USER_AGENT,
				'timeout'    => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'error',
				'reason' => 'request failed: ' . $response->get_error_message(),
				'body'   => null,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// A 404 is a genuine "TVMaze has nothing" rather than a fault, and is
		// the only non-2xx that earns a checked-marker.
		if ( 404 === $code ) {
			return array(
				'status' => 'none',
				'reason' => '',
				'body'   => null,
			);
		}

		// Rate limited. TVMaze asks clients to pause and retry rather than treat
		// this as permanent, so it must not be recorded as a no-match.
		if ( 429 === $code ) {
			usleep( self::BACKOFF_MS * 1000 );
			return array(
				'status' => 'error',
				'reason' => 'HTTP 429 rate limited -- backed off ' . ( self::BACKOFF_MS / 1000 ) . 's. Re-run with a larger --sleep.',
				'body'   => null,
			);
		}

		// 301 is the documented success shape for /lookup/shows; normally
		// already followed by wp_remote_get, but accepted here in case
		// redirection is disabled by a filter.
		if ( 200 !== $code && 301 !== $code ) {
			return array(
				'status' => 'error',
				'reason' => 'HTTP ' . $code,
				'body'   => null,
			);
		}

		return array(
			'status' => 'ok',
			'reason' => '',
			'body'   => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * A post title fit for terminal output.
	 *
	 * Titles come back HTML-encoded -- "Sex &#038; Violence", "Linc&#8217;s",
	 * "Uusi Päivä &#8211; Tabula Rasa" -- which is right for the web and noise in
	 * a CLI table. Same decode cli-audit.php uses.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function display_title( int $post_id ): string {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Fit a title into a fixed-width column: truncate, then pad.
	 *
	 * Both halves have to be character-aware, and the padding half is the one
	 * that is easy to miss.
	 *
	 * Truncation uses mb_substr rather than substr because these titles contain
	 * multibyte characters (Päivä, Lindenstraße, Shoujo☆Kageki) and a byte-wise
	 * cut lands mid-character and prints mojibake.
	 *
	 * Padding is done here rather than with sprintf's '%-42s', because that pads
	 * to a byte count too: "Gideon’s Crossing" is 17 characters but 19 bytes, so
	 * sprintf emitted two spaces too few and shunted the next column left.
	 * Decoding HTML entities is what exposed this -- '&#8217;' is seven ASCII
	 * bytes, and the ' it decodes to is three bytes but one visible character.
	 *
	 * mb_strlen, not mb_strwidth: WordPress polyfills mb_substr and mb_strlen in
	 * wp-includes/compat.php but not mb_strwidth, so using the latter would fatal
	 * where mbstring is missing. The cost is that full-width CJK still counts as
	 * one column when it occupies two -- acceptable for a CLI table, and no worse
	 * than before.
	 *
	 * @param string $title Decoded title.
	 * @param int    $width Column width in characters.
	 *
	 * @return string
	 */
	private function fit( string $title, int $width ): string {
		$title = mb_substr( $title, 0, $width );

		return $title . str_repeat( ' ', max( 0, $width - mb_strlen( $title ) ) );
	}

	/**
	 * Would exact aired years ever be consulted for this show?
	 *
	 * Mirrors the tier order in Longevity::run_years(): the curated season count
	 * wins for a show that has finished and has a count recorded, so aired years
	 * are only reachable when one of those is untrue.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return bool
	 */
	private function aired_years_would_be_used( int $show_id ): bool {
		$seasons = (int) get_post_meta( $show_id, 'lezshows_seasons', true );

		if ( $seasons < 1 ) {
			return true;
		}

		$airdates    = Airdates::get( $show_id );
		$finish      = trim( (string) $airdates['finish'] );
		$still_going = '' === $finish || Airdates::is_still_airing( $finish );

		if ( $still_going ) {
			return true;
		}

		// A finish year that has not passed yet also counts as still airing,
		// matching how run_years() and do_the_math() treat it.
		return (int) $finish >= (int) gmdate( 'Y' );
	}

	/**
	 * Derive the set of calendar years a show actually aired.
	 *
	 * @param int $tvmaze_id TVMaze show ID.
	 *
	 * @return array<int, int> Empty when unavailable.
	 */
	private function fetch_aired_years( int $tvmaze_id ): array {
		$response = $this->request( self::TVMAZE_URL . '/shows/' . $tvmaze_id . '/seasons' );

		if ( 'ok' !== $response['status'] || ! is_array( $response['body'] ) ) {
			return array();
		}

		return Longevity::aired_years_from_seasons( $response['body'], (int) gmdate( 'Y' ) );
	}

	/**
	 * List every show still without a TVMaze ID, with the reason.
	 *
	 * Makes no API calls. Exists because a count cannot be acted on: the response
	 * to "TVMaze does not carry this web series" is nothing, and the response to
	 * "our IMDb ID is stale" is a data fix. Seeing the titles and the IMDb IDs
	 * side by side is what separates them.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_missing( array $assoc_args = array() ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$reason = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'reason', 'all' );

		$valid = array( 'all', 'no-match', 'no-imdb', 'never-checked' );
		if ( ! in_array( $reason, $valid, true ) ) {
			\WP_CLI::error( 'Invalid --reason. Use: ' . implode( ', ', $valid ) );
		}

		$rows = $this->get_missing_rows( $reason );

		if ( empty( $rows ) ) {
			\WP_CLI::success( 'Nothing missing: every published show has a TVMaze ID.' );
			return;
		}

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'id', 'show', 'format', 'reason', 'imdb', 'airdates', 'last_checked' )
		);

		if ( 'table' === $format ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( count( $rows ) . ' show(s) listed.' );
			\WP_CLI::log( 'no-match      = we asked TVMaze with our IMDb ID and it had nothing.' );
			\WP_CLI::log( 'no-imdb       = no IMDb ID to ask with; never attempted.' );
			\WP_CLI::log( 'never-checked = has an IMDb ID but has not been through backfill yet.' );
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Tip: wp lwtv tvmaze missing --reason=no-match --format=csv > no-match.csv' );
		}
	}

	/**
	 * Find no-match shows whose IMDb ID has probably gone stale.
	 *
	 * IMDb reassigns title IDs, leaving the old one working as a redirect. TVMaze
	 * stores one canonical IMDb ID per show and /lookup/shows?imdb= is an exact
	 * match against it, so a stale-but-redirecting alias 404s even though the ID
	 * still resolves perfectly well for a human clicking it. Only Murders in the
	 * Building is the worked example: TVMaze holds tt11691774, we held tt12851524,
	 * and both point at the same show on IMDb itself.
	 *
	 * This searches TVMaze by NAME instead, and reports where the IMDb ID it holds
	 * differs from ours. Name search is exactly the fuzzy guess this command
	 * refuses to trust for writes -- so it is used only to surface candidates for
	 * review. Nothing is written, ever.
	 *
	 * Worth fixing beyond TVMaze: cli-tmdb.php resolves TMDB IDs from the same
	 * lezshows_imdb value, so a stale ID fails there too.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_reconcile( array $assoc_args = array() ): void {
		$format   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$limit    = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$do_all   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$sleep_ms = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS );

		$candidates = $this->get_missing_rows( 'no-match' );

		if ( empty( $candidates ) ) {
			\WP_CLI::success( 'No no-match shows to reconcile.' );
			return;
		}

		if ( ! $do_all ) {
			$candidates = array_slice( $candidates, 0, max( 1, $limit ) );
		}

		\WP_CLI::log( 'Read-only. Searching TVMaze by name for ' . count( $candidates ) . ' show(s).' );
		\WP_CLI::log( 'Nothing is written -- this proposes IMDb corrections for review.' );
		\WP_CLI::log( '' );

		$rows  = array();
		$tally = array(
			'imdb differs (likely stale)'  => 0,
			'same imdb (genuinely absent)' => 0,
			'tvmaze has no imdb'           => 0,
			'not found by name'            => 0,
			'api errors'                   => 0,
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Probing', count( $candidates ) );

		foreach ( $candidates as $candidate ) {
			$response = $this->request( self::TVMAZE_URL . '/search/shows?q=' . rawurlencode( $candidate['show'] ) );

			if ( 'error' === $response['status'] ) {
				++$tally['api errors'];
				$progress->tick();
				usleep( $sleep_ms * 1000 );
				continue;
			}

			$best = ( is_array( $response['body'] ) && isset( $response['body'][0]['show'] ) )
				? $response['body'][0]
				: null;

			if ( null === $best ) {
				++$tally['not found by name'];
				$progress->tick();
				usleep( $sleep_ms * 1000 );
				continue;
			}

			$their_imdb = $best['show']['externals']['imdb'] ?? '';
			$their_imdb = is_string( $their_imdb ) ? trim( $their_imdb ) : '';

			if ( '' === $their_imdb ) {
				++$tally['tvmaze has no imdb'];
			} elseif ( $their_imdb === $candidate['imdb'] ) {
				// Same ID, and the exact lookup still failed -- so TVMaze really
				// does not have this show, or has it without the external link.
				++$tally['same imdb (genuinely absent)'];
			} else {
				++$tally['imdb differs (likely stale)'];

				$rows[] = array(
					'id'          => $candidate['id'],
					'show'        => $candidate['show'],
					'our_imdb'    => $candidate['imdb'],
					'tvmaze_imdb' => $their_imdb,
					'tvmaze_id'   => (int) $best['show']['id'],
					'tvmaze_name' => (string) ( $best['show']['name'] ?? '' ),
					'score'       => number_format( (float) ( $best['score'] ?? 0 ), 2 ),
				);
			}

			$progress->tick();
			usleep( $sleep_ms * 1000 );
		}

		$progress->finish();

		if ( ! empty( $rows ) ) {
			\WP_CLI\Utils\format_items(
				$format,
				$rows,
				array( 'id', 'show', 'our_imdb', 'tvmaze_imdb', 'tvmaze_id', 'tvmaze_name', 'score' )
			);
			\WP_CLI::log( '' );
		}

		$summary = array();
		foreach ( $tally as $metric => $value ) {
			$summary[] = array(
				'outcome' => $metric,
				'shows'   => $value,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $summary, array( 'outcome', 'shows' ) );

		if ( ! empty( $rows ) ) {
			\WP_CLI::warning(
				count( $rows ) . ' show(s) where TVMaze holds a different IMDb ID. Check tvmaze_name '
				. 'matches before changing anything -- a name search can land on the wrong show, and '
				. 'TVMaze also merges continuations (Criminal Minds: Evolution lives on the parent '
				. 'Criminal Minds entry), which looks identical to a stale ID but is not one.'
			);
		}
	}

	/**
	 * Build the rows for `missing`.
	 *
	 * One query for the shows and their two meta values, one batched term lookup
	 * for formats, then Airdates::get() per row off the primed post cache.
	 *
	 * @param string $reason One of all|no-match|no-imdb|never-checked.
	 *
	 * @return array<int, array<string, string|int>>
	 */
	private function get_missing_rows( string $reason ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id,
				        COALESCE( im.meta_value, '' ) as imdb,
				        COALESCE( chk.meta_value, '' ) as checked
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tv.post_id IS NULL
				 ORDER BY p.post_title ASC",
				self::META_TVMAZE,
				self::META_IMDB,
				self::META_CHECKED,
				CPT_Shows::SLUG
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $found ) ) {
			return array();
		}

		$ids = array_map( static fn( $row ) => (int) $row['id'], $found );

		// Prime both caches the loop below reads from, so Airdates::get() and the
		// format lookup are not 467 individual queries.
		update_meta_cache( 'post', $ids );
		$formats = $this->formats_for( $ids );

		$rows = array();

		foreach ( $found as $row ) {
			$id       = (int) $row['id'];
			$has_imdb = '' !== trim( (string) $row['imdb'] );

			if ( ! $has_imdb ) {
				$why = 'no-imdb';
			} elseif ( '' === trim( (string) $row['checked'] ) ) {
				$why = 'never-checked';
			} else {
				$why = 'no-match';
			}

			// An acknowledged non-match is a decision, not a backlog item.
			if ( $this->is_ignored( $id ) ) {
				continue;
			}

			if ( 'all' !== $reason && $reason !== $why ) {
				continue;
			}

			$airdates = Airdates::get( $id );
			$finish   = '' === trim( $airdates['finish'] ) ? 'current' : $airdates['finish'];

			$rows[] = array(
				'id'           => $id,
				'show'         => $this->display_title( $id ),
				'format'       => $formats[ $id ] ?? '(none)',
				'reason'       => $why,
				'imdb'         => $has_imdb ? $row['imdb'] : '-',
				'airdates'     => $airdates['start'] . '-' . $finish,
				'last_checked' => '' === trim( (string) $row['checked'] )
					? '-'
					: gmdate( 'Y-m-d', (int) $row['checked'] ),
			);
		}

		return $rows;
	}

	/**
	 * lez_formats slug per show ID, from one batched lookup.
	 *
	 * @param array $ids Show IDs.
	 *
	 * @return array<int, string>
	 */
	private function formats_for( array $ids ): array {
		$out   = array();
		$terms = wp_get_object_terms( $ids, 'lez_formats', array( 'fields' => 'all_with_object_id' ) );

		if ( is_wp_error( $terms ) ) {
			return $out;
		}

		foreach ( $terms as $term ) {
			$out[ (int) $term->object_id ] = $term->slug;
		}

		return $out;
	}

	/**
	 * The checked-but-unmatched shows, grouped by lez_formats term.
	 *
	 * Read-only, and cheap: one query for the IDs, one batched term lookup.
	 *
	 * @return array<int, array<string, string|int>> Empty when nothing has been checked yet.
	 */
	private function get_no_match_breakdown(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tv.post_id IS NULL",
				self::META_CHECKED,
				self::META_TVMAZE,
				CPT_Shows::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$ids = array_map( 'intval', (array) $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$total  = count( $ids );
		$tally  = array();
		$tagged = array();

		$terms = wp_get_object_terms( $ids, 'lez_formats', array( 'fields' => 'all_with_object_id' ) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tagged[ $term->object_id ] = true;

				if ( ! isset( $tally[ $term->slug ] ) ) {
					$tally[ $term->slug ] = 0;
				}
				++$tally[ $term->slug ];
			}
		}

		// A show with no lez_formats term is an ordinary series by default, the
		// same assumption the score preview prints.
		$untagged = $total - count( $tagged );
		if ( $untagged > 0 ) {
			$tally['(no format term)'] = $untagged;
		}

		arsort( $tally );

		$out = array();
		foreach ( $tally as $slug => $count ) {
			$out[] = array(
				'format' => $slug,
				'shows'  => $count,
				'share'  => number_format( $count / $total * 100, 1 ) . '%',
			);
		}

		$out[] = array(
			'format' => 'TOTAL',
			'shows'  => $total,
			'share'  => '100.0%',
		);

		return $out;
	}

	/**
	 * Coverage counts. Makes no API calls.
	 *
	 * @return array<string, int>
	 */
	private function get_counts(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$published = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status = 'publish'",
				CPT_Shows::SLUG
			)
		);

		$has_tvmaze = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND pm.meta_value != ''",
				self::META_TVMAZE,
				CPT_Shows::SLUG
			)
		);

		$has_aired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND pm.meta_value != ''",
				self::META_AIRED,
				CPT_Shows::SLUG
			)
		);

		$checked_no_match = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tv.post_id IS NULL",
				self::META_CHECKED,
				self::META_TVMAZE,
				CPT_Shows::SLUG
			)
		);

		$no_ids = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND im.post_id IS NULL AND tv.post_id IS NULL",
				self::META_IMDB,
				self::META_TVMAZE,
				CPT_Shows::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'published shows'              => $published,
			'have a TVMaze ID'             => $has_tvmaze,
			'have aired years stored'      => $has_aired,
			'checked, no match found'      => $checked_no_match,
			'skipped: no IMDb ID'          => $no_ids,
			'candidates for backfill'      => $this->count_candidates( false ),
			'candidates incl. past misses' => $this->count_candidates( true ),
			'need aired years (seasons)'   => count( $this->get_seasons_candidates( 'oldest', false, 0 ) ),
			'acknowledged non-matches'     => count( $this->overrides()['ignored'] ),
			'TVMaze ID set by hand'        => count( $this->overrides()['ids'] ),
		);
	}

	/**
	 * Count backfill candidates without materialising the ID list.
	 *
	 * Requires an IMDb ID. Not because TVMaze demands one to list a show -- its
	 * inclusion policy says nothing about IMDb -- but because an exact ID lookup
	 * is the only match we trust enough to write into a scoring input. Shows
	 * without one are reported separately by status() and never counted as
	 * candidates.
	 *
	 * @param bool $retry_missed Include shows already checked without a match.
	 *
	 * @return int
	 */
	private function count_candidates( bool $retry_missed ): int {
		global $wpdb;

		$checked_clause = $retry_missed ? '' : 'AND chk.post_id IS NULL';

		// Reachable if we have an IMDb ID to ask with, OR an editor set the TVMaze
		// ID by hand. And never reachable once an editor has ticked "ignore" --
		// that is an explicit "stop asking". All literals.
		$curated_exists = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} man"
			. " WHERE man.post_id = p.ID AND man.meta_key = 'lezshows_tvmaze_id_manual'"
			. " AND man.meta_value != '' AND man.meta_value != '0' )";
		$not_ignored    = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ign"
			. " WHERE ign.post_id = p.ID AND ign.meta_key = 'lezshows_tvmaze_ignore'"
			. " AND ign.meta_value != '' AND ign.meta_value != '0' )";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tv.post_id IS NULL
				   AND ( im.post_id IS NOT NULL OR {$curated_exists} )
				   AND {$not_ignored}
				   {$checked_clause}",
				self::META_IMDB,
				self::META_TVMAZE,
				self::META_CHECKED,
				CPT_Shows::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $count;
	}

	/**
	 * Shows missing a TVMaze ID, in the requested order.
	 *
	 * @param string $order        One of ORDER_CLAUSES.
	 * @param bool   $retry_missed Include shows already checked without a match.
	 * @param int    $limit        0 for no limit.
	 *
	 * @return array<int, int>
	 */
	private function get_candidates( string $order, bool $retry_missed, int $limit ): array {
		global $wpdb;

		// Both interpolations are keys into fixed constants, never user input:
		// $order was validated against ORDER_CLAUSES, and $checked_clause is one
		// of two literals.
		$order_by       = self::ORDER_CLAUSES[ $order ];
		$checked_clause = $retry_missed ? '' : 'AND chk.post_id IS NULL';
		$limit_clause   = ( $limit > 0 ) ? 'LIMIT ' . (int) $limit : '';

		// Reachable if we have an IMDb ID to ask with, OR an editor set the TVMaze
		// ID by hand. And never reachable once an editor has ticked "ignore" --
		// that is an explicit "stop asking". All literals.
		$curated_exists = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} man"
			. " WHERE man.post_id = p.ID AND man.meta_key = 'lezshows_tvmaze_id_manual'"
			. " AND man.meta_value != '' AND man.meta_value != '0' )";
		$not_ignored    = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ign"
			. " WHERE ign.post_id = p.ID AND ign.meta_key = 'lezshows_tvmaze_ignore'"
			. " AND ign.meta_value != '' AND ign.meta_value != '0' )";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tv ON tv.post_id = p.ID AND tv.meta_key = %s AND tv.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tv.post_id IS NULL
				   AND ( im.post_id IS NOT NULL OR {$curated_exists} )
				   AND {$not_ignored}
				   {$checked_clause}
				 ORDER BY {$order_by}
				 {$limit_clause}",
				self::META_IMDB,
				self::META_TVMAZE,
				self::META_CHECKED,
				CPT_Shows::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', (array) $ids );
	}
}

\WP_CLI::add_command( 'lwtv tvmaze', 'WP_CLI_LWTV_TVMaze' );
