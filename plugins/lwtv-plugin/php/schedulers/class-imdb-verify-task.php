<?php
/**
 * IMDb Verification Task
 *
 * Detects IMDb IDs that have gone stale.
 *
 * IMDb reassigns title and name IDs and leaves the previous one working as a
 * redirect, so a stale ID still opens the right page in a browser while silently
 * breaking every exact-match API lookup keyed on it. Nothing about the value
 * looks wrong, which is why Debug_Tool::validate_imdb() cannot catch it: it is
 * well-formed, it is the right prefix, and it works when clicked.
 *
 * Detection therefore cannot come from IMDb -- automated requests there get
 * blocked, and a check that silently reports "fine" for everything is worse than
 * no check. It comes instead from third parties that store a canonical IMDb ID
 * and whose IDs we already hold:
 *
 *   shows  -> TVMaze /shows/{id}, externals.imdb
 *   actors -> TMDB /person/{id}, imdb_id  (via _Components\CPTs::get_tmdb_info)
 *
 * TVMaze is a particularly good oracle for shows here: it carries television
 * only, exactly like this site, so when it disagrees with us its ID is the one
 * guaranteed to point at a TV entity rather than a film.
 *
 * Runs on Action Scheduler and never during save_post. An HTTP call in a save
 * hook blocks the editor, and save_post fires on autosaves, revisions, bulk
 * edits, REST writes and the cron-driven recalculations -- far more often than
 * "a human edited a show".
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\CPTs;
use LWTV\_Helpers\Imdb_Canonical;

/**
 * Class Imdb_Verify_Task
 */
class Imdb_Verify_Task {

	/**
	 * Action Scheduler hook name.
	 */
	const AS_HOOK = 'lwtv_imdb_verify_task';

	/**
	 * Action Scheduler group name.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * TV Maze API URL.
	 */
	const TVMAZE_URL = 'https://api.tvmaze.com';

	/**
	 * How many posts to verify per run.
	 */
	const BATCH_SIZE = 25;

	/**
	 * Pause between requests, in microseconds.
	 *
	 * 500ms, sized for TVMaze's documented "at least 20 calls every 10 seconds"
	 * rather than TMDB's more generous allowance, because a mixed queue could be
	 * all shows. A single conservative delay beats two rate-limit budgets for a
	 * background job nobody is waiting on.
	 */
	const DELAY_US = 500000;

	/**
	 * Per-post-type configuration.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function types(): array {
		return array(
			'post_type_shows'  => array(
				'imdb'      => 'lezshows_imdb',
				'canonical' => 'lezshows_imdb_canonical',
				'oracle_id' => 'lezshows_tvmaze_id',
				'ignore'    => 'lezshows_tvmaze_ignore',
			),
			'post_type_actors' => array(
				'imdb'      => 'lezactors_imdb',
				'canonical' => 'lezactors_imdb_canonical',
				'oracle_id' => 'lezactors_tmdb_id',
				'ignore'    => '',
			),
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::AS_HOOK, array( $this, 'process_queue' ) );
	}

	/**
	 * Queue a post for verification.
	 *
	 * Called from save_post. Does no HTTP and makes no judgement -- it only
	 * decides whether asking is worthwhile, then schedules.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool Whether the post was queued.
	 */
	public function queue_post( int $post_id ): bool {
		$config = $this->types()[ get_post_type( $post_id ) ] ?? null;

		if ( null === $config ) {
			return false;
		}

		// Nothing to compare, or nothing to compare against.
		if ( '' === Imdb_Canonical::normalise( get_post_meta( $post_id, $config['imdb'], true ) ) ) {
			return false;
		}

		if ( empty( get_post_meta( $post_id, $config['oracle_id'], true ) ) ) {
			return false;
		}

		// An editor has said not to chase this show's third-party match.
		if ( '' !== $config['ignore'] && ! empty( get_post_meta( $post_id, $config['ignore'], true ) ) ) {
			return false;
		}

		$queue = $this->get_queue();

		if ( in_array( $post_id, $queue, true ) ) {
			return false;
		}

		$queue[] = $post_id;
		$this->set_queue( $queue );

		if ( ! as_next_scheduled_action( self::AS_HOOK ) ) {
			as_schedule_single_action( time() + 60, self::AS_HOOK, array(), self::AS_GROUP );
		}

		return true;
	}

	/**
	 * Verify a batch from the queue (Action Scheduler handler).
	 */
	public function process_queue(): void {
		$queue = $this->get_queue();

		if ( empty( $queue ) ) {
			return;
		}

		$batch     = array_slice( $queue, 0, self::BATCH_SIZE );
		$remaining = array_slice( $queue, self::BATCH_SIZE );
		$stale     = 0;

		foreach ( $batch as $post_id ) {
			if ( Imdb_Canonical::STALE === $this->verify( (int) $post_id ) ) {
				++$stale;
			}

			usleep( self::DELAY_US );
		}

		$this->set_queue( $remaining );

		lwtv_plugin()->debug_log(
			'imdb-verify',
			'Verified ' . count( $batch ) . ' post(s), ' . $stale . ' stale, ' . count( $remaining ) . ' still queued'
		);

		if ( ! empty( $remaining ) ) {
			as_schedule_single_action( time() + 60, self::AS_HOOK, array(), self::AS_GROUP );
		}
	}

	/**
	 * Verify one post against its oracle.
	 *
	 * Returns the verdict rather than a boolean so callers can report on the
	 * distinction between "the oracle agrees" and "the oracle had nothing to say"
	 * -- both leave the meta clear, but only one of them means anything.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $dry_run Compute the verdict without writing meta.
	 *
	 * @return string An Imdb_Canonical verdict, or 'unreachable' when the oracle
	 *                could not be asked at all.
	 */
	public function verify( int $post_id, bool $dry_run = false ): string {
		$config = $this->types()[ get_post_type( $post_id ) ] ?? null;

		if ( null === $config ) {
			return 'unreachable';
		}

		$ours   = get_post_meta( $post_id, $config['imdb'], true );
		$theirs = ( 'post_type_shows' === get_post_type( $post_id ) )
			? $this->tvmaze_imdb( $post_id )
			: $this->tmdb_imdb( $post_id );

		// A transport failure is not a verdict. Leaving the stored value alone
		// means an outage cannot clear a real flag, nor invent one.
		if ( null === $theirs ) {
			return 'unreachable';
		}

		$verdict = Imdb_Canonical::verdict( $ours, $theirs );

		if ( $dry_run ) {
			return $verdict;
		}

		if ( Imdb_Canonical::STALE === $verdict ) {
			update_post_meta( $post_id, $config['canonical'], Imdb_Canonical::normalise( $theirs ) );
			return $verdict;
		}

		// Match, no-oracle, or nothing of ours to check: clear any previous
		// finding. This is what makes a corrected ID stop being reported without
		// anyone having to clean up after themselves.
		delete_post_meta( $post_id, $config['canonical'] );

		return $verdict;
	}

	/**
	 * The meta keys this task reads and writes, per post type.
	 *
	 * Exposed so the CLI can build candidate queries against the same keys rather
	 * than repeating them and drifting.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function config(): array {
		return $this->types();
	}

	/**
	 * The IMDb ID TVMaze holds for a show.
	 *
	 * @param int $post_id Show post ID.
	 *
	 * @return string|null Null on transport failure, '' when TVMaze has no link.
	 */
	private function tvmaze_imdb( int $post_id ): ?string {
		$tvmaze_id = (int) get_post_meta( $post_id, 'lezshows_tvmaze_id', true );

		if ( $tvmaze_id < 1 ) {
			return null;
		}

		$response = wp_remote_get(
			self::TVMAZE_URL . '/shows/' . $tvmaze_id,
			array(
				'user-agent' => 'LezWatch.TV IMDb verification (+https://lezwatchtv.com)',
				'timeout'    => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return null;
		}

		// Present but null means TVMaze has the show and no IMDb link, which is a
		// real answer: '' rather than null.
		return (string) ( $body['externals']['imdb'] ?? '' );
	}

	/**
	 * The IMDb ID TMDB holds for an actor.
	 *
	 * Reuses _Components\CPTs::get_tmdb_info(), which already handles the API key
	 * check, the /person/{id} shape and TMDB's status_message errors.
	 *
	 * @param int $post_id Actor post ID.
	 *
	 * @return string|null Null on failure, '' when TMDB has no link.
	 */
	private function tmdb_imdb( int $post_id ): ?string {
		$info = ( new CPTs() )->get_tmdb_info( $post_id );

		if ( ! is_array( $info ) ) {
			return null;
		}

		return (string) ( $info['imdb_id'] ?? '' );
	}

	/**
	 * Read the queue.
	 *
	 * @return array<int, int>
	 */
	private function get_queue(): array {
		$queue = lwtv_plugin()->get_transient( 'lwtv_imdb_verify_queue' );

		return is_array( $queue ) ? array_map( 'intval', $queue ) : array();
	}

	/**
	 * Write the queue.
	 *
	 * @param array $queue Post IDs.
	 */
	private function set_queue( array $queue ): void {
		lwtv_plugin()->set_transient( 'lwtv_imdb_verify_queue', array_values( array_unique( $queue ) ), DAY_IN_SECONDS );
	}

	/**
	 * Queue status, for the scheduler admin screen.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		return array(
			'queued'         => count( $this->get_queue() ),
			'next_scheduled' => as_next_scheduled_action( self::AS_HOOK ),
		);
	}
}
