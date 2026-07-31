<?php
/**
 * Scores Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Scores {

	/**
	 * Optimal chunk size for 4-core, 4GB server
	 */
	const OPTIMAL_CHUNK_SIZE = 100;

	/**
	 * Statistics Scores - Optimized with lazy loading
	 *
	 * @param string $post_type Post type to process
	 * @return array
	 */
	public function make( $post_type ) {
		try {
			$transient = 'scores_' . $post_type;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_scores_lazy( $post_type );

				// save array as transient.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building scores statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build scores using lazy loading to limit memory usage
	 *
	 * @param string $post_type Post type to process
	 * @return array
	 */
	private function build_scores_lazy( $post_type ) {
		try {
			// Validate input
			if ( empty( $post_type ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Invalid post_type provided' );
				return array();
			}

			$results_array = array();
			$chunk_size    = self::OPTIMAL_CHUNK_SIZE;
			$offset        = 0;
			$total_count   = $this->get_total_count( $post_type );

			do {
				$chunk = $this->get_scores_chunk( $post_type, $chunk_size, $offset );

				if ( empty( $chunk ) ) {
					break;
				}

				$this->process_scores_chunk( $chunk, $results_array );

				// Memory cleanup between chunks
				unset( $chunk );
				$offset += $chunk_size;

			} while ( $offset < $total_count );

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building scores lazy: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get scores chunk using optimized SQL query
	 *
	 * @param string $post_type Post type to query
	 * @param int    $limit     Number of records to fetch
	 * @param int    $offset    Offset for pagination
	 * @return array
	 */
	private function get_scores_chunk( $post_type, $limit, $offset ) {
		global $wpdb;

		try {
			// Single optimized queery with LIMIT/OFFSET and permalink data
			$queery = $wpdb->prepare(
				"SELECT p.ID, p.post_name, pm.meta_value as score
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'lezshows_the_score'
				 WHERE p.post_type = %s
				 AND p.post_status = 'publish'
				 ORDER BY p.ID
				 LIMIT %d OFFSET %d",
				$post_type,
				$limit,
				$offset
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			return $wpdb->get_results( $queery, ARRAY_A );

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting scores chunk: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Process scores chunk and add to results array
	 *
	 * @param array $chunk Chunk of score data
	 * @param array &$results_array Results array (passed by reference)
	 * @return void
	 */
	private function process_scores_chunk( $chunk, &$results_array ) {
		try {
			// Prime the post cache for the whole chunk in one query so the
			// get_permalink() call below doesn't run a get_post() query per row.
			$post_ids = array_map( 'intval', wp_list_pluck( $chunk, 'ID' ) );
			if ( ! empty( $post_ids ) ) {
				_prime_post_caches( $post_ids, false, false );
			}

			foreach ( $chunk as $row ) {
				$show_id   = (int) $row['ID'];
				$permalink = get_permalink( $show_id );

				$results_array[ $show_id ] = array(
					'id'    => $show_id,
					'count' => $row['score'] ? $row['score'] : 0,
					'url'   => $permalink,
				);
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error processing scores chunk: ' . $e->getMessage() );
		}
	}

	/**
	 * Get every published show's score as a flat list.
	 *
	 * Feeds Score_Distribution::histogram() / median() / tails(). Shows
	 * with no score meta are skipped (a stored zero still counts).
	 *
	 * @return array Raw score values (numeric strings, unclamped).
	 */
	public function get_score_values(): array {
		global $wpdb;

		try {
			// Prefixed 'scores_' so the derived-tier invalidation pattern
			// ('scores_*' in Transients::get_cache_dependencies) clears it.
			$transient = 'scores_values_shows';
			$values    = lwtv_plugin()->get_transient( $transient );

			if ( false !== $values ) {
				return $values;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table names come from $wpdb.
			$results = $wpdb->get_col(
				"SELECT pm.meta_value
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'lezshows_the_score'
				 WHERE p.post_type = 'post_type_shows'
				 AND p.post_status = 'publish'
				 AND pm.meta_value IS NOT NULL
				 AND pm.meta_value != ''"
			);

			$values = is_array( $results ) ? $results : array();

			if ( ! empty( $values ) ) {
				lwtv_plugin()->set_transient( $transient, $values, DAY_IN_SECONDS );
			}

			return $values;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting score values: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get show scores grouped by every year each show was on air.
	 *
	 * A show contributes its (whole-run) score to each year between its
	 * airdate start and finish, matching how On_Air_Optimized counts
	 * shows per year — 'current' finishes run to this year, explicit
	 * finish years are capped to it. Feeds
	 * Score_Distribution::yearly_average().
	 *
	 * @return array Year => array of raw score values.
	 */
	public function get_scores_by_on_air_year(): array {
		global $wpdb;

		try {
			$transient = 'scores_by_on_air_year';
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false !== $array ) {
				return $array;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; table names come from $wpdb.
			$results = $wpdb->get_results(
				"SELECT p.ID, score_meta.meta_value AS score, air_meta.meta_value AS airdates_serialized
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} score_meta ON p.ID = score_meta.post_id AND score_meta.meta_key = 'lezshows_the_score'
				 INNER JOIN {$wpdb->postmeta} air_meta ON p.ID = air_meta.post_id AND air_meta.meta_key = 'lezshows_airdates'
				 WHERE p.post_type = 'post_type_shows'
				 AND p.post_status = 'publish'
				 AND score_meta.meta_value IS NOT NULL
				 AND score_meta.meta_value != ''
				 AND air_meta.meta_value IS NOT NULL
				 AND air_meta.meta_value != ''",
				ARRAY_A
			);
			$results = is_array( $results ) ? $results : array();

			// Same "now" derivation as On_Air_Optimized: the site timezone,
			// not gmdate()'s UTC year (off-by-one around New Year).
			$current_year = (int) ( new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ) )->format( 'Y' );
			$first_year   = (int) LWTV_FIRST_YEAR;

			$array = array();

			foreach ( $results as $row ) {
				$airdates = maybe_unserialize( $row['airdates_serialized'] );

				if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
					continue;
				}

				$start_year  = (int) $airdates['start'];
				$finish_year = ( 'current' === $airdates['finish'] ) ? $current_year : min( $current_year, (int) $airdates['finish'] );

				for ( $year = max( $first_year, $start_year ); $year <= $finish_year; $year++ ) {
					$array[ $year ][] = $row['score'];
				}
			}

			ksort( $array );

			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting scores by on-air year: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get total count of posts for the given post type
	 *
	 * @param string $post_type Post type to count
	 * @return int
	 */
	private function get_total_count( $post_type ): int {
		global $wpdb;

		try {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					 WHERE post_type = %s AND post_status = 'publish'",
					$post_type
				)
			);

			return (int) $count;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting total count: ' . $e->getMessage() );
			return 0;
		}
	}
}
