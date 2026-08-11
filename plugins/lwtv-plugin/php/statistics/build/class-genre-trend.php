<?php
/**
 * Genre trend WP glue: pulls each show's premiere year (from
 * lezshows_airdates.start, the same field Format_Trend and On_Air_Optimized
 * use) paired with every lez_genres term it carries, tallies both a
 * distinct-show count and a genre-tag count (keyed by slug, so templates can
 * link to the real term archive) per year, and hands the result to the pure
 * Genre_Decade_Buckets transform.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genre_Trend {

	/**
	 * Decade-bucketed genre mix, oldest to newest.
	 *
	 * @param int $min_bucket_size Passed through to Genre_Decade_Buckets::build().
	 * @param int $top_n           Passed through to Genre_Decade_Buckets::build().
	 * @return array See Genre_Decade_Buckets::build() for the return shape.
	 */
	public function generate( int $min_bucket_size = 20, int $top_n = 3 ): array {
		return Genre_Decade_Buckets::build( $this->get_year_genre_tally(), $min_bucket_size, $top_n );
	}

	/**
	 * One SQL pass: every published show's premiere-year meta paired with
	 * each lez_genres term it carries (one row per show-genre pair, so a
	 * multi-genre show contributes several rows). Rolled up into
	 * [ year => { 'shows': int, 'genres': [ slug => { 'name': string, 'count': int }, … ] } ],
	 * where 'shows' is deduped per show ID so a show with three genres
	 * still only counts once toward that year's total. Genres are keyed by
	 * slug (not name) so Genre_Decade_Buckets — and ultimately the
	 * template — can link to the real term archive instead of guessing a
	 * slug from the display name.
	 *
	 * @return array
	 */
	private function get_year_genre_tally(): array {
		global $wpdb;

		$cache_key   = 'genre_trend_year_tally';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data;
		}

		try {
			$query = "SELECT
					shows.ID AS show_id,
					air_meta.meta_value AS airdates_serialized,
					t.slug AS genre_slug,
					t.name AS genre_name
				FROM {$wpdb->posts} shows
				INNER JOIN {$wpdb->postmeta} air_meta ON shows.ID = air_meta.post_id AND air_meta.meta_key = 'lezshows_airdates'
				INNER JOIN {$wpdb->term_relationships} tr ON shows.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'lez_genres'
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE shows.post_type = 'post_type_shows'
				AND shows.post_status = 'publish'
				AND air_meta.meta_value IS NOT NULL
				AND air_meta.meta_value != ''";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query, no user input.
			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Genre trend query failed: ' . $wpdb->last_error );
				return array();
			}

			// Two running totals per year: which show IDs have been seen
			// (deduped, since one show can produce several rows above) and
			// the raw per-genre tag counts (intentionally NOT deduped —
			// each genre a show carries should count toward that genre).
			$years_seen_shows = array();
			$tally            = array();

			foreach ( $results as $row ) {
				$airdates = maybe_unserialize( $row['airdates_serialized'] );
				if ( ! is_array( $airdates ) || empty( $airdates['start'] ) ) {
					continue;
				}

				$year = (int) $airdates['start'];
				$show = (int) ( $row['show_id'] ?? 0 );
				$slug = (string) ( $row['genre_slug'] ?? '' );
				$name = (string) ( $row['genre_name'] ?? '' );
				if ( $year <= 0 || $show <= 0 || '' === $slug ) {
					continue;
				}

				if ( ! isset( $tally[ $year ] ) ) {
					$tally[ $year ] = array(
						'shows'  => 0,
						'genres' => array(),
					);
				}

				if ( empty( $years_seen_shows[ $year ][ $show ] ) ) {
					$years_seen_shows[ $year ][ $show ] = true;
					++$tally[ $year ]['shows'];
				}

				if ( ! isset( $tally[ $year ]['genres'][ $slug ] ) ) {
					$tally[ $year ]['genres'][ $slug ] = array(
						'name'  => ( '' !== $name ) ? $name : $slug,
						'count' => 0,
					);
				}
				++$tally[ $year ]['genres'][ $slug ]['count'];
			}

			lwtv_plugin()->set_transient( $cache_key, $tally, DAY_IN_SECONDS );

			return $tally;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating genre trend statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
