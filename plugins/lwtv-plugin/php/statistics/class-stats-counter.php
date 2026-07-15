<?php
/**
 * Statistics Counter Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy;
use LWTV\Statistics\Build\Dead as Build_Dead;

class Stats_Counter {

	/**
	 * Count the number of shows along with some other weird things.
	 *
	 * @param string $type  Type of output (onair, total, score)
	 * @param string $tax   The taxonomy   (stations, nations, etc)
	 * @param string $term  The term       (amc, united-kingdom, etc)
	 *
	 * @return array        [total number, on-air, total score, on-air score]
	 */
	public function count_shows( $type, $tax, $term ) {
		$queery = ( new Queery_Taxonomy() )->make( 'post_type_shows', 'lez_' . $tax, 'slug', $term );
		$return = 0;

		if ( ! is_object( $queery ) ) {
			return 0;
		}

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
		$date = $dt->format( 'Y' );

		if ( $queery->have_posts() ) {
			update_meta_cache( 'post', wp_list_pluck( $queery->posts, 'ID' ) );
			switch ( $type ) {
				case 'onair':
					// How many shows are on air.
					$onair = 0;
					foreach ( $queery->posts as $show ) {
						$end = get_post_meta( $show->ID, 'lezshows_airdates_finish', true );
						if ( empty( $end ) ) {
							$legacy = get_post_meta( $show->ID, 'lezshows_airdates', true );
							$end    = is_array( $legacy ) ? ( $legacy['finish'] ?? '' ) : '';
						}
						if ( ! empty( $end ) && ( 'current' === lcfirst( $end ) || $end >= $date ) ) {
							++$onair;
						}
					}
					$return = $onair;
					break;
				case 'score':
					// What's the average show score for the shows we're calculating.
					$score = 0;
					foreach ( $queery->posts as $show ) {
						$this_score = get_post_meta( $show->ID, 'lezshows_the_score', true );
						if ( $this_score ) {
							$score += $this_score;
						}
					}
					$score = ( $queery->post_count > 0 ) ? ( $score / $queery->post_count ) : 0;

					$return = round( $score, 2 );
					break;
				case 'onairscore':
					// What's the average show score for shows on air?
					$score = 0;
					$onair = 0;
					foreach ( $queery->posts as $show ) {
						$this_score = get_post_meta( $show->ID, 'lezshows_the_score', true );
						if ( $this_score ) {
							$end = get_post_meta( $show->ID, 'lezshows_airdates_finish', true );
							if ( empty( $end ) ) {
								$legacy = get_post_meta( $show->ID, 'lezshows_airdates', true );
								$end    = is_array( $legacy ) ? ( $legacy['finish'] ?? '' ) : '';
							}
							if ( ! empty( $end ) && ( 'current' === lcfirst( $end ) || $end >= $date ) ) {
								$score += $this_score;
								++$onair;
							}
						}
					}
					$score  = ( 0 !== $onair ) ? ( $score / $onair ) : $onair;
					$return = round( $score, 2 );
					break;
				default:
					// How many shows are there?
					$return = $queery->post_count;
			}
		}
		return $return;
	}

	/**
	 * Generate total counts
	 *
	 * @return int Total counts
	 */
	public function generate_total_counts( $subject, $death = false ) {
		if ( $death ) {
			return ( new Build_Dead() )->total_dead_characters( $subject );
		}

		return wp_count_posts( 'post_type_' . $subject )->publish;
	}

	/**
	 * Cumulative growth series for a subject, one entry per year.
	 *
	 * @param string $subject One of: shows, characters, actors, dead.
	 * @return array<int,array{year:int,count:int}> Year-ordered, cumulative counts.
	 */
	public function get_growth_series( $subject ) {
		$valid = array( 'shows', 'characters', 'actors', 'dead' );
		if ( ! in_array( $subject, $valid, true ) ) {
			return array();
		}

		$cache_key   = 'stats_growth_series_' . $subject;
		$cached_data = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return (array) $cached_data;
		}

		$per_year = array();

		if ( 'dead' === $subject ) {
			// Reuse the death-year data (character death counts per year).
			$years = ( new Build_Dead() )->generate_years_data();
			foreach ( $years as $row ) {
				$year              = (int) $row['death_year'];
				$per_year[ $year ] = ( $per_year[ $year ] ?? 0 ) + (int) $row['death_count'];
			}
		} else {
			global $wpdb;
			$post_type = 'post_type_' . $subject;
			// One grouped query: published entries per creation year.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT YEAR(post_date) AS post_year, COUNT(*) AS total
					FROM {$wpdb->posts}
					WHERE post_type = %s AND post_status = 'publish'
					GROUP BY YEAR(post_date)
					ORDER BY post_year ASC",
					$post_type
				),
				ARRAY_A
			);
			if ( ! is_array( $results ) ) {
				lwtv_plugin()->error_log( 'statistics', 'Growth series query failed for ' . $subject . ' - ' . $wpdb->last_error );
				return array();
			}
			foreach ( $results as $row ) {
				$per_year[ (int) $row['post_year'] ] = (int) $row['total'];
			}
		}

		$series = $this->cumulate( $per_year );

		if ( ! empty( $series ) ) {
			lwtv_plugin()->set_transient( $cache_key, $series, DAY_IN_SECONDS );
		}

		return $series;
	}

	/**
	 * Turn a year=>count map into a year-ordered cumulative series.
	 *
	 * @param array<int,int> $per_year Map of year => count for that year.
	 * @return array<int,array{year:int,count:int}>
	 */
	private function cumulate( array $per_year ) {
		ksort( $per_year );
		$running = 0;
		$series  = array();
		foreach ( $per_year as $year => $count ) {
			$running += (int) $count;
			$series[] = array(
				'year'  => (int) $year,
				'count' => $running,
			);
		}
		return $series;
	}
}
