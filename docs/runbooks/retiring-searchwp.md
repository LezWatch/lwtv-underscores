# Retiring SearchWP

* Replaced by: Meilisearch (see [meilisearch.md](meilisearch.md))
* Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749)
* Status (2026-11-25): **SearchWP is live.** Meilisearch has a characters index and a shows index in progress. Nothing below the "Now" section should be done yet.

SearchWP indexes ~15,000 posts into its own MySQL tables (`wp_searchwp_index` alone is ~620,000 rows, 175 MB on a copy of production). Its documented comfortable ceiling is around 20,000 posts, and the site adds 10 to 50 a day. This runbook is the order of operations for getting off it without breaking search for a single visitor.

## What SearchWP does for us today

Everything that must be replaced, or consciously dropped, before SearchWP can go.

| Piece | Where | What it does |
| --- | --- | --- |
| **SearchWP** (`searchwp/index.php`) | Plugin | Four engines: `default` (posts, pages, actors, characters, shows), `shows`, `characters`, `actors`. Stemming on. |
| **Modal Search Form** | Plugin + `searchwp-modal-form/lwtv.php`, `template-parts/header/searchbox-searchwp.php`, `template-parts/header/navbar.php`, `header.php` | The header search overlay, with the Shows / Characters / Actors scope picker. |
| **Live Ajax Search** | Plugin + `searchwp-live-ajax-search/live-search-results.php` | Results as you type inside the modal, including the "also known as" line (`LWTV\Meilisearch\Build\Alt_Names`, shared with the Meilisearch results page). |
| **Scope switch** | `plugins/lwtv-plugin/php/plugins/class-searchwp.php` (`maybe_switch_engine`), `searchform.php` | The sidebar search's `lwtv_scope` picks an engine. |
| **FacetWP search facets** | FacetWP settings (database, not code) | `search_shows`, `search_characters` and `search_actors` use engines `swp_shows`, `swp_characters`, `swp_actors`. **Easy to miss: nothing in the repo mentions them.** |
| **Custom Results Order** | Plugin | 22 posts pinned to the top for particular searches. |
| **Redirects** | Plugin | Search redirects (`searchwp_redirects` option): 8 on lwtv.local. Dropped, see below. |
| **`search.php`** | Theme | The full results page. It is a normal WordPress loop; SearchWP supplies the results behind it. |
| Styles | `scss/addons/_searchwp.scss`, `.searchwp-*` rules in `scss/partials/_colors-dark.scss`, loaded from `style.scss` | Modal and live-search look, light and dark. |

## Now: trim the engines (safe today)

These make SearchWP's index smaller while it is still live. Each is reversible from the SearchWP settings screen.

What's **not** there: no engine indexes custom fields (meta), so there are no wildcard meta rules to remove. The index is already lean on that front.

### 1. Drop `author` from every source

Every source in every engine indexes **author** (the editor's display name) at weight 1. Nobody searches for which editor wrote a character page, and it can make an editor's name match unrelated posts.

1. WP Admin → SearchWP → Algorithm.
2. For each engine (`default`, `shows`, `characters`, `actors`), open each source and untick **Author**.
3. Save. SearchWP rebuilds the index, so do this when traffic is low. Watch progress on the same screen.

### 2. Optional: stop character descriptions outranking names

This is ranking tuning, not trimming; only do it if you want SearchWP to behave better until it's retired. In the `characters` engine, **Content** is weighted 300, the same as **Title** and **Slug**. That's why a search for "sarah" puts a character whose write-up mentions a Sarah above characters named Sarah. Lowering Content to a small weight (or Excerpt and Content both) would push name matches up. Test a few searches before and after on lwtv.local first.

Leave **Slug** alone: it duplicates the title, so removing it would halve the title's weight relative to content and make ranking worse, not better.

## Before cutover: what Meilisearch needs first

Don't switch anything off until every line here is done and checked on lwtv.local and staging.

- [ ] **All four indexes** (`characters`, `shows`, `actors`, `content` for posts and pages) built and syncing on each environment. See [meilisearch.md](meilisearch.md).
- [ ] **Search UI replacement** for the modal and live search: Meilisearch's browser search with the search key, the same Shows / Characters / Actors scope picker, highlighting, and the "also known as" line for alternate show names. Search requests use `matchingStrategy: "frequency"` (decided in the characters design).
- [ ] **Full results page**: `search.php` needs results from Meilisearch (or a deliberate fallback to native WordPress search). Built behind `LWTV_MEILI_MODAL`: see [meilisearch.md](meilisearch.md#search-results-page). It falls back to SearchWP when Meilisearch fails.
- [ ] **FacetWP search facets**: switch `search_shows`, `search_characters` and `search_actors` to their Meilisearch engines ("Meilisearch – Shows" and so on) in FacetWP → Facets. The engines exist in code: see [meilisearch.md](meilisearch.md#facetwp-keyword-facets). Check every archive that shows those facets. Until `LWTV_MEILI_MODAL` covers everyone, visitors still get SearchWP through them.
- [ ] **While `LWTV_MEILI_MODAL` is on**, the redirect and the pins below no longer apply to the results page or the header modal for the people it covers: both only run inside SearchWP's own query, which the Meilisearch page and modal skip. That's why the switch stays at `'editors'` until the next two steps are done (Mika, 2026-11-26). The pins and redirects are now settled (both dropped), so nothing in this list blocks `'everyone'` any more.
- [x] **Pinned results**: **dropped, nothing to port** (Mika, 2026-11-26). The 22 pins covered four searches on the default engine, all set to exact match (the terms are in the `searchwp_cro_settings` option; the pins are post meta `searchwp_cro_<md5(query . engine)>`):
  - **"star trek":** 9 Star Trek shows. Meilisearch puts all 9 in the Shows tab's top 10, and also finds *Starfleet Academy*, which wasn't pinned.
  - **"sabrina":** *Chilling Adventures of Sabrina*. Meilisearch ranks it first in Shows.
  - **"law and order"** and **"law & order":** the 5 Law & Order shows plus the actor Elisabeth Röhm. The shows fill Meilisearch's top 5 in Shows. Röhm stays findable in the Actors tab but is no longer first; that's accepted.
  - **"ncis":** set up with nothing pinned. NCIS ranks first anyway.

  The pins stay in SearchWP's data until the plugin is deleted, so there's nothing to clean up separately.
- [x] **Search redirects**: **dropped, nothing to port** (Mika, 2026-11-26). lwtv.local had 8, all exact match on every engine. Before cutover, check production's list with `wp option get searchwp_redirects --format=json` in case it has others.
  - **Four were shortcuts, and Meilisearch covers them:** "amster" (New Amsterdam), "For all" (For All Mankind), "The l" (The L Word; Meilisearch puts *Generation Q* first and the original just below), "nog star trek" (Deep Space Nine; Meilisearch lists every Star Trek show, Strange New Worlds first).
  - **Four sent spam searches to /about/:** "free underground porn films", "phim sex", "elon musk children", "陸カフェroom". They now get an ordinary results page. Search pages are `noindex`, so that's accepted.
- [ ] **Fallback when Meilisearch is down**: the simplest version drops back to native WordPress search. Needed before cutover, not before.
- [ ] **Production keys and indexes** built (`wp lwtv meili reindex --fresh` on production, with Mika's go-ahead).

## Cutover

Do it on staging first, then production, at low traffic.

1. Deploy the theme release that ships the Meilisearch search UI. Until SearchWP is deactivated, keep the SearchWP UI as the default (behind whatever switch that release provides) so the deploy itself changes nothing.
2. Run `wp lwtv meili status` for every index: document counts match published counts, settings match.
3. Switch the site to the Meilisearch UI.
4. Check by hand: header search (each scope), the sidebar search, `search.php` with a query, each FacetWP page with a search facet, a misspelled name, an alternate show name, and dark mode.
5. **Deactivate** (don't delete) the five SearchWP plugins: SearchWP, Custom Results Order, Live Ajax Search, Modal Search Form, Redirects. The theme guards every call (`function_exists( 'searchwp_modal_form_trigger' )`, `class_exists( 'SearchWP' )`), so it loses features rather than fataling. Load the home page, a search, and a FacetWP page right after.
6. Watch the PHP error log and the `meilisearch` debug topic for a day.

### Rollback

Reactivate the five plugins and switch the UI back. SearchWP's tables are untouched while it's deactivated; if posts changed in the meantime, SearchWP → Algorithm → rebuild the index.

## Cleanup (after two weeks with no rollback)

### Plugins and data

1. In SearchWP's settings, check whether it's set to delete its data on uninstall. If not, the tables stay behind after deleting the plugin.
2. Delete the five plugins.
3. Remove what's left, after a database backup:
   - Tables: `wp_searchwp_index`, `wp_searchwp_tokens`, `wp_searchwp_status`, `wp_searchwp_log`, `wp_searchwp_redirects_stats` (about 190 MB on the production copy).
   - Options: `option_name LIKE 'searchwp%' OR option_name LIKE '_searchwp%'` (about 40).
   - Post meta: `meta_key LIKE '%searchwp%'` (about 26 rows, mostly the pins).

### Code (one PR)

- Delete `searchwp-modal-form/`, `searchwp-live-ajax-search/`, `template-parts/header/searchbox-searchwp.php`, `scss/addons/_searchwp.scss` (first move its `.lwtv-search-scope` pill rules, and their dark-mode rules in `_colors-dark.scss`, into `_meili-search-page.scss`: the sidebar card still uses them) and its `meta.load-css` line in `style.scss`, and the `.searchwp-*` rules in `scss/partials/_colors-dark.scss`.
- Delete `plugins/lwtv-plugin/php/plugins/class-searchwp.php` and its `new SearchWP()` in `_components/class-plugins.php`.
- [x] **`Alt_Names` moved** to `LWTV\Meilisearch\Build` (`plugins/lwtv-plugin/php/meilisearch/build/class-alt-names.php`, test in `tests/unit/Meilisearch/AltNamesTest.php`), 2026-11-26. Deleting `searchwp-live-ajax-search/` no longer takes it with it.
- Simplify `header.php`, `template-parts/header/navbar.php` and `searchform.php`, which branch on SearchWP being present.
- Remove SearchWP entries from `.github/phpstan/phpstan-baseline.neon` (regenerate with `composer analyse:baseline`), `.github/low-risk.txt`, `.gemini/styleguide.md`, `docs/dependencies.md`, `docs/theme-structure.txt` and `docs/plugin-structure.txt`.
- Run `npm run build`, `composer lint`, `composer analyse`, `vendor/bin/phpunit`.

Then update this runbook's status line to "Retired" with the date.
