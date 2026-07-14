# Most Clichéd Characters Statistics View — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `/statistics/characters/most-cliches/` view that ranks characters by how many `lez_cliches` terms each carries, shown as a bar chart plus a ranked table (top 20, ties at the cutoff included).

**Architecture:** A new build class runs one grouped SQL query that counts `lez_cliches` terms per published character and returns a top-20-with-ties array keyed by character ID. The existing stats generator exposes it through the existing `Stats_Handler` barchart path; a new template renders both that chart and a ranked table. Routing reuses the generic `statistics/characters/{view}` rewrite already in place — no new rewrite rule.

**Tech Stack:** WordPress (theme + `lwtv-plugin`), PHP 8.1+, PSR-4 under `LWTV\`, `$wpdb`, Chart.js (via existing formatters), Bootstrap table markup.

## Global Constraints

- **PHP:** 8.1+ minimum. **WordPress:** 6.5+ minimum.
- **Lint:** `composer lint` (phpcs, WordPress-Extra) must pass. Autofix: `composer lint-fix`.
- **No PHP unit-test harness exists** in this repo. Verification is `composer lint` + manual browser checks against the local site `https://lwtv.local/`. Do not scaffold phpunit.
- **Class files:** named `class-*.php`, one class per file, namespace mirrors directory path under `LWTV\`.
- **i18n:** all user-facing strings use `__()`/`_e()`/`esc_html_e()` etc. with the `'lwtv'` text domain.
- **Taxonomy slug:** characters' clichés live in `lez_cliches`. Character CPT slug is `post_type_characters` (`LWTV\CPTs\Characters::SLUG`).
- **Auto-escaped functions** (do NOT wrap in `esc_*`): `lwtv_plugin`, `generate_characters_statistics`, and the others listed in `phpcs.xml.dist`.
- **Cliché counting includes ALL `lez_cliches` terms** — no term is excluded (parity with the existing clichés popularity page).

---

### Task 1: Build class + generator wiring (data layer)

Creates the query that ranks characters by cliché count and exposes it through the stats generator. These change together — the generator case is meaningless without the build class, and neither is browser-visible on its own.

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/build/class-cliche-leaders.php`
- Modify: `plugins/lwtv-plugin/php/statistics/class-stats-generator.php` (add `use` alias near line 20; add `case` in `generate_characters()` near line 166)

**Interfaces:**
- Consumes: `lwtv_plugin()->get_transient()` / `set_transient()`, `lwtv_plugin()->debug_log()`, global `$wpdb`, `get_permalink()`, `WEEK_IN_SECONDS`.
- Produces: `LWTV\Statistics\Build\Cliche_Leaders::generate(): array` returning `[ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]`, sorted highest count first. Consumed by Task 2 (template) and by the generator's `'most-cliches'` case for the barchart.

- [ ] **Step 1: Create the build class**

Create `plugins/lwtv-plugin/php/statistics/build/class-cliche-leaders.php`:

```php
<?php
/**
 * Most Clichéd Characters Query Class
 *
 * Ranks published characters by how many lez_cliches terms each one carries.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cliche_Leaders {

	/**
	 * Number of top characters to show before including ties.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 20;

	/**
	 * Generate the most-clichéd-characters leaderboard.
	 *
	 * Returns the top TOP_LIMIT characters by cliché count, plus any characters
	 * tied at the cutoff count. Keyed by character ID, ordered highest first.
	 *
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]
	 */
	public function generate() {
		$transient = 'cliche_leaders_characters';
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_leaders_data();

			// Cache for 7 days since character data is relatively stable.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build the leaderboard by counting lez_cliches terms per character.
	 *
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data() {
		global $wpdb;

		// Count how many lez_cliches terms each published character carries.
		// No user input: taxonomy and post_type are hardcoded literals.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, COUNT(tr.term_taxonomy_id) as cliche_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->term_relationships} tr ON chars.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			AND tt.taxonomy = 'lez_cliches'
			GROUP BY chars.ID
			ORDER BY cliche_count DESC";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cliché leaders query returned no results: ' . $wpdb->last_error );
			return array();
		}

		// Cutoff = the cliché count at position TOP_LIMIT. Keep everyone at or above it,
		// so ties at the boundary are all included. When there are fewer than TOP_LIMIT
		// characters overall, the threshold stays 1 and all of them are kept.
		$threshold = 1;
		if ( count( $results ) >= self::TOP_LIMIT ) {
			$threshold = (int) $results[ self::TOP_LIMIT - 1 ]['cliche_count'];
		}

		$leaders = array();
		foreach ( $results as $row ) {
			$count = (int) $row['cliche_count'];
			if ( $count < $threshold ) {
				break; // Results are ordered DESC, so nothing below the threshold remains.
			}
			$id             = (int) $row['id'];
			$leaders[ $id ] = array(
				'name'  => $row['name'],
				'count' => $count,
				'url'   => get_permalink( $id ),
			);
		}

		return $leaders;
	}
}
```

- [ ] **Step 2: Add the `use` alias in the generator**

In `plugins/lwtv-plugin/php/statistics/class-stats-generator.php`, add this line alongside the other `Build\` use statements (they sit just after the `namespace`/`ABSPATH` block, around line 19-21):

```php
use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;
```

- [ ] **Step 3: Add the `most-cliches` case to `generate_characters()`**

In the same file, inside the `switch ( $type )` in `generate_characters()` (the `case 'cliches':` block is around line 149-151), add a new case immediately after the `'cliches'` case:

```php
			case 'most-cliches':
				$all_data = ( new Build_Cliche_Leaders() )->generate();
				break;
```

The existing code below the switch wraps `$all_data` as `$data['characters']` and routes it through `Stats_Handler` with `source_type = 'characters'` and `bar_direction = 'vertical'` — no other change needed.

- [ ] **Step 4: Lint the changed files**

Run: `composer lint`
Expected: PASS (no errors on `class-cliche-leaders.php` or `class-stats-generator.php`). If phpcs reports fixable spacing/alignment, run `composer lint-fix` and re-run `composer lint`.

- [ ] **Step 5: (Optional) Sanity-check the data if WP-CLI reaches the DB**

If you have WP-CLI wired to the local site (e.g. inside the Local shell), confirm the array shape:

Run: `wp eval 'print_r( ( new LWTV\Statistics\Build\Cliche_Leaders() )->generate() );'`
Expected: an array keyed by integer character IDs, each with `name`, `count`, `url`; `count` values non-increasing. If WP-CLI is not available here, skip — Task 2's browser check verifies the data end-to-end.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/build/class-cliche-leaders.php plugins/lwtv-plugin/php/statistics/class-stats-generator.php
git commit -m "feat: add most-clichéd-characters data layer"
```

---

### Task 2: Template + route registration (the visible page)

Creates the view template (bar chart + ranked table) and registers the view so `/statistics/characters/most-cliches/` renders and the nav tab appears. The template and the route must land together: the template is unreachable without the route, and the route `include`s the template.

**Files:**
- Create: `plugins/lwtv-plugin/php/statistics/templates/characters/most-cliches.php`
- Modify: `plugins/lwtv-plugin/php/statistics/templates/characters.php` (`$valid_views` at line 19; view `switch` around lines 87-90)

**Interfaces:**
- Consumes: `LWTV\Statistics\Build\Cliche_Leaders::generate()` (Task 1); `lwtv_plugin()->generate_characters_statistics( 'barchart', 'most-cliches' )` (routes into Task 1's generator case).
- Produces: the rendered page + `MOST CLICHES` nav tab. No downstream consumers.

- [ ] **Step 1: Create the template**

Create `plugins/lwtv-plugin/php/statistics/templates/characters/most-cliches.php`:

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters with the most clichés.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;

// Cached in Task 1's build class, so this call is free even though the barchart
// path below fetches the same data.
$cliche_leaders = ( new Build_Cliche_Leaders() )->generate();
?>
<h3><?php esc_html_e( 'Characters With the Most Clichés', 'lwtv' ); ?></h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-12">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'barchart', 'most-cliches' ); ?>
		</div>
	</div>
</div>

<?php if ( ! empty( $cliche_leaders ) ) { ?>
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Rank', 'lwtv' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Character', 'lwtv' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Clichés', 'lwtv' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$rank = 0;
		foreach ( $cliche_leaders as $leader ) {
			++$rank;
			echo '<tr>
					<th scope="row">' . (int) $rank . '</th>
					<td><a href="' . esc_url( $leader['url'] ) . '">' . esc_html( $leader['name'] ) . '</a></td>
					<td>' . (int) $leader['count'] . '</td>
				</tr>';
		}
		?>
	</tbody>
</table>
<?php } ?>
<?php
```

Note: rank is sequential row numbering (1..N); tied characters occupy adjacent ranks. Ties affect *membership* in the list (per the spec), not rank-number sharing.

- [ ] **Step 2: Register the view slug**

In `plugins/lwtv-plugin/php/statistics/templates/characters.php`, line 19, add `'most-cliches'` right after `'cliches'` so the tabs sit next to each other:

```php
	$valid_views     = array( 'cliches', 'most-cliches', 'gender', 'sexuality', 'queer-irl', 'on-air' );
```

- [ ] **Step 3: Add the `switch` case that includes the template**

In the same file, in the `switch ( $view )` block, add this case immediately after the existing `case 'cliches':` block (which ends around line 90):

```php
		case 'most-cliches':
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __FILE__ ) . 'characters/most-cliches.php';
			break;
```

No rewrite-rule change or `flush_rewrite_rules()` is needed: `class-query-vars.php` already maps `^statistics/characters/([^/]+)/?$` to `view=$matches[1]`, which matches `most-cliches`.

- [ ] **Step 4: Lint the changed files**

Run: `composer lint`
Expected: PASS on `templates/characters/most-cliches.php` and `templates/characters.php`. Use `composer lint-fix` for any auto-fixable issues, then re-run.

- [ ] **Step 5: Verify in the browser on the local site**

Open `https://lwtv.local/statistics/characters/most-cliches/` and confirm:
- A **MOST CLICHES** tab appears in the characters stats navbar and is marked active.
- The bar chart renders with the top characters by cliché count.
- The ranked table below lists Rank / Character (each name links to its character page) / Clichés, ordered highest count first.
- The 20th and any tied characters at that count all appear (list may be slightly longer than 20).
- Spot-check: click the top character and confirm its assigned clichés roughly match the displayed count.
- Open `https://lwtv.local/statistics/characters/cliches/` and confirm the original clichés popularity page is unchanged.

If the tab or page 404s, the local site's rewrite rules may be stale — re-save Permalinks (Settings → Permalinks → Save) on the local site to flush, then retry.

- [ ] **Step 6: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/characters/most-cliches.php plugins/lwtv-plugin/php/statistics/templates/characters.php
git commit -m "feat: add most-clichéd-characters stats view and tab"
```

---

## Self-Review

**Spec coverage:**
- New tab under characters → Task 2 Steps 2-3 (`$valid_views` + `switch` case), routing note. ✓
- Both bar chart + table → Task 2 Step 1 (chart via generator, table below). ✓
- Top 20 with ties at cutoff → Task 1 `TOP_LIMIT` + threshold logic. ✓
- Table rows Rank / Character (linked) / count → Task 2 Step 1 markup. ✓
- Inverse query grouped by character → Task 1 build class. ✓
- Week-long transient cache → Task 1 `generate()`. ✓
- One shared dataset feeds chart + table → both call `Cliche_Leaders::generate()` (cached). ✓
- Existing clichés page unchanged → no edits to `cliches.php`; verified in Task 2 Step 5. ✓
- i18n `lwtv` text domain → all strings in Task 2 Step 1. ✓

**Placeholder scan:** No TBD/TODO/"handle edge cases"; all code is complete. ✓

**Type consistency:** `Cliche_Leaders::generate()` returns `[ id => [ name, count, url ] ]` in Task 1 and is consumed with exactly those keys in Task 2. `Build_Cliche_Leaders` alias used consistently in the generator and template. ✓

**Testing note:** This repo has no PHP unit-test harness (only phpcs). Verification is therefore lint + manual browser checks, which is honest to the toolset rather than a fabricated test suite.
