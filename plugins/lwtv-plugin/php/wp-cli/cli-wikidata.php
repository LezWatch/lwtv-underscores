<?php
/*
 * WP CLI Commands for WikiData Q-IDs.
 *
 * Backfills lezactors_wikidata_qid from each actor's IMDb ID, recording how the
 * match was made so an unattended process can tell a verified identity from a
 * guess.
 *
 * Only the exact IMDb statement match (P345) is allowed to write here. The name
 * search that Debugger\Actors uses for its diff view is deliberately absent: a
 * first-hit name match is a coin toss for a common name, and this command's
 * output is what the death audit later treats as an identity.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\This_Year\Build\Shared_Builder;
use LWTV\Wikidata\Build\Qid_Trust;
use LWTV\Wikidata\Identity;

/**
 * LezWatch.TV commands for WikiData identity data.
 */
class WP_CLI_LWTV_WikiData {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * Default number of actors to process when --limit is not given.
	 *
	 * Low on purpose: measure the hit rate on a sample before spending a couple
	 * of thousand API calls.
	 */
	public const DEFAULT_LIMIT = 100;

	/**
	 * Columns for a backfill result row.
	 */
	public const FIELDS = array( 'actor_id', 'actor', 'status', 'imdb', 'qid', 'was', 'note' );

	/**
	 * Construct to block facet from munging results.
	 */
	public function __construct() {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter( 'facetwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * Resolve actors to WikiData Q-IDs.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : What to do.
	 * options:
	 * - status   (how many actors are identified, unverified, or unreachable)
	 * - backfill (resolve Q-IDs from IMDb IDs)
	 * - actor    (look one actor up and explain the outcome)
	 * ---
	 *
	 * [<id>]
	 * : Actor post ID (required for 'actor').
	 *
	 * [--dry-run]
	 * : Backfill only. Report what would be written without writing it.
	 *
	 * [--limit=<number>]
	 * : Backfill only. How many actors to process. 0 for no limit.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--sleep=<ms>]
	 * : Backfill only. Pause between requests, in milliseconds.
	 * ---
	 * default: 400
	 * ---
	 *
	 * [--retry-missed]
	 * : Backfill only. Include actors already checked without a match.
	 *
	 * [--reverify]
	 * : Backfill only. Re-check Q-IDs we cannot vouch for -- those from a name
	 * search, and those stored before we recorded sources -- against the IMDb
	 * match. This is what turns an inherited Q-ID into one the death audit will
	 * act on.
	 *
	 * [--letter=<letter>]
	 * : Backfill only. Restrict to one alphabet bucket: a-z, 'num' (#), or 'intl' (-).
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # How much of the catalogue can we actually identify?
	 *     wp lwtv wikidata status
	 *
	 *     # Rehearse a backfill on a sample
	 *     wp lwtv wikidata backfill --dry-run
	 *
	 *     # Fill in the blanks, a letter at a time
	 *     wp lwtv wikidata backfill --letter=a --limit=0
	 *
	 *     # Verify the Q-IDs we inherited, so the death audit can use them
	 *     wp lwtv wikidata backfill --reverify --limit=0
	 *
	 *     # Explain one actor
	 *     wp lwtv wikidata actor 6789
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {
		$this->format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$type         = $args[0] ?? 'status';

		try {
			switch ( $type ) {
				case 'status':
					$this->cmd_status();
					break;
				case 'backfill':
					$this->cmd_backfill( $assoc_args );
					break;
				case 'actor':
					$this->cmd_actor( (int) ( $args[1] ?? 0 ) );
					break;
				default:
					\WP_CLI::error( 'Invalid type. Use: status, backfill, actor <id>.' );
			}
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}
	}

	/* ------------------------------------------------------------------
	 * STATUS
	 * ---------------------------------------------------------------- */

	/**
	 * How identifiable is the actor catalogue?
	 *
	 * The split that matters is trusted versus unverified. A large unverified
	 * count is not a broken database -- those Q-IDs may well be right -- it is
	 * the number of actors the death audit has to refuse to answer about until
	 * --reverify has checked them.
	 */
	private function cmd_status(): void {
		$counts = $this->status_counts();

		$rows = array();
		foreach ( $counts['breakdown'] as $label => $count ) {
			$rows[] = array(
				'group' => $label,
				'count' => $count,
			);
		}

		\WP_CLI\Utils\format_items( $this->format, $rows, array( 'group', 'count' ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( '%d published actors.', $counts['total'] ) );
		\WP_CLI::log( sprintf( '%d can be identified for unattended checks (trusted Q-ID).', $counts['trusted'] ) );

		if ( $counts['unverified'] > 0 ) {
			\WP_CLI::log( sprintf( '%d hold a Q-ID we cannot vouch for -- try: wp lwtv wikidata backfill --reverify --limit=0', $counts['unverified'] ) );
		}

		if ( $counts['candidates'] > 0 ) {
			\WP_CLI::log( sprintf( '%d have an IMDb ID and no Q-ID -- try: wp lwtv wikidata backfill --limit=0', $counts['candidates'] ) );
		}

		if ( $counts['unreachable'] > 0 ) {
			\WP_CLI::log( sprintf( '%d have neither, so nothing can be looked up for them.', $counts['unreachable'] ) );
		}
	}

	/* ------------------------------------------------------------------
	 * BACKFILL
	 * ---------------------------------------------------------------- */

	/**
	 * Resolve Q-IDs for as many actors as the flags allow.
	 *
	 * @param array $assoc_args Associative args.
	 */
	private function cmd_backfill( array $assoc_args ): void {
		$dry_run  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit    = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', self::DEFAULT_LIMIT );
		$sleep_ms = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'sleep', Identity::DEFAULT_SLEEP_MS );
		$letter   = $this->parse_letter( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'letter', '' ) );
		$flags    = array(
			'retry_missed' => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'retry-missed', false ),
			'reverify'     => (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'reverify', false ),
		);

		$identity  = new Identity();
		$actor_ids = $this->get_candidates( $flags['retry_missed'] );

		if ( '' !== $letter ) {
			$actor_ids = $this->filter_by_letter( $actor_ids, $letter );
		}

		if ( empty( $actor_ids ) ) {
			\WP_CLI::success( 'Nothing to resolve. Try --reverify, --retry-missed, or `wp lwtv wikidata status`.' );
			return;
		}

		$is_table = ( 'table' === $this->format );
		$rows     = array();
		$tally    = array();
		$skipped  = array();
		$done     = 0;

		$progress = $is_table
			? \WP_CLI\Utils\make_progress_bar( sprintf( 'Checking up to %d of %d actors', ( $limit > 0 ) ? $limit : count( $actor_ids ), count( $actor_ids ) ), count( $actor_ids ) )
			: null;

		foreach ( $actor_ids as $actor_id ) {
			if ( $progress ) {
				$progress->tick();
			}

			if ( $limit > 0 && $done >= $limit ) {
				break;
			}

			$actor_id = (int) $actor_id;

			// The decision itself is Qid_Trust's, so the CLI, the scheduler and
			// the SQL above cannot drift into disagreeing about who is worth a
			// request. The query is only a cheap way to narrow the field.
			$decision = Qid_Trust::should_check( $identity->collect( $actor_id, $flags ) );

			if ( ! $decision['check'] ) {
				$skipped[ $decision['reason'] ] = ( $skipped[ $decision['reason'] ] ?? 0 ) + 1;
				continue;
			}

			$result = $identity->resolve_and_record( $actor_id, $dry_run );
			$status = $result['status'];

			$tally[ $status ] = ( $tally[ $status ] ?? 0 ) + 1;
			++$done;

			// 'confirmed' is the boring outcome and by far the most common under
			// --reverify. Listing thousands of them would bury the conflicts.
			if ( 'confirmed' !== $status ) {
				$rows[] = $this->build_row( $actor_id, $identity->imdb_id( $actor_id ), $result );
			}

			$identity->throttle( $sleep_ms );
		}

		if ( $progress ) {
			$progress->finish();
		}

		if ( ! empty( $rows ) ) {
			\WP_CLI\Utils\format_items( $this->format, $rows, self::FIELDS );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI STDERR write, must bypass STDOUT so redirected CSV/JSON stays clean
		fwrite( STDERR, $this->summary_line( $tally, $skipped, $done, $dry_run ) . "\n" );
	}

	/* ------------------------------------------------------------------
	 * SINGLE ACTOR
	 * ---------------------------------------------------------------- */

	/**
	 * Look one actor up and say what happened, including when nothing did.
	 *
	 * @param int $actor_id Actor post ID.
	 */
	private function cmd_actor( int $actor_id ): void {
		if ( ! $actor_id || CPT_Actors::SLUG !== get_post_type( $actor_id ) ) {
			\WP_CLI::error( 'Pass the post ID of an actor: wp lwtv wikidata actor <id>' );
		}

		$identity = new Identity();
		$name     = $this->clean_title( $actor_id );
		$decision = Qid_Trust::should_check( $identity->collect( $actor_id, array( 'reverify' => true ) ) );

		if ( ! $decision['check'] ) {
			$trusted = $identity->trusted_qid( $actor_id );

			\WP_CLI::log( $name . ': not looked up -- ' . $decision['reason'] . '.' );
			\WP_CLI::log(
				'' !== $trusted['qid']
					? 'The death audit will use ' . $trusted['qid'] . ' (' . $trusted['source'] . ').'
					: 'The death audit cannot identify this actor.'
			);
			return;
		}

		$result = $identity->resolve_and_record( $actor_id );

		\WP_CLI\Utils\format_items(
			$this->format,
			array( $this->build_row( $actor_id, $identity->imdb_id( $actor_id ), $result ) ),
			self::FIELDS
		);
	}

	/* ------------------------------------------------------------------
	 * HELPERS
	 * ---------------------------------------------------------------- */

	/**
	 * One result row.
	 *
	 * @param int    $actor_id Actor post ID.
	 * @param string $imdb     The IMDb ID we asked with.
	 * @param array  $result   Result from Identity::resolve_and_record().
	 * @return array
	 */
	private function build_row( int $actor_id, string $imdb, array $result ): array {
		return array(
			'actor_id' => $actor_id,
			'actor'    => $this->clean_title( $actor_id ),
			'status'   => $result['status'],
			'imdb'     => $imdb,
			'qid'      => $result['qid'],
			'was'      => $result['was'],
			'note'     => $result['reason'],
		);
	}

	/**
	 * A post title fit for terminal output.
	 *
	 * @param int $actor_id Actor post ID.
	 * @return string
	 */
	private function clean_title( int $actor_id ): string {
		return html_entity_decode( get_the_title( $actor_id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * One-line summary of a run.
	 *
	 * Reports the skip reasons as well as the results, because the interesting
	 * failure of a backfill is a run that did almost nothing: "3 resolved" reads
	 * the same whether there were four candidates or four thousand.
	 *
	 * @param array $tally   Status => count.
	 * @param array $skipped Skip reason => count.
	 * @param int   $done    Actors actually asked about.
	 * @param bool  $dry_run Whether anything was written.
	 * @return string
	 */
	private function summary_line( array $tally, array $skipped, int $done, bool $dry_run ): string {
		$parts = array();

		$parts[] = sprintf(
			/* translators: %d: number of actors looked up. */
			_n( '%d actor looked up', '%d actors looked up', $done, 'lwtv' ),
			$done
		);

		if ( ! empty( $tally ) ) {
			$bits = array();
			foreach ( $tally as $status => $count ) {
				$bits[] = $count . ' ' . $status;
			}
			$parts[] = '(' . implode( ', ', $bits ) . ')';
		}

		if ( ! empty( $skipped ) ) {
			arsort( $skipped );
			$bits = array();
			foreach ( array_slice( $skipped, 0, 4, true ) as $reason => $count ) {
				$bits[] = $count . ' ' . $reason;
			}
			$parts[] = sprintf(
				/* translators: 1: total skipped, 2: comma-separated reasons. */
				__( '%1$d skipped: %2$s', 'lwtv' ),
				array_sum( $skipped ),
				implode( ', ', $bits )
			);
		}

		$prefix = $dry_run
			? __( 'Dry run -- nothing written.', 'lwtv' )
			: __( 'Backfill complete.', 'lwtv' );

		return $prefix . ' ' . implode( '. ', $parts ) . '.';
	}

	/**
	 * Map the --letter flag to a Shared_Builder marker.
	 *
	 * @param string $letter Raw flag value.
	 * @return string Marker ('' = no filter, 'a'-'z', '#', '-').
	 */
	private function parse_letter( string $letter ): string {
		$letter = strtolower( trim( $letter ) );

		if ( '' === $letter ) {
			return '';
		}
		if ( in_array( $letter, array( 'num', '#', '0-9' ), true ) ) {
			return '#';
		}
		if ( in_array( $letter, array( 'intl', 'other', '-' ), true ) ) {
			return '-';
		}
		if ( preg_match( '/^[a-z]$/', $letter ) ) {
			return $letter;
		}

		\WP_CLI::error( 'Invalid --letter. Use a-z, num (#), or intl (-).' );
		return '';
	}

	/**
	 * Keep only the actors in one alphabet bucket.
	 *
	 * @param array  $actor_ids Actor post IDs.
	 * @param string $letter    A marker from parse_letter().
	 * @return array
	 */
	private function filter_by_letter( array $actor_ids, string $letter ): array {
		$builder = new Shared_Builder();
		$marker  = ( 1 === strlen( $letter ) && ctype_alpha( $letter ) ) ? strtoupper( $letter ) : $letter;

		return array_values(
			array_filter(
				$actor_ids,
				fn( $id ) => $builder->get_character_marker( get_the_title( $id ) ) === $marker
			)
		);
	}

	/**
	 * Actors that might be worth a lookup, cheaply.
	 *
	 * A superset, not the decision: Qid_Trust::should_check() makes the actual
	 * call per actor. What this excludes is only what SQL can rule out for
	 * certain -- an editor's "stop asking", a hand-set Q-ID, a Q-ID we already
	 * trust, no IMDb meta of any kind to ask with, and (unless --retry-missed)
	 * an actor with no Q-ID we have already asked about without success.
	 *
	 * @param bool $retry_missed Include actors already checked without a match.
	 * @return array<int, int>
	 */
	private function get_candidates( bool $retry_missed ): array {
		global $wpdb;

		// Literals only, all of them keys from Identity and Qid_Trust.
		$has_imdb = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} im"
			. " WHERE im.post_id = p.ID AND im.meta_key IN ( '" . Identity::META_IMDB . "', '" . Identity::META_IMDB_CANONICAL . "' )"
			. " AND im.meta_value != '' )";

		$not_ignored = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ign"
			. " WHERE ign.post_id = p.ID AND ign.meta_key = '" . Identity::META_IGNORE . "'"
			. " AND ign.meta_value != '' AND ign.meta_value != '0' )";

		$no_manual = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} man"
			. " WHERE man.post_id = p.ID AND man.meta_key = '" . Identity::META_QID_MANUAL . "'"
			. " AND man.meta_value != '' )";

		$trusted_sources = "'" . implode( "', '", Qid_Trust::TRUSTED ) . "'";

		// A previous no-match only silences an actor who still has no Q-ID. One
		// that does needs re-checking on its own terms, which is --reverify's
		// business rather than this marker's.
		$checked_clause = $retry_missed ? '' : 'AND ( q.post_id IS NOT NULL OR chk.post_id IS NULL )';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} q ON q.post_id = p.ID AND q.meta_key = %s AND q.meta_value != ''
				 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} chk ON chk.post_id = p.ID AND chk.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND {$has_imdb}
				   AND {$not_ignored}
				   AND {$no_manual}
				   AND ( q.post_id IS NULL OR COALESCE( s.meta_value, '' ) NOT IN ( {$trusted_sources} ) )
				   {$checked_clause}
				 ORDER BY p.post_title ASC",
				Identity::META_QID,
				Identity::META_SOURCE,
				Identity::META_CHECKED,
				CPT_Actors::SLUG
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The counts behind `status`.
	 *
	 * @return array
	 */
	private function status_counts(): array {
		global $wpdb;

		$trusted_sources = "'" . implode( "', '", Qid_Trust::TRUSTED ) . "'";

		$has_imdb = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} im"
			. " WHERE im.post_id = p.ID AND im.meta_key IN ( '" . Identity::META_IMDB . "', '" . Identity::META_IMDB_CANONICAL . "' )"
			. " AND im.meta_value != '' )";

		$manual_set = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} man"
			. " WHERE man.post_id = p.ID AND man.meta_key = '" . Identity::META_QID_MANUAL . "'"
			. " AND man.meta_value != '' )";

		$ignored = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ign"
			. " WHERE ign.post_id = p.ID AND ign.meta_key = '" . Identity::META_IGNORE . "'"
			. " AND ign.meta_value != '' AND ign.meta_value != '0' )";

		$groups = array(
			// Trusted: a hand-set Q-ID, or one we resolved from an IMDb ID.
			'trusted'     => "( {$manual_set} OR ( q.post_id IS NOT NULL AND COALESCE( s.meta_value, '' ) IN ( {$trusted_sources} ) ) )",
			// A Q-ID we hold but cannot vouch for.
			'unverified'  => "( NOT {$manual_set} AND q.post_id IS NOT NULL AND COALESCE( s.meta_value, '' ) NOT IN ( {$trusted_sources} ) )",
			// No Q-ID, but an IMDb ID to find one with.
			'candidates'  => "( NOT {$manual_set} AND q.post_id IS NULL AND {$has_imdb} AND NOT {$ignored} )",
			// No Q-ID and nothing to look one up with.
			'unreachable' => "( NOT {$manual_set} AND q.post_id IS NULL AND NOT {$has_imdb} AND NOT {$ignored} )",
			// An editor has said stop asking.
			'ignored'     => $ignored,
		);

		$counts = array();

		// On the interpolated {$clause}: every string in $groups is composed a
		// few lines up from class constants (Identity::META_*,
		// Qid_Trust::TRUSTED, CPT_Actors::SLUG) and literal SQL. No request
		// data, CLI argument or meta value reaches it, and $groups is a closed
		// map iterated by key, so there is no path for a caller to add one. The
		// three values that *are* dynamic are %s placeholders below. Composing
		// these as placeholders is not possible -- prepare() escapes values, not
		// SQL fragments, and would quote each clause into a string literal.
		foreach ( $groups as $key => $clause ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts[ $key ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
					 LEFT JOIN {$wpdb->postmeta} q ON q.post_id = p.ID AND q.meta_key = %s AND q.meta_value != ''
					 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
					 WHERE p.post_type = %s AND p.post_status = 'publish'
					   AND {$clause}",
					Identity::META_QID,
					Identity::META_SOURCE,
					CPT_Actors::SLUG
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$counts['total'] = (int) ( wp_count_posts( CPT_Actors::SLUG )->publish ?? 0 );

		$counts['breakdown'] = array(
			'trusted Q-ID (usable unattended)' => $counts['trusted'],
			'Q-ID held but unverified'         => $counts['unverified'],
			'no Q-ID, IMDb ID available'       => $counts['candidates'],
			'no Q-ID and no IMDb ID'           => $counts['unreachable'],
			'ignored by an editor'             => $counts['ignored'],
		);

		return $counts;
	}
}

\WP_CLI::add_command( 'lwtv wikidata', 'WP_CLI_LWTV_WikiData' );
