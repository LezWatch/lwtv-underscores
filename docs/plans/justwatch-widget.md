# JustWatch widget for Ways to Watch

Date: 2026-10-01
Status: Planned
Branch context: work happens on a feature branch; PRs target `production`.

## Goal

On a show page, replace the curated "Ways to Watch" buttons with the JustWatch
Partner Widget when we can identify the show to JustWatch, and keep the curated
links as the fallback everywhere else.

Widget docs: <https://apis.justwatch.com/docs/widget/configuration/> and
[customization](https://apis.justwatch.com/docs/widget/customization/).

## Decisions made with the user

- **Credential:** a JustWatch **Widget** API key, set as `JUSTWATCH_API_KEY` in
  `wp-config.php`. Not the Content Partner API.
- **No server-side cache or scheduled refresh.** The visitor's browser fetches
  the offers and the widget geo-detects the country, so we never hold any data
  to cache. Caching and a refresh task come back only if we move to the
  Content Partner API, which needs a contract and a different token.
- **Full replace:** when the widget renders, the curated links do not.
- **Per-show override:** an editor can force the curated links for a show.
- **Branding:** a generic JustWatch link for now. The widget may already render
  its own (to be checked, see [Open questions](#open-questions)). There is an open
  ask with JustWatch about what they require.

## Scope

**In**

- A pure decision class for widget, curated or nothing, plus the widget
  attributes. Unit-tested, written test-first.
- A new ACF true/false field to force curated links, with its meta key registered.
- Widget rendering and conditional script enqueue in `Theme\Ways_To_Watch`.
- A template gate change so shows with an IMDb ID but no curated links get the
  widget.
- Light and dark theme handling.
- Docs.

**Out**

- The Content Partner API, caching, Action Scheduler tasks, cron.
- Any change to `lez_watch_urls`, `Watch_Hosts`, the provider validators or the
  debugger checks. The curated data and its tooling stay in place as the fallback.
- Detecting "JustWatch has the title but no offers" in PHP. Widget data never
  reaches the server. The override field covers this case.
- Show Score, statistics, REST exports.

## Why the override matters

JustWatch does not know many web series or smaller international shows. For
those, the widget shows "no offers" or "title not found" where our curated links
used to be, and PHP cannot detect that. The override is how an editor puts the
curated links back. Expect to use it more for web series than for network TV.

JustWatch can also disable a widget key without notice (Widget T&C 2.5). Removing
the define restores the curated links site-wide, so the curated data must not be
pruned.

## Decision logic

`LWTV\CPTs\Shows\Watching\JustWatch` in
`plugins/lwtv-plugin/php/cpts/shows/watching/class-justwatch.php`. Pure: no WP
calls, no constants read inside. The WordPress glue reads the inputs and passes
them in.

```php
JustWatch::decide(
	string $api_key,       // '' when JUSTWATCH_API_KEY is undefined or empty
	$imdb,                 // raw lezshows_imdb
	$imdb_canonical,       // raw lezshows_imdb_canonical
	bool $force_curated,   // the override field
	bool $has_curated      // lezshows_waystowatch row count > 0
): array                   // { mode: 'justwatch'|'curated'|'none', imdb: string }
```

Rules, in order:

1. `$force_curated` → `curated` if `$has_curated`, else `none`.
2. Trimmed `$api_key` is `''` → `curated` / `none`.
3. IMDb ID = `Imdb_Canonical::normalise( $imdb_canonical )` if non-empty, else
   `Imdb_Canonical::normalise( $imdb )`.
   - Canonical wins: `Imdb_Verify_Task` writes it when our stored ID still
     redirects, and JustWatch would miss on the old ID.
   - `normalise()` accepts pasted imdb.com URLs and rejects junk.
4. ID is `''`, or is not a `tt` ID (`normalise()` also passes `nm` person IDs)
   → `curated` / `none`.
5. Otherwise → `justwatch` with that ID.

`JustWatch::widget_attributes( string $api_key, string $imdb, string $theme ): array`
returns the `data-*` map, also pure:

| Attribute | Value |
|---|---|
| `data-jw-widget` | `''` (present) |
| `data-api-key` | the key |
| `data-object-type` | `show` |
| `data-id` | the IMDb ID |
| `data-id-type` | `imdb` |
| `data-theme` | `light` or `dark`; anything else falls back to `light` |
| `data-no-offers-message` | i18n string with `{{title}}`, e.g. "We don't know of anywhere streaming {{title}} right now." |
| `data-title-not-found-message` | i18n string |

Message strings go through `__()` with the `lwtv` text domain. The test bootstrap
already shims `__()`.

## Implementation steps

### 1. Tests first: `tests/unit/CPTs/JustWatchTest.php`

Add `require_once` for `class-justwatch.php` to `tests/bootstrap.php` next to the
other `watching/` classes (line ~87). `class-imdb-canonical.php` is already
required.

Cases for `decide()`:

- No key, curated links → `curated`. No key, no links → `none`.
- Whitespace-only key → treated as no key.
- Key, valid `tt` ID → `justwatch`, ID lowercased.
- Key, IMDb URL pasted in the field → `justwatch` with the extracted ID.
- Key, empty ID, curated links → `curated`. Key, empty ID, no links → `none`.
- Key, junk ID → `curated` / `none`.
- Key, `nm` ID (wrong field content) → `curated` / `none`.
- Canonical present and different → canonical ID used.
- Canonical junk, stored ID valid → stored ID used.
- Override on, everything else valid → `curated`. Override on, no links → `none`.

Cases for `widget_attributes()`:

- Fixed attributes present with exact values.
- `dark` → `dark`; `light` → `light`; `''` or `auto` → `light`.

### 2. ACF field and meta registration

- `plugins/lwtv-plugin/acf-json/group_lwtv_shows_details.json`: add right after
  `field_lwtv_lezshows_waystowatch`:
  - key `field_lwtv_lezshows_waystowatch_curated`, name
    `lezshows_waystowatch_curated`, `true_false`, `ui: 1`, default 0
  - label "Always use these links"
  - instructions: "Show the links above instead of the JustWatch widget. Turn on
    when JustWatch doesn't list this show, lists it with no offers, or matches the
    wrong show."
  - Bump the group's `modified` timestamp so ACF sync picks it up.
- `plugins/lwtv-plugin/php/cpts/class-post-meta.php`: register
  `lezshows_waystowatch_curated` for shows with `show_in_rest => false`, matching
  `lezshows_tvmaze_ignore`.

### 3. Rendering: `Theme\Ways_To_Watch`

- `ways_to_watch( $id )` gathers the inputs:
  - `defined( 'JUSTWATCH_API_KEY' ) ? (string) JUSTWATCH_API_KEY : ''`
  - `lezshows_imdb`, `lezshows_imdb_canonical`
  - `lezshows_waystowatch_curated === '1'`
  - the curated row count
- It calls `JustWatch::decide()` and branches:
  - `none` → return `''`.
  - `curated` → today's path, unchanged.
  - `justwatch` → `render_justwatch( $imdb )`.
- `render_justwatch()`:
  - The same icon and "Ways to Watch:" heading as today, so the section looks
    the same either way.
  - The widget `<div>`, each attribute escaped with `esc_attr()`.
  - **Fallback content inside the div:** a plain "Find where to watch on
    JustWatch" link. If the widget script is blocked (ad blockers commonly block
    third-party widgets) or fails, the reader still gets something. **Verify that
    the widget replaces the div's children.** If it appends instead, move the
    fallback to `<noscript>` and accept that blocked-script visitors see nothing.
  - The generic branding link below the widget (`https://www.justwatch.com/`,
    anchor text "JustWatch"), unless step 5 finds the widget already renders one.
  - `wp_enqueue_script( 'justwatch-widget', 'https://widget.justwatch.com/justwatch_widget.js', array(), null, array( 'in_footer' => true, 'strategy' => 'async' ) )`.
    The template part runs in the body, before `wp_footer`, so enqueuing here
    still prints. Only show pages that render the widget load the script.
- On `none`, `ways_to_watch()` returns `''`.

### 4. Template gate: `template-parts/partials/shows/ways-to-watch.php`

Replace the `get_post_meta( …, 'lezshows_waystowatch', true )` early return with
"call `get_ways_to_watch()` and return early if it is `''`". Without this change,
a show with an IMDb ID and no curated rows never opens the section and never gets
the widget. Because the empty output signals `none`, no extra template tag is
needed.

### 5. Light and dark theme

The site uses a user toggle: `bootstrap-color-mode.js` sets `data-bs-theme` on
`<html>` in the `<head>`, before the body renders. So:

- Server-side, render `data-theme="light"`.
- Directly after the widget div and before the async script can run, print a
  small inline script (`wp_add_inline_script( 'justwatch-widget', …, 'before' )`
  or `wp_print_inline_script_tag()`) that copies
  `document.documentElement.getAttribute('data-bs-theme')` into each
  `[data-jw-widget]`'s `data-theme`.
- **Limitation:** switching the theme after the page loads does not re-theme a
  rendered widget. Check whether the widget re-reads `data-theme` on a mutation.
  If it doesn't, live switching needs a reload. That's acceptable; document it
  and move on.

### 6. Styles

`scss/addons/` or wherever `.ways-to-watch-container` is styled today
(`_layout.scss`, `_responsive.scss`, `_colors-dark.scss`). Space the widget and
the branding link, use `rem` for type, and check the branding link's contrast in
both modes with the `colors.$lwtv-*` variables. Rebuild with `npm run buildquick`.

### 7. Docs

- New `docs/integrations/justwatch.md`: the define, the decision rules, the
  override, the theme handling, the no-cache rationale and the open questions.
- `docs/architecture/watch-providers.md`: a short "JustWatch widget" section near
  the top saying the curated pipeline is now the fallback, linking the new doc.
  Note the Decisions line "No HTTP at render time" still holds server-side.
- `docs/README.md` index entry. `CHANGELOG.md` entry.
- Privacy policy (site content, not code): note that show pages load a
  third-party script from JustWatch. Their T&C say the widget collects no
  personal data, but the request still goes to their servers.

## Verification

- `vendor/bin/phpunit --filter JustWatch` green, then the full suite.
- `composer lint`, `npm run lint`.
- On staging with the define set:
  - A network show with an IMDb ID → widget renders, curated links absent,
    branding present.
  - A show with a stale ID and a canonical ID → widget uses the canonical ID
    (inspect `data-id`).
  - A web series with no IMDb ID → curated links unchanged.
  - A show with an IMDb ID and no curated rows → section now appears with the widget.
  - Override on → curated links, no widget, script not enqueued.
  - Dark mode on, reload → `data-theme="dark"`.
  - Ad blocker on → fallback link visible (or the `<noscript>` decision recorded).
  - Non-show pages → `justwatch_widget.js` not loaded.
- On staging with the define removed: every show matches today's output.

## Open questions

1. **Branding:** whether the widget's own output already satisfies the
   "JustWatch link next to every widget" rule, and whether a generic
   `justwatch.com` link is acceptable without the title's `full_path`. The ask is
   open with JustWatch. Until they answer, ship the generic link and remove it
   if the widget's own link turns out to be enough.
2. **Fallback children:** whether the widget replaces or appends to the div's
   existing content (step 3).
3. **Live theme switching:** whether the widget reacts to a `data-theme` change
   (step 5).
4. **Editor worklist (maybe later):** a validator tab listing shows that now
   render the widget *and* have curated links, so editors can spot-check which
   ones need the override. Worth doing if "no offers" turns out to be common
   after launch.
