<?php
/**
 * Name: Trope Categories
 * Description: The canonical good/maybe/bad/ploy trope-slug groupings used
 * by the show score (Calculations::show_tropes_score()) and now shared with
 * the Statistics layer so stats views can group the same tropes the same
 * way.
 */

namespace LWTV\CPTs\Shows\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trope_Categories {

	/**
	 * Good tropes are always good.
	 */
	const GOOD = array( 'happy-ending', 'everyones-queer' );

	/**
	 * Maybe tropes are only good if there isn't Queer-for-Ratings.
	 */
	const MAYBE = array( 'big-queer-wedding', 'coming-out', 'subtext' );

	/**
	 * BAD tropes are always good.
	 */
	const BAD = array( 'queerbashing', 'in-prison', 'queerbaiting', 'big-bad-queers' );

	/**
	 * PLOY tropes are sketchy.
	 */
	const PLOY = array( 'queer-for-ratings', 'queer-laughs', 'happy-then-not', 'erasure', 'queer-of-the-week', 'background-queers' );

	/**
	 * Look up which category a trope slug belongs to.
	 *
	 * @param string $slug Trope term slug.
	 * @return string One of 'good', 'maybe', 'bad', 'ploy', or '' if the
	 *                 slug isn't in any of the four lists (e.g. a purely
	 *                 descriptive trope like "literary-inspired", or "none").
	 */
	public static function category_for( string $slug ): string {
		foreach ( array(
			'good'  => self::GOOD,
			'maybe' => self::MAYBE,
			'bad'   => self::BAD,
			'ploy'  => self::PLOY,
		) as $category => $slugs ) {
			if ( in_array( $slug, $slugs, true ) ) {
				return $category;
			}
		}
		return '';
	}
}
