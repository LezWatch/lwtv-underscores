<?php
/**
 * Catalog depth data acquisition.
 *
 * Seasons and episodes totals across the show archive, plus the
 * coverage count the template uses to decide whether the totals are
 * complete enough to brag about — sparse data would undersell the
 * library rather than impress.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Catalog_Depth {

	/**
	 * Seasons/episodes totals and coverage.
	 *
	 * @return array n (shows), seasons_sum, episodes_sum, with_episodes
	 *               (shows that have an episode count at all).
	 */
	public function get_totals(): array {
		global $wpdb;

		try {
			// Named 'stats_meta_*' to ride the counts-tier invalidation pattern.
			$transient = 'stats_meta_catalog_depth';
			$totals    = lwtv_plugin()->get_transient( $transient );

			if ( false !== $totals ) {
				return $totals;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table names come from $wpdb.
			$row = $wpdb->get_row(
				"SELECT COUNT(*) AS n,
					COALESCE( SUM( CAST( seasons.meta_value AS UNSIGNED ) ), 0 ) AS seasons_sum,
					COALESCE( SUM( CAST( episodes.meta_value AS UNSIGNED ) ), 0 ) AS episodes_sum,
					COALESCE( SUM( CASE WHEN CAST( episodes.meta_value AS UNSIGNED ) > 0 THEN 1 ELSE 0 END ), 0 ) AS with_episodes
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} seasons ON p.ID = seasons.post_id AND seasons.meta_key = 'lezshows_seasons'
				 LEFT JOIN {$wpdb->postmeta} episodes ON p.ID = episodes.post_id AND episodes.meta_key = 'lezshows_episodes'
				 WHERE p.post_type = 'post_type_shows'
				 AND p.post_status = 'publish'",
				ARRAY_A
			);

			$totals = array(
				'n'             => (int) ( $row['n'] ?? 0 ),
				'seasons_sum'   => (int) ( $row['seasons_sum'] ?? 0 ),
				'episodes_sum'  => (int) ( $row['episodes_sum'] ?? 0 ),
				'with_episodes' => (int) ( $row['with_episodes'] ?? 0 ),
			);

			if ( $totals['n'] > 0 ) {
				lwtv_plugin()->set_transient( $transient, $totals, HOUR_IN_SECONDS );
			}

			return $totals;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building catalog depth totals: ' . $e->getMessage() );
			return array();
		}
	}
}
