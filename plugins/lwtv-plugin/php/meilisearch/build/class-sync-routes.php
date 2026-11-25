<?php
/**
 * Name: Sync Routes
 * Description: Which indexes a post change queues work for.
 *
 * Pure. Each queued index then works out, via Index::affected_by(), which of
 * its own documents the change touches. A routine show save only touches the
 * show's document: character documents quote a show's title and visibility,
 * which the `changed` event already covers.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sync_Routes {

	/**
	 * Events: an ACF save, a rename while published or a move across publish,
	 * a delete, and a show score recalculation.
	 */
	const EVENTS = array( 'acf_save', 'changed', 'delete', 'score' );

	/**
	 * Event => post type => index types, in registry order.
	 */
	const ROUTES = array(
		'acf_save' => array(
			'post_type_characters' => array( 'characters', 'shows' ),
			'post_type_shows'      => array( 'shows' ),
			'post_type_actors'     => array( 'characters', 'shows' ),
		),
		'changed'  => array(
			'post_type_characters' => array( 'characters', 'shows' ),
			'post_type_shows'      => array( 'characters', 'shows' ),
			'post_type_actors'     => array( 'characters', 'shows' ),
		),
		'delete'   => array(
			'post_type_characters' => array( 'characters' ),
			'post_type_shows'      => array( 'shows' ),
		),
		'score'    => array(
			'post_type_shows' => array( 'shows' ),
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
