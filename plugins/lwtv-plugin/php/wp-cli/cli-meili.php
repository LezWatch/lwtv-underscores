<?php
/*
 * WP CLI Commands for the Meilisearch prototype.
 *
 * status reads the server and each index (not its version: /version needs a
 * global key, and the indexing key is scoped to this environment's indexes),
 * settings applies the versioned settings files, reindex builds an index, and
 * sync re-syncs everything one post affects. status, settings and reindex take
 * --type=characters|shows|all (default characters). Every command names the
 * index and host before it touches anything.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Prune_Filter;
use LWTV\Meilisearch\Build\Type_Option;
use LWTV\Meilisearch\Client;
use LWTV\Meilisearch\Config;
use LWTV\Meilisearch\Index;
use LWTV\Meilisearch\Indexes;

/**
 * LezWatch.TV command to manage the Meilisearch indexes.
 */
class WP_CLI_LWTV_Meili {

	/**
	 * Posts per batch.
	 */
	public const BATCH = 500;

	private Config $config;
	private Client $client;
	private Indexes $indexes;

	/**
	 * Show server health and, per index, its document count and whether
	 * its settings match the repo file.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : characters, shows, or all. Default characters.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili status
	 *     wp lwtv meili status --type=all
	 *
	 * @subcommand status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args = array(), $assoc_args = array() ) {
		$this->boot();

		$health = $this->client->health();
		if ( is_wp_error( $health ) ) {
			\WP_CLI::error( 'Server unreachable: ' . $health->get_error_message() );
		}
		\WP_CLI::log( 'Health: ' . (string) ( $health['status'] ?? 'unknown' ) );

		foreach ( $this->selected( $assoc_args ) as $index ) {
			$this->name( $index );

			$stats = $this->client->index_stats( $index->uid() );
			if ( is_wp_error( $stats ) ) {
				\WP_CLI::warning( 'Index stats: ' . $stats->get_error_message() );
				continue;
			}
			\WP_CLI::log( 'Documents: ' . (int) ( $stats['numberOfDocuments'] ?? 0 ) . ( ! empty( $stats['isIndexing'] ) ? ' (indexing)' : '' ) );

			$live = $this->client->get_settings( $index->uid() );
			if ( is_wp_error( $live ) ) {
				\WP_CLI::warning( 'Settings: ' . $live->get_error_message() );
				continue;
			}

			$drift = array();
			foreach ( $this->repo_settings( $index ) as $key => $value ) {
				if ( ( $live[ $key ] ?? null ) !== $value ) {
					$drift[] = $key;
				}
			}

			if ( empty( $drift ) ) {
				\WP_CLI::success( 'Settings match the repo file.' );
			} else {
				\WP_CLI::warning( sprintf( 'Settings differ from the repo file: %s. Run `wp lwtv meili settings --type=%s`.', implode( ', ', $drift ), $index->type() ) );
			}
		}
	}

	/**
	 * Create an index if it is missing and apply its repo settings file.
	 *
	 * This is also what switches an index on for an environment: sync never
	 * creates an index.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : characters, shows, or all. Default characters.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili settings
	 *     wp lwtv meili settings --type=shows
	 *
	 * @subcommand settings
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function settings( $args = array(), $assoc_args = array() ) {
		$this->boot();
		foreach ( $this->selected( $assoc_args ) as $index ) {
			$this->name( $index );
			$this->apply_settings( $index, false );
			\WP_CLI::success( 'Settings applied.' );
		}
	}

	/**
	 * Build an index from published posts.
	 *
	 * Re-sends every published post, then removes any document whose post
	 * is no longer published (deleted, trashed, unpublished), so a plain
	 * reindex repairs drift without emptying the index.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : characters, shows, or all. Default characters.
	 *
	 * [--fresh]
	 * : Delete and recreate the index, and apply settings, first. Also the
	 *   upgrade path after a Meilisearch version change.
	 *
	 * [--batch=<n>]
	 * : Posts per request. Default 500.
	 *
	 * [--dry-run]
	 * : Build documents and print a count and one sample. Sends nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili reindex --dry-run
	 *     wp lwtv meili reindex --type=shows --fresh
	 *
	 * @subcommand reindex
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reindex( $args, $assoc_args = array() ) {
		$this->boot();

		$fresh   = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fresh', false );
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$batch   = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', self::BATCH ) );

		foreach ( $this->selected( $assoc_args ) as $index ) {
			$this->name( $index );
			$this->reindex_one( $index, $fresh, $dry_run, $batch );
		}
	}

	/**
	 * Sync one post now: its own document, and every document in every
	 * index that quotes it.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Post ID (character, show or actor).
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili sync 123
	 *
	 * @subcommand sync
	 *
	 * @param array $args Positional arguments.
	 */
	public function sync( $args ) {
		$this->boot();

		$id = (int) ( $args[0] ?? 0 );
		if ( $id <= 0 ) {
			\WP_CLI::error( 'Give a post ID.' );
		}

		$post_type = get_post_type( $id );
		$failed    = false;

		foreach ( $this->indexes->all() as $index ) {
			$ids = ( false === $post_type ) ? array() : $index->affected_by( (string) $post_type, $id );
			$own = ( false === $post_type || $index->post_type() === $post_type );
			if ( empty( $ids ) && ! $own ) {
				continue;
			}

			$this->name( $index );
			if ( Index_State::EXISTS !== $index->state() ) {
				\WP_CLI::warning( sprintf( 'Skipped: the index is not set up or not reachable. Run `wp lwtv meili settings --type=%s` first.', $index->type() ) );
				continue;
			}

			if ( $own ) {
				\WP_CLI::log( null === $index->document( $id ) ? "Post {$id} is not a published {$index->type()} post: removing its document." : "Indexing post {$id}." );
				$failed = ! $index->sync( $id ) || $failed;
				continue;
			}

			$sent = $index->sync_many( $ids );
			\WP_CLI::log( sprintf( 'Re-sent %d of %d linked document(s).', $sent, count( $ids ) ) );
			$failed = ( $sent < count( $ids ) ) || $failed;
		}

		if ( $failed ) {
			\WP_CLI::error( 'Sync failed for at least one index; see the meilisearch debug log.' );
		}

		\WP_CLI::success( 'Queued on the search server.' );
	}

	/**
	 * Build one index.
	 *
	 * @param Index $index   Index.
	 * @param bool  $fresh   Delete and recreate first.
	 * @param bool  $dry_run Send nothing.
	 * @param int   $batch   Posts per request.
	 * @return void
	 */
	private function reindex_one( Index $index, bool $fresh, bool $dry_run, int $batch ): void {
		$started = microtime( true );

		if ( $fresh && ! $dry_run ) {
			$this->apply_settings( $index, true );
		} elseif ( ! $dry_run ) {
			// Adding documents would create a missing index with default settings.
			$stats = $this->client->index_stats( $index->uid() );
			if ( Index_State::MISSING === Index_State::classify( is_wp_error( $stats ) ? $stats->get_error_message() : null ) ) {
				\WP_CLI::error( sprintf( '%s is not set up on this environment. Run `wp lwtv meili reindex --type=%s --fresh` (or `wp lwtv meili settings --type=%2$s` first).', $index->uid(), $index->type() ) );
			}
		}

		$built     = 0;
		$sample    = null;
		$published = array();
		$page      = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => $index->post_type(),
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
			$ids   = array_map( 'intval', $query->posts );
			$found = count( $ids );

			if ( empty( $ids ) ) {
				break;
			}

			$published = array_merge( $published, $ids );
			$documents = $index->documents( $ids );
			$built    += count( $documents );
			$sample    = $sample ?? ( $documents[0] ?? null );

			if ( ! $dry_run && ! empty( $documents ) ) {
				$this->wait( $this->client->add_documents( $index->uid(), $documents, 60 ), "batch {$page}" );
			}

			\WP_CLI::log( sprintf( 'Batch %d: %d document(s).', $page, count( $documents ) ) );

			++$page;
			wp_cache_flush_runtime();
		} while ( $found === $batch );

		if ( ! $dry_run && ! $fresh ) {
			$this->prune( $index, $published );
		}

		$elapsed = microtime( true ) - $started;

		if ( $dry_run ) {
			\WP_CLI::log( (string) wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			\WP_CLI::success( sprintf( 'Dry run: built %d document(s) in %.1fs. Nothing was sent.', $built, $elapsed ) );
			return;
		}

		$stats = $this->client->index_stats( $index->uid() );
		$count = is_wp_error( $stats ) ? 'unknown' : (string) (int) ( $stats['numberOfDocuments'] ?? 0 );

		\WP_CLI::success( sprintf( 'Sent %d document(s); index holds %s. Elapsed %.1fs.', $built, $count, $elapsed ) );
	}

	/**
	 * Delete documents for posts that are no longer published.
	 *
	 * @param Index $index     Index.
	 * @param int[] $published Every published post ID this run saw.
	 * @return void
	 */
	private function prune( Index $index, array $published ): void {
		$filter = Prune_Filter::not_in( $published );
		if ( null === $filter ) {
			\WP_CLI::warning( 'No published posts found; skipping the stale-document cleanup.' );
			return;
		}

		$hint   = sprintf( ' If this says `id` is not filterable, run `wp lwtv meili settings --type=%s` first.', $index->type() );
		$result = $this->client->delete_documents_by_filter( $index->uid(), $filter );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( 'Stale-document cleanup: ' . $result->get_error_message() . $hint );
		}

		if ( ! isset( $result['taskUid'] ) ) {
			\WP_CLI::error( 'Stale-document cleanup: the server did not return a task to wait for.' );
		}

		$task = $this->client->wait_for_task( (int) $result['taskUid'], 300 );
		if ( is_wp_error( $task ) ) {
			\WP_CLI::error( 'Stale-document cleanup: ' . $task->get_error_message() . $hint );
		}

		\WP_CLI::log( sprintf( 'Removed %d stale document(s).', (int) ( $task['details']['deletedDocuments'] ?? 0 ) ) );
	}

	/**
	 * Read config and set up the client and indexes.
	 *
	 * @return void
	 */
	private function boot(): void {
		$this->config = Config::from_constants();

		if ( ! $this->config->is_configured() ) {
			\WP_CLI::error( 'Meilisearch is not configured. Define in wp-config.php: ' . implode( ', ', $this->config->missing() ) );
		}

		$this->client  = new Client( $this->config );
		$this->indexes = new Indexes( $this->client, $this->config );
	}

	/**
	 * The indexes --type selects.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return Index[]
	 */
	private function selected( array $assoc_args ): array {
		try {
			$types = Type_Option::parse( isset( $assoc_args['type'] ) ? (string) $assoc_args['type'] : null, $this->indexes->types() );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		return array_values( array_filter( array_map( array( $this->indexes, 'get' ), $types ?? array() ) ) );
	}

	/**
	 * Say which index and host a command is about to touch.
	 *
	 * @param Index $index Index.
	 * @return void
	 */
	private function name( Index $index ): void {
		\WP_CLI::log( sprintf( 'Index: %s on %s', $index->uid(), $this->config->host() ) );
	}

	/**
	 * An index's repo settings file.
	 *
	 * @param Index $index Index.
	 * @return array
	 */
	private function repo_settings( Index $index ): array {
		try {
			return Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		return array();
	}

	/**
	 * Optionally delete the index, create it if missing, and apply settings.
	 *
	 * @param Index $index Index.
	 * @param bool  $fresh Delete first.
	 * @return void
	 */
	private function apply_settings( Index $index, bool $fresh ): void {
		$uid      = $index->uid();
		$settings = $this->repo_settings( $index );

		if ( $fresh ) {
			$deleted = $this->client->delete_index( $uid );
			if ( ! $this->is_missing_index( $deleted ) ) {
				$this->wait( $deleted, 'delete index', true );
			}
			$index->forget_state();
			\WP_CLI::log( "Deleted {$uid} (if it existed)." );
		}

		if ( $this->is_missing_index( $this->client->index_stats( $uid ) ) ) {
			$this->wait( $this->client->create_index( $uid, Index_Settings::PRIMARY_KEY ), 'create index' );
			$index->forget_state();
			\WP_CLI::log( "Created {$uid}." );
		}

		$this->wait( $this->client->update_settings( $uid, $settings ), 'update settings' );
	}

	/**
	 * Whether a response or task says the index does not exist.
	 *
	 * @param array|\WP_Error $result Client result.
	 * @return bool
	 */
	private function is_missing_index( $result ): bool {
		return is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'index_not_found' );
	}

	/**
	 * Wait for the task a call returned; stop on any error.
	 *
	 * @param array|\WP_Error $result       Client result holding a taskUid.
	 * @param string          $label        What the task was, for messages.
	 * @param bool            $allow_absent Treat index_not_found as done.
	 * @return void
	 */
	private function wait( $result, string $label, bool $allow_absent = false ): void {
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( "{$label}: " . $result->get_error_message() );
		}

		if ( ! isset( $result['taskUid'] ) ) {
			\WP_CLI::error( "{$label}: the server did not return a task to wait for." );
		}

		$task = $this->client->wait_for_task( (int) $result['taskUid'], 300 );
		if ( is_wp_error( $task ) && ! ( $allow_absent && $this->is_missing_index( $task ) ) ) {
			\WP_CLI::error( "{$label}: " . $task->get_error_message() );
		}
	}
}

\WP_CLI::add_command( 'lwtv meili', 'WP_CLI_LWTV_Meili' );
