<?php
/**
 * Name: Show Longevity
 * Description: Pure maths for longevity-weighted character scoring.
 *
 * Headcount used to drive a show's character score: the old aggregate was an
 * unbounded sum clamped to 100, so any show with enough characters saturated
 * the ceiling regardless of how briefly those characters were on screen. A
 * 50-year soap that cycled 200 one-episode characters through outranked a
 * tightly-written five-season drama.
 *
 * This class holds the replacement maths, in two parts:
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
 * Everything here is static and pure -- no WordPress calls, no globals, no
 * meta reads -- following the same precedent as Airdates::resolve(), so it is
 * unit-testable without a WordPress runtime. See tests/unit/CPTs/ShowLongevityTest.php.
 * WordPress glue (meta reads, term lookups) stays with the caller.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

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
	 * CALIBRATED against all 2255 published shows via `wp lwtv score-preview
	 * --all`. Chosen as the balance point between two display boundaries that
	 * pull in opposite directions:
	 *
	 *    K   median   vs old   Failing (<20)   90+ Club   deciles moved
	 *    9    59.87    +5.37       60 -> 47     16 -> 13       55%
	 *   15    57.42    +2.92       60 -> 50     16 ->  8       37%
	 *   20    56.26    +1.76       60 -> 52     16 ->  6       28%
	 *   30    55.09    +0.59       60 -> 55     16 ->  1       20%
	 *
	 * K=30 would hold the median almost exactly, but it nearly empties "The
	 * 90+ Club" (a named section on the scores stats page) because reaching a
	 * character score of 90 would need a raw X of 270. K=15 accepts a ~3 point
	 * median rise to keep the top of the distribution populated.
	 *
	 * The median rise is deliberate, not drift. The old character score sat on
	 * the floor of its range -- median 10.0, with 52% of shows at or below 10 --
	 * and went NEGATIVE for 133 shows (worst -19), so it barely participated in
	 * the four-way average except to subtract. The new median is 37, which is a
	 * real quarter of the score. Some shrinkage of the 90+ Club is likewise
	 * correct: it was partly populated by the 38 shows whose character score was
	 * clamped at 100, each collecting a free 25 points on the total.
	 *
	 * So this constant does not merely rescale rankings -- it sets how much the
	 * character component weighs at all. Re-derive it from a fresh --all run if
	 * any of the value or weight constants change.
	 *
	 * Never calibrate from a single show. An earlier 9.0 came from Transparent
	 * alone and was off by 6x. Shows whose old character score was clamped are
	 * unreproducible on principle: Transparent's 93.08 needed a component of
	 * 100.02, which an asymptotic curve can never reach.
	 */
	const SATURATION_K = 15.0;

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
	 *
	 * A missing year list is a documentation gap, not evidence the character
	 * was barely present, so it must never resolve to zero -- that would turn
	 * incomplete data into a score penalty.
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
	 *
	 * This replaced a flat +10, which was double the maximum role points and so
	 * made casting outrank prominence 2:1 for every character: on Transparent a
	 * one-scene guest played by a queer actor (4.73) outscored a five-season
	 * co-lead (4.69). Multiplying instead of adding ties the reward to how much
	 * of the show the character actually was.
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
	 *
	 * This replaced a flat -5, which made a dead guest a net NEGATIVE (1 - 5 =
	 * -4), so documenting a dead one-scene queer character lowered the show's
	 * score. Scaling instead of subtracting keeps every documented character
	 * worth a non-negative amount, and makes killing a lead cost more in
	 * absolute terms (2.50) than killing a guest (0.50) -- which is the actual
	 * Bury Your Gays concern.
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
	 *
	 * This replaced a show-level aggregate, (trans - trans_irl) * -5, which was
	 * flat, unweighted and invisible per character -- on Transparent it removed
	 * 32% of the show's character value while treating a one-scene guest and a
	 * five-season lead identically. Per character and longevity-weighted, so
	 * miscasting a lead now costs more than miscasting a walk-on.
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
	 *
	 * ⚠ Verified only against the slugs seen on Transparent (which surfaced
	 * `non-binary` and `genderqueer` via the unclassified warning). Reconcile
	 * with `wp term list lez_gender --fields=slug,count` before enabling.
	 *
	 * This is a deliberate departure from the convention in
	 * Statistics\Build\Character_Queer_Cast_Firsts, which avoids "hardcoding an
	 * exhaustive list" precisely so new gender terms keep working without code
	 * changes. An allowlist trades that for precision, and the cost is that
	 * adding a gender term becomes a code change. The unclassified bucket exists
	 * so that cost is paid loudly instead of by silent under-counting.
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
	 * falls through to 'unknown' and scores neutrally -- see
	 * classify_actor_gender().
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
	 * Reconciled against the live taxonomy (6070 actors). The substring rules
	 * already cover 271 of them, and ACTOR_CIS covers 5596.
	 *
	 * Four slugs are deliberately NOT listed, by editorial decision rather than
	 * oversight — whether they are held to the trans/NB casting standard is an
	 * identity question, not a technical one:
	 *
	 *   demigender (3), androgynous (2), no-label (2), two-spirit (1)
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

			// With no premiere date the season cannot be placed on a timeline
			// at all, so it contributes nothing rather than being guessed at.
			// A premiere in the future means the season has not aired yet and
			// must not inflate the denominator.
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
	 * @param array  $aired_years   Years the show aired, from
	 *                              aired_years_from_seasons(). Empty to fall through.
	 * @param int    $seasons       Stored season count (lezshows_seasons). 0 to skip.
	 * @param string $start         Airdate start year.
	 * @param string $finish        Airdate finish year, or the 'current' sentinel.
	 * @param int    $current_year  The current year.
	 * @param array  $hiatus_years  Known off-air years, if any.
	 *
	 * @return int Always at least 1 -- this is a denominator.
	 */
	public static function run_years( array $aired_years, int $seasons, string $start, string $finish, int $current_year, array $hiatus_years = array() ): int {
		$start_year = self::year_from_value( $start );

		// Should be unreachable -- every show has a start year -- but this is a
		// division, so it is guarded rather than trusted.
		if ( null === $start_year ) {
			return 1;
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

		// Tier 1: the curated season count, for finished shows only.
		//
		// Capped at the span because years aired can never exceed the years
		// between premiere and finale, while a season count can -- streaming
		// shows drop two seasons in one calendar year.
		if ( ! $still_airing && $seasons >= 1 ) {
			return max( 1, min( $seasons, $span ) );
		}

		// Tier 2: the exact set of years the show was on screen.
		$aired = array_unique( array_filter( array_map( 'intval', $aired_years ) ) );
		if ( ! empty( $aired ) ) {
			return count( $aired );
		}

		// Tier 3: subtract only gap years that actually fall inside the run.
		$gaps = 0;
		foreach ( array_unique( array_map( 'intval', $hiatus_years ) ) as $gap ) {
			if ( $gap >= $start_year && $gap <= $finish_year ) {
				++$gaps;
			}
		}

		return max( 1, $span - $gaps );
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
	 * Role points are the base -- they stand for how present the character is
	 * within a given year -- and the three qualities scale that base rather
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
	 * silently stop those characters being assessed -- a show could quietly
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
	 * Three states, and the third one matters: 'unknown' covers both actors
	 * explicitly tagged `undefined`/`unknown` (37 in the live taxonomy) and any
	 * slug added in future that nobody has triaged. Those must score neutrally.
	 * Treating "we do not know this actor's gender" as "this actor is cis" would
	 * dock a show for OUR missing data -- the same failure the character side
	 * already guards against.
	 *
	 * Pure counterpart to Queeries\Is_Actor_Trans, which is deliberately left
	 * unmodified (it feeds count_queers_all_types() and class-show-characters.php,
	 * and widening it would move existing counts). This keeps that class's
	 * substring rule so future `trans*` slugs match without a code change, and
	 * adds the same treatment for `non-binary*`.
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
	 * Pfefferman was reduced twice over -- once for losing the queer-irl boost,
	 * once for the trans miscast -- for the single fact that a cis, non-queer
	 * actor was cast as a trans woman. Stacking also let two x2 boosts reach x4
	 * and overtake the entire role hierarchy, so a recurring character outranked
	 * a series lead.
	 *
	 * Which standard applies depends on the role: a trans or non-binary
	 * character is judged on trans/NB casting, everyone else on queer casting.
	 *
	 * Unclassified characters get a neutral 1.0. A gender term nobody has
	 * triaged yet must not move a score in either direction -- it belongs in the
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
