<?php
/**
 * Name: LWTV Lookup
 * Description: Finds existing LWTV shows and their characters by title.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Build\Name_Match;

class Lwtv_Lookup {

	const TRANSIENT = 'lwtv_triage_show_titles';

	/**
	 * IDs of shows whose title matches.
	 *
	 * @param string $title Show title from the submission.
	 * @return int[]
	 */
	public function show_ids( string $title ): array {
		$all = get_transient( self::TRANSIENT );

		if ( ! is_array( $all ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient below.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'post_type_shows'
				),
				ARRAY_A
			);

			$all = array();
			foreach ( (array) $rows as $row ) {
				// Titles saved through kses hold entities (&amp;, &#8217;).
				$all[ (int) $row['ID'] ] = html_entity_decode( (string) $row['post_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}

			set_transient( self::TRANSIENT, $all, HOUR_IN_SECONDS );
		}

		$ids = array();
		foreach ( $all as $id => $show_title ) {
			if ( Name_Match::titles_match( $title, (string) $show_title ) ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	/**
	 * Titles of the characters appearing in the given shows.
	 *
	 * Joins to posts on post_type because ACF copies this meta onto revisions.
	 *
	 * @param int[] $show_ids Show post IDs.
	 * @return string[]
	 */
	public function character_titles( array $show_ids ): array {
		$show_ids = array_values(
			array_filter(
				array_unique( array_map( 'intval', $show_ids ) ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);
		if ( empty( $show_ids ) ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $show_ids ), '%d' ) );
		$args         = array_merge(
			array( 'post_type_characters', $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_show' ) ),
			$show_ids
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$titles = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT chars.post_title
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID
				WHERE chars.post_type = %s
				AND chars.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				AND pm.meta_key LIKE %s
				AND pm.meta_value IN ( {$placeholders} )",
				$args
			)
		);
		// phpcs:enable

		return array_values(
			array_map(
				static function ( $title ): string {
					return html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				},
				(array) $titles
			)
		);
	}
}
