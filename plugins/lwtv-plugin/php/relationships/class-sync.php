<?php
/**
 * Name: Sync
 * Description: Writes a character's show and actor links into the link table.
 *
 * Dual-write period: the shadow taxonomy is still written by
 * LWTV\CPTs\Characters\Calculations. This class only handles the table.
 *
 * @package LWTV
 */

namespace LWTV\Relationships;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Characters;
use LWTV\CPTs\Shows;
use LWTV\Relationships\Build\Link_Rows;

class Sync {

	/**
	 * Link types this class owns. Never 'character': those rows are legacy
	 * imports and must survive a sync.
	 */
	const TYPES = array( 'show', 'actor' );

	/**
	 * Post types whose deletion can leave link rows behind.
	 */
	const DELETE_POST_TYPES = array( Characters::SLUG, Shows::SLUG, Actors::SLUG );

	public function __construct() {
		add_action( 'acf/save_post', array( $this, 'on_acf_save' ), 20 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
	}

	/**
	 * Rewrite a character's show and actor rows from its ACF fields.
	 *
	 * Runs even when both fields are empty, so removed links are deleted.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return void
	 */
	public static function character( int $character_id ): void {
		$rows = Link_Rows::from_fields(
			$character_id,
			get_field( 'lezchars_show_group', $character_id ),
			get_field( 'lezchars_actor', $character_id )
		);

		Store::replace_for_character( $character_id, $rows, self::TYPES );
	}

	/**
	 * ACF save hook. ACF also passes 'options', 'term_12', 'user_3' and so on.
	 *
	 * @param int|string $post_id ACF's post ID.
	 *
	 * @return void
	 */
	public function on_acf_save( $post_id ): void {
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;

		if ( Characters::SLUG !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		self::character( $post_id );
	}

	/**
	 * Drop a deleted post's rows, on either side of a link.
	 *
	 * Other post types are skipped: they can't be in the table, and this hook
	 * fires for every post deletion.
	 *
	 * @param int $post_id Post being deleted.
	 *
	 * @return void
	 */
	public function on_delete( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), self::DELETE_POST_TYPES, true ) ) {
			return;
		}

		Store::delete_for_post( $post_id );
	}
}
