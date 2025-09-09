<?php

namespace LWTV\Statistics\Build;

class Dead_Year {

	/**
	 * Statistics Death By Year - Optimized with single query
	 *
	 * Death is insane. This is just looping a lot of things to sort
	 * out who died in what year, so we can use it by other functions.
	 * Now optimized with single query instead of N+1 pattern.
	 *
	 * @return array
	 */
	public function make() {
		try {
			$transient = 'dead_year_stats';
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_year_optimized();

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-year-error', 'Error building dead year statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build dead year statistics using optimized single query
	 *
	 * @return array
	 */
	private function build_dead_year_optimized() {
		global $wpdb;

		try {
			// Create the date with regards to timezones
			$timestamp = time();
			$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
			$dt->setTimestamp( $timestamp );
			$this_year = $dt->format( 'Y' );

			// Death by year
			$year_first = LWTV_FIRST_YEAR;
			$year_range = range( $this_year, $year_first );

			$results_array = array();

			// Build base array with all years
			foreach ( $year_range as $year ) {
				$results_array[ $year ] = array(
					'name'  => $year,
					'count' => 0,
					'url'   => home_url( '/this-year/' . $year . '/' ),
				);
			}

			// Get all dead characters with their death year meta data
			// Characters can die multiple times, so we need to handle arrays in PHP
			// phpcs:disable
			$queery = "SELECT
				chars.ID,
				death_meta.meta_value
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} death_meta ON chars.ID = death_meta.post_id AND death_meta.meta_key = 'lezchars_death_year'
			INNER JOIN {$wpdb->term_relationships} dead_rel ON chars.ID = dead_rel.object_id
			INNER JOIN {$wpdb->term_taxonomy} dead_tax ON dead_rel.term_taxonomy_id = dead_tax.term_taxonomy_id
			INNER JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			AND death_meta.meta_value IS NOT NULL
			AND death_meta.meta_value != ''
			AND dead_tax.taxonomy = 'lez_cliches'
			AND dead_term.slug = 'dead'";
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$characters = $wpdb->get_results( $queery, ARRAY_A );

			// Process death years in PHP to handle multiple deaths per character
			$year_counts = array();
			foreach ( $characters as $char ) {
				$death_years = maybe_unserialize( $char['meta_value'] );
				if ( is_array( $death_years ) ) {
					foreach ( $death_years as $death_date ) {
						if ( is_string( $death_date ) && preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $death_date, $matches ) ) {
							$year                 = (int) $matches[1];
							$year_counts[ $year ] = ( $year_counts[ $year ] ?? 0 ) + 1;
						}
					}
				}
			}

			// Convert to results format
			$results = array();
			foreach ( $year_counts as $year => $count ) {
				$results[] = array(
					'death_year'  => $year,
					'death_count' => $count,
				);
			}

			// Update counts with actual data
			foreach ( $results as $row ) {
				$death_year = (int) $row['death_year'];
				if ( isset( $results_array[ $death_year ] ) ) {
					$results_array[ $death_year ]['count'] = (int) $row['death_count'];
				}
			}

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-year-error', 'Error building dead year statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
