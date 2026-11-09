<?php
/**
 * Facet Re-index Task Handler
 *
 * Re-indexes the characters attached to an actor or show when it is renamed,
 * so their facet rows stop quoting the old title.
 * See docs/architecture/facetwp-indexing.md.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Relationships\Character_Links;

/**
 * Class Facet_Reindex_Task
 */
class Facet_Reindex_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register Action Scheduler / cron hook.
		add_action( 'lwtv_facet_reindex_task', array( $this, 'process_facet_reindex_task' ) );
	}

	/**
	 * Re-index every character attached to this post.
	 *
	 * @param int $post_id The actor or show post ID whose characters need re-indexing.
	 * @return void
	 */
	public function process_facet_reindex_task( int $post_id ): void {
		if ( ! function_exists( 'FWP' ) || ! isset( FWP()->indexer ) ) {
			lwtv_plugin()->debug_log( 'facetwp', "FacetWP unavailable; skipping character re-index for ID: {$post_id}" );
			return;
		}

		$character_ids = $this->get_character_ids( $post_id );

		if ( empty( $character_ids ) ) {
			lwtv_plugin()->debug_log( 'facetwp', "No characters found to re-index for ID: {$post_id}" );
			return;
		}

		foreach ( $character_ids as $character_id ) {
			FWP()->indexer->index( $character_id );
		}

		lwtv_plugin()->debug_log( 'facetwp', 'Re-indexed ' . count( $character_ids ) . " character(s) for ID: {$post_id}" );
	}

	/**
	 * Get the character IDs attached to an actor or show.
	 *
	 * Characters own the relationship; Character_Links answers the reverse
	 * lookup for both shows and actors.
	 *
	 * @param int $post_id The actor or show post ID.
	 * @return int[] Character post IDs.
	 */
	private function get_character_ids( int $post_id ): array {
		$links = new Character_Links();

		$candidates = match ( get_post_type( $post_id ) ) {
			CPT_Shows::SLUG  => $links->characters_for_show( $post_id ),
			CPT_Actors::SLUG => $links->characters_for_actor( $post_id ),
			default          => array(),
		};

		$character_ids = array();

		foreach ( $candidates as $character_id ) {
			if ( CPT_Characters::SLUG === get_post_type( $character_id ) ) {
				$character_ids[] = $character_id;
			}
		}

		return array_values( array_unique( $character_ids ) );
	}
}
