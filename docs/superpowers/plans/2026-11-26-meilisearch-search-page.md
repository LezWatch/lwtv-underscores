# Meilisearch Search Results Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When `LWTV_MEILI_MODAL` is on, `/?s=` is served from Meilisearch. The page has four tabs with counts. Each tab is a link with its own pages. Results are modal-style rows with excerpts and bigger images. If Meilisearch fails, the page falls back to SearchWP without any visible error.

**Architecture:** The plugin hooks the main search query before SearchWP does (`pre_get_posts`, priority below 0). It sends one server-side `/multi-search` with the **search key**. If that works, it tells SearchWP to skip this query (`searchwp\native\short_circuit`) and hands the main query the ordered posts (`posts_pre_query`). `search.php` then renders tabs, rows and pagination from a template tag. If the request fails, none of those filters are added, so SearchWP runs as it does today. The request body and response parsing are pure `build/` classes, unit-tested.

**Tech Stack:** PHP 8.1+ / WordPress 6.5+, PHPUnit (pure only, no WP bootstrap), theme templates + Dart Sass, Meilisearch v1.57 `/multi-search`, SearchWP 4 native hooks.

**Spec:** `docs/superpowers/specs/2026-11-26-meilisearch-search-page-design.md`. Read it with the modal spec `2026-11-26-meilisearch-search-modal-design.md`, whose row rules this page reuses.

## Global Constraints

- **No commits** without Mika's explicit approval in that turn. Leave the work uncommitted in the main checkout (`/Users/ipstenu/Development/lezwatch/lwtv-underscores`, `release/8.0.0`). A hook blocks Edit/Write there, so build in a worktree and copy across with a script.
- **The server searches with `Config::search_key()`.** The indexing key has no `search` action. Never log or print either key.
- **Don't change SearchWP or its add-ons, their templates, or their configuration.**
- **Switch:** `LWTV_MEILI_MODAL`, read only through `Search_Modal::enabled()`, the same decision the modal uses. When it's off or the module isn't configured, the page stays byte-for-byte as it is today.
- **Tab order and types:** `shows`, `characters`, `actors`, `content` (`Modal_Config::TABS`). Each query uses `matchingStrategy: 'frequency'` and `showRankingScore: true`.
- **Strongest-match rule** (same as the modal): the highest top-hit `_rankingScore` wins, ties go in tab order, and zero-hit tabs are skipped. If every tab has zero hits, use `shows`.
- **Paging:** 20 per page. With no `type`, `paged` is ignored. A `paged` past the last page shows the last page.
- **Post types loaded:** `post_type_shows`, `post_type_characters`, `post_type_actors`, `post`, `page`. Status is `publish` only.
- **Request timeout:** 2 seconds.
- **Images:**
  - Characters and actors use `character-img`, shown about 96 px wide.
  - Shows and posts use `postloop-img`, shown about 160 px wide.
  - All images have `srcset`, `alt=""`, and `loading="lazy"` except the first row.
  - With no thumbnail, use the live-results placeholder SVG.
- **"Also known as":** `Alt_Names::matching( get_field( 'lezshows_show_names', $id ), $q, ( new \LWTV\Features\Languages() )->all_languages(), 'remove_accents' )`. Format it exactly as `live-search-results.php` does.
- **Strings and styles:** i18n with the `'lwtv-underscores'` text domain. Font sizes in `rem`. Colors from `colors.$lwtv-*`. Dark overrides go in `_colors-dark.scss`. AA contrast in both modes.
- **Accessibility:**
  - Tabs are a `<nav aria-label>` list of links. The current tab has `aria-current="page"`. Each tab is at least 44×44 px, and no label is truncated.
  - Pagination is a `<nav aria-label>` with `aria-current="page"`.
- `noindex` stays on search pages. That comes from WordPress core (`wp_robots`), so don't touch it.
- **Out of scope:** index changes and reindexing, FacetWP, pins and redirects (accepted as off while the switch is on), and moving `Alt_Names`.

## Review Focus

1. **Both searches running.** When Meilisearch answers, SearchWP's native search and core's `LIKE` query must not run as well. Pinned in Task 3 by a live `did_action( 'searchwp\query\before' ) === 0` check and a `SAVEQUERIES` count.
2. **`paged` past the end, and a tab with 0 hits.** `/?s=supergirl&type=shows&paged=9` must show the last page, not an empty page. `&type=actors` with 0 actors must show the no-results message plus links to tabs that have hits. Pinned in Task 1 (`Search_Results` cases) and the Task 5 live checks.
3. **Stale IDs.** Meilisearch returns an ID that WordPress doesn't (unpublished, deleted). It's skipped and the page still renders. Pinned in Task 3 by a live check with a fake ID injected through the request filter.
4. **Hostile input.** `s` containing quotes or HTML, `type=<script>`, `paged=-5` and `paged=abc` must be sanitized, escaped and clamped. Pinned in Task 1 (`Search_Params` cases) and a Task 5 live check.
5. **Other queries untouched.** Admin search, feeds (`/?s=x&feed=rss2`), REST, the SearchWP live-ajax request and secondary `WP_Query`s must not be intercepted. Pinned in the Task 3 guard list and a Task 5 live check of the admin Posts search and the feed.

---

### Task 1: Pure request, params and results

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-search-params.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-search-request.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-search-results.php`
- Modify: `tests/bootstrap.php` (three `require_once` lines after the modal ones)
- Test: `tests/unit/Meilisearch/SearchParamsTest.php`, `SearchRequestTest.php`, `SearchResultsTest.php`

**Interfaces:**
- Produces:
  - `Search_Params::type( mixed $raw, array $types ): ?string` returns a known type or `null`. It trims and lowercases. Arrays and unknown values return `null`.
  - `Search_Params::paged( mixed $raw ): int` returns at least 1. Non-numeric, negative and array values give 1.
  - `Search_Request::PER_PAGE = 20`.
  - `Search_Request::body( string $q, ?string $type, int $paged, array $uids ): array` takes `$uids` as type → uid in tab order and returns `{ queries: [...] }`. Each query is `{ indexUid, q, matchingStrategy: 'frequency', showRankingScore: true, attributesToRetrieve: [ 'id' ], limit, offset }`.
    - With a `$type`, that tab gets `limit 20` and `offset ( paged - 1 ) * 20`. The other tabs get `limit 1, offset 0`.
    - With no `$type`, every tab gets `limit 20, offset 0`.
  - `Search_Results::parse( array $response, array $types, ?string $type, int $paged ): array` returns `{ counts: type=>int, type: string, ids: int[], page: int, pages: int, retry_offset: ?int }`.
    - `type` is the given type, or the strongest match when `$type` is null.
    - `ids` holds the chosen tab's hit IDs, in order.
    - `pages` is `max( 1, ceil( total / 20 ) )`.
    - `page` is `min( paged, pages )`, or 1 when `$type` is null.
    - `retry_offset` is set to `( pages - 1 ) * 20` only when the chosen tab has `total > 0` but returned no hits because the offset was past the end. Otherwise it's `null`.
  - `Search_Results::retry_body( string $q, string $type, string $uid, int $offset ): array` builds a one-query body for that retry.

- [ ] **Step 1: Write the failing tests.** Each case below is its own test method with exact `assertSame` values:
  - **Params:**
    - `type( 'Characters', TYPES )` → `'characters'`
    - `type( 'x<script>', TYPES )` → `null`
    - `type( array( 'shows' ), TYPES )` → `null`
    - `paged( '3' )` → 3
    - `paged( '-5' )` → 1
    - `paged( 'abc' )` → 1
    - `paged( null )` → 1
  - **Request:**
    - The body for `type=characters`, `paged=3` has the characters query at `limit 20, offset 40` and the others at `limit 1, offset 0`.
    - With no type, all four queries have `limit 20, offset 0`.
    - The order of `indexUid` values follows `$uids`.
    - Every query has `attributesToRetrieve === [ 'id' ]`.
    - `json_encode` of the body keeps `limit` and `offset` as ints.
  - **Results:**
    - The strongest match picks `characters` when its top score is higher.
    - A tie picks `shows`, by tab order.
    - All zeros give `shows`.
    - Counts come from `estimatedTotalHits`.
    - `type=shows`, `paged=9` with a total of 25 and no hits gives `page 2`, `pages 2` and `retry_offset 20`.
    - `type=shows`, `paged=2` with a total of 25 and 5 hits gives `retry_offset null` and `ids` in hit order.
    - No type with `paged=4` gives `page 1`.
    - A response missing `results`, or with the wrong count of results, throws `\UnexpectedValueException`. The glue treats that as a failure and falls back.

- [ ] **Step 2:** Run `vendor/bin/phpunit --filter 'SearchParams|SearchRequest|SearchResults'`. Expected: errors, because the classes are not found.

- [ ] **Step 3:** Implement the three classes in the `class-type-option.php` style: an ABSPATH guard, a header docblock and static methods. They take no WordPress calls.

- [ ] **Step 4:** Run `vendor/bin/phpunit`. Expected: the whole suite is green.

---

### Task 2: `Client::multi_search()` with the search key

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-client.php`

**Interfaces:**
- Produces: `Client::multi_search( array $body, int $timeout = 2 ): array|\WP_Error` posts `/multi-search` with `Authorization: Bearer <search key>`. A non-2xx response, an undecodable body, or any transport error becomes a `WP_Error` whose message comes from `describe_error()`. Refactor `request()` to take the key to use, with the indexing key as the default, so the existing callers don't change.

- [ ] **Step 1: Implement**, keeping the existing `request()` behavior for every current caller.

- [ ] **Step 2: Live check on lwtv.local.** Use the wp-cli wrapper from the background.
  - Run `wp eval '$c=new LWTV\Meilisearch\Config(LWTV_MEILI_URL,LWTV_MEILI_INDEX_KEY,LWTV_MEILI_SEARCH_KEY,LWTV_MEILI_INDEX_PREFIX); $r=(new LWTV\Meilisearch\Client($c))->multi_search(LWTV\Meilisearch\Build\Search_Request::body("supergirl",null,1,array("shows"=>"local_shows","characters"=>"local_characters","actors"=>"local_actors","content"=>"local_content"))); echo is_wp_error($r)?$r->get_error_message():count($r["results"]);'`. Expected: `4`.
  - Repeat with `local_nope` for one uid. Expected: a `WP_Error` message containing `index_not_found`, with no key in it.

- [ ] **Step 3:** Run `composer lint` and `TMPDIR=<scratch> composer analyse`. Expected: clean.

---

### Task 3: Main-query glue: `Search_Page`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-page.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-meilisearch.php`. Construct `Search_Page` next to `Search_Modal`, register its hooks inside `init()` only when configured, and add the template tag.
- Modify: `plugins/lwtv-plugin/php/class-plugin.php`. Add a `@method` line for the new tag, the same way the modal's tags were added.

**Interfaces:**
- Consumes: `Search_Modal::enabled()`, `Indexes::get( $type )`, `Index::uid()`, `Index::documents( int[] $ids )`, and everything from Tasks 1 and 2.
- Produces:
  - Template tag `lwtv_plugin()->meili_search_results(): ?array`. It returns `null` when the page isn't served from Meilisearch on this request. Otherwise it returns `{ q, type, counts, page, pages, rows }`, where `rows` lists, in order, `{ post: WP_Post, type, document: array }` and `document` is `Index::documents()` output for that post. That gives the detail line the same fields the modal shows, built from fresh WordPress data.
  - Debug filters for the live checks: `lwtv_meili_search_request` (array body → array body), applied before sending, and `lwtv_meili_search_ids` (int[] → int[]), applied to the chosen tab's IDs before `get_posts()`. Both are documented as debugging hooks.

- [ ] **Step 1: Implement the hooks**

  `add_action( 'pre_get_posts', array( $this, 'maybe_search' ), -10 )`. That runs before SearchWP's `Native::init` at priority 0. `maybe_search( WP_Query $q )` returns early unless all of these hold:
  - `$q->is_main_query()`
  - `$q->is_search()`
  - `! is_admin()`
  - `! $q->is_feed()`
  - `! wp_doing_ajax()`
  - `! ( defined( 'REST_REQUEST' ) && REST_REQUEST )`
  - `'' !== trim( get_search_query( false ) )`
  - `enabled()`

  Then it runs these steps:
  1. Read `$type = Search_Params::type( $_GET['type'] ?? null, TABS )` (sanitize with `wp_unslash` and `sanitize_key`) and `$paged = Search_Params::paged( get_query_var( 'paged' ) ?: ( $_GET['paged'] ?? 1 ) )`.
  2. Build the body, pass it through the filter, and call `multi_search()`. If the result is a `WP_Error` or `parse()` throws, call `debug_log( 'meilisearch', … )` and return. That's the fallback: nothing else is hooked.
  3. If `retry_offset` is set, send one more request with `retry_body()` and use its `ids`. If that fails, fall back the same way.
  4. Pass the IDs through `lwtv_meili_search_ids`, then run `get_posts( array( 'post_type' => FIVE_TYPES, 'post_status' => 'publish', 'post__in' => $ids ?: array( 0 ), 'orderby' => 'post__in', 'posts_per_page' => count( $ids ) ?: 1, 'suppress_filters' => true, 'ignore_sticky_posts' => true ) )`. Skip any ID that's missing.
  5. Build the document per row with one `Index::documents()` call for the tab.
  6. Store the result on the instance.
  7. Add `searchwp\native\short_circuit`, returning true only for this `$q` (compare with `===`).
  8. Add `posts_pre_query` at priority 1000, again only for this `$q`. It returns the loaded posts and sets `$q->found_posts = counts[type]` and `$q->max_num_pages = pages`. Remove both filters after they fire once.

- [ ] **Step 2: Live checks on lwtv.local.** Set `LWTV_MEILI_MODAL` to `'editors'`; it's already there. Run these logged in through the built-in browser, or by curl with the editor cookie.
  - **One search** (Review Focus 1). With a throwaway mu-plugin that writes `did_action( 'searchwp\query\before' )` and `get_num_queries()` to the debug log at `shutdown`, loading `/?s=supergirl` gives `did_action` of 0.
  - **SearchWP still runs when it should.** Logged out, the same page gives `did_action` ≥ 1.
  - **Stale ID** (Review Focus 3). A throwaway mu-plugin uses `lwtv_meili_search_ids` to put `999999999` first. The page renders every other row, with no warning or notice in the PHP log.
  - **Fallback.** Use `lwtv_meili_search_request` to rename a uid to `local_nope`. The page renders today's SearchWP card grid, and the `meilisearch` debug log has the line.
  - Remove the throwaway mu-plugins.

- [ ] **Step 3:** Run `composer lint` and PHPStan. Expected: clean.

---

### Task 4: Theme: page, rows, tabs, pagination, sidebar, modal link

**Files:**
- Modify: `search.php`. When `$results = lwtv_plugin()->meili_search_results()`, render the new parts. Otherwise use the existing markup, unchanged.
- Create: `template-parts/search/meili-tabs.php`, `meili-row.php`, `meili-pagination.php`
- Modify: `searchform.php`. When `lwtv_plugin()->meili_modal_enabled()`, skip the scope radios, the hidden `lwtv_scope` field and the inline jQuery.
- Modify: `inc/js/meili-search.js`. The "See all results" URL adds `&type=` for the selected tab, in both the panel link and `searchUrl()`.
- Create: `scss/addons/_meili-search-page.scss`. Load it in `style.scss` next to `meili-search`. Share variables with the modal partial where they exist.
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: `meili_search_results()` from Task 3. `$results['rows'][n]['document']` fields match the modal's `FIELDS` (shows: `years`, `characters`; characters: `shows`, `actors`; actors: `characters`, `character_count`; content: `type`, `date`).

- [ ] **Step 1: Tabs.** `<nav aria-label="Search results by type"><ul>`, with one `<li><a>` per tab, linking to `add_query_arg( array( 's' => q, 'type' => t ), home_url( '/' ) )` and escaped with `esc_url`. The current tab has `aria-current="page"`. The link text is the label, plus the count in its own span. Use `number_format_i18n`.

- [ ] **Step 2: Heading.** "Search results for: <q>" (keep the existing h1 markup and the search icon). Below it, a count line built with `_n()` per type, for example "14 characters".

- [ ] **Step 3: Rows.** A `<ol class="lwtv-meili-results">` of rows:
  - The image is `get_the_post_thumbnail( $post, SIZE, array( 'alt' => '', 'loading' => first ? 'eager' : 'lazy', 'sizes' => … ) )`. With no thumbnail, use the placeholder SVG from `live-search-results.php`.
  - The type icon uses the same symbolicons and screen-reader text as the modal.
  - The title is an `<a>` to `get_permalink`.
  - The detail line follows the modal's per-type rule: list, never pair.
  - "Also known as" appears on shows, built from the Global Constraints call.
  - The excerpt uses `get_the_excerpt( $post )` inside `<p class="… line-clamp-2">`.
  - All output is escaped with `esc_html`, `esc_url` or `esc_attr`.

- [ ] **Step 4: No results in a tab.** Show a message ("No characters match supergirl.") and links to the tabs that have hits. If there are no hits anywhere, include the existing `template-parts/content/none`.

- [ ] **Step 5: Pagination.** Use `paginate_links( array( 'base' => add_query_arg( array( 's' => q, 'type' => type, 'paged' => '%#%' ), home_url( '/' ) ), 'format' => '', 'current' => page, 'total' => pages, 'type' => 'list' ) )` inside `<nav aria-label="Search results pages">`. Add `aria-current` if `paginate_links` doesn't already. It does in 6.5+, so verify. Show nothing when `pages < 2`.

- [ ] **Step 6: Styles.**
  - Tabs reuse the modal's 44 px rules, and the row scrolls sideways if it has to.
  - Rows use a flex layout. The image column is a fixed `6rem` for portrait images and `10rem` for landscape, and a little smaller below `sm`.
  - `line-clamp: 2` with the `-webkit-` prefix.
  - Focus rings are visible.
  - Under `prefers-reduced-motion`, nothing animates.
  - Build with `nvm use && npm run buildquick`.

- [ ] **Step 7: Lint.** Run `npm run fix:js && npm run lint:js && npm run lint:css && composer lint`. Expected: clean.

---

### Task 5: Live verification, docs

**Files:**
- Modify: `docs/runbooks/meilisearch.md`. Add a "Search results page" section covering what the switch now covers, how to tell which page you're on, and the fallback. Add the **gate** next to the `'everyone'` instructions: keep `'editors'` until the redirect and pinned-results steps in `retiring-searchwp.md` are done.
- Modify: `docs/runbooks/retiring-searchwp.md`.
  - Note that, while the switch is on, the redirect and the pins no longer apply to the results page or the modal.
  - Add a step to move `LWTV\Plugins\SearchWP\Build\Alt_Names` (and its test) to `LWTV\Meilisearch\Build` before the SearchWP integration is deleted.
- Modify: `docs/architecture/meilisearch.md`. Add a "Search results page" section covering the hook order, the short-circuit, the template tag, the debug filter and the fallback.
- Regenerate with `npm run docs:structure`.

- [ ] **Step 1: Browser checks on lwtv.local** with the built-in browser, logged in as an editor. Each check passes or fails.
  - **Tab choice and counts.** `/?s=supergirl` opens on Shows, and `/?s=alex+danvers` opens on Characters. The counts match the modal's for the same query.
  - **Paging.** `&type=characters&paged=2` shows results 21–40. `&type=shows&paged=9` shows the last page (Review Focus 2).
  - **Empty tab.** A query with 0 actors shows the empty-tab message and links to the other tabs.
  - **"See all results"** in the modal opens on the tab you were on.
  - **No JavaScript.** With JS disabled (`javascript_tool` can't do this, so check the page source with curl instead), the tab links, pagination and result links are all plain `<a href>`s.
  - **Hostile input** (Review Focus 4). `/?s="><img src=x onerror=alert(1)>` renders as text. `&type=<script>` falls back to the strongest match. `&paged=-5` gives page 1.
  - **Other queries untouched** (Review Focus 5). The admin Posts search still works through the normal admin search. `/?s=supergirl&feed=rss2` returns the usual feed.
  - **Accessibility tree.** `read_page` shows a navigation landmark with the current tab marked, and pagination with the current page marked.
  - **Phone width.** At 375 px, every tab is at least 44 px tall, no label is ellipsized, the page has no horizontal scroll, and the images are the smaller size.
  - **Dark mode.** Tab text, the underline, rows and excerpt all meet AA contrast.
  - **`noindex`** is present in `<head>`.
  - **Logged out** with `'editors'`, the page is identical to before, still SearchWP's card grid.
  - **Sidebar card.** With the switch on, the card has no scope radios. Logged out, it still has them.

- [ ] **Step 2: Full gate.** Run `vendor/bin/phpunit && composer lint && TMPDIR=<scratch> composer analyse && npm run lint:js && npm run lint:css && npm run docs:structure:check`. Expected: everything green.

- [ ] **Step 3: Hand over.** Leave everything uncommitted in the main checkout. Report the result of each check, with evidence, plus every ruling made along the way.
