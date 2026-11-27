# Meilisearch search results page — design

Status: approved for planning. Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Follows `2026-11-26-meilisearch-search-modal-design.md`.

## Why

The header search modal already runs on Meilisearch, behind `LWTV_MEILI_MODAL`. But pressing Enter, or clicking "See all results", still lands on `search.php`, which runs on SearchWP. This project moves that page to Meilisearch too. With the modal and this page both moved, everything SearchWP does in visitor-facing search is replaced; only the leftovers listed under non-goals remain.

## Goals

- `/?s=<query>` shows the same four **tabs with counts** as the modal: Shows, Characters, Actors, Posts. Each tab is a real link with its own pages of results.
- Each result is a **modal-style row** with a **two-line excerpt** and a **bigger image** than the modal's.
- It works fully **without JavaScript**.
- It meets the same accessibility bar as the modal: WCAG 2.1 AA, in light and dark mode.
- It rolls out behind the modal's switch. If Meilisearch fails, the page falls back to SearchWP without anyone noticing.

## Non-goals

- **Search engines.** Search results stay `noindex`. WordPress core adds that through `wp_robots` on search requests. Don't remove it, and don't add canonical or SEO work.
- FacetWP's `search_*` facets, pinned results (Custom Results Order), the redirect, and uninstalling SearchWP (see `docs/runbooks/retiring-searchwp.md`).
- Index changes or reindexing.
- Search logging. It's dropped, and SearchWP's own log is already turned off in `plugins/class-searchwp.php`.

## Anti-goals

- **Running both searches.** The page must not pay for SearchWP's query and Meilisearch's on the same request.
- **Showing a visitor an error** or an empty page because Meilisearch is down.
- **A tab interface only JavaScript can operate.**
- **The indexing key used for searching or printed anywhere.** The server searches with the search key.

## Approaches considered

1. **Meilisearch orders, WordPress renders. Chosen.** One server-side `/multi-search` request returns the four counts and one page of IDs for the selected tab. One `WP_Query` (`post__in`, `orderby => post__in`) loads those posts. Rows use normal WordPress template functions (`get_the_excerpt()`, `get_the_post_thumbnail()` at real theme sizes with `srcset`, symbolicons), so nothing in the index changes. The cost is one extra WordPress query of at most 20 posts.
2. **Render straight from the index documents, like the modal.** Rejected. It would need excerpt and larger-image fields in three indexes, a reindex on every environment, and syncs to keep those excerpts fresh.
3. **Reuse the modal's JavaScript to build the page.** Rejected: the page must work without JavaScript.

## Decided behavior

### URLs and tabs

- **URL:** `/?s=<query>&type=<shows|characters|actors|content>&paged=<n>`.
  - With no `type`, the page opens on the strongest-match tab, using the same rule as the modal: the highest top-hit `_rankingScore`, ties going in tab order, and tabs with zero hits skipped. If every tab has zero hits, it shows the no-results state on Shows.
  - An unknown `type` is treated as no `type`.
  - A `paged` past the last page shows that tab's last page.
- **Tabs** are a labelled `<nav>` holding a list of links, not ARIA `role="tab"` widgets, because each tab loads a new page. The current tab has `aria-current="page"`. Each link's text is the label and the count ("Characters 14"). Tabs keep their place and are never hidden, even at zero.
- **Mobile tabs** follow the modal's rules: at least 44×44 px each, labels never truncated, and the row scrolls sideways if needed.
- The modal's **"See all results"** link adds `&type=<current tab>`. That's a small change in `inc/js/meili-search.js`.

### The query

- **One `POST /multi-search` from PHP**, using the new `Client::multi_search()` method, the **search key**, and a short timeout of about 2 seconds.
  - There's one query per index. Each uses `matchingStrategy: 'frequency'` and `showRankingScore: true`.
  - Each query asks only for `id`. "Also known as" reads alt names from WordPress, not the index.
  - The selected tab's query uses `limit: 20` and `offset: (paged − 1) × 20`.
  - The other three use `limit: 1`. That's enough for their counts and for picking the strongest-match tab.
  - With no `type`, all four use `limit: 20` and offset 0. One request then gives both the pick and the first page. With no `type`, `paged` is ignored and the page shows page 1 of the picked tab. Every link the page and the modal make carries `type`, so this only affects hand-made or old URLs (*builder's-check call*).
- The counts are `estimatedTotalHits`. The page count is `ceil( total / 20 )`.
- The posts load with a single `WP_Query`: `post_type` one of the five (`post_type_shows`, `post_type_characters`, `post_type_actors`, `post`, `page`), `post_status` publish, `post__in` set to the IDs, `orderby` `post__in`, and `posts_per_page` set to the number of IDs.
  - IDs that WordPress no longer returns are skipped. That happens when the index is stale, for example a post was unpublished during a failed sync.
  - The page doesn't re-query to fill the gap. Stale hits are rare, and the runbook's `reindex` fixes them.

### Rows

```
┌──────┐ Alex Danvers                        ← title links to the post
│ img  │ Supergirl · Chyler Leigh            ← detail line (same as the modal)
│      │ Alex is Kara's sister and a DEO …   ← excerpt, two lines max
└──────┘
```

- **Detail line:** the same per-type rule as the modal: list, never pair. Read the values from WordPress, not the index, so they match the post.
  - Shows: years, and the number of characters.
  - Characters: up to 2 shows and the first actor.
  - Actors: up to 2 characters and "N characters".
  - Posts: "News" or "Page", then the month and year.

  The plan decides where these values come from. Reuse the existing helpers that the index classes already call, so the modal and the page agree.
- **"Also known as"** on show rows: exactly what today's live results do. Call `Alt_Names::matching()` on the ACF `lezshows_show_names` rows, with `Languages::all_languages()` and `remove_accents`, and show "Name (Language)". The page gets the language labels, which the modal doesn't have (*builder's-check call*). `Alt_Names` lives under `LWTV\Plugins\SearchWP\Build`. Leave it there for now, but add a line to `docs/runbooks/retiring-searchwp.md`: it must move to `LWTV\Meilisearch\Build` before the SearchWP integration is deleted, because this page now uses it.
- **Excerpt:** `get_the_excerpt()`, trimmed to two lines with CSS (`line-clamp`). The full text stays in the markup for screen readers.
- **Images:** `get_the_post_thumbnail()` with `srcset`/`sizes`. Use `character-img` (350×412) for characters and actors, displayed about 96 px wide, and `postloop-img` (525×300) for shows and posts, displayed about 160 px wide. Both are a little smaller on phones. `alt=""`, because the title names the same thing. Use `loading="lazy"` on every image except the first row's. With no thumbnail, show the same generic placeholder SVG as today's live results.
- **Type icons** keep their screen-reader text, as in the modal.

### Page

- **Heading:** "Search results for: <query>", followed by a count line for the current tab ("14 characters"). It uses `_n()`.
- **Pagination:** numbered (Prev, 1, 2, …, Next) inside a `<nav aria-label>`. The current page has `aria-current="page"`. Links keep `s` and `type`.
- **No results in a tab:** a short message, plus links to the other tabs that do have results.
- **Empty query** (`/?s=`): keep today's behavior, the no-results template.
- **Sidebar card (`searchform.php`):** when the switch is on, drop the SearchWP scope radios and their inline jQuery. The form just submits `s`. When the switch is off, nothing changes.
- **Styles:** a new `scss/addons/_` partial, or the modal's, if it's easier to share the row styles. Colors use `colors.$lwtv-*`, dark-mode overrides go in `_colors-dark.scss`, font sizes are in `rem`, and AA contrast is required in both modes.

### Switch and fallback

- **Same switch: `LWTV_MEILI_MODAL`,** read through `Modal_Switch::enabled()`. When it's on for a request, both the modal and the page use Meilisearch. This is decided, and the constant keeps its name.
- **When the switch is on, SearchWP must not run its own search for the main query.** How to stop it is left to the plan. It could short-circuit the main search query (`posts_pre_query` or similar) or turn off SearchWP's main-query integration for that request. Either way, the plan has to check it live: one search, not two.
- **Redirects and pinned results stop working while the switch is on.** SearchWP Redirects (`searchwp\query\before`) and Custom Results Order (filters on SearchWP's main SQL) only run inside SearchWP's own query, which this page suppresses. This is accepted for now (Mika, 2026-11-26). **`LWTV_MEILI_MODAL` stays at `'editors'` until the redirect and pinned-results steps in `docs/runbooks/retiring-searchwp.md` are done.** The plan adds that gate to the runbook's "Search modal" section, next to the `'everyone'` instructions. The header modal already skips both for the users it covers.
- **Fallback:** if the Meilisearch request fails (WP_Error, non-2xx, or timeout), the page lets the normal SearchWP main query run and renders today's template, unchanged. The failure is logged to the `meilisearch` debug topic. Visitors see no error. Because the fallback needs SearchWP's query, the decision to use Meilisearch has to happen before the main query runs.

### Security and robustness

- `s`, `type` and `paged` are read with sanitization: `get_search_query()`, a whitelist for `type`, and `absint` with a minimum of 1 for `paged`. The query goes to Meilisearch as data in the JSON body, never into a filter string.
- Output escapes with the usual `esc_*` functions. Index text never reaches the page, apart from IDs and the alt names used only for matching.
- With the switch off, or the module unconfigured, the page behaves byte-for-byte as it does today.

## Where things go

- **Plugin, pure code (`meilisearch/build/`), with PHPUnit tests written first:**
  - Building the multi-search request body from the query, type, page and the uid map.
  - Reading the response into counts, the chosen tab, the IDs and the page count.
  - Clamping `paged`.
  - Parsing `type`.
  - Building the pagination links, if that's pure.
- **Plugin, WordPress glue:** `Client::multi_search()`, and a class that runs the search for the main query, shares the results with the template, and blocks SearchWP when it should.
- **Theme:** `search.php` branches when results come from Meilisearch. The tabs, rows and pagination go in new `template-parts/` files. `searchform.php` changes as above. `inc/js/meili-search.js` gets the `&type=` change.
- **Docs:** the search sections of `docs/architecture/meilisearch.md` and `docs/runbooks/meilisearch.md`, plus `docs:structure`.

## Left to the builder

- How SearchWP's main-query search is suppressed, as long as the live check proves only one search runs.
- How results are passed to the template: a global, a query var, or a template-tag accessor.
- The exact image `sizes` attribute and breakpoints, matching the widths above.
- Whether the row styles are shared with the modal.

## Verification

- PHPUnit tests for the `build/` logic, plus `composer lint`, PHPStan, `npm run lint:js` and `npm run lint:css`.
- **Live on lwtv.local with `'editors'`:**
  - `/?s=supergirl` opens on Shows, and `/?s=alex+danvers` on Characters.
  - The counts match the modal.
  - `&type=` and `&paged=` work, and a `paged` past the end lands on the last page.
  - Clicking "See all results" in the modal opens the same tab.
- **One search per page:** show that SearchWP didn't run when Meilisearch answered. Use whatever the plan picks, such as a query count or a SearchWP hook that never fires.
- **With JavaScript disabled:** the tabs, paging and links all work.
- **Keyboard and VoiceOver:** the tabs read as navigation with the current page marked, and the count line is read.
- **At 375 px:** tabs are at least 44 px tall, labels are never truncated, and nothing overflows.
- **Dark mode** contrast passes.
- **Fallback:** with a bad URL from a throwaway filter, the page renders today's SearchWP results with no error, and the failure is logged.
- **Logged out with `'editors'`:** the page is unchanged from today.
- `noindex` is still in the page's `<head>`.
