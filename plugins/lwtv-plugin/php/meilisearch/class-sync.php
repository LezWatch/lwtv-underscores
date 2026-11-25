<?php
/**
 * Name: Sync
 * Description: Keeps every index in step with WordPress.
 *
 * Hooks only queue work (Action Scheduler via lwtv_plugin()->schedule_task()),
 * so the search server can never slow or break an editor's save. Which
 * indexes an event touches is Build\Sync_Routes; which documents in each is
 * that index's affected_by(), worked out when the task runs. The queued
 * handlers log failures and return; `wp lwtv meili reindex` repairs drift.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Characters;
use LWTV\Meilisearch\Build\Change_Filter;
use LWTV\Meilisearch\Build\Sync_Routes;
use LWTV\Relationships\Store;

class Sync {

	/**
	 * Task type prefix; hooks are lwtv_meili_{index type}_task.
	 */
	const TASK_PREFIX = 'meili_';

	/**
	 * Seconds before a queued sync runs.
	 */
	const DELAY = 5;

	private Indexes $indexes;

	/**
	 * Shows each character was on before this request's ACF save.
	 *
	 * @var array<int, int[]>
	 */
	private array $shows_before = array();

	/**
	 * Actors each character was played by before this request's ACF save.
	 *
	 * @var array<int, int[]>
	 */
	private array $actors_before = array();

	/**
	 * @param Indexes $indexes Every index.
	 */
	public function __construct( Indexes $indexes ) {
		$this->indexes = $indexes;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Before ACF and the link table (20) write, to see a character's old shows.
		add_action( 'acf/save_post', array( $this, 'before_acf_save' ), 5 );
		// After the link table's own sync at 20, so tasks read fresh links.
		add_action( 'acf/save_post', array( $this, 'on_acf_save' ), 30 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		// Before the link table deletes the post's rows at 10.
		add_action( 'before_delete_post', array( $this, 'on_delete' ), 5 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'lwtv_show_score_updated', array( $this, 'on_score_updated' ) );
		add_action( 'lwtv_actor_calculated', array( $this, 'on_actor_calculated' ) );

		foreach ( $this->indexes->all() as $type => $index ) {
			add_action( 'lwtv_' . self::TASK_PREFIX . $type . '_task', array( $index, 'run' ) );
		}
	}

	/**
	 * Remember a character's shows and actors before the save changes them,
	 * so a show the character leaves, or an actor no longer linked, drops
	 * its name too.
	 *
	 * @param int|string $post_id ACF's post ID.
	 * @return void
	 */
	public function before_acf_save( $post_id ): void {
		$post_id = $this->acf_post_id( $post_id );
		if ( $post_id && Characters::SLUG === get_post_type( $post_id ) ) {
			$this->shows_before[ $post_id ]  = Store::objects_for_character( $post_id, 'show' );
			$this->actors_before[ $post_id ] = Store::objects_for_character( $post_id, 'actor' );
		}
	}

	/**
	 * ACF save: queue every index the post type routes to, plus any show a
	 * character just left and any actor it was linked to.
	 *
	 * @param int|string $post_id ACF's post ID ('options', 'term_12', ... are skipped).
	 * @return void
	 */
	public function on_acf_save( $post_id ): void {
		$post_id = $this->acf_post_id( $post_id );
		if ( ! $post_id ) {
			return;
		}

		$this->route( 'acf_save', (string) get_post_type( $post_id ), $post_id );

		foreach ( $this->shows_before[ $post_id ] ?? array() as $show_id ) {
			$this->queue( Shows_Index::TYPE, $show_id );
		}
		unset( $this->shows_before[ $post_id ] );

		foreach ( $this->actors_before[ $post_id ] ?? array() as $actor_id ) {
			$this->queue( Actors_Index::TYPE, $actor_id );
		}
		unset( $this->actors_before[ $post_id ] );
	}

	/**
	 * A post moving on or off the public site (trash, quick edit, bulk edit,
	 * scheduled publish). wp_publish_post() fires this but not post_updated.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition( $new_status, $old_status, $post ): void {
		if ( $post instanceof \WP_Post && Change_Filter::crosses_publish( (string) $old_status, (string) $new_status ) ) {
			$this->route( 'changed', (string) $post->post_type, (int) $post->ID );
		}
	}

	/**
	 * A deleted post's document goes when its task runs. A character's shows
	 * and actors are read now, while its link rows still exist.
	 *
	 * @param int $post_id Post being deleted.
	 * @return void
	 */
	public function on_delete( $post_id ): void {
		$post_id   = (int) $post_id;
		$post_type = (string) get_post_type( $post_id );

		$this->route( 'delete', $post_type, $post_id );

		if ( Characters::SLUG === $post_type ) {
			foreach ( Store::objects_for_character( $post_id, 'show' ) as $show_id ) {
				$this->queue( Shows_Index::TYPE, $show_id );
			}
			foreach ( Store::objects_for_character( $post_id, 'actor' ) as $actor_id ) {
				$this->queue( Actors_Index::TYPE, $actor_id );
			}
		}
	}

	/**
	 * A rename while published (any path: edit screen, quick edit, REST, CLI).
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
			$this->route( 'changed', (string) $post_after->post_type, (int) $post_id );
		}
	}

	/**
	 * A show's score was recalculated (save, calculation task, wp lwtv calc).
	 *
	 * @param int $post_id Show post ID.
	 * @return void
	 */
	public function on_score_updated( $post_id ): void {
		$post_id = (int) $post_id;
		$this->route( 'score', (string) get_post_type( $post_id ), $post_id );
	}

	/**
	 * An actor's counts and queer flag were recalculated: the actors index's
	 * tie-breakers, written after the save that triggered them.
	 *
	 * @param int $post_id Actor post ID.
	 * @return void
	 */
	public function on_actor_calculated( $post_id ): void {
		$post_id = (int) $post_id;
		$this->route( 'calculated', (string) get_post_type( $post_id ), $post_id );
	}

	/**
	 * Queue one task per index an event routes to.
	 *
	 * @param string $event     Sync_Routes event.
	 * @param string $post_type Post type.
	 * @param int    $post_id   Post ID.
	 * @return void
	 */
	private function route( string $event, string $post_type, int $post_id ): void {
		foreach ( Sync_Routes::for_event( $event, $post_type ) as $type ) {
			$this->queue( $type, $post_id );
		}
	}

	/**
	 * A real post ID from ACF's save hook, or 0.
	 *
	 * @param int|string $post_id ACF's post ID.
	 * @return int
	 */
	private function acf_post_id( $post_id ): int {
		if ( ! is_numeric( $post_id ) ) {
			return 0;
		}

		$post_id = (int) $post_id;

		return ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ? 0 : $post_id;
	}

	/**
	 * Queue an index's task for a post (one pending task per post per index).
	 *
	 * @param string $type    Index type.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function queue( string $type, int $post_id ): void {
		if ( $post_id > 0 && null !== $this->indexes->get( $type ) ) {
			lwtv_plugin()->schedule_task( self::TASK_PREFIX . $type, $post_id, 0, self::DELAY );
		}
	}
}
