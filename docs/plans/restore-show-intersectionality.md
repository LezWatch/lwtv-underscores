# Restore Intersectionality Display on Show Pages

**Status:** Planned
**Editor request:** Bring back intersectionality on show pages so that, when browsing search results, it's clear which shows carry which intersectionality labels (e.g. a show found via PoC-Centric that also has Diverse Cast).

## Root cause

The card never went away — it silently stopped rendering.

`template-parts/sidebar/post_type_shows.php:33` calls:

```php
get_template_part( 'template-parts/partials/shows/card', 'intersectionality', array( 'show_id' => $show_id ) );
```

The partial on disk is `template-parts/partials/shows/card-intersections.php`. WordPress finds neither `card-intersectionality.php` nor a `card.php` fallback, so it outputs nothing, with no error.

A fix for exactly this line exists in commit `fc7892a9` ("Intersections", 2024-07-24) on `bugfix/post-psdev-2`, but that branch was never merged.

## Data model (unchanged, no migration needed)

- Taxonomy `lez_intersections`, registered on `post_type_shows` only (`plugins/lwtv-plugin/php/cpts/class-shows.php:42`).
- Edited via ACF field `lezshows_intersectional` (`acf-json/group_lwtv_shows_details.json`), `save_terms: 1`, so terms are already on every show.
- Feeds show scoring (`class-calculations.php:404-411`, +3/term capped at 15) — **not touched by this plan**.
- Term icons in term meta `lez_termsmeta_icon`; list styling already exists (`scss/_layout.scss:1512-1544`, dark mode `scss/partials/_colors-dark.scss:225`). No CSS work needed.

## Changes

### 1. Fix the template-part slug (the actual restore)

`template-parts/sidebar/post_type_shows.php:33` — change `'intersectionality'` → `'intersections'`.

(Chosen over renaming the file: smaller diff, matches the unmerged fc7892a9 fix, avoids updating `docs/theme-structure.txt`.)

### 2. Clean up `card-intersections.php`

- Line 20: `<section id="ratings">` collides with `card-ratings.php`'s `id="ratings"`. Change to `id="intersections"`.
- Line 31: typo "postivie" → "positive" in the aria-label.
- Line 35: modernize the symbolicon call to match `card-tropes.php:34`:
  ```php
  echo lwtv_plugin()->get_symbolicon( svg: $icon . '.svg', icon: 'svg-lemon', max_size: '32' );
  ```
  Also guard the case where `lez_termsmeta_icon` is empty (falls back gracefully; default icon elsewhere is `flag-wave.svg`).
- Line 23: wrap the card header for i18n: `<?php esc_html_e( 'Intersectionality', 'lwtv' ); ?>` (per project i18n convention; the hardcoded string predates it).
- Line 27: stale comment says "loop over each returned trope" — copy/paste from tropes; update.

### 3. Fix the debugger bug

`plugins/lwtv-plugin/php/debugger/class-shows.php:235` — `check_intersection_problems()` reads `get_post_meta( $show_id, 'lez_intersections', true )`. That meta key doesn't exist (terms live in the taxonomy), so the check always short-circuits and never runs. Change to:

```php
$intersections = get_the_terms( $show_id, 'lez_intersections' );
```

The existing `if ( ! $intersections || is_wp_error( $intersections ) )` guard already handles both `false` and `WP_Error`. This re-enables the "shows with intersectionality should have matching characters" validation.

## Verification

1. `composer lint` (phpcs) on the three touched files.
2. On a dev site: view a show with 2+ intersections (confirm card renders with icons + term links), a show with one, and a show with none (confirm no empty card).
3. Confirm term links resolve to `/intersection/<slug>/` archives.
4. Check light and dark mode rendering of the icon list.
5. Trigger the show debugger on a show that has intersections but disabled characters; confirm `check_intersection_problems()` now reports.
6. Confirm show scores are unchanged (no scoring code touched).

## Phase 2: Contextual badges on result cards (implemented)

Addresses the editor's actual pain point: telling shows apart in filtered search results.

**Design (chosen from three mocked variants):** a labeled icon row in `card-meta` — `Intersectionality:` followed by ~20px term symbolicons, each linking to its term archive, with tooltip + aria-label. Rejected: title-row callouts (muddies the alerts/ratings iconography) and full text lists (too much clutter, wraps on term-heavy shows).

**Contextual trigger — the row only renders when the view is intersection-focused:**

- `is_tax( 'lez_intersections' )` — term archives like `/intersection/poc-centric/`.
- FacetWP `/shows/` archive when the `show_intersectionality` facet has a selection. Checked via **both** `FWP()->facet->facets['show_intersectionality']['selected_values']` (populated during AJAX re-renders) and `FWP()->request->url_vars['show_intersectionality']` (populated on initial page load from `?fwp_show_intersectionality=...` URLs — note this install uses the `fwp_` prefix, not FacetWP's default `_`).

Default `/shows/` browsing and all other archive contexts are unchanged.

**Implementation:** all in `template-parts/excerpt/shows.php` (shared by `archive-post_type_shows.php` and `taxonomy.php`, so both views get it from one edit). No SCSS changes needed — the global dark-mode `svg` fill rule covers the icons, and light mode inherits the muted meta styling.

**Verified live:** term archive shows rows; plain `/shows/` shows none; facet selection via UI (AJAX path) and via URL (fresh-load path) both show rows; Bootstrap tooltips initialize (title moves to `data-bs-original-title`, aria-labels intact); term links resolve; light + dark mode both legible.

## Phase 3: Statistics page improvements (implemented)

For `/statistics/shows/intersectionality/`:

- **Breakdown display switched to lollipop charts.** Share mode against all ~2,255 shows rendered every bar as a near-invisible sliver (top term = 5% width); an interim leaderboard mode fixed legibility but full-width filled bars misread as "100% of shows." Final form: a new `'lollipop'` mode in `partials/ranked-bars.php` (stick + end dot, scaled relative to the top term, raw count label, visual is `aria-hidden` with the name/count text carrying the data). No fill means nothing reads as a percentage. Styles in `scss/addons/_stats.scss` (family colors match the existing bar fills) with a dark-mode pink override in `scss/partials/_colors-dark.scss`. Both intersectionality panels use it; other stats views are unchanged.
- **New "Common Pairings" panel.** Counts how often intersections co-occur on the same show (top 8 pairs, minimum 2 shows). Architecture per the build → format → templates pattern:
  - Pure transform: `statistics/build/class-intersection-pairs.php` (`count_pairs()` / `top_pairs()`), written test-first — `tests/unit/Statistics/IntersectionPairsTest.php`, registered in `tests/bootstrap.php`.
  - WP glue: new `Taxonomy_Optimized::get_object_term_slug_map()` (single `$wpdb` query, transient-cached for a week, published posts only).
  - Each pairing links to `/shows/?fwp_show_intersectionality=a,b`.
- **Facet logic caveat:** the `show_intersectionality` facet was OR logic, so a pair link ("16 shows") initially opened a 171-show OR result. Resolution: facet flipped to **AND** in FacetWP backend settings (site admin change, not in this repo). If that setting ever reverts, the pairing links become misleading again.

Follow-ups discussed but not built: per-term trend-over-time panel (needs `Ymd` date care), term descriptions in the ranked list, currently-airing counts, re-enabling intersections in nations/stations views (commented out as "not enough data yet").

## Explicitly out of scope (possible follow-ups)

- The `intersectionality` vs `intersections` naming split (stats URL segment vs internal type string) — the same inconsistency that caused the original bug. Worth a rename audit someday, not now.
- Linking the sidebar card header to the stats view at `/statistics/shows/intersectionality/`.

## Risk

Low. Template + debugger only; no scoring, no CPT relationships, no queries changed.
