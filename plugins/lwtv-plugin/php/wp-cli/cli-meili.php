<?php
/*
 * WP CLI Commands for the Meilisearch prototype.
 *
 * status reads the server and index (not its version: /version needs a
 * global key, and the indexing key is scoped to this environment's indexes), settings applies the versioned settings
 * file, reindex builds the characters index, and sync re-syncs one character.
 * Every command names the index and host before it touches anything.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Characters;
use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Client;
use LWTV\Meilisearch\Config;
use LWTV\Meilisearch\Indexer;

/**
 * LezWatch.TV command to manage the Meilisearch characters index.
 */
class WP_CLI_LWTV_Meili {

	/**
	 * Characters per batch.
	 */
	public const BATCH = 500;

	private Config $config;
	private Client $client;
	private Indexer $indexer;

	/**
	 * Show server health, the index's document count, and whether
	 * its settings match the repo file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili status
	 *
	 * @subcommand status
	 */
	public function status() {
		$this->boot();

		$health = $this->client->health();
		if ( is_wp_error( $health ) ) {
			\WP_CLI::error( 'Server unreachable: ' . $health->get_error_message() );
		}
		\WP_CLI::log( 'Health: ' . (string) ( $health['status'] ?? 'unknown' ) );

		$stats = $this->client->index_stats( $this->indexer->uid() );
		if ( is_wp_error( $stats ) ) {
			\WP_CLI::warning( 'Index stats: ' . $stats->get_error_message() );
			return;
		}
		\WP_CLI::log( 'Documents: ' . (int) ( $stats['numberOfDocuments'] ?? 0 ) . ( ! empty( $stats['isIndexing'] ) ? ' (indexing)' : '' ) );

		$live = $this->client->get_settings( $this->indexer->uid() );
		if ( is_wp_error( $live ) ) {
			\WP_CLI::warning( 'Settings: ' . $live->get_error_message() );
			return;
		}

		$drift = array();
		foreach ( $this->repo_settings() as $key => $value ) {
			if ( ( $live[ $key ] ?? null ) !== $value ) {
				$drift[] = $key;
			}
		}

		if ( empty( $drift ) ) {
			\WP_CLI::success( 'Settings match the repo file.' );
		} else {
			\WP_CLI::warning( 'Settings differ from the repo file: ' . implode( ', ', $drift ) . '. Run `wp lwtv meili settings`.' );
		}
	}

	/**
	 * Create the index if it is missing and apply the repo settings file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili settings
	 *
	 * @subcommand settings
	 */
	public function settings() {
		$this->boot();
		$this->apply_settings( false );
		\WP_CLI::success( 'Settings applied.' );
	}

	/**
	 * Build the characters index from published characters.
	 *
	 * ## OPTIONS
	 *
	 * [--fresh]
	 * : Delete and recreate the index, and apply settings, first. Also the
	 *   upgrade path after a Meilisearch version change.
	 *
	 * [--batch=<n>]
	 * : Characters per request. Default 500.
	 *
	 * [--dry-run]
	 * : Build documents and print a count and one sample. Sends nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv meili reindex --dry-run
	 *     wp lwtv meili reindex --fresh
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
		$started = microtime( true );

		if ( $fresh && ! $dry_run ) {
			$this->apply_settings( true );
		}

		$built  = 0;
		$sample = null;
		$page   = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => Characters::SLUG,
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

			$documents = $this->indexer->documents( $ids );
			$built    += count( $documents );
			$sample    = $sample ?? ( $documents[0] ?? null );

			if ( ! $dry_run && ! empty( $documents ) ) {
				$this->wait( $this->client->add_documents( $this->indexer->uid(), $documents, 60 ), "batch {$page}" );
			}

			\WP_CLI::log( sprintf( 'Batch %d: %d document(s).', $page, count( $documents ) ) );

			++$page;
			wp_cache_flush_runtime();
		} while ( $found === $batch );

		$elapsed = microtime( true ) - $started;

		if ( $dry_run ) {
			\WP_CLI::log( (string) wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			\WP_CLI::success( sprintf( 'Dry run: built %d document(s) in %.1fs. Nothing was sent.', $built, $elapsed ) );
			return;
		}

		$stats = $this->client->index_stats( $this->indexer->uid() );
		$count = is_wp_error( $stats ) ? 'unknown' : (string) (int) ( $stats['numberOfDocuments'] ?? 0 );

		\WP_CLI::success( sprintf( 'Sent %d document(s); index holds %s. Elapsed %.1fs.', $built, $count, $elapsed ) );
	}

	/**
	 * Sync one character now, adding or removing its document.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Character post ID.
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
			\WP_CLI::error( 'Give a character post ID.' );
		}

		$document = $this->indexer->document( $id );
		\WP_CLI::log( null === $document ? "Character {$id} is not published: removing its document." : "Indexing character {$id}." );

		if ( ! $this->indexer->sync( $id ) ) {
			\WP_CLI::error( 'Sync failed; see the meilisearch debug log.' );
		}

		\WP_CLI::success( 'Queued on the search server.' );
	}

	/**
	 * Read config, set up the client, and name the target index.
	 *
	 * @return void
	 */
	private function boot(): void {
		$this->config = Config::from_constants();

		if ( ! $this->config->is_configured() ) {
			\WP_CLI::error( 'Meilisearch is not configured. Define in wp-config.php: ' . implode( ', ', $this->config->missing() ) );
		}

		$this->client  = new Client( $this->config );
		$this->indexer = new Indexer( $this->client, $this->config );

		\WP_CLI::log( sprintf( 'Index: %s on %s', $this->indexer->uid(), $this->config->host() ) );
	}

	/**
	 * The repo settings file for characters.
	 *
	 * @return array
	 */
	private function repo_settings(): array {
		try {
			return Index_Settings::load( (string) file_get_contents( Index_Settings::path( Indexer::TYPE ) ) );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		return array();
	}

	/**
	 * Optionally delete the index, create it if missing, and apply settings.
	 *
	 * @param bool $fresh Delete first.
	 * @return void
	 */
	private function apply_settings( bool $fresh ): void {
		$uid      = $this->indexer->uid();
		$settings = $this->repo_settings();

		if ( $fresh ) {
			$deleted = $this->client->delete_index( $uid );
			if ( ! $this->is_missing_index( $deleted ) ) {
				$this->wait( $deleted, 'delete index', true );
			}
			\WP_CLI::log( "Deleted {$uid} (if it existed)." );
		}

		if ( $this->is_missing_index( $this->client->index_stats( $uid ) ) ) {
			$this->wait( $this->client->create_index( $uid, Index_Settings::PRIMARY_KEY ), 'create index' );
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

		$task = $this->client->wait_for_task( (int) ( $result['taskUid'] ?? 0 ), 300 );
		if ( is_wp_error( $task ) && ! ( $allow_absent && $this->is_missing_index( $task ) ) ) {
			\WP_CLI::error( "{$label}: " . $task->get_error_message() );
		}
	}
}

\WP_CLI::add_command( 'lwtv meili', 'WP_CLI_LWTV_Meili' );
