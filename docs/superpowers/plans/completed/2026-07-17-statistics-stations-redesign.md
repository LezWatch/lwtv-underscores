# Statistics on Stations Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the Nations redesign to `/statistics/stations/` (re-pointed at `lez_stations`), generalizing the leaderboard into a shared partial so both sections use one component.

**Architecture:** Preserve the render path (`stations.php` picker + routing → `stations/all.php` or `stations/single.php`) and the existing `<select name="station">` GET form / `?station=` / per-view URLs / `$valid_views`. Reuse every Nations component; the only new file is a generalized `partials/leaderboard.php`.

**Tech Stack:** PHP 8.1+ (`LWTV\` PSR-4, `lwtv_plugin()`), Bootstrap 5, SCSS, inline SVG, existing count-up JS, Symbolicons sprite. No PHPUnit — gates are PHPCS + build + browser.

## Global Constraints

- **Reuse mandate:** reuse Nations components/tokens; NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Reuse the `.lwtv-nation-*` / `.lwtv-nations-lb*` class NAMES as-is on Stations (they are not nation-specific in meaning) — do NOT add `.lwtv-stations-*` duplicates, so **no SCSS changes are expected**.
- **Family map:** Stations→blue (`shows`), depth/tropes→green (`characters`), biggest-platform/shows→yellow (`actors`), dead→red (`dead-characters`), growth/ramp/on-air→pink (`nations-new` / `$lwtv-pink` / `$ramp-*`).
- **`ltrim($view,'_')` for the `'array'` path:** `generate_station_statistics($station, ltrim($view,'_'), 'array')` — the `_`-prefixed view returns empty otherwise (same gotcha as Nations).
- **No routing/data/scoring changes;** `intersections` stays omitted; Chart.js enqueue untouched (Death + This Year still use it).
- **PHP:** WordPress-Extra PHPCS clean. `get_symbolicon` echoes carry `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`; all other output escaped. i18n `'lwtv'`; `number_format_i18n()`; `_n()` for counts. Guard divisors.
- **Build:** `npm run buildquick` needs Node 24 (`source ~/.nvm/nvm.sh; nvm use`). Regenerates `style.css`/`style.min.css` — include IF changed (this plan expects no SCSS change, so they likely won't change; if a build touches them, include them).
- **Commit hygiene:** stage ONLY the files a task names (explicit paths). NEVER `git add -A` — the user edits this branch in parallel.
- **Editor hazard:** if a build unexpectedly modifies `scss/addons/_stats.scss`, `git checkout --` it (this plan shouldn't touch SCSS).

## Environment (NON-OBVIOUS)

- **PHPCS:** `composer lint` / `composer lint-fix`. **Build (only if SCSS changed):** `source ~/.nvm/nvm.sh; nvm use && npm run buildquick`.
- **Site:** Local, `https://lwtv.local` (self-signed → `curl -sk`). Test: `https://lwtv.local/statistics/stations/`, `.../stations/?station=the-cw`, `.../stations/sexuality/?station=the-cw`, etc. Find a real station slug from the leaderboard if `the-cw` isn't one.
- **wp-cli:** `php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" <args>`

## Data (verified — mirrors nations)

`stations.php` already loads `$all_stations_data` (`make_comprehensive('post_type_shows','lez_stations',true)` → `[slug=>['count','name','url']]`), `$character_counts` (`[slug=>['total','dead']]`), `$show_counts` (`[slug=>['onair','total','score','onairscore']]`), `$all_shows_count`, `$count`. Per-view: `generate_station_statistics($station, ltrim($view,'_'), 'array')` returns the raw slice (sexuality/gender `[[name,count,url,slug]]`; tropes/formats `[[name,count,url]]`; on-air `[year=>[name=>year,count,url]]`). `get_bulk_first_years('lez_stations', $slugs)` exists.

## File structure

**New:** `plugins/lwtv-plugin/php/statistics/templates/partials/leaderboard.php`.
**Modified:** `stations.php`; `stations/all.php`; `stations/single.php`; `nations/all.php` (repoint include); `class-stats-enqueues.php` (add `'stations'`).
**Removed:** `nations/leaderboard.php`.

**Renderability invariant:** each task leaves `/statistics/stations/` + `/statistics/nations/` rendering. Not-yet-ported station views keep their current (old Chart.js) output until their task lands; Chart.js stays enqueued.

---

### Task 1: Generalize the leaderboard partial

**Files:** create `partials/leaderboard.php`; modify `nations/all.php`; remove `nations/leaderboard.php`. **Nations rendered output MUST stay byte-identical.**

- [ ] **Step 1: Create `partials/leaderboard.php`** (generalized from `nations/leaderboard.php`; defaults reproduce the Nations output exactly)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ranked taxonomy leaderboard (nations / stations): rank · name · share bar
 * (true share of all shows, ramp by rank) · shows·pct · characters · dead.
 *
 * @package LezWatch.TV
 *
 * @var array  $leaderboard_rows  Ranked [ slug => ['name','count'] ], desc by count.
 * @var array  $leaderboard_chars [ slug => ['total','dead'] ].
 * @var int    $leaderboard_all   Total shows (for share %).
 * @var string $leaderboard_base  Base URL for row links (default '/statistics/nations/').
 * @var string $leaderboard_qvar  Query var for row links (default 'nation').
 * @var string $leaderboard_title Panel title.
 * @var string $leaderboard_col   Name-column header (default 'Nation').
 * @var string $leaderboard_items Lowercase plural noun for the sub-line (default 'nations').
 * @var string $leaderboard_icon_svg Header sprite file (default 'globe.svg').
 * @var string $leaderboard_icon_fa  Header FA fallback (default 'svg-globe').
 */

$lb_rows  = is_array( $leaderboard_rows ) ? $leaderboard_rows : array();
$lb_total = count( $lb_rows );
$lb_shown = array_slice( $lb_rows, 0, 10, true );
$lb_rank  = 0;
$lb_base  = $leaderboard_base ?? '/statistics/nations/';
$lb_qvar  = $leaderboard_qvar ?? 'nation';
$lb_title = $leaderboard_title ?? __( 'Nations by number of shows', 'lwtv' );
$lb_col   = $leaderboard_col ?? __( 'Nation', 'lwtv' );
$lb_items = $leaderboard_items ?? __( 'nations', 'lwtv' );
$lb_isvg  = $leaderboard_icon_svg ?? 'globe.svg';
$lb_ifa   = $leaderboard_icon_fa ?? 'svg-globe';
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon sexuality">
			<?php echo lwtv_plugin()->get_symbolicon( svg: $lb_isvg, icon: $lb_ifa, max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php echo esc_html( $lb_title ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: count, 2: plural noun (nations/stations). */
					esc_html__( 'Top 10 of %1$s %2$s with shows.', 'lwtv' ),
					esc_html( number_format_i18n( $lb_total ) ),
					esc_html( $lb_items )
				);
				?>
			</p>
		</div>
	</header>
	<div class="lwtv-nations-lb">
		<div class="lwtv-nations-lb-head">
			<span></span>
			<span><?php echo esc_html( $lb_col ); ?></span>
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
			$lb_ramp  = min( $lb_rank, 5 );
			?>
			<div class="lwtv-nations-lb-row">
				<span class="lwtv-nations-lb-rank"><?php echo esc_html( number_format_i18n( $lb_rank ) ); ?></span>
				<a class="lwtv-nations-lb-name" href="<?php echo esc_url( add_query_arg( $lb_qvar, $lb_slug, $lb_base ) ); ?>"><?php echo esc_html( $lb_data['name'] ); ?></a>
				<span class="lwtv-nations-lb-track">
					<span class="lwtv-nations-lb-bar lwtv-nations-lb-bar--<?php echo (int) $lb_ramp; ?>" style="width:0" data-grow-to="<?php echo esc_attr( (string) $lb_pct ); ?>"></span>
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

- [ ] **Step 2: Repoint `nations/all.php`** — change ONLY the include path (defaults reproduce nation output):

Replace:
```php
// Ranked nation leaderboard (Task 3 partial).
$leaderboard_rows  = $lwtv_ranked;
$leaderboard_chars = $character_counts;
$leaderboard_all   = (int) $all_shows_count;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'nations/leaderboard.php';
```
with:
```php
// Ranked nation leaderboard (shared partial; nation defaults reproduce prior output).
$leaderboard_rows  = $lwtv_ranked;
$leaderboard_chars = $character_counts;
$leaderboard_all   = (int) $all_shows_count;
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/leaderboard.php';
```

- [ ] **Step 3: Remove the old partial**

```bash
git rm plugins/lwtv-plugin/php/statistics/templates/nations/leaderboard.php
```

- [ ] **Step 4: Lint + verify Nations byte-identical**

```bash
composer lint-fix && composer lint
php -l plugins/lwtv-plugin/php/statistics/templates/partials/leaderboard.php
# Nations leaderboard still renders 10 rows, ramp, dead-red, correct links:
curl -sk https://lwtv.local/statistics/nations/ | grep -c 'lwtv-nations-lb-row'          # -> 10
curl -sk https://lwtv.local/statistics/nations/ | grep -oE 'href="[^"]*nation=[a-z-]+' | head -3
curl -sk https://lwtv.local/statistics/nations/ | grep -o 'Nations by number of shows'   # present
curl -sk https://lwtv.local/statistics/nations/ | grep -o 'Top 10 of [0-9]* nations with shows.'  # present, unchanged text
```
Expected: identical Nations leaderboard (10 rows, `?nation=` links, "Nations by number of shows", "Top 10 of N nations with shows."). No SCSS/build change (PHP only).

- [ ] **Step 5: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/partials/leaderboard.php plugins/lwtv-plugin/php/statistics/templates/nations/all.php plugins/lwtv-plugin/php/statistics/templates/nations/leaderboard.php
git commit -m "refactor(stats): generalize leaderboard into a shared partial

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Stations shell + enqueue

**Files:** modify `stations.php` (port of `nations.php`), `class-stats-enqueues.php`.

- [ ] **Step 1: Replace `stations.php` with a port of `nations.php`**

Copy the FULL current contents of `plugins/lwtv-plugin/php/statistics/templates/nations.php` into `stations.php`, then apply these exact replacements throughout:
- `nation` → `station` (variable names, query var, class-free text): `$sent_nation`→`$sent_station`, `$valid_nation`→`$valid_station`, `$nation`→`$station`, `get_query_var( 'nation'`→`get_query_var( 'station'`, `$all_nations_data`→`$all_stations_data`.
- `'lez_country'` → `'lez_stations'`.
- `generate_nation_statistics` → `generate_station_statistics`.
- `/statistics/nations/` → `/statistics/stations/` (baseurl in the sub-nav + Reset link).
- `nations/all.php` → `stations/all.php`; `nations/single.php` → `stations/single.php` (the include-dispatch paths).
- Visible strings: the `<label>` "Nation" → "Station"; `<option value="all">All Nations` → `All Stations`; sub-nav `aria-label` "Nation statistics views" → "Station statistics views"; the docblock "nation statistics" → "station statistics".
- Keep everything else identical (picker markup, `onchange="this.form.submit()"`, `<noscript>` Go, sub-nav loop, `.lwtv-stats-overview` wrapper, the `_`-prefix + include dispatch, the WP_DEBUG comment).

NOTE: the current `stations.php` already has the correct data-loading head (station/lez_stations); this replacement swaps in the redesigned markup shell. After the port, confirm the head still loads `$all_stations_data`/`$character_counts`/`$show_counts`/`$all_shows_count`/`$count`.

- [ ] **Step 2: Add `'stations'` to the count-up JS enqueue gate** in `class-stats-enqueues.php`:

```php
		if ( in_array( $statistics, array( 'none', 'shows', 'characters', 'actors', 'nations', 'stations' ), true ) ) {
```

- [ ] **Step 3: Lint + verify**

```bash
composer lint-fix && composer lint
php -l plugins/lwtv-plugin/php/statistics/templates/stations.php
curl -sk https://lwtv.local/statistics/stations/ | grep -c 'lwtv-nations-pickerform'   # -> 1 (picker present)
curl -sk https://lwtv.local/statistics/stations/ | grep -o 'statistics-overview.js'      # -> present (JS now enqueued)
S=$(curl -sk https://lwtv.local/statistics/stations/ | grep -oE '\?station=[a-z0-9-]+' | head -1 | sed 's/?station=//'); echo "sample station slug: $S"
curl -sk "https://lwtv.local/statistics/stations/?station=$S" | grep -o 'lwtv-stats-subnav-item is-active[^>]*>[^<]*'   # -> Overview
```
Expected: picker renders; count-up JS enqueued; sub-nav shows for a single station. (all.php/single.php still old below — Tasks 3-4 replace.)

- [ ] **Step 4: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/stations.php plugins/lwtv-plugin/php/statistics/class-stats-enqueues.php
git commit -m "feat(stats): stations picker/sub-nav shell + animation enqueue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: All Stations — counters + shared leaderboard

**Files:** replace `stations/all.php` (port of `nations/all.php`).

- [ ] **Step 1: Replace `stations/all.php` with a port of `nations/all.php`**

Copy the FULL current contents of `nations/all.php` into `stations/all.php`, then apply:
- `$all_nations_data` → `$all_stations_data`; `$lwtv_nation_total` → `$lwtv_station_total`; docblock "All-nations" → "All-stations".
- `'lez_country'` → `'lez_stations'` (the `get_bulk_first_years` call).
- Eyebrow: `esc_html_e( 'Around the World', 'lwtv' )` → `esc_html_e( 'Across the Dial', 'lwtv' )`.
- **Card 1 (Stations):** `'label' => __( 'Nations'` → `__( 'Stations'`; `'caption' => __( 'With at least one queer show'` → `__( 'Networks & platforms tracked'`; `'svg' => 'globe.svg', 'icon' => 'svg-globe'` → `'svg' => 'satellite-signal.svg', 'icon' => 'svg-satellite-signal'`; `'count' => $lwtv_nation_total` → `$lwtv_station_total`.
- **Card 3 (Biggest Platform):** replace the "US + UK share" computation + card. Change the computation block:
  ```php
  // Biggest platform = the top station's share of all shows.
  $lwtv_top_count = ! empty( $lwtv_ranked ) ? (int) reset( $lwtv_ranked )['count'] : 0;
  $lwtv_top_name  = ! empty( $lwtv_ranked ) ? reset( $lwtv_ranked )['name'] : '';
  $lwtv_topshare  = ( $all_shows_count > 0 ) ? round( ( $lwtv_top_count / $all_shows_count ) * 100 ) : 0;
  ```
  (Remove the old `$lwtv_top_counts` / `array_slice(...,0,2)` lines.) And the card:
  ```php
  array(
      'family'  => 'actors',
      'label'   => __( 'Biggest Platform', 'lwtv' ),
      'count'   => $lwtv_topshare,
      'suffix'  => '%',
      /* translators: %s: top station name. */
      'caption' => $lwtv_top_name ? sprintf( __( '%s leads — no network dominates', 'lwtv' ), $lwtv_top_name ) : __( 'No single network dominates', 'lwtv' ),
      'svg'     => 'location-target.svg',
      'icon'    => 'svg-location-target',
  ),
  ```
- **Card 4 (New Since 2020):** caption `__( 'Debuted their first queer show'` → `__( 'Aired their first queer show'` (leave family `nations-new`, svg `graph-line`).
- **Leaderboard include:** replace the include block with station params:
  ```php
  // Ranked station leaderboard (shared partial).
  $leaderboard_rows      = $lwtv_ranked;
  $leaderboard_chars     = $character_counts;
  $leaderboard_all       = (int) $all_shows_count;
  $leaderboard_base      = '/statistics/stations/';
  $leaderboard_qvar      = 'station';
  $leaderboard_title     = __( 'Stations by number of shows', 'lwtv' );
  $leaderboard_col       = __( 'Station', 'lwtv' );
  $leaderboard_items     = __( 'stations', 'lwtv' );
  $leaderboard_icon_svg  = 'satellite-signal.svg';
  $leaderboard_icon_fa   = 'svg-satellite-signal';
  // phpcs:ignore PEAR.Files.IncludingFile.UseRequire
  include plugin_dir_path( __DIR__ ) . 'partials/leaderboard.php';
  ```
- Keep the metric-card render loop, `.lwtv-metric-grid--4`, families, `data-count-to`/`data-count-suffix` markup identical.

- [ ] **Step 2: Lint + verify**

```bash
composer lint-fix && composer lint
php -l plugins/lwtv-plugin/php/statistics/templates/stations/all.php
curl -sk https://lwtv.local/statistics/stations/ | grep -c 'lwtv-metric-card'                 # -> 4
curl -sk https://lwtv.local/statistics/stations/ | grep -oE 'card-header (shows|characters|actors|nations-new)' | sort -u
curl -sk https://lwtv.local/statistics/stations/ | grep -o 'Across the Dial'                   # present
curl -sk https://lwtv.local/statistics/stations/ | grep -c 'lwtv-nations-lb-row'               # -> 10 (station leaderboard)
curl -sk https://lwtv.local/statistics/stations/ | grep -oE 'href="[^"]*station=[a-z0-9-]+' | head -3  # station links
curl -sk https://lwtv.local/statistics/stations/ | grep -o 'Stations by number of shows'       # present
```
Expected: 4 cards (Stations/Have-10+/Biggest-Platform %/New-Since-2020), eyebrow "Across the Dial", 10-row leaderboard with `?station=` links + "Stations by number of shows". Cross-check Biggest Platform ≈ handoff's ~7%.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/stations/all.php
git commit -m "feat(stats): all-stations counters + shared leaderboard

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Single station — profile + 6 views

**Files:** replace `stations/single.php` (port of `nations/single.php`).

- [ ] **Step 1: Replace `stations/single.php` with a port of `nations/single.php`**

Copy the FULL current contents of `nations/single.php` into `stations/single.php`, then apply these exact replacements (variable names + data source + visible strings only; **keep all `.lwtv-nation-*` CSS class names as-is** — they're reused, not renamed):
- `$all_nations_data` → `$all_stations_data`.
- `$nation` → `$station` (the two occurrences: `$lwtv_slug = ltrim( $nation, '_' )` and the docblock `@var string $nation`).
- `generate_nation_statistics` → `generate_station_statistics` (all four `'array'`-path calls — they already use `ltrim( $view, '_' )`; keep that).
- The default name fallback `__( 'Nation', 'lwtv' )` → `__( 'Station', 'lwtv' )`.
- Profile eyebrow `esc_html_e( 'National Profile', 'lwtv' )` → `esc_html_e( 'Station Profile', 'lwtv' )`.
- Docblock "Single-nation statistics" → "Single-station statistics".
- Leave EVERYTHING else identical: the `.lwtv-nation-preamble` line + its text (generic — "Use the tabs above…"), `.lwtv-nation-profile*` markup, the `$lwtv_build_segments` closure, the `switch( $view )` with all six cases (`_all` Overview 4 counters+score+sentence; `_sexuality`/`_gender`/`_formats` donuts; `_tropes` ranked-bars; `_on-air` trendline **with the Best Year fireworks callout**), the `$lwtv_name`/`$lwtv_shows`/`$lwtv_chars`/etc. variable names (generic — do not rename).

The Best Year callout, donut segment builder, and all `$donut`/`$ranked`/`$trend` configs port verbatim (they reference `$lwtv_name` etc., which now hold the station's data).

- [ ] **Step 2: Lint + verify**

```bash
composer lint-fix && composer lint
php -l plugins/lwtv-plugin/php/statistics/templates/stations/single.php
S=$(curl -sk https://lwtv.local/statistics/stations/ | grep -oE '\?station=[a-z0-9-]+' | head -1 | sed 's/?station=//'); echo "station: $S"
curl -sk "https://lwtv.local/statistics/stations/?station=$S" | grep -o 'Station Profile'                       # present
curl -sk "https://lwtv.local/statistics/stations/?station=$S" | grep -c 'lwtv-metric-card'                      # -> 4 (overview)
for v in sexuality gender formats; do echo -n "$v donut segs: "; curl -sk "https://lwtv.local/statistics/stations/$v/?station=$S" | grep -oE 'lwtv-donut-seg--[a-z0-9]+' | sort -u | tr '\n' ' '; echo; done
curl -sk "https://lwtv.local/statistics/stations/tropes/?station=$S" | grep -c 'lwtv-leader-row'                # -> >0
curl -sk "https://lwtv.local/statistics/stations/on-air/?station=$S" | grep -o 'lwtv-trend-callout-text">[^<]*' # Best Year sentence
# No Chart.js canvas in any single-station view:
for v in "" sexuality gender tropes formats on-air; do echo -n "$v canvas: "; curl -sk "https://lwtv.local/statistics/stations/$v/?station=$S" | grep -c '<canvas'; done
```
Expected: profile bar ("Station Profile" + name + shows/chars/dead); Overview 4 counters; donuts (sexuality/gender/formats); tropes green bars; on-air trendline + Best Year callout with fireworks; every single-station view `<canvas>`=0.

- [ ] **Step 3: Commit**

```bash
git add plugins/lwtv-plugin/php/statistics/templates/stations/single.php
git commit -m "feat(stats): single-station profile + six server-rendered views

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Full verification + visual QA

**Files:** none expected (fixes only if issues found).

- [ ] **Step 1: Lint + build clean**

```bash
composer lint-fix && composer lint
npm run lint:css
source ~/.nvm/nvm.sh; nvm use && npm run buildquick   # only if SCSS changed; otherwise skip
```

- [ ] **Step 2: Render + regression sweep** (find a real station slug first)

```bash
S=$(curl -sk https://lwtv.local/statistics/stations/ | grep -oE '\?station=[a-z0-9-]+' | head -1 | sed 's/?station=//')
for u in \
  "statistics/stations/" \
  "statistics/stations/?station=$S" \
  "statistics/stations/sexuality/?station=$S" \
  "statistics/stations/gender/?station=$S" \
  "statistics/stations/tropes/?station=$S" \
  "statistics/stations/formats/?station=$S" \
  "statistics/stations/on-air/?station=$S" \
  "statistics/nations/" "statistics/nations/?nation=united-kingdom" \
  "statistics/" "statistics/shows/" "statistics/characters/" "statistics/actors/" "statistics/death/"; do
  code=$(curl -sk -o /tmp/s.html -w "%{http_code}" "https://lwtv.local/$u")
  err=$(grep -ciE "Fatal error|Warning:|Notice:" /tmp/s.html)
  echo "$u -> HTTP $code, php-errors=$err"
done
echo "death still has chartjs:"; curl -sk https://lwtv.local/statistics/death/ | grep -c 'chart.min.js'
```
Expected: every URL 200 / 0 php-errors; **Nations leaderboard unchanged** (byte-identical); Death still loads Chart.js.

- [ ] **Step 3: Browser QA** on `https://lwtv.local/statistics/stations/` + a single station, against `design_handoff_statistics_stations/screenshots/` (01–04 + dark). (Count-up/bars freeze on the automation tab's backgrounded rAF — force finals via `el.style.width=data-grow-to` / `el.textContent=data-count-to` for screenshots, per the Nations round.)
  - All Stations: picker, 4 counters (blue/green/yellow/pink), leaderboard (ramp darkest→lightest, true-share bars, dead red, `?station=` drill-in).
  - Single station: profile ("Station Profile"); sub-nav; Overview (counters + score + sentence); Sexuality/Gender/Formats donuts; Tropes green bars; On-Air trendline + Best Year fireworks callout + start/end year axis.
  - Light + dark; picker auto-submit + Reset; primary tab bar shows Stations active.
  - Regression: Nations + other sections unchanged.

- [ ] **Step 4: Commit** (only if Step 3 required fixes)

---

## Self-Review

**Spec coverage:** generalized leaderboard → T1; stations shell + enqueue → T2; all-stations counters (Biggest Platform) + leaderboard → T3; single-station profile + 6 views (Best Year reused) → T4; verification → T5. Reuse mandate, family map, `ltrim` gotcha, no-SCSS-change, i18n/escaping, divisor guards, Chart.js-untouched — enforced per task + Global Constraints. ✓

**Placeholder scan:** no TBD/TODO. Ports are expressed as copy + explicit substitution lists (concrete). New code (shared leaderboard, Biggest Platform, station leaderboard params) is given in full. Deferred: exact station slug discovered at verify time; Biggest Platform caption names the top station dynamically. ✓

**Type consistency:** shared `partials/leaderboard.php` var contract (`$leaderboard_rows/chars/all/base/qvar/title/col/items/icon_svg/icon_fa`) matches both callers; nation defaults reproduce byte-identical output. `generate_station_statistics(...,ltrim($view,'_'),'array')` shapes match `donut.php`/`ranked-bars.php`/`trendline.php` contracts (same as Nations). Reused `.lwtv-nation-*`/`.lwtv-nations-lb*` classes exist in SCSS. ✓

## Known follow-ups (out of scope)
- After Stations, only Death + This Year still use Chart.js.
- Counter/profile copy is derived/handoff-illustrative — owner may tune.
- Sub-nav labels + reused `.lwtv-nation-*` class names carry the "nation" wording/naming on Stations (functional; rename later if desired).
