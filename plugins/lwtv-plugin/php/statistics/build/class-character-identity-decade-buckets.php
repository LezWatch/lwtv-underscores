<?php
/**
 * Character-identity-by-decade bucketer.
 *
 * Pure array-in/array-out grouping of a per-year term tally into decades,
 * folding the earliest sparse decades into a single leading bucket so a
 * handful of characters from the medium's early years don't render as an
 * overconfident 100% pie. Mirrors Format_Decade_Buckets exactly — lez_gender
 * and lez_sexuality are both single-value taxonomies on Characters (an ACF
 * "select" field wraps each, so a character carries exactly one term), the
 * same shape Format_Decade_Buckets already assumes for lez_formats on Shows.
 * A dedicated class rather than reusing Format_Decade_Buckets directly, to
 * keep its own name/docblock/tests honest about which data it describes —
 * same reasoning Genre_Decade_Buckets already exists alongside Format's.
 *
 * No WordPress calls — unit-testable without a WP runtime.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Identity_Decade_Buckets {

	/**
	 * Group a per-year term tally into decade buckets.
	 *
	 * @param array $year_term_tally [ year => [ term_name => count, … ], … ], any order.
	 * @param int   $min_bucket_size Fold the earliest decades together until their
	 *                                combined count reaches this. Default 20.
	 * @return array Ordered oldest → newest. Each row: {
	 *   @type string   $type      'before' (a merged leading bucket) or 'decade'.
	 *   @type int|null $from      First year in the bucket, or null for 'before'.
	 *   @type int      $to        Exclusive end year (the decade's start + 10).
	 *   @type int      $total     Total characters in the bucket.
	 *   @type array    $terms     [ term_name => count, … ], insertion order.
	 *   @type array    $pcts      [ term_name => float, … ], one-decimal percentages.
	 *   @type string   $lead_term Name of the largest term in the bucket.
	 *   @type float    $lead_pct  That term's percentage.
	 * } Empty array when there is no data.
	 */
	public static function build( array $year_term_tally, int $min_bucket_size = 20 ): array {
		$min_bucket_size = max( 1, $min_bucket_size );

		// 1. Roll individual years into decades.
		$decades = array();
		foreach ( $year_term_tally as $year => $terms ) {
			$year = (int) $year;
			if ( $year <= 0 || ! is_array( $terms ) ) {
				continue;
			}
			$decade = (int) floor( $year / 10 ) * 10;
			foreach ( $terms as $term => $count ) {
				$term                        = (string) $term;
				$decades[ $decade ][ $term ] = ( $decades[ $decade ][ $term ] ?? 0 ) + (int) $count;
			}
		}

		if ( empty( $decades ) ) {
			return array();
		}

		ksort( $decades );

		// 2. Fold the earliest decades into one "before" bucket until it
		// clears $min_bucket_size, then emit every decade after that on
		// its own.
		$buckets       = array();
		$leading       = array();
		$leading_total = 0;
		$leading_done  = false;
		$first_decade  = array_key_first( $decades );

		foreach ( $decades as $decade => $terms ) {
			if ( ! $leading_done ) {
				$current_total = array_sum( $terms );

				// A first decade that already clears the floor on its own
				// stands alone as a real 'decade' bucket rather than getting
				// wrapped in a 'before' label it doesn't need — 'before'
				// only makes sense once at least one earlier, sparser decade
				// has actually been folded in.
				if ( $decade === $first_decade && $current_total >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'decade', $decade, $decade + 10, $terms );
					$leading_done = true;
					continue;
				}

				foreach ( $terms as $term => $count ) {
					$leading[ $term ] = ( $leading[ $term ] ?? 0 ) + $count;
				}
				$leading_total += $current_total;

				if ( $leading_total >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'before', null, $decade + 10, $leading );
					$leading_done = true;
				}
				continue;
			}

			$buckets[] = self::describe( 'decade', $decade, $decade + 10, $terms );
		}

		// Every decade was sparse and the leading bucket never cleared the
		// threshold — emit it anyway rather than silently dropping data.
		if ( ! $leading_done ) {
			$buckets[] = self::describe( 'before', null, null, $leading );
		}

		return $buckets;
	}

	/**
	 * Summarize one bucket's term tally: total, percentages, and the lead.
	 *
	 * @param string   $type  'before' or 'decade'.
	 * @param int|null $from  First year in the bucket, or null for 'before'.
	 * @param int|null $to    Exclusive end year.
	 * @param array    $terms [ term_name => count, … ].
	 * @return array See build()'s return shape for one row.
	 */
	private static function describe( string $type, ?int $from, ?int $to, array $terms ): array {
		$total = array_sum( $terms );

		$lead_term  = '';
		$lead_count = -1;
		foreach ( $terms as $term => $count ) {
			if ( $count > $lead_count ) {
				$lead_count = $count;
				$lead_term  = $term;
			}
		}

		$pcts = array();
		foreach ( $terms as $term => $count ) {
			$pcts[ $term ] = ( $total > 0 ) ? round( ( $count / $total ) * 100, 1 ) : 0.0;
		}

		return array(
			'type'      => $type,
			'from'      => $from,
			'to'        => $to,
			'total'     => $total,
			'terms'     => $terms,
			'pcts'      => $pcts,
			'lead_term' => $lead_term,
			'lead_pct'  => $pcts[ $lead_term ] ?? 0.0,
		);
	}
}
