<?php
/*
 * WP CLI Commands for the character link table (lwtv_char_links).
 *
 * rebuild rewrites the table from ACF, and diff checks the table against ACF
 * for drift.
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
