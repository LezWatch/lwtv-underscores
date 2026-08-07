<?php
/**
 * Trope category coverage: how many distinct shows carry at least one
 * trope from each of Trope_Categories' good/maybe/bad/ploy buckets.
 *
 * Pure array-in/array-out — no WordPress calls. Takes the same object →
 * slugs shape Taxonomy_Optimized::get_object_term_slug_map() and
 * Intersection_Pairs already use, so the WP glue in tropes.php only needs
 * to fetch that map once and can feed it to both.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Trope_Categories;

class Trope_Category_Coverage {

	/**
	 * Count shows-with-at-least-one-trope, per category.
	 *
	 * A show carrying tropes from multiple categories counts toward each
	 * of them — the four totals are independent, not a partition, so they
	 * are not expected to sum to the show count.
	 *
	 * @param array $object_terms [ object_id => [ slug, … ] ], any order.
	 * @return array [ 'good'=>int, 'maybe'=>int, 'bad'=>int, 'ploy'=>int ]
	 */
	public static function count( array $object_terms ): array {
		$totals = array(
			'good'  => 0,
			'maybe' => 0,
			'bad'   => 0,
			'ploy'  => 0,
		);

		$category_slugs = array(
			'good'  => Trope_Categories::GOOD,
			'maybe' => Trope_Categories::MAYBE,
			'bad'   => Trope_Categories::BAD,
			'ploy'  => Trope_Categories::PLOY,
		);

		foreach ( $object_terms as $slugs ) {
			$slugs = array_map( 'strval', (array) $slugs );
			if ( empty( $slugs ) ) {
				continue;
			}

			foreach ( $category_slugs as $category => $cat_slugs ) {
				if ( array_intersect( $slugs, $cat_slugs ) ) {
					++$totals[ $category ];
				}
			}
		}

		return $totals;
	}

	/**
	 * Which alignment categories each show touches, kept together per show
	 * rather than tallied independently like count() does.
	 *
	 * A show carrying both a "happy-ending" (good) and a "queerbashing"
	 * (bad) trope touches two categories, not one — count() would credit
	 * both totals and lose that they came from the same show. Keeping the
	 * per-show set lets a caller tell "pure" shows (exactly one category)
	 * apart from "mixed" ones (two or more). The output is also exactly the
	 * [ id => [ slug, … ] ] shape Intersection_Pairs::count_pairs() expects,
	 * so it can find the most common category *pairing* for free, just with
	 * category names standing in for trope slugs.
	 *
	 * @param array $object_terms [ object_id => [ slug, … ] ], any order.
	 * @return array [ object_id => [ 'good', 'bad', … ] ] deduped category names touched; objects touching none are omitted entirely.
	 */
	public static function category_sets( array $object_terms ): array {
		$category_slugs = array(
			'good'  => Trope_Categories::GOOD,
			'maybe' => Trope_Categories::MAYBE,
			'bad'   => Trope_Categories::BAD,
			'ploy'  => Trope_Categories::PLOY,
		);

		$sets = array();
		foreach ( $object_terms as $id => $slugs ) {
			$slugs = array_map( 'strval', (array) $slugs );
			if ( empty( $slugs ) ) {
				continue;
			}

			$touched = array();
			foreach ( $category_slugs as $category => $cat_slugs ) {
				if ( array_intersect( $slugs, $cat_slugs ) ) {
					$touched[] = $category;
				}
			}

			if ( ! empty( $touched ) ) {
				$sets[ $id ] = $touched;
			}
		}

		return $sets;
	}

	/**
	 * Split category_sets()' output into "pure" (exactly one category) vs
	 * "mixed" (two or more), with mixed's share of the two combined.
	 *
	 * @param array $category_sets Output of category_sets().
	 * @return array { 'pure' => int, 'mixed' => int, 'mixed_pct' => float } mixed_pct is 0.0 when pure+mixed is 0.
	 */
	public static function alignment_split( array $category_sets ): array {
		$pure  = 0;
		$mixed = 0;

		foreach ( $category_sets as $touched ) {
			if ( count( $touched ) >= 2 ) {
				++$mixed;
			} else {
				++$pure;
			}
		}

		$total = $pure + $mixed;

		return array(
			'pure'      => $pure,
			'mixed'     => $mixed,
			'mixed_pct' => ( $total > 0 ) ? round( ( $mixed / $total ) * 100, 1 ) : 0.0,
		);
	}
}
