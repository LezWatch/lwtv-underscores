<?php
/**
 * IMDb Verification Task
 *
 * Detects stale (still-redirecting) IMDb IDs by asking TVMaze (shows) and TMDB
 * (actors), on Action Scheduler, never in save_post. See
 * docs/integrations/imdb.md#imdb_verify_task and
 * docs/architecture/scheduling.md#no-http-in-save_post.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\CPTs;
use LWTV\_Helpers\Imdb_Canonical;
use LWTV\_Helpers\Tmdb_Response;

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
	 * 500ms, sized for TVMaze rather than TMDB. See
	 * docs/integrations/tvmaze.md#rate-limits.
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
				'imdb'           => 'lezshows_imdb',
				'canonical'      => 'lezshows_imdb_canonical',
				'oracle_id'      => 'lezshows_tvmaze_id',
				'no_oracle_meta' => 'lezshows_tvmaze_ignore',
			),
			'post_type_actors' => array(
				'imdb'           => 'lezactors_imdb',
				'canonical'      => 'lezactors_imdb_canonical',
				'oracle_id'      => 'lezactors_tmdb_id',
				'no_oracle_meta' => '',
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
		if ( '' !== $config['no_oracle_meta'] && ! empty( get_post_meta( $post_id, $config['no_oracle_meta'], true ) ) ) {
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
	 * Bails without a TMDB ID: the /find/ fallback carries no imdb_id. Checked
	 * here too because the CLI and debugger call verify() directly.
	 *
	 * @param int $post_id Actor post ID.
	 *
	 * @return string|null Null when TMDB could not be asked, '' when it has the
	 *                     person and no IMDb link.
	 */
	private function tmdb_imdb( int $post_id ): ?string {
		if ( empty( get_post_meta( $post_id, 'lezactors_tmdb_id', true ) ) ) {
			return null;
		}

		// Null (shape cannot answer) must stay distinct from '' (no link).
		return Tmdb_Response::imdb_id( ( new CPTs() )->get_tmdb_info( $post_id ) );
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
