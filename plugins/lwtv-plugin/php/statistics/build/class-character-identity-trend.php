<?php
/**
 * Character identity trend WP glue: pulls each published character's
 * earliest on-screen year (from the lezchars_show_group repeater's own
 * `appears` sub-field — the same source Character_Longevity_Leaders uses,
 * since characters have no premiere-year field of their own) paired with
 * whichever term it carries on a given taxonomy. lez_gender and
 * lez_sexuality are both ACF "select" fields wrapping a taxonomy, so each
 * character contributes to exactly one term — the same single-value shape
 * Format_Trend already relies on for lez_formats on Shows.
 *
 * One query, two pure consumers: generate_decades() feeds
 * Character_Identity_Decade_Buckets for a trend chart, and generate_firsts()
 * finds the earliest-recorded character per term. Both read the same
 * cached row set rather than querying twice.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Identity_Trend {

	/**
	 * Decade-bucketed identity mix, oldest to newest.
	 *
	 * @param string $taxonomy        Taxonomy slug — 'lez_gender' or 'lez_sexuality'.
	 * @param int    $min_bucket_size Passed through to Character_Identity_Decade_Buckets::build().
	 * @return array See Character_Identity_Decade_Buckets::build() for the return shape.
	 */
	public function generate_decades( string $taxonomy, int $min_bucket_size = 20 ): array {
		$tally = array();
		foreach ( $this->get_character_rows( $taxonomy ) as $row ) {
			$tally[ $row['year'] ][ $row['term_name'] ] = ( $tally[ $row['year'] ][ $row['term_name'] ] ?? 0 ) + 1;
		}

		return Character_Identity_Decade_Buckets::build( $tally, $min_bucket_size );
	}

	/**
	 * The earliest-recorded character for each term on the taxonomy —
	 * "earliest" meaning the lowest earliest-on-screen-year among characters
	 * carrying that term, ties broken by the lower character ID so the
	 * result is stable across cache regenerations.
	 *
	 * @param string $taxonomy Taxonomy slug — 'lez_gender' or 'lez_sexuality'.
	 * @return array [ term_slug => { 'name': string, 'year': int, 'char_id': int, 'char_name': string, 'url': string } ]
	 */
	public function generate_firsts( string $taxonomy ): array {
		$firsts = array();
		foreach ( $this->get_character_rows( $taxonomy ) as $row ) {
			$slug = $row['term_slug'];
			if ( ! isset( $firsts[ $slug ] )
				|| $row['year'] < $firsts[ $slug ]['year']
				|| ( $row['year'] === $firsts[ $slug ]['year'] && $row['id'] < $firsts[ $slug ]['char_id'] )
			) {
				$firsts[ $slug ] = array(
					'name'      => $row['term_name'],
					'year'      => $row['year'],
					'char_id'   => $row['id'],
					'char_name' => $row['name'],
					'url'       => get_permalink( $row['id'] ),
				);
			}
		}

		return $firsts;
	}

	/**
	 * One SQL pass + PHP fold: every published character's earliest
	 * on-screen year, paired with whichever term it carries on $taxonomy.
	 * A character can produce several appears-rows (one per show they're
	 * credited in) — folded down to that character's own minimum year —
	 * but carries exactly one term on a single-value taxonomy, so this
	 * returns at most one row per character, never double-counting the way
	 * joining appears-rows directly against term rows would.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array [ { 'id': int, 'name': string, 'year': int, 'term_slug': string, 'term_name': string }, … ]
	 */
	private function get_character_rows( string $taxonomy ): array {
		global $wpdb;

		$cache_key   = 'character_identity_trend_' . sanitize_key( $taxonomy );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data;
		}

		try {
			// Both variables go through placeholders: the LIKE pattern's
			// wildcard is part of the bound %s value (not hand-escaped in
			// the query text), per WordPress.DB.PreparedSQLPlaceholders —
			// $wpdb->prepare() escapes the literal "%" and "_" in the value
			// itself, so the wildcard still works as a wildcard.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT chars.ID as id, chars.post_title as name, appears.meta_value as years, t.slug as term_slug, t.name as term_name
						FROM {$wpdb->posts} chars
						INNER JOIN {$wpdb->postmeta} appears ON appears.post_id = chars.ID
							AND appears.meta_key LIKE %s
							AND appears.meta_value != ''
						INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = chars.ID
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE chars.post_type = 'post_type_characters'
						AND chars.post_status = 'publish'",
					$wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_appears' ),
					$taxonomy
				),
				ARRAY_A
			);

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Character identity trend query failed (' . $taxonomy . '): ' . $wpdb->last_error );
				return array();
			}

			$folded = array();
			foreach ( $results as $row ) {
				$years = maybe_unserialize( $row['years'] );
				if ( is_array( $years ) ) {
					$years = array_values( array_filter( array_map( 'intval', $years ) ) );
				} elseif ( is_numeric( $years ) ) {
					$years = array( (int) $years );
				} else {
					$years = array();
				}

				if ( empty( $years ) ) {
					continue;
				}

				$id       = (int) $row['id'];
				$min_year = min( $years );

				if ( ! isset( $folded[ $id ] ) ) {
					$folded[ $id ] = array(
						'id'        => $id,
						'name'      => $row['name'],
						'year'      => $min_year,
						'term_slug' => (string) $row['term_slug'],
						'term_name' => (string) $row['term_name'],
					);
				} else {
					$folded[ $id ]['year'] = min( $folded[ $id ]['year'], $min_year );
				}
			}

			$data = array_values( $folded );

			// Cache for 7 days, same cadence Character_Longevity_Leaders
			// uses — character data is relatively stable.
			lwtv_plugin()->set_transient( $cache_key, $data, WEEK_IN_SECONDS );

			return $data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating character identity trend (' . $taxonomy . '): ' . $e->getMessage() );
			return array();
		}
	}
}
