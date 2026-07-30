# Statistics Cache-Warming Rewire Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After any content edit, stats caches are proactively re-warmed (debounced so a burst collapses into one warm) instead of going cold until the next visitor pays a full rebuild.

**Architecture:** Keep the existing clear-on-`shutdown` model. Replace the broken warm wiring (a no-op stub, death-only background warm, 30-min-late scheduling) with a single deferred, debounced, comprehensive warm via Action Scheduler, plus a daily-cron backstop. Gate the redundant `wp_options` DELETE pass behind `! wp_using_ext_object_cache()` since production runs Redis.

**Tech Stack:** WordPress (PHP 8.1+), Action Scheduler, PHPUnit 11 (pure-transform harness, no WP bootstrap).

**Spec:** `docs/superpowers/specs/2026-07-30-stats-cache-warming-design.md`

## Global Constraints

- **Do NOT `git commit`.** The human reviews and commits the entire change as one diff (repo `CLAUDE.md` workflow + user preference). Each task ends with a **Checkpoint** (lint + tests + pause for review), never a commit.
- **PHP 8.1+ min**; **WordPress-Extra** standard via `phpcs.xml.dist`. Lint: `composer lint`. Autofix: `composer lint-fix`.
- Class files are `class-*.php`, one class per file, namespace mirrors path under `LWTV\`.
- Custom auto-escaped functions (`lwtv_plugin`, etc.) must not be wrapped in `esc_*`.
- Unit tests cover **pure transforms only** (no WP globals/queries). WP-glue is verified against the running site. Harness: `vendor/bin/phpunit`; bootstrap `tests/bootstrap.php` defines `ABSPATH` and `require`s classes-under-test directly.
- All new user-facing strings (none expected here) use the `'lwtv'` text domain.

---

### Task 1: Debounce helper, warm constants, deadline clearer (`Transients`)

Pure additions to `Transients` plus a unit test for the debounce math. No behavior change yet — nothing calls the new code until Task 4.

**Files:**
- Modify: `plugins/lwtv-plugin/php/_components/class-transients.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Components/WarmScheduleTest.php` (create)

**Interfaces:**
- Produces (consumed by Tasks 3 & 4):
  - `Transients::next_stats_warm_time( int $now, int $deadline, int $delay, int $max ): array` → `array( 'target' => int, 'deadline' => int )` (pure)
  - `Transients::clear_stats_warm_deadline(): void`
  - Constants: `WARM_HOOK = 'lwtv_warm_statistics_cache'`, `WARM_GROUP = 'lwtv'`, `WARM_DEADLINE_OPTION = 'lwtv_stats_warm_deadline'`, `WARM_DEBOUNCE_DELAY = 2 * MINUTE_IN_SECONDS`, `WARM_MAX_DELAY = 10 * MINUTE_IN_SECONDS`

- [ ] **Step 1: Add the new class constants**

In `class-transients.php`, immediately after the existing `const STATS_INDEX_OPTION = 'lwtv_stats_cache_index';` block, add:

```php
	/**
	 * Action Scheduler hook + group for the debounced statistics warm.
	 */
	const WARM_HOOK  = 'lwtv_warm_statistics_cache';
	const WARM_GROUP = 'lwtv';

	/**
	 * Option holding the hard deadline (unix time) for the in-progress warm
	 * burst. 0/absent means no burst is currently open.
	 *
	 * @var string
	 */
	const WARM_DEADLINE_OPTION = 'lwtv_stats_warm_deadline';

	/**
	 * Trailing debounce: fire the warm this long after the LAST edit in a burst.
	 */
	const WARM_DEBOUNCE_DELAY = 2 * MINUTE_IN_SECONDS;

	/**
	 * Hard cap: never defer the warm more than this past the FIRST edit in a burst.
	 */
	const WARM_MAX_DELAY = 10 * MINUTE_IN_SECONDS;
```

- [ ] **Step 2: Add the pure `next_stats_warm_time()` method and the deadline clearer**

Add these two methods to the class (near the other static helpers, e.g. just above `record_stats_key()`):

```php
	/**
	 * Compute when the debounced statistics warm should next fire.
	 *
	 * Pure arithmetic (no WordPress calls) so it is unit-testable. A burst of
	 * edits keeps pushing the target forward by $delay, but never past a hard
	 * deadline of first_edit + $max, so a long editing session still warms.
	 *
	 * @param int $now      Current unix time.
	 * @param int $deadline Existing burst deadline (0/absent = no burst open).
	 * @param int $delay    Trailing debounce delay in seconds.
	 * @param int $max      Max seconds to defer past the first edit in a burst.
	 * @return array { 'target' => int, 'deadline' => int }
	 */
	public static function next_stats_warm_time( int $now, int $deadline, int $delay, int $max ): array {
		if ( $deadline <= 0 ) {
			// No burst in progress: open a new one.
			$deadline = $now + $max;
		}

		$target = min( $now + $delay, $deadline );

		return array(
			'target'   => $target,
			'deadline' => $deadline,
		);
	}

	/**
	 * End the current warm-burst window. Called once the comprehensive warm has
	 * actually run (see Statistics_Cache_Warming::warm_all()).
	 *
	 * @return void
	 */
	public static function clear_stats_warm_deadline(): void {
		delete_option( self::WARM_DEADLINE_OPTION );
	}
```

- [ ] **Step 3: Make `Transients` loadable in the pure harness**

In `tests/bootstrap.php`, after the existing `require_once` lines, add (order matters — the interfaces must load before the class that implements them):

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-component.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-templater.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/class-transients.php';
```

- [ ] **Step 4: Write the failing test**

Create `tests/unit/Components/WarmScheduleTest.php`:

```php
<?php
/**
 * Unit tests for the pure debounce-timing helper behind the statistics
 * cache-warming schedule. See
 * docs/superpowers/specs/2026-07-30-stats-cache-warming-design.md.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Components;

use PHPUnit\Framework\TestCase;
use LWTV\_Components\Transients;

class WarmScheduleTest extends TestCase {

	// No burst open (deadline 0): open one and fire $delay from now.
	public function test_no_active_burst_opens_deadline_and_targets_now_plus_delay(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] ); // 1000 + max(600)
		$this->assertSame( 1120, $result['target'] );   // min(1000+120, 1600)
	}

	// Burst open with room: push target forward, keep the same deadline.
	public function test_active_burst_with_room_pushes_target_keeps_deadline(): void {
		$result = Transients::next_stats_warm_time( 1200, 1600, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1320, $result['target'] );   // min(1200+120, 1600)
	}

	// Burst open near the cap: clamp the target to the deadline.
	public function test_active_burst_near_cap_clamps_target_to_deadline(): void {
		$result = Transients::next_stats_warm_time( 1550, 1600, 120, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1600, $result['target'] );   // min(1550+120=1670, 1600)
	}

	// delay == max, fresh burst: target lands exactly on the deadline.
	public function test_delay_equal_to_max_targets_deadline(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 600, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1600, $result['target'] );
	}

	// Zero delay: warm as soon as possible within the burst window.
	public function test_zero_delay_targets_now(): void {
		$result = Transients::next_stats_warm_time( 1000, 0, 0, 600 );

		$this->assertSame( 1600, $result['deadline'] );
		$this->assertSame( 1000, $result['target'] );
	}
}
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter WarmSchedule`
Expected: FAIL — `Error: Call to undefined method LWTV\_Components\Transients::next_stats_warm_time()` (until Step 2's method is present; if you did Step 2 first, instead confirm the assertions pass — the goal is to see the test execute against your implementation).

- [ ] **Step 6: Run the full suite to verify it passes and nothing regressed**

Run: `vendor/bin/phpunit`
Expected: PASS — previous count (102) + 5 new = 107 tests, all green.

- [ ] **Step 7: Lint the touched PHP**

Run: `vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/_components/class-transients.php tests/unit/Components/WarmScheduleTest.php tests/bootstrap.php`
Expected: no violations.

- [ ] **Step 8: Checkpoint**

Confirm: 107 tests pass, phpcs clean. Do **not** commit — pause for human review of Task 1.

---

### Task 2: Gate the DELETE storm behind `! wp_using_ext_object_cache()` (`Transients`)

Independent, low-risk. Under Redis the object-cache-aware index walk already evicts real transients; the raw `wp_options` DELETE pass matches nothing and is pure waste. Not unit-testable (WP glue) — verified live.

**Files:**
- Modify: `plugins/lwtv-plugin/php/_components/class-transients.php` (`clear_cache_tier()`)

**Interfaces:**
- Consumes: nothing new. Produces: nothing new (behavioral change only).

- [ ] **Step 1: Wrap the SQL fallback in the object-cache guard**

In `clear_cache_tier()`, the inner `foreach ( $patterns as $pattern )` loop currently ends with the index walk followed by an unconditional SQL fallback block:

```php
			// Fallback for DB-stored transients (no persistent object cache),
			// and to sweep any keys that predate the index.
			$sql_pattern = str_replace( '*', '%', $pattern );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $sql_pattern
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_timeout_' . $sql_pattern
				)
			);
```

Replace it with the same block wrapped in the guard:

```php
			// Fallback for DB-stored transients (no persistent object cache), and
			// to sweep any keys that predate the index. Under a persistent object
			// cache (Redis) transients live outside wp_options, so this SQL pass
			// matches nothing — skip it entirely to avoid a needless DELETE storm
			// on every content edit.
			if ( ! wp_using_ext_object_cache() ) {
				$sql_pattern = str_replace( '*', '%', $pattern );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_' . $sql_pattern
					)
				);
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_timeout_' . $sql_pattern
					)
				);
			}
```

Leave the index walk above it and the `$changed`/`update_option()` after the loop untouched.

- [ ] **Step 2: Lint**

Run: `vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/_components/class-transients.php`
Expected: no violations.

- [ ] **Step 3: Syntax check**

Run: `php -l plugins/lwtv-plugin/php/_components/class-transients.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Checkpoint**

Do **not** commit — pause for human review. (Live DB-query verification happens in the final Task 6 verification pass.)

---

### Task 3: `warm_all()` + cliche-leaders warmer + `'all'` tier (`Statistics_Cache_Warming`)

Give the warmer a single comprehensive entry point that the debounced scheduler will target, fix the one live coverage gap (Cliche Leaders), and make it best-effort so one failing builder can't abort the rest. WP glue — verified live.

**Files:**
- Modify: `plugins/lwtv-plugin/php/schedulers/class-statistics-cache-warming.php`

**Interfaces:**
- Consumes: `Transients::clear_stats_warm_deadline()` (Task 1).
- Produces (consumed by Tasks 4 & 5):
  - `Statistics_Cache_Warming::warm_all(): void`
  - `Statistics_Cache_Warming::warm_cache_tier( string $tier = 'all', int $post_id = 0 ): void` (now handles `'all'`)

- [ ] **Step 1: Add the two `use` imports**

In the `use` block at the top of `class-statistics-cache-warming.php`, add:

```php
use LWTV\_Components\Transients;
use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;
```

- [ ] **Step 2: Default `warm_cache_tier()` to `'all'` and route it**

Replace the existing `warm_cache_tier()` method signature and switch with:

```php
	public function warm_cache_tier( string $tier = 'all', int $post_id = 0 ): void {
		lwtv_plugin()->debug_log( 'statistics', "Starting cache warming for tier: {$tier}, post ID: {$post_id}" );

		switch ( $tier ) {
			case 'all':
				$this->warm_all();
				break;
			case 'counts':
				$this->warm_count_caches();
				break;
			case 'derived':
				$this->warm_derived_caches();
				break;
			case 'stable':
				$this->warm_stable_caches();
				break;
		}

		lwtv_plugin()->debug_log( 'statistics', "Completed cache warming for tier: {$tier}" );
	}
```

(The Action Scheduler job is scheduled with empty args in Task 4, so the hook callback receives no `$tier` and falls through to the `'all'` default.)

- [ ] **Step 3: Add `warm_all()`**

Add this public method (e.g. directly below `warm_cache_tier()`):

```php
	/**
	 * Warm every statistics dataset that backs a visitor-facing view.
	 *
	 * Best-effort: each step is isolated so one failing builder cannot abort the
	 * rest. Clears the debounce window at the end because the burst is now fully
	 * warmed.
	 *
	 * @return void
	 */
	public function warm_all(): void {
		$steps = array(
			'warm_count_caches',
			'warm_death_statistics',
			'warm_taxonomy_statistics',
			'warm_on_air_statistics',
			'warm_queer_irl_statistics',
			'warm_formats_statistics',
			'warm_loved_statistics',
			'warm_worth_it_statistics',
			'warm_nation_statistics',
			'warm_station_statistics',
			'warm_cliche_leaders_statistics',
		);

		foreach ( $steps as $step ) {
			try {
				$this->$step();
			} catch ( \Throwable $e ) {
				lwtv_plugin()->error_log( 'statistics', "Cache warm step {$step} failed: " . $e->getMessage() );
			}
		}

		// The burst is fully warmed — end the debounce window.
		Transients::clear_stats_warm_deadline();

		lwtv_plugin()->debug_log( 'statistics', 'Completed full statistics cache warm.' );
	}
```

- [ ] **Step 4: Add the cliche-leaders warmer**

Add this private method alongside the other `warm_*_statistics()` methods:

```php
	/**
	 * Warm the "most cliches" leaderboard cache (backs the characters/most-cliches
	 * view). Previously unwarmed, so it always rebuilt cold after an edit.
	 *
	 * @return void
	 */
	private function warm_cliche_leaders_statistics(): void {
		( new Build_Cliche_Leaders() )->generate();

		lwtv_plugin()->debug_log( 'statistics', 'Warming cliche leaders statistics caches...' );
	}
```

- [ ] **Step 5: Lint + syntax check**

Run: `php -l plugins/lwtv-plugin/php/schedulers/class-statistics-cache-warming.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/schedulers/class-statistics-cache-warming.php`
Expected: no syntax errors, no phpcs violations.

- [ ] **Step 6: Checkpoint**

Do **not** commit — pause for human review.

---

### Task 4: Rewire invalidation to schedule the debounced warm; delete dead code (`Transients`)

Replace the no-op-stub + per-tier-background warm branching with a single debounced `schedule_stats_warm()` call, and remove the now-dead `warm_cache_tier()` stub, `schedule_cache_warming()`, and `CACHE_DURATION` constant. WP + Action Scheduler glue — verified live.

**Files:**
- Modify: `plugins/lwtv-plugin/php/_components/class-transients.php`

**Interfaces:**
- Consumes: `Transients::next_stats_warm_time()`, the `WARM_*` constants (Task 1); the `WARM_HOOK` Action Scheduler event handled by `Statistics_Cache_Warming::warm_cache_tier('all')` (Task 3).
- Produces: a single `WARM_HOOK` job per burst.

- [ ] **Step 1: Add `schedule_stats_warm()`**

Add this private method to `Transients` (e.g. below `process_deferred_cache_invalidation()`):

```php
	/**
	 * Schedule (or reschedule) the single debounced statistics warm.
	 *
	 * A burst of edits collapses into one job: each call unschedules the pending
	 * warm and reschedules it to next_stats_warm_time(), which trails the last
	 * edit by WARM_DEBOUNCE_DELAY but never past first_edit + WARM_MAX_DELAY.
	 * No-ops when Action Scheduler is unavailable — the daily cron backstop and
	 * lazy rebuild still keep pages correct.
	 *
	 * @return void
	 */
	private function schedule_stats_warm(): void {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			return;
		}

		$deadline = (int) get_option( self::WARM_DEADLINE_OPTION, 0 );
		$next     = self::next_stats_warm_time( time(), $deadline, self::WARM_DEBOUNCE_DELAY, self::WARM_MAX_DELAY );

		// Reschedule the single pending warm forward to the new target.
		as_unschedule_all_actions( self::WARM_HOOK, array(), self::WARM_GROUP );
		as_schedule_single_action( $next['target'], self::WARM_HOOK, array(), self::WARM_GROUP );

		update_option( self::WARM_DEADLINE_OPTION, $next['deadline'], false );

		lwtv_plugin()->debug_log( 'caching', 'Scheduled debounced statistics warm for ' . $next['target'] );
	}
```

- [ ] **Step 2: Rewrite the warm section of `process_deferred_cache_invalidation()`**

Replace the current method body (the version that builds `$warming_requests`, calls `$this->warm_cache_tier(...)` for `immediate`, and `$this->schedule_cache_warming(...)` for `background`) with:

```php
	public function process_deferred_cache_invalidation(): void {
		if ( empty( self::$invalidation_queue ) ) {
			return;
		}

		$dependencies      = $this->get_cache_dependencies();
		$patterns_to_clear = array();

		foreach ( self::$invalidation_queue as $request ) {
			foreach ( $this->get_tiers_for_content_type( $request['content_type'] ) as $tier ) {
				if ( ! isset( $dependencies[ $tier ] ) ) {
					continue;
				}

				$tier_config = $dependencies[ $tier ];

				// 'preserve' tiers are intentionally never cleared on save.
				if ( 'preserve' === $tier_config['priority'] ) {
					continue;
				}

				foreach ( $tier_config['patterns'] as $pattern ) {
					$patterns_to_clear[ $pattern ] = true;
				}
			}
		}

		// Clear all affected patterns in one batch.
		if ( ! empty( $patterns_to_clear ) ) {
			$this->clear_cache_tier( array_keys( $patterns_to_clear ) );
		}

		// Schedule ONE debounced, comprehensive warm. A burst of edits reschedules
		// the same job forward, so the whole burst warms the final state once.
		$this->schedule_stats_warm();

		$count = count( self::$invalidation_queue );
		lwtv_plugin()->debug_log( 'caching', "Processed {$count} deferred cache invalidation requests" );

		// Clear the queue.
		self::$invalidation_queue = array();
	}
```

- [ ] **Step 3: Delete the dead code**

Remove all three of the following from `class-transients.php`:

1. The `warm_cache_tier()` stub (the private method whose body only calls `debug_log()` "Warming ... cache tier for post ID").
2. The `schedule_cache_warming()` method (the one that calls `as_schedule_single_action( time() + self::CACHE_DURATION, 'lwtv_warm_statistics_cache', ... )`).
3. The now-unused `const CACHE_DURATION = HOUR_IN_SECONDS / 2;` (grep-confirmed used only by `schedule_cache_warming()`).

- [ ] **Step 4: Confirm no dangling references**

Run: `grep -n "warm_cache_tier\|schedule_cache_warming\|CACHE_DURATION" plugins/lwtv-plugin/php/_components/class-transients.php`
Expected: **no output** (all three are gone from this file; the surviving `warm_cache_tier` lives only in `Statistics_Cache_Warming`).

- [ ] **Step 5: Lint + syntax + full suite**

Run: `php -l plugins/lwtv-plugin/php/_components/class-transients.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/_components/class-transients.php && vendor/bin/phpunit`
Expected: no syntax errors, no phpcs violations, 107 tests pass (the pure helper still loads).

- [ ] **Step 6: Checkpoint**

Do **not** commit — pause for human review.

---

### Task 5: Daily-cron backstop (`cli-generate.php`)

A once-a-day comprehensive warm so caches are never stale for long even with zero edits and zero traffic. WP-CLI glue — verified by running the command.

**Files:**
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-generate.php` (`run_cron_daily()` + `use` block)

**Interfaces:**
- Consumes: `Statistics_Cache_Warming::warm_all()` (Task 3).

- [ ] **Step 1: Add the `use` import**

In the `use` block at the top of `cli-generate.php`, add:

```php
use LWTV\Schedulers\Statistics_Cache_Warming;
```

- [ ] **Step 2: Warm at the end of `run_cron_daily()`**

At the end of `run_cron_daily()` (after the `run_debug_checker( $day )` call), add:

```php
		// Warm the statistics caches so they're never stale for long with no edits.
		\WP_CLI::log( 'Warming statistics caches...' );
		( new Statistics_Cache_Warming() )->warm_all();
		\WP_CLI::success( 'Statistics caches warmed.' );
```

- [ ] **Step 3: Lint + syntax check**

Run: `php -l plugins/lwtv-plugin/php/wp-cli/cli-generate.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/wp-cli/cli-generate.php`
Expected: no syntax errors, no phpcs violations.

- [ ] **Step 4: Checkpoint**

Do **not** commit — pause for human review.

---

### Task 6: Live verification (against the running site)

None of the WP/Action-Scheduler glue is unit-testable; verify it against `lwtv.local` (see the local wp-cli setup). Report results; do not commit.

- [ ] **Step 1: Full suite + lint sweep**

Run: `vendor/bin/phpunit && composer lint`
Expected: 107 tests pass; phpcs clean across the project.

- [ ] **Step 2: Single-edit schedules one warm ~2 min out**

Edit and save one show in wp-admin. Then:
Run: `wp action-scheduler list --hook=lwtv_warm_statistics_cache --status=pending` (or inspect Tools → Scheduled Actions).
Expected: exactly one pending `lwtv_warm_statistics_cache` action, scheduled ~2 minutes ahead. Confirm `wp option get lwtv_stats_warm_deadline` returns a timestamp ~10 min ahead.

- [ ] **Step 3: A burst collapses to one warm**

In quick succession, save a show and 2–3 characters.
Expected: still exactly **one** pending `lwtv_warm_statistics_cache` action (rescheduled forward), not one per save.

- [ ] **Step 4: The warm runs and leaves caches warm**

Trigger the queue: `wp action-scheduler run --hook=lwtv_warm_statistics_cache`.
Then: `wp lwtv cache status`.
Expected: tracked stats transients show `cached => yes`; `wp option get lwtv_stats_warm_deadline` now returns nothing (deadline cleared by `warm_all()`).

- [ ] **Step 5: No DELETE storm under Redis**

With Redis active, save a show with `SAVEQUERIES` on (or Query Monitor) and inspect queries at `shutdown`.
Expected: no `DELETE FROM wp_options WHERE option_name LIKE '_transient_%'` queries fire.

- [ ] **Step 6: Daily cron warms everything**

Run: `wp lwtv generate cron daily`.
Expected: log shows "Warming statistics caches..." → "Statistics caches warmed."; `wp lwtv cache status` shows tracked transients cached.

- [ ] **Step 7: Eviction canary still passes**

Run: `wp lwtv cache verify`.
Expected: canary is evicted on invalidation (existing behavior intact).

- [ ] **Step 8: Report & hand off**

Summarize verification results for the human. Do **not** commit — the human reviews and commits the whole change as one diff.

---

## Self-Review

**Spec coverage:**
- Goal 1 (proactive warm after edit) → Tasks 3+4. ✓
- Goal 2 (saves stay fast / deferred) → Task 4 (`schedule_stats_warm` defers to Action Scheduler; no inline warm). ✓
- Goal 3 (burst → one warm) → Task 1 (`next_stats_warm_time`) + Task 4 (reschedule-forward). ✓
- Goal 4 (coverage incl. missing warmers) → Task 3 (`warm_all` + cliche-leaders; Scores dropped as dead code per spec finding). ✓
- Goal 5 (stop DELETE storm under Redis) → Task 2. ✓
- Goal 6 (daily backstop) → Task 5. ✓
- Testing (pure debounce unit test + live checklist) → Task 1 Steps 4–6, Task 6. ✓
- Non-goals (stampede lock, per-type granularity) → not implemented, as intended. ✓

**Placeholder scan:** No TBD/TODO; every code step has concrete code; every run step has an exact command and expected result.

**Type consistency:** `next_stats_warm_time(int,int,int,int): array{target,deadline}` defined in Task 1, consumed with those exact args in Task 4. `clear_stats_warm_deadline()` defined in Task 1, called in Task 3's `warm_all()`. `warm_all()` defined in Task 3, called in Tasks 4 (via the `'all'` hook route) and 5. `WARM_HOOK`/`WARM_GROUP`/`WARM_DEADLINE_OPTION` consistent across Tasks 1 and 4. Consistent. ✓
