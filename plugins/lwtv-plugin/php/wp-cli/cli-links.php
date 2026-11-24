<?php
/*
 * WP CLI Commands for the character link table (lwtv_char_links).
 *
 * rebuild rewrites the table from ACF, diff checks the table against ACF
 * for drift, and purge-shadow deletes the retired shadow taxonomy's data.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Characters;
use LWTV\CPTs\Shows;
use LWTV\Relationships\Build\Link_Rows;
use LWTV\Relationships\Character_Links;
use LWTV\Relationships\Shadow_Purge;
use LWTV\Relationships\Store;
use LWTV\Relationships\Sync;

/**
 * LezWatch.TV command to manage character links.
 */
class WP_CLI_LWTV_Links {

	/**
	 * Posts fetched per batch.
	 */
	public const BATCH = 200;

	/**
	 * Rebuild the character link table.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Only this character.
	 *
	 * [--legacy-characters]
	 * : Import character-to-character links from the relationship chart and
	 *   the shadow taxonomy instead of rewriting show and actor links from ACF.
	 *   Deprecated: kept until Phase 6 purges the shadow terms.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv links rebuild
	 *     wp lwtv links rebuild --legacy-characters
	 *     wp lwtv links rebuild --id=123
	 *
	 * @subcommand rebuild
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function rebuild( $args, $assoc_args = array() ) {
		$id     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'id', 0 );
		$legacy = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'legacy-characters', false );

		$ids = ( $id > 0 ) ? array( $id ) : null;

		if ( $id > 0 && Characters::SLUG !== get_post_type( $id ) ) {
			\WP_CLI::error( $id . ' is not a character.' );
		}

		$before = Store::count();
		$done   = 0;
		$sent   = 0;
		$links  = new Character_Links();

		$progress = ( null === $ids ) ? \WP_CLI\Utils\make_progress_bar( 'Characters', $this->count_posts( Characters::SLUG, array( 'any', 'trash' ) ) ) : null;

		foreach ( $this->batches( Characters::SLUG, array( 'any', 'trash' ), $ids ) as $batch ) {
			foreach ( $batch as $character_id ) {
				if ( $legacy ) {
					$rows = Link_Rows::legacy_characters(
						$character_id,
						$links->legacy_chart_targets( $character_id ),
						$links->legacy_holders( $character_id )
					);

					if ( ! empty( $rows ) ) {
						Store::insert( $rows );
						$sent += count( $rows );
					}
				} else {
					Sync::character( $character_id );
				}

				++$done;

				if ( $progress ) {
					$progress->tick();
				}
			}

			$this->free_memory();
		}

		if ( $progress ) {
			$progress->finish();
		}

		$after = Store::count();

		if ( $legacy ) {
			\WP_CLI::success( sprintf( '%d characters read, %d character rows sent. Table rows: %d before, %d after.', $done, $sent, $before, $after ) );
			return;
		}

		\WP_CLI::success( sprintf( '%d characters synced. Table rows: %d before, %d after.', $done, $before, $after ) );
	}

	/**
	 * Compare the link table with ACF, character by character.
	 *
	 * ACF (lezchars_show_group and lezchars_actor) is the source of truth, and
	 * every character save rewrites the table from it, so they should agree.
	 * Prints only the characters that differ. Only published objects are
	 * compared, the same as the site reads. Character-to-character links are
	 * legacy imports with no ACF source, so they are not checked.
	 *
	 * This is a report: it exits 0 either way.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : show, actor or all (default).
	 *
	 * [--id=<id>]
	 * : Only this character.
	 *
	 * [--format=<format>]
	 * : table (default) or csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv links diff
	 *     wp lwtv links diff --type=actor --format=csv
	 *     wp lwtv links diff --id=123
	 *
	 * @subcommand diff
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function diff( $args, $assoc_args = array() ) {
		$type   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'all' );
		$id     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'id', 0 );
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$slugs = array(
			'show'  => Shows::SLUG,
			'actor' => Actors::SLUG,
		);

		if ( 'all' !== $type && ! isset( $slugs[ $type ] ) ) {
			\WP_CLI::error( 'Type must be show, actor or all.' );
		}

		$types = ( 'all' === $type ) ? array_keys( $slugs ) : array( $type );

		$ids = null;

		if ( $id > 0 ) {
			if ( Characters::SLUG !== get_post_type( $id ) ) {
				\WP_CLI::error( $id . ' is not a character.' );
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				\WP_CLI::warning( $id . ' is not published, skipping.' );
				\WP_CLI::log( '0 characters, 0 mismatched' );
				return;
			}
			$ids = array( $id );
		}

		$links   = new Character_Links();
		$rows    = array();
		$checked = 0;

		$progress = ( null === $ids ) ? \WP_CLI\Utils\make_progress_bar( 'Comparing characters', $this->count_posts( Characters::SLUG, array( 'publish' ) ) ) : null;

		foreach ( $this->batches( Characters::SLUG, array( 'publish' ), $ids ) as $batch ) {
			foreach ( $batch as $character_id ) {
				++$checked;

				$acf = Link_Rows::from_fields(
					$character_id,
					get_field( 'lezchars_show_group', $character_id ),
					get_field( 'lezchars_actor', $character_id )
				);

				foreach ( $types as $object_type ) {
					$from_acf   = $this->published_of_type( Link_Rows::ids_of( $acf, $object_type ), $slugs[ $object_type ] );
					$from_table = ( 'show' === $object_type ) ? $links->shows_for_character( $character_id ) : $links->actors_for_character( $character_id );

					$diff = Link_Rows::compare( $from_acf, $from_table );

					if ( empty( $diff['missing'] ) && empty( $diff['extra'] ) ) {
						continue;
					}

					$rows[] = array(
						'id'               => $character_id,
						'title'            => html_entity_decode( get_the_title( $character_id ), ENT_QUOTES, 'UTF-8' ),
						'type'             => $object_type,
						'missing_in_table' => implode( ' ', $diff['missing'] ),
						'extra_in_table'   => implode( ' ', $diff['extra'] ),
					);
				}

				if ( $progress ) {
					$progress->tick();
				}
			}

			$this->free_memory();
		}

		if ( $progress ) {
			$progress->finish();
		}

		if ( ! empty( $rows ) ) {
			\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'title', 'type', 'missing_in_table', 'extra_in_table' ) );
			\WP_CLI::log( 'Fix a character with `wp lwtv links rebuild --id=<id>`.' );
		}

		\WP_CLI::log( sprintf( '%d characters, %d mismatched', $checked, count( array_unique( array_column( $rows, 'id' ) ) ) ) );
	}

	/**
	 * Delete the retired shadow taxonomy's data. Dry run unless --execute.
	 *
	 * Removes every shadow_tax_characters term with its term_relationships,
	 * term_taxonomy, terms and termmeta rows, AIOSEO's rows for those terms,
	 * and the shadow_shadow_tax_characters_term_id postmeta on characters.
	 * Deletes run in batches, one transaction per batch.
	 *
	 * Before deleting, it checks that every legacy character-to-character link
	 * the shadow terms hold is already in the link table, because the purge
	 * destroys the only copy. If any are missing, run
	 * `wp lwtv links rebuild --legacy-characters` first.
	 *
	 * This cannot be undone. Back up the database first.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be deleted and run the safety checks. This is the
	 *   default.
	 *
	 * [--execute]
	 * : Actually delete.
	 *
	 * [--batch=<number>]
	 * : Terms (or postmeta rows) per batch. Default 500.
	 *
	 * [--skip-legacy-check]
	 * : Skip the legacy character link check. Only for when the taxonomy is no
	 *   longer registered and the check cannot run.
	 *
	 * [--yes]
	 * : Don't ask for confirmation before deleting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv links purge-shadow
	 *     wp lwtv links purge-shadow --execute
	 *     wp lwtv links purge-shadow --execute --batch=200 --yes
	 *
	 * @subcommand purge-shadow
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function purge_shadow( $args, $assoc_args = array() ) {
		$execute = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'execute', false );
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$batch   = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', Shadow_Purge::DEFAULT_BATCH );
		$skip    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-legacy-check', false );

		if ( $execute && $dry_run ) {
			\WP_CLI::error( 'Use --dry-run or --execute, not both.' );
		}

		if ( $batch < 1 || $batch > 5000 ) {
			\WP_CLI::error( '--batch must be between 1 and 5000.' );
		}

		$counts = Shadow_Purge::counts();
		$this->print_shadow_counts( $counts );

		$nothing = ( 0 === $counts['terms'] && 0 === $counts['postmeta'] && 0 === $counts['orphan_termmeta'] );

		if ( $nothing ) {
			\WP_CLI::success( 'No shadow taxonomy data left.' );
			return;
		}

		if ( $counts['shared_terms'] > 0 ) {
			\WP_CLI::error( sprintf( '%d shadow terms share a term_id with another taxonomy. Deleting their terms and termmeta rows would affect that taxonomy, so this needs a manual look.', $counts['shared_terms'] ) );
		}

		$missing = $skip ? null : $this->missing_legacy_rows();

		if ( null === $missing ) {
			\WP_CLI::warning( 'Legacy character link check skipped.' );
		} elseif ( ! empty( $missing ) ) {
			\WP_CLI::warning( sprintf( '%d legacy character links exist only as shadow terms and would be lost:', count( $missing ) ) );
			\WP_CLI\Utils\format_items( 'table', array_slice( $missing, 0, 20 ), array( 'character_id', 'object_id', 'object_type' ) );
			if ( count( $missing ) > 20 ) {
				\WP_CLI::log( sprintf( '…and %d more.', count( $missing ) - 20 ) );
			}
		} else {
			\WP_CLI::log( 'Legacy character links: all present in the link table.' );
		}

		if ( ! $execute ) {
			\WP_CLI::log( '' );
			\WP_CLI::success( 'Dry run: nothing was deleted. Back up the database, then re-run with --execute.' );
			return;
		}

		if ( ! empty( $missing ) ) {
			\WP_CLI::error( 'Run `wp lwtv links rebuild --legacy-characters`, then try again.' );
		}

		\WP_CLI::confirm( 'Permanently delete the shadow taxonomy data above? Make sure you have a database backup.', $assoc_args );

		$totals = array(
			'terms'           => 0,
			'relationships'   => 0,
			'termmeta'        => 0,
			'aioseo_terms'    => 0,
			'term_rows'       => 0,
			'postmeta'        => 0,
			'orphan_termmeta' => 0,
		);

		try {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting shadow terms', $counts['terms'] );

			do {
				$done = Shadow_Purge::purge_terms_batch( $batch );

				foreach ( $done as $key => $value ) {
					$totals[ $key ] += $value;
				}

				$progress->tick( $done['terms'] );
				$this->free_memory();
			} while ( $done['terms'] > 0 );

			$progress->finish();

			$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting character postmeta', $counts['postmeta'] );

			do {
				$deleted             = Shadow_Purge::purge_postmeta_batch( $batch );
				$totals['postmeta'] += $deleted;
				$progress->tick( $deleted );
			} while ( $deleted > 0 );

			$progress->finish();

			$totals['orphan_termmeta'] = Shadow_Purge::purge_orphan_termmeta();
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() . ' Earlier batches were committed; re-run to continue.' );
		}

		Shadow_Purge::finish();

		\WP_CLI::success(
			sprintf(
				'Deleted %d terms (%d term rows), %d relationships, %d termmeta (+%d orphaned), %d AIOSEO rows and %d postmeta.',
				$totals['terms'],
				$totals['term_rows'],
				$totals['relationships'],
				$totals['termmeta'],
				$totals['orphan_termmeta'],
				$totals['aioseo_terms'],
				$totals['postmeta']
			)
		);

		$this->print_shadow_counts( Shadow_Purge::counts() );
	}

	/**
	 * Print the purge report.
	 *
	 * @param array $counts Shadow_Purge::counts() output.
	 *
	 * @return void
	 */
	private function print_shadow_counts( array $counts ): void {
		$rows = array(
			array(
				'data'   => 'term_taxonomy (' . Shadow_Purge::TAXONOMY . ')',
				'rows'   => $counts['terms'],
				'action' => 'delete',
			),
			array(
				'data'   => 'term_relationships',
				'rows'   => $counts['relationships'],
				'action' => 'delete',
			),
			array(
				'data'   => 'termmeta',
				'rows'   => $counts['termmeta'],
				'action' => 'delete',
			),
			array(
				'data'   => 'termmeta (orphaned ' . Shadow_Purge::TERM_META_KEY . ')',
				'rows'   => $counts['orphan_termmeta'],
				'action' => 'delete',
			),
			array(
				'data'   => 'postmeta (' . Shadow_Purge::POST_META_KEY . ')',
				'rows'   => $counts['postmeta'],
				'action' => 'delete',
			),
			array(
				'data'   => 'aioseo_terms',
				'rows'   => ( null === $counts['aioseo_terms'] ) ? 'no table' : $counts['aioseo_terms'],
				'action' => 'delete',
			),
			array(
				'data'   => 'shared term_ids (must be 0)',
				'rows'   => $counts['shared_terms'],
				'action' => 'check',
			),
			array(
				'data'   => 'postmeta (lezchars_relationship_chart)',
				'rows'   => $counts['relationship_chart_meta'],
				'action' => 'keep',
			),
			array(
				'data'   => 'term_relationships with no taxonomy (any)',
				'rows'   => $counts['other_orphan_relationships'],
				'action' => 'keep',
			),
		);

		foreach ( $counts['relationships_by_type'] as $post_type => $total ) {
			$rows[] = array(
				'data'   => '  relationships on ' . $post_type,
				'rows'   => $total,
				'action' => 'delete',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'data', 'rows', 'action' ) );
	}

	/**
	 * Legacy character links that the shadow terms hold but the table lacks.
	 *
	 * Rebuilds the same rows `rebuild --legacy-characters` would import and
	 * compares them with the table. Needs the taxonomy registered.
	 *
	 * Exits with an error when the taxonomy is not registered.
	 *
	 * @return array Missing rows.
	 */
	private function missing_legacy_rows(): array {
		if ( ! taxonomy_exists( Shadow_Purge::TAXONOMY ) ) {
			\WP_CLI::error( 'The shadow taxonomy is not registered, so the legacy link check cannot run. Use --skip-legacy-check if the import is known to be done.' );
		}

		$links    = new Character_Links();
		$expected = array();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Checking legacy character links', $this->count_posts( Characters::SLUG, array( 'any', 'trash' ) ) );

		foreach ( $this->batches( Characters::SLUG, array( 'any', 'trash' ), null ) as $batch ) {
			foreach ( $batch as $character_id ) {
				$rows = Link_Rows::legacy_characters(
					$character_id,
					$links->legacy_chart_targets( $character_id ),
					$links->legacy_holders( $character_id )
				);

				foreach ( $rows as $row ) {
					$expected[] = $row;
				}

				$progress->tick();
			}

			$this->free_memory();
		}

		$progress->finish();

		return Link_Rows::missing_rows( $expected, Shadow_Purge::character_rows() );
	}

	/**
	 * Keep only published posts of one type.
	 *
	 * @param int[]  $ids       Post IDs.
	 * @param string $post_type Post type to keep.
	 *
	 * @return int[]
	 */
	private function published_of_type( array $ids, string $post_type ): array {
		$keep = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( 'publish' === get_post_status( $id ) && get_post_type( $id ) === $post_type ) {
				$keep[] = $id;
			}
		}

		return $keep;
	}

	/**
	 * Post IDs in batches, ascending.
	 *
	 * @param string     $post_type Post type.
	 * @param string[]   $statuses  Post statuses.
	 * @param int[]|null $only      A fixed list of IDs, yielded as one batch.
	 *
	 * @return \Generator
	 */
	private function batches( string $post_type, array $statuses, ?array $only ): \Generator {
		if ( null !== $only ) {
			yield $only;
			return;
		}

		$paged = 1;

		do {
			$ids = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => $statuses,
					'fields'         => 'ids',
					'posts_per_page' => self::BATCH,
					'paged'          => $paged,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $ids ) ) {
				yield array_map( 'intval', $ids );
			}

			++$paged;
		} while ( ! empty( $ids ) );
	}

	/**
	 * Count posts for a progress bar.
	 *
	 * @param string   $post_type Post type.
	 * @param string[] $statuses  Post statuses.
	 *
	 * @return int
	 */
	private function count_posts( string $post_type, array $statuses ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => $statuses,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Keep memory bounded between batches.
	 */
	private function free_memory(): void {
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} else {
			wp_cache_flush();
		}
	}
}

\WP_CLI::add_command( 'lwtv links', 'WP_CLI_LWTV_Links' );
