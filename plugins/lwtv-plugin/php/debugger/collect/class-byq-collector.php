<?php
/**
 * Fetches what the BYQ rules need.
 *
 * Two ACF reads per character plus a trope check per show they appear on. The
 * trope checks are the expensive part — the same handful of shows come up over
 * and over across a batch of dead characters — so they are resolved once per
 * batch rather than once per character-show pair.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Byq_Rules;

class Byq_Collector {

	/**
	 * How many characters to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * ACF field holding the character's death years.
	 */
	const FIELD_DEATH_YEAR = 'lezchars_death_year';

	/**
	 * ACF field holding the character's shows.
	 */
	const FIELD_SHOWS = 'lezchars_show_group';

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

		$rows = array();

		foreach ( $character_ids as $char_id ) {
			$rows[ $char_id ] = $this->show_rows( $char_id );
		}

		$has_trope = $this->tropes_for( $rows );

		$collected = array();

		foreach ( $character_ids as $char_id ) {
			$shows = array();

			foreach ( $rows[ $char_id ] as $row ) {
				$show_id = $this->show_id( $row );

				$shows[] = array(
					'show_id'   => $show_id,
					'title'     => $show_id ? (string) get_the_title( $show_id ) : '',
					'has_trope' => $has_trope[ $show_id ] ?? false,
				);
			}

			$collected[] = array(
				'post_id'        => $char_id,
				'has_death_year' => $this->has_death_year( $char_id ),
				'shows'          => $shows,
			);
		}

		return $collected;
	}

	/**
	 * Does the character have at least one recorded death date?
	 *
	 * The ACF repeater can hold rows with empty dates, which is not the same as
	 * having a date.
	 *
	 * @param  int $char_id Character post ID.
	 * @return bool
	 */
	private function has_death_year( int $char_id ): bool {
		$death_rows = get_field( self::FIELD_DEATH_YEAR, $char_id );

		if ( ! is_array( $death_rows ) ) {
			return false;
		}

		return ! empty( array_filter( array_column( $death_rows, 'date' ) ) );
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
	 * Which of a batch's shows carry the BYQ trope.
	 *
	 * One term query for the batch rather than a has_term() per character-show
	 * pair: a batch of dead characters references the same shows repeatedly.
	 *
	 * @param  array<int, array> $rows_by_character Raw ACF rows, keyed by character.
	 * @return array<int, bool>
	 */
	private function tropes_for( array $rows_by_character ): array {
		$show_ids = array();

		foreach ( $rows_by_character as $rows ) {
			foreach ( $rows as $row ) {
				$show_id = $this->show_id( $row );

				if ( $show_id ) {
					$show_ids[ $show_id ] = false;
				}
			}
		}

		if ( empty( $show_ids ) ) {
			return array();
		}

		$terms = wp_get_object_terms(
			array_keys( $show_ids ),
			Byq_Rules::TROPE_TAXONOMY,
			array(
				'fields' => 'all_with_object_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $show_ids;
		}

		foreach ( $terms as $term ) {
			if ( Byq_Rules::TROPE === $term->slug ) {
				$show_ids[ (int) $term->object_id ] = true;
			}
		}

		return $show_ids;
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
