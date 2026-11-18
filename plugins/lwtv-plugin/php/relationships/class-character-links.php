<?php
/**
 * Name: Character Links
 * Description: The one place that answers "which characters link to what".
 *
 * Every reader of character ↔ show/actor/character links goes through this
 * class. It reads the lwtv_char_links table (Store), which returns published
 * posts only.
 *
 * @package LWTV
 */

namespace LWTV\Relationships;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Characters;

class Character_Links {

	/**
	 * Term meta on a shadow term that holds its character's post ID.
	 *
	 * @deprecated 7.5.0 Only the legacy import uses it. Remove in Phase 6.
	 */
	const TERM_POST_META = 'shadow_shadow_tax_characters_post_id';

	/**
	 * Post meta on a character that holds its shadow term ID.
	 *
	 * @deprecated 7.5.0 Only the legacy import uses it. Remove in Phase 6.
	 */
	const POST_TERM_META = 'shadow_shadow_tax_characters_term_id';

	/**
	 * Characters that list a show.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return int[] Character post IDs.
	 */
	public function characters_for_show( int $show_id ): array {
		return Store::characters_for_object( $show_id, 'show' );
	}

	/**
	 * Characters that list an actor.
	 *
	 * @param int $actor_id Actor post ID.
	 *
	 * @return int[] Character post IDs.
	 */
	public function characters_for_actor( int $actor_id ): array {
		return Store::characters_for_object( $actor_id, 'actor' );
	}

	/**
	 * Shows a character is linked to.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Show post IDs.
	 */
	public function shows_for_character( int $character_id ): array {
		return Store::objects_for_character( $character_id, 'show' );
	}

	/**
	 * Actors a character is linked to.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Actor post IDs.
	 */
	public function actors_for_character( int $character_id ): array {
		return Store::objects_for_character( $character_id, 'actor' );
	}

	/**
	 * Characters linked to a character in either direction (legacy data).
	 *
	 * The character itself is never included.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Character post IDs, sorted.
	 */
	public function characters_referencing( int $character_id ): array {
		return Store::characters_linked_to_character( $character_id );
	}

	/**
	 * Legacy: characters that hold a character's shadow term.
	 *
	 * Only `wp lwtv links rebuild --legacy-characters` calls this. It uses
	 * core term functions, so it works while the taxonomy is registered.
	 *
	 * @deprecated 7.5.0 Remove in Phase 6, with the shadow terms.
	 *
	 * @param int $character_id Character post ID (X).
	 *
	 * @return int[] Character post IDs.
	 */
	public function legacy_holders( int $character_id ): array {
		$term_id = (int) get_post_meta( $character_id, self::POST_TERM_META, true );

		if ( $term_id < 1 ) {
			return array();
		}

		$objects = get_objects_in_term( $term_id, Characters::SHADOW_TAXONOMY );

		if ( is_wp_error( $objects ) || ! is_array( $objects ) ) {
			return array();
		}

		$ids = array();

		foreach ( array_map( 'intval', $objects ) as $object_id ) {
			if ( Characters::SLUG === get_post_type( $object_id ) ) {
				$ids[] = $object_id;
			}
		}

		return $ids;
	}

	/**
	 * Legacy: the characters a character's relationship chart points at.
	 *
	 * lezchars_relationship_chart was a CMB2 select2 field over the shadow
	 * taxonomy, so it stores shadow TERM IDs, not post IDs. Each one is mapped
	 * back to its character post through the term meta. Values that don't map
	 * to a character post are dropped.
	 *
	 * Only `wp lwtv links rebuild --legacy-characters` calls this.
	 *
	 * @deprecated 7.5.0 Remove in Phase 6, with the shadow terms.
	 *
	 * @param int $character_id Character post ID (X).
	 *
	 * @return int[] Character post IDs, in chart order.
	 */
	public function legacy_chart_targets( int $character_id ): array {
		$chart = get_post_meta( $character_id, 'lezchars_relationship_chart', true );

		if ( empty( $chart ) ) {
			return array();
		}

		$targets = array();

		foreach ( (array) $chart as $term_id ) {
			$term_id = (int) $term_id;

			if ( $term_id < 1 ) {
				continue;
			}

			$post_id = (int) get_term_meta( $term_id, self::TERM_POST_META, true );

			if ( $post_id >= 1 && Characters::SLUG === get_post_type( $post_id ) ) {
				$targets[] = $post_id;
			}
		}

		return $targets;
	}
}
