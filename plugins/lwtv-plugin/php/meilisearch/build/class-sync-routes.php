<?php
/**
 * Name: Sync Routes
 * Description: Which indexes a post change queues work for.
 *
 * Pure. Each queued index then works out, via Index::affected_by(), which of
 * its own documents the change touches. A routine show save only touches the
 * show's document: character and actor documents quote a show's title and
 * visibility, which the `changed` event already covers.
 *
 * Posts and pages aren't ACF-driven, so they use `save` (save_post) instead
 * of `acf_save`. Every save counts, since the content index holds the body.
 * Content documents quote no other post, and no other index quotes a post
 * or page, so their routes only ever reach `content`.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sync_Routes {

	/**
	 * Events: an ACF save, a post or page save, a rename while published or a move across publish,
	 * a delete, a show score recalculation, and an actor recalculation (the
	 * queer flag and character count the actors index ranks ties by).
	 */
	const EVENTS = array( 'acf_save', 'save', 'changed', 'delete', 'score', 'calculated' );

	/**
	 * Event => post type => index types, in registry order.
	 */
	const ROUTES = array(
		'acf_save'   => array(
			'post_type_characters' => array( 'characters', 'shows', 'actors' ),
			'post_type_shows'      => array( 'shows' ),
			'post_type_actors'     => array( 'characters', 'shows', 'actors' ),
		),
		'save'       => array(
			'post' => array( 'content' ),
			'page' => array( 'content' ),
		),
		'changed'    => array(
			'post_type_characters' => array( 'characters', 'shows', 'actors' ),
			'post_type_shows'      => array( 'characters', 'shows', 'actors' ),
			'post_type_actors'     => array( 'characters', 'shows', 'actors' ),
			'post'                 => array( 'content' ),
			'page'                 => array( 'content' ),
		),
		'delete'     => array(
			'post_type_characters' => array( 'characters' ),
			'post_type_shows'      => array( 'shows' ),
			'post_type_actors'     => array( 'actors' ),
			'post'                 => array( 'content' ),
			'page'                 => array( 'content' ),
		),
		'score'      => array(
			'post_type_shows' => array( 'shows' ),
		),
		'calculated' => array(
			'post_type_actors' => array( 'actors' ),
		),
	);

	/**
	 * Index types to queue for an event on a post type.
	 *
	 * @param string $event     One of EVENTS.
	 * @param string $post_type Post type slug.
	 * @return string[]
	 */
	public static function for_event( string $event, string $post_type ): array {
		return self::ROUTES[ $event ][ $post_type ] ?? array();
	}
}
