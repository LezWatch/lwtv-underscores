<?php
/**
 * Name: Indexer
 * Description: Gathers a character's data from WordPress and syncs its document.
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

class Indexer {

	/**
	 * Index type for characters.
	 */
	const TYPE = 'characters';

	private Client $client;
	private Config $config;

	/**
	 * @param Client $client Meilisearch client.
	 * @param Config $config Connection settings.
	 */
	public function __construct( Client $client, Config $config ) {
		$this->client = $client;
		$this->config = $config;
	}

	/**
	 * The characters index uid for this environment.
	 *
	 * @return string
	 */
	public function uid(): string {
		return $this->config->index( self::TYPE );
	}

	/**
	 * One character's document, or null when it should not be in the index.
	 *
	 * @param int $character_id Character post ID.
	 * @return array|null
	 */
	public function document( int $character_id ): ?array {
		$documents = $this->documents( array( $character_id ) );

		return $documents[0] ?? null;
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

	/**
	 * Add or remove one character's document, matching its current state.
	 *
	 * Never throws; failures are logged.
	 *
	 * @param int $character_id Character post ID.
	 * @return bool
	 */
	public function sync( int $character_id ): bool {
		$document = $this->document( $character_id );
		$result   = ( null === $document )
			? $this->client->delete_document( $this->uid(), $character_id )
			: $this->client->add_documents( $this->uid(), array( $document ), Client::SHORT_TIMEOUT );

		if ( is_wp_error( $result ) ) {
			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'sync(%d) on %s failed: %s', $character_id, $this->uid(), $result->get_error_message() ) );
			return false;
		}

		return true;
	}

	/**
	 * Send documents for many characters, in one request per chunk.
	 *
	 * @param int[] $ids   Character post IDs.
	 * @param int   $chunk Characters per request.
	 * @return int Number of documents sent.
	 */
	public function sync_many( array $ids, int $chunk = 100 ): int {
		$sent = 0;
		foreach ( array_chunk( $ids, max( 1, $chunk ) ) as $batch ) {
			$documents = $this->documents( $batch );
			if ( empty( $documents ) ) {
				continue;
			}

			$result = $this->client->add_documents( $this->uid(), $documents );
			if ( is_wp_error( $result ) ) {
				lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'sync_many() on %s failed for %d character(s): %s', $this->uid(), count( $documents ), $result->get_error_message() ) );
				continue;
			}

			$sent += count( $documents );
		}

		return $sent;
	}

	/**
	 * Title and public visibility for linked shows and actors.
	 *
	 * An ID whose post type isn't the one its field expects (a stale or
	 * mistyped ID pointing at a page, post or character) is left out. An
	 * actor marked hide_all is hidden even if its status hasn't caught up.
	 *
	 * @param array<int, string> $expected Post ID => expected post type.
	 * @return array<int, array{title:string, visible:bool}>
	 */
	private function lookup( array $expected ): array {
		unset( $expected[0] );
		$ids = array_keys( $expected );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, false, true );

		$lookup = array();
		foreach ( $ids as $id ) {
			$status = get_post_status( $id );
			if ( false === $status || get_post_type( $id ) !== $expected[ $id ] ) {
				continue;
			}

			$visible = ( 'publish' === $status );
			if ( $visible && Actors::SLUG === get_post_type( $id ) ) {
				$privacy = get_post_meta( $id, 'lezactors_make_option_private', true );
				$visible = ! ( is_array( $privacy ) && in_array( 'hide_all', $privacy, true ) );
			}

			$lookup[ $id ] = array(
				'title'   => (string) get_post_field( 'post_title', $id ),
				'visible' => $visible,
			);
		}

		return $lookup;
	}
}
