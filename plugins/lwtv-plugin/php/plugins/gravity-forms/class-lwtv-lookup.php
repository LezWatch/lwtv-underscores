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

use LWTV\Plugins\Gravity_Forms\Build\Duplicate_Verdict;
use LWTV\Plugins\Gravity_Forms\Build\Name_Match;
use LWTV\Relationships\Character_Links;

class Lwtv_Lookup {

	const TRANSIENT = 'lwtv_triage_show_titles';

	/**
	 * Published show titles and alternate titles, for the submit-time duplicate check.
	 */
	const LIVE_TRANSIENT = 'lwtv_triage_live_show_names';

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

	/**
	 * IDs of published shows whose title or any alternate title matches.
	 *
	 * @param string $title Show title from the submission.
	 * @return int[]
	 */
	public function live_show_ids( string $title ): array {
		return Duplicate_Verdict::matching_show_ids( $title, $this->live_show_names() );
	}

	/**
	 * Titles of the published characters linked to the given shows.
	 *
	 * Reads the character links table, which returns published characters only.
	 *
	 * @param int[] $show_ids Show post IDs.
	 * @return string[]
	 */
	public function live_character_titles( array $show_ids ): array {
		$links  = new Character_Links();
		$titles = array();

		foreach ( array_unique( array_map( 'intval', $show_ids ) ) as $show_id ) {
			if ( $show_id <= 0 ) {
				continue;
			}
			foreach ( $links->characters_for_show( $show_id ) as $character_id ) {
				$titles[] = html_entity_decode( (string) get_post_field( 'post_title', $character_id, 'raw' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return array_values( array_unique( $titles ) );
	}

	/**
	 * Every name each published show goes by: its title plus its alternate titles.
	 *
	 * Joins postmeta to posts on post_type and status because ACF copies the
	 * repeater meta onto revisions. Cached for an hour, so a show published in
	 * the last hour is missed (and that submission is handled as normal).
	 *
	 * @return array<int, string[]> Show ID => names.
	 */
	private function live_show_names(): array {
		$names = get_transient( self::LIVE_TRANSIENT );
		if ( is_array( $names ) ) {
			return $names;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient below.
		$titles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'post_type_shows'
			),
			ARRAY_A
		);
		$alts   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id AS ID, pm.meta_value AS name
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND p.post_status = 'publish'
				AND pm.meta_key LIKE %s AND pm.meta_value <> ''",
				'post_type_shows',
				$wpdb->esc_like( 'lezshows_show_names_' ) . '%' . $wpdb->esc_like( '_lezshows_alt_show_name' )
			),
			ARRAY_A
		);
		// phpcs:enable

		$names = array();
		foreach ( array( (array) $titles, (array) $alts ) as $rows ) {
			foreach ( $rows as $row ) {
				$name = (string) ( $row['post_title'] ?? $row['name'] ?? '' );
				// Titles saved through kses hold entities (&amp;, &#8217;).
				$names[ (int) $row['ID'] ][] = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		set_transient( self::LIVE_TRANSIENT, $names, HOUR_IN_SECONDS );

		return $names;
	}
}
