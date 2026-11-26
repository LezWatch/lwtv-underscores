<?php
/**
 * Name: Reindexer
 * Description: The reindex and settings steps the CLI and the Search Settings jobs share.
 *
 * One place for: which posts are in a page of an index, building and sending
 * their documents, the settings payload (repo file plus the Search Rules
 * synonyms) and the stale-document prune. Nothing here waits on a task: the
 * CLI waits around these calls, and the background jobs poll instead.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Prune_Filter;

class Reindexer {

	/**
	 * Posts per batch.
	 */
	const BATCH = 500;

	private Client $client;
	private Search_Rules_Store $rules;

	/**
	 * @param Client             $client Meilisearch client.
	 * @param Search_Rules_Store $rules  Saved synonyms.
	 */
	public function __construct( Client $client, Search_Rules_Store $rules ) {
		$this->client = $client;
		$this->rules  = $rules;
	}

	/**
	 * Published post IDs for one page of an index, oldest first.
	 *
	 * @param Index $index Index.
	 * @param int   $page  1-based page.
	 * @param int   $batch Posts per page.
	 * @return int[]
	 */
	public function page_ids( Index $index, int $page, int $batch = self::BATCH ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => $index->post_types(),
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $batch,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Up to $batch published post IDs above $after_id, lowest first.
	 *
	 * Keyset paging for background jobs, whose pages can be minutes apart: a
	 * post trashed or published meanwhile can't shift a page and skip one.
	 *
	 * @param Index $index    Index.
	 * @param int   $after_id Highest ID already read (0 to start).
	 * @param int   $batch    Posts per page.
	 * @return int[]
	 */
	public function page_ids_after( Index $index, int $after_id, int $batch = self::BATCH ): array {
		$where = static function ( $sql ) use ( $after_id ) {
			global $wpdb;
			return $sql . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after_id );
		};

		add_filter( 'posts_where', $where );
		$query = new \WP_Query(
			array(
				'post_type'              => $index->post_types(),
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $batch,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_term_cache' => false,
			)
		);
		remove_filter( 'posts_where', $where );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Every published post ID the index covers, right now.
	 *
	 * @param Index $index Index.
	 * @return int[]
	 */
	public function published_ids( Index $index ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => $index->post_types(),
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * How many published posts the index covers, for progress.
	 *
	 * @param Index $index Index.
	 * @return int
	 */
	public function total( Index $index ): int {
		$total = 0;
		foreach ( $index->post_types() as $post_type ) {
			$counts = wp_count_posts( $post_type );
			$total += (int) ( $counts->publish ?? 0 );
		}

		return $total;
	}

	/**
	 * Build one page's documents and, unless it's a dry run, send them.
	 *
	 * @param Index $index   Index.
	 * @param int[] $ids     Post IDs.
	 * @param bool  $dry_run Build only.
	 * @return array{built: int[], documents: array[], result: array|\WP_Error|null}
	 */
	public function send_page( Index $index, array $ids, bool $dry_run = false ): array {
		$documents = empty( $ids ) ? array() : $index->documents( $ids );
		// Only what was built counts: a published post the index skips (an
		// actor marked hide_all whose status hasn't caught up) gets pruned.
		$built  = array_map( 'intval', array_column( $documents, 'id' ) );
		$result = ( $dry_run || empty( $documents ) ) ? null : $this->client->add_documents( $index->uid(), $documents, 60 );

		return array(
			'built'     => $built,
			'documents' => $documents,
			'result'    => $result,
		);
	}

	/**
	 * The settings an index should have: its repo file plus the saved
	 * synonyms, so applying it never wipes them.
	 *
	 * @param Index $index Index.
	 * @return array
	 * @throws \InvalidArgumentException When the repo file is invalid.
	 */
	public function settings_payload( Index $index ): array {
		$settings = Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
		$synonyms = $this->rules->synonyms_map();

		// An empty map must go as an object ({}), not a list.
		$settings['synonyms'] = empty( $synonyms ) ? new \stdClass() : $synonyms;

		return $settings;
	}

	/**
	 * Delete the documents of posts that weren't built this run.
	 *
	 * @param Index $index Index.
	 * @param int[] $built Every post ID built this run.
	 * @return array|\WP_Error|null The task, or null when there is nothing to keep (no filter).
	 */
	public function prune( Index $index, array $built ) {
		$filter = Prune_Filter::not_in( $built );
		if ( null === $filter ) {
			return null;
		}

		return $this->client->delete_documents_by_filter( $index->uid(), $filter );
	}
}
