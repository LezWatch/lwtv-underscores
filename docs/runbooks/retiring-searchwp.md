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
| **Live Ajax Search** | Plugin + `searchwp-live-ajax-search/live-search-results.php` | Results as you type inside the modal, including the "also known as" line (`LWTV\Plugins\SearchWP\Build\Alt_Names`). |
| **Scope switch** | `plugins/lwtv-plugin/php/plugins/class-searchwp.php` (`maybe_switch_engine`), `searchform.php` | The sidebar search's `lwtv_scope` picks an engine. |
| **FacetWP search facets** | FacetWP settings (database, not code) | `search_shows`, `search_characters` and `search_actors` use engines `swp_shows`, `swp_characters`, `swp_actors`. **Easy to miss: nothing in the repo mentions them.** |
| **Custom Results Order** | Plugin | 22 posts pinned to the top for particular searches. |
| **Redirects** | Plugin | 1 search redirect (`searchwp_redirects` option). |
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
- [ ] **Full results page**: `search.php` needs results from Meilisearch (or a deliberate fallback to native WordPress search).
- [ ] **FacetWP search facets**: re-point `search_shows`, `search_characters` and `search_actors` at a non-SearchWP engine (FacetWP's built-in WordPress search, or a Meilisearch-backed engine through FacetWP's search-engine filter). Check every page that shows those facets.
- [ ] **Pinned results**: decide what happens to the 22 Custom Results Order pins. Meilisearch has search rules for promoting results, or they can be dropped if they're stale. Export the list first (it's in post meta: `SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE meta_key LIKE '%searchwp_cro%'`).
- [ ] **The search redirect**: move the one redirect in SearchWP → Redirects to nginx or a redirect plugin.
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

- Delete `searchwp-modal-form/`, `searchwp-live-ajax-search/`, `template-parts/header/searchbox-searchwp.php`, `scss/addons/_searchwp.scss` and its `meta.load-css` line in `style.scss`, and the `.searchwp-*` rules in `scss/partials/_colors-dark.scss`.
- Delete `plugins/lwtv-plugin/php/plugins/class-searchwp.php` and its `new SearchWP()` in `_components/class-plugins.php`.
- `plugins/lwtv-plugin/php/plugins/searchwp/build/class-alt-names.php` and `tests/unit/Plugins/AltNamesTest.php`: keep the logic if the new UI reuses it for "also known as", but move it out of `plugins/searchwp/`. Otherwise delete both and the `require_once` in `tests/bootstrap.php`.
- Simplify `header.php`, `template-parts/header/navbar.php` and `searchform.php`, which branch on SearchWP being present.
- Remove SearchWP entries from `.github/phpstan/phpstan-baseline.neon` (regenerate with `composer analyse:baseline`), `.github/low-risk.txt`, `.gemini/styleguide.md`, `docs/dependencies.md`, `docs/theme-structure.txt` and `docs/plugin-structure.txt`.
- Run `npm run build`, `composer lint`, `composer analyse`, `vendor/bin/phpunit`.

Then update this runbook's status line to "Retired" with the date.
