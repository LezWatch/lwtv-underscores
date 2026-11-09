<?php
/**
 * Name: Character Links
 * Description: The one place that answers "which characters link to what".
 *
 * Every reader of character ↔ show/actor/character links goes through this
 * class. It is backed by the shadow taxonomy by default and by the
 * lwtv_char_links table (Store) when the SOURCE_OPTION option is 'table'.
 *
 * Shadow lookups return any post status, the same as the old readers did;
 * the consumers filter by status themselves. Table lookups (Store) return
 * published posts only.
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

class Character_Links {

	/**
	 * Option that picks the read source: 'table', or anything else for 'shadow'.
	 */
	const SOURCE_OPTION = 'lwtv_char_links_source';

	/**
	 * Term meta on a shadow term that holds its character's post ID.
	 */
	const TERM_POST_META = 'shadow_shadow_tax_characters_post_id';

	/**
	 * Post meta on a character that holds its shadow term ID.
	 */
	const POST_TERM_META = 'shadow_shadow_tax_characters_term_id';

	/**
	 * Source this instance is pinned to, or null to read the option.
	 *
	 * @var string|null
	 */
	private ?string $pinned = null;

	/**
	 * A copy of this instance pinned to one source, ignoring the option.
	 *
	 * The shadow-term sync uses from( 'shadow' ): it reconciles the shadow
	 * terms, so it needs the shadow view whatever the site reads from.
	 *
	 * @param string $source 'shadow' or 'table'. Anything else means 'shadow'.
	 *
	 * @return self
	 */
	public function from( string $source ): self {
		$clone         = clone $this;
		$clone->pinned = ( 'table' === $source ) ? 'table' : 'shadow';

		return $clone;
	}

	/**
	 * Where reads come from.
	 *
	 * @return string 'table' only when pinned to it or the option is exactly 'table', otherwise 'shadow'.
	 */
	public function source(): string {
		if ( null !== $this->pinned ) {
			return $this->pinned;
		}

		return ( 'table' === get_option( self::SOURCE_OPTION ) ) ? 'table' : 'shadow';
	}

	/**
	 * Characters that list a show.
	 *
	 * @param int $show_id Show post ID.
	 *
	 * @return int[] Character post IDs.
	 */
	public function characters_for_show( int $show_id ): array {
		if ( 'table' === $this->source() ) {
			return Store::characters_for_object( $show_id, 'show' );
		}

		return $this->shadow_characters_for_object( $show_id );
	}

	/**
	 * Characters that list an actor.
	 *
	 * @param int $actor_id Actor post ID.
	 *
	 * @return int[] Character post IDs.
	 */
	public function characters_for_actor( int $actor_id ): array {
		if ( 'table' === $this->source() ) {
			return Store::characters_for_object( $actor_id, 'actor' );
		}

		return $this->shadow_characters_for_object( $actor_id );
	}

	/**
	 * Shows a character is linked to.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Show post IDs.
	 */
	public function shows_for_character( int $character_id ): array {
		if ( 'table' === $this->source() ) {
			return Store::objects_for_character( $character_id, 'show' );
		}

		return $this->shadow_objects_for_character( $character_id, Shows::SLUG );
	}

	/**
	 * Actors a character is linked to.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Actor post IDs.
	 */
	public function actors_for_character( int $character_id ): array {
		if ( 'table' === $this->source() ) {
			return Store::objects_for_character( $character_id, 'actor' );
		}

		return $this->shadow_objects_for_character( $character_id, Actors::SLUG );
	}

	/**
	 * Characters linked to a character in either direction (legacy data).
	 *
	 * The union of the characters this one's lezchars_relationship_chart lists
	 * and the characters that have this one's shadow term attached. The
	 * character itself is never included.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Character post IDs, sorted.
	 */
	public function characters_referencing( int $character_id ): array {
		if ( 'table' === $this->source() ) {
			return Store::characters_linked_to_character( $character_id );
		}

		return $this->shadow_characters_referencing( $character_id );
	}

	/**
	 * Shadow: characters whose terms are attached to a show or actor.
	 *
	 * Kept in wp_get_object_terms() order (term name), which is the order the
	 * old get_the_posts() reader returned. Not deduplicated, also as before.
	 *
	 * @param int $object_id Show or actor post ID.
	 *
	 * @return int[] Character post IDs.
	 */
	private function shadow_characters_for_object( int $object_id ): array {
		$term_ids = wp_get_object_terms( $object_id, Characters::SHADOW_TAXONOMY, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
			return array();
		}

		$characters = array();

		foreach ( $term_ids as $term_id ) {
			$character_id = (int) get_term_meta( (int) $term_id, self::TERM_POST_META, true );

			if ( $character_id >= 1 ) {
				$characters[] = $character_id;
			}
		}

		return $characters;
	}

	/**
	 * Shadow: posts of one type that have a character's term attached.
	 *
	 * @param int    $character_id Character post ID.
	 * @param string $post_type    Post type to keep.
	 *
	 * @return int[] Post IDs.
	 */
	private function shadow_objects_for_character( int $character_id, string $post_type ): array {
		$ids = array();

		foreach ( $this->shadow_holders( $character_id ) as $object_id ) {
			if ( get_post_type( $object_id ) === $post_type ) {
				$ids[] = $object_id;
			}
		}

		return $ids;
	}

	/**
	 * Shadow: characters that hold a character's term.
	 *
	 * Shared by the read path and the legacy import
	 * (wp lwtv links rebuild --legacy-characters) so they cannot drift apart.
	 *
	 * @param int $character_id Character post ID (X).
	 *
	 * @return int[] Character post IDs.
	 */
	public function legacy_holders( int $character_id ): array {
		return $this->shadow_objects_for_character( $character_id, Characters::SLUG );
	}

	/**
	 * The characters a character's legacy relationship chart points at.
	 *
	 * lezchars_relationship_chart was a CMB2 select2 field over the shadow
	 * taxonomy, so it stores shadow TERM IDs, not post IDs. Each one is mapped
	 * back to its character post through the term meta. Values that don't map
	 * to a character post are dropped.
	 *
	 * Shared by the read path and the legacy import so they cannot drift apart.
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

	/**
	 * Shadow: the legacy character ↔ character union.
	 *
	 * @param int $character_id Character post ID (X).
	 *
	 * @return int[] Character post IDs, sorted.
	 */
	private function shadow_characters_referencing( int $character_id ): array {
		$holders = $this->legacy_holders( $character_id );
		$chart   = $this->legacy_chart_targets( $character_id );
		$rows    = Link_Rows::legacy_characters( $character_id, $chart, $holders );

		return Link_Rows::other_ids( $rows, $character_id );
	}

	/**
	 * Shadow: every object that has a character's term attached, any type.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[] Object IDs, or none when the character has no term.
	 */
	private function shadow_holders( int $character_id ): array {
		$term_id = (int) get_post_meta( $character_id, self::POST_TERM_META, true );

		if ( $term_id < 1 ) {
			return array();
		}

		$objects = get_objects_in_term( $term_id, Characters::SHADOW_TAXONOMY );

		if ( is_wp_error( $objects ) || ! is_array( $objects ) ) {
			return array();
		}

		return array_map( 'intval', $objects );
	}
}
