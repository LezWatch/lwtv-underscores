# Calendar: Consolidate Three Views Into One

**Status:** Implemented, then rebuilt to the "Compact agenda" (1c) design handoff. ICS export is still open. See **Agenda redesign** at the end for what changed in the second pass and where I deviated from the handoff.
**Scope:** `plugins/lwtv-plugin/php/calendar/`, `scss/addons/_calendar.scss`, `plugins/lwtv-plugin/assets/js/`
**Risk:** Low — the calendar is TVMaze-sourced and touches the show CPT only via `Names::get_link()` for permalinks. No character/actor relationships, no show-score inputs, no statistics dependencies.

## Decision

Keep the **day-grouped chronological list** as the single view. Delete Grid and Calendar. Replace the Calendar tab's actual job ("see it as a calendar") with an ICS/subscribe feed so people get it in their own calendar app.

Rationale: at 0–3 episodes/day, a 7-column grid is mostly whitespace and too narrow for episode titles (which is why the Calendar view silently drops them). The card Grid costs ~4× the vertical scroll for the same content, and the thumbnails aren't informative on a schedule. The list scales from one episode to thirty per day, needs no breakpoint work, and reads correctly to a screen reader as what it is.

---

## Bugs found while auditing (fix as part of this)

These are all live on production right now.

1. **`Calendar_Meta_Batcher` is a complete no-op — and there was nothing left for it to do.**
   `Data_Processor::process_calendar_data()` called `batch_load_calendar_data( $raw_calendar )`, but raw shows from `Generate_Calendar::make()` only have `show_name`, `title`, `timestamp`, `native_tz`. There is no `show_id` key yet, so the `isset( $show['show_id'] )` guard never passed and `$show_ids` was always empty. Every batch query, thumbnail preload, and the three-size HTML pre-generation was skipped.

   Following it through, the preload had no surviving consumer anyway: the meta keys it loads (`lezshows_tvmaze_timezone`, `lezshows_tvmaze_id`, `lezshows_imdb`) are only read by `TVMaze`, which the list path never calls once `native_tz` is gone (bug 2), and thumbnails were only used by the Grid view. `get_meta()` also self-heals with a single-ID fallback, so nothing depended on the preload being warm.

   *What the real cost was:* `process_show_data()` called `$names->make()` **twice** per show — once for `'name'`, once for `'id'` — and each `make()` runs up to two `get_page_by_path()` queries. Four path lookups per show where two would do, on every cold-cache render of three weeks of schedule.

   *Fix applied:* removed the dead `batch_load_calendar_data()` call and the thumbnail machinery it fed (`batch_load_thumbnails`, `pre_generate_thumbnail_html`, `get_thumbnail`, `get_lazy_thumbnail` — 189 lines), and added `Names::resolve()`, which does the lookup once and memoises per request. `make()` now delegates to it, so its public API is unchanged for `TVMaze`.

   *Still dead, not touched:* `plugins/lwtv-plugin/php/_helpers/class-calendar-database-optimizer.php` has zero callers anywhere in the codebase. Worth deleting separately.

2. **`native_tz` is always the literal string `'timezone'`.**
   `Generate_Calendar` lines 58 and 66 hardcode `'native_tz' => 'timezone'`. Because that's truthy, `Data_Processor` line 100 never falls through to `TVMaze::get_timezone()`, and `Display_Grid::get_native_date()`'s `in_array( ..., timezone_identifiers_list() )` check always fails. The native-timezone display has never rendered. Deleting Grid deletes the only consumer, so this resolves itself — but the hardcoded string should come out of `Generate_Calendar` too.

3. **The list view's "today" row highlight is dead code.**
   `Display_List` line 49 compares `$weekday` (a `Y-m-d` string) against `$today->format( 'l' )` (a day name). Never true, so `$highlight` is always `''`. Line 55 does the same comparison correctly. This is why the List view shows a "Today" badge but no row tint, while the Calendar view tints the whole column.
   *Fix:* compare against `$today->format( 'Y-m-d' )`, or just use the already-computed `$show['display_data']['highlight_class']`.

4. **Invalid table markup in the list view.**
   `Display_List` line 74 emits `</tbody>` *inside* the per-show `foreach`. A day with three shows produces three closing tags and no opener for shows 2–3. Browsers recover, but this is the likely source of any odd row spacing.

5. **Anchor ID inconsistency.** Empty days got `id="list_2026-08-22"`; days with shows got `id="list_saturday"`. `get_subnav()` linked to `#list_<dayname>`. Not a broken link in practice (the subnav only links days that have shows) but it was two schemes. Now uniformly `id="day_<Y-m-d>"`.

6. **Bonus, found while editing:** `get_week_of_days()` mutates the `DateTime` it is handed, walking it forward six days. The list view calls it twice on the same object — once via `get_subnav()`, once for the table — and only worked because the second call happened to rewind to the right Sunday. It now clones its input.

7. **Also fixed:** the "Today" marker was an `<a name="today">` nested inside a `<button disabled>`, which is not valid and not focusable. Replaced with a `<span id="today" class="badge">`, preserving the `#today` anchor target.

---

## Files deleted

| File | Lines |
|---|---|
| `plugins/lwtv-plugin/php/calendar/class-display-grid.php` | 189 |
| `plugins/lwtv-plugin/php/calendar/class-display-calendar.php` | 165 |
| `plugins/lwtv-plugin/assets/js/calendar-tabs.js` | 23 |

Removing `calendar-tabs.js` drops a jQuery-dependent script from the page and removes the Bootstrap tab JS dependency for this template.

## `class-display.php` changes

- **Line 45** — delete `wp_enqueue_script( 'lwtv-calendar', ... )`.
- **Line 52** — delete `$get_tvview`.
- **Lines 117–171** — delete `get_tab_navigation()` and `get_tab_content()` (55 lines). The intro copy and `<a name="caltop">` anchor move into a small `get_intro()`; the body becomes a direct `( new Display_List() )->get_shows( $processed_calendar, $date_query )`.
- **Lines 183–190 / 248–280** — drop `$tv_view` from `get_footer()` and `get_footer_navigation()`; remove `'tvview'` from both query-arg arrays and the `$this_week` URL.
- **Line 329** — `get_subnav()` loses its `$prefix` param. It only existed so Grid could `str_replace( 'list_', 'grid_', ... )` (Grid line 37). That hack dies with it.
- Remove the `Display_Grid` / `Display_Calendar` references at lines 132–133.

## `class-ics-parser.php`

- **Line 58** — remove `$vars[] = 'tvview';` from `query_vars()`. Keep `tvdate`.

## `class-data-processor.php`

Fields that lose their last consumer once Grid and Calendar are gone:

| Field | Only consumer |
|---|---|
| `native_tz` (+ the `TVMaze::get_timezone()` fallback, lines 96–102) | `Display_Grid::get_native_date()` |
| `time_data['formatted_time']` | `Display_Calendar` line 154 |
| `display_data['is_today']` | nothing (already unused) |
| `display_data['highlight_class']` | nothing (already unused — but see bug 3; better to *use* it than delete it) |
| `episode_count` | nothing (already unused) |

Still needed: `show_name`, `show_link`, `show_id`, `title`, `timestamp`, `time_data['lwtv_date']`, `display_data['dot_class']`, `episode_badge` (the widget uses this one too, at `inc/widgets/calendar-widget.php:100`).

**Bump `CACHE_PREFIX` to `lwtv_processed_calendar_v3_`** (line 29). The comment in the file says to do this whenever the shape changes; skipping it serves v2 payloads to a view that no longer matches.

Thumbnails can drop out of `Calendar_Meta_Batcher::batch_load_calendar_data()` entirely — Grid was the only view rendering images. That also removes `pre_generate_thumbnail_html()`'s three-sizes-per-show loop, which only ever needed `'thumbnail'`.

## SCSS

`scss/addons/_calendar.scss` (93 lines) — remove:

- `.ep-calendar-calendar td` (Calendar only)
- `.calendar-show-img` (Grid only)
- `ul#calendarTab a.nav-link` and its three nested states — ~18 lines of tab styling
- `h3.ep-calendar-day-heading` (Grid only; also in `_colors-dark.scss:455`)
- `.ep-calendar-heading-weekday` (Calendar only)

Also: `scss/partials/_responsive.scss:115` `.ep-calendar-grid-col` (Grid only). Keep `_colors-dark.scss:448` `.ep-calendar-title` — the list uses it.

Net: roughly half of `_calendar.scss` goes.

## Untouched

`class-generate-calendar.php` (except bug 2), `class-tvmaze.php`, `class-names.php`, `_components/class-calendar.php`, `inc/widgets/calendar-widget.php` (uses `Build_Calendar` + `Data_Processor` directly, no `Display_*`), `rest-api/class-whats-on-json.php` (uses `ICS_Parser` directly).

---

## Then: make the list good enough to be the only view

Done in this pass:

- `font-variant-numeric: tabular-nums` + `white-space: nowrap` on `.ep-calendar-item-time` so times align vertically.
- Empty days collapsed via `.ep-calendar-empty` (smaller type, tighter padding) instead of occupying as much room as a day with two episodes.

Still open:

1. Sticky day header; scroll to `#today` on load when viewing the current week. (`thead.dayjump` already has `scroll-margin-top: 55px`, so the anchor offset is handled — this is just `position: sticky`.)
2. Consider dropping the repeated `(EDT)` from every row — the intro already states the timezone once. That string is built in `Data_Processor` as `time_data['lwtv_date']`, so it's a one-line change plus a cache bump.
3. Add a month jump alongside the existing Last/Next week pagination.
4. Optional: a compact 7-day strip at the top with per-day counts, as *navigation* — this preserves the "glance across the week" affordance the grid was reaching for, without a second view.
5. Dark mode: `thead.dayjump` has no dark override (the Grid's `h3.ep-calendar-day-heading` had one, and it was deleted with the view). The `<th>` carries Bootstrap's `.text-bg-secondary`, which may be sufficient — needs a visual check on a running site, which is why it was left alone here.

## New: ICS export

There is currently no `text/calendar` output anywhere in the codebase — `ICS_Parser` only *reads* TVMaze's feed. Add a subscribe endpoint as a sibling to `whats-on-json` in `plugins/lwtv-plugin/php/rest-api/`, emitting `BEGIN:VCALENDAR` with one `VEVENT` per airing, plus a `webcal://` link on the calendar page. This is what the Calendar tab was actually for.

## Migration / redirects

`?tvview=grid` and `?tvview=calendar` URLs exist in the wild (the tab JS wrote them into the address bar on every tab click, so they're in browser history and possibly bookmarks and search indexes). Since `tvview` is simply ignored once removed, those URLs still render the list — no 404s, no redirect needed. Confirm nothing in the sitemap or internal links hardcodes `tvview` before shipping.

## Verification

- `npm run lint:css` passes and `style.scss` compiles clean (verified).
- **`composer lint` and `vendor/bin/phpunit` have NOT been run** — no PHP available in the environment this was written in. Run both before committing.
- `style.css` / `style.min.css` need a rebuild (`npm run buildquick`) on Node 24 per `.nvmrc`.
- No unit tests to add — none of this is a pure `build/` transform. Verify against the running site:
  - Current week, a past week, a future week beyond the 2–4 week projection window (empty-calendar path).
  - A day with a binge (array `title`) to confirm the episode badge and `<ul>` still render.
  - A week where TVMaze returns nothing (`$calendar['none']`) and where it errors entirely.
  - Light and dark mode, WCAG AA on the day headers.
  - Confirm the widget still renders after the `CACHE_PREFIX` bump.

---

# Agenda redesign (pass 2)

Implements the "Compact agenda" (1c) direction from the `LezWatch.TV-Calendar`
handoff. The information architecture is unchanged from pass 1 — one
day-grouped list — but the range, chrome, and per-episode state are new.

## What changed

- **One week per page, paginated.** Briefly tried three weeks continuous, but
  the day-of-week pill strip is a "where am I in the week" indicator and means
  nothing spanning three. Reverted to a single week with the Last/Next Week
  pagination and the `Week of…` heading retained.

  Worth knowing: `Display::make()` used to fetch last, this and next week and
  merge all three, then render only one. That was left over from the deleted
  3-week Calendar view. It now fetches only the week it renders, which removes
  two ICS parses per request - the most expensive part of the page.
- **Quiet days are omitted** rather than shown as "No shows on this day" — the
  day-of-week strip covers it, and across 21 days the empty rows were noise.
- **Sticky header** with a timezone eyebrow, a jump-to-today control, and the
  seven day-of-week pills.
- **Per-episode aired state.** Each dot carries an ISO airtime; the client
  greys it once the episode has actually aired, so an episode on today's own
  row goes grey mid-day while the rest of today stays in its accent treatment.

## New files

| File | Purpose |
|---|---|
| `php/calendar/build/class-agenda.php` | Pure transform: day-grouping, labels, airtime recovery, week strip. No WP. |
| `tests/unit/Calendar/AgendaTest.php` | 14 tests, heaviest on the airtime math. |
| `php/calendar/class-display-agenda.php` | Renderer (replaces `class-display-list.php`). |
| `assets/js/calendar-agenda.js` | Dot state + scroll-to-today. No jQuery. |

`Data_Processor` lost `display_data` entirely (`is_past`, `dot_class`) — the
agenda derives dot state itself. Cache prefix is now `v4`.

## The airtime trap

`Generate_Calendar` does not store the real airing instant. It takes TVMaze's
UTC time and **adds** the US/Eastern offset, so reading the value back as UTC
yields the Eastern wall clock. Fine for display, wrong for comparison.

Emitting that timestamp straight into `data-airtime` would grey every dot out
**4 hours early** in EDT and **5 hours early** in EST. `Agenda::airtime()`
reinterprets the wall clock in the real timezone, which recovers the true
instant and produces the correct offset for that date. `AgendaTest` asserts
both the DST and standard-time cases, and asserts the drift is non-zero so the
bug cannot silently return.

The handoff's mock hardcoded `-04:00`, which would have broken in November.

## Deviations from the handoff

These are deliberate. Everything else is implemented as specified.

1. **No opacity fade on past days.** The spec called for `0.55`–`0.7` opacity
   over a `#8a8a8a` grey. Measured, that lands at **1.84–2.24:1** against
   white; AA needs 4.5:1. Past days instead use a new `$lwtv-grey-muted: #666`
   (5.74:1) at full opacity, and recency is carried by the grey dot. The
   graduated ramp is gone.
2. **Theme pink, not `#c9186f`.** Using the existing `$lwtv-pink: #cb3e85`
   keeps one pink in the system. Consequence: `$lwtv-pink` is 4.60:1 on pure
   white, so it fails on *any* tinted background. Anywhere pink text meets the
   pink tint — today's day header, the jump button — uses
   `$lwtv-pink-deep: #9e2968` instead (4.91:1 on `$lwtv-pink-light`, 7.07:1 on
   white). There is no pink tint that works with `$lwtv-pink` itself.
3. **Day pills stay interactive.** The spec made them a static indicator. They
   were already jump links and losing that is a regression; seven things that
   look exactly like buttons but aren't is also a usability trap. Days with
   episodes link to their group, quiet days render as inert spans.
4. **No auto-scroll on load.** The spec scrolls to today on load. On a real
   page that yanks the visitor past the heading and intro before they've seen
   them, and it fights the browser's scroll restoration. Scrolling now happens
   on the Today button, or when arriving on `#ep-agenda-today` directly.
5. **Page scroll, not a nested scroller.** The mock is a 420px box with
   `overflow-y: auto`. Nesting a scroll container inside page scroll traps the
   wheel, breaks in-page find, and hurts print. The panel is capped at `44rem`
   for line length and the header is `position: sticky` against page scroll.
6. **Icons from symbolicons** (`clock`, `calendar-alt`), not Lucide, per the
   handoff's own note about avoiding a second icon dependency.

## Still open

- **ICS export** — nothing emits `text/calendar` yet. Unchanged from pass 1.
- **Dark mode needs a visual check.** Written but never rendered: `$lwtv-pink`
  is only ~3:1 on the dark surfaces, so accents step up to `$lwtv-pink-medium`
  (6.03:1) and `$lwtv-pink-light`. Verify on a running site.
- **Mobile.** The handoff mocked nothing below its 420px reference. The layout
  is flex with a `4.75rem` time column and should hold, but the seven-pill
  strip will wrap on narrow screens — it is set to `flex-wrap: wrap`, which is
  a guess at the right behavior.
- `class-calendar-database-optimizer.php` still has zero callers.

## Verification status

- `style.scss` compiles; `npm run lint:css` clean; `node --check` clean on the
  new JS.
- The airtime shift and recovery were verified independently against the real
  tz database (Node `Intl`), confirming the 4h/5h drift figures.
- **`composer lint` and `vendor/bin/phpunit` have NOT been run** — no PHP in
  the authoring environment. `AgendaTest` is written but unexecuted. Run both
  before committing.
- `style.css` / `style.min.css` need `npm run buildquick` on Node 24.

---

# Linter scope fixes

Found while verifying the calendar work: both JS and CSS linting covered only a
fraction of the codebase, so a green `npm run lint` meant much less than it
looked like.

## CSS

`lint:css` globbed `style.scss` alone - a file containing nothing but `@use`
statements, since `lint-style` does not follow those into partials. Every real
stylesheet in `scss/` was unlinted. Meanwhile `fix:css` globbed `'**/*.scss'`,
which reaches into `vendor/twbs/bootstrap/scss/` - 164 files - so it could
rewrite vendored Bootstrap sources.

- `.stylelintrc.json` - relaxes `selector-class-pattern` to a regex accepting
  kebab-case, snake_case and BEM. The 466 violations were all legitimate: BEM
  (`entry-meta-card__row--tags`), WordPress core snake_case
  (`widget_categories`, `current_page_parent`), plugin markup
  (`facetwp-facet-shows_scores`) and generated symbolicon names. None were
  renameable. `no-descending-specificity` is off; it is noisy with nested SCSS
  and `_colors-dark.scss` already disabled it file-wide.
- `.stylelintignore` - mirrors `phpcs.xml.dist`'s exclusions. Fixes `lint:css`
  and closes the `fix:css` hazard in one place.
- `lint:css` now globs `'**/*.scss'`: 40 files, exit 0.

759 findings to zero: 655 absorbed by config, 91 auto-fixed, 13 by hand. Of
those 13, two were real (a dead `margin-left` in `_searchwp.scss` cancelled by
a `margin: 0` two lines below, and a fully redundant duplicate `svg` block in
`_stats.scss`); two were safe merges; the rest got scoped disables, including
`.lwtv-donut-legend-track .progress-bar`, whose own comment says it
deliberately overrides an earlier block via source order.

**Verified inert** by diffing compiled CSS against a pre-change baseline:
`style-editor.css` and `style-admin.css` byte-identical, `style.css` 60 changed
lines - all `currentColor`→`currentcolor`, `:after`→`::after`, `0px`→`0`, or
deletions from the merges. Zero new or changed values.

## JavaScript

`lint:js` globbed `blocks/src/**/*.js` only, so `inc/js/` and
`plugins/lwtv-plugin/assets/js/` were never linted.

`wp-scripts lint-js` is not blocks-only - it wraps ESLint with
`@wordpress/eslint-plugin` and lints whatever it is pointed at. No second
linter is needed.

- `eslint.config.js` - flat config extending the `@wordpress/scripts` default,
  adding the same ignores as `.stylelintignore` plus `**/*.min.js` and the
  bundled `jquery.tablesorter.js`, and declaring browser globals (plus
  `jQuery`, `bootstrap`, `FWP`) for `inc/js/` and the plugin's `assets/js/`.
  Without that last part, `location` failed `no-undef` - a config gap, not a
  code problem.
- Scripts renamed to `lint:js:blocks` / `:theme` / `:plugin`. The `-` variants
  double-ran, because npm-run-all's `*` does not cross `:` but does match
  `js-blocks` - so `lint:*` picked up the children as well as `lint:js`.
  `fix:js` also pointed at the `lint:` scripts, so it never actually fixed
  anything; it now fans out to real `fix:js:*` siblings.

537 findings to zero: ~490 auto-fixed (mostly `prettier/prettier` - the theme
JS had never been formatted - plus `no-var`), 15 resolved by the globals
config, and ~40 by hand.

**Behavioural fixes worth a smoke test**, since none of this is covered by
tests and I could not exercise it:

- `navigation.js` - `i` and `len` in the menu focus loop were undeclared,
  leaking onto `window`. Now `let`. Also dropped `subMenus`, which was queried
  and never used.
- `a11y.js` - two `.map()` calls used purely for side effects became
  `.forEach()`, and the unused jQuery argument was removed. Behaviour-identical,
  but this file sets the aria attributes on widget and social menus.
- `skip-link-focus-fix.js` - `is_webkit`/`is_opera`/`is_ie` renamed to camel
  case; local variables only.
- `searchbox.js`, `lwtv-theme-scripts.js` - five `==` to `===`. Both sides are
  strings or numbers in every case.
- `statistics-overview.js` - nested ternary unwound; unused catch binding
  dropped.

Keyboard navigation, the skip link, and the search overlay are the things to
click through.
