<?php
/*
 * WP CLI Commands for IMDb ID verification.
 *
 * IMDb reassigns title and name IDs and leaves the previous one working as a
 * redirect, so a stale ID still opens the right page in a browser while silently
 * breaking every exact-match API lookup keyed on it. Well-formed, right prefix,
 * works when clicked -- and wrong. Debug_Tool::validate_imdb() cannot see it.
 *
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\_Helpers\Imdb_Canonical;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Schedulers\Imdb_Verify_Task;

/**
 * LezWatch.TV commands for IMDb ID verification.
 */
class WP_CLI_LWTV_Imdb {

	/**
	 * Default number of posts to process when --limit is not given.
	 */
	public const DEFAULT_LIMIT = 100;

	/**
	 * Pause between requests, in milliseconds.
	 *
	 * Matches Imdb_Verify_Task: sized for TVMaze's documented 20-calls-per-10-
	 * seconds rather than TMDB's more generous allowance, since a shows sweep is
	 * entirely TVMaze.
	 */
	public const DEFAULT_SLEEP_MS = 500;

	/**
	 * Allowed --order values mapped to SQL. Fixed strings, never user input.
	 */
	public const ORDER_CLAUSES = array(
		'oldest' => 'p.ID ASC',
		'newest' => 'p.ID DESC',
		'random' => 'RAND()',
	);

	/**
	 * Verify or report on IMDb IDs.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 *   - status: report what is known, without calling any API.
	 *   - verify: check IDs against their oracle and record disagreements.
	 *   - list: list the recorded disagreements. No API calls.
	 *
	 * [<type>]
	 * : Which post type to work on.
	 * ---
	 * default: all
	 * options:
	 *   - shows
	 *   - actors
	 *   - all
	 * ---
	 *
	 * [--limit=<number>]
	 * : How many posts to verify. Applies per type. Ignored with --all.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--all]
	 * : Verify every candidate. This is the flag for seeding the first pass.
	 *
	 * [--dry-run]
	 * : Report verdicts without writing meta. Still calls the APIs.
	 *
	 * [--only-stale]
	 * : Print a line only for disagreements, not for every post checked.
	 *
	 * [--sleep=<ms>]
	 * : Milliseconds to pause between API requests.
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
	 * [--format=<format>]
	 * : Output format for status and list.
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
	 *     # What do we already know? No API calls.
	 *     $ wp lwtv imdb status
	 *
	 *     # Sample the rate honestly before committing to a full sweep.
	 *     $ wp lwtv imdb verify shows --dry-run --order=random
	 *
	 *     # Seed the first full pass. ~15 min per 1800 shows at the default sleep.
	 *     $ wp lwtv imdb verify --all --only-stale
	 *
	 *     # Work the findings.
	 *     $ wp lwtv imdb list --format=csv > stale-imdb.csv
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$action = $args[0] ?? '';
		$types  = $this->resolve_types( $args[1] ?? 'all' );

		switch ( $action ) {
			case 'status':
				$this->run_status( $types, $assoc_args );
				break;
			case 'list':
				$this->run_list( $types, $assoc_args );
				break;
			case 'verify':
				$this->run_verify( $types, $assoc_args );
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: status, verify, list' );
		}
	}

	/**
	 * Turn the <type> argument into post-type configs.
	 *
	 * Reads them from Imdb_Verify_Task so the CLI and the background queue cannot
	 * disagree about which meta keys are involved.
	 *
	 * @param string $requested shows, actors or all.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function resolve_types( string $requested ): array {
		$all = ( new Imdb_Verify_Task() )->config();

		$map = array(
			'shows'  => CPT_Shows::SLUG,
			'actors' => CPT_Actors::SLUG,
		);

		if ( 'all' === $requested ) {
			return $all;
		}

		if ( ! isset( $map[ $requested ] ) ) {
			\WP_CLI::error( 'Invalid type. Use: shows, actors, all' );
		}

		return array( $map[ $requested ] => $all[ $map[ $requested ] ] );
	}

	/**
	 * Report coverage. Makes no API calls.
	 *
	 * @param array $types      Post-type configs.
	 * @param array $assoc_args Flags.
	 */
	private function run_status( array $types, array $assoc_args ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$rows = array();
		foreach ( $types as $post_type => $config ) {
			$rows[] = array(
				'type'        => $post_type,
				'verifiable'  => count( $this->candidates( $post_type, $config, 'oldest', 0 ) ),
				'known stale' => $this->count_stale( $post_type, $config ),
				'oracle'      => ( CPT_Shows::SLUG === $post_type ) ? 'TVMaze' : 'TMDB',
			);
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'type', 'verifiable', 'known stale', 'oracle' ) );

		if ( 'table' === $format ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'verifiable  = has an IMDb ID and a third-party ID to check it against.' );
			\WP_CLI::log( 'known stale = a disagreement is recorded. Absence means no disagreement' );
			\WP_CLI::log( '              found OR never checked -- those are deliberately' );
			\WP_CLI::log( '              indistinguishable, so nothing reads silence as verified.' );
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Seed the first pass with: wp lwtv imdb verify --all --only-stale' );
		}
	}

	/**
	 * List recorded disagreements. Makes no API calls.
	 *
	 * @param array $types      Post-type configs.
	 * @param array $assoc_args Flags.
	 */
	private function run_list( array $types, array $assoc_args ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$rows = array();
		foreach ( $types as $post_type => $config ) {
			foreach ( $this->stale_posts( $post_type, $config ) as $row ) {
				$rows[] = array(
					'id'        => (int) $row['id'],
					'type'      => $post_type,
					'title'     => html_entity_decode( get_the_title( (int) $row['id'] ), ENT_QUOTES, 'UTF-8' ),
					'ours'      => (string) $row['ours'],
					'canonical' => (string) $row['canonical'],
					'oracle'    => ( CPT_Shows::SLUG === $post_type ) ? 'TVMaze' : 'TMDB',
				);
			}
		}

		if ( empty( $rows ) ) {
			\WP_CLI::success( 'No recorded disagreements.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'type', 'title', 'ours', 'canonical', 'oracle' ) );

		if ( 'table' === $format ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( count( $rows ) . ' disagreement(s). Check which ID is right before changing anything:' );
			\WP_CLI::log( 'a continuation that the oracle folds into a parent entry looks identical' );
			\WP_CLI::log( 'to a stale ID. For shows, tick "Ignore TVMaze Match" to silence one.' );
		}
	}

	/**
	 * Verify candidates against their oracle.
	 *
	 * @param array $types      Post-type configs.
	 * @param array $assoc_args Flags.
	 */
	private function run_verify( array $types, array $assoc_args ): void {
		$limit      = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$do_all     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$dry_run    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$only_stale = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'only-stale', false );
		$sleep_ms   = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', self::DEFAULT_SLEEP_MS );
		$order      = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'oldest' );

		if ( ! isset( self::ORDER_CLAUSES[ $order ] ) ) {
			\WP_CLI::error( 'Invalid --order. Use: ' . implode( ', ', array_keys( self::ORDER_CLAUSES ) ) );
		}

		if ( $dry_run ) {
			\WP_CLI::log( \WP_CLI::colorize( '%3DRY RUN -- no meta will be written. APIs are still called.%n' ) );
		}

		$task  = new Imdb_Verify_Task();
		$tally = array(
			'stale'       => 0,
			'match'       => 0,
			'no-oracle'   => 0,
			'not-set'     => 0,
			'unreachable' => 0,
		);

		foreach ( $types as $post_type => $config ) {
			$ids = $this->candidates( $post_type, $config, $order, $do_all ? 0 : max( 1, $limit ) );

			if ( empty( $ids ) ) {
				\WP_CLI::log( 'Nothing verifiable for ' . $post_type . '.' );
				continue;
			}

			\WP_CLI::log(
				'Verifying ' . count( $ids ) . ' ' . $post_type . ' against '
				. ( ( CPT_Shows::SLUG === $post_type ) ? 'TVMaze' : 'TMDB' )
				. ', ' . $sleep_ms . 'ms apart.'
			);

			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				$verdict = $task->verify( $post_id, $dry_run );

				if ( isset( $tally[ $verdict ] ) ) {
					++$tally[ $verdict ];
				}

				if ( Imdb_Canonical::STALE === $verdict || ! $only_stale ) {
					$title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
					\WP_CLI::log(
						sprintf(
							'  %-11s #%-7d %s',
							$verdict,
							$post_id,
							mb_substr( $title, 0, 48 )
						)
					);
				}

				usleep( $sleep_ms * 1000 );
			}

			\WP_CLI::log( '' );
		}

		$rows = array();
		foreach ( $tally as $verdict => $count ) {
			$rows[] = array(
				'verdict' => $verdict,
				'posts'   => $count,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'verdict', 'posts' ) );

		if ( $tally['stale'] > 0 ) {
			\WP_CLI::warning( $tally['stale'] . ' disagreement(s). Review with: wp lwtv imdb list' );
		}

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run complete. Nothing was written.' );
		} else {
			\WP_CLI::success( 'Verification complete.' );
		}
	}

	/**
	 * Posts that can be verified: an IMDb ID, an oracle ID, and no "no oracle entry" flag.
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $config    Meta keys for this type.
	 * @param string $order     One of ORDER_CLAUSES.
	 * @param int    $limit     0 for no limit.
	 *
	 * @return array<int, int>
	 */
	private function candidates( string $post_type, array $config, string $order, int $limit ): array {
		global $wpdb;

		// All interpolations are internal literals: $order was validated against
		// ORDER_CLAUSES, the meta keys come from Imdb_Verify_Task::config(), and
		// $skip_clause is one of two fixed strings.
		$order_by     = self::ORDER_CLAUSES[ $order ];
		$limit_clause = ( $limit > 0 ) ? 'LIMIT ' . (int) $limit : '';
		$skip_clause  = '';

		// Posts flagged as having no oracle entry are not candidates: there is
		// nothing to verify our ID against.
		if ( '' !== $config['no_oracle_meta'] ) {
			$skip_clause = "AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} noc"
				. ' WHERE noc.post_id = p.ID AND noc.meta_key = %s'
				. " AND noc.meta_value != '' AND noc.meta_value != '0' )";
		}

		$params = array( $config['imdb'], $config['oracle_id'] );
		if ( '' !== $skip_clause ) {
			$params[] = $config['no_oracle_meta'];
		}
		$params[] = $post_type;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// Placeholder count is variable ($skip_clause is conditional), so the sniff's static count of $params can't match; the spread always supplies exactly as many values as %s tokens above.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 INNER JOIN {$wpdb->postmeta} orc ON orc.post_id = p.ID AND orc.meta_key = %s AND orc.meta_value != ''
				 WHERE 1=1
				   {$skip_clause}
				   AND p.post_type = %s AND p.post_status = 'publish'
				 ORDER BY {$order_by}
				 {$limit_clause}",
				...$params
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * How many posts of this type have a recorded disagreement.
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $config    Meta keys for this type.
	 *
	 * @return int
	 */
	private function count_stale( string $post_type, array $config ): int {
		return count( $this->stale_posts( $post_type, $config ) );
	}

	/**
	 * Posts with a recorded disagreement, with both IDs.
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $config    Meta keys for this type.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function stale_posts( string $post_type, array $config ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id, im.meta_value as ours, can.meta_value as canonical
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} can ON can.post_id = p.ID AND can.meta_key = %s AND can.meta_value != ''
				 INNER JOIN {$wpdb->postmeta} im ON im.post_id = p.ID AND im.meta_key = %s AND im.meta_value != ''
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				 ORDER BY p.post_title ASC",
				$config['canonical'],
				$config['imdb'],
				$post_type
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Re-check rather than trusting the stored verdict: if an editor has since
		// corrected the ID to match, the meta is stale and this is no longer a
		// finding. Same live comparison the debugger does.
		return array_values(
			array_filter(
				(array) $found,
				static fn( $row ) => Imdb_Canonical::is_stale( $row['ours'], $row['canonical'] )
			)
		);
	}
}

\WP_CLI::add_command( 'lwtv imdb', 'WP_CLI_LWTV_Imdb' );
