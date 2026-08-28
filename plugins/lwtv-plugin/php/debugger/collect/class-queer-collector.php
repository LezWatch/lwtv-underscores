<?php
/**
 * Fetches what the queer-consistency rules need.
 *
 * The expensive part is asking whether an actor is queer, which is its own query
 * per actor -- and the same actors recur across a batch of characters, so the
 * verdicts are resolved once per batch and reused.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Queer_Rules;
use LWTV\Queeries\Is_Actor_Queer;

class Queer_Collector {

	/**
	 * How many characters to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * ACF field holding the character's actors.
	 */
	const FIELD_ACTORS = 'lezchars_actor';

	/**
	 * Taxonomy holding character clichés.
	 */
	const CLICHES = 'lez_cliches';

	/**
	 * Collect one batch of characters.
	 *
	 * @param  array<int> $character_ids Character post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( array $character_ids ): array {
		$character_ids = array_values( array_unique( array_map( 'intval', $character_ids ) ) );

		if ( empty( $character_ids ) ) {
			return array();
		}

		update_postmeta_cache( $character_ids );

		$actors  = array();
		$flagged = $this->flagged_for( $character_ids );

		foreach ( $character_ids as $char_id ) {
			$actors[ $char_id ] = $this->actors_for( $char_id );
		}

		$is_queer  = $this->queer_verdicts( $actors );
		$collected = array();

		foreach ( $character_ids as $char_id ) {
			$any_queer = false;

			foreach ( $actors[ $char_id ] as $actor_id ) {
				if ( ! empty( $is_queer[ $actor_id ] ) ) {
					$any_queer = true;
					break;
				}
			}

			$collected[] = array(
				'post_id'       => $char_id,
				'has_actors'    => ! empty( $actors[ $char_id ] ),
				'flagged_queer' => $flagged[ $char_id ] ?? false,
				'actor_queer'   => $any_queer,
			);
		}

		return $collected;
	}

	/**
	 * Which characters in the batch carry the queer-irl cliché.
	 *
	 * @param  array<int> $character_ids Character post IDs.
	 * @return array<int, bool>
	 */
	private function flagged_for( array $character_ids ): array {
		$terms = wp_get_object_terms(
			$character_ids,
			self::CLICHES,
			array(
				'fields' => 'all_with_object_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$flagged = array();

		foreach ( $terms as $term ) {
			if ( Queer_Rules::CLICHE === $term->slug ) {
				$flagged[ (int) $term->object_id ] = true;
			}
		}

		return $flagged;
	}

	/**
	 * The actor IDs linked to one character.
	 *
	 * @param  int $char_id Character post ID.
	 * @return array<int>
	 */
	private function actors_for( int $char_id ): array {
		$actors = get_field( self::FIELD_ACTORS, $char_id );

		if ( ! is_array( $actors ) ) {
			return array();
		}

		$ids = array();

		foreach ( $actors as $actor ) {
			if ( $actor instanceof \WP_Post ) {
				$ids[] = (int) $actor->ID;
				continue;
			}

			if ( is_numeric( $actor ) ) {
				$ids[] = (int) $actor;
			}
		}

		return $ids;
	}

	/**
	 * Is-queer verdict per actor, resolved once for the batch.
	 *
	 * @param  array<int, array<int>> $actors_by_character Actor IDs per character.
	 * @return array<int, bool>
	 */
	private function queer_verdicts( array $actors_by_character ): array {
		$verdicts = array();
		$checker  = new Is_Actor_Queer();

		foreach ( $actors_by_character as $actor_ids ) {
			foreach ( $actor_ids as $actor_id ) {
				if ( ! isset( $verdicts[ $actor_id ] ) ) {
					$verdicts[ $actor_id ] = (bool) $checker->make( $actor_id );
				}
			}
		}

		return $verdicts;
	}
}
