# Statistics on Nations Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/statistics/nations/` on the shared stats shell — an All-Nations view (4 nation-specific counters + a ranked nation leaderboard) and a Single-nation profile with 6 server-rendered sub-views (Overview, Sexuality, Gender, Tropes, Formats, On Air) — replacing the per-view Chart.js output with reused SVG partials.

**Architecture:** Preserve the render path (`nations.php` picker + routing + `$valid_views` → `nations/all.php` or `nations/single.php`) and the existing `<select name="nation">` GET form / `?nation=` / per-view URLs. The primary tab bar is already in the page shell. Reuse `partials/{donut,ranked-bars,trendline}.php`, `.lwtv-metric-card`, `.lwtv-stats-subnav`, the donut ramp + `$ramp-*` tokens; add one new partial (`nations/leaderboard.php`) + SCSS.

**Tech Stack:** PHP 8.1+ (`LWTV\` PSR-4, `lwtv_plugin()` facade), Bootstrap 5, SCSS (Dart Sass via `@wordpress/scripts`), inline SVG, existing count-up JS (already enqueued on `is_page('statistics')`), Symbolicons sprite (+ FA fallback). No Chart.js output on these views (its enqueue stays — Stations/Death/This Year still use it). No PHPUnit — gates are PHPCS + build + browser.

## Global Constraints

- **Reuse mandate:** reuse components/tokens; NO hardcoded hex (deliberate `rgba(colors.$lwtv-pink, .14)` for the growth tile is the only sanctioned derived value); do NOT revert the user's committed color/size tweaks.
- **Family map:** Nations counter / Sexuality / profile eyebrow → **blue** (`shows`/`sexuality`); Have-10+ / Characters / Tropes → **green** (`characters`); US+UK-share / Shows-count → **yellow** (`actors`); Dead → **red** (`dead-characters`); Growth counter / leaderboard bars / on-air line → **pink** (`$lwtv-pink` + `$ramp-*`).
- **Tokens:** `$lwtv-stats-{blue,green,yellow,red}(-background/-border)`, `$lwtv-pink`/`$lwtv-dkpink`/`$lwtv-ltpink`/`$lwtv-medgrey`/`$lwtv-bordergrey`, and the in-block SCSS vars `$ramp-1..$ramp-5` (dkpink→ltpink, defined in `_stats.scss` inside `.statistics {`). Donut seg classes `dkpink/pink/mid/mid2/ltpink/grey` exist.
- **No routing/data changes:** keep query vars, `$valid_views`, the GET form, scoring; the disabled `intersections` view stays omitted. Chart.js enqueue is NOT touched.
- **PHP:** WordPress-Extra PHPCS clean. `get_symbolicon` echoes carry `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`; all other output escaped. i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts. Guard every divisor; harden single-key unwrap (`is_array && ! empty`).
- **Animation contract:** count-up `data-count-to` (+ visible final value); growable bars `data-grow-to` + `style="width:0"`; donut rings static. Reduced-motion → finals (handled by the shared JS).
- **Build:** `npm run buildquick` needs **Node 24** (`source ~/.nvm/nvm.sh; nvm use` first). Building regenerates `style.css`/`style.min.css` — include them. Never edit `blocks/build/`/`inc/dist/`.
- **Editor hazard:** stylelint fix-on-save can mangle `scss/addons/_stats.scss` (jams `//` comments, drops declarations; a KNOWN pre-existing mangled comment sits near line ~205 — not yours). After SCSS edits + build, `git diff` the SCSS and confirm ONLY intended changes.
- **Commit hygiene:** stage ONLY the files a task names (explicit paths). NEVER `git add -A` — the user commits to this branch in parallel; leave anything you didn't touch.

## Environment (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build:** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`. **CSS lint:** `npm run lint:css` (run after `nvm use`).
- **Site:** Local, `https://lwtv.local` (self-signed → `curl -sk`). Test: `https://lwtv.local/statistics/nations/` and `https://lwtv.local/statistics/nations/?nation=united-kingdom` + `.../sexuality/?nation=united-kingdom` etc.
- **wp-cli:** `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>`

## Data shapes (verified live)

Already loaded in `nations.php` and passed to the includes (in scope for all.php/single.php):
- `$all_nations_data` = `make_comprehensive('post_type_shows','lez_country', true)` → `[slug => ['count'(int),'name','url']]` (sorted by name, includes empty). **No date field.**
- `$character_counts` = `get_bulk_character_counts('lez_country', slugs)` → `[slug => ['total'(int),'dead'(int)]]`.
- `$show_counts` = `get_bulk_show_counts(...)` → `[slug => ['onair'(int),'total'(int),'score'(float),'onairscore'(float)]]`.
- `$all_shows_count` = `generate_total_counts('shows')`; `$count` = `count($all_nations_data)`.

Per-nation view data (single nation): `lwtv_plugin()->generate_nation_statistics($nation, $view, 'array')` with `$nation`=`_slug`, `$view`=`_all`/`_sexuality`/`_gender`/`_tropes`/`_formats`/`_on-air` returns the raw slice:
- `_sexuality`, `_gender` → flat list `[ ['name','count'(int),'url','slug'], … ]` (unsorted).
- `_tropes`, `_formats` → flat list `[ ['name','count'(int),'url'], … ]` (sorted count DESC; NO slug).
- `_on-air` → year-keyed `[ 2015 => ['name'=>2015,'count'(int),'url'], … ]` (ksort asc; range = nation's actual years).
- `_all` → assoc `['basic'=>[…], 'gender'=>[…], 'sexuality'=>[…], 'tropes'=>[…], 'formats'=>[…], 'on_air'=>[…]]`. (Overview will use `$character_counts`/`$show_counts` directly instead — simpler.)

## File structure

**Modified:** `plugins/lwtv-plugin/php/statistics/templates/nations.php`; `nations/all.php`; `nations/single.php`; `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php` (add one read helper); `scss/addons/_stats.scss`; `scss/partials/_colors-dark.scss`.
**New:** `plugins/lwtv-plugin/php/statistics/templates/nations/leaderboard.php`.

**Renderability invariant:** every task leaves `/statistics/nations/` and the single-nation views rendering. Single-nation views not yet redesigned keep emitting their current Chart.js output (Chart.js stays enqueued) until their task replaces them.

---

### Task 1: Shell — picker row, container, nation sub-nav

**Files:** modify `nations.php`.

Rewrite the presentational parts of `nations.php` while keeping ALL the PHP logic above the `?>` (the `$nation`/`$view`/`$valid_views` resolution, the data loads, and the `$nation`/`$view` `_`-prefixing + include dispatch at the bottom) intact. Only replace the markup between the logic blocks.

- [ ] **Step 1: Replace the title + form + nav-tabs markup**

Replace from `?>\n<h2>...` down to the end of the `<ul class="nav nav-tabs">...</ul>` and the `<p>&nbsp;</p>`, with:

```php
?>
<div class="lwtv-stats-overview">
	<div class="lwtv-nations-picker">
		<form method="get" id="go" class="lwtv-nations-pickerform">
			<label for="nation" class="lwtv-stats-eyebrow"><?php esc_html_e( 'Nation', 'lwtv' ); ?></label>
			<select name="nation" id="nation" class="form-select lwtv-nations-select">
				<option value="all"><?php esc_html_e( 'All Nations', 'lwtv' ); ?></option>
				<?php
				foreach ( $all_nations_data as $lwtv_n_slug => $lwtv_n_data ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $lwtv_n_slug ),
						selected( $nation, $lwtv_n_slug, false ),
						esc_html( $lwtv_n_data['name'] )
					);
				}
				?>
			</select>
			<button type="submit" id="submit" class="btn btn-outline-primary btn-sm"><?php esc_html_e( 'Go', 'lwtv' ); ?></button>
			<?php if ( 'all' !== $nation ) : ?>
				<a class="lwtv-nations-reset" href="/statistics/nations/"><?php esc_html_e( 'Reset to all nations', 'lwtv' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<?php
	// Nation sub-nav (single nation only). The primary tab bar is in the page shell.
	if ( 'all' !== $nation ) {
		$lwtv_sub_base  = '/statistics/nations/';
		$lwtv_sub_query = array( 'nation' => $nation );
		$lwtv_subnav    = array_merge( array( 'overview' => 'shows' ), $valid_views );
		echo '<nav class="lwtv-stats-subnav" aria-label="' . esc_attr__( 'Nation statistics views', 'lwtv' ) . '">';
		foreach ( $lwtv_subnav as $lwtv_v => $lwtv_pt ) {
			$lwtv_is  = ( $view === $lwtv_v );
			$lwtv_url = ( 'overview' === $lwtv_v ) ? add_query_arg( $lwtv_sub_query, $lwtv_sub_base ) : add_query_arg( $lwtv_sub_query, $lwtv_sub_base . $lwtv_v . '/' );
			printf(
				'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
				$lwtv_is ? ' is-active' : '',
				esc_url( $lwtv_url ),
				$lwtv_is ? ' aria-current="page"' : '',
				esc_html( ucwords( str_replace( '-', ' ', $lwtv_v ) ) )
			);
		}
		echo '</nav>';
	}
	?>
<?php
// (existing $col_class / $cpts_type + the _-prefix dispatch block follows unchanged)
```

Then, remove the now-unused `$title_nation` construction in the `switch ( $nation )` block ONLY IF trivial — safer: leave the switch as-is (it sets `$title_nation`, now unused) to minimize churn, OR delete the `<h2>` usage (done above) and the switch. **Decision:** delete the whole `switch ( $nation ) { … }` block (it only built `$title_nation`, which is no longer printed) to avoid dead code. Keep `$count`, `$shows_count`, `$all_shows_count`.

- [ ] **Step 2: Close the container**

At the very end of the file, after the existing `</div>` that closes `.container` `.row`, and before/after the WP_DEBUG comment, add the closing wrapper. Concretely, change the trailing:

```php
	</div>
</div>

<?php
// Performance monitoring
```

so the `.lwtv-stats-overview` div opened in Step 1 is closed after the `.container`:

```php
	</div>
</div>
</div><!-- .lwtv-stats-overview -->

<?php
// Performance monitoring
```

(The existing `.container`/`.row` wrapper around the include stays — the new `.lwtv-stats-overview` wraps everything.)

- [ ] **Step 3: SCSS — picker row**

In `scss/addons/_stats.scss`, inside the `.statistics {` block (near the other `.lwtv-*` rules), add:

```scss
	.lwtv-nations-picker {
		margin-bottom: 24px;
	}

	.lwtv-nations-pickerform {
		display: flex;
		align-items: center;
		gap: 12px;
		flex-wrap: wrap;

		label {
			margin: 0;
		}
	}

	.lwtv-nations-select {
		width: auto;
		min-width: 220px;
	}

	.lwtv-nations-reset {
		font-size: 0.85rem;
		color: colors.$lwtv-pink;

		&:hover {
			color: colors.$lwtv-purple;
		}
	}
```

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-nations-pickerform'                       # -> 1
curl -sk "https://lwtv.local/statistics/nations/?nation=united-kingdom" | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'  # -> Overview
curl -sk "https://lwtv.local/statistics/nations/sexuality/?nation=united-kingdom" | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'  # -> Sexuality
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-stats-subnav'                              # -> 0 (no sub-nav on all-nations)
```
Expected: picker renders; sub-nav shows only for a single nation with the correct active item; all-nations table (old all.php) still renders below.

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): nations picker row + nation sub-nav shell

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: All Nations — 4 counters + New-Since-2020 query helper

**Files:** modify `nations/all.php`, `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php`, `scss/addons/_stats.scss`.

- [ ] **Step 1: Add a bulk earliest-year read helper**

In `class-taxonomy-optimized.php`, add a new PUBLIC method (purely additive — no existing caller touched), modeled on `get_bulk_show_counts`'s join pattern. It returns each nation's earliest show start year from `lezshows_airdates_start` meta:

```php
	/**
	 * Bulk earliest show start-year per term (one grouped query).
	 *
	 * @param string $taxonomy Taxonomy slug (e.g. 'lez_country').
	 * @param array  $slugs    Term slugs to include.
	 * @return array           [ slug => (int) earliest year | 0 ].
	 */
	public function get_bulk_first_years( $taxonomy, $slugs ) {
		global $wpdb;

		$first_years = array();
		foreach ( $slugs as $slug ) {
			$first_years[ ltrim( $slug, '_' ) ] = 0;
		}
		if ( empty( $slugs ) ) {
			return $first_years;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug AS slug, MIN( CAST( pm.meta_value AS UNSIGNED ) ) AS first_year
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post_type_shows' AND p.post_status = 'publish'
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'lezshows_airdates_start'
				 WHERE pm.meta_value != ''
				 GROUP BY t.slug",
				$taxonomy
			),
			ARRAY_A
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				$first_years[ $row['slug'] ] = (int) $row['first_year'];
			}
		}

		return $first_years;
	}
```

(Note: reads the primary `lezshows_airdates_start` meta. A handful of legacy-only shows storing dates in the serialized `lezshows_airdates` are not counted — acceptable for this derived counter; flagged as owner-tunable.)

- [ ] **Step 2: Rewrite `nations/all.php`**

Replace the whole file body (below the docblock) with the counters + a leaderboard include (the leaderboard partial is built in Task 3; reference it here):

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * All-nations statistics: counters + ranked leaderboard.
 *
 * @package LezWatch.TV
 *
 * @var array $all_nations_data
 * @var array $character_counts
 * @var array $show_counts
 * @var int   $all_shows_count
 * @var int   $count
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Nations with at least one show, ranked by show count (desc).
$lwtv_ranked = array();
foreach ( $all_nations_data as $lwtv_slug => $lwtv_data ) {
	if ( (int) $lwtv_data['count'] > 0 ) {
		$lwtv_ranked[ $lwtv_slug ] = $lwtv_data;
	}
}
uasort( $lwtv_ranked, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

$lwtv_nation_total = count( $lwtv_ranked );

// Derived counters (compute, don't store).
$lwtv_depth = 0; // nations with >= 10 shows.
foreach ( $lwtv_ranked as $lwtv_data ) {
	if ( (int) $lwtv_data['count'] >= 10 ) {
		++$lwtv_depth;
	}
}

// US + UK share = the top two nations' combined share of all shows.
$lwtv_top_counts = array_slice( array_map( fn( $d ) => (int) $d['count'], array_values( $lwtv_ranked ) ), 0, 2 );
$lwtv_topshare   = ( $all_shows_count > 0 ) ? round( ( array_sum( $lwtv_top_counts ) / $all_shows_count ) * 100 ) : 0;

// New since 2020 = nations whose earliest show started 2020 or later.
$lwtv_first_years = ( new Build_Taxonomy_Optimized() )->get_bulk_first_years( 'lez_country', array_keys( $lwtv_ranked ) );
$lwtv_new_2020    = 0;
foreach ( $lwtv_ranked as $lwtv_slug => $lwtv_data ) {
	$lwtv_fy = $lwtv_first_years[ ltrim( $lwtv_slug, '_' ) ] ?? 0;
	if ( $lwtv_fy >= 2020 ) {
		++$lwtv_new_2020;
	}
}

$lwtv_cards = array(
	array(
		'family'  => 'shows',
		'label'   => __( 'Nations', 'lwtv' ),
		'count'   => $lwtv_nation_total,
		'suffix'  => '',
		'caption' => __( 'With at least one queer show', 'lwtv' ),
		'svg'     => 'globe.svg',
		'icon'    => 'svg-globe',
	),
	array(
		'family'  => 'characters',
		'label'   => __( 'Have 10+ Shows', 'lwtv' ),
		'count'   => $lwtv_depth,
		'suffix'  => '',
		'caption' => __( 'A real depth of catalogue', 'lwtv' ),
		'svg'     => 'layer-group.svg',
		'icon'    => 'svg-layer-group',
	),
	array(
		'family'  => 'actors',
		'label'   => __( 'US + UK Share', 'lwtv' ),
		'count'   => $lwtv_topshare,
		'suffix'  => '%',
		'caption' => __( 'Two countries, most of the shows', 'lwtv' ),
		'svg'     => 'target.svg',
		'icon'    => 'svg-bullseye',
	),
	array(
		'family'  => 'nations-new',
		'label'   => __( 'New Since 2020', 'lwtv' ),
		'count'   => $lwtv_new_2020,
		'suffix'  => '',
		'caption' => __( 'Debuted their first queer show', 'lwtv' ),
		'svg'     => 'graph-line.svg',
		'icon'    => 'svg-arrow-trend-up',
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Around the World', 'lwtv' ); ?></p>
<div class="lwtv-metric-grid lwtv-metric-grid--4">
	<?php
	foreach ( $lwtv_cards as $lwtv_card ) {
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $lwtv_card['family'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $lwtv_card['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_card['svg'], icon: $lwtv_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $lwtv_card['count']; ?>" data-count-suffix="<?php echo esc_attr( $lwtv_card['suffix'] ); ?>"><?php echo esc_html( number_format_i18n( $lwtv_card['count'] ) . $lwtv_card['suffix'] ); ?></span>
			<span class="lwtv-metric-caption"><?php echo esc_html( $lwtv_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
// Ranked nation leaderboard (Task 3 partial).
$leaderboard_rows  = $lwtv_ranked;
$leaderboard_chars = $character_counts;
$leaderboard_all   = (int) $all_shows_count;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'nations/leaderboard.php';
```

- [ ] **Step 3: SCSS — pink metric family + 4-up grid check**

In `scss/addons/_stats.scss`: (a) confirm `.lwtv-metric-grid--4` exists (grep); if not, add it beside `--3`. (b) Add the pink `nations-new` metric family — in the `.card-header.*` family group (top of the `.statistics, .thisyear {` block) add a new selector list entry:

```scss
	.card-header.nations-new {
		color: colors.$lwtv-pink;
		background-color: rgba(colors.$lwtv-pink, 0.14);
		border-color: rgba(colors.$lwtv-pink, 0.28);
	}
```

and in the `.lwtv-metric-icon.*` group add:

```scss
	.lwtv-metric-icon.nations-new {
		color: colors.$lwtv-pink;
		background-color: rgba(colors.$lwtv-pink, 0.14);
	}
```

(`data-count-suffix` is already supported by the shared count-up JS from the actor-modals round.)

- [ ] **Step 4: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-metric-card'      # -> 4
curl -sk https://lwtv.local/statistics/nations/ | grep -oE 'card-header (shows|characters|actors|nations-new)' | sort -u
curl -sk https://lwtv.local/statistics/nations/ | grep -o 'lwtv-metric-number[^>]*data-count-to="[0-9]*"[^>]*>[^<]*' | head
```
Expected: 4 cards (blue/green/yellow/pink), counters = Nations count, ≥10 count, US+UK %, New-since-2020 count. Cross-check the numbers are plausible (e.g. Nations ≈ 60+, US+UK ≈ 70-80%). No fatal. (The leaderboard include will 404 until Task 3 creates the partial — if PHP `include` warns, proceed; Task 3 lands immediately after. To keep this task's page clean, you MAY create an empty-safe `nations/leaderboard.php` stub in this commit and flesh it out in Task 3 — but the plan lands Task 3 next, so a transient include warning is acceptable; note it in the report.)

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/all.php plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): all-nations metric cards + earliest-year helper

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: All Nations — ranked leaderboard partial

**Files:** create `nations/leaderboard.php`; modify `scss/addons/_stats.scss`.

- [ ] **Step 1: Create `nations/leaderboard.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ranked nation leaderboard: rank · nation · share bar (ramp by rank) ·
 * shows·pct · characters · dead.
 *
 * @package LezWatch.TV
 *
 * @var array $leaderboard_rows  Ranked [ slug => ['name','count'] ], desc by count.
 * @var array $leaderboard_chars [ slug => ['total','dead'] ].
 * @var int   $leaderboard_all   Total shows (for share %).
 */

$lb_rows  = is_array( $leaderboard_rows ) ? $leaderboard_rows : array();
$lb_top   = ! empty( $lb_rows ) ? (int) reset( $lb_rows )['count'] : 0;
$lb_total = count( $lb_rows );
$lb_shown = array_slice( $lb_rows, 0, 10, true );
$lb_rank  = 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon sexuality">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'globe.svg', icon: 'svg-globe', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Nations by number of shows', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				/* translators: %s: number of nations shown / total. */
				printf( esc_html__( 'Top 10 of %s nations with shows.', 'lwtv' ), esc_html( number_format_i18n( $lb_total ) ) );
				?>
			</p>
		</div>
	</header>
	<div class="lwtv-nations-lb">
		<div class="lwtv-nations-lb-head">
			<span></span>
			<span><?php esc_html_e( 'Nation', 'lwtv' ); ?></span>
			<span><?php esc_html_e( 'Share of all shows', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Shows', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Chars', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Dead', 'lwtv' ); ?></span>
		</div>
		<?php
		foreach ( $lb_shown as $lb_slug => $lb_data ) {
			++$lb_rank;
			$lb_clean = ltrim( $lb_slug, '_' );
			$lb_shows = (int) $lb_data['count'];
			$lb_chars = (int) ( $leaderboard_chars[ $lb_clean ]['total'] ?? 0 );
			$lb_dead  = (int) ( $leaderboard_chars[ $lb_clean ]['dead'] ?? 0 );
			$lb_pct   = ( $leaderboard_all > 0 ) ? round( ( $lb_shows / $leaderboard_all ) * 100, 1 ) : 0;
			$lb_width = ( $lb_top > 0 ) ? round( ( $lb_shows / $lb_top ) * 100, 1 ) : 0;
			$lb_ramp  = min( $lb_rank, 5 );
			?>
			<div class="lwtv-nations-lb-row">
				<span class="lwtv-nations-lb-rank"><?php echo esc_html( number_format_i18n( $lb_rank ) ); ?></span>
				<a class="lwtv-nations-lb-name" href="<?php echo esc_url( add_query_arg( 'nation', $lb_slug, '/statistics/nations/' ) ); ?>"><?php echo esc_html( $lb_data['name'] ); ?></a>
				<span class="lwtv-nations-lb-track">
					<span class="lwtv-nations-lb-bar lwtv-nations-lb-bar--<?php echo (int) $lb_ramp; ?>" style="width:0" data-grow-to="<?php echo esc_attr( (string) $lb_width ); ?>"></span>
				</span>
				<span class="lwtv-nations-lb-num"><?php echo esc_html( number_format_i18n( $lb_shows ) . ' · ' . $lb_pct . '%' ); ?></span>
				<span class="lwtv-nations-lb-num"><?php echo esc_html( number_format_i18n( $lb_chars ) ); ?></span>
				<span class="lwtv-nations-lb-num lwtv-nations-lb-dead"><?php echo esc_html( number_format_i18n( $lb_dead ) ); ?></span>
			</div>
			<?php
		}
		?>
	</div>
</section>
```

- [ ] **Step 2: Leaderboard SCSS**

In `scss/addons/_stats.scss`, inside the `.statistics {` block (where `$ramp-1..$ramp-5` are in scope), add:

```scss
	.lwtv-nations-lb {
		margin-top: 4px;
	}

	.lwtv-nations-lb-head,
	.lwtv-nations-lb-row {
		display: grid;
		grid-template-columns: 18px 148px 1fr 104px 66px 60px;
		gap: 12px;
		align-items: center;
		padding: 10px 0;
	}

	.lwtv-nations-lb-row {
		border-top: 1px solid colors.$lwtv-bordergrey;
	}

	.lwtv-nations-lb-head {
		font-size: 0.7rem;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: colors.$lwtv-medgrey;
	}

	.lwtv-nations-lb-num {
		text-align: right;
		font-size: 0.8rem;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
	}

	.lwtv-nations-lb-rank {
		font-size: 0.75rem;
		font-weight: 700;
		color: colors.$lwtv-medgrey;
		font-variant-numeric: tabular-nums;
	}

	.lwtv-nations-lb-name {
		font-weight: 600;
		color: colors.$lwtv-pink;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;

		&:hover {
			color: colors.$lwtv-purple;
		}
	}

	.lwtv-nations-lb-track {
		height: 8px;
		background-color: colors.$lwtv-ltgrey;
		border-radius: 999px;
		overflow: hidden;
	}

	.lwtv-nations-lb-bar {
		display: block;
		height: 100%;
		border-radius: 999px;
		transition: none;
	}

	.lwtv-nations-lb-bar--1 { background-color: $ramp-1; }
	.lwtv-nations-lb-bar--2 { background-color: $ramp-2; }
	.lwtv-nations-lb-bar--3 { background-color: $ramp-3; }
	.lwtv-nations-lb-bar--4 { background-color: $ramp-4; }
	.lwtv-nations-lb-bar--5 { background-color: $ramp-5; }

	.lwtv-nations-lb-dead {
		color: colors.$lwtv-stats-red;
	}
```

- [ ] **Step 3: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-nations-lb-row'    # -> 10
curl -sk https://lwtv.local/statistics/nations/ | grep -oE 'lwtv-nations-lb-bar--[1-5]' | sort | uniq -c
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-nations-lb-dead'   # -> 10
```
Expected: 10 leaderboard rows, ramp classes 1–5 present, dead column red. Browser-check the ramp reads darkest→lightest top→bottom, bars grow, row links drill in.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/leaderboard.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): nations ranked leaderboard partial

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Single nation — profile bar + Overview view

**Files:** modify `nations/single.php`, `scss/addons/_stats.scss`.

Rewrite `single.php` into: profile bar (always) + a `switch ( $view )` over the 6 views. In THIS task, implement the profile bar and the `_all` (Overview) case fully; the other 5 cases keep emitting the CURRENT output (the existing `generate_nation_statistics(..., 'piechart'|'percentage'|'trendline')` echoes) so they still render (Chart.js stays enqueued) until Tasks 5–6 replace them.

- [ ] **Step 1: Rewrite `single.php` head + profile bar + Overview**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Single-nation statistics: profile bar + one view.
 *
 * @package LezWatch.TV
 *
 * @var array  $all_nations_data
 * @var array  $character_counts
 * @var array  $show_counts
 * @var string $nation  Nation slug, '_'-prefixed.
 * @var string $view    View, '_'-prefixed.
 */

$lwtv_slug   = ltrim( $nation, '_' );
$lwtv_vslug  = ltrim( $view, '_' );
$lwtv_ndata  = $all_nations_data[ $lwtv_slug ] ?? array( 'name' => __( 'Nation', 'lwtv' ), 'count' => 0 );
$lwtv_name   = $lwtv_ndata['name'];
$lwtv_shows  = (int) ( $show_counts[ $lwtv_slug ]['total'] ?? $lwtv_ndata['count'] ?? 0 );
$lwtv_onair  = (int) ( $show_counts[ $lwtv_slug ]['onair'] ?? 0 );
$lwtv_score  = (float) ( $show_counts[ $lwtv_slug ]['score'] ?? 0 );
$lwtv_oascore = (float) ( $show_counts[ $lwtv_slug ]['onairscore'] ?? 0 );
$lwtv_chars  = (int) ( $character_counts[ $lwtv_slug ]['total'] ?? 0 );
$lwtv_dead   = (int) ( $character_counts[ $lwtv_slug ]['dead'] ?? 0 );
?>
<div class="lwtv-nation-profile bg-light">
	<div class="lwtv-nation-profile-id">
		<span class="lwtv-stats-eyebrow sexuality"><?php esc_html_e( 'Nation Profile', 'lwtv' ); ?></span>
		<h2 class="lwtv-nation-profile-name"><?php echo esc_html( $lwtv_name ); ?></h2>
	</div>
	<div class="lwtv-nation-profile-figs">
		<span><strong data-count-to="<?php echo (int) $lwtv_shows; ?>"><?php echo esc_html( number_format_i18n( $lwtv_shows ) ); ?></strong><em><?php esc_html_e( 'shows', 'lwtv' ); ?></em></span>
		<span><strong data-count-to="<?php echo (int) $lwtv_chars; ?>"><?php echo esc_html( number_format_i18n( $lwtv_chars ) ); ?></strong><em><?php esc_html_e( 'characters', 'lwtv' ); ?></em></span>
		<span class="lwtv-nation-profile-dead"><strong data-count-to="<?php echo (int) $lwtv_dead; ?>"><?php echo esc_html( number_format_i18n( $lwtv_dead ) ); ?></strong><em><?php esc_html_e( 'dead', 'lwtv' ); ?></em></span>
	</div>
</div>

<?php
switch ( $view ) {
	case '_all':
		$lwtv_ov_cards = array(
			array( 'family' => 'shows', 'label' => __( 'Shows', 'lwtv' ), 'count' => $lwtv_shows, 'svg' => 'tv.svg', 'icon' => 'svg-tv' ),
			array( 'family' => 'sexuality', 'label' => __( 'On Air Now', 'lwtv' ), 'count' => $lwtv_onair, 'svg' => 'tv.svg', 'icon' => 'svg-tv' ),
			array( 'family' => 'characters', 'label' => __( 'Characters', 'lwtv' ), 'count' => $lwtv_chars, 'svg' => 'group.svg', 'icon' => 'svg-users' ),
			array( 'family' => 'dead-characters', 'label' => __( 'Dead', 'lwtv' ), 'count' => $lwtv_dead, 'svg' => 'skull.svg', 'icon' => 'svg-skull' ),
		);
		?>
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section">
			<?php
			/* translators: %s: nation name. */
			printf( esc_html__( '%s at a Glance', 'lwtv' ), esc_html( $lwtv_name ) );
			?>
		</p>
		<div class="lwtv-metric-grid lwtv-metric-grid--4">
			<?php
			foreach ( $lwtv_ov_cards as $lwtv_c ) {
				?>
				<div class="lwtv-metric-card card-header <?php echo esc_attr( $lwtv_c['family'] ); ?>">
					<div class="lwtv-metric-top">
						<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_c['label'] ); ?></span>
						<span class="lwtv-metric-icon <?php echo esc_attr( $lwtv_c['family'] ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_c['svg'], icon: $lwtv_c['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
					<span class="lwtv-metric-number" data-count-to="<?php echo (int) $lwtv_c['count']; ?>"><?php echo esc_html( number_format_i18n( $lwtv_c['count'] ) ); ?></span>
				</div>
				<?php
			}
			?>
		</div>
		<p class="lwtv-nation-score">
			<?php
			/* translators: 1: average score, 2: on-air average score. */
			printf( esc_html__( 'Average score: %1$s / 100 (on-air %2$s / 100).', 'lwtv' ), esc_html( number_format_i18n( round( $lwtv_score ) ) ), esc_html( number_format_i18n( round( $lwtv_oascore ) ) ) );
			?>
		</p>
		<p class="lwtv-nation-sentence">
			<?php
			/* translators: 1: on-air count, 2: nation name, 3: total shows. */
			printf( esc_html__( '%1$s of %2$s\'s %3$s shows are currently on air. Use the tabs above to break its catalogue down by sexuality, gender, tropes, formats, and shows-on-air over time.', 'lwtv' ), esc_html( number_format_i18n( $lwtv_onair ) ), esc_html( $lwtv_name ), esc_html( number_format_i18n( $lwtv_shows ) ) );
			?>
		</p>
		<?php
		break;

	case '_on-air':
		echo wp_kses_post( '<h4>Shows On-Air Per Year</h4>' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the function. (Replaced in Task 6.)
		echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'trendline' );
		break;

	default:
		// Sexuality / Gender / Tropes / Formats — current output until Tasks 5–6.
		?>
		<div class="row">
			<div class="col-sm-6"><?php echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'piechart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="col-sm-6"><?php echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'percentage' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
		break;
}
```

- [ ] **Step 2: SCSS — profile bar + score/sentence**

In `scss/addons/_stats.scss` inside `.statistics {`:

```scss
	.lwtv-nation-profile {
		display: flex;
		align-items: center;
		justify-content: space-between;
		flex-wrap: wrap;
		gap: 16px;
		padding: 20px 24px;
		border: 1px solid colors.$lwtv-bordergrey;
		border-radius: 14px;
		margin-bottom: 20px;
	}

	.lwtv-nation-profile-name {
		margin: 2px 0 0;
		font-size: 1.375rem;
		font-weight: 700;
	}

	.lwtv-nation-profile-figs {
		display: flex;
		gap: 28px;

		span {
			display: flex;
			flex-direction: column;
			align-items: flex-end;
		}

		strong {
			font-size: 1.5rem;
			font-weight: 700;
			line-height: 1;
			font-variant-numeric: tabular-nums;
		}

		em {
			font-size: 0.7rem;
			font-style: normal;
			color: colors.$lwtv-medgrey;
			text-transform: lowercase;
		}
	}

	.lwtv-nation-profile-dead strong {
		color: colors.$lwtv-stats-red;
	}

	.lwtv-nation-score,
	.lwtv-nation-sentence {
		font-size: 0.9rem;
		color: colors.$lwtv-medgrey;
	}
```

`.lwtv-stats-eyebrow.sexuality` should already be blue via the family rules; if the eyebrow doesn't pick up blue, add `.lwtv-stats-eyebrow.sexuality { color: colors.$lwtv-stats-blue; }` in the same block.

- [ ] **Step 3: Lint + build + verify**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
U="https://lwtv.local/statistics/nations/?nation=united-kingdom"
curl -sk "$U" | grep -c 'lwtv-nation-profile\b'        # -> 1
curl -sk "$U" | grep -c 'lwtv-metric-card'             # -> 4 (overview counters)
curl -sk "$U" | grep -o 'lwtv-nation-profile-name[^>]*>[^<]*'
curl -sk "https://lwtv.local/statistics/nations/sexuality/?nation=united-kingdom" | grep -c 'lwtv-nation-profile\b'  # -> 1 (profile shows on every view)
```
Expected: profile bar (name + shows/chars/dead-red) on every single-nation view; Overview shows 4 counters + score line + sentence; other views still render (old charts) beneath the profile.

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/single.php scss/addons/_stats.scss style.css style.min.css
git commit -m "feat(stats): single-nation profile bar + Overview

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Single nation — Sexuality, Gender, Formats donuts

**Files:** modify `nations/single.php`.

Replace the `default:` case (which currently renders sexuality/gender/tropes/formats as old charts) with per-view handling: `_sexuality`, `_gender`, `_formats` → donuts; `_tropes` stays on the old output (Task 6 replaces it). Add a small shared helper inside `single.php` to build ramp donut segments.

- [ ] **Step 1: Add donut cases**

Above the `switch`, add a segment-builder closure:

```php
/**
 * Build donut segments from a [name,count,...] list: top N ramp + grey remainder.
 *
 * @param array  $list       Items with 'name' + 'count'.
 * @param int    $topn       Number of ramped segments before folding into Other.
 * @param string $grey_match Optional lowercase name to force into the grey slot first (e.g. 'cisgender').
 * @return array [ segments, total ]
 */
$lwtv_build_segments = function ( $list, $topn, $grey_match = '' ) {
	$list = is_array( $list ) ? $list : array();
	$total = 0;
	foreach ( $list as $it ) {
		$total += (int) $it['count'];
	}
	$ramp = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );
	$segments = array();
	$grey_val = 0;

	// Pull the grey-matched item (cisgender) out first, if present.
	if ( '' !== $grey_match ) {
		foreach ( $list as $k => $it ) {
			if ( strtolower( $it['name'] ) === $grey_match ) {
				$grey_val = (int) $it['count'];
				unset( $list[ $k ] );
				break;
			}
		}
	}

	uasort( $list, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

	if ( '' !== $grey_match ) {
		$segments[] = array(
			'label' => ucfirst( $grey_match ),
			'count' => $grey_val,
			'pct'   => ( $total > 0 ) ? round( ( $grey_val / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
	}

	$i = 0;
	$named = $grey_val;
	foreach ( $list as $it ) {
		if ( $i >= $topn || (int) $it['count'] <= 0 ) {
			break;
		}
		$c = (int) $it['count'];
		$named += $c;
		$segments[] = array(
			'label' => $it['name'],
			'count' => $c,
			'pct'   => ( $total > 0 ) ? round( ( $c / $total ) * 100, 1 ) : 0,
			'class' => $ramp[ $i ],
		);
		++$i;
	}
	$other = max( 0, $total - $named );
	if ( $other > 0 ) {
		$segments[] = array(
			'label' => __( 'Other', 'lwtv' ),
			'count' => $other,
			'pct'   => ( $total > 0 ) ? round( ( $other / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
	}
	return array( $segments, $total );
};
```

Then in the `switch`, add these cases (before `default:`):

```php
	case '_sexuality':
	case '_gender':
	case '_formats':
		$lwtv_raw  = lwtv_plugin()->generate_nation_statistics( $nation, $view, 'array' );
		$lwtv_list = ( is_array( $lwtv_raw ) && ! empty( $lwtv_raw ) ) ? $lwtv_raw : array();

		if ( '_gender' === $view ) {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 4, 'cisgender' );
			$lwtv_eyebrow  = sprintf( /* translators: %s nation */ __( 'Character Gender — %s', 'lwtv' ), $lwtv_name );
			$lwtv_headline = __( 'Gender identities', 'lwtv' );
			$lwtv_sub      = __( 'characters', 'lwtv' );
		} elseif ( '_formats' === $view ) {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 5 );
			$lwtv_eyebrow  = sprintf( /* translators: %s nation */ __( 'Show Formats — %s', 'lwtv' ), $lwtv_name );
			$lwtv_headline = __( 'How these shows are made', 'lwtv' );
			$lwtv_sub      = __( 'shows', 'lwtv' );
		} else {
			list( $lwtv_segs, $lwtv_tot ) = $lwtv_build_segments( $lwtv_list, 5 );
			$lwtv_eyebrow  = sprintf( /* translators: %s nation */ __( 'Character Sexual Orientation — %s', 'lwtv' ), $lwtv_name );
			$lwtv_headline = __( 'Sexual orientations', 'lwtv' );
			$lwtv_sub      = __( 'characters', 'lwtv' );
		}

		$donut = array(
			'segments'    => $lwtv_segs,
			'center'      => $lwtv_tot,
			'center_sub'  => $lwtv_sub,
			'eyebrow'     => $lwtv_eyebrow,
			'headline'    => $lwtv_headline,
			'description' => '',
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
		break;
```

Leave the `default:` case for `_tropes` only (still old output until Task 6).

- [ ] **Step 2: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
for v in sexuality gender formats; do
  echo "== $v =="
  curl -sk "https://lwtv.local/statistics/nations/$v/?nation=united-kingdom" | grep -oE 'lwtv-donut-seg--[a-z0-9]+' | sort | uniq -c
  curl -sk "https://lwtv.local/statistics/nations/$v/?nation=united-kingdom" | grep -o 'lwtv-donut-center-num[^>]*>[^<]*'
done
```
Expected: each renders a donut (sexuality/formats = ramp + grey Other; gender = grey Cisgender + ramp + Other); centre numbers present; segment shares ~100%. No Chart.js `<canvas>` in these three views.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/single.php style.css style.min.css
git commit -m "feat(stats): single-nation sexuality/gender/formats donuts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

(No SCSS this task — donut styles already apply on `.statistics`.)

---

### Task 6: Single nation — Tropes ranked bars + On-Air trendline

**Files:** modify `nations/single.php`.

- [ ] **Step 1: Tropes case (ranked bars)**

Add a `case '_tropes':` (replacing its use of the `default:` old output). Remove `_tropes` from `default:` by giving it its own case:

```php
	case '_tropes':
		$lwtv_traw  = lwtv_plugin()->generate_nation_statistics( $nation, $view, 'array' );
		$lwtv_trows = ( is_array( $lwtv_traw ) && ! empty( $lwtv_traw ) ) ? $lwtv_traw : array();
		$ranked = array(
			'rows'   => $lwtv_trows,               // [ ['name','count','url'], … ]
			'total'  => $lwtv_shows,               // share vs nation show count.
			'family' => 'characters',
			'title'  => sprintf( /* translators: %s nation */ __( 'Most common tropes in %s', 'lwtv' ), $lwtv_name ),
			'sub'    => __( 'Shows can carry several, so shares add past 100%.', 'lwtv' ),
			'svg'    => 'tag.svg',
			'icon'   => 'svg-tag',
			'base'   => '',                        // rows carry their own 'url'.
			'mode'   => 'share',
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
		break;
```

NOTE: `ranked-bars.php` reads `$ranked_row['count']` and, with `base=''`, `$ranked_row['url']`. The tropes rows have `name`/`count`/`url` (no slug) — compatible. Confirm `ranked-bars.php` doesn't require a `slug` key when `base` is empty (it uses `$ranked_row['url'] ?? '#'`).

- [ ] **Step 2: On-Air case (trendline)**

Replace the `_on-air` case body:

```php
	case '_on-air':
		$lwtv_oaraw = lwtv_plugin()->generate_nation_statistics( $nation, $view, 'array' );
		$lwtv_oaraw = ( is_array( $lwtv_oaraw ) && ! empty( $lwtv_oaraw ) ) ? $lwtv_oaraw : array();
		$lwtv_points = array();
		foreach ( $lwtv_oaraw as $lwtv_year => $lwtv_item ) {
			$lwtv_points[] = array( 'year' => (int) $lwtv_item['name'], 'count' => (int) $lwtv_item['count'] );
		}
		$lwtv_last = ! empty( $lwtv_points ) ? end( $lwtv_points ) : array( 'year' => 0, 'count' => 0 );
		$trend = array(
			'points'       => $lwtv_points,
			'eyebrow'      => sprintf( /* translators: %s nation */ __( 'Shows On Air Per Year — %s', 'lwtv' ), $lwtv_name ),
			'headline'     => __( 'On-air over time', 'lwtv' ),
			'description'  => sprintf( /* translators: %s nation */ __( 'Shows from %s active in each year, from the first tracked title to today.', 'lwtv' ), $lwtv_name ),
			'current'      => (int) $lwtv_last['count'],
			'current_year' => (int) $lwtv_last['year'],
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/trendline.php';
		break;
```

After Tasks 5–6, the `default:` case should be unreachable for any valid view; leave a minimal `default:` that renders nothing (or the profile only). Confirm all 6 `$valid_views`+overview map to a real case.

- [ ] **Step 3: Lint + build + verify**

```bash
composer lint-fix && composer lint
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
curl -sk "https://lwtv.local/statistics/nations/tropes/?nation=united-kingdom" | grep -c 'lwtv-leader-row'   # -> >0 (green ranked bars)
curl -sk "https://lwtv.local/statistics/nations/on-air/?nation=united-kingdom" | grep -c 'lwtv-trend'        # -> >0 (trendline)
# No Chart.js canvas in any single-nation view now:
for v in "" sexuality gender tropes formats on-air; do
  echo -n "$v canvas: "; curl -sk "https://lwtv.local/statistics/nations/$v/?nation=united-kingdom" | grep -c '<canvas'
done
```
Expected: tropes = green ranked bars; on-air = trendline; every single-nation view `<canvas>` count = 0 (all server-rendered now).

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/nations/single.php style.css style.min.css
git commit -m "feat(stats): single-nation tropes bars + on-air trendline

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Full verification + polish

**Files:** none expected (fixes only if verification finds issues).

- [ ] **Step 1: Lint + build clean**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick
```

- [ ] **Step 2: Render + regression sweep**

```bash
for u in \
  "statistics/nations/" \
  "statistics/nations/?nation=united-kingdom" \
  "statistics/nations/sexuality/?nation=united-kingdom" \
  "statistics/nations/gender/?nation=united-kingdom" \
  "statistics/nations/tropes/?nation=united-kingdom" \
  "statistics/nations/formats/?nation=united-kingdom" \
  "statistics/nations/on-air/?nation=united-kingdom" \
  "statistics/" "statistics/shows/" "statistics/actors/" "statistics/stations/" "statistics/death/"; do
  code=$(curl -sk -o /tmp/n.html -w "%{http_code}" "https://lwtv.local/$u")
  err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/n.html)
  echo "$u -> HTTP $code, php-errors=$err"
done
# Chart.js still enqueued for the not-yet-migrated sections:
echo "stations canvas/chartjs still present:"; curl -sk https://lwtv.local/statistics/stations/ | grep -c 'chart.min.js'
```
Expected: every URL HTTP 200, php-errors=0; Stations/Death still load Chart.js (unchanged).

- [ ] **Step 3: Browser QA** on `https://lwtv.local/statistics/nations/` + a single nation, against `design_handoff_statistics_nations/screenshots/` (01–04 + dark):
  - All Nations: picker, 4 counters (blue/green/yellow/pink) count up, leaderboard (ramp darkest→lightest, dead red, bars grow, row links drill in).
  - Single nation: profile bar (name + shows/chars/dead-red); sub-nav active states; Overview (4 counters + score + sentence); Sexuality/Gender/Formats donuts; Tropes green bars; On-Air trendline.
  - Light + dark; picker + Reset switch nations; primary tab bar shows Nations active; reduced-motion; narrow layout.
  - Regression: `/statistics/shows|characters|actors/` unchanged; a Chart.js section (`/statistics/stations/`) still renders.

- [ ] **Step 4: Commit** (only if Step 3 required fixes)

```bash
git add <changed paths>
git commit -m "fix(stats): <what> from nations verification

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:** picker + sub-nav shell → T1; 4 all-nations counters (+ New-Since-2020 query) → T2; ranked leaderboard → T3; profile bar + Overview → T4; Sexuality/Gender/Formats donuts → T5; Tropes bars + On-Air trendline → T6; verification → T7. Reuse mandate, family map, tokens, i18n/escaping, divisor guards, animation contract, dark mode, no routing/Chart.js-enqueue changes — enforced per task + Global Constraints. New SCSS: picker/reset (T1), pink `nations-new` metric family (T2), leaderboard grid+ramp (T3), profile bar/score (T4). New PHP: `nations/leaderboard.php` (T3), `get_bulk_first_years` (T2). ✓

**Placeholder scan:** no TBD/TODO. Deferred with concrete verify steps: sprite ids for layers/target/trending-up (FA fallbacks `svg-layer-group`/`svg-bullseye`/`svg-arrow-trend-up` — degrade gracefully); `.lwtv-metric-grid--4` existence (grep, add if missing); `ranked-bars.php` slug-optional with `base=''`. Known caveat: `get_bulk_first_years` reads only `lezshows_airdates_start` (legacy-serialized-only shows uncounted) — owner-tunable, non-blocking. ✓

**Type consistency:** `$donut` contract (segments[label,count,pct,class], center, center_sub, eyebrow, headline, description) matches `donut.php`; `$ranked` (rows[name,count,url], total, family, title, sub, svg, icon, base, mode) matches `ranked-bars.php`; `$trend` (points[year,count], eyebrow, headline, description, current, current_year) matches `trendline.php`. `$all_nations_data`/`$character_counts`/`$show_counts` shapes match the verified data and the nations.php scope passed into the includes. `data-count-to`/`data-count-suffix`/`data-grow-to` match the shared JS. Segment classes (dkpink/pink/mid/mid2/ltpink/grey) and `$ramp-1..5` exist. ✓

## Known follow-ups (out of scope)
- Counter/headline/sentence copy is derived/handoff-illustrative — owner may tune.
- `get_bulk_first_years` uses the primary `lezshows_airdates_start` meta only; if the "New Since 2020" figure looks low, extend to the legacy serialized `lezshows_airdates` form.
- After Nations, only Stations, Death, and This Year still use Chart.js.
