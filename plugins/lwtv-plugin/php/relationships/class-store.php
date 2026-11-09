<?php
/**
 * Name: Store
 * Description: The custom character link table, its install and its queries.
 *
 * Rows are array{character_id:int, object_id:int, object_type:string}, as built
 * by LWTV\Relationships\Build\Link_Rows. Reads return published posts only.
 *
 * @package LWTV
 */

namespace LWTV\Relationships;

use LWTV\Relationships\Build\Link_Rows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Store {

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'lwtv_char_links_db_version';

	/**
	 * Transient set when the table could not be created, to stop retries.
	 */
	const INSTALL_FAILED_TRANSIENT = 'lwtv_char_links_install_failed';

	/**
	 * Rows per INSERT statement.
	 */
	const BATCH_SIZE = 500;

	/**
	 * Allowed object types.
	 */
	const TYPES = Link_Rows::TYPES;

	/**
	 * Table name, with prefix.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lwtv_char_links';
	}

	/**
	 * Create or update the table when the stored schema version is stale.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		if ( false !== get_transient( self::INSTALL_FAILED_TRANSIENT ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  character_id BIGINT UNSIGNED NOT NULL,
  object_id BIGINT UNSIGNED NOT NULL,
  object_type VARCHAR(20) NOT NULL,
  PRIMARY KEY  (character_id, object_type, object_id),
  KEY reverse (object_id, object_type)
) {$charset};";

		dbDelta( $sql );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			lwtv_plugin()->debug_log( 'char-links', 'maybe_install(): table was not created: ' . $wpdb->last_error );
			set_transient( self::INSTALL_FAILED_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );
			return;
		}

		delete_transient( self::INSTALL_FAILED_TRANSIENT );

		// Autoloaded, so reading it is never a not-found lookup. No-op if it exists.
		add_option( Character_Links::SOURCE_OPTION, 'shadow' );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Replace a character's rows of the given types, in one transaction.
	 *
	 * Rows whose character_id differs from $character_id are skipped.
	 *
	 * @param int      $character_id Character post ID.
	 * @param array    $rows         Link rows.
	 * @param string[] $types        Object types to replace.
	 *
	 * @return void
	 */
	public static function replace_for_character( int $character_id, array $rows, array $types ): void {
		global $wpdb;

		$types = array_values( array_intersect( array_map( 'strval', $types ), self::TYPES ) );
		if ( empty( $types ) ) {
			return;
		}

		$rows = Link_Rows::only( $rows, $character_id, $types );

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( array( $character_id ), $types );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, transaction control.
		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table; table name is $wpdb->prefix based and placeholders are generated.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE character_id = %d AND object_type IN ({$placeholders})", $args ) );

		$ok = ( false !== $deleted );

		if ( $ok && ! empty( $rows ) ) {
			foreach ( array_chunk( $rows, self::BATCH_SIZE ) as $chunk ) {
				if ( false === self::insert_chunk( $chunk ) ) {
					$ok = false;
					break;
				}
			}
		}

		if ( $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction control.
			$wpdb->query( 'COMMIT' );
			return;
		}

		// Read the error first: query() flushes last_error.
		$error = $wpdb->last_error;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transaction control.
		$wpdb->query( 'ROLLBACK' );
		lwtv_plugin()->debug_log( 'char-links', sprintf( 'replace_for_character(%d) failed and was rolled back: %s', $character_id, $error ) );
	}

	/**
	 * Insert rows, ignoring duplicates. Used by the legacy import.
	 *
	 * @param array $rows Link rows.
	 *
	 * @return void
	 */
	public static function insert( array $rows ): void {
		global $wpdb;

		foreach ( array_chunk( array_values( $rows ), self::BATCH_SIZE ) as $chunk ) {
			if ( false === self::insert_chunk( $chunk ) ) {
				lwtv_plugin()->debug_log( 'char-links', 'insert() failed: ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Published objects of a type that a character links to.
	 *
	 * @param int    $character_id Character post ID.
	 * @param string $object_type  'show', 'actor' or 'character'.
	 *
	 * @return int[]
	 */
	public static function objects_for_character( int $character_id, string $object_type ): array {
		global $wpdb;
		$table = self::table();

		$sql = "SELECT l.object_id FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.object_id WHERE l.character_id = %d AND l.object_type = %s AND p.post_status = 'publish' ORDER BY p.post_title ASC, l.object_id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; table name comes from $wpdb, values are prepared.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $character_id, $object_type ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Published characters that link to an object.
	 *
	 * @param int    $object_id   Object post ID.
	 * @param string $object_type 'show', 'actor' or 'character'.
	 *
	 * @return int[]
	 */
	public static function characters_for_object( int $object_id, string $object_type ): array {
		global $wpdb;
		$table = self::table();

		$sql = "SELECT l.character_id FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.character_id WHERE l.object_id = %d AND l.object_type = %s AND p.post_status = 'publish' ORDER BY p.post_title ASC, l.character_id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; table name comes from $wpdb, values are prepared.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $object_id, $object_type ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Published characters linked to a character, in either direction.
	 *
	 * @param int $character_id Character post ID.
	 *
	 * @return int[]
	 */
	public static function characters_linked_to_character( int $character_id ): array {
		global $wpdb;
		$table = self::table();

		$sql = "SELECT l.object_id AS linked_id FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.object_id WHERE l.character_id = %d AND l.object_type = 'character' AND p.post_status = 'publish'
				UNION
				SELECT l.character_id AS linked_id FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.character_id WHERE l.object_id = %d AND l.object_type = 'character' AND p.post_status = 'publish'
				ORDER BY linked_id";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; table name comes from $wpdb, values are prepared.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $character_id, $character_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Delete every row that mentions a post, as the character or as the object.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function delete_for_post( int $post_id ): void {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; table name comes from $wpdb.
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE character_id = %d OR object_id = %d", $post_id, $post_id ) );

		if ( false === $result ) {
			lwtv_plugin()->debug_log( 'char-links', sprintf( 'delete_for_post(%d) failed: %s', $post_id, $wpdb->last_error ) );
		}
	}

	/**
	 * Total rows in the table.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Static query; table name comes from $wpdb.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * One multi-row INSERT IGNORE.
	 *
	 * @param array $chunk Link rows.
	 *
	 * @return int|bool Query result, or false on failure.
	 */
	private static function insert_chunk( array $chunk ) {
		global $wpdb;

		$table  = self::table();
		$values = array();
		$args   = array();

		foreach ( $chunk as $row ) {
			if ( ! isset( $row['character_id'], $row['object_id'], $row['object_type'] ) || ! in_array( $row['object_type'], self::TYPES, true ) ) {
				continue;
			}
			$values[] = '(%d,%d,%s)';
			$args[]   = (int) $row['character_id'];
			$args[]   = (int) $row['object_id'];
			$args[]   = (string) $row['object_type'];
		}

		if ( empty( $values ) ) {
			return 0;
		}

		$sql = "INSERT IGNORE INTO {$table} (character_id, object_id, object_type) VALUES " . implode( ',', $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; placeholders generated above, table name from $wpdb.
		return $wpdb->query( $wpdb->prepare( $sql, $args ) );
	}
}
