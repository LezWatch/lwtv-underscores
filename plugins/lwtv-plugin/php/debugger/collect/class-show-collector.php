<?php
/**
 * Fetches what the show rules need, in as few queries as it can manage.
 *
 * The WordPress half of the split: everything here touches meta, terms or
 * queries, and nothing here decides whether anything is wrong. Build\Show_Rules
 * does that, from the plain arrays this returns.
 *
 * Collecting in batches is not incidental. The old scan read five taxonomies
 * per show one show at a time, so a full run was thousands of term queries;
 * `wp_get_object_terms()` takes a list of IDs, so one query per batch covers
 * every taxonomy for every show in it. Batching also bounds the memory that
 * primed meta and term caches hold.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Show_Rules;
use LWTV\CPTs\Shows\Airdates;

class Show_Collector {

	/**
	 * How many shows to gather per pass.
	 *
	 * Small enough that the primed caches stay modest, large enough that the
	 * per-batch queries are worth their round trip.
	 */
	const BATCH = 200;

	/**
	 * Collect one batch of shows.
	 *
	 * @param  array<int> $show_ids Show post IDs.
	 * @return array<int, array<string, mixed>> One entry per show, in ID order.
	 */
	public function collect( array $show_ids ): array {
		$show_ids = array_values( array_unique( array_map( 'intval', $show_ids ) ) );

		if ( empty( $show_ids ) ) {
			return array();
		}

		// One query each, rather than one per show per lookup.
		update_postmeta_cache( $show_ids );
		$terms = $this->terms_for( $show_ids );

		$collected = array();

		foreach ( $show_ids as $show_id ) {
			$meta = $this->meta_for( $show_id );
			$slug = (string) get_post_field( 'post_name', $show_id );

			$show = array(
				'post_id'            => $show_id,
				'slug'               => $slug,
				'meta'               => $meta,
				'terms'              => $terms[ $show_id ] ?? array(),
				'airdates'           => Airdates::get( $show_id ),
				'duplicate'          => $this->duplicate_candidate( $slug ),
				'disabled_character' => null,
			);

			/*
			 * Only asked when the show claims the intersection, because it means
			 * loading the show's characters. Left null otherwise, which the rule
			 * reads as "not applicable" rather than "no disabled character".
			 */
			if ( in_array( 'disabled', $show['terms'][ Show_Rules::INTERSECTIONS ] ?? array(), true ) ) {
				$show['disabled_character'] = $this->has_disabled_character( $show_id );
			}

			$collected[] = $show;
		}

		return $collected;
	}

	/**
	 * The meta the rules need, for one show.
	 *
	 * Reads through get_post_meta() so the cache primed above is used; asking for
	 * only the keys the rules declare keeps the collected array honest about
	 * what the rules are allowed to look at.
	 *
	 * @param  int $show_id Show post ID.
	 * @return array<string, mixed>
	 */
	private function meta_for( int $show_id ): array {
		$meta = array();

		foreach ( Show_Rules::meta_keys() as $key ) {
			$meta[ $key ] = get_post_meta( $show_id, $key, true );
		}

		return $meta;
	}

	/**
	 * Term slugs for a batch, grouped by show and taxonomy.
	 *
	 * @param  array<int> $show_ids Show post IDs.
	 * @return array<int, array<string, array<string>>>
	 */
	private function terms_for( array $show_ids ): array {
		$grouped = array();

		$terms = wp_get_object_terms(
			$show_ids,
			Show_Rules::taxonomies(),
			array(
				'fields' => 'all_with_object_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $grouped;
		}

		foreach ( $terms as $term ) {
			$grouped[ (int) $term->object_id ][ $term->taxonomy ][] = $term->slug;
		}

		return $grouped;
	}

	/**
	 * The show a numerically-suffixed slug might be duplicating.
	 *
	 * Costs a query, so it only runs for the few slugs that end in a number.
	 *
	 * @param  string $slug Post slug.
	 * @return array Empty when there is no candidate.
	 */
	private function duplicate_candidate( string $slug ): array {
		$base = Show_Rules::base_slug( $slug );

		if ( '' === $base ) {
			return array();
		}

		$possible = get_page_by_path( $base, OBJECT, Show_Rules::POST_TYPE );

		if ( ! is_object( $possible ) ) {
			return array();
		}

		return array(
			'id'   => (int) $possible->ID,
			'imdb' => (string) get_post_meta( $possible->ID, 'lezshows_imdb', true ),
		);
	}

	/**
	 * Does any character on this show carry the `disabled` cliché?
	 *
	 * @param  int $show_id Show post ID.
	 * @return bool
	 */
	private function has_disabled_character( int $show_id ): bool {
		$characters = lwtv_plugin()->get_characters_list( $show_id, 'query' );

		if ( empty( $characters ) ) {
			return false;
		}

		foreach ( array_unique( $characters ) as $character ) {
			if ( has_term( 'disabled', 'lez_cliches', $character ) ) {
				return true;
			}
		}

		return false;
	}
}
