<?php
/**
 * Format-by-decade bucketer.
 *
 * Pure array-in/array-out grouping of a per-year format tally into decades,
 * folding the earliest sparse decades into a single leading bucket so a
 * handful of shows from the medium's early years don't render as an
 * overconfident 100% pie. No WordPress calls — unit-testable without a WP
 * runtime (see tests/unit/Statistics/FormatDecadeBucketsTest.php). All
 * labels/i18n stay in the templates; this class only reports the shape.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Format_Decade_Buckets {

	/**
	 * Group a per-year format tally into decade buckets.
	 *
	 * @param array $year_format_tally [ year => [ format_name => count, … ], … ], any order.
	 * @param int   $min_bucket_size   Fold the earliest decades together until their
	 *                                 combined count reaches this. Default 20.
	 * @return array Ordered oldest → newest. Each row: {
	 *   @type string   $type        'before' (a merged leading bucket) or 'decade'.
	 *   @type int|null $from        First year in the bucket, or null for 'before'.
	 *   @type int      $to          Exclusive end year (the decade's start + 10).
	 *   @type int      $total       Total shows in the bucket.
	 *   @type array    $formats     [ format_name => count, … ], insertion order.
	 *   @type array    $pcts        [ format_name => float, … ], one-decimal percentages.
	 *   @type string   $lead_format Name of the largest format in the bucket.
	 *   @type float    $lead_pct    That format's percentage.
	 * } Empty array when there is no data.
	 */
	public static function build( array $year_format_tally, int $min_bucket_size = 20 ): array {
		$min_bucket_size = max( 1, $min_bucket_size );

		// 1. Roll individual years into decades.
		$decades = array();
		foreach ( $year_format_tally as $year => $formats ) {
			$year = (int) $year;
			if ( $year <= 0 || ! is_array( $formats ) ) {
				continue;
			}
			$decade = (int) floor( $year / 10 ) * 10;
			foreach ( $formats as $format => $count ) {
				$format                        = (string) $format;
				$decades[ $decade ][ $format ] = ( $decades[ $decade ][ $format ] ?? 0 ) + (int) $count;
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

		foreach ( $decades as $decade => $formats ) {
			if ( ! $leading_done ) {
				$current_total = array_sum( $formats );

				// A first decade that already clears the floor on its own
				// stands alone as a real 'decade' bucket rather than
				// getting wrapped in a 'before' label it doesn't need —
				// 'before' only makes sense once at least one earlier,
				// sparser decade has actually been folded in.
				if ( $decade === $first_decade && $current_total >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'decade', $decade, $decade + 10, $formats );
					$leading_done = true;
					continue;
				}

				foreach ( $formats as $format => $count ) {
					$leading[ $format ] = ( $leading[ $format ] ?? 0 ) + $count;
				}
				$leading_total += $current_total;

				if ( $leading_total >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'before', null, $decade + 10, $leading );
					$leading_done = true;
				}
				continue;
			}

			$buckets[] = self::describe( 'decade', $decade, $decade + 10, $formats );
		}

		// Every decade was sparse and the leading bucket never cleared the
		// threshold — emit it anyway rather than silently dropping data.
		if ( ! $leading_done ) {
			$buckets[] = self::describe( 'before', null, null, $leading );
		}

		return $buckets;
	}

	/**
	 * Summarize one bucket's format tally: total, percentages, and the lead.
	 *
	 * @param string   $type    'before' or 'decade'.
	 * @param int|null $from    First year in the bucket, or null for 'before'.
	 * @param int|null $to      Exclusive end year.
	 * @param array    $formats [ format_name => count, … ].
	 * @return array See build()'s return shape for one row.
	 */
	private static function describe( string $type, ?int $from, ?int $to, array $formats ): array {
		$total = array_sum( $formats );

		$lead_format = '';
		$lead_count  = -1;
		foreach ( $formats as $format => $count ) {
			if ( $count > $lead_count ) {
				$lead_count  = $count;
				$lead_format = $format;
			}
		}

		$pcts = array();
		foreach ( $formats as $format => $count ) {
			$pcts[ $format ] = ( $total > 0 ) ? round( ( $count / $total ) * 100, 1 ) : 0.0;
		}

		return array(
			'type'        => $type,
			'from'        => $from,
			'to'          => $to,
			'total'       => $total,
			'formats'     => $formats,
			'pcts'        => $pcts,
			'lead_format' => $lead_format,
			'lead_pct'    => $pcts[ $lead_format ] ?? 0.0,
		);
	}
}
