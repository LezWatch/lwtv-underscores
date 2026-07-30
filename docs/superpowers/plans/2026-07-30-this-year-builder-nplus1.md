# This Year Builders N+1 Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut the DB round-trips of a cold `this-year` stats rebuild (kill 7 `get_page_by_path` loops, prime caches before N+1 loops, cache two adjacent `statistics/build/` queries) without changing any output.

**Architecture:** Two composable behavior-preserving techniques — (1) carry the post `ID` that the SQL already fetches through the builders so no downstream code re-resolves `slug → ID`, and (2) prime the WP object cache in bulk before each per-row loop so `get_field`/`get_post_meta`/`get_the_terms`/`get_permalink` become in-memory hits. Correctness is proven by a byte-identical golden-output diff plus a query-count drop on the running site; these are WordPress-glue methods and are not unit-testable.

**Tech Stack:** WordPress (PHP 8.1+), ACF, Action Scheduler, WP object cache (Redis in prod), WP-CLI against `lwtv.local`.

**Spec:** `docs/superpowers/specs/2026-07-30-this-year-builder-nplus1-design.md`

## Revision — 2026-07-30 (bulk slug→id map, supersedes Tasks 2 & 3 "carry ID")

During execution, Task 2 revealed that `get_shows_for_year()` / `get_characters_for_year()`
are returned **verbatim by a public REST endpoint** (`rest-api/class-this-year-json.php`
`one_year()` / `ten_years()`). So the original "carry an `'id'` key through the returned
array" approach would have changed a public API response (added an `id` field, leaked
internal post IDs) — a real output change, not internal plumbing. It also required a
`_v2_` cache-key bump with a deploy-transition risk.

**Revised approach:** the base builders' output is left **completely unchanged** (no `id`
key, no cache-key bump). The methods that need IDs call a new private
`map_slugs_to_ids( array $slugs, string $post_type ): array` helper that resolves all
slugs in ONE `WHERE post_name IN (…)` query, replacing the per-row `get_page_by_path()`.
This kills the same N+1, changes zero output, and needs no cache versioning. The
cache-priming half of the plan is unchanged. Tasks 2 and 3 are now executed from the
revised briefs; the golden-diff dump (Task 1) drops all `id`-stripping and adds the REST
`one_year()` output so the public surface is covered. Sections below marked "carry ID" /
`_v2_` are historical — follow the revised briefs.

- **Do NOT `git commit`.** Each task ends with a **Checkpoint** (verify + pause for review). The human commits the whole diff.
- **Behavior-preserving:** after every code task, the golden dump MUST be byte-identical to the Task 1 baseline. A diff is a failure — stop and investigate, do not "adjust the baseline."
- **No SQL year pre-filtering** on serialized `appears`/`airdates` meta (would risk dropping rows). Keep the exact PHP filtering.
- **PHP 8.1+**, **WordPress-Extra** via `phpcs.xml.dist`. Lint: `composer lint`. Tests: `vendor/bin/phpunit`.
- Meta prefixes: shows `lezshows_`, characters `lezchars_`. Class files `class-*.php`, namespace mirrors path under `LWTV\`.
- **Running `wp` on lwtv.local** needs the Local socket override (site id `aCt09KKZS`; confirm current if it fails):
  ```
  SOCK="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock"
  WPP="/Users/ipstenu/Websites/Local/lwtv-new/app/public"
  php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" <args>
  ```
- Scratchpad for throwaway scripts/artifacts: `/private/tmp/claude-501/-Users-ipstenu-Development-lezwatch-lwtv-underscores/6d456347-e1a6-44ba-9dda-c2f2067b825e/scratchpad`

---

### Task 1: Capture the golden baseline (safety net, no source changes)

Build the dump + measure scripts and capture the **before** state. No plugin code changes in this task.

**Files:**
- Create: `<scratchpad>/dump-thisyear.php` (throwaway, not committed)
- Create: `<scratchpad>/measure-thisyear.php` (throwaway, not committed)
- Artifacts: `<scratchpad>/baseline.json`, `<scratchpad>/baseline-queries.txt`

**Interfaces:**
- Produces: `baseline.json` (normalized output of every affected public method) and `baseline-queries.txt` (cold-rebuild query counts) that all later tasks diff/compare against.

- [ ] **Step 1: Write the dump script**

Create `<scratchpad>/dump-thisyear.php`:

```php
<?php
use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Shows_Builder;
use LWTV\Statistics\Build\Taxonomy_Optimized;
use LWTV\Statistics\Build\Dead;
use LWTV\Rest_API\This_Year_JSON;

$year = 2023; // a complete year with real data

$cb = new Characters_Builder();
$sb = new Shows_Builder();
$tx = new Taxonomy_Optimized();
$dd = new Dead();

$overview            = $cb->get_overview_character_stats( $year );
$actor_counts        = $cb->get_actor_counts_for_year( $year );
// actor_counts maps are order-insensitive (source query has no ORDER BY); ksort
// so key order can't cause a false diff. Consumers sort by value anyway.
ksort( $overview['actor_counts'] );
ksort( $actor_counts );

$out = array();
$out['characters_for_year']      = $cb->get_characters_for_year( $year );
$out['overview_char_stats']      = $overview;
$out['actor_counts']             = $actor_counts;
$out['character_extras']         = $cb->get_character_extras_for_year( $year );
$out['character_count']          = $cb->get_character_count_for_year( $year );
$out['dead_character_count']     = $cb->get_dead_character_count_for_year( $year );
$out['dead_characters_for_year'] = $cb->get_dead_characters_for_year( $year );
$out['characters_with_shows']    = $cb->get_characters_with_shows_for_year( $year );

$out['shows_for_year']           = $sb->get_shows_for_year( $year );
$out['show_count']               = $sb->get_show_count_for_year( $year );
$out['started_show_count']       = $sb->get_started_show_count_for_year( $year );
$out['ended_show_count']         = $sb->get_ended_show_count_for_year( $year );
$out['shows_with_characters']    = $sb->get_shows_with_characters_for_year( $year );
$out['shows_by_name']            = $sb->get_shows_for_year_by_name( $year );
$out['shows_by_format']          = $sb->get_shows_for_year_by_format( $year );
$out['shows_by_nation']          = $sb->get_shows_for_year_by_nation( $year );
$out['new_shows']                = $sb->get_new_shows_for_year( $year );
$out['ended_shows']              = $sb->get_ended_shows_for_year( $year );

$country_slugs = get_terms( array( 'taxonomy' => 'lez_country', 'fields' => 'slugs', 'hide_empty' => false ) );
$station_slugs = get_terms( array( 'taxonomy' => 'lez_stations', 'fields' => 'slugs', 'hide_empty' => false ) );
$out['bulk_first_years_country']  = is_wp_error( $country_slugs ) ? array() : $tx->get_bulk_first_years( 'lez_country', $country_slugs );
$out['bulk_first_years_stations'] = is_wp_error( $station_slugs ) ? array() : $tx->get_bulk_first_years( 'lez_stations', $station_slugs );

$out['dead_generate_years']      = $dd->generate_years( 'array' );
$out['dead_generate_years_data'] = $dd->generate_years_data();
$out['dead_generate_list']       = $dd->generate_list( 'array' );
$out['dead_version_hash']        = $dd->get_data_version_hash();

// Public REST surface: one_year() returns the builder arrays verbatim, so this
// is the real external contract the refactor must not change. Capturing it here
// is what makes the golden diff cover the public API, not just internal calls.
$out['rest_one_year']            = ( new This_Year_JSON() )->one_year( $year );

$path = getenv( 'LWTV_DUMP_OUT' ) ?: '/tmp/lwtv-dump.json';
file_put_contents( $path, wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n" );
WP_CLI::success( 'Dumped to ' . $path );
```

- [ ] **Step 2: Write the query-count script**

Create `<scratchpad>/measure-thisyear.php`:

```php
<?php
use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Shows_Builder;

$year = 2023;
$cb   = new Characters_Builder();
$sb   = new Shows_Builder();
global $wpdb;

// Force a cold rebuild by clearing this year's this-year transients (both old and
// _v2_ keys) so the measurement reflects real rebuild cost.
foreach ( array(
	'lwtv_characters_year_', 'lwtv_characters_year_v2_',
	'lwtv_characters_shows_year_',
	'lwtv_overview_char_stats_year_',
	'lwtv_shows_year_', 'lwtv_shows_year_v2_',
	'lwtv_shows_characters_year_',
	'lwtv_shows_year_by_name_', 'lwtv_shows_year_by_format_', 'lwtv_shows_year_by_nation_',
) as $prefix ) {
	lwtv_plugin()->delete_transient( $prefix . $year );
}

$measure = function ( string $label, callable $fn ) use ( $wpdb ) {
	// Clear again per-call so each method is measured cold.
	$start = $wpdb->num_queries;
	$fn();
	WP_CLI::log( sprintf( '%-40s %d queries', $label, $wpdb->num_queries - $start ) );
};

// Clear, then measure the two heaviest end-to-end builders cold.
$measure( 'characters_with_shows (cold)', function () use ( $cb, $year ) {
	$cb->clear_year_cache( $year );
	lwtv_plugin()->delete_transient( 'lwtv_characters_shows_year_' . $year );
	$cb->get_characters_with_shows_for_year( $year );
} );
$measure( 'shows_by_name (cold)', function () use ( $sb, $year ) {
	$sb->clear_year_cache( $year );
	lwtv_plugin()->delete_transient( 'lwtv_shows_year_by_name_' . $year );
	$sb->get_shows_for_year_by_name( $year );
} );
$measure( 'overview_char_stats (cold)', function () use ( $cb, $year ) {
	lwtv_plugin()->delete_transient( 'lwtv_overview_char_stats_year_' . $year );
	$cb->get_overview_character_stats( $year );
} );
WP_CLI::success( 'Measured.' );
```

- [ ] **Step 3: Capture the baseline output**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/baseline.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
```
Expected: `Success: Dumped to <scratchpad>/baseline.json`, and the file is non-empty valid JSON.

- [ ] **Step 4: Capture the baseline query counts**

Run:
```
php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/measure-thisyear.php" 2>/dev/null | tee "<scratchpad>/baseline-queries.txt"
```
Expected: three lines with per-method cold query counts (record them — these are the numbers to beat).

- [ ] **Step 5: Checkpoint**

Confirm `baseline.json` and `baseline-queries.txt` exist and look sane. No source changed. Pause for review.

---

### Task 2: Shows-builder — carry ID + prime caches

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php`

**Interfaces:**
- Produces: `get_shows_for_year()` output elements now include `'id' => (int)`. `get_show_id_by_slug()` is removed.

- [ ] **Step 1: Version the base cache key and carry the ID**

In `get_shows_for_year()`, change the cache key line:
```php
$cache_key     = 'lwtv_shows_year_v2_' . $year;
```
and in the `$shows[] = array( ... )` block add the `id`:
```php
$shows[] = array(
	'id'      => (int) $row['ID'],
	'slug'    => $row['slug'],
	'name'    => $row['name'],
	'started' => $on_air_data['started'],
	'ended'   => $on_air_data['ended'],
);
```

- [ ] **Step 2: Use the carried ID in `get_shows_with_characters_for_year()`**

Replace:
```php
		$show_ids = array();
		foreach ( $shows as $show ) {
			$show_ids[] = $this->get_show_id_by_slug( $show['slug'] );
		}
```
with:
```php
		$show_ids = array_values( array_filter( array_map( static fn( $s ) => (int) $s['id'], $shows ) ) );
```

- [ ] **Step 3: Use the carried ID in `enhance_shows_with_characters()`**

Replace:
```php
			$show_id    = $this->get_show_id_by_slug( $show['slug'] );
```
with:
```php
			$show_id    = (int) $show['id'];
```

- [ ] **Step 4: Prime caches + use the carried ID in the three `_by_*` methods**

In each of `get_shows_for_year_by_name()`, `get_shows_for_year_by_format()`, and `get_shows_for_year_by_nation()`, immediately after the `if ( empty( $shows ) ) { return array(); }` guard, insert:
```php
		// Prime post rows, meta, and terms once so the per-show reads below hit cache.
		$prime_ids = array_values( array_filter( array_map( static fn( $s ) => (int) $s['id'], $shows ) ) );
		if ( ! empty( $prime_ids ) ) {
			_prime_post_caches( $prime_ids, true, true );
		}
```
and in each of their `foreach ( $shows as $show )` loops replace:
```php
			$show_id = $this->get_show_id_by_slug( $show['slug'] );
```
with:
```php
			$show_id = (int) $show['id'];
```
(Keep the existing `if ( ! $show_id ) { continue; }` guard — harmless and preserves behavior.)

- [ ] **Step 5: Prime caches in `get_characters_for_shows_in_year()`**

After `if ( empty( $candidate_character_ids ) ) { return array(); }`, insert:
```php
		$candidate_character_ids = array_map( 'intval', $candidate_character_ids );
		update_meta_cache( 'post', $candidate_character_ids );
```
Then, immediately before `$character_data = $this->get_character_data_by_ids( array_unique( $character_ids ) );`, insert:
```php
		$matched_ids = array_values( array_unique( array_map( 'intval', $character_ids ) ) );
		if ( ! empty( $matched_ids ) ) {
			update_meta_cache( 'post', $matched_ids );
			update_object_term_cache( $matched_ids, 'post_type_characters' );
		}
```

- [ ] **Step 6: Prime posts in `get_character_data_by_ids()`**

After its `if ( empty( $character_ids ) ) { return array(); }` guard, insert:
```php
		_prime_post_caches( array_map( 'intval', $character_ids ), false, false );
```

- [ ] **Step 7: Delete the now-unused `get_show_id_by_slug()`**

Remove the entire `get_show_id_by_slug()` method (docblock + body).

- [ ] **Step 8: Confirm no dangling references**

Run: `grep -n "get_show_id_by_slug" plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php`
Expected: no output.

- [ ] **Step 9: Lint + syntax**

Run: `php -l plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php`
Expected: no syntax errors, no violations.

- [ ] **Step 10: Golden diff — output must be identical**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/after.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
diff "<scratchpad>/baseline.json" "<scratchpad>/after.json" && echo "IDENTICAL"
```
Expected: `IDENTICAL` (no diff). If anything differs, STOP and investigate.

- [ ] **Step 11: Checkpoint**

Golden diff identical, lint clean. Pause for review. Do not commit.

---

### Task 3: Characters-builder — carry ID + prime caches

**Files:**
- Modify: `plugins/lwtv-plugin/php/this-year/build/class-characters-builder.php`

**Interfaces:**
- Produces: `get_characters_for_year()` output elements now include `'id' => (int)`. `get_character_id_by_slug()` is removed.

- [ ] **Step 1: Version the base cache key, prime meta, and carry the ID in `get_characters_for_year()`**

Change the cache key:
```php
$cache_key     = 'lwtv_characters_year_v2_' . $year;
```
Immediately after `$results = $wpdb->get_results( $query, ARRAY_A );`, insert:
```php
			// Prime meta once so the per-character get_field()/get_post_meta() below hit cache.
			$prime_ids = array_values( array_filter( array_map( static fn( $r ) => (int) $r['ID'], (array) $results ) ) );
			if ( ! empty( $prime_ids ) ) {
				update_meta_cache( 'post', $prime_ids );
			}
```
In the `$characters[ $row['slug'] ] = array( ... )` block add the `id`:
```php
					$characters[ $row['slug'] ] = array(
						'id'          => (int) $row['ID'],
						'slug'        => $row['slug'],
						'name'        => $row['name'],
						'dead'        => (bool) $row['is_dead'],
						'death_years' => $row['death_years'] ?? array(),
						'last_death'  => $row['last_death'] ?? '',
					);
```

- [ ] **Step 2: Prime meta + terms in `get_overview_character_stats()`**

Immediately after its `$results = $wpdb->get_results( $query, ARRAY_A );`, insert:
```php
				$prime_ids = array_values( array_filter( array_map( static fn( $r ) => (int) $r['ID'], (array) $results ) ) );
				if ( ! empty( $prime_ids ) ) {
					update_meta_cache( 'post', $prime_ids );
					update_object_term_cache( $prime_ids, 'post_type_characters' );
				}
```

- [ ] **Step 3: Use the carried ID in `get_characters_with_shows_for_year()`**

Replace:
```php
		$character_ids = array();
		foreach ( $characters as $character ) {
			$character_ids[] = $this->get_character_id_by_slug( $character['slug'] );
		}
```
with:
```php
		$character_ids = array_values( array_filter( array_map( static fn( $c ) => (int) $c['id'], $characters ) ) );
```

- [ ] **Step 4: Prime meta + use the carried ID in `enhance_characters_with_shows()`**

At the very top of the method body (before `$show_ids = array();`), insert:
```php
		$character_ids = array_values( array_filter( array_map( 'intval', $character_ids ) ) );
		if ( ! empty( $character_ids ) ) {
			update_meta_cache( 'post', $character_ids );
		}
```
Then in the second loop replace:
```php
			$character_id = $this->get_character_id_by_slug( $character['slug'] );
```
with:
```php
			$character_id = (int) $character['id'];
```

- [ ] **Step 5: Prime posts in `get_show_data_by_ids()`**

After its `if ( empty( $show_ids ) ) { return array(); }` guard, insert:
```php
		_prime_post_caches( array_map( 'intval', $show_ids ), false, false );
```

- [ ] **Step 6: Delete the now-unused `get_character_id_by_slug()`**

Remove the entire `get_character_id_by_slug()` method (docblock + body).

- [ ] **Step 7: Confirm no dangling references**

Run: `grep -n "get_character_id_by_slug" plugins/lwtv-plugin/php/this-year/build/class-characters-builder.php`
Expected: no output.

- [ ] **Step 8: Lint + syntax**

Run: `php -l plugins/lwtv-plugin/php/this-year/build/class-characters-builder.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/this-year/build/class-characters-builder.php`
Expected: no syntax errors, no violations.

- [ ] **Step 9: Golden diff — output must be identical**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/after.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
diff "<scratchpad>/baseline.json" "<scratchpad>/after.json" && echo "IDENTICAL"
```
Expected: `IDENTICAL`. If anything differs, STOP and investigate.

- [ ] **Step 10: Checkpoint**

Golden diff identical, lint clean. Pause for review.

---

### Task 4: Cache `get_bulk_first_years()` + track it for invalidation

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-transients.php`

**Interfaces:**
- Consumes: `lwtv_plugin()->get_transient()` / `->set_transient()`.
- Produces: `bulk_first_years_*` transients, tracked in the `counts` tier.

- [ ] **Step 1: Add the transient wrapper to `get_bulk_first_years()`**

In `get_bulk_first_years()`, the method currently initializes `$first_years`, then has `if ( empty( $slugs ) ) { return $first_years; }`. Immediately after that empty-guard, insert the cache check:
```php
		$cache_key   = 'bulk_first_years_' . $taxonomy . '_' . md5( wp_json_encode( $slugs ) );
		$cached_data = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}
```
Then, immediately before the final `return $first_years;`, insert:
```php
		lwtv_plugin()->set_transient( $cache_key, $first_years, DAY_IN_SECONDS );
```

- [ ] **Step 2: Track the new key in the counts tier**

In `class-transients.php`, `get_cache_dependencies()`, in the `counts` tier `patterns` array, add `'bulk_first_years_*',` alongside the existing `'bulk_show_counts_*',` entry.

- [ ] **Step 3: Lint + syntax**

Run: `php -l plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php && php -l plugins/lwtv-plugin/php/_components/class-transients.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php plugins/lwtv-plugin/php/_components/class-transients.php`
Expected: no syntax errors, no violations.

- [ ] **Step 4: Golden diff + full suite**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/after.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
diff "<scratchpad>/baseline.json" "<scratchpad>/after.json" && echo "IDENTICAL"
vendor/bin/phpunit 2>&1 | tail -4
```
Expected: `IDENTICAL`; 107 tests pass (the transient tracking still loads in the harness).

- [ ] **Step 5: Checkpoint**

Pause for review.

---

### Task 5: Request-memoize `get_data_version_hash()` (class-dead.php)

**Files:**
- Modify: `plugins/lwtv-plugin/php/statistics/build/class-dead.php`

**Interfaces:**
- Produces: same hash value, computed once per request.

- [ ] **Step 1: Replace the method body with a memoized, null-safe version**

Replace the whole `get_data_version_hash()` method with:
```php
	public function get_data_version_hash() {
		static $hash = null;
		if ( null !== $hash ) {
			return $hash;
		}

		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'post_type_characters'
			)
		);

		$hash = md5( (string) $last_modified );

		return $hash;
	}
```

- [ ] **Step 2: Lint + syntax**

Run: `php -l plugins/lwtv-plugin/php/statistics/build/class-dead.php && vendor/bin/phpcs --standard=phpcs.xml.dist plugins/lwtv-plugin/php/statistics/build/class-dead.php`
Expected: no syntax errors, no violations.

- [ ] **Step 3: Golden diff — the hash and all Dead outputs must be identical**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/after.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
diff "<scratchpad>/baseline.json" "<scratchpad>/after.json" && echo "IDENTICAL"
```
Expected: `IDENTICAL` (memoization must not change the value, only how often it's computed).

- [ ] **Step 4: Checkpoint**

Pause for review.

---

### Task 6: Final verification — output identical, queries dropped

**Files:** none (verification only).

- [ ] **Step 1: Full suite + project lint**

Run: `vendor/bin/phpunit && composer lint`
Expected: 107 tests pass; phpcs clean across the project.

- [ ] **Step 2: Final golden diff**

Run:
```
LWTV_DUMP_OUT="<scratchpad>/after.json" php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/dump-thisyear.php" 2>/dev/null
diff "<scratchpad>/baseline.json" "<scratchpad>/after.json" && echo "IDENTICAL"
```
Expected: `IDENTICAL`.

- [ ] **Step 3: Query-count drop**

Run:
```
php -d error_reporting=0 -d mysqli.default_socket="$SOCK" "$(which wp)" --path="$WPP" eval-file "<scratchpad>/measure-thisyear.php" 2>/dev/null | tee "<scratchpad>/after-queries.txt"
diff "<scratchpad>/baseline-queries.txt" "<scratchpad>/after-queries.txt" || true
```
Expected: each method's cold query count is **substantially lower** than the baseline recorded in Task 1 (no per-row `get_page_by_path`/meta/term/permalink queries).

- [ ] **Step 4: Report & hand off**

Summarize: golden diff identical, per-method query deltas (before → after), tests + lint green. Do **not** commit — the human reviews and commits the whole change as one diff. (Note: the `_v2_` base cache keys will cold-rebuild once per year on first prod access; the Redis DELETE gate and warm cover them.)

---

## Self-Review

**Spec coverage:**
- A. Carry ID + delete `*_id_by_slug` → Tasks 2 (shows, 5 sites) & 3 (characters, 2 sites). ✓
- A. `_v2_` cache-key bump on the two base builders → Task 2 Step 1, Task 3 Step 1. ✓
- B. Prime caches per loop (the spec's table) → Task 2 Steps 4–6, Task 3 Steps 1–2,4–5. ✓
- C. `get_bulk_first_years()` caching → Task 4 (+ invalidation tracking, a spec-implied correctness need). ✓
- D. `get_data_version_hash()` memoization → Task 5. ✓
- Testing: golden-diff baseline (Task 1) + per-task diff + query-count drop (Task 6). ✓
- Non-goal (no SQL year pre-filter) respected — no task adds one. ✓

**Placeholder scan:** No TBD/TODO; every code step shows exact before/after; every run step has a command and expected result. `<scratchpad>` is a defined path (Global Constraints).

**Type consistency:** `'id' => (int)` added in Tasks 2/3 Step 1 is consumed as `(int) $x['id']` in the same tasks. `get_show_id_by_slug`/`get_character_id_by_slug` deleted only after all their callers are replaced (verified by the grep steps). `bulk_first_years_` key in Task 4 Step 1 matches the `bulk_first_years_*` pattern added in Step 2. `update_meta_cache`/`update_object_term_cache`/`_prime_post_caches` used with the argument shapes from the spec's priming table. Consistent. ✓
