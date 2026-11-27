# Meilisearch search modal — design

Status: approved for planning. Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749).

## Why

LezWatch.TV is replacing SearchWP with a self-hosted Meilisearch server. The four indexes (`characters`, `shows`, `actors`, `content`) are built, synced and live on lwtv.local and staging. In Search Compare, Meilisearch wins clearly on both relevance and speed. Visitors don't see any of that yet. This project puts Meilisearch behind the header search modal, which is where most people search.

Meilisearch will eventually replace the full results page (`search.php`) too. That is a separate, later project.

## Goals

- The header search button opens a new modal. As the visitor types, it searches all four indexes in one request and shows the results under **tabs with counts**: Shows, Characters, Actors and Posts.
- It's fast: results come straight from the browser to Meilisearch, with no WordPress request per keystroke.
- It's fully accessible to keyboard and screen-reader users, and the tabs are comfortable to tap on phones.
- It sits behind a switch, so editors can try it before visitors see it.
- If Meilisearch is down, visitors never see an error: the old SearchWP modal takes over.

## Non-goals

- The full results page (`search.php`, `?s=`). It stays on SearchWP for now.
- The sidebar search card (`searchform.php`). It's unchanged for now and is handled with the results page.
- Uninstalling SearchWP or its add-ons, or changing their configuration. They stay as the fallback. FacetWP's `search_*` facets, pinned results (Custom Results Order) and the redirect are out of scope (see `docs/runbooks/retiring-searchwp.md`).
- New index fields. The one possible exception is under "Also known as" below.
- Synonyms and ranking tuning.
- Search logging. Modal searches go straight to Meilisearch and no longer reach SearchWP's search log (`wp_searchwp_log`). That's intentional: nobody uses those stats (Mika, 2026-11-26).

## Anti-goals (a failure even if it works)

- Any part of the modal a screen-reader or keyboard user can't operate, or that moves their focus without them asking.
- Visitors seeing an error, an empty modal, or a spinner that never ends because the search server is down.
- Tabs that reorder or disappear while someone is typing.
- The indexing key reaching the browser. Only `LWTV_MEILI_SEARCH_KEY` may be sent there.
- A page-cache leak, where a page cached for editors gives visitors the editor-only modal, or the other way round.

## Approaches considered

1. **Our own modal in plain JavaScript, searching Meilisearch from the browser.** Chosen. It's the fastest, adds no dependencies, and we control every bit of markup, so the accessibility requirements can be met exactly. The cost: result rows are drawn in JS, so they don't reuse `searchwp-live-ajax-search/live-search-results.php`.
2. **A WordPress REST proxy with PHP-rendered rows.** Rejected for the modal. Every keystroke would boot WordPress, which throws away the speed that motivates this work. It's the right shape for the results page later.
3. **Meilisearch's InstantSearch widgets.** Mika's first preference. Rejected because its markup can't be bent to the accessibility rules below, and it's a large dependency that doesn't match the theme's JS.

## Decided behavior

### Layout

```
┌──────────────────────────────────────┐
│ [ supergirl                      ✕ ] │
│ ┌────────┬───────────┬────────┬─────┐│
│ │Shows   │Characters │Actors  │Posts││
│ │  1     │   14      │  9     │ 23  ││
│ └────────┴───────────┴────────┴─────┘│
│ ▢ Alex Danvers                       │
│   Supergirl · Chyler Leigh · regular │
│ ▢ Nia Nal                            │
│   Supergirl · Nicole Maines · regular│
│ …                                    │
│            See all results →         │
└──────────────────────────────────────┘
```

- **Four tabs** in this order: Shows, Characters, Actors, Posts. Posts covers posts and pages (the `content` index). Each tab shows its label and its hit count (`estimatedTotalHits`).
- **Only one list is visible at a time:** the selected tab's results.
- **Up to 10 results per tab.** "See all results" goes to the existing results page (`/?s=<query>`). That page stays on SearchWP until it's replaced.
- **Mobile:** tabs split the full width evenly, at least 44×44 CSS px each, with the count on its own line under the label. If they still don't fit, the tab row scrolls horizontally. Labels are never truncated.

### Which tab is selected

- After each search, the selected tab is the one whose **top hit has the highest `_rankingScore`**, using `showRankingScore: true`. Ties go in tab order: Shows, Characters, Actors, Posts. Tabs with zero hits are never auto-selected.
- **Once the visitor picks a tab themselves** (click, tap, or arrow keys onto it), auto-selection stops for the rest of that modal session. Their tab stays selected as they keep typing. Closing and reopening the modal starts fresh.
- Tabs with zero hits stay in place, show "0", and can still be selected (they show the no-results message). Tabs never reorder or hide.

### Search request

- After typing pauses for about 150 ms, the modal sends **one multi-search request** (`POST /multi-search`, not federated), with one query per index. Each query uses:
  - `limit: 10`
  - `matchingStrategy: 'frequency'`, as Search Compare does (see the characters spec, "Query options")
  - `showRankingScore: true`
  - `attributesToRetrieve` limited to the fields the rows display
- The URL, search key and the four index uids come from WordPress at page load. The uids are `{prefix}_{type}`, built by `Config::index()`.
- An empty or whitespace-only query clears results and counts and sends nothing.
- Responses that arrive out of order are ignored, the same as `fail()` and run tracking in `compare.js`. Only the latest query's response is drawn.

### Result rows

Every row is a link to the document's `url`, with the `thumb` image and the type's icon, as in today's live results. The detail line, per type:

| Tab | Title | Detail line |
| --- | --- | --- |
| Shows | `name` (+ `qualifier` if set) | `years` · number of characters |
| Characters | `name` | up to two `shows` · first of `actors` |
| Actors | `name` | up to two `characters` · "N characters" |
| Posts | `title` | "News" or "Page" · month and year of `date` |

*Builder's-check call:* the index stores shows, actors and characters as separate de-duplicated lists, so it can't reliably pair "this character on this show" or "this role on this show". The detail lines above only list, they never pair. `role` is left off the Characters row because it's the character's best role across all shows, which would read wrong next to a single show. Pairings would need a new index field, which is out of scope.

- **"Also known as"** (shows only): show "Also known as: <names>" using **today's rule**, ported to JS: an alternate name is shown only if it contains every word of the query, compared case- and accent-insensitively. This is `LWTV\Plugins\SearchWP\Build\Alt_Names::matching()`; read it and match its behavior. Don't use Meilisearch's match info for this, because it includes typo matches and, under `frequency`, names that matched only some of the words. `Alt_Names` stays where it is, since SearchWP's live results still use it (*builder's-check call*). Today's line also gives the language ("Cable Girls (English)"). The index doesn't carry languages, so v1 shows names only. Adding `{name, language}` pairs to the shows document is a possible follow-up, not part of this work.
- Text from the index goes into the page as text (`textContent`), never as HTML. If highlighting is used, build it from `_matchesPosition` offsets or a strictly escaped `_formatted` with a custom pre/post tag. Never use `innerHTML` with raw index text.

### Accessibility (WCAG 2.1 AA, light and dark mode)

- **The modal is a dialog:** `role="dialog"`, `aria-modal="true"`, and a label ("Search the site"). Opening it moves focus to the search input. Focus is trapped inside the modal. **Escape** closes it and returns focus to the button that opened it. Background scrolling is locked while it's open.
- **The tabs follow the WAI-ARIA tabs pattern.** `role="tablist"` / `tab` / `tabpanel`, `aria-selected`, `aria-controls`, roving `tabindex`. Left and right arrows move between tabs, and Home and End jump to the first and last. Each tab's accessible name includes its count ("Characters, 14 results").
- **Auto-selection never moves focus.** While the visitor is typing, a strongest-match change updates `aria-selected` and the visible panel only. Focus stays in the input.
- **One polite status message per completed search** (`aria-live="polite"`, not on every keystroke). For example: "14 characters, 1 show, 9 actors, 23 posts. Showing Characters." It also announces "No results for supergirl" when nothing matched, and the fallback message when there is one.
- **The input and results are linked.** Down arrow from the input moves to the first result in the selected panel. Up and Down move through results, Enter opens the selected one, and Up from the first result goes back to the input. The input keeps a real label, not just a placeholder.
- **Selection is shown by more than color.** The selected tab is underlined and bold. Focus rings are always visible. All colors use the `colors.$lwtv-*` SCSS variables and pass AA contrast in both color modes. Dark mode overrides go in `_colors-dark.scss`, nested under `#masthead` as the dark-mode work requires.
- Thumbnails get `alt=""`, because the title next to them names the same thing. Type icons keep screen-reader text, as today.
- With `prefers-reduced-motion`, nothing animates: no open/close transition and no tab-underline slide.
- Font sizes are in `rem`.

### The switch

- A new constant, `LWTV_MEILI_MODAL`, set in `wp-config.php`:
  - unset or `false` — off. Everyone gets the SearchWP modal, as today.
  - `'editors'` — logged-in users who can `edit_posts` get the new modal. Everyone else gets SearchWP.
  - `'everyone'` — everyone gets the new modal.
- It only takes effect when the module is configured, meaning the three required `LWTV_MEILI_*` constants are set. Otherwise it's treated as off.
- **Page caching:** logged-in users already bypass the page cache, so `'editors'` can't leak into cached pages. `'everyone'` produces the same markup for every visitor, so it's cache-safe.
- Turning it on for everyone on production is Mika's call. Production rollout is not part of this work.

### Fallback when Meilisearch is down

- While SearchWP's modal plugin is active, its modal markup and scripts **stay on the page** when the new modal is on. They're hidden, but the code can open them.
- If a search request fails (network error, any non-2xx, or no response within **2 seconds**), the new modal closes and the SearchWP modal opens with the same query in its input. Its live search runs. For the rest of that page view, the header button opens the SearchWP modal.
- The fallback is announced through the status region. Nothing shows the visitor an error.
- If SearchWP's modal isn't available, the new modal shows a short "Search is unavailable right now. Try the search page" message with a link to `/?s=<query>`.
- The failure is logged to the browser console, without the key. Nothing is reported back to WordPress in v1.

## Where things go

- **Plugin** (`plugins/lwtv-plugin/php/meilisearch/`, namespace `LWTV\Meilisearch\`): a front-end class that decides whether the modal is on for this request, and that prints the browser config (URL, search key, index uids, strings) as a JSON blob. Pass numbers as real JSON numbers: `wp_localize_script` turns them into strings, which caused the `invalid_search_limit` bug. The pure decision (switch value + configured + can edit → on/off) goes in `build/` and is unit-tested test-first. The "Also known as" rule is JS (see Result rows). There's no JS test harness, so check it by hand against the `Alt_Names` PHPUnit cases.
- **Theme:**
  - The trigger and modal markup go in a new `template-parts/header/searchbox-meili.php`. `navbar.php` picks between it and `searchbox-searchwp.php` in both places it renders a trigger: bar mode and drawer mode.
  - The script goes in `inc/js/`. Use a script module if that fits the existing `wp_enqueue_script_module` pattern. No jQuery and no new npm dependencies.
  - Styles go in a new `scss/addons/_` partial, plus the dark-mode overrides.
- **i18n:** every string goes through `__()` with the `lwtv-underscores` text domain, and pluralized counts use `_n()`. The JS gets its strings from the config blob, including singular and plural forms for each type.
- **Docs:** update `docs/architecture/meilisearch.md` and add a "Search modal" section to `docs/runbooks/meilisearch.md` (the switch, the fallback, how to tell which modal you're seeing). Regenerate `docs:structure`.

## Left to the builder

- The exact debounce value (about 150 ms) and the open and close details.
- How to open the SearchWP modal from code. It uses MicroModal (`assets/dist/searchwp-modal-form.js`), so either click its hidden trigger or call its API, whichever is more robust.
- Thumbnail size and the exact row CSS, matching today's live results where possible.
- Whether the strongest-match pick and status-message building are factored into small pure JS functions. Recommended, even though the repo has no JS test harness, so they can be checked by hand in the console.

## Verification

- PHPUnit tests for the `build/` logic, plus `composer lint`, `TMPDIR=<scratch> composer analyse`, `npm run lint:js` and `npm run lint:css`.
- Live on lwtv.local, with `LWTV_MEILI_MODAL` set to `'editors'` and the current user able to edit:
  - "supergirl" opens on Shows, "alex danvers" on Characters, and a post title on Posts.
  - The counts match Search Compare.
  - Picking a tab stops auto-switching.
- **Keyboard only:** open, type, move through the tabs, move through the results, open one, and use Escape. Focus never jumps on its own.
- **VoiceOver (Safari):** tab names with counts, one status message per search, and a sensible order.
- **Mobile width (375 px):** tabs are at least 44 px tall, nothing is truncated, and nothing overflows the page.
- **Dark mode:** contrast passes for the tabs, rows and focus rings.
- **Fallback:** block the search host (a bad URL in the config, or the browser's network tools). The SearchWP modal opens with the query, and no error is shown.
- **Logged-out visitor with `'editors'` set:** gets the SearchWP modal, unchanged.
