<?php
/**
 * Name: Show Longevity
 * Description: Pure maths for longevity-weighted character scoring.
 *
 * Per-character tenure weight plus a saturating show-level curve. Deliberately
 * a sum, not an average, so volume never penalises a show.
 * See docs/scoring/character-score.md.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Longevity {

	/**
	 * How much of the weight comes from share-of-run.
	 *
	 * Must sum to 1.0 with CURVE_WEIGHT.
	 */
	const SHARE_WEIGHT = 0.7;

	/**
	 * How much of the weight comes from curved absolute years, so absolute
	 * tenure still counts on a long show.
	 * See docs/scoring/character-score.md#longevity-weight.
	 */
	const CURVE_WEIGHT = 0.3;

	/**
	 * Years of tenure at which the curve term reaches 1.0.
	 */
	const ABSOLUTE_CAP = 8;

	/**
	 * Shapes the saturating ceiling: raw value equal to this scores 50.
	 *
	 * Calibrated corpus-wide on the component's own distribution, never on the
	 * median total or a single show. See docs/scoring/calibration.md#saturation-k.
	 */
	const SATURATION_K = 10.0;

	/**
	 * Points per role.
	 */
	const ROLE_POINTS = array(
		'regular'   => 5,
		'recurring' => 2,
		'guest'     => 1,
	);

	/**
	 * Weight to assume when a character has no `appears` years recorded.
	 */
	const ROLE_PROXY = array(
		'regular'   => 0.7,
		'recurring' => 0.4,
		'guest'     => 0.15,
	);

	/**
	 * Fallback proxy weight for an unrecognised role.
	 */
	const ROLE_PROXY_DEFAULT = 0.15;

	/**
	 * Multiplier applied when a character's PRIMARY actor is queer IRL.
	 *
	 * 1.0 means doubling: queer casting is worth as much as doubling this
	 * character's screen time.
	 */
	const QIRL_BOOST = 1.0;

	/**
	 * Multiplier applied when a character carries no clichés.
	 *
	 * Secondary to the other two by design.
	 */
	const NO_CLICHES_BOOST = 0.25;

	/**
	 * Multiplier applied when a character is dead.
	 *
	 * 0.5 means "killing a character halves everything they contributed."
	 */
	const DEAD_FACTOR = 0.5;

	/**
	 * Multiplier when a trans character's PRIMARY actor is also trans.
	 */
	const TRANS_BOOST = 1.0;

	/**
	 * Multiplier when a trans character's primary actor is NOT trans.
	 *
	 * Below 1.0 on purpose: casting a cis actor in a trans role is treated as
	 * actively costing a show, not merely failing to earn a bonus.
	 */
	const TRANS_MISCAST_FACTOR = 0.5;

	/**
	 * lez_gender slugs NOT held to the trans/non-binary casting standard.
	 */
	const GENDER_CIS = array( 'cisgender', 'intersex', 'unknown' );

	/**
	 * lez_gender slugs held to the trans/non-binary casting standard.
	 *
	 * Non-binary and genderqueer are included deliberately.
	 * See docs/scoring/character-score.md#character-gender.
	 */
	const GENDER_TRANS_OR_NB = array(
		'trans-woman',
		'trans-man',
		'transgender',
		'non-binary-transgender',
		'non-binary',
		'genderqueer',
	);

	/**
	 * lez_actor_gender slugs meaning the actor is explicitly cis.
	 *
	 * Only an explicit cis tag justifies a miscast penalty. Anything unrecognised
	 * falls through to 'unknown' and scores neutrally.
	 */
	const ACTOR_CIS = array(
		'cisgender',
		'cis-man',
		'cis-woman',
		'intersex',
	);

	/**
	 * lez_actor_gender slugs for gender-diverse identities that neither the
	 * 'trans' nor the 'non-binary' substring rule catches.
	 *
	 * demigender, androgynous, no-label and two-spirit are deliberately NOT listed
	 * (editorial decision; they score neutrally). See
	 * docs/scoring/character-score.md#deliberately-omitted-actor-gender-slugs.
	 */
	const ACTOR_GENDER_DIVERSE = array(
		'genderfluid',
		'genderqueer',
		'agender',
		'gender-non-conforming',
	);

	/**
	 * Build the set of calendar years a show actually aired, from TVMaze season
	 * records: the union of every season's premiereDate-endDate range.
	 * See docs/scoring/character-score.md#aired-years.
	 *
	 * @param array $seasons      Season records, each optionally carrying
	 *                            'premiereDate' and 'endDate' as Y-m-d strings.
	 * @param int   $current_year The current year, for still-airing seasons.
	 *
	 * @return array<int, int> Ascending, deduplicated list of years.
	 */
	public static function aired_years_from_seasons( array $seasons, int $current_year ): array {
		$years = array();

		foreach ( $seasons as $season ) {
			if ( ! is_array( $season ) ) {
				continue;
			}

			$start = self::year_from_date( $season['premiereDate'] ?? null );

			// Every show should have a premier date.
			if ( null === $start || $start > $current_year ) {
				continue;
			}

			// A null end date means the season is still running, or TVMaze has
			// not recorded its finish yet. Either way it runs to the present.
			$end = self::year_from_date( $season['endDate'] ?? null );
			if ( null === $end ) {
				$end = $current_year;
			}

			// Never project a run into the future, and never let a corrupt end
			// date that precedes the premiere invert the range.
			$end = min( $end, $current_year );
			$end = max( $end, $start );

			for ( $year = $start; $year <= $end; $year++ ) {
				$years[ $year ] = true;
			}
		}

		$years = array_keys( $years );
		sort( $years );

		return $years;
	}

	/**
	 * Calendar years of slack allowed between a show's recorded start and the
	 * first year TVMaze has dated.
	 *
	 * One year absorbs a December premiere recorded as the following year, or an
	 * airdate that is simply off by one.
	 */
	const AIRED_START_SLACK = 1;

	/**
	 * Seasons of slack allowed between the season count and the aired-year count.
	 *
	 * One season covers ordinary Sept-May scheduling, where N seasons occupy N-1
	 * calendar years. Two seasons inside one calendar year does happen, so a
	 * larger gap than this means seasons are missing from the API, not that the
	 * show aired unusually fast.
	 */
	const AIRED_SEASON_SLACK = 1;

	/**
	 * Minimum share of credited years the aired-years set must account for.
	 *
	 * Any credited year the set does not contain is either an `appears` data
	 * error or a season TVMaze has not dated -- there is no third explanation,
	 * since a character cannot appear in a year the show was not airing. Volume
	 * is what tells the two apart, so this is a ratio and not a hard zero.
	 * See docs/scoring/calibration.md#coverage-min.
	 */
	const COVERAGE_MIN = 0.75;

	/**
	 * Distinct credited years required before coverage is allowed to judge.
	 *
	 * Sized against COVERAGE_MIN so a single stray `appears` year can never on
	 * its own reject a set: with five years of evidence one error leaves 0.80,
	 * still above the threshold. Below this floor the evidence is too thin to
	 * distinguish a bad set from a bad year, so the signal abstains.
	 */
	const COVERAGE_MIN_EVIDENCE = 5;

	/** Verdicts from aired_years_verdict(). */
	const VERDICT_NONE       = 'none';
	const VERDICT_OK         = 'ok';
	const VERDICT_SEASONS    = 'seasons';
	const VERDICT_LATE_START = 'late-start';
	const VERDICT_COVERAGE   = 'coverage';

	/**
	 * What share of the years characters are credited in does the aired-years
	 * set actually contain?
	 *
	 * Compares the UNION of credited years across the show's characters, not a
	 * per-character or per-row tally, so one long-serving regular cannot swamp
	 * the measurement and a year credited to six characters counts once.
	 *
	 * @param array $aired_years    Years from aired_years_from_seasons().
	 * @param array $credited_years Every year any character is credited on this
	 *                              show. Duplicates and zeroes are fine.
	 *
	 * @return float 0.0 to 1.0. Returns 1.0 when there is nothing to explain,
	 *               so no-evidence never reads as bad coverage.
	 */
	public static function appearance_coverage( array $aired_years, array $credited_years ): float {
		$credited = array_unique( array_filter( array_map( 'intval', $credited_years ) ) );

		if ( empty( $credited ) ) {
			return 1.0;
		}

		$aired  = array_unique( array_filter( array_map( 'intval', $aired_years ) ) );
		$inside = array_intersect( $credited, $aired );

		return count( $inside ) / count( $credited );
	}

	/**
	 * Where the credited years the aired set cannot explain actually fall.
	 *
	 * @param array $aired_years    Years from aired_years_from_seasons().
	 * @param array $credited_years Every year any character is credited.
	 *
	 * @return array{outside:int,hole:int,total:int}
	 */
	public static function discarded_years( array $aired_years, array $credited_years ): array {
		$aired    = array_unique( array_filter( array_map( 'intval', $aired_years ) ) );
		$credited = array_unique( array_filter( array_map( 'intval', $credited_years ) ) );
		$missing  = array_diff( $credited, $aired );

		$out = array(
			'outside' => 0,
			'hole'    => 0,
			'total'   => count( $missing ),
		);

		if ( empty( $aired ) || empty( $missing ) ) {
			$out['outside'] = count( $missing );

			return $out;
		}

		$low  = min( $aired );
		$high = max( $aired );

		foreach ( $missing as $year ) {
			if ( $year < $low || $year > $high ) {
				++$out['outside'];
			} else {
				++$out['hole'];
			}
		}

		return $out;
	}

	/**
	 * Vet a TVMaze-derived aired-years set before trusting it.
	 *
	 * Three signals, in order: fewer years than seasons, a late start, and poor
	 * coverage of credited years (opt-in via $credited_years).
	 * See docs/scoring/character-score.md#aired-years-plausibility.
	 *
	 * @param array  $aired_years    Years from aired_years_from_seasons().
	 * @param int    $seasons        Stored season count. 0 when unknown.
	 * @param string $start          Airdate start year.
	 * @param array  $credited_years Every year any character is credited on this
	 *                               show. Empty to skip signal 3.
	 *
	 * @return string One of the VERDICT_* constants.
	 */
	public static function aired_years_verdict( array $aired_years, int $seasons, string $start, array $credited_years = array() ): string {
		if ( empty( $aired_years ) ) {
			return self::VERDICT_NONE;
		}

		// Signal 1: fewer aired years than seasons.
		if ( $seasons >= 2 && count( $aired_years ) < ( $seasons - self::AIRED_SEASON_SLACK ) ) {
			return self::VERDICT_SEASONS;
		}

		// Signal 2: the set begins well after the show did.
		$start_year = self::year_from_value( $start );

		if ( null !== $start_year && min( $aired_years ) > ( $start_year + self::AIRED_START_SLACK ) ) {
			return self::VERDICT_LATE_START;
		}

		// Signal 3: the set cannot explain where the characters were.
		$credited = array_unique( array_filter( array_map( 'intval', $credited_years ) ) );

		if ( count( $credited ) >= self::COVERAGE_MIN_EVIDENCE
			&& self::appearance_coverage( $aired_years, $credited ) < self::COVERAGE_MIN ) {
			return self::VERDICT_COVERAGE;
		}

		return self::VERDICT_OK;
	}

	/**
	 * The vetted aired-years set: unchanged when trustworthy, empty to fall
	 * through to a later tier.
	 *
	 * @param array  $aired_years    Years from aired_years_from_seasons().
	 * @param int    $seasons        Stored season count. 0 when unknown.
	 * @param string $start          Airdate start year.
	 * @param array  $credited_years Every year any character is credited on this
	 *                               show. Empty to skip signal 3.
	 *
	 * @return array
	 */
	public static function usable_aired_years( array $aired_years, int $seasons, string $start, array $credited_years = array() ): array {
		$verdict = self::aired_years_verdict( $aired_years, $seasons, $start, $credited_years );

		return ( self::VERDICT_OK === $verdict ) ? $aired_years : array();
	}

	/**
	 * How many years a show ran, for use as the denominator of share-of-run.
	 *
	 * Four tiers, in preference order:
	 *
	 *  1. The stored season count, for FINISHED shows only.
	 *  2. The TVMaze-derived set of years actually aired.
	 *  3. The airdate span less known off-air years, if hiatus data exists.
	 *  4. The raw airdate span.
	 *
	 * Tier 1 before tier 2 is curated over exact, on purpose; $credited_count
	 * floors its undercount. See docs/scoring/character-score.md#run-years.
	 *
	 * @param array  $aired_years    Years the show aired, from
	 *                               aired_years_from_seasons(). Empty to fall through.
	 * @param int    $seasons        Stored season count (lezshows_seasons). 0 to skip.
	 * @param string $start          Airdate start year.
	 * @param string $finish         Airdate finish year, or the 'current' sentinel.
	 * @param int    $current_year   The current year.
	 * @param array  $hiatus_years   Known off-air years, if any.
	 * @param int    $credited_count Distinct years any character is credited. 0 to skip.
	 *
	 * @return int Always at least 1 -- this is a denominator.
	 */
	public static function run_years( array $aired_years, int $seasons, string $start, string $finish, int $current_year, array $hiatus_years = array(), int $credited_count = 0 ): int {
		return self::run_years_detail( $aired_years, $seasons, $start, $finish, $current_year, $hiatus_years, $credited_count )['years'];
	}

	/**
	 * run_years() plus which tier produced it, so callers never re-derive the tier.
	 *
	 * Floored at distinct credited years, capped at the span; not applied to
	 * tier 2. See docs/scoring/character-score.md#credited-years-floor.
	 *
	 * @param array  $aired_years    Years the show aired, from
	 *                               aired_years_from_seasons(). Empty to fall through.
	 * @param int    $seasons        Stored season count (lezshows_seasons). 0 to skip.
	 * @param string $start          Airdate start year.
	 * @param string $finish         Airdate finish year, or the 'current' sentinel.
	 * @param int    $current_year   The current year.
	 * @param array  $hiatus_years   Known off-air years, if any.
	 * @param int    $credited_count Distinct years any character is credited. 0 to skip.
	 *
	 * @return array{years:int,tier:int,still_airing:bool,span:int,floored:bool}
	 */
	public static function run_years_detail( array $aired_years, int $seasons, string $start, string $finish, int $current_year, array $hiatus_years = array(), int $credited_count = 0 ): array {
		$start_year = self::year_from_value( $start );

		// Should be unreachable -- every show has a start year -- but this is a
		// division, so it is guarded rather than trusted.
		if ( null === $start_year ) {
			return array(
				'years'        => 1,
				'tier'         => 4,
				'still_airing' => false,
				'span'         => 1,
				'floored'      => false,
			);
		}

		// An empty finish, or the 'current' sentinel, means still airing. So
		// does a finish year that has not passed yet, matching how
		// do_the_math() decides lezshows_on_air.
		$finish_year  = self::year_from_value( $finish );
		$still_airing = ( null === $finish_year ) || ( $finish_year >= $current_year );

		if ( null === $finish_year ) {
			$finish_year = $current_year;
		}

		$finish_year = max( $finish_year, $start_year );
		$span        = ( $finish_year - $start_year ) + 1;

		$out = array(
			'years'        => max( 1, $span ),
			'tier'         => 4,
			'still_airing' => $still_airing,
			'span'         => $span,
			'floored'      => false,
		);

		// The floor, capped at the span. Applied to every tier except 2, where the
		// air dates are authoritative -- see the docblock.
		$floor = min( max( 0, $credited_count ), $span );

		// Tier 1: the curated season count, for finished shows only.
		//
		// Capped at the span because years aired can never exceed the years
		// between premiere and finale, while a season count can -- streaming
		// shows drop two seasons in one calendar year.
		if ( ! $still_airing && $seasons >= 1 ) {
			$years          = max( 1, min( $seasons, $span ) );
			$out['floored'] = $floor > $years;
			$out['years']   = max( $years, $floor );
			$out['tier']    = 1;

			return $out;
		}

		// Tier 2: the exact set of years the show was on screen.
		$aired = array_unique( array_filter( array_map( 'intval', $aired_years ) ) );
		if ( ! empty( $aired ) ) {
			$out['years'] = count( $aired );
			$out['tier']  = 2;

			return $out;
		}

		// Tier 3: subtract only gap years that actually fall inside the run.
		$gaps = 0;
		foreach ( array_unique( array_map( 'intval', $hiatus_years ) ) as $gap ) {
			if ( $gap >= $start_year && $gap <= $finish_year ) {
				++$gaps;
			}
		}

		$years          = max( 1, $span - $gaps );
		$out['floored'] = $floor > $years;
		$out['years']   = max( $years, $floor );
		$out['tier']    = ( $gaps > 0 ) ? 3 : 4;

		return $out;
	}

	/**
	 * How many distinct years a character was credited on a show.
	 *
	 * Reads the `appears` sub-field of one lezchars_show_group row. That field
	 * is a multi-value select stored per show row, so a character credited on
	 * two shows keeps a separate year list for each and there is no
	 * cross-contamination between them.
	 *
	 * @param mixed $appears     Raw `appears` value: an array of years, or a
	 *                           bare scalar when only one year is selected.
	 * @param array $aired_years Years the show aired. When supplied, years
	 *                           outside that set are dropped as data errors.
	 *
	 * @return int
	 */
	public static function character_years( $appears, array $aired_years = array() ): int {
		if ( is_array( $appears ) ) {
			$years = array_map( 'intval', $appears );
		} elseif ( is_numeric( $appears ) ) {
			$years = array( (int) $appears );
		} else {
			return 0;
		}

		$years = array_unique( array_filter( $years ) );

		if ( empty( $years ) ) {
			return 0;
		}

		// Where the show's aired years are known, a credited year outside them
		// is a data-entry error. Intersecting drops it, which is why no
		// separate clamp on share is needed.
		if ( ! empty( $aired_years ) ) {
			$years = array_intersect( $years, array_map( 'intval', $aired_years ) );
		}

		return count( $years );
	}

	/**
	 * The per-character longevity weight.
	 *
	 * Blends share-of-run with a curved absolute-year term. Worked examples in
	 * docs/scoring/character-score.md#longevity-weight.
	 *
	 * @param int $years     Distinct years credited.
	 * @param int $run_years Years the show ran.
	 *
	 * @return float 0.0 when there are no years, otherwise up to 1.0.
	 */
	public static function weight( int $years, int $run_years ): float {
		if ( 1 > $years ) {
			return 0.0;
		}

		$share = min( 1.0, $years / max( 1, $run_years ) );
		$curve = sqrt( min( $years, self::ABSOLUTE_CAP ) / self::ABSOLUTE_CAP );

		return min( 1.0, ( self::SHARE_WEIGHT * $share ) + ( self::CURVE_WEIGHT * $curve ) );
	}

	/**
	 * Weight to use when a character has no recorded `appears` years.
	 *
	 * @param string $role Character role on the show.
	 *
	 * @return float Always above zero.
	 */
	public static function role_proxy_weight( string $role ): float {
		$role = strtolower( trim( $role ) );

		return (float) ( self::ROLE_PROXY[ $role ] ?? self::ROLE_PROXY_DEFAULT );
	}

	/**
	 * The unweighted value of a single character.
	 *
	 * Role points scaled (not added to) by casting, clichés and death, so
	 * prominence stays meaningful. See docs/scoring/character-score.md#per-character-value.
	 *
	 * @param string $role               Character role on the show.
	 * @param float  $casting_multiplier From casting_multiplier(). A single float
	 *                                   so the casting signals cannot compound.
	 * @param bool   $no_cliches         Whether the character carries no clichés.
	 * @param bool   $dead               Whether the character is dead.
	 *
	 * @return float Never negative, so documenting a character can never cost a
	 *               show points.
	 */
	public static function character_value( string $role, float $casting_multiplier = 1.0, bool $no_cliches = false, bool $dead = false ): float {
		$role  = strtolower( trim( $role ) );
		$value = (float) ( self::ROLE_POINTS[ $role ] ?? 0 );

		$value *= max( 0.0, $casting_multiplier );

		if ( $no_cliches ) {
			$value *= 1 + self::NO_CLICHES_BOOST;
		}

		if ( $dead ) {
			$value *= self::DEAD_FACTOR;
		}

		return $value;
	}

	/**
	 * Classify a character's gender terms for the trans casting check.
	 *
	 * Three states, not a boolean: an unknown term is 'unclassified' (reported),
	 * never silently cis. See docs/scoring/character-score.md#character-gender.
	 *
	 * @param array $slugs The character's lez_gender term slugs.
	 *
	 * @return string 'trans-or-nb', 'cis', or 'unclassified'.
	 */
	public static function classify_gender( array $slugs ): string {
		$slugs = array_map( 'strtolower', array_map( 'strval', $slugs ) );

		if ( empty( $slugs ) ) {
			return 'unclassified';
		}

		// A trans/NB term wins a mixed set: a character tagged both trans-woman
		// and something else is still a trans role for casting purposes.
		if ( ! empty( array_intersect( $slugs, self::GENDER_TRANS_OR_NB ) ) ) {
			return 'trans-or-nb';
		}

		if ( ! empty( array_intersect( $slugs, self::GENDER_CIS ) ) ) {
			return 'cis';
		}

		return 'unclassified';
	}

	/**
	 * Classify an actor's gender terms for the casting check.
	 *
	 * @param array $slugs The actor's lez_actor_gender term slugs.
	 *
	 * @return string 'trans-or-nb', 'cis', or 'unknown'.
	 */
	public static function classify_actor_gender( array $slugs ): string {
		$found_cis = false;

		foreach ( $slugs as $slug ) {
			$slug = strtolower( trim( (string) $slug ) );

			if ( '' === $slug ) {
				continue;
			}

			// Substring, not exact match: the taxonomy is full of compound slugs
			// (non-binary-woman, two-spirit-trans-man) an exact list would miss.
			if ( false !== strpos( $slug, 'trans' ) || false !== strpos( $slug, 'non-binary' ) ) {
				return 'trans-or-nb';
			}

			if ( in_array( $slug, self::ACTOR_GENDER_DIVERSE, true ) ) {
				return 'trans-or-nb';
			}

			if ( in_array( $slug, self::ACTOR_CIS, true ) ) {
				$found_cis = true;
			}
		}

		// Cis only wins if nothing trans or non-binary was found, so a
		// multi-term actor is never miscounted as cis.
		return $found_cis ? 'cis' : 'unknown';
	}

	/**
	 * The single casting multiplier for one character.
	 *
	 * One casting decision, one multiplier; never stacked. Trans/NB roles are
	 * judged on trans/NB casting, everyone else on queer casting, and
	 * unclassified characters get a neutral 1.0.
	 * See docs/scoring/character-score.md#casting-multiplier.
	 *
	 * @param string $gender_class        Character's class, from classify_gender().
	 * @param bool   $primary_actor_queer First-billed actor is queer IRL, AND the
	 *                                    character is tagged queer-irl.
	 * @param string $actor_class         First-billed actor's class, from
	 *                                    classify_actor_gender().
	 *
	 * @return float Always within [ TRANS_MISCAST_FACTOR, 1 + BOOST ].
	 */
	public static function casting_multiplier( string $gender_class, bool $primary_actor_queer, string $actor_class ): float {
		if ( 'trans-or-nb' === $gender_class ) {
			if ( 'trans-or-nb' === $actor_class ) {
				return 1 + self::TRANS_BOOST;
			}

			// Only an explicitly cis actor earns the penalty. An actor whose
			// gender we have not recorded is our data gap, and a show must not
			// be docked for it.
			if ( 'cis' === $actor_class ) {
				return self::TRANS_MISCAST_FACTOR;
			}

			return 1.0;
		}

		if ( 'unclassified' === $gender_class ) {
			return 1.0;
		}

		return $primary_actor_queer ? ( 1 + self::QIRL_BOOST ) : 1.0;
	}

	/**
	 * Map a raw weighted total onto 0-100 along a saturating curve.
	 *
	 * A curve rather than a hard clamp, so shows do not tie at exactly 100.
	 * See docs/scoring/character-score.md#saturation-curve.
	 *
	 * @param float      $raw               Sum of (character_value * weight).
	 * @param float|null $ceiling_constant  Override for SATURATION_K, for calibration runs.
	 *
	 * @return float Between 0 (inclusive) and 100 (exclusive).
	 */
	public static function saturate( float $raw, ?float $ceiling_constant = null ): float {
		if ( 0.0 >= $raw ) {
			return 0.0;
		}

		$ceiling_constant = $ceiling_constant ?? self::SATURATION_K;

		return ( 100.0 * $raw ) / ( $raw + $ceiling_constant );
	}

	/**
	 * Pull the year out of a Y-m-d date string.
	 *
	 * Deliberately string-based rather than using date parsing: the input is
	 * always an ISO date from TVMaze, and substring extraction cannot be
	 * shifted by a timezone.
	 *
	 * @param mixed $date Date string, or anything else.
	 *
	 * @return int|null Null when there is no parseable year.
	 */
	private static function year_from_date( $date ): ?int {
		if ( ! is_string( $date ) ) {
			return null;
		}

		if ( 1 !== preg_match( '/^(\d{4})-\d{2}-\d{2}/', trim( $date ), $matches ) ) {
			return null;
		}

		return (int) $matches[1];
	}

	/**
	 * Pull the year out of a bare airdate meta value.
	 *
	 * @param string $value A four-digit year, the 'current' sentinel, or empty.
	 *
	 * @return int|null Null for anything without a leading four-digit year.
	 */
	private static function year_from_value( string $value ): ?int {
		if ( 1 !== preg_match( '/^(\d{4})/', trim( $value ), $matches ) ) {
			return null;
		}

		return (int) $matches[1];
	}
}
