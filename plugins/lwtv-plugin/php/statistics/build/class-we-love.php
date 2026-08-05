<?php
/**
 * Shows We Love data acquisition.
 *
 * WP-glue side of the We Love It view: the loved-show roster rows and
 * the archive-wide aggregate totals. All math on this data lives in
 * the pure Build\We_Love_Compare transform.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class We_Love {

	/**
	 * The loved-show roster: one row per show, newest premiere first,
	 * unknown premieres sorted last (the roster is a complete catalog —
	 * a missing show is a bug the reader can see).
	 *
	 * @return array Rows: id, title, url, start, finish, airing, years
	 *               (label parts), chars, actors, dead, gold, happy,
	 *               countries[].
	 */
	public function get_roster(): array {
		try {
			// Prefixed 'we_love_' to match the derived-tier invalidation pattern.
			$transient = 'we_love_roster';
			$rows      = lwtv_plugin()->get_transient( $transient );

			if ( false !== $rows ) {
				return $rows;
			}

			$loved = get_posts(
				array(
					'post_type'      => 'post_type_shows',
					'post_status'    => 'publish',
					// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- The loved cohort is ~32 shows and hand-picked; 200 is a generous ceiling, and the result is day-cached.
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'meta_key'       => 'lezshows_worthit_show_we_love', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small flagged cohort, day-cached.
					'meta_value'     => 'on', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);

			$rows = array();
			foreach ( $loved as $loved_post ) {
				$air    = get_post_meta( $loved_post->ID, 'lezshows_airdates', true );
				$start  = ( is_array( $air ) && ! empty( $air['start'] ) ) ? (int) $air['start'] : 0;
				$finish = ( is_array( $air ) && isset( $air['finish'] ) ) ? $air['finish'] : '';
				$airing = ( 'current' === $finish );

				$countries = wp_get_post_terms( $loved_post->ID, 'lez_country', array( 'fields' => 'slugs' ) );

				$rows[] = array(
					'id'        => $loved_post->ID,
					'title'     => get_the_title( $loved_post ),
					'url'       => get_permalink( $loved_post ),
					'start'     => $start,
					'finish'    => $airing ? 0 : (int) $finish,
					'airing'    => $airing,
					'chars'     => (int) get_post_meta( $loved_post->ID, 'lezshows_char_count', true ),
					'actors'    => (int) get_post_meta( $loved_post->ID, 'lezshows_queer_irl_count', true ),
					'dead'      => (int) get_post_meta( $loved_post->ID, 'lezshows_dead_count', true ),
					'gold'      => has_term( 'gold', 'lez_stars', $loved_post ),
					'happy'     => has_term( 'happy-ending', 'lez_tropes', $loved_post ),
					'countries' => ( is_array( $countries ) && ! is_wp_error( $countries ) ) ? $countries : array(),
				);
			}

			// Newest premiere first; unknown (0) premieres sort last.
			usort(
				$rows,
				static function ( $a, $b ) {
					if ( ( 0 === $a['start'] ) !== ( 0 === $b['start'] ) ) {
						return ( 0 === $a['start'] ) ? 1 : -1;
					}
					return $b['start'] <=> $a['start'];
				}
			);

			if ( ! empty( $rows ) ) {
				lwtv_plugin()->set_transient( $transient, $rows, DAY_IN_SECONDS );
			}

			return $rows;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building We Love roster: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Archive-wide aggregate totals (loved shows INCLUDED — the pure
	 * versus() transform derives the everything-else side by
	 * subtraction so the groups never overlap).
	 *
	 * @return array n, chars_sum, actors_sum, happy, deadly.
	 */
	public function get_archive_totals(): array {
		global $wpdb;

		try {
			$transient = 'we_love_archive_totals';
			$totals    = lwtv_plugin()->get_transient( $transient );

			if ( false !== $totals ) {
				return $totals;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table names come from $wpdb.
			$row = $wpdb->get_row(
				"SELECT COUNT(*) AS n,
					COALESCE( SUM( CAST( chars.meta_value AS UNSIGNED ) ), 0 ) AS chars_sum,
					COALESCE( SUM( CAST( actors.meta_value AS UNSIGNED ) ), 0 ) AS actors_sum,
					COALESCE( SUM( CASE WHEN CAST( dead.meta_value AS UNSIGNED ) > 0 THEN 1 ELSE 0 END ), 0 ) AS deadly
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} chars ON p.ID = chars.post_id AND chars.meta_key = 'lezshows_char_count'
				 LEFT JOIN {$wpdb->postmeta} actors ON p.ID = actors.post_id AND actors.meta_key = 'lezshows_queer_irl_count'
				 LEFT JOIN {$wpdb->postmeta} dead ON p.ID = dead.post_id AND dead.meta_key = 'lezshows_dead_count'
				 WHERE p.post_type = 'post_type_shows'
				 AND p.post_status = 'publish'",
				ARRAY_A
			);

			// Shows carrying the happy-ending trope, archive-wide.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table names come from $wpdb.
			$happy = $wpdb->get_var(
				"SELECT COUNT( DISTINCT p.ID )
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				 WHERE p.post_type = 'post_type_shows'
				 AND p.post_status = 'publish'
				 AND tt.taxonomy = 'lez_tropes'
				 AND t.slug = 'happy-ending'"
			);

			$totals = array(
				'n'          => (int) ( $row['n'] ?? 0 ),
				'chars_sum'  => (int) ( $row['chars_sum'] ?? 0 ),
				'actors_sum' => (int) ( $row['actors_sum'] ?? 0 ),
				'happy'      => (int) $happy,
				'deadly'     => (int) ( $row['deadly'] ?? 0 ),
			);

			if ( $totals['n'] > 0 ) {
				lwtv_plugin()->set_transient( $transient, $totals, DAY_IN_SECONDS );
			}

			return $totals;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building We Love archive totals: ' . $e->getMessage() );
			return array();
		}
	}
}
