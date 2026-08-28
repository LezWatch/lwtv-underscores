<?php
/**
 * Name: Show Rating
 * Description: Pure point tables for a show's base rating: realness,
 * quality, screentime, worth-it verdict, star rating, trigger warning, and
 * the Shows We Love bonus.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Rating {

	/** Multiplier applied to the summed realness+quality+screentime rating. */
	const BASE_MULTIPLIER = 3;

	/** Each of realness/quality/screentime is clamped to this before summing. */
	const BASE_RATING_CAP = 5;

	/** Points for the editorial "worth it" verdict. */
	const WORTH_IT_SCORES = array(
		'Yes' => 10,
		'Meh' => 5,
		'No'  => -10,
		'TBD' => 0,
	);

	/** Points for the show's star rating. */
	const STAR_SCORES = array(
		'gold'   => 20,
		'silver' => 10,
		'bronze' => 5,
		'anti'   => -15,
	);

	/**
	 * Points for the show's normalized trigger-warning level.
	 *
	 * Deliberately negative: a high trigger warning is a downgrade, per the
	 * site's own scoring documentation -- "If a show is actively detrimental
	 * to some viewers, with abuse, or excessive violence, its score is
	 * downgraded." Alias handling ('on', 'medium') lives in Trigger_Warning,
	 * not here -- see class-trigger-warning.php.
	 */
	const TRIGGER_SCORES = array(
		'high' => -15,
		'med'  => -10,
		'low'  => -5,
	);

	/** Bonus for a show tagged "Shows We Love". */
	const SHOWS_WE_LOVE_BONUS = 40;

	/**
	 * @param int    $realness       lezshows_realness_rating, already cast to int.
	 * @param int    $quality        lezshows_quality_rating, already cast to int.
	 * @param int    $screentime     lezshows_screentime_rating, already cast to int.
	 * @param string $worth_it       lezshows_worthit_rating, e.g. 'Yes', 'Meh', 'No', 'TBD'.
	 * @param string $stars          lez_stars term slug or meta value.
	 * @param string $trigger        lez_triggers term slug or meta value, alias or canonical.
	 * @param bool   $shows_we_love  Whether lezshows_worthit_show_we_love is 'on'.
	 * @return int
	 */
	public static function score(
		int $realness,
		int $quality,
		int $screentime,
		string $worth_it,
		string $stars,
		string $trigger,
		bool $shows_we_love
	): int {
		$score  = ( min( $realness, self::BASE_RATING_CAP ) + min( $quality, self::BASE_RATING_CAP ) + min( $screentime, self::BASE_RATING_CAP ) ) * self::BASE_MULTIPLIER;
		$score += self::WORTH_IT_SCORES[ $worth_it ] ?? 0;
		$score += self::STAR_SCORES[ $stars ] ?? 0;
		$score += self::TRIGGER_SCORES[ Trigger_Warning::normalize( $trigger ) ] ?? 0;
		$score += $shows_we_love ? self::SHOWS_WE_LOVE_BONUS : 0;

		return $score;
	}
}
