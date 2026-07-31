<?php
/**
 * Intersection pair (co-occurrence) transform for the shows
 * intersectionality statistics page.
 *
 * Pure array-in / array-out helpers. No WordPress runtime dependency — every
 * query, term lookup, permalink, and i18n string stays in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts how often intersectionality terms appear together on the same show.
 */
class Intersection_Pairs {

	/**
	 * Count every co-occurring pair of term slugs across a set of objects.
	 *
	 * Slugs are deduped per object, pairs are canonically (alphabetically)
	 * ordered, and objects with fewer than two distinct slugs contribute
	 * nothing. Results sort by count descending, then pair key ascending
	 * for a deterministic order.
	 *
	 * @param array $object_terms [ object_id => [ slug, … ] ].
	 * @return array [ [ 'slugs' => [ a, b ], 'count' => int ], … ]
	 */
	public static function count_pairs( array $object_terms ): array {
		$counts = array();

		foreach ( $object_terms as $slugs ) {
			$slugs = array_values( array_unique( array_map( 'strval', (array) $slugs ) ) );
			sort( $slugs, SORT_STRING );

			$total = count( $slugs );
			if ( $total < 2 ) {
				continue;
			}

			for ( $i = 0; $i < $total - 1; $i++ ) {
				for ( $j = $i + 1; $j < $total; $j++ ) {
					$key = $slugs[ $i ] . '|' . $slugs[ $j ];
					if ( ! isset( $counts[ $key ] ) ) {
						$counts[ $key ] = 0;
					}
					++$counts[ $key ];
				}
			}
		}

		// Count descending, then pair key ascending on ties.
		uksort( $counts, fn( $a, $b ) => ( $counts[ $b ] <=> $counts[ $a ] ) ?: strcmp( $a, $b ) );

		$out = array();
		foreach ( $counts as $key => $count ) {
			$out[] = array(
				'slugs' => explode( '|', $key ),
				'count' => $count,
			);
		}

		return $out;
	}

	/**
	 * Keep the strongest pairs: at most $take rows, each with at least $min shows.
	 *
	 * @param array $pairs Output of count_pairs().
	 * @param int   $take  Maximum rows to keep.
	 * @param int   $min   Minimum count a pair needs to stay in.
	 * @return array Filtered, still sorted, re-indexed.
	 */
	public static function top_pairs( array $pairs, int $take = 8, int $min = 1 ): array {
		$kept = array_filter( $pairs, fn( $pair ) => (int) ( $pair['count'] ?? 0 ) >= $min );
		return array_slice( array_values( $kept ), 0, max( 0, $take ) );
	}
}
