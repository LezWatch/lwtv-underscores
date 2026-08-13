<?php
/**
 * Death trend WP glue: buckets recorded death dates by the decade the
 * death itself happened in (not a character's earliest on-screen year),
 * paired with whichever term the dying character carries on a given
 * single-value taxonomy (lez_sexuality / lez_gender). A character can
 * contribute more than one row here — the lezchars_death_year repeater
 * tracks soap-opera-style resurrections, and each dated death is its own
 * event in time, so a character who died in the 1990s and again in the
 * 2000s counts once in each decade's tally. That's the opposite of
 * Character_Identity_Trend, which deliberately folds every row down to
 * one per character; here the repetition is the point — it's what makes
 * "is it getting better or worse per decade" a meaningful question.
 *
 * Reuses Character_Identity_Decade_Buckets::build() as-is rather than
 * writing a second bucketer — that class only needs a
 * [ year => [ term_name => count ] ] tally, and doesn't care whether the
 * year came from a premiere date or a death date.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Death_Trend {

	/**
	 * Decade-bucketed death mix, oldest to newest.
	 *
	 * @param string $taxonomy        Taxonomy slug — 'lez_sexuality' or 'lez_gender'.
	 * @param int    $min_bucket_size Passed through to Character_Identity_Decade_Buckets::build().
	 * @return array See Character_Identity_Decade_Buckets::build() for the return shape.
	 */
	public function generate_decades( string $taxonomy = 'lez_sexuality', int $min_bucket_size = 10 ): array {
		$tally = array();
		foreach ( $this->get_death_rows( $taxonomy ) as $row ) {
			// ACF's date_picker stores raw postmeta as Ymd; legacy rows may
			// still be Y-m-d — strip non-digits first so both parse the same way.
			$digits = preg_replace( '/\D/', '', (string) $row['death_date'] );
			if ( 8 !== strlen( $digits ) ) {
				continue;
			}

			$year = (int) substr( $digits, 0, 4 );
			if ( $year <= 0 ) {
				continue;
			}

			$term                    = (string) $row['term_name'];
			$tally[ $year ][ $term ] = ( $tally[ $year ][ $term ] ?? 0 ) + 1;
		}

		return Character_Identity_Decade_Buckets::build( $tally, $min_bucket_size );
	}

	/**
	 * One SQL pass: every recorded death date on a published character,
	 * paired with whichever term that character carries on $taxonomy. Joins
	 * wp_posts and restricts post_type/post_status the same way
	 * Dead::get_death_date_rows() does — ACF copies death-date meta onto
	 * revision posts too, so an unrestricted postmeta scan would double-count.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array [ { 'death_date': string, 'term_name': string }, … ]
	 */
	private function get_death_rows( string $taxonomy ): array {
		global $wpdb;

		$cache_key   = 'death_trend_rows_' . sanitize_key( $taxonomy );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data;
		}

		try {
			// Both variables go through placeholders: the LIKE pattern's
			// wildcard is part of the bound %s value, per
			// WordPress.DB.PreparedSQLPlaceholders.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT deaths.meta_value as death_date, t.name as term_name
						FROM {$wpdb->posts} chars
						INNER JOIN {$wpdb->postmeta} deaths ON deaths.post_id = chars.ID
							AND deaths.meta_key LIKE %s
							AND deaths.meta_value != ''
						INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = chars.ID
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE chars.post_type = 'post_type_characters'
						AND chars.post_status = 'publish'",
					$wpdb->esc_like( 'lezchars_death_year_' ) . '%' . $wpdb->esc_like( '_date' ),
					$taxonomy
				),
				ARRAY_A
			);

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Death trend query failed (' . $taxonomy . '): ' . $wpdb->last_error );
				return array();
			}

			// Cache for 1 day, matching Dead::get_death_date_rows()'s own
			// cadence since this reads the same underlying meta rows.
			lwtv_plugin()->set_transient( $cache_key, $results, DAY_IN_SECONDS );

			return $results;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating death trend rows (' . $taxonomy . '): ' . $e->getMessage() );
			return array();
		}
	}
}
