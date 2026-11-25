<?php
/**
 * Name: Actors Index
 * Description: Gathers an actor's data from WordPress for its document.
 *
 * WordPress glue: verified against the running site, not unit-tested. The
 * decisions live in Build\Actor_Document.
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
use LWTV\Meilisearch\Build\Actor_Document;
use LWTV\Relationships\Store;

class Actors_Index extends Index {

	const TYPE = 'actors';

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
		return Actors::SLUG;
	}

	/**
	 * @inheritDoc
	 */
	public function label(): string {
		return __( 'Actors', 'lwtv-underscores' );
	}

	/**
	 * Actors quote their characters' names and those characters' shows.
	 *
	 * @param string $post_type Changed post's type.
	 * @param int    $post_id   Changed post's ID.
	 * @return int[]
	 */
	public function affected_by( string $post_type, int $post_id ): array {
		if ( Actors::SLUG === $post_type ) {
			return array( $post_id );
		}
		if ( Characters::SLUG === $post_type ) {
			return Store::objects_for_character( $post_id, 'actor' );
		}
		if ( Shows::SLUG === $post_type ) {
			$actors = array();
			foreach ( Store::characters_for_object( $post_id, 'show' ) as $character_id ) {
				$actors = array_merge( $actors, Store::objects_for_character( $character_id, 'actor' ) );
			}
			return array_values( array_unique( $actors ) );
		}

		return array();
	}

	/**
	 * Documents for published actors that aren't hide_all; others are skipped.
	 *
	 * hide_all is read from meta, not trusted to the post status, which can
	 * lag behind the privacy choice. Primes post and meta caches for the
	 * actors, their characters and those characters' shows.
	 *
	 * @param int[] $ids Actor post IDs.
	 * @return array[]
	 */
	public function documents( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, false, true );

		$actors     = array();
		$characters = array();
		foreach ( $ids as $id ) {
			if ( Actors::SLUG !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}

			$privacy = get_post_meta( $id, 'lezactors_make_option_private', true );
			if ( is_array( $privacy ) && in_array( 'hide_all', $privacy, true ) ) {
				continue;
			}

			$actors[ $id ] = array(
				'privacy'    => $privacy,
				'characters' => Store::characters_for_object( $id, 'actor' ),
			);
			$characters    = array_merge( $characters, $actors[ $id ]['characters'] );
		}

		$characters = array_values( array_unique( $characters ) );
		if ( ! empty( $characters ) ) {
			_prime_post_caches( $characters, false, false );
		}

		$shows_of = array();
		$shows    = array();
		foreach ( $characters as $character_id ) {
			$shows_of[ $character_id ] = Store::objects_for_character( $character_id, 'show' );
			$shows                     = array_merge( $shows, $shows_of[ $character_id ] );
		}

		$shows = array_values( array_unique( $shows ) );
		if ( ! empty( $shows ) ) {
			_prime_post_caches( $shows, false, false );
		}

		$documents = array();
		foreach ( $actors as $id => $actor ) {
			$character_titles = array();
			$show_titles      = array();
			foreach ( $actor['characters'] as $character_id ) {
				$character_titles[] = (string) get_post_field( 'post_title', $character_id );
				foreach ( $shows_of[ $character_id ] ?? array() as $show_id ) {
					$show_titles[] = (string) get_post_field( 'post_title', $show_id );
				}
			}

			$thumb       = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$documents[] = Actor_Document::build(
				$id,
				(string) get_post_field( 'post_title', $id ),
				get_field( 'lezactors_other_names', $id, false ),
				$character_titles,
				$show_titles,
				get_post_meta( $id, 'lezactors_birth', true ),
				get_post_meta( $id, 'lezactors_death', true ),
				$actor['privacy'],
				wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ),
				get_post_meta( $id, 'lezactors_queer', true ),
				get_post_meta( $id, 'lezactors_char_count', true ),
				(string) get_permalink( $id ),
				$thumb ? (string) $thumb : ''
			);
		}

		return $documents;
	}
}
