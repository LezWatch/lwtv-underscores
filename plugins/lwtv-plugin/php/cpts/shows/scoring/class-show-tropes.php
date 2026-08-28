<?php
/**
 * Name: Show Tropes
 * Description: Pure maths for a show's trope score -- the good/maybe/bad/ploy
 * trope tally, the intersectionality bonus, and the death deductions.
 *
 * Extracted verbatim from Calculations::show_tropes_score(), which reads
 * taxonomy terms and meta directly and so cannot be unit-tested itself. This
 * class takes the already-resolved values and does only arithmetic.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Tropes {

	/** Score when a show has no tropes tagged at all, or is tagged 'none'. */
	const NO_TROPES_SCORE = 80;

	/** Score when a show has tropes, but none fall into good/maybe/bad/ploy. */
	const UNCATEGORIZED_TROPES_SCORE = 70;

	/** Intersectionality bonus per tagged lez_intersections term. */
	const INTERSECTIONALITY_BONUS_PER_TERM = 3;

	/** Ceiling on the intersectionality bonus, regardless of term count. */
	const INTERSECTIONALITY_BONUS_MAX = 15;

	/** Multiplier when the show has a dead queer character and no happy ending. */
	const DEAD_NO_HAPPY_ENDING_FACTOR = 0.66;

	/** Multiplier when the show has a dead queer character but a happy ending. */
	const DEAD_HAPPY_ENDING_FACTOR = 0.75;

	/**
	 * @param string[] $trope_slugs        Slugs of the show's lez_tropes terms.
	 * @param bool     $death_override     True when lezshows_byq_override is
	 *                                     set, which cancels the death
	 *                                     deduction below.
	 * @param int      $intersection_count Count of the show's lez_intersections terms.
	 * @return float
	 */
	public static function score( array $trope_slugs, bool $death_override, int $intersection_count ): float {
		$has_dead     = ! $death_override && in_array( 'dead-queers', $trope_slugs, true );
		$is_happy_end = in_array( 'happy-ending', $trope_slugs, true );

		if ( empty( $trope_slugs ) || in_array( 'none', $trope_slugs, true ) ) {
			$score = (float) self::NO_TROPES_SCORE;
		} else {
			$counts = array(
				'good'  => count( array_intersect( $trope_slugs, Trope_Categories::GOOD ) ),
				'maybe' => count( array_intersect( $trope_slugs, Trope_Categories::MAYBE ) ),
				'bad'   => count( array_intersect( $trope_slugs, Trope_Categories::BAD ) ),
				'ploy'  => count( array_intersect( $trope_slugs, Trope_Categories::PLOY ) ),
			);
			$any    = $counts['good'] + $counts['maybe'] + $counts['bad'] + $counts['ploy'];

			if ( 0 === $any ) {
				$score = (float) self::UNCATEGORIZED_TROPES_SCORE;
			} else {
				$base  = $counts['good'] + $counts['maybe'] - $counts['bad'] - $counts['ploy'];
				$score = ( $base > 0 ) ? ( $base / $any ) * 100 : 0.0;
			}
		}

		$score += min( $intersection_count * self::INTERSECTIONALITY_BONUS_PER_TERM, self::INTERSECTIONALITY_BONUS_MAX );
		$score  = max( 0.0, $score );

		if ( 0.0 !== $score && $has_dead ) {
			$score *= $is_happy_end ? self::DEAD_HAPPY_ENDING_FACTOR : self::DEAD_NO_HAPPY_ENDING_FACTOR;
		}

		return min( 100.0, $score );
	}
}
