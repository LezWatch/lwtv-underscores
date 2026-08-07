<?php
/**
 * Format trend WP glue: pulls each show's premiere year (from
 * lezshows_airdates.start, the same field On_Air_Optimized uses) paired
 * with its lez_formats term, tallies them, and hands the result to the
 * pure Format_Decade_Buckets transform.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Format_Trend {

	/**
	 * Decade-bucketed format mix, oldest to newest.
	 *
	 * @param int $min_bucket_size Passed through to Format_Decade_Buckets::build().
	 * @return array See Format_Decade_Buckets::build() for the return shape.
	 */
	public function generate( int $min_bucket_size = 20 ): array {
		return Format_Decade_Buckets::build( $this->get_year_format_tally(), $min_bucket_size );
	}

	/**
	 * One SQL pass: every published show's premiere-year meta paired with
	 * its format term, tallied as [ year => [ format_name => count ] ].
	 * Formats is a single-value taxonomy (see Build\Formats), so each show
	 * contributes to exactly one format.
	 *
	 * @return array
	 */
	private function get_year_format_tally(): array {
		global $wpdb;

		$cache_key   = 'format_trend_year_tally';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			$query = "SELECT
					air_meta.meta_value AS airdates_serialized,
					t.name AS format_name
				FROM {$wpdb->posts} shows
				INNER JOIN {$wpdb->postmeta} air_meta ON shows.ID = air_meta.post_id AND air_meta.meta_key = 'lezshows_airdates'
				INNER JOIN {$wpdb->term_relationships} tr ON shows.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'lez_formats'
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE shows.post_type = 'post_type_shows'
				AND shows.post_status = 'publish'
				AND air_meta.meta_value IS NOT NULL
				AND air_meta.meta_value != ''";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query, no user input.
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Format trend query failed: ' . $wpdb->last_error );
				return array();
			}

			$tally = array();
			foreach ( $results as $row ) {
				$airdates = maybe_unserialize( $row['airdates_serialized'] );
				if ( ! is_array( $airdates ) || empty( $airdates['start'] ) ) {
					continue;
				}

				$year   = (int) $airdates['start'];
				$format = (string) ( $row['format_name'] ?? '' );
				if ( $year <= 0 || '' === $format ) {
					continue;
				}

				$tally[ $year ][ $format ] = ( $tally[ $year ][ $format ] ?? 0 ) + 1;
			}

			lwtv_plugin()->set_transient( $cache_key, $tally, DAY_IN_SECONDS );

			return $tally;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating format trend statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
