<?php
/**
 * Name: Shadow Purge
 * Description: Counts and deletes the retired shadow taxonomy's data.
 *
 * The shadow taxonomy (shadow_tax_characters) was replaced by the
 * lwtv_char_links table in 7.4.5 and stopped being written in 7.5.0. This
 * class removes what it left behind: its term_relationships, term_taxonomy,
 * terms and termmeta rows, the character postmeta that pointed at each term,
 * and AIOSEO's per-term rows.
 *
 * It works with $wpdb directly, so it does not need the taxonomy to be
 * registered. Every delete runs in batches, one transaction per batch.
 * `wp lwtv links purge-shadow` is the only caller.
 *
 * @package LWTV
 */

namespace LWTV\Relationships;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shadow_Purge {

	/**
	 * The retired taxonomy. A literal, not Characters::SHADOW_TAXONOMY, so this
	 * keeps working after that constant is removed.
	 */
	const TAXONOMY = 'shadow_tax_characters';

	/**
	 * Term meta on a shadow term that holds its character's post ID.
	 */
	const TERM_META_KEY = 'shadow_shadow_tax_characters_post_id';

	/**
	 * Post meta on a character that holds its shadow term ID.
	 */
	const POST_META_KEY = 'shadow_shadow_tax_characters_term_id';

	/**
	 * Default rows per batch.
	 */
	const DEFAULT_BATCH = 500;

	/**
	 * What is left to delete, plus related data that is reported but kept.
	 *
	 * @return array{
	 *     terms:int,
	 *     relationships:int,
	 *     termmeta:int,
	 *     orphan_termmeta:int,
	 *     postmeta:int,
	 *     aioseo_terms:int|null,
	 *     shared_terms:int,
	 *     relationships_by_type:array<string,int>,
	 *     relationship_chart_meta:int,
	 *     other_orphan_relationships:int
	 * }
	 */
	public static function counts(): array {
		global $wpdb;

		$tax    = self::TAXONOMY;
		$aioseo = self::aioseo_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off maintenance report.
		$counts = array(
			'terms'                      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ) ),
			'relationships'              => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s", $tax ) ),
			'termmeta'                   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tt.taxonomy = %s", $tax ) ),
			'orphan_termmeta'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = %s AND tt.term_id IS NULL", self::TERM_META_KEY ) ),
			'postmeta'                   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::POST_META_KEY ) ),
			'aioseo_terms'               => null,
			'shared_terms'               => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} a INNER JOIN {$wpdb->term_taxonomy} b ON b.term_id = a.term_id AND b.term_taxonomy_id <> a.term_taxonomy_id WHERE a.taxonomy = %s", $tax ) ),
			'relationships_by_type'      => array(),
			'relationship_chart_meta'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", 'lezchars_relationship_chart' ) ),
			'other_orphan_relationships' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_taxonomy_id IS NULL" ),
		);

		if ( null !== $aioseo ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is $wpdb->prefix based and confirmed by SHOW TABLES.
			$counts['aioseo_terms'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$aioseo} a INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = a.term_id WHERE tt.taxonomy = %s", $tax ) );
		}

		$by_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(p.post_type, '(missing post)') AS post_type, COUNT(*) AS total FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s GROUP BY p.post_type ORDER BY total DESC",
				$tax
			),
			ARRAY_A
		);
		// phpcs:enable

		foreach ( (array) $by_type as $row ) {
			$counts['relationships_by_type'][ (string) $row['post_type'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Delete one batch of shadow terms with their relationships and meta.
	 *
	 * Takes the lowest term_taxonomy_ids first. Terms rows are deleted only
	 * when no other taxonomy still uses the term_id (pre-4.2 shared terms).
	 *
	 * @param int $limit Terms per batch.
	 *
	 * @return array{terms:int, relationships:int, termmeta:int, aioseo_terms:int, term_rows:int} All zero when nothing was left.
	 *
	 * @throws \RuntimeException When a query fails. The batch is rolled back.
	 */
	public static function purge_terms_batch( int $limit ): array {
		global $wpdb;

		$done = array(
			'terms'         => 0,
			'relationships' => 0,
			'termmeta'      => 0,
			'aioseo_terms'  => 0,
			'term_rows'     => 0,
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off maintenance; table names come from $wpdb, IN() placeholders are generated and $steps holds only prepared statements.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s ORDER BY term_taxonomy_id ASC LIMIT %d",
				self::TAXONOMY,
				max( 1, $limit )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return $done;
		}

		$tt_ids   = array_map( 'intval', array_column( $rows, 'term_taxonomy_id' ) );
		$term_ids = array_map( 'intval', array_column( $rows, 'term_id' ) );
		$tt_in    = self::placeholders( $tt_ids );
		$term_in  = self::placeholders( $term_ids );
		$aioseo   = self::aioseo_table();

		$object_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_in})", $tt_ids ) ) );

		$wpdb->query( 'START TRANSACTION' );

		$steps = array(
			'relationships' => $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_in})", $tt_ids ),
			'termmeta'      => $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$term_in})", $term_ids ),
			'aioseo_terms'  => ( null !== $aioseo ) ? $wpdb->prepare( "DELETE FROM {$aioseo} WHERE term_id IN ({$term_in})", $term_ids ) : null,
			'terms'         => $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_taxonomy_id IN ({$tt_in})", array_merge( array( self::TAXONOMY ), $tt_ids ) ),
			'term_rows'     => $wpdb->prepare( "DELETE t FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.term_id IN ({$term_in}) AND tt.term_id IS NULL", $term_ids ),
		);

		foreach ( $steps as $step => $sql ) {
			if ( null === $sql ) {
				continue;
			}

			$result = $wpdb->query( $sql );

			if ( false === $result ) {
				$error = $wpdb->last_error;
				$wpdb->query( 'ROLLBACK' );
				throw new \RuntimeException( esc_html( sprintf( 'Deleting %s failed, batch rolled back: %s', $step, $error ) ) );
			}

			$done[ $step ] = (int) $result;
		}

		$wpdb->query( 'COMMIT' );
		// phpcs:enable

		clean_term_cache( $term_ids, self::TAXONOMY, false );
		wp_cache_delete_multiple( $object_ids, self::TAXONOMY . '_relationships' );

		return $done;
	}

	/**
	 * Delete one batch of the character postmeta that held each shadow term ID.
	 *
	 * @param int $limit Rows per batch.
	 *
	 * @return int Rows deleted; 0 when nothing was left.
	 *
	 * @throws \RuntimeException When the delete fails.
	 */
	public static function purge_postmeta_batch( int $limit ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off maintenance; IN() placeholders are generated.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC LIMIT %d",
				self::POST_META_KEY,
				max( 1, $limit )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$meta_ids = array_map( 'intval', array_column( $rows, 'meta_id' ) );
		$in       = self::placeholders( $meta_ids );

		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_id IN ({$in})", array_merge( array( self::POST_META_KEY ), $meta_ids ) ) );
		// phpcs:enable

		if ( false === $result ) {
			throw new \RuntimeException( esc_html( 'Deleting postmeta failed: ' . $wpdb->last_error ) );
		}

		wp_cache_delete_multiple( array_unique( array_map( 'intval', array_column( $rows, 'post_id' ) ) ), 'post_meta' );

		return (int) $result;
	}

	/**
	 * Delete shadow term meta whose term is already gone.
	 *
	 * @return int Rows deleted.
	 *
	 * @throws \RuntimeException When the delete fails.
	 */
	public static function purge_orphan_termmeta(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off maintenance.
		$result = $wpdb->query( $wpdb->prepare( "DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = %s AND tt.term_id IS NULL", self::TERM_META_KEY ) );

		if ( false === $result ) {
			throw new \RuntimeException( esc_html( 'Deleting orphan termmeta failed: ' . $wpdb->last_error ) );
		}

		return (int) $result;
	}

	/**
	 * Clear the taxonomy-wide caches once everything is deleted.
	 *
	 * @return void
	 */
	public static function finish(): void {
		delete_option( self::TAXONOMY . '_children' );
		clean_taxonomy_cache( self::TAXONOMY );
	}

	/**
	 * Every character-to-character row in the link table.
	 *
	 * @return array<int, array{character_id:int, object_id:int, object_type:string}>
	 */
	public static function character_rows(): array {
		global $wpdb;
		$table = Store::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; table name comes from $wpdb.
		$rows = $wpdb->get_results( "SELECT character_id, object_id, object_type FROM {$table} WHERE object_type = 'character'", ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * AIOSEO's per-term table, when it exists.
	 *
	 * @return string|null Table name, or null.
	 */
	private static function aioseo_table(): ?string {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_terms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return ( $found === $table ) ? $table : null;
	}

	/**
	 * A comma-separated list of %d placeholders.
	 *
	 * @param int[] $ids IDs.
	 *
	 * @return string
	 */
	private static function placeholders( array $ids ): string {
		return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	}
}
