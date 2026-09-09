<?php
/**
 * Facet Re-index Task Handler
 *
 * Re-indexes the characters attached to an actor or a show.
 *
 * The FacetWP index stores a display value per row, and for the character
 * facets that value is another post's title, resolved at the moment the
 * *character* is indexed (see Plugins\FacetWP\Indexing). Renaming an actor or
 * a show therefore leaves every character that references it holding the old
 * name, and nothing in the normal save path re-indexes those characters --
 * Calculation_Task only re-indexes the post it just calculated.
 *
 * A rename fans out to every character on that actor or show, so this runs
 * deferred rather than inline on save.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Characters as CPT_Characters;

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
	 * Characters own the relationship, and their shadow terms are what get
	 * attached to actors and shows, so the shadow taxonomy is the lookup in
	 * both directions.
	 *
	 * @param int $post_id The actor or show post ID.
	 * @return int[] Character post IDs.
	 */
	private function get_character_ids( int $post_id ): array {
		$terms = get_the_terms( $post_id, CPT_Characters::SHADOW_TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			lwtv_plugin()->debug_log( 'facetwp', "No shadow character terms on ID: {$post_id}; cannot re-index its characters" );
			return array();
		}

		$character_ids = array();

		foreach ( $terms as $term ) {
			$character_id = (int) \Shadow_Taxonomy\Core\get_associated_post_id( $term );

			if ( $character_id > 0 && CPT_Characters::SLUG === get_post_type( $character_id ) ) {
				$character_ids[] = $character_id;
			}
		}

		return array_unique( $character_ids );
	}
}
