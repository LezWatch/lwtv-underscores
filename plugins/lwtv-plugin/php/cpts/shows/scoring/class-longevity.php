<?php
/**
 * Name: Show Longevity
 * Description: Pure maths for longevity-weighted character scoring.
 *
 * Headcount used to drive a show's character score, in two parts:
 *
 *  1. A per-character weight in (0, 1] blending share-of-run with a curved
 *     absolute-year term, so a character who was actually around counts for
 *     more than one who passed through.
 *  2. A saturating curve for the show-level aggregate, so volume gets
 *     diminishing returns instead of a penalty. Deliberately NOT an average:
 *     under an average every one-episode guest drags a show's score down, which
 *     would mean documenting a minor queer character costs the show points.
 *     Penalising thorough documentation would be the wrong incentive for this
 *     site, so short-tenured characters contribute a small positive amount and
 *     the curve flattens instead.
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
	 * How much of the weight comes from curved absolute years.
	 *
	 * Share-of-run alone would flatten every character on a long show: five
	 * solid years on a 20-year series would score 0.25 while the same five
	 * years on a five-year series scored 1.0. This term keeps absolute tenure
	 * worth something.
	 */
	const CURVE_WEIGHT = 0.3;

	/**
	 * Years of tenure at which the curve term reaches 1.0.
	 */
	const ABSOLUTE_CAP = 8;

	/**
	 * Shapes the saturating ceiling: raw value equal to this scores 50.
	 *
	 * CALIBRATED against all 2255 published shows. The objective matters more than
	 * the number, and the obvious objective is the wrong one.
	 *
	 * ⚠ DO NOT calibrate by matching the median TOTAL score but the
	 * component's own distribution:
	 *
	 *    K    char p50   char p99   total median   Failing (<20)   90+ Club
	 *    5.4     50.0       86.7        +8.25            -             -
	 *    8       40.2       81.5        +6.05         60 -> 44     16 -> 14
	 *   10       35.0       77.8        +4.91         60 -> 47     16 -> 12
	 *   15       26.4       70.1        +3.01         60 -> 50     16 ->  8
	 *   40       11.8       46.8        -0.03         60 -> 56     16 ->  1
	 *
	 * K=10 moves the character median from 10 to 35. That fixes most of the scale
	 * error while leaving this the HARD component it ought to be, measuring
	 * documented queer screen time, which most shows genuinely do little of, so it
	 * should not sit level with `alive %` at 69. K=5.4 (= median X, so the median
	 * show scores exactly 50) is the cleanest rule but shifts totals by +8.
	 *
	 * The resulting median rise is a correction, not drift, and needs saying out
	 * loud in the methodology note: most scores go UP because a broken component
	 * stopped dragging the average down.
	 *
	 * Shrinkage of "The 90+ Club" is likewise mostly correct rather than a loss:
	 * **12 of its 16 members had a character score pinned at exactly 100**, so
	 * their membership was manufactured by the clamp rather than measured. The
	 * honest baseline is 4, and K=10 gives 12.
	 *
	 * Never calibrate from a single show. An earlier 9.0 came from Transparent
	 * alone and was off by 6x. Shows whose old character score was clamped are
	 * unreproducible on principle: Transparent's 93.08 needed a component of
	 * 100.02, which an asymptotic curve can never reach.
	 *
	 * Re-deriving this needs no new `--all` run. The preview's `char_new_raw`
	 * column IS the X this constant divides, and the three components K does not
	 * touch are recoverable as `4 * score_new_raw - char_new`, so any existing CSV
	 * can be swept offline for every candidate K at once.
	 */
	const SATURATION_K = 10.0;

	/**
	 * Points per role. Unchanged from the previous scoring model.
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
	 * 1.0 means doubling, and the plain-language test for this number is:
	 * "casting a queer actor is worth as much as doubling this character's
	 * screen time." Raise it if queer casting should outweigh prominence.
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
	 * Non-binary and genderqueer are included deliberately: a non-binary role
	 * should go to a trans or non-binary actor, and the site treats non-binary
	 * characters as a core constituency rather than an edge case.
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
	 * Four slugs are deliberately NOT listed, by editorial decision rather than
	 * oversight — whether they are held to the trans/NB casting standard is an
	 * identity question, not a technical one:
	 *
	 *   demigender, androgynous, no-label, two-spirit
	 *
	 * androgynous often describes presentation rather than identity, and no-label
	 * is a deliberate refusal to categorise — which an allowlist should not
	 * resolve in either direction. Omission is the safe outcome: they classify as
	 * 'unknown' and score neutrally, so no show is ever docked over them.
	 * `two-spirit-trans-man` already matches via the trans substring.
	 */
	const ACTOR_GENDER_DIVERSE = array(
		'genderfluid',
		'genderqueer',
		'agender',
		'gender-non-conforming',
	);

	/**
	 * Build the set of calendar years a show actually aired, from TVMaze season
	 * records.
	 *
	 * TVMaze gives both `premiereDate` and `endDate` per season, which is why
	 * it is preferred over a season count or the raw airdate span. The union of
	 * those ranges is exact where both approximations are wrong:
	 *
	 *  - A season running Sept-May covers two calendar years; a season count
	 *    records it as one.
	 *  - A single-day streaming drop covers one year; the span may imply more.
	 *  - A revival gap drops out of the union for free, where the airdate span
	 *    swallows it whole (The X-Files reads as 26 years but aired in ~14).
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
	 * TVMaze's season coverage is patchy for long-running shows, so one can come
	 * back with only a handful of its years dated. That is worse than having
	 * nothing, because the set is used twice and both uses are damaged:
	 *
	 *  - as the run_years denominator, where a short set inflates every weight;
	 *  - and intersected against each character's `appears` in character_years(),
	 *    where every year TVMaze does not list is silently discarded.
	 *
	 * Measured across the live corpus, 13 shows had their denominator shrink while
	 * mean character weight ALSO fell. A smaller denominator can only raise
	 * weights, so the intersection was throwing away real screen time -- mostly
	 * long-running international soaps (Gute Zeiten schlechte Zeiten, Unter Uns,
	 * Ros na Rún, Salatut elämät).
	 *
	 * Three signals, in ascending cost:
	 *
	 * 1. The set has fewer years than the show has seasons. Precise, but only 23
	 *    of 376 tier-2 shows have a season count recorded to compare against.
	 *    Caught 4 of the 13.
	 * 2. The set starts materially later than the show's recorded start year.
	 *    Caught 2 of the 13, and only small-span shows. This was built on the
	 *    assumption that TVMaze back-fills recent seasons first, so a short set
	 *    would start late -- MEASUREMENT DISPROVED THAT. Gute Zeiten schlechte
	 *    Zeiten has 9 dated years across a 35-year span and its set starts at the
	 *    premiere; the holes are in the middle and the end. Kept because it is
	 *    free and does catch a real shape, but it is not the workhorse.
	 * 3. The set cannot account for the years characters are actually credited
	 *    in. This measures the damage directly instead of predicting it from the
	 *    set's shape, which is why it separates the two ways a set can be short
	 *    of the span where 1 and 2 cannot:
	 *
	 *      - A genuine revival gap (The X-Files: 1993-2002, then 2016 and 2018)
	 *        has no appearances inside the hole, because the show was not airing.
	 *        Coverage is near-total, and the set is real data. Kept.
	 *      - A data gap has characters credited squarely inside the hole, which
	 *        is proof the show aired there and the set is incomplete. Rejected.
	 *
	 * Two further signals were designed and are deliberately absent, both killed
	 * by measurement rather than by taste:
	 *
	 *  - Separating discarded years OUTSIDE the set's range from those inside an
	 *    internal hole, on the theory that only the former is unambiguous. Both
	 *    the case it was meant to catch and the case it was meant to spare put
	 *    every discarded year inside a hole. See discarded_years().
	 *  - Comparing the SIZE of the two records. Provably redundant against signal
	 *    3, and fires on nothing. See the note above run_years().
	 *
	 * Signal 3 needs the character data, so it is opt-in via $credited_years: a
	 * caller that has not gathered characters yet gets signals 1 and 2 only,
	 * rather than a silently weaker check that looks like the full one.
	 *
	 * Takes no current-year argument on purpose: no signal needs one, because
	 * aired_years_from_seasons() has already clamped the set so it cannot run into
	 * the future.
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
	 *  1. The stored season count, for shows that have FINISHED. Editorially
	 *     curated, local, and no API dependency. Transparent's span says 6 while
	 *     its 5 seasons match the 5 calendar years it actually aired, because no
	 *     season landed in 2018.
	 *  2. The TVMaze-derived set of years actually aired. Exact.
	 *  3. The airdate span less known off-air years, if hiatus data exists.
	 *  4. The raw airdate span. Today's behaviour -- wrong for revival shows,
	 *     but never missing, since airdate coverage is complete.
	 *
	 * Tier 1 deliberately excludes still-airing shows: a season currently on air
	 * may not be counted in the meta yet, which would understate the run.
	 *
	 * ⚠ Tier 1 before tier 2 is a deliberate choice of curated data over exact
	 * data, and it has a known cost. A season count undercounts calendar years
	 * whenever seasons straddle a year boundary, which is most broadcast drama:
	 * Arrested Development ran 5 seasons across 7 calendar years, so tier 1 says
	 * 5 where tier 2 says 7. Undercounting the denominator inflates every
	 * character's `share`. Tier 2 handles straddling seasons, multiple drops in
	 * one year, and revival gaps correctly. The ordering is fine, but it is not
	 * the accuracy ordering -- see the plan doc.
	 *
	 * $credited_count is the floor that bounds that cost.
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
	 * run_years() plus which tier produced it.
	 *
	 * Exists because reporting the tier is not optional.
	 *
	 * ## The credited-years floor
	 *
	 * A denominator narrower than the span of its own numerators is internally
	 * inconsistent: if our records say characters were on screen across five
	 * calendar years, the show cannot have run for three. So the result is floored
	 * at the number of distinct years any character is credited in.
	 *
	 * The case that forced it, from a live run -- The L Word: Generation Q:
	 *
	 *     seasons 3   span 5   credited_years 5   tier 1 -> run_years 3
	 *
	 * Three seasons across five calendar years, so tier 1 said 3. Every character
	 * with three or more years then had `share` capped at 1.0, giving the show the
	 * largest X in the corpus (116.6) and making it the only show whose uncapped
	 * total cleared 100. Floored to 5, that inflation disappears.
	 *
	 * Two bounds on the floor, both deliberate:
	 *
	 *  - **Capped at the span.** A show cannot have aired in more calendar years
	 *    than lie between its premiere and its finale, so a mistyped `appears`
	 *    year cannot push the denominator past that. The residual effect of such a
	 *    typo is to raise the denominator by one, which LOWERS the show's score --
	 *    the safe direction, since a data error should never flatter a show.
	 *  - **Never applied to tier 2.** Where we have actual per-season air dates,
	 *    that set is the authoritative statement of which years the show existed,
	 *    and character_years() already intersects against it -- so the numerator
	 *    cannot exceed the denominator and there is nothing to inflate. Raising it
	 *    above |aired| would mean dividing by years the show demonstrably did not
	 *    air. The floor exists for denominators derived from something OTHER than
	 *    air dates, which is exactly where undercounting happens.
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
	 * Blends share-of-run with a curved absolute-year term. Worked examples:
	 *
	 *   40 of 50 yrs (soap regular)   share 0.80  curve 1.00  ->  0.86
	 *    1 of 50 yrs (soap guest)     share 0.02  curve 0.35  ->  0.12
	 *    4 of  5 yrs (drama regular)  share 0.80  curve 0.71  ->  0.77
	 *    3 of  3 yrs (web series)     share 1.00  curve 0.61  ->  0.88
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
	 * Role points are the base and stand for how present the character is
	 * within a given year. The three qualities scale that base rather
	 * than adding to it. Multiplying is what keeps prominence meaningful: a
	 * queer actor cast in a lead is worth more than one cast in a single scene,
	 * and killing a lead costs more than killing a walk-on.
	 *
	 * Note this composes with weight(): role is intensity of presence within a
	 * year, the longevity weight is how many years that lasted, so their
	 * product approximates total screen time. They are not two competing
	 * measures of the same thing.
	 *
	 * @param string $role               Character role on the show.
	 * @param float  $casting_multiplier From casting_multiplier(). Taking a
	 *                                   single float rather than separate
	 *                                   queer-irl and trans flags is deliberate:
	 *                                   it makes "one casting decision, one
	 *                                   multiplier" structural, so the two
	 *                                   signals cannot silently start
	 *                                   compounding again.
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
	 * Returns one of three states rather than a boolean, and the third state is
	 * the point. An allowlist that answers only yes/no would treat any gender
	 * term it has never heard of as cis, so adding a term to the taxonomy would
	 * silently stop those characters being assessed. For example, a show could quietly
	 * cease being checked and nobody would see it happen. 'unclassified' makes
	 * that a reportable condition instead.
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

			// Substring, not exact match, for both families. This taxonomy is
			// full of compound slugs -- non-binary-woman, non-binary-intersex,
			// non-binary-gender-fluid, two-spirit-trans-man -- and an exact-match
			// list silently missed every one of them.
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
	 * One casting decision produces one multiplier. Queer-irl and trans casting
	 * used to be separate multipliers that compounded, which meant Maura
	 * Pfefferman was reduced twice over (once for losing the queer-irl boost,
	 * once for the trans miscast) for the single fact that a cis, non-queer
	 * actor was cast as a trans woman. Stacking also let two x2 boosts reach x4
	 * and overtake the entire role hierarchy, so a recurring character outranked
	 * a series lead.
	 *
	 * Which standard applies depends on the role: a trans or non-binary
	 * character is judged on trans/NB casting, everyone else on queer casting.
	 *
	 * Unclassified characters get a neutral 1.0. A gender term nobody has
	 * triaged yet must not move a score in either direction and it belongs in the
	 * unclassified report, not in the maths.
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
	 * Replaces the old hard clamp at 100, which stacked shows at exactly 100
	 * and made the top of the ranking carry no information. The asymptote means
	 * no amount of headcount buys a perfect score, while each additional
	 * character still contributes something positive.
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
