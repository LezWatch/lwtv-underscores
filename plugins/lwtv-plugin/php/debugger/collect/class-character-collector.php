<?php
/**
 * Fetches what the character rules need.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Character_Rules;

class Character_Collector {

	/**
	 * How many characters to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * ACF field holding the character's shows.
	 */
	const FIELD_SHOWS = 'lezchars_show_group';

	/**
	 * ACF field holding the character's actors.
	 */
	const FIELD_ACTORS = 'lezchars_actor';

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
		$cliches = $this->cliches_for( $character_ids );

		$collected = array();
		$rows      = array();

		// Two passes so every show a batch references can be cached in one go.
		foreach ( $character_ids as $char_id ) {
			$rows[ $char_id ] = $this->show_rows( $char_id );
		}

		$this->prime_shows( $rows );

		foreach ( $character_ids as $char_id ) {
			$collected[] = array(
				'post_id'    => $char_id,
				'cliches'    => $cliches[ $char_id ] ?? array(),
				'last_death' => get_post_meta( $char_id, Character_Rules::META_DEATH, true ),
				'has_actors' => ! empty( get_field( self::FIELD_ACTORS, $char_id ) ),
				'shows'      => $this->describe_shows( $rows[ $char_id ] ),
			);
		}

		return $collected;
	}

	/**
	 * Cliché slugs for a batch, grouped by character.
	 *
	 * @param  array<int> $character_ids Character post IDs.
	 * @return array<int, array<string>>
	 */
	private function cliches_for( array $character_ids ): array {
		$grouped = array();

		$terms = wp_get_object_terms(
			$character_ids,
			Character_Rules::taxonomies(),
			array(
				'fields' => 'all_with_object_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $grouped;
		}

		foreach ( $terms as $term ) {
			$grouped[ (int) $term->object_id ][] = $term->slug;
		}

		return $grouped;
	}

	/**
	 * The raw ACF show rows for one character.
	 *
	 * @param  int $char_id Character post ID.
	 * @return array
	 */
	private function show_rows( int $char_id ): array {
		$rows = get_field( self::FIELD_SHOWS, $char_id );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Warm the post cache for every show referenced in a batch.
	 *
	 * @param  array<int, array> $rows_by_character Raw ACF rows, keyed by character.
	 * @return void
	 */
	private function prime_shows( array $rows_by_character ): void {
		$show_ids = array();

		foreach ( $rows_by_character as $rows ) {
			foreach ( $rows as $row ) {
				$show_id = $this->show_id( $row );

				if ( $show_id ) {
					$show_ids[] = $show_id;
				}
			}
		}

		$show_ids = array_values( array_unique( $show_ids ) );

		if ( ! empty( $show_ids ) ) {
			_prime_post_caches( $show_ids, false, false );
		}
	}

	/**
	 * Reduce ACF rows to what the rules test.
	 *
	 * @param  array $rows Raw ACF rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function describe_shows( array $rows ): array {
		$described = array();

		foreach ( $rows as $row ) {
			$show_id = $this->show_id( $row );

			$described[] = array(
				'show_id'   => $show_id,
				'title'     => $show_id ? (string) get_the_title( $show_id ) : '',
				// Deliberately only "is an array", matching the original check:
				// an empty appears[] is a different (unreported) situation from
				// the field never having been filled in.
				'has_years' => isset( $row['appears'] ) && is_array( $row['appears'] ),
				'has_role'  => isset( $row['type'] ) && '' !== $row['type'],
			);
		}

		return $described;
	}

	/**
	 * The show ID from one ACF row.
	 *
	 * ACF hands this back as an ID, a post object, or a single-element array of
	 * either, depending on the field's return format and how the row was saved.
	 *
	 * @param  array $row Raw ACF row.
	 * @return int 0 when the row names no show.
	 */
	private function show_id( array $row ): int {
		$show = $row['show'] ?? '';

		if ( is_array( $show ) ) {
			$show = reset( $show );
		}

		if ( $show instanceof \WP_Post ) {
			return (int) $show->ID;
		}

		return is_numeric( $show ) ? (int) $show : 0;
	}
}
