# Fortify Weak Points Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retire the three age-related weak points identified in the codebase-quality assessment — bare magic numbers in the show scoring engine, no automated regression coverage for that scoring math, and stylistic/duplication drift in older hand-written files — without changing a single show's stored score.

**Architecture:** Extract the pure arithmetic already buried inside `Calculations::show_score()` and `Calculations::show_tropes_score()` into new pure, static, named-constant classes in `plugins/lwtv-plugin/php/cpts/shows/`, following the precedent already set by `Longevity` and `Character_Score` in the same directory. A new `Trigger_Warning::normalize()` helper collapses a legacy-alias table that had drifted into two independent copies (`Calculations` and `Content_Warning`) — the exact "two copies of one decision" failure mode `Character_Score`'s own docblock warns about. `Content_Warning::make()` (the concrete example of an older, concern-mixing file) is refactored to consume that single source of truth instead of re-deriving it.

**Tech Stack:** PHP 8.1+, PHPUnit 11 (bootstrap-free pure-unit harness at `tests/bootstrap.php`), WordPress 6.5+ runtime for the glue code left behind.

**Spec:** None — this plan is its own spec. Every code sample below was read directly from `class-calculations.php`, `class-character-score.php`, `class-longevity.php`, `class-trope-categories.php`, `class-content-warning.php`, and `tests/bootstrap.php` as they exist on `feat/legacy-score`, so the "before" behavior it promises to preserve is the real current behavior, not a guess.

## Global Constraints

- **Behavior-preserving only.** Per CLAUDE.md: "Do not alter scoring weights without understanding the downstream effects on all existing show scores." Every task here moves arithmetic, it does not change it. No task may alter a point value, a threshold, or a factor.
- PHP 8.1+ (PHPCompatibilityWP enforced); WordPress-Extra via `phpcs.xml.dist`.
- New logic goes in `build`-style pure classes and is tested first, per CLAUDE.md's testing section — no WP globals, `$wpdb`, or output in these classes.
- One class per `class-*.php` file; namespace mirrors directory path under `LWTV\`.
- **No commits during execution.** Per this project's standing workflow preference, do not run `git commit` per task or pause for staged review mid-plan — leave all changes uncommitted and let the user review the full diff and commit once at the end.

---

## Context: what each task fixes

The quality assessment flagged three weak points. This plan addresses all three, and two of them turn out to share a root cause:

1. **Magic numbers in the scoring engine** — `show_score()`'s worth-it/star/trigger point tables and `show_tropes_score()`'s trope-category math are bare literals inline in `Calculations`, unlike `Longevity`'s named-constant style a few files over.
2. **No automated regression coverage for the highest-stakes code** — `Calculations` can't be unit-tested as written because every method reads `get_post_meta()`/`get_the_terms()` directly. Extracting the pure arithmetic (Tasks 2–3) is what makes it testable at all, which is why it fixes both weak points at once.
3. **Consistency across eras / duplicated decisions** — `Content_Warning::make()` (2018-era, `theme/`) independently re-implements the same `'on'` → `'high'`, `'medium'` → `'med'` trigger-alias table that `Calculations::show_score()` also hardcodes. Two independent copies of one decision is precisely the failure pattern `Character_Score`'s docblock documents causing three real bugs already. Task 1 gives both call sites one shared, tested source of truth; Task 4 is the concrete "touch an old file, leave it better" example this establishes as a repeatable pattern.

## File Structure

- Create: `plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php` — `Trigger_Warning`, the single canonical alias→level mapping.
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php` — `Show_Rating`, pure extraction of `show_score()`'s point tables.
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php` — `Show_Tropes`, pure extraction of `show_tropes_score()`'s math.
- Create: `tests/unit/CPTs/TriggerWarningTest.php`
- Create: `tests/unit/CPTs/ShowRatingTest.php`
- Create: `tests/unit/CPTs/ShowTropesTest.php`
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php:33-102` (`show_score()`) and `:295-388` (`show_tropes_score()`, docblock included) — delegate to the new classes.
- Modify: `plugins/lwtv-plugin/php/theme/class-content-warning.php` — consume `Trigger_Warning::normalize()`.
- Modify: `tests/bootstrap.php` — `require_once` the three new classes.

---

### Task 1: `Trigger_Warning` — one canonical alias table

**Files:**
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php`
- Test: `tests/unit/CPTs/TriggerWarningTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Produces: `LWTV\CPTs\Shows\Trigger_Warning::normalize( string $slug ): string`, returning one of `'high'`, `'med'`, `'low'`, or `'none'`. Tasks 2 and 4 both consume this.

- [ ] **Step 1: Write the failing tests**

```php
<?php
/**
 * Tests for Trigger_Warning's legacy-alias normalization.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Trigger_Warning;
use PHPUnit\Framework\TestCase;

final class TriggerWarningTest extends TestCase {

	public function test_canonical_slugs_pass_through(): void {
		$this->assertSame( 'high', Trigger_Warning::normalize( 'high' ) );
		$this->assertSame( 'med', Trigger_Warning::normalize( 'med' ) );
		$this->assertSame( 'low', Trigger_Warning::normalize( 'low' ) );
	}

	public function test_legacy_aliases_map_to_their_canonical_slug(): void {
		$this->assertSame( 'high', Trigger_Warning::normalize( 'on' ) );
		$this->assertSame( 'med', Trigger_Warning::normalize( 'medium' ) );
	}

	public function test_unrecognized_or_empty_slug_is_none(): void {
		$this->assertSame( 'none', Trigger_Warning::normalize( '' ) );
		$this->assertSame( 'none', Trigger_Warning::normalize( 'nope' ) );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter TriggerWarningTest`
Expected: FAIL — `Trigger_Warning` class not found.

- [ ] **Step 3: Write the class**

```php
<?php
/**
 * Name: Trigger Warning
 * Description: The single canonical mapping from a lez_triggers slug (or its
 * legacy alias) to a normalized level.
 *
 * Extracted because this alias table used to have two independent copies:
 * one inline in Calculations::show_score() and one inline in
 * Content_Warning::make(). Two copies of one decision is the exact failure
 * mode Character_Score's own docblock documents causing three real bugs in
 * this project already. There is now exactly one.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trigger_Warning {

	/**
	 * Legacy alias => canonical slug.
	 *
	 * 'on' and 'medium' are older spellings of 'high' and 'med' that still
	 * exist in stored data.
	 */
	const ALIASES = array(
		'on'     => 'high',
		'medium' => 'med',
	);

	/** Canonical levels a normalized value can resolve to. */
	const LEVELS = array( 'high', 'med', 'low' );

	/**
	 * Normalize a trigger-warning slug to a canonical level.
	 *
	 * @param string $slug Raw slug or meta value, e.g. 'on', 'medium', 'low'.
	 * @return string One of 'high', 'med', 'low', or 'none'.
	 */
	public static function normalize( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		$slug = self::ALIASES[ $slug ] ?? $slug;

		return in_array( $slug, self::LEVELS, true ) ? $slug : 'none';
	}
}
```

- [ ] **Step 4: Register the new file in the test bootstrap**

In `tests/bootstrap.php`, immediately after line 56 (`class-trope-categories.php`), add:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php';
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter TriggerWarningTest`
Expected: PASS (3 tests, 6 assertions)

---

### Task 2: `Show_Rating` — extract `show_score()`'s point tables

**Files:**
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php`
- Test: `tests/unit/CPTs/ShowRatingTest.php`
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php:33-102`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Trigger_Warning::normalize( string ): string` (Task 1).
- Produces: `LWTV\CPTs\Shows\Show_Rating::score( int $realness, int $quality, int $screentime, string $worth_it, string $stars, string $trigger, bool $shows_we_love ): int`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
/**
 * Tests for Show_Rating, the pure extraction of show_score()'s point tables.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Show_Rating;
use PHPUnit\Framework\TestCase;

final class ShowRatingTest extends TestCase {

	public function test_base_ratings_are_summed_and_tripled(): void {
		// (5+5+5) * 3 = 45, with no worth-it/star/trigger/bonus contribution.
		$this->assertSame( 45, Show_Rating::score( 5, 5, 5, '', '', '', false ) );
	}

	public function test_base_ratings_are_clamped_at_five(): void {
		// A rating above 5 must not out-earn a perfect 5 -- preserves the
		// min( $rating, 5 ) clamp from the pre-extraction code.
		$this->assertSame( 45, Show_Rating::score( 7, 9, 20, '', '', '', false ) );
	}

	public function test_unknown_worth_it_or_star_value_contributes_nothing(): void {
		$this->assertSame( 0, Show_Rating::score( 0, 0, 0, 'Unrated', 'no-such-tier', '', false ) );
	}

	public function test_trigger_aliases_score_identically_to_their_canonical_slug(): void {
		$this->assertSame(
			Show_Rating::score( 0, 0, 0, '', '', 'high', false ),
			Show_Rating::score( 0, 0, 0, '', '', 'on', false )
		);
		$this->assertSame(
			Show_Rating::score( 0, 0, 0, '', '', 'med', false ),
			Show_Rating::score( 0, 0, 0, '', '', 'medium', false )
		);
	}

	public function test_full_show_matches_a_hand_worked_example(): void {
		// base (3+4+5)*3=36, worth_it 'No'=-10, star 'silver'=+10,
		// trigger 'low'=-5, shows-we-love +40 => 71.
		$this->assertSame( 71, Show_Rating::score( 3, 4, 5, 'No', 'silver', 'low', true ) );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter ShowRatingTest`
Expected: FAIL — `Show_Rating` class not found.

- [ ] **Step 3: Write the class**

```php
<?php
/**
 * Name: Show Rating
 * Description: Pure point tables for a show's base rating: realness,
 * quality, screentime, worth-it verdict, star rating, trigger warning, and
 * the Shows We Love bonus.
 *
 * Extracted verbatim from Calculations::show_score(), which reads meta and
 * taxonomy terms directly and so cannot be unit-tested itself. This class
 * takes the already-resolved values and does only arithmetic, following the
 * same precedent as Longevity -- no WordPress calls, no globals.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

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
```

- [ ] **Step 4: Register the new file in the test bootstrap**

In `tests/bootstrap.php`, immediately after the `class-trigger-warning.php` line added in Task 1, add:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php';
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter ShowRatingTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Wire `Calculations::show_score()` to delegate**

In `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`, replace lines 33–102 (the whole `show_score()` method body from the base-rating comment through `return $score;`) with:

```php
	public function show_score( $post_id ) {

		// If this is not a valid show post type, we skip.
		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		// Get all meta fields at once to reduce database queries
		$meta_fields = $this->get_show_meta_fields( $post_id );

		// Get all taxonomy terms at once to reduce database queries
		$taxonomy_terms = $this->get_show_taxonomy_terms( $post_id );

		return Show_Rating::score(
			(int) $meta_fields['lezshows_realness_rating'],
			(int) $meta_fields['lezshows_quality_rating'],
			(int) $meta_fields['lezshows_screentime_rating'],
			(string) $meta_fields['lezshows_worthit_rating'],
			(string) ( $taxonomy_terms['lez_stars'] ?? $meta_fields['lez_stars'] ),
			(string) ( $taxonomy_terms['lez_triggers'] ?? $meta_fields['lezshows_triggerwarning'] ),
			'on' === $meta_fields['lezshows_worthit_show_we_love']
		);
	}
```

`Show_Rating` is in the same `LWTV\CPTs\Shows` namespace as `Calculations`, so no new `use` import is needed.

- [ ] **Step 7: Run the full pure-unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS, no regressions in any other suite (this step only touched WP-glue code that pure tests can't reach; it confirms nothing else broke).

- [ ] **Step 8: Verify against the running site**

`Calculations::show_score()` itself is WP-glue and out of pure-unit reach, per CLAUDE.md's testing boundary — verify it live instead. Pick 4–5 real shows covering each branch (a `gold`/`Yes`/no-trigger show, a `high`/`on`-trigger show, a `med`/`medium`-trigger show, and a `Shows We Love` show), and for each: note `lezshows_the_score` before this change, run `wp lwtv calc <post_id> --force`, and confirm the score is byte-for-byte identical after. Any difference means the extraction is not behavior-preserving and must be fixed before continuing.

---

### Task 3: `Show_Tropes` — extract `show_tropes_score()`'s math

**Files:**
- Create: `plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php`
- Test: `tests/unit/CPTs/ShowTropesTest.php`
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php:295-388` (docblock included)
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Trope_Categories::GOOD|MAYBE|BAD|PLOY` (already required by `tests/bootstrap.php`, unchanged).
- Produces: `LWTV\CPTs\Shows\Show_Tropes::score( array $trope_slugs, bool $death_override, int $intersection_count ): float`.

Independent of Tasks 1–2 — can be done in either order, but is listed after them for narrative flow.

- [ ] **Step 1: Write the failing tests**

```php
<?php
/**
 * Tests for Show_Tropes, the pure extraction of show_tropes_score()'s math.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Show_Tropes;
use PHPUnit\Framework\TestCase;

final class ShowTropesTest extends TestCase {

	public function test_no_tropes_at_all_scores_eighty(): void {
		$this->assertSame( 80.0, Show_Tropes::score( array(), false, 0 ) );
	}

	public function test_the_none_slug_scores_eighty_even_alongside_other_tropes(): void {
		$this->assertSame( 80.0, Show_Tropes::score( array( 'none', 'dead-queers' ), true, 0 ) );
	}

	public function test_tropes_present_but_uncategorized_score_seventy(): void {
		// 'literary-inspired' is a real, purely descriptive trope slug that is
		// in none of GOOD/MAYBE/BAD/PLOY.
		$this->assertSame( 70.0, Show_Tropes::score( array( 'literary-inspired' ), false, 0 ) );
	}

	public function test_only_maybe_tropes_score_full_marks(): void {
		// good=0 maybe=2 bad=0 ploy=0, any=2, base=2 => (2/2)*100 = 100.
		$this->assertSame( 100.0, Show_Tropes::score( array( 'coming-out', 'subtext' ), false, 0 ) );
	}

	public function test_bad_tropes_outweighing_good_floor_at_zero(): void {
		// any=1 base=-1, not > 0, so score is 0 rather than negative.
		$this->assertSame( 0.0, Show_Tropes::score( array( 'queerbashing' ), false, 0 ) );
	}

	public function test_intersectionality_bonus_is_added_and_capped_at_fifteen(): void {
		// base score 0 (one bad, one maybe cancel out) + min(5*3, 15) = 15.
		$this->assertSame( 15.0, Show_Tropes::score( array( 'queerbashing', 'coming-out' ), false, 5 ) );
	}

	public function test_death_without_happy_ending_cuts_the_score_by_a_third(): void {
		// maybe=1 => score 100, dead-queers present, no happy-ending => *0.66.
		$this->assertSame( 66.0, Show_Tropes::score( array( 'coming-out', 'dead-queers' ), false, 0 ) );
	}

	public function test_death_with_happy_ending_cuts_the_score_by_a_quarter(): void {
		// happy-ending is itself a GOOD trope: good=1 => score 100, dead-queers
		// present, happy-ending present => *0.75.
		$this->assertSame( 75.0, Show_Tropes::score( array( 'happy-ending', 'dead-queers' ), false, 0 ) );
	}

	public function test_death_override_cancels_the_death_deduction(): void {
		$this->assertSame( 100.0, Show_Tropes::score( array( 'coming-out', 'dead-queers' ), true, 0 ) );
	}
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter ShowTropesTest`
Expected: FAIL — `Show_Tropes` class not found.

- [ ] **Step 3: Write the class**

```php
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

namespace LWTV\CPTs\Shows;

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
			$any = $counts['good'] + $counts['maybe'] + $counts['bad'] + $counts['ploy'];

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
```

- [ ] **Step 4: Register the new file in the test bootstrap**

In `tests/bootstrap.php`, add after the `class-show-rating.php` line from Task 2:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php';
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter ShowTropesTest`
Expected: PASS (9 tests)

- [ ] **Step 6: Wire `Calculations::show_tropes_score()` to delegate**

In `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`, replace lines 295–388 (the docblock plus the whole `show_tropes_score()` method) with:

```php
	/**
	 * Calculate show tropes score.
	 */
	public function show_tropes_score( $post_id ) {

		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		$tropes      = wp_get_post_terms( $post_id, 'lez_tropes' );
		$trope_slugs = wp_list_pluck( $tropes, 'slug' );

		// Death Override Checker.
		$override = get_post_meta( $post_id, 'lezshows_byq_override', true );

		// Add Intersectionality Bonus
		$intersection       = get_the_terms( $post_id, 'lez_intersections' );
		$intersection_count = is_array( $intersection ) ? count( $intersection ) : 0;

		return Show_Tropes::score( $trope_slugs, ! empty( $override ), $intersection_count );
	}
```

- [ ] **Step 7: Run the full pure-unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS, no regressions.

- [ ] **Step 8: Verify against the running site**

Same protocol as Task 2 Step 8. Pick shows covering each branch: no tropes, only-good tropes, a bad/ploy-heavy show, a show with intersectionality terms, and a show with `dead-queers` (with and without `lezshows_byq_override` set). Compare `lezshows_the_score` before and after `wp lwtv calc <post_id> --force`.

---

### Task 4: `Content_Warning` — collapse the duplicated alias table

**Files:**
- Modify: `plugins/lwtv-plugin/php/theme/class-content-warning.php`

**Interfaces:**
- Consumes: `Trigger_Warning::normalize( string ): string` (Task 1). No unit test for this file — `make()` calls `get_post_type()`, `get_the_terms()`, `get_post_meta()`, and `term_description()` directly, which is WP-glue outside the pure-unit boundary per CLAUDE.md's testing section. Verified live in Step 3 below.

This is the concrete, bounded example that demonstrates the fix for weak point 3 — not a mass rewrite of every older file, but a repeatable pattern: when you're already touching an old file, replace a duplicated decision with the one now-canonical source of truth.

- [ ] **Step 1: Confirm the duplication this replaces**

Current file (`plugins/lwtv-plugin/php/theme/class-content-warning.php:35-50`):

```php
		switch ( $trigger ) {
			case 'on':
			case 'high':
				$warning_array['card'] = 'danger';
				break;
			case 'med':
			case 'medium':
				$warning_array['card'] = 'warning';
				break;
			case 'low':
				$warning_array['card'] = 'info';
				break;
			default:
				$warning_array['card']    = 'none';
				$warning_array['content'] = 'none';
		}
```

The `'on'`/`'high'` and `'med'`/`'medium'` pairs are this file's own copy of the exact alias table `Trigger_Warning` (Task 1) now owns.

- [ ] **Step 2: Rewrite the class to delegate**

Replace the full contents of `plugins/lwtv-plugin/php/theme/class-content-warning.php` with:

```php
<?php
/**
 * Name: Content Warning
 * Description: The card style and text for a show's content warning.
 *
 * Alias handling ('on', 'medium') is delegated to Trigger_Warning::normalize()
 * rather than duplicated here. This file used to carry its own copy of that
 * table, independently of Calculations::show_score() -- two copies of one
 * decision, the exact pattern Character_Score's docblock documents causing
 * three real bugs in this project already.
 *
 * @package LWTV
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Trigger_Warning;

class Content_Warning {

	/** Bootstrap alert style per canonical trigger level. */
	const CARD_STYLES = array(
		'high' => 'danger',
		'med'  => 'warning',
		'low'  => 'info',
	);

	/**
	 * Show content warning.
	 *
	 * If a show has a content warning, let's show it.
	 *
	 * @access public
	 * @param int $show_id Show post ID.
	 * @return array{card:string,content:string}
	 */
	public function make( $show_id ) {

		$warning_array = array(
			'card'    => 'none',
			'content' => 'none',
		);

		// If there's no post ID passed or it's not a show, we show nothing.
		if ( is_null( $show_id ) || 'post_type_shows' !== get_post_type( $show_id ) ) {
			return $warning_array;
		}

		$trigger_terms = get_the_terms( $show_id, 'lez_triggers' );
		$has_term      = ! empty( $trigger_terms ) && ! is_wp_error( $trigger_terms );
		$trigger       = $has_term ? $trigger_terms[0]->slug : get_post_meta( $show_id, 'lezshows_triggerwarning', true );
		$level         = Trigger_Warning::normalize( (string) $trigger );

		if ( ! isset( self::CARD_STYLES[ $level ] ) ) {
			return $warning_array;
		}

		$warning_array['card']    = self::CARD_STYLES[ $level ];
		$warning_array['content'] = $has_term ? term_description( $trigger_terms[0]->term_id ) : '<strong>WARNING</strong> This show may be upsetting to watch.';

		return $warning_array;
	}
}
```

- [ ] **Step 3: Verify against the running site**

`Content_Warning::make()` has exactly one caller: `_components/class-theme.php:185`. Load a show page (or call `lwtv_plugin()->content_warning( $show_id )` from wp-cli `eval`) for at least one show in each of: no trigger set, `'low'`, `'med'`/`'medium'`, `'high'`/`'on'`, and confirm the returned `card` and `content` are identical to what the page rendered before this change.

- [ ] **Step 4: Run the full pure-unit suite one more time**

Run: `vendor/bin/phpunit`
Expected: PASS. This confirms the earlier `Trigger_Warning` tests (Task 1) still cover the exact alias behavior this file now relies on.

---

## What this plan deliberately does not do

- It does not touch `plugins/lwtv-plugin/php/plugins/gravity-forms/class-gf-approvals.php`. That file is an explicit fork of a third-party plugin built against the Gravity Forms `GFFeedAddOn` framework — its callback-heavy shape and sparse docblocks are the framework's own convention, not this project's, so it isn't a fair example of "your hand-built style" and refactoring it risks breaking a framework contract for no scoring or testing benefit.
- It does not attempt a full sweep of every older `theme/` file. `Content_Warning` (Task 4) is one demonstrated, low-risk example; the pattern — extract a duplicated decision into a tested pure class when you're already in the file for another reason — is the reusable takeaway, not a mandate to rewrite everything at once.
- It does not change `show_tropes_score()`'s or `show_score()`'s point values, thresholds, or ordering. Every constant above is the same number that was already there.
