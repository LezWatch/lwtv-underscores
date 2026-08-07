<?php
/**
 * Term count distribution: how many objects carry 0, 1, 2, 3, …, N+ terms of
 * a taxonomy. Pure array-in / array-out — no WordPress runtime dependency.
 *
 * Feeds "how loaded is a typical show" views (e.g. Tropes' distribution
 * panel) where a flat average/median hides the real spread — two shows can
 * both average out to "2 tropes" while one carries exactly 2 every time and
 * the other swings between 0 and 4.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buckets objects by how many distinct terms of a taxonomy they carry.
 */
class Term_Count_Distribution {

	/**
	 * Bucket objects by how many (non-excluded) distinct term slugs they carry.
	 *
	 * $object_terms only lists objects that have at least one term relationship
	 * row — that's how Taxonomy_Optimized::get_object_term_slug_map() queries —
	 * so objects with zero relationships never appear in it at all. $total_objects
	 * is the true denominator (e.g. every published show) and is what makes the
	 * "0" bucket and every pct accurate; pass count( $object_terms ) if there is
	 * no external total to compare against.
	 *
	 * @param array $object_terms  [ object_id => [ slug, … ] ].
	 * @param int   $total_objects Denominator for the 0 bucket and every pct.
	 * @param array $exclude_slugs Slugs to drop before counting (e.g. a "None" placeholder).
	 * @param int   $overflow_at   Counts at or above this collapse into one "N+" bucket.
	 * @return array [ [ 'label' => '0'|'1'|…|'N+', 'count' => int, 'pct' => float ], … ], one row per bucket 0..overflow_at.
	 */
	public static function build( array $object_terms, int $total_objects, array $exclude_slugs = array(), int $overflow_at = 4 ): array {
		$exclude_slugs = array_map( 'strval', $exclude_slugs );
		$overflow_at   = max( 1, $overflow_at );

		$buckets = array_fill( 0, $overflow_at + 1, 0 );
		$seen    = 0;

		foreach ( $object_terms as $slugs ) {
			$slugs = array_unique( array_diff( array_map( 'strval', (array) $slugs ), $exclude_slugs ) );
			++$seen;
			$bucket = min( count( $slugs ), $overflow_at );
			++$buckets[ $bucket ];
		}

		// Objects absent from the map entirely (no term relationship row at
		// all) carry zero terms by definition — the map can never tell us
		// about them directly, so the gap between $total_objects and every
		// object the map did see all lands in the 0 bucket.
		$buckets[0] += max( 0, $total_objects - $seen );

		$out = array();
		foreach ( $buckets as $n => $count ) {
			$out[] = array(
				'label' => ( $n === $overflow_at ) ? $n . '+' : (string) $n,
				'count' => $count,
				'pct'   => ( $total_objects > 0 ) ? round( ( $count / $total_objects ) * 100, 1 ) : 0.0,
			);
		}

		return $out;
	}

	/**
	 * Turn bucket counts into whole cells for a pictogram (e.g. a 100-dot
	 * waffle), guaranteed to sum to exactly $cells.
	 *
	 * Rounding each bucket's share independently (e.g. round( pct )) can
	 * over- or under-shoot the total by a cell or two once every bucket's
	 * rounding error is added up — a 100-dot waffle would then render 98 or
	 * 102 dots. The largest-remainder method (each bucket gets its floored
	 * share, then leftover cells go one at a time to the buckets with the
	 * biggest fractional remainder) is the standard fix and always lands on
	 * exactly $cells.
	 *
	 * @param array $buckets       Output of build().
	 * @param int   $total_objects Same denominator passed to build().
	 * @param int   $cells         Total cells to distribute (e.g. 100 for a waffle).
	 * @return int[] One cell count per bucket, same order as $buckets, summing to $cells (or 0s if $total_objects <= 0).
	 */
	public static function to_cells( array $buckets, int $total_objects, int $cells = 100 ): array {
		if ( $total_objects <= 0 || empty( $buckets ) ) {
			return array_fill( 0, count( $buckets ), 0 );
		}

		$floors     = array();
		$remainders = array();
		$floor_sum  = 0;

		foreach ( $buckets as $i => $bucket ) {
			$exact            = ( (int) $bucket['count'] / $total_objects ) * $cells;
			$floor            = (int) floor( $exact );
			$floors[ $i ]     = $floor;
			$remainders[ $i ] = $exact - $floor;
			$floor_sum       += $floor;
		}

		$leftover = $cells - $floor_sum;

		// Largest fractional remainder first; ties broken by original order
		// (stable arsort keeps insertion order for equal values in PHP 8+).
		arsort( $remainders );
		$order       = array_keys( $remainders );
		$order_count = count( $order );

		for ( $i = 0; $i < $leftover && $i < $order_count; $i++ ) {
			++$floors[ $order[ $i ] ];
		}

		ksort( $floors );
		return array_values( $floors );
	}

	/**
	 * The single object carrying the most (non-excluded) distinct terms.
	 *
	 * Ties are common once counts get small (plenty of shows can share a
	 * "most tropes" count of 3 or 4), so this reports how many objects tied
	 * for the top spot rather than silently picking a winner and implying
	 * uniqueness — callers should hedge their wording when 'tied' > 1. The
	 * one object actually returned is the lowest object ID among the tie,
	 * which is arbitrary but deterministic (same input always picks the
	 * same object, unlike relying on whatever order the DB happened to
	 * return rows in).
	 *
	 * @param array $object_terms  [ object_id => [ slug, … ] ].
	 * @param array $exclude_slugs Slugs to drop before counting (e.g. a "None" placeholder).
	 * @return array { 'id' => int, 'count' => int, 'tied' => int } id is 0 and tied is 0 when $object_terms is empty.
	 */
	public static function top_object( array $object_terms, array $exclude_slugs = array() ): array {
		$exclude_slugs = array_map( 'strval', $exclude_slugs );

		$counts = array();
		foreach ( $object_terms as $id => $slugs ) {
			$slugs               = array_unique( array_diff( array_map( 'strval', (array) $slugs ), $exclude_slugs ) );
			$counts[ (int) $id ] = count( $slugs );
		}

		if ( empty( $counts ) ) {
			return array(
				'id'    => 0,
				'count' => 0,
				'tied'  => 0,
			);
		}

		$max     = max( $counts );
		$winners = array_keys( array_filter( $counts, static fn( $count ) => $count === $max ) );
		sort( $winners );

		return array(
			'id'    => $winners[0],
			'count' => $max,
			'tied'  => count( $winners ),
		);
	}
}
