# Design: "Most Clichéd Characters" Statistics View

**Date:** 2026-07-14
**Status:** Approved
**Area:** `plugins/lwtv-plugin/php/statistics/`

## Problem

`/statistics/characters/cliches/` currently shows **cliché popularity** — for each cliché
term, how many characters carry it (a pie chart + percentage breakdown). This groups the
`lez_cliches` taxonomy *by term*.

We want the inverse: rank **characters** by how many clichés each one carries — a leaderboard
of the most clichéd characters. This groups `lez_cliches` *by character (object)*.

## Requirements

- Lives as a **new tab** under `/statistics/characters/`, separate from the existing clichés page.
- Displays **both** a bar chart (top 20) and a ranked table.
- Scope is **top 20 characters**, but **ties at the cutoff are included** (if characters tie at
  the 20th-place count, keep them all — list may run slightly over 20).
- Table rows show **Rank / Character (linked) / number of clichés** — count only, no per-character
  cliché name listing.
- Does not modify the existing clichés popularity page.

## Design

### 1. Route & navigation

No new rewrite rule is required. `class-query-vars.php` already registers
`^statistics/characters/([^/]+)/?$` → `view=$matches[1]`, so any sub-view under `characters/`
routes to the characters template.

Changes in `templates/characters.php`:

- Add `'most-cliches'` to the `$valid_views` array.
- Add a `case 'most-cliches':` to the view `switch` that includes `characters/most-cliches.php`.

The navbar (`templates/characters/navbar.php`) auto-generates its tabs from `$valid_views`,
uppercasing the slug and replacing dashes with spaces. The new tab renders as **MOST CLICHES**.

New URL: `/statistics/characters/most-cliches/`.

### 2. Data layer — inverse query

New build class: `plugins/lwtv-plugin/php/statistics/build/class-cliche-leaders.php`

- Namespace: `LWTV\Statistics\Build`
- Class: `Cliche_Leaders`
- Method: `generate()`

Query (single `$wpdb` prepared query):

```sql
SELECT p.ID, p.post_title, COUNT(tr.term_taxonomy_id) AS cliche_count
FROM {posts} p
INNER JOIN {term_relationships} tr ON p.ID = tr.object_id
INNER JOIN {term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
WHERE tt.taxonomy = 'lez_cliches'
  AND p.post_type = 'post_type_characters'
  AND p.post_status = 'publish'
GROUP BY p.ID
ORDER BY cliche_count DESC
```

**Top-20-with-ties logic (in PHP):** read the `cliche_count` at index 19 (the 20th row). Keep
every character whose count is `>=` that threshold. This includes all boundary ties; the result
may run 20–23 rows.

Return shape — array keyed by character ID:

```php
[
  <char_id> => [
    'name'  => <post_title>,
    'count' => <cliche_count>,
    'url'   => get_permalink( <char_id> ),
  ],
  ...
]
```

This `name` + `count` shape is exactly what `Format\Barcharts_Optimized::format()` consumes, so
the chart needs no special handling.

**Caching:** store the result in a transient for `WEEK_IN_SECONDS`, matching every other stats
build class. Use `lwtv_plugin()->get_transient()` / `set_transient()`.

### 3. Generator wiring

In `class-stats-generator.php::generate_characters()`, add:

```php
case 'most-cliches':
    $all_data = ( new Build_Cliche_Leaders() )->generate();
    break;
```

The result flows through the existing `Stats_Handler` for the `barchart` format
(`vertical` bar direction, consistent with other character stats).

### 4. Template — chart + table

New template: `plugins/lwtv-plugin/php/statistics/templates/characters/most-cliches.php`

- `<h3>Characters With the Most Clichés</h3>`
- Bar chart: `lwtv_plugin()->generate_characters_statistics( 'barchart', 'most-cliches' )`
- Ranked table below the chart:
  - Columns: Rank, Character (linked via the row's `url`), # of Clichés.
  - Built by instantiating `Cliche_Leaders` directly in the template — the same pattern
    `templates/characters.php` uses when it instantiates `Build_Taxonomy_Optimized` directly.
    The transient cache makes this second call free.
- One shared dataset (top-20 + ties) feeds both chart and table, so they always agree.

All user-facing strings are i18n-ready with the `lwtv` text domain.

## Out of Scope (YAGNI)

- No per-character cliché name listing (count only).
- No changes to the existing clichés popularity page.
- No overview-page tile or summary widget.

## Files Touched

| File | Change |
|------|--------|
| `statistics/build/class-cliche-leaders.php` | **New** — inverse query + top-20-with-ties |
| `statistics/class-stats-generator.php` | Add `most-cliches` case to `generate_characters()` |
| `statistics/templates/characters.php` | Add view to `$valid_views` + `switch` case |
| `statistics/templates/characters/most-cliches.php` | **New** — chart + ranked table |

## Verification

- Visit `/statistics/characters/most-cliches/` on the local site (`https://lwtv.local/`) and
  confirm the tab appears, the bar chart renders, and the table lists ranked characters.
- Spot-check the top character's count against its actual assigned clichés.
- Confirm the existing `/statistics/characters/cliches/` page is unchanged.
- `composer lint` passes.
