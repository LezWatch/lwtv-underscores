<?php
/*
 * WP CLI Commands for the character link table (lwtv_char_links).
 *
 * rebuild rewrites the table from ACF, diff compares it with the shadow
 * taxonomy, and source picks which of the two the site reads from.
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
use LWTV\Theme\Show_Characters;

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
						get_post_meta( $character_id, 'lezchars_relationship_chart', true ),
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
	 * Compare the shadow taxonomy and the link table.
	 *
	 * Prints only the objects that differ. This is a report: it exits 0 either way.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : show (default), actor or character.
	 *
	 * [--id=<id>]
	 * : Only this object.
	 *
	 * [--format=<format>]
	 * : table (default) or csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv links diff --type=show
	 *     wp lwtv links diff --type=character --format=csv
	 *
	 * @subcommand diff
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function diff( $args, $assoc_args = array() ) {
		$type   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'show' );
		$id     = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'id', 0 );
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$slugs = array(
			'show'      => Shows::SLUG,
			'actor'     => Actors::SLUG,
			'character' => Characters::SLUG,
		);

		if ( ! isset( $slugs[ $type ] ) ) {
			\WP_CLI::error( 'Type must be show, actor or character.' );
		}

		$slug = $slugs[ $type ];
		$ids  = null;

		if ( $id > 0 ) {
			if ( get_post_type( $id ) !== $slug ) {
				\WP_CLI::error( $id . ' is not a ' . $type . '.' );
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				\WP_CLI::warning( $id . ' is not published, skipping.' );
				\WP_CLI::log( '0 objects, 0 mismatched' );
				return;
			}
			$ids = array( $id );
		}

		$shadow = ( new Character_Links() )->from( 'shadow' );
		$table  = ( new Character_Links() )->from( 'table' );

		$rows    = array();
		$objects = 0;

		$progress = ( null === $ids ) ? \WP_CLI\Utils\make_progress_bar( 'Comparing ' . $type . 's', $this->count_posts( $slug, array( 'publish' ) ) ) : null;

		foreach ( $this->batches( $slug, array( 'publish' ), $ids ) as $batch ) {
			foreach ( $batch as $object_id ) {
				++$objects;

				switch ( $type ) {
					case 'show':
						$from_shadow = $shadow->characters_for_show( $object_id );
						$from_table  = $table->characters_for_show( $object_id );
						break;
					case 'actor':
						$from_shadow = $shadow->characters_for_actor( $object_id );
						$from_table  = $table->characters_for_actor( $object_id );
						break;
					default:
						$from_shadow = $shadow->characters_referencing( $object_id );
						$from_table  = $table->characters_referencing( $object_id );
				}

				$from_shadow = $this->published_characters( $from_shadow );
				$from_table  = $this->published_characters( $from_table );

				$diff = Link_Rows::compare( $from_shadow, $from_table );

				if ( empty( $diff['missing'] ) && empty( $diff['extra'] ) ) {
					if ( $progress ) {
						$progress->tick();
					}
					continue;
				}

				$rows[] = array(
					'id'               => $object_id,
					'title'            => html_entity_decode( get_the_title( $object_id ), ENT_QUOTES, 'UTF-8' ),
					'missing_in_table' => implode( ' ', $diff['missing'] ),
					'extra_in_table'   => implode( ' ', $diff['extra'] ),
					'acf_agrees'       => $this->acf_agrees( $type, $object_id, array_merge( $diff['missing'], $diff['extra'] ) ),
				);

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
			\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'title', 'missing_in_table', 'extra_in_table', 'acf_agrees' ) );
		}

		\WP_CLI::log( sprintf( '%d objects, %d mismatched', $objects, count( $rows ) ) );
	}

	/**
	 * Show or set where the site reads character links from.
	 *
	 * ## OPTIONS
	 *
	 * [<source>]
	 * : shadow or table. Omit to print the current source.
	 *
	 * [--force]
	 * : Switch to table even when the link table is missing or empty.
	 *   Without it, run `wp lwtv links rebuild` first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv links source
	 *     wp lwtv links source table
	 *     wp lwtv links source table --force
	 *
	 * @subcommand source
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function source( $args, $assoc_args = array() ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::log( ( new Character_Links() )->source() );
			return;
		}

		$value = (string) $args[0];

		if ( ! in_array( $value, array( 'shadow', 'table' ), true ) ) {
			\WP_CLI::error( 'Source must be shadow or table.' );
		}

		if ( 'table' === $value && ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			global $wpdb;
			$table = Store::table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );

			if ( ! $exists || 0 === Store::count() ) {
				\WP_CLI::error( 'The link table is missing or empty. Run `wp lwtv links rebuild` first, or pass --force to switch anyway.' );
			}
		}

		// update_option() refreshes the option cache; no full cache flush is needed.
		update_option( Character_Links::SOURCE_OPTION, $value );
		Show_Characters::flush_cache();

		\WP_CLI::success( 'Source is now ' . ( new Character_Links() )->source() );
	}

	/**
	 * Does each differing character's ACF say the link exists?
	 *
	 * @param string $type      show, actor or character.
	 * @param int    $object_id The show or actor (or character).
	 * @param int[]  $ids       Differing character IDs.
	 *
	 * @return string e.g. "123:acf=yes 456:acf=no", or n/a for characters.
	 */
	private function acf_agrees( string $type, int $object_id, array $ids ): string {
		if ( 'character' === $type ) {
			return 'n/a';
		}

		$out = array();

		foreach ( $ids as $character_id ) {
			$character_id = (int) $character_id;

			if ( 'show' === $type ) {
				$rows = Link_Rows::from_fields( $character_id, get_field( 'lezchars_show_group', $character_id ), array() );
			} else {
				$rows = Link_Rows::from_fields( $character_id, array(), get_field( 'lezchars_actor', $character_id ) );
			}

			$out[] = $character_id . ':acf=' . ( in_array( $object_id, Link_Rows::ids_of( $rows, $type ), true ) ? 'yes' : 'no' );
		}

		return implode( ' ', $out );
	}

	/**
	 * Keep only published characters.
	 *
	 * @param int[] $ids Post IDs.
	 *
	 * @return int[]
	 */
	private function published_characters( array $ids ): array {
		$keep = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( 'publish' === get_post_status( $id ) && Characters::SLUG === get_post_type( $id ) ) {
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
