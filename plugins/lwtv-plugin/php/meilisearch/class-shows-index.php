<?php
/**
 * Name: Shows Index
 * Description: Gathers a show's data from WordPress for its document.
 *
 * WordPress glue: verified against the running site, not unit-tested. The
 * decisions live in Build\Show_Document.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Characters;
use LWTV\CPTs\Shows;
use LWTV\CPTs\Shows\Airdates;
use LWTV\Meilisearch\Build\Show_Document;
use LWTV\Relationships\Store;

class Shows_Index extends Index {

	const TYPE = 'shows';

	/**
	 * @inheritDoc
	 */
	public function type(): string {
		return self::TYPE;
	}

	/**
	 * @inheritDoc
	 */
	public function post_type(): string {
		return Shows::SLUG;
	}

	/**
	 * @inheritDoc
	 */
	public function label(): string {
		return __( 'Shows', 'lwtv-underscores' );
	}

	/**
	 * Shows quote their characters' names and those characters' actors.
	 *
	 * @param string $post_type Changed post's type.
	 * @param int    $post_id   Changed post's ID.
	 * @return int[]
	 */
	public function affected_by( string $post_type, int $post_id ): array {
		if ( Shows::SLUG === $post_type ) {
			return array( $post_id );
		}
		if ( Characters::SLUG === $post_type ) {
			return Store::objects_for_character( $post_id, 'show' );
		}
		if ( Actors::SLUG === $post_type ) {
			$shows = array();
			foreach ( Store::characters_for_object( $post_id, 'actor' ) as $character_id ) {
				$shows = array_merge( $shows, Store::objects_for_character( $character_id, 'show' ) );
			}
			return array_values( array_unique( $shows ) );
		}

		return array();
	}

	/**
	 * Documents for published shows; others are skipped.
	 *
	 * Primes post, meta and term caches for the shows, their published
	 * characters, and those characters' actors, so a batch stays cheap.
	 *
	 * @param int[] $ids Show post IDs.
	 * @return array[]
	 */
	public function documents( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, true, true );

		$shows      = array();
		$characters = array();
		foreach ( $ids as $id ) {
			if ( Shows::SLUG !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}

			$shows[ $id ] = Store::characters_for_object( $id, 'show' );
			$characters   = array_merge( $characters, $shows[ $id ] );
		}

		$characters = array_values( array_unique( $characters ) );
		if ( ! empty( $characters ) ) {
			_prime_post_caches( $characters, false, true );
		}

		$actors_of = array();
		$linked    = array();
		foreach ( $characters as $character_id ) {
			$actors                     = get_field( 'lezchars_actor', $character_id, false );
			$actors_of[ $character_id ] = is_array( $actors ) ? array_map( 'intval', $actors ) : array();
			foreach ( $actors_of[ $character_id ] as $actor_id ) {
				$linked[ $actor_id ] = Actors::SLUG;
			}
		}

		$lookup = $this->lookup( $linked );

		$documents = array();
		foreach ( $shows as $id => $character_ids ) {
			$titles = array();
			$actors = array();
			foreach ( $character_ids as $character_id ) {
				$titles[] = (string) get_post_field( 'post_title', $character_id );
				foreach ( $actors_of[ $character_id ] ?? array() as $actor_id ) {
					if ( isset( $lookup[ $actor_id ] ) ) {
						$actors[ $actor_id ] = $lookup[ $actor_id ];
					}
				}
			}

			$thumb       = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$documents[] = Show_Document::build(
				$id,
				(string) get_post_field( 'post_title', $id ),
				get_field( 'lezshows_show_names', $id, false ),
				Airdates::get( $id ),
				get_post_meta( $id, 'lezshows_worthit_rating', true ),
				get_post_meta( $id, 'lezshows_the_score', true ),
				$titles,
				$actors,
				$this->term_names( $id, 'lez_stations' ),
				$this->term_names( $id, 'lez_country' ),
				(string) get_permalink( $id ),
				$thumb ? (string) $thumb : ''
			);
		}

		return $documents;
	}

	/**
	 * Term names on a post, from the primed term cache.
	 *
	 * @param int    $id       Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function term_names( int $id, string $taxonomy ): array {
		$terms = get_the_terms( $id, $taxonomy );

		return is_array( $terms ) ? array_map( fn( $term ) => (string) $term->name, $terms ) : array();
	}
}
