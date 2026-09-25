<?php
/**
 * New Post
 *
 * Announces new blog posts (trigger `new_posts`) and new shows (trigger
 * `new_shows`) through Postiz, once each.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Postiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Airdates;
use LWTV\Postiz\Build\Show_Announcement;

class New_Post extends Postiz {

	/**
	 * Post meta marking a post or show as announced (Unix time). Doubles as the
	 * lock: add_post_meta( ..., unique ) fails if another request got there first.
	 */
	const ANNOUNCED_META = 'lwtv_postiz_announced';

	/**
	 * schedule_task() type for show announcements (hook `lwtv_postiz_show_task`).
	 */
	const SHOW_TASK = 'postiz_show';

	/**
	 * Show post type.
	 */
	const SHOW_TYPE = 'post_type_shows';

	/**
	 * Constructor - register hooks for the enabled triggers
	 */
	public function __construct() {
		parent::__construct();

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( $this->is_type_triggered_enabled( 'new_posts' ) ) {
			// Not publish_post: that fires on every save of a published post.
			add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );
		}

		if ( $this->is_type_triggered_enabled( 'new_shows' ) ) {
			add_action( 'transition_post_status', array( $this, 'handle_show_transition' ), 10, 3 );

			// Characters usually arrive after the show is published, and the
			// calculation task writes the count; catch it there.
			add_action( 'added_post_meta', array( $this, 'handle_char_count' ), 10, 4 );
			add_action( 'updated_post_meta', array( $this, 'handle_char_count' ), 10, 4 );

			add_action( 'lwtv_' . self::SHOW_TASK . '_task', array( $this, 'announce_show' ) );
		}
	}

	/**
	 * Announce a blog post on its first publish only.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function handle_post_transition( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$this->handle_new_post_added( $post->ID );
	}

	/**
	 * Post a blog post to Postiz, once.
	 *
	 * @param int $post_id The post ID
	 * @return array|\WP_Error|null Null when it was already announced.
	 */
	public function handle_new_post_added( $post_id ) {
		if ( ! add_post_meta( $post_id, self::ANNOUNCED_META, time(), true ) ) {
			parent::log_new_post_message( 'Already announced. Skipping', $post_id );
			return null;
		}

		$result = $this->post_new_post( $this->get_post_content( $post_id ), $post_id );

		$this->record_result( $result, $post_id, 'New post' );

		return $result;
	}

	/**
	 * A show was published: announce it if it already has characters.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function handle_show_transition( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || self::SHOW_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$this->maybe_queue_show( $post->ID );
	}

	/**
	 * A show's character count was written: announce it if it is now eligible.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	public function handle_char_count( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( 'lezshows_char_count' !== $meta_key || (int) $meta_value < 1 ) {
			return;
		}

		$this->maybe_queue_show( (int) $object_id );
	}

	/**
	 * Queue the announcement so the Postiz request stays out of the save.
	 *
	 * @param int $show_id Show ID.
	 * @return void
	 */
	private function maybe_queue_show( int $show_id ): void {
		if ( ! Show_Announcement::should_announce( $this->show_state( $show_id ), time() ) ) {
			return;
		}

		lwtv_plugin()->schedule_task( self::SHOW_TASK, $show_id );
	}

	/**
	 * Background task: re-check, then announce the show.
	 *
	 * @param int $show_id Show ID.
	 * @return void
	 */
	public function announce_show( $show_id ): void {
		$show_id = (int) $show_id;

		// Re-checked: the show may have been unpublished or announced since.
		if ( ! Show_Announcement::should_announce( $this->show_state( $show_id ), time() ) ) {
			return;
		}

		if ( ! add_post_meta( $show_id, self::ANNOUNCED_META, time(), true ) ) {
			return;
		}

		$result = $this->post_new_post( $this->get_show_content( $show_id ), $show_id );

		$this->record_result( $result, $show_id, 'New show' );
	}

	/**
	 * Inputs for Show_Announcement::should_announce().
	 *
	 * @param int $show_id Show ID.
	 * @return array
	 */
	private function show_state( int $show_id ): array {
		$is_show = self::SHOW_TYPE === get_post_type( $show_id );

		return array(
			'status'       => $is_show ? (string) get_post_status( $show_id ) : '',
			'char_count'   => (int) get_post_meta( $show_id, 'lezshows_char_count', true ),
			'announced'    => '' !== (string) get_post_meta( $show_id, self::ANNOUNCED_META, true ),
			'published_at' => (int) get_post_time( 'U', true, $show_id ),
		);
	}

	/**
	 * Log the outcome; on failure, release the lock so a later event can retry.
	 *
	 * @param array|\WP_Error $result  Postiz response.
	 * @param int             $post_id Post ID.
	 * @param string          $label   'New post' or 'New show'.
	 * @return void
	 */
	private function record_result( $result, int $post_id, string $label ): void {
		if ( is_wp_error( $result ) ) {
			delete_post_meta( $post_id, self::ANNOUNCED_META );
			parent::log_new_post_message( $label . ' failed to post to Postiz: ' . $result->get_error_message(), $post_id );
			return;
		}

		parent::log_new_post_message( $label . ' posted to Postiz', $post_id );
	}

	/**
	 * Get the content for the new post
	 *
	 * @param int $post_id The post ID
	 * @return string The content
	 */
	private function get_post_content( $post_id ) {
		$content = get_the_excerpt( $post_id );

		if ( empty( $content ) ) {
			$content = get_the_title( $post_id );
		}

		// Bluesky posts have a 300-character limit
		if ( strlen( $content ) > 300 ) {
			$content = substr( $content, 0, 296 ) . ' ...';
		}

		return $content;
	}

	/**
	 * The announcement text for a show. See Show_Announcement::content().
	 *
	 * @param int $show_id Show ID.
	 * @return string
	 */
	private function get_show_content( int $show_id ): string {
		$name     = html_entity_decode( get_the_title( $show_id ), ENT_QUOTES, 'UTF-8' );
		$stations = wp_get_post_terms( $show_id, 'lez_stations', array( 'fields' => 'names' ) );
		$stations = is_wp_error( $stations ) ? array() : $stations;
		$year     = Show_Announcement::year( Airdates::get( $show_id )['start'] );
		$hashtag  = Show_Announcement::hashtag( sanitize_title( Show_Announcement::clean_title( $name ) ) );

		return Show_Announcement::content( $name, $stations, $year, $hashtag, (string) get_permalink( $show_id ) );
	}

	/**
	 * Post "New Post" content to Postiz
	 *
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @return array|\WP_Error Response array or WP_Error on failure
	 */
	public function post_new_post( $content, $post_id ) {
		$post_type = get_post_type( $post_id );

		$options = array(
			'group'     => $post_type . '_' . $post_id,
			'image'     => parent::get_images( $post_id ),
			'tags'      => parent::get_tags( 'post', $post_id ),
			'shortLink' => false,
		);

		return parent::create_post( $content, $options );
	}
}
