<?php
/**
 * WikiData Q-ID Resolution Task
 *
 * Resolves an actor's WikiData Q-ID from their IMDb ID shortly after they are
 * saved, so a newly added actor is identifiable to the death audit without
 * anyone remembering to run a backfill.
 *
 * Runs on Action Scheduler and never during save_post. An HTTP call in a save
 * hook blocks the editor, and save_post fires on autosaves, revisions, bulk
 * edits, REST writes and the cron-driven recalculations -- far more often than
 * "a human edited an actor".
 *
 * Only the exact IMDb statement match (P345) can write here, because nothing
 * reads this task's reasoning before the death audit treats its output as an
 * identity. The name search stays where a human will see its results.
 *
 * The queue is deliberately the same shape as Imdb_Verify_Task's: a transient
 * list of post IDs, drained in batches by a single scheduled action. Two small
 * queues that behave identically are easier to reason about during an incident
 * than one clever shared one.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Wikidata\Build\Qid_Trust;
use LWTV\Wikidata\Identity;

/**
 * Class Wikidata_Qid_Task
 */
class Wikidata_Qid_Task {

	/**
	 * Action Scheduler hook name.
	 */
	const AS_HOOK = 'lwtv_wikidata_qid_task';

	/**
	 * Action Scheduler group name.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Transient holding the queue.
	 */
	const QUEUE = 'lwtv_wikidata_qid_queue';

	/**
	 * Transient holding the per-post error count, keyed by post ID.
	 *
	 * Separate from the queue so a post ID re-queued by a later save starts over
	 * with a clean slate only when we say so, not as a side effect of the queue
	 * being rewritten on every run.
	 */
	const ATTEMPTS = 'lwtv_wikidata_qid_attempts';

	/**
	 * How many actors to resolve per run.
	 */
	const BATCH_SIZE = 25;

	/**
	 * How many consecutive transport failures before a post is dropped.
	 *
	 * The queue reschedules itself every 60 seconds and set_queue() writes a
	 * fresh TTL each time, so the transient's own expiry never fires while the
	 * queue is non-empty. Without a ceiling here, one post WikiData will never
	 * answer for keeps the whole queue alive and re-requests it ~1,400 times a
	 * day. Three strikes is enough to ride out a brief outage or a 429 without
	 * turning a permanent fault into a permanent load.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::AS_HOOK, array( $this, 'process_queue' ) );
	}

	/**
	 * Queue an actor for Q-ID resolution.
	 *
	 * Called from save_post. Does no HTTP: it reads a handful of meta values,
	 * asks Qid_Trust whether a lookup is even worthwhile, and appends to a
	 * transient. Everything expensive happens later.
	 *
	 * @param  int $post_id The post ID.
	 * @return bool Whether the post was queued.
	 */
	public function queue_post( int $post_id ): bool {
		if ( CPT_Actors::SLUG !== get_post_type( $post_id ) ) {
			return false;
		}

		$identity = new Identity();

		// The same decision the CLI backfill makes, from the same rules: an
		// ignored actor, a hand-set Q-ID, one we already trust, or no IMDb ID to
		// ask with all mean there is nothing here worth a request. Without
		// 'reverify' this also leaves inherited Q-IDs alone -- upgrading those
		// is a deliberate bulk pass, not something a save should trigger.
		if ( ! Qid_Trust::should_check( $identity->collect( $post_id ) )['check'] ) {
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
	 * Resolve a batch from the queue (Action Scheduler handler).
	 */
	public function process_queue(): void {
		$queue = $this->get_queue();

		if ( empty( $queue ) ) {
			return;
		}

		$identity  = new Identity();
		$batch     = array_slice( $queue, 0, self::BATCH_SIZE );
		$remaining = array_slice( $queue, self::BATCH_SIZE );
		$attempts  = $this->get_attempts();
		$resolved  = 0;
		$retry     = array();
		$abandoned = array();

		foreach ( $batch as $post_id ) {
			$post_id = (int) $post_id;
			$result  = $identity->resolve_and_record( $post_id );

			if ( in_array( $result['status'], array( 'found', 'confirmed', 'conflict' ), true ) ) {
				++$resolved;
			}

			// A transport failure or a rate limit is not an answer, and unlike
			// the CLI there is no human here to re-run it. Put it back so the
			// next batch tries again; resolve_and_record() wrote no
			// checked-marker, so nothing has been recorded as a no-match.
			//
			// Only genuine faults land here. An ambiguous IMDb ID is an answer
			// and carries its own checked-marker, so it leaves by the front door.
			if ( 'error' === $result['status'] ) {
				$count = ( $attempts[ $post_id ] ?? 0 ) + 1;

				if ( $count >= self::MAX_ATTEMPTS ) {
					// Give up, loudly. No checked-marker is written, so the next
					// save or a `wp lwtv wikidata backfill` still picks this up --
					// we are abandoning the 60-second retry, not the actor.
					$abandoned[] = $post_id;
					unset( $attempts[ $post_id ] );

					lwtv_plugin()->debug_log(
						'wikidata',
						'Gave up on actor ' . $post_id . ' after ' . $count . ' failed lookups: ' . $result['reason']
					);
				} else {
					$attempts[ $post_id ] = $count;
					$retry[]              = $post_id;
				}
			} elseif ( isset( $attempts[ $post_id ] ) ) {
				// It answered. Forget the earlier stumbles.
				unset( $attempts[ $post_id ] );
			}

			$identity->throttle();
		}

		$remaining = array_merge( $remaining, $retry );

		$this->set_queue( $remaining );
		$this->set_attempts( $attempts, $remaining );

		lwtv_plugin()->debug_log(
			'wikidata',
			'Resolved ' . $resolved . ' of ' . count( $batch ) . ' actor(s), ' . count( $retry ) . ' to retry, ' . count( $abandoned ) . ' abandoned, ' . count( $remaining ) . ' still queued'
		);

		if ( ! empty( $remaining ) ) {
			as_schedule_single_action( time() + 60, self::AS_HOOK, array(), self::AS_GROUP );
		}
	}

	/**
	 * Read the queue.
	 *
	 * @return array<int, int>
	 */
	private function get_queue(): array {
		$queue = lwtv_plugin()->get_transient( self::QUEUE );

		return is_array( $queue ) ? array_map( 'intval', $queue ) : array();
	}

	/**
	 * Write the queue.
	 *
	 * @param  array $queue Post IDs.
	 * @return void
	 */
	private function set_queue( array $queue ): void {
		lwtv_plugin()->set_transient( self::QUEUE, array_values( array_unique( $queue ) ), DAY_IN_SECONDS );
	}

	/**
	 * Read the per-post error counts.
	 *
	 * @return array<int, int> Post ID => consecutive failures.
	 */
	private function get_attempts(): array {
		$attempts = lwtv_plugin()->get_transient( self::ATTEMPTS );

		if ( ! is_array( $attempts ) ) {
			return array();
		}

		$clean = array();
		foreach ( $attempts as $post_id => $count ) {
			$clean[ (int) $post_id ] = (int) $count;
		}

		return $clean;
	}

	/**
	 * Write the per-post error counts, keeping only what is still queued.
	 *
	 * Pruning against the queue is what stops this growing without bound. A post
	 * that has left the queue -- resolved, abandoned, or dropped by hand -- has no
	 * use for its old failure count, and if it is queued again later it deserves a
	 * fresh three attempts rather than inheriting a tally from last week.
	 *
	 * @param  array $attempts  Post ID => count.
	 * @param  array $remaining The queue as just written.
	 * @return void
	 */
	private function set_attempts( array $attempts, array $remaining ): void {
		$attempts = array_intersect_key( $attempts, array_flip( array_map( 'intval', $remaining ) ) );

		if ( empty( $attempts ) ) {
			lwtv_plugin()->delete_transient( self::ATTEMPTS );
			return;
		}

		lwtv_plugin()->set_transient( self::ATTEMPTS, $attempts, DAY_IN_SECONDS );
	}

	/**
	 * Queue status, for the scheduler admin screen and CLI.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		$attempts = $this->get_attempts();

		return array(
			'queued'         => count( $this->get_queue() ),
			'retrying'       => count( $attempts ),
			'worst_attempts' => empty( $attempts ) ? 0 : max( $attempts ),
			'next_scheduled' => as_next_scheduled_action( self::AS_HOOK ),
		);
	}
}
