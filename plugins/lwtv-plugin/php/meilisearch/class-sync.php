<?php
/**
 * Name: Sync
 * Description: Keeps the characters index in step with WordPress.
 *
 * Hooks only queue work (Action Scheduler via lwtv_plugin()->schedule_task()),
 * so the search server can never slow or break an editor's save. The queued
 * handlers log failures and return; `wp lwtv meili reindex` repairs any
 * drift, including documents a failed delete left behind.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Characters;
use LWTV\CPTs\Shows;
use LWTV\Meilisearch\Build\Change_Filter;
use LWTV\Relationships\Store;

class Sync {

	/**
	 * Task types; the hooks are lwtv_{type}_task.
	 */
	const CHARACTER_TASK = 'meili_character';
	const FANOUT_TASK    = 'meili_fanout';

	/**
	 * Seconds before a queued sync runs.
	 */
	const DELAY = 5;

	/**
	 * Characters per request when fanning out.
	 */
	const FANOUT_CHUNK = 100;

	private Indexer $indexer;

	/**
	 * @param Indexer $indexer Shared indexer.
	 */
	public function __construct( Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// After the link table's own sync at 20, so fan-outs read fresh links.
		add_action( 'acf/save_post', array( $this, 'on_acf_save' ), 30 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );

		add_action( 'lwtv_' . self::CHARACTER_TASK . '_task', array( $this, 'run_character' ) );
		add_action( 'lwtv_' . self::FANOUT_TASK . '_task', array( $this, 'run_fanout' ) );
	}

	/**
	 * ACF save: a character re-syncs itself; an actor re-syncs its characters,
	 * since its privacy setting is ACF meta that changes without a rename.
	 *
	 * @param int|string $post_id ACF's post ID ('options', 'term_12', ... are skipped).
	 * @return void
	 */
	public function on_acf_save( $post_id ): void {
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$type = get_post_type( $post_id );
		if ( Characters::SLUG === $type ) {
			$this->queue( self::CHARACTER_TASK, $post_id );
		} elseif ( Actors::SLUG === $type ) {
			$this->queue( self::FANOUT_TASK, $post_id );
		}
	}

	/**
	 * A character, show or actor moving on or off the public site (trash,
	 * quick edit, bulk edit, scheduled publish) re-syncs, even without an
	 * ACF save. wp_publish_post() fires this but not post_updated.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition( $new_status, $old_status, $post ): void {
		if ( $post instanceof \WP_Post && Change_Filter::crosses_publish( (string) $old_status, (string) $new_status ) ) {
			$this->queue_for( (string) $post->post_type, (int) $post->ID );
		}
	}

	/**
	 * A deleted character's document is removed when the task runs.
	 *
	 * @param int $post_id Post being deleted.
	 * @return void
	 */
	public function on_delete( $post_id ): void {
		if ( Characters::SLUG === get_post_type( (int) $post_id ) ) {
			$this->queue( self::CHARACTER_TASK, (int) $post_id );
		}
	}

	/**
	 * A rename while published (any path: edit screen, quick edit, REST, CLI)
	 * re-syncs the character, or the characters linked to a show or actor.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post after the update.
	 * @param \WP_Post $post_before Post before the update.
	 * @return void
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ): void {
		if ( ! $post_after instanceof \WP_Post || ! $post_before instanceof \WP_Post ) {
			return;
		}

		if ( Change_Filter::needs_resync( (string) $post_before->post_title, (string) $post_after->post_title, (string) $post_before->post_status, (string) $post_after->post_status ) ) {
			$this->queue_for( (string) $post_after->post_type, (int) $post_id );
		}
	}

	/**
	 * Task: add or remove one character's document.
	 *
	 * @param int $character_id Character post ID.
	 * @return void
	 */
	public function run_character( $character_id ): void {
		try {
			$this->indexer->sync( (int) $character_id );
		} catch ( \Throwable $e ) {
			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Character task %d threw: %s', (int) $character_id, $e->getMessage() ) );
		}
	}

	/**
	 * Task: re-sync every published character linked to a show or actor.
	 *
	 * @param int $post_id Show or actor post ID.
	 * @return void
	 */
	public function run_fanout( $post_id ): void {
		$post_id = (int) $post_id;

		try {
			$type = get_post_type( $post_id );
			if ( Shows::SLUG === $type ) {
				$object_type = 'show';
			} elseif ( Actors::SLUG === $type ) {
				$object_type = 'actor';
			} else {
				return;
			}

			$ids  = Store::characters_for_object( $post_id, $object_type );
			$sent = $this->indexer->sync_many( $ids, self::FANOUT_CHUNK );

			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Fan-out for %s %d: %d of %d character(s) sent.', $object_type, $post_id, $sent, count( $ids ) ) );
		} catch ( \Throwable $e ) {
			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Fan-out task %d threw: %s', $post_id, $e->getMessage() ) );
		}
	}

	/**
	 * Queue the right task for a post type; other post types are ignored.
	 *
	 * @param string $post_type Post type.
	 * @param int    $post_id   Post ID.
	 * @return void
	 */
	private function queue_for( string $post_type, int $post_id ): void {
		if ( Characters::SLUG === $post_type ) {
			$this->queue( self::CHARACTER_TASK, $post_id );
		} elseif ( Shows::SLUG === $post_type || Actors::SLUG === $post_type ) {
			$this->queue( self::FANOUT_TASK, $post_id );
		}
	}

	/**
	 * Queue a task for a post.
	 *
	 * @param string $task    Task type.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function queue( string $task, int $post_id ): void {
		lwtv_plugin()->schedule_task( $task, $post_id, 0, self::DELAY );
	}
}
