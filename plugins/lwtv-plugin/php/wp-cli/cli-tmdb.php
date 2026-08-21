<?php
/*
 * WP CLI Commands for TMDB data.
 *
 * Backfills TMDB IDs for shows and actors that never had a lookup attempted.
 *
 * Context: TMDB IDs are normally populated on save, which means posts predating
 * that behaviour never got one. As of 2026-08-20 that was 2014 of 2262
 * published shows, with 2225 holding an IMDb ID to look up from and zero
 * recorded failed lookups. See DEBUGGER-REVIEW.md section 1.3.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\_Components\CPTs;
use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Schedulers\TMDB_Batch_Task;

/**
 * LezWatch.TV commands for TMDB data.
 */
class WP_CLI_LWTV_TMDB {

	/**
	 * Default number of posts to process when --limit is not given.
	 *
	 * Low on purpose: measure the hit rate on a sample before spending a
	 * couple of thousand API calls.
	 */
	const DEFAULT_LIMIT = 100;

	/**
	 * Default pause between requests, in milliseconds.
	 *
	 * ~4 req/sec, comfortably inside the limit the batch task assumes and
	 * matching the delay it already uses between individual requests.
	 */
	const DEFAULT_SLEEP_MS = 250;

	/**
	 * Allowed --order values mapped to SQL. Fixed strings, never user input.
	 *
	 * 'oldest' is the default because it makes repeated --limit runs advance
	 * through the backlog. It is a poor sampler though: the oldest posts are the
	 * long-established, mainstream ones, so a hit rate measured that way runs
	 * optimistic. Use 'random' when the number needs to mean something.
	 */
	const ORDER_CLAUSES = array(
		'oldest' => 'p.ID ASC',
		'newest' => 'p.ID DESC',
		'random' => 'RAND()',
	);

	/**
	 * Per-post-type configuration.
	 *
	 * Everything that differs between shows and actors lives here, so the
	 * lookup and reporting code stays type-agnostic.
	 *
	 * - meta_tmdb / meta_imdb: existing keys, see CPTs\Post_Meta.
	 * - meta_checked: timestamp of the last *attempted* lookup. Written on a
	 *   hit and on a genuine no-match, so "TMDB has nothing" and "we never
	 *   asked" stop being indistinguishable. Deliberately NOT written when the
	 *   API errors, so an outage can't permanently mark posts as unmatched.
	 * - imdb_kind: which prefix validate_imdb() should expect ('tt' vs 'nm').
	 * - result_key: where TMDB's find endpoint puts matches for this type.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_types(): array {
		return array(
			'shows'  => array(
				'post_type'    => CPT_Shows::SLUG,
				'singular'     => 'show',
				'plural'       => 'shows',
				'meta_tmdb'    => 'lezshows_tmdb_id',
				'meta_imdb'    => 'lezshows_imdb',
				'meta_checked' => 'lezshows_tmdb_checked',
				'imdb_kind'    => 'show',
				'imdb_example' => 'tt12345',
				'result_key'   => 'tv_results',
				// TV movies exist in the corpus, and TMDB files those under
				// movie_results. Detected but not stored -- see look_up().
				'other_key'    => 'movie_results',
				'debug_cmd'    => 'wp lwtv debug show_imdb',
			),
			'actors' => array(
				'post_type'    => CPT_Actors::SLUG,
				'singular'     => 'actor',
				'plural'       => 'actors',
				'meta_tmdb'    => 'lezactors_tmdb_id',
				'meta_imdb'    => 'lezactors_imdb',
				'meta_checked' => 'lezactors_tmdb_checked',
				'imdb_kind'    => 'actor',
				'imdb_example' => 'nm12345',
				'result_key'   => 'person_results',
				'other_key'    => '',
				'debug_cmd'    => 'wp lwtv debug actor_imdb',
			),
		);
	}

	/**
	 * Backfill or report on TMDB IDs.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 *   - status: report coverage without calling the API at all.
	 *   - backfill: look up missing TMDB IDs via each post's IMDb ID.
	 *
	 * [<type>]
	 * : Which post type to work on.
	 * ---
	 * default: shows
	 * options:
	 *   - shows
	 *   - actors
	 *   - all
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format for `status`. Non-table formats emit data only.
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
	 * : How many posts to process. Applies per type. Ignored when --all is passed.
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
	 * : Also re-attempt posts previously checked and found to have no match.
	 *
	 * [--sleep=<ms>]
	 * : Milliseconds to pause between API requests.
	 * ---
	 * default: 250
	 * ---
	 *
	 * [--order=<order>]
	 * : Which end of the backlog to work through. `oldest` makes repeated
	 * --limit runs walk steadily forward. `random` is the one to use when you
	 * are trying to *estimate* a hit rate, because oldest-first is biased
	 * toward long-established, mainstream titles that TMDB is certain to have.
	 * `newest` samples the hard end deliberately.
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
	 *     $ wp lwtv tmdb status all
	 *
	 *     # Estimate the hit rate honestly: random sample, nothing written.
	 *     $ wp lwtv tmdb backfill shows --dry-run --order=random
	 *
	 *     # Or probe the awkward end on purpose.
	 *     $ wp lwtv tmdb backfill shows --dry-run --order=newest
	 *
	 *     # Happy with the hit rate? Do 100 for real.
	 *     $ wp lwtv tmdb backfill shows
	 *
	 *     # Then the rest, for both types.
	 *     $ wp lwtv tmdb backfill all --all
	 *
	 *     # Much later, give previous no-matches another go.
	 *     $ wp lwtv tmdb backfill all --all --retry-missed
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$action = $args[0] ?? '';
		$types  = $this->resolve_types( $args[1] ?? 'shows' );

		switch ( $action ) {
			case 'status':
				$this->run_status( $types, $assoc_args );
				break;
			case 'backfill':
				$this->run_backfill( $types, $assoc_args );
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: status, backfill' );
		}
	}

	/**
	 * Turn the <type> argument into a list of type configs.
	 *
	 * @param string $requested 'shows', 'actors' or 'all'.
	 * @return array<string, array<string, string>>
	 */
	private function resolve_types( string $requested ): array {
		$all = $this->get_types();

		if ( 'all' === $requested ) {
			return $all;
		}

		if ( ! isset( $all[ $requested ] ) ) {
			\WP_CLI::error( 'Invalid type. Use: ' . implode( ', ', array_keys( $all ) ) . ', all' );
		}

		return array( $requested => $all[ $requested ] );
	}

	/**
	 * Report coverage. Makes no API calls.
	 *
	 * Metrics run down the rows and types across the columns, so `status all`
	 * is one table you can read side by side rather than two you have to
	 * scroll between.
	 *
	 * @param array $types      Type configs keyed by type key.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	private function run_status( array $types, array $assoc_args ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$counts = array();
		foreach ( $types as $key => $type ) {
			$counts[ $key ] = $this->get_counts( $type );
		}

		// Metric labels are identical across types, so take them from the first.
		$metrics = array_keys( reset( $counts ) );
		$rows    = array();

		foreach ( $metrics as $metric ) {
			$row = array( 'metric' => $metric );
			foreach ( $counts as $key => $set ) {
				$row[ $key ] = $set[ $metric ];
			}
			$rows[] = $row;
		}

		$columns = array_merge( array( 'metric' ), array_keys( $counts ) );

		// Machine-readable formats get data only -- no decoration to strip.
		if ( 'table' !== $format ) {
			\WP_CLI\Utils\format_items( $format, $rows, $columns );
			return;
		}

		$this->render_table( $columns, $rows );

		foreach ( $counts as $key => $set ) {
			if ( $set['candidates for backfill'] > 0 ) {
				\WP_CLI::log( sprintf( 'Next: wp lwtv tmdb backfill %s --dry-run', $key ) );
			}
		}
	}

	/**
	 * Render a table through WP_CLI::log().
	 *
	 * format_items() writes tables on a buffered path while WP_CLI::log() does
	 * a direct fwrite to STDOUT, so mixing the two reorders output -- every log
	 * line lands before every table. Emitting the table through the same
	 * channel keeps things in the order they were written.
	 *
	 * @param array $columns Column keys, in display order.
	 * @param array $rows    Rows keyed by column key.
	 * @return void
	 */
	private function render_table( array $columns, array $rows ): void {
		// Widest value in each column, header included.
		$widths = array();
		foreach ( $columns as $column ) {
			$widths[ $column ] = strlen( (string) $column );
		}
		foreach ( $rows as $row ) {
			foreach ( $columns as $column ) {
				$widths[ $column ] = max( $widths[ $column ], strlen( (string) ( $row[ $column ] ?? '' ) ) );
			}
		}

		$rule = '+';
		foreach ( $columns as $column ) {
			$rule .= str_repeat( '-', $widths[ $column ] + 2 ) . '+';
		}

		$header = '|';
		foreach ( $columns as $column ) {
			$header .= ' ' . str_pad( (string) $column, $widths[ $column ] ) . ' |';
		}

		\WP_CLI::log( $rule );
		\WP_CLI::log( $header );
		\WP_CLI::log( $rule );

		foreach ( $rows as $row ) {
			$line = '|';
			foreach ( $columns as $column ) {
				$value = (string) ( $row[ $column ] ?? '' );
				// Right-align numbers, left-align everything else.
				$line .= ' ' . ( is_numeric( $value )
					? str_pad( $value, $widths[ $column ], ' ', STR_PAD_LEFT )
					: str_pad( $value, $widths[ $column ] ) ) . ' |';
			}
			\WP_CLI::log( $line );
		}

		\WP_CLI::log( $rule );
	}

	/**
	 * Backfill TMDB IDs across one or more types.
	 *
	 * @param array $types      Type configs keyed by type key.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	private function run_backfill( array $types, array $assoc_args ): void {
		// Guard first: without the key every lookup fails, and there is no
		// point walking the corpus to discover that a few thousand times.
		if ( ! defined( 'TMDB_API' ) ) {
			\WP_CLI::error( 'TMDB_API is not defined. Nothing can be looked up.' );
		}

		$dry_run      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$process_all  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$retry_missed = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'retry-missed', false );
		$limit        = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$sleep_ms     = max( 0, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS ) );
		$order        = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'oldest' );

		if ( ! isset( self::ORDER_CLAUSES[ $order ] ) ) {
			\WP_CLI::error( 'Invalid --order. Use: ' . implode( ', ', array_keys( self::ORDER_CLAUSES ) ) );
		}

		// Derived from the batch task's own limits so the two can't drift apart.
		$floor_ms = (int) ceil(
			( TMDB_Batch_Task::RATE_LIMIT_WINDOW * 1000 ) / max( 1, TMDB_Batch_Task::RATE_LIMIT_REQUESTS )
		);
		if ( $sleep_ms < $floor_ms ) {
			\WP_CLI::warning(
				sprintf(
					'--sleep=%d is below the %dms implied by the rate limit this codebase assumes (%d requests / %ds). Expect throttling.',
					$sleep_ms,
					$floor_ms,
					TMDB_Batch_Task::RATE_LIMIT_REQUESTS,
					TMDB_Batch_Task::RATE_LIMIT_WINDOW
				)
			);
		}

		if ( $process_all ) {
			$limit = 0;
		} elseif ( $limit < 1 ) {
			\WP_CLI::error( '--limit must be 1 or more (or pass --all).' );
		}

		if ( $dry_run ) {
			\WP_CLI::log( 'DRY RUN — the API will be called, but no post meta will be written.' );
		}

		$grand_total = 0;

		foreach ( $types as $key => $type ) {
			$grand_total += $this->backfill_type( $key, $type, $limit, $retry_missed, $dry_run, $process_all, $sleep_ms, $order );
		}

		if ( count( $types ) > 1 ) {
			\WP_CLI::log( '' );
			\WP_CLI::success(
				$dry_run
					? sprintf( 'Dry run complete across all types. %d ID(s) would have been saved.', $grand_total )
					: sprintf( '%d TMDB ID(s) saved across all types.', $grand_total )
			);
		}
	}

	/**
	 * Backfill a single post type.
	 *
	 * @param string $key          Type key.
	 * @param array  $type         Type config.
	 * @param int    $limit        Max posts, 0 for no limit.
	 * @param bool   $retry_missed Include previous no-matches.
	 * @param bool   $dry_run      Suppress writes.
	 * @param bool   $process_all  Whether --all was passed.
	 * @param int    $sleep_ms     Pause between requests.
	 * @param string $order        Key into ORDER_CLAUSES.
	 * @return int Number of IDs found.
	 */
	private function backfill_type( string $key, array $type, int $limit, bool $retry_missed, bool $dry_run, bool $process_all, int $sleep_ms, string $order ): int {
		$post_ids = $this->get_candidates( $type, $limit, $retry_missed, $order );

		\WP_CLI::log( '' );
		\WP_CLI::log( strtoupper( $type['plural'] ) );

		if ( empty( $post_ids ) ) {
			\WP_CLI::log(
				sprintf(
					'Nothing to backfill. Every %s either has a TMDB ID or has no IMDb ID to look one up with.',
					$type['singular']
				)
			);
			return 0;
		}

		\WP_CLI::log(
			sprintf(
				'Processing %d %s at ~%s req/sec%s...',
				count( $post_ids ),
				1 === count( $post_ids ) ? $type['singular'] : $type['plural'],
				$sleep_ms > 0 ? round( 1000 / $sleep_ms, 1 ) : 'un-throttled ',
				$retry_missed ? ', including previous no-matches' : ''
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Looking up', count( $post_ids ) );

		$hits       = 0;
		$misses     = 0;
		$errors     = 0;
		$skipped    = 0;
		$invalid    = 0;
		$wrong_kind = 0;
		$last_one   = array_key_last( $post_ids );

		foreach ( $post_ids as $index => $post_id ) {
			$progress->tick();

			// Belt and braces. get_candidates() already excludes these, but a
			// long --all run can overlap an editor saving a post mid-flight.
			if ( ! empty( get_post_meta( $post_id, $type['meta_tmdb'], true ) ) ) {
				++$skipped;
				continue;
			}

			$imdb_id = (string) get_post_meta( $post_id, $type['meta_imdb'], true );

			// Don't spend a request on an IMDb ID that can't be valid.
			if ( ! ( new Debug_Tool() )->validate_imdb( $imdb_id, $type['imdb_kind'] ) ) {
				++$invalid;
				lwtv_plugin()->debug_log(
					'tmdb',
					sprintf( 'Backfill skipped %s %d: malformed IMDb ID "%s"', $type['singular'], $post_id, $imdb_id )
				);
				continue;
			}

			$result = $this->look_up( $post_id, $type );

			if ( 'error' === $result['status'] ) {
				++$errors;
			} elseif ( 'wrong_kind' === $result['status'] ) {
				++$wrong_kind;
				lwtv_plugin()->debug_log(
					'tmdb',
					sprintf(
						'Backfill: %s %d resolves to TMDB movie %s, not a TV series. Not stored.',
						$type['singular'],
						$post_id,
						$result['tmdb_id']
					)
				);
			} elseif ( 'hit' === $result['status'] ) {
				++$hits;
				if ( ! $dry_run ) {
					update_post_meta( $post_id, $type['meta_tmdb'], $result['tmdb_id'] );
					update_post_meta( $post_id, $type['meta_checked'], time() );
				}
			} else {
				++$misses;
				// Record the attempt so this post isn't indistinguishable from
				// "never tried" next time round.
				if ( ! $dry_run ) {
					update_post_meta( $post_id, $type['meta_checked'], time() );
				}
			}

			// No need to wait after the final request.
			if ( $sleep_ms > 0 && $index !== $last_one ) {
				usleep( $sleep_ms * 1000 );
			}
		}

		$progress->finish();
		$this->report( $key, $type, $hits, $misses, $errors, $skipped, $invalid, $wrong_kind, $dry_run, $process_all, $order );

		return $hits;
	}

	/**
	 * Look up one post's TMDB ID via its IMDb ID.
	 *
	 * Distinguishes four outcomes, because only a genuine no-match should be
	 * recorded as checked:
	 *   - hit:        TMDB returned an ID of the expected kind.
	 *   - wrong_kind: TMDB has this IMDb ID, but as a movie rather than a TV
	 *                 series. Storing that ID would break the later
	 *                 /tv/{id} call in get_tmdb_info(), so it is reported and
	 *                 left alone -- deliberately NOT sentinelled, because TMDB
	 *                 does have data and a human may want to act on it.
	 *   - miss:       TMDB answered, and has nothing for this IMDb ID.
	 *   - error:      the request failed, so we learned nothing.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $type    Type config.
	 * @return array{status: string, tmdb_id: string}
	 */
	private function look_up( int $post_id, array $type ): array {
		// get_tmdb_info() takes the find-by-IMDb path whenever there is no TMDB
		// ID on the post, which is exactly the case we're in here. It returns
		// false for transport failures and API errors alike.
		$data = ( new CPTs() )->get_tmdb_info( $post_id );

		if ( false === $data || ! is_array( $data ) ) {
			return array(
				'status'  => 'error',
				'tmdb_id' => '',
			);
		}

		$tmdb_id = $data[ $type['result_key'] ][0]['id'] ?? ( $data['id'] ?? '' );

		if ( ! empty( $tmdb_id ) ) {
			return array(
				'status'  => 'hit',
				'tmdb_id' => (string) $tmdb_id,
			);
		}

		// Nothing in the expected bucket. Did it land in another one?
		if ( ! empty( $type['other_key'] ) && ! empty( $data[ $type['other_key'] ][0]['id'] ) ) {
			return array(
				'status'  => 'wrong_kind',
				'tmdb_id' => (string) $data[ $type['other_key'] ][0]['id'],
			);
		}

		return array(
			'status'  => 'miss',
			'tmdb_id' => '',
		);
	}

	/**
	 * Print the summary for one type.
	 *
	 * @param string $key         Type key.
	 * @param array  $type        Type config.
	 * @param int    $hits        IDs found.
	 * @param int    $misses      Genuine no-matches.
	 * @param int    $errors      Failed requests.
	 * @param int    $skipped     Already had an ID by the time we got there.
	 * @param int    $invalid     Malformed IMDb IDs, no request spent.
	 * @param int    $wrong_kind  Found on TMDB, but as the wrong media type.
	 * @param bool   $dry_run     Whether anything was written.
	 * @param bool   $process_all Whether this was an --all run.
	 * @param string $order       Ordering used, for the sampling caveat.
	 * @return void
	 */
	private function report( string $key, array $type, int $hits, int $misses, int $errors, int $skipped, int $invalid, int $wrong_kind, bool $dry_run, bool $process_all, string $order ): void {
		$attempted = $hits + $misses + $errors + $wrong_kind;

		// Rendered via render_table() rather than format_items() so it stays in
		// order relative to the surrounding log lines. See render_table().
		$this->render_table(
			array( 'result', 'count' ),
			array(
				array(
					'result' => 'matched',
					'count'  => $hits,
				),
				array(
					'result' => 'no match on TMDB',
					'count'  => $misses,
				),
				array(
					'result' => 'request failed (will retry next run)',
					'count'  => $errors,
				),
				array(
					'result' => 'skipped - already had an ID',
					'count'  => $skipped,
				),
				array(
					'result' => 'skipped - malformed IMDb ID',
					'count'  => $invalid,
				),
				array(
					'result' => 'on TMDB as a movie, not a series (not stored)',
					'count'  => $wrong_kind,
				),
			)
		);

		if ( $attempted > 0 ) {
			\WP_CLI::log(
				sprintf(
					'Hit rate: %s%% (%d of %d lookups).',
					round( $hits / $attempted * 100, 1 ),
					$hits,
					$attempted
				)
			);

			// Don't let a biased sample masquerade as an estimate.
			if ( ! $process_all && 'oldest' === $order ) {
				\WP_CLI::log( 'Note: that sample was oldest-first, which favours well-known titles. Use --order=random for a hit rate you can extrapolate from.' );
			}
		}

		if ( $wrong_kind > 0 ) {
			\WP_CLI::log(
				sprintf(
					'%d %s match a TMDB *movie* rather than a TV series. Left untouched and not marked as checked — likely TV movies needing a decision. See the tmdb debug log for IDs.',
					$wrong_kind,
					$type['plural']
				)
			);
		}

		if ( $invalid > 0 ) {
			\WP_CLI::log(
				sprintf(
					'%d %s have a malformed IMDb ID (expected %s) — see: %s',
					$invalid,
					$type['plural'],
					$type['imdb_example'],
					$type['debug_cmd']
				)
			);
		}

		if ( $dry_run ) {
			\WP_CLI::log( sprintf( 'Dry run: %d ID(s) would have been saved.', $hits ) );
			return;
		}

		\WP_CLI::log( sprintf( '%d TMDB ID(s) saved.', $hits ) );

		if ( ! $process_all ) {
			$remaining = $this->get_counts( $type )['candidates for backfill'];
			if ( $remaining > 0 ) {
				\WP_CLI::log(
					sprintf( '%d candidate(s) left for %s. Use --all to process everything.', $remaining, $key )
				);
			}
		}
	}

	/**
	 * Coverage counts for one type, mirroring DEBUGGER-QUERIES.sql.
	 *
	 * @param array $type Type config.
	 * @return array<string, int>
	 */
	private function get_counts( array $type ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$published = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status = 'publish'",
				$type['post_type']
			)
		);

		$has_tmdb = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND pm.meta_value != ''",
				$type['meta_tmdb'],
				$type['post_type']
			)
		);

		$checked_no_match = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} tm ON tm.post_id = p.ID AND tm.meta_key = %s AND tm.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND tm.post_id IS NULL",
				$type['meta_checked'],
				$type['meta_tmdb'],
				$type['post_type']
			)
		);

		$no_imdb = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tm ON tm.post_id = p.ID AND tm.meta_key = %s AND tm.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND im.post_id IS NULL AND tm.post_id IS NULL",
				$type['meta_imdb'],
				$type['meta_tmdb'],
				$type['post_type']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'published'                  => $published,
			'has a TMDB ID'              => $has_tmdb,
			'checked, no match on TMDB'  => $checked_no_match,
			'no IMDb ID to look up with' => $no_imdb,
			'candidates for backfill'    => $this->count_candidates( $type, false ),
			'candidates incl. retries'   => $this->count_candidates( $type, true ),
		);
	}

	/**
	 * Count backfill candidates without materialising the ID list.
	 *
	 * @param array $type         Type config.
	 * @param bool  $retry_missed Include posts already checked without a match.
	 * @return int
	 */
	private function count_candidates( array $type, bool $retry_missed ): int {
		global $wpdb;

		$checked_clause = $retry_missed ? '' : 'AND chk.post_id IS NULL';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} im
				     ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tm
				     ON tm.post_id = p.ID AND tm.meta_key = %s AND tm.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} chk
				     ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = 'publish'
				   AND tm.post_id IS NULL
				   {$checked_clause}",
				$type['meta_imdb'],
				$type['meta_tmdb'],
				$type['meta_checked'],
				$type['post_type']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Posts needing a TMDB lookup.
	 *
	 * A candidate has no non-empty TMDB ID, has a non-empty IMDb ID to look up
	 * from, and — unless $retry_missed — has not already been checked.
	 *
	 * Ordering is caller's choice -- see ORDER_CLAUSES for why the default is a
	 * bad way to estimate a hit rate.
	 *
	 * @param array  $type         Type config.
	 * @param int    $limit        Max rows, or 0 for no limit.
	 * @param bool   $retry_missed Include posts already checked without a match.
	 * @param string $order        Key into ORDER_CLAUSES.
	 * @return array<int> Post IDs.
	 */
	private function get_candidates( array $type, int $limit, bool $retry_missed, string $order = 'oldest' ): array {
		global $wpdb;

		$checked_clause = $retry_missed ? '' : 'AND chk.post_id IS NULL';
		$limit_clause   = $limit > 0 ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';
		$order_clause   = self::ORDER_CLAUSES[ $order ] ?? self::ORDER_CLAUSES['oldest'];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} im
				     ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} tm
				     ON tm.post_id = p.ID AND tm.meta_key = %s AND tm.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} chk
				     ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = 'publish'
				   AND tm.post_id IS NULL
				   {$checked_clause}
				 ORDER BY {$order_clause}
				 {$limit_clause}",
				$type['meta_imdb'],
				$type['meta_tmdb'],
				$type['meta_checked'],
				$type['post_type']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map( 'intval', (array) $ids );
	}
}

\WP_CLI::add_command( 'lwtv tmdb', 'WP_CLI_LWTV_TMDB' );
