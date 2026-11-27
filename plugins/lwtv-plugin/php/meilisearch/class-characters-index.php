<?php
/**
 * Name: Characters Index
 * Description: Gathers a character's data from WordPress for its document.
 *
 * WordPress glue: verified against the running site, not unit-tested. The
 * decisions live in Build\Character_Document.
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
use LWTV\Meilisearch\Build\Character_Document;
use LWTV\Meilisearch\Build\Show_Group;
use LWTV\Relationships\Store;

class Characters_Index extends Index {

	const TYPE = 'characters';

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
		return Characters::SLUG;
	}

	/**
	 * @inheritDoc
	 */
	public function label(): string {
		return __( 'Characters', 'lwtv-underscores' );
	}

	/**
	 * Characters quote their shows' and actors' titles.
	 *
	 * @param string $post_type Changed post's type.
	 * @param int    $post_id   Changed post's ID.
	 * @return int[]
	 */
	public function affected_by( string $post_type, int $post_id ): array {
		if ( Characters::SLUG === $post_type ) {
			return array( $post_id );
		}
		if ( Shows::SLUG === $post_type ) {
			return Store::characters_for_object( $post_id, 'show' );
		}
		if ( Actors::SLUG === $post_type ) {
			return Store::characters_for_object( $post_id, 'actor' );
		}

		return array();
	}

	/**
	 * Documents for published characters; others are skipped.
	 *
	 * Primes post and meta caches for the characters and every linked show
	 * and actor first, so a batch costs a handful of queries, not thousands.
	 *
	 * @param int[] $ids Character post IDs.
	 * @return array[]
	 */
	public function documents( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, false, true );

		$raw    = array();
		$linked = array();
		foreach ( $ids as $id ) {
			if ( Characters::SLUG !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}

			$rows   = Show_Group::normalize( get_field( 'lezchars_show_group', $id, false ) );
			$actors = get_field( 'lezchars_actor', $id, false );
			$actors = is_array( $actors ) ? $actors : array();

			$raw[ $id ] = array( $rows, $actors );
			foreach ( $rows as $row ) {
				$linked[ (int) $row['show'] ] = Shows::SLUG;
			}
			foreach ( $actors as $actor_id ) {
				$linked[ (int) $actor_id ] = Actors::SLUG;
			}
		}

		$lookup = $this->lookup( $linked );

		$documents = array();
		foreach ( $raw as $id => list( $rows, $actors ) ) {
			$thumb       = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$documents[] = Character_Document::build(
				$id,
				(string) get_post_field( 'post_title', $id ),
				$rows,
				$actors,
				$lookup,
				(string) get_permalink( $id ),
				$thumb ? (string) $thumb : ''
			);
		}

		return $documents;
	}
}
