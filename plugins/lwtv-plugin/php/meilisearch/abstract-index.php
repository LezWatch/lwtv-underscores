<?php
/**
 * Name: Index
 * Description: One Meilisearch index: its post type, documents and sync.
 *
 * WordPress glue: verified against the running site, not unit-tested. Each
 * index answers one question, affected_by(): "this post changed; which of my
 * documents does that touch?" Sync asks it and queues work; nothing outside
 * an index needs to know what its documents quote.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Index_State;

abstract class Index {

	protected Client $client;
	protected Config $config;

	/**
	 * @param Client $client Meilisearch client.
	 * @param Config $config Connection settings.
	 */
	public function __construct( Client $client, Config $config ) {
		$this->client = $client;
		$this->config = $config;
	}

	/**
	 * Index type: the uid suffix and the settings file name.
	 *
	 * @return string
	 */
	abstract public function type(): string;

	/**
	 * Post type this index holds.
	 *
	 * @return string
	 */
	abstract public function post_type(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Documents for published posts of this index's type; others are skipped.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array[]
	 */
	abstract public function documents( array $ids ): array;

	/**
	 * IDs of this index's documents a change to a post affects.
	 *
	 * @param string $post_type Changed post's type.
	 * @param int    $post_id   Changed post's ID.
	 * @return int[]
	 */
	abstract public function affected_by( string $post_type, int $post_id ): array;

	/**
	 * Index uid for this environment.
	 *
	 * @return string
	 */
	public function uid(): string {
		return $this->config->index( $this->type() );
	}

	/**
	 * Absolute path to the versioned settings file.
	 *
	 * @return string
	 */
	public function settings_path(): string {
		return Index_Settings::path( $this->type() );
	}

	/**
	 * One post's document, or null when it should not be in the index.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	public function document( int $id ): ?array {
		$documents = $this->documents( array( $id ) );

		return $documents[0] ?? null;
	}

	/**
	 * Add or remove one post's document, matching its current state.
	 *
	 * Never throws; failures are logged.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function sync( int $id ): bool {
		$document = $this->document( $id );
		$result   = ( null === $document )
			? $this->client->delete_document( $this->uid(), $id )
			: $this->client->add_documents( $this->uid(), array( $document ), Client::SHORT_TIMEOUT );

		if ( is_wp_error( $result ) ) {
			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'sync(%d) on %s failed: %s', $id, $this->uid(), $result->get_error_message() ) );
			return false;
		}

		return true;
	}

	/**
	 * Send documents for many posts, in one request per chunk.
	 *
	 * @param int[] $ids   Post IDs.
	 * @param int   $chunk Posts per request.
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
				lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'sync_many() on %s failed for %d document(s): %s', $this->uid(), count( $documents ), $result->get_error_message() ) );
				continue;
			}

			$sent += count( $documents );
		}

		return $sent;
	}

	/**
	 * Task body: bring every document a post change affects up to date.
	 *
	 * Writes nothing unless the index is known to exist, so sync can never
	 * create an index with default settings. A post that no longer exists
	 * has its document deleted. Never throws.
	 *
	 * @param int $post_id Changed post ID.
	 * @return void
	 */
	public function run( int $post_id ): void {
		try {
			$state = $this->state();
			if ( Index_State::EXISTS !== $state ) {
				lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Skipped post %d on %s: index %s.', $post_id, $this->uid(), Index_State::MISSING === $state ? 'not set up' : 'unreachable' ) );
				return;
			}

			$post_type = get_post_type( $post_id );
			if ( false === $post_type ) {
				$result = $this->client->delete_document( $this->uid(), $post_id );
				if ( is_wp_error( $result ) ) {
					lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Delete %d on %s failed: %s', $post_id, $this->uid(), $result->get_error_message() ) );
				}
				return;
			}

			if ( $this->post_type() === $post_type ) {
				$this->sync( $post_id );
				return;
			}

			$ids = $this->affected_by( (string) $post_type, $post_id );
			if ( ! empty( $ids ) ) {
				$sent = $this->sync_many( $ids );
				lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Fan-out from %s %d to %s: %d of %d document(s) sent.', $post_type, $post_id, $this->uid(), $sent, count( $ids ) ) );
			}
		} catch ( \Throwable $e ) {
			lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'Task for post %d on %s threw: %s', $post_id, $this->uid(), $e->getMessage() ) );
		}
	}

	/**
	 * Whether the index exists on the server, cached.
	 *
	 * @param bool $check On a cache miss, ask the server (false: return unknown).
	 * @return string An Index_State constant.
	 */
	public function state( bool $check = true ): string {
		$cached = get_transient( $this->state_key() );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		if ( ! $check ) {
			return Index_State::UNKNOWN;
		}

		$result = $this->client->index_stats( $this->uid() );
		$state  = Index_State::classify( is_wp_error( $result ) ? $result->get_error_message() : null );
		$ttl    = Index_State::ttl( $state );
		if ( $ttl > 0 ) {
			set_transient( $this->state_key(), $state, $ttl );
		}

		return $state;
	}

	/**
	 * Drop the cached state (after creating or deleting the index).
	 *
	 * @return void
	 */
	public function forget_state(): void {
		delete_transient( $this->state_key() );
	}

	/**
	 * Transient key for the cached state.
	 *
	 * @return string
	 */
	private function state_key(): string {
		return 'lwtv_meili_state_' . $this->uid();
	}

	/**
	 * Title and public visibility for linked posts.
	 *
	 * An ID whose post type isn't the one its field expects (a stale or
	 * mistyped ID pointing at a page, post or character) is left out. An
	 * actor marked hide_all is hidden even if its status hasn't caught up.
	 *
	 * @param array<int, string> $expected Post ID => expected post type.
	 * @return array<int, array{title:string, visible:bool}>
	 */
	protected function lookup( array $expected ): array {
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
